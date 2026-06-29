<?php
/**
 * Owner edit of a listing — saves changes from edit-listing.php.
 *
 * Only the logged-in, phone-verified OWNER of the listing may edit it. Updates
 * the core fields + per-day hours, and reconciles photos: existing images the
 * owner kept stay, removed ones are deleted, and newly uploaded ones are added.
 *
 * Request (JSON): {
 *   listing_id, category_id, name, description, address, city, latitude,
 *   longitude, mobile, whatsapp, email, is_24x7, open_time, close_time,
 *   opening_hours, kept_image_ids:[..], new_images:["data:..",..],
 *   primary:{type:'existing',id} | {type:'new',index}
 * }
 */
require_once 'includes/json_guard.php';
require_once 'includes/db.php';
require_once 'includes/seo.php';
require_once 'includes/user_auth.php';

header('Content-Type: application/json; charset=UTF-8');

function hd_edit_json($data, int $status = 200)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hd_edit_json(['success' => false, 'message' => 'Invalid request method'], 405);
}

$hdUser = hd_current_user();
if (!$hdUser) {
    hd_edit_json(['success' => false, 'message' => 'Please log in.', 'auth_required' => true], 401);
}
if (empty($hdUser['phone_verified'])) {
    hd_edit_json([
        'success'        => false,
        'message'        => 'Please verify your phone number before editing a listing.',
        'phone_required' => true,
        'redirect'       => 'profile.php?verify=required',
    ], 403);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    hd_edit_json(['success' => false, 'message' => 'Invalid input.'], 400);
}

$listing_id = intval($input['listing_id'] ?? 0);
if ($listing_id <= 0) {
    hd_edit_json(['success' => false, 'message' => 'Invalid listing.'], 400);
}

foreach (['category_id', 'name', 'description', 'address', 'mobile'] as $field) {
    if (empty($input[$field])) {
        hd_edit_json(['success' => false, 'message' => ucfirst($field) . ' is required'], 400);
    }
}

$conn = getDbConnection();
if (!$conn) {
    hd_edit_json(['success' => false, 'message' => 'Service temporarily unavailable. Please try again.'], 503);
}

// Ownership check — only the owner can edit.
$own = $conn->prepare("SELECT user_id FROM listings WHERE id = ? LIMIT 1");
$own->bind_param('i', $listing_id);
$own->execute();
$ownerRow = $own->get_result()->fetch_assoc();
$own->close();
if (!$ownerRow) {
    hd_edit_json(['success' => false, 'message' => 'Listing not found.'], 404);
}
if ((int) $ownerRow['user_id'] !== (int) $hdUser['id']) {
    hd_edit_json(['success' => false, 'message' => 'You can only edit your own listings.'], 403);
}

// Validate category.
$cat_id   = intval($input['category_id']);
$catStmt  = $conn->prepare("SELECT id FROM categories WHERE id = ? AND status = 1");
$catStmt->bind_param('i', $cat_id);
$catStmt->execute();
if ($catStmt->get_result()->num_rows === 0) {
    $catStmt->close();
    hd_edit_json(['success' => false, 'message' => 'Invalid or inactive category selected'], 400);
}
$catStmt->close();

// Sanitize fields (mirrors add-listing-submit.php).
$name        = trim($input['name']);
$description = trim($input['description']);
$address     = trim($input['address']);
$city        = !empty($input['city'])     ? trim($input['city'])  : '';
$latitude    = isset($input['latitude'])  && $input['latitude']  !== '' ? floatval($input['latitude'])  : 0.0;
$longitude   = isset($input['longitude']) && $input['longitude'] !== '' ? floatval($input['longitude']) : 0.0;
$mobile      = preg_replace('/[^\d+]/', '', trim($input['mobile']));
$whatsapp    = !empty($input['whatsapp']) ? preg_replace('/[^\d+]/', '', trim($input['whatsapp'])) : $mobile;
$email       = !empty($input['email'])    ? trim($input['email']) : '';
$is_24x7     = ($input['is_24x7'] == '1' || ($input['is_24x7'] ?? null) === true) ? 1 : 0;
$open_time   = $is_24x7 ? '00:00:00' : (!empty($input['open_time'])  ? $input['open_time']  : '09:00:00');
$close_time  = $is_24x7 ? '00:00:00' : (!empty($input['close_time']) ? $input['close_time'] : '18:00:00');

// Per-day opening hours (JSON). Self-heal the column.
$opening_hours_json = $is_24x7 ? null : hd_opening_hours_to_json($input['opening_hours'] ?? null);
$hasOpeningHoursCol = false;
$ohCol = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'listings' AND COLUMN_NAME = 'opening_hours'");
if ($ohCol && $ohCol->num_rows > 0) {
    $hasOpeningHoursCol = true;
} elseif (@$conn->query("ALTER TABLE listings ADD COLUMN opening_hours TEXT NULL")) {
    $hasOpeningHoursCol = true;
}

// --- Reconcile photos ----------------------------------------------------
$keptIds = array_values(array_filter(array_map('intval', (array) ($input['kept_image_ids'] ?? []))));
$newImgs = is_array($input['new_images'] ?? null) ? $input['new_images'] : [];

