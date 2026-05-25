<?php
require_once '../config.php';

// Apply rate limiting
checkRateLimit(30, 60); // 30 requests per minute for search

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$latitude = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$longitude = isset($_GET['lng']) ? floatval($_GET['lng']) : null;

if (empty($search)) {
    sendResponse(['error' => 'Search query is required'], 400);
}

// Limit search length to prevent abuse
if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

// Build query using prepared statements
$params = [];
$types = '';

$selectFields = "
    SELECT 
        l.id, l.name, l.description, l.address, l.city, l.mobile,
        c.name as category_name,
        c.icon as category_icon,
        COALESCE(AVG(r.rating), 0) as avg_rating,
        COUNT(r.id) as review_count";

if ($latitude !== null && $longitude !== null) {
    $selectFields .= ",
        (6371 * acos(
            cos(radians(?)) * 
            cos(radians(l.latitude)) * 
            cos(radians(l.longitude) - radians(?)) + 
            sin(radians(?)) * 
            sin(radians(l.latitude))
        )) as distance_km";
    $params[] = $latitude;
    $params[] = $longitude;
    $params[] = $latitude;
    $types .= 'ddd';
}

$query = $selectFields . "
    FROM listings l
    LEFT JOIN categories c ON l.category_id = c.id
    LEFT JOIN reviews r ON l.id = r.listing_id
    WHERE l.status = 'approved' 
    AND (l.name LIKE ? 
         OR l.description LIKE ? 
         OR l.address LIKE ?
         OR c.name LIKE ?)";

$searchParam = '%' . $search . '%';
$params[] = $searchParam;
$params[] = $searchParam;
$params[] = $searchParam;
$params[] = $searchParam;
$types .= 'ssss';

if (!empty($category)) {
    $query .= " AND c.name LIKE ?";
    $catParam = '%' . $category . '%';
    $params[] = $catParam;
    $types .= 's';
}

$query .= " GROUP BY l.id";

if ($latitude !== null && $longitude !== null) {
    $query .= " ORDER BY distance_km ASC";
} else {
    $query .= " ORDER BY avg_rating DESC";
}

$query .= " LIMIT 20";

$stmt = $conn->prepare($query);
if (!$stmt) {
    sendResponse(['success' => false, 'message' => 'Database error'], 500);
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$baseUrl = "https://healthdial.com/HealthDial/uploads/listings/";

$results = [];
while($row = $result->fetch_assoc()) {
    // Get primary image using prepared statement
    $imgStmt = $conn->prepare("
        SELECT image_path, is_external_url 
        FROM listing_images 
        WHERE listing_id = ? AND is_primary = 1 
        LIMIT 1
    ");
    $imgStmt->bind_param("i", $row['id']);
    $imgStmt->execute();
    $imgResult = $imgStmt->get_result();
    
    $image = null;
    if ($imgResult->num_rows > 0) {
        $imgRow = $imgResult->fetch_assoc();
        $imgPath = trim($imgRow['image_path']);
        if ($imgPath) {
            $image = (strpos($imgPath, 'http') !== false || $imgRow['is_external_url'])
                ? $imgPath
                : $baseUrl . $imgPath;
        }
    }
    $imgStmt->close();
    
    $item = [
        'id' => $row['id'],
        'name' => $row['name'],
        'category' => $row['category_name'],
        'categoryIcon' => $row['category_icon'],
        'description' => $row['description'],
        'address' => $row['address'],
        'city' => $row['city'] ?? '',
        'rating' => round($row['avg_rating'], 1),
        'reviewCount' => $row['review_count'],
        'mobile' => $row['mobile'],
        'image' => $image
    ];
    
    if (isset($row['distance_km'])) {
        $item['distance'] = round($row['distance_km'], 2);
    }
    
    $results[] = $item;
}

$stmt->close();

sendResponse([
    'success' => true,
    'data' => [
        'results' => $results,
        'total' => count($results)
    ]
]);
?>