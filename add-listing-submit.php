<?php
require_once 'includes/db.php';
require_once 'includes/seo.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

function sendJson($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    sendJson(['success' => false, 'message' => 'Invalid JSON input'], 400);
}

// Validate required fields
foreach (['category_id', 'name', 'description', 'address', 'mobile'] as $field) {
    if (empty($input[$field])) {
        sendJson(['success' => false, 'message' => ucfirst($field) . ' is required'], 400);
    }
}
if (empty($input['images']) || !is_array($input['images'])) {
    sendJson(['success' => false, 'message' => 'At least one photo is required'], 400);
}

$conn = getDbConnection();
if (!$conn) {
    sendJson(['success' => false, 'message' => 'Database connection failed. Please try again later.'], 503);
}

// Validate category
$cat_id = intval($input['category_id']);
$catStmt = $conn->prepare("SELECT id FROM categories WHERE id = ? AND status = 1");
$catStmt->bind_param("i", $cat_id);
$catStmt->execute();
if ($catStmt->get_result()->num_rows === 0) {
    sendJson(['success' => false, 'message' => 'Invalid or inactive category selected'], 400);
}
$catStmt->close();

// Sanitize fields
$name        = trim($input['name']);
$description = trim($input['description']);
$address     = trim($input['address']);
$city        = !empty($input['city'])     ? trim($input['city'])  : '';
$latitude    = isset($input['latitude'])  && $input['latitude']  !== '' ? floatval($input['latitude'])  : 0.0;
$longitude   = isset($input['longitude']) && $input['longitude'] !== '' ? floatval($input['longitude']) : 0.0;
$mobile      = preg_replace('/[^\d+]/', '', trim($input['mobile']));
$whatsapp    = !empty($input['whatsapp']) ? preg_replace('/[^\d+]/', '', trim($input['whatsapp'])) : $mobile;
$email       = !empty($input['email'])    ? trim($input['email']) : '';
$is_24x7     = ($input['is_24x7'] == '1' || $input['is_24x7'] === true) ? 1 : 0;
$open_time   = $is_24x7 ? '00:00:00' : (!empty($input['open_time'])  ? $input['open_time']  : '09:00:00');
$close_time  = $is_24x7 ? '00:00:00' : (!empty($input['close_time']) ? $input['close_time'] : '18:00:00');
$user_id     = 0; // Guest — no auth required
$status      = 'approved';

// Block duplicate submissions: same name + phone + location already listed
$dupId = hd_find_duplicate_listing($conn, $name, $mobile, $latitude, $longitude);
if ($dupId) {
    sendJson(['success' => false, 'message' => 'This business is already listed with the same name, phone number and location.'], 409);
}

$conn->begin_transaction();

try {
    $sql = "INSERT INTO listings
        (user_id, category_id, name, description, address, city, latitude, longitude,
         mobile, whatsapp, email, open_time, close_time, is_24x7, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("DB prepare error: " . $conn->error);

    $stmt->bind_param(
        "iissssddsssssis",
        $user_id, $cat_id, $name, $description, $address, $city,
        $latitude, $longitude, $mobile, $whatsapp, $email,
        $open_time, $close_time, $is_24x7, $status
    );
    if (!$stmt->execute()) throw new Exception("Insert failed: " . $stmt->error);

    $listing_id = $conn->insert_id;
    $stmt->close();

    // Auto-generate slug, city_slug, category_slug (non-fatal if columns don't exist yet)
    try {
        if (hd_db_has_column($conn, 'listings', 'slug') &&
            hd_db_has_column($conn, 'listings', 'city_slug') &&
            hd_db_has_column($conn, 'listings', 'category_slug')) {

            $cnStmt = $conn->prepare("SELECT name FROM categories WHERE id = ? LIMIT 1");
            $cnStmt->bind_param('i', $cat_id);
            $cnStmt->execute();
            $cnRow     = $cnStmt->get_result()->fetch_assoc();
            $cnStmt->close();
            $cat_name_for_slug = $cnRow['name'] ?? 'medical';

            $slug      = hd_listing_slug_from_parts($name, $address, $city, $listing_id, true);
            $city_slug = hd_city_slug($city);
            $cat_slug  = hd_slugify($cat_name_for_slug, 'medical');

            $slugStmt = $conn->prepare("UPDATE listings SET slug = ?, city_slug = ?, category_slug = ? WHERE id = ?");
            $slugStmt->bind_param('sssi', $slug, $city_slug, $cat_slug, $listing_id);
            $slugStmt->execute();
            $slugStmt->close();
        }
    } catch (Exception $slugEx) {
        error_log('Slug gen failed for listing ' . $listing_id . ': ' . $slugEx->getMessage());
    }

    // Handle image uploads (base64)
    $upload_dir = __DIR__ . '/HealthDial/uploads/listings/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $saved = 0;
    $ext_map = [
        'image/jpeg' => 'jpg', 'image/jpg' => 'jpg',
        'image/png'  => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'
    ];

    foreach ($input['images'] as $i => $img) {
        if (empty($img['data'])) continue;

        $b64 = $img['data'];
        if (strpos($b64, ',') !== false) {
            $b64 = explode(',', $b64)[1];
        }

        $bytes = base64_decode($b64, true);
        if ($bytes === false) continue;

        $info = @getimagesizefromstring($bytes);
        if (!$info) continue;

        $ext      = $ext_map[$info['mime']] ?? 'jpg';
        $filename = 'listing_' . $listing_id . '_' . uniqid() . '.' . $ext;
        $filepath = $upload_dir . $filename;

        if (file_put_contents($filepath, $bytes) === false) continue;

        $is_primary = ($i === 0) ? 1 : 0;
        $imgSql     = "INSERT INTO listing_images (listing_id, image_path, is_primary, created_at, is_external_url) VALUES (?, ?, ?, NOW(), 0)";
        $imgStmt    = $conn->prepare($imgSql);
        $imgStmt->bind_param("isi", $listing_id, $filename, $is_primary);
        $imgStmt->execute();
        $imgStmt->close();
        $saved++;
    }

    if ($saved === 0) {
        $conn->rollback();
        sendJson(['success' => false, 'message' => 'Could not save photos. Please use JPG, PNG, or WebP files under 5MB.'], 400);
    }

    $conn->commit();

    sendJson([
        'success' => true,
        'message' => 'Listing submitted! It will go live after our team reviews it (usually within 24 hours).',
        'data'    => ['listing_id' => $listing_id, 'name' => $name, 'status' => 'pending', 'images' => $saved]
    ], 201);

} catch (Exception $e) {
    $conn->rollback();
    error_log('add-listing-submit error: ' . $e->getMessage());
    sendJson(['success' => false, 'message' => 'Server error. Please try again.'], 500);
}