// Current images for this listing.
$current = [];
$cur = $conn->prepare("SELECT id, image_path, is_external_url FROM listing_images WHERE listing_id = ?");
$cur->bind_param('i', $listing_id);
$cur->execute();
$curRes = $cur->get_result();
while ($r = $curRes->fetch_assoc()) {
    $current[(int) $r['id']] = $r;
}
$cur->close();

// Keep only ids that actually belong to this listing.
$keptIds = array_values(array_filter($keptIds, function ($id) use ($current) {
    return isset($current[$id]);
}));

if (count($keptIds) === 0 && count($newImgs) === 0) {
    hd_edit_json(['success' => false, 'message' => 'A listing needs at least one photo.'], 400);
}

$upload_dir = __DIR__ . '/HealthDial/uploads/listings/';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0777, true);
}
$ext_map = [
    'image/jpeg' => 'jpg', 'image/jpg' => 'jpg',
    'image/png'  => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
];

$conn->begin_transaction();
try {
    // 1) Core fields.
    $sql = "UPDATE listings SET
                category_id = ?, name = ?, description = ?, address = ?, city = ?,
                latitude = ?, longitude = ?, mobile = ?, whatsapp = ?, email = ?,
                open_time = ?, close_time = ?, is_24x7 = ?, updated_at = NOW()
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('DB prepare error: ' . $conn->error);
    }
    $stmt->bind_param(
        'issssddssssssi',
        $cat_id, $name, $description, $address, $city,
        $latitude, $longitude, $mobile, $whatsapp, $email,
        $open_time, $close_time, $is_24x7, $listing_id
    );
    if (!$stmt->execute()) {
        throw new Exception('Update failed: ' . $stmt->error);
    }
    $stmt->close();

    // 2) Opening hours.
    if ($hasOpeningHoursCol) {
        $ohStmt = $conn->prepare("UPDATE listings SET opening_hours = ? WHERE id = ?");
        if ($ohStmt) {
            $ohStmt->bind_param('si', $opening_hours_json, $listing_id);
            $ohStmt->execute();
            $ohStmt->close();
        }
    }

    // 3) Delete removed images (DB rows + local files).
    foreach ($current as $id => $row) {
        if (in_array($id, $keptIds, true)) {
            continue;
        }
        $del = $conn->prepare("DELETE FROM listing_images WHERE id = ? AND listing_id = ?");
        $del->bind_param('ii', $id, $listing_id);
        $del->execute();
        $del->close();
        if (empty($row['is_external_url'])) {
            $path = $upload_dir . basename((string) $row['image_path']);
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    // 4) Add new images.
    $newIds = [];
    foreach ($newImgs as $img) {
        $data = is_array($img) ? ($img['data'] ?? '') : $img;
        if (!$data) {
            continue;
        }
        if (strpos($data, ',') !== false) {
            $data = explode(',', $data)[1];
        }
        $bytes = base64_decode($data, true);
        if ($bytes === false) {
            continue;
        }
        $info = @getimagesizefromstring($bytes);
        if (!$info) {
            continue;
        }
        $ext      = $ext_map[$info['mime']] ?? 'jpg';
        $filename = 'listing_' . $listing_id . '_' . uniqid() . '.' . $ext;
        if (file_put_contents($upload_dir . $filename, $bytes) === false) {
            continue;
        }
        $ins = $conn->prepare("INSERT INTO listing_images (listing_id, image_path, is_primary, created_at, is_external_url) VALUES (?, ?, 0, NOW(), 0)");
        $ins->bind_param('is', $listing_id, $filename);
        $ins->execute();
        $newIds[] = $conn->insert_id;
        $ins->close();
    }

    // 5) Resolve the cover (is_primary).
    $conn->query("UPDATE listing_images SET is_primary = 0 WHERE listing_id = " . $listing_id);
    $primary    = is_array($input['primary'] ?? null) ? $input['primary'] : [];
    $primaryId  = 0;
    if (($primary['type'] ?? '') === 'existing' && in_array((int) ($primary['id'] ?? 0), $keptIds, true)) {
        $primaryId = (int) $primary['id'];
    } elseif (($primary['type'] ?? '') === 'new') {
        $idx = (int) ($primary['index'] ?? -1);
        if ($idx >= 0 && isset($newIds[$idx])) {
            $primaryId = $newIds[$idx];
        }
    }
    if (!$primaryId) {
        $primaryId = $keptIds[0] ?? ($newIds[0] ?? 0);
    }
    if ($primaryId) {
        $pStmt = $conn->prepare("UPDATE listing_images SET is_primary = 1 WHERE id = ? AND listing_id = ?");
        $pStmt->bind_param('ii', $primaryId, $listing_id);
        $pStmt->execute();
        $pStmt->close();
    }

    $conn->commit();
    hd_edit_json([
        'success'  => true,
        'message'  => 'Listing updated successfully.',
        'redirect' => 'listing-detail.php?id=' . $listing_id,
    ]);
} catch (Exception $e) {
    $conn->rollback();
    error_log('edit-listing-submit error: ' . $e->getMessage());
    hd_edit_json(['success' => false, 'message' => 'Could not save changes. Please try again.'], 500);
}
