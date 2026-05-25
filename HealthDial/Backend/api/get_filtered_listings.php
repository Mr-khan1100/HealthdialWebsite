<?php
require_once '../config.php';


/* ==============================
   HELPER FUNCTIONS
============================== */

function getIntParam($key, $default = 0) {
    return isset($_GET[$key]) ? intval($_GET[$key]) : $default;
}

function getFloatParam($key, $default = null) {
    return isset($_GET[$key]) ? floatval($_GET[$key]) : $default;
}

function getStringParam($key, $default = '') {
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

$baseUrl = "https://healthdial.com/HealthDial/uploads/listings/";

/* ==============================
   INPUT PARAMETERS
============================== */

$category_id = getIntParam('category_id');
$city        = getStringParam('city');
$latitude    = getFloatParam('lat');
$longitude   = getFloatParam('lng');
$page        = max(1, getIntParam('page', 1));
$limit       = max(1, min(100, getIntParam('limit', 20)));
$offset      = ($page - 1) * $limit;

$radius = isset($_GET['radius']) ? max(1, intval($_GET['radius'])) : 100; // Default 100km to cover districts

/* ==============================
   BASE CONDITIONS
============================== */

$conditions = [];
$params = [];
$types = '';

$conditions[] = "l.status = 'approved'";

/* ---- Category Filter ---- */
if ($category_id > 0) {
    $conditions[] = "l.category_id = ?";
    $params[] = $category_id;
    $types .= 'i';
}

/* ---- City/Location Text Filter (fuzzy — searches city and address) ---- */
if (!empty($city)) {
    $cityParam = '%' . $city . '%';
    $conditions[] = "(l.city LIKE ? OR l.address LIKE ?)";
    $params[] = $cityParam;
    $params[] = $cityParam;
    $types .= 'ss';
}

/* ---- Full-text Search Filter ---- */
$search = getStringParam('search');
if (!empty($search)) {
    $searchParam = '%' . $search . '%';
    $conditions[] = "(l.name LIKE ? OR l.description LIKE ? OR l.address LIKE ?)";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}

/* ---- Location Filter (GPS coordinates + radius) ---- */
$distanceSelect = "";
$havingClause = "";

if ($latitude !== null && $longitude !== null) {

    $distanceSelect = ",
        (6371 * acos(
            cos(radians(?)) * 
            cos(radians(l.latitude)) * 
            cos(radians(l.longitude) - radians(?)) + 
            sin(radians(?)) * 
            sin(radians(l.latitude))
        )) AS distance_km";

    array_unshift($params, $latitude, $longitude, $latitude);
    $types = 'ddd' . $types;

    // Only apply radius filter and lat/lng requirement if no city text filter is active
    // (city text filter already narrows results; radius would be too restrictive
    //  and requiring lat/lng would exclude listings without GPS coordinates)
    if (empty($city)) {
        $conditions[] = "l.latitude IS NOT NULL AND l.longitude IS NOT NULL";
        $havingClause = " HAVING distance_km <= {$radius}";
    }
}

/* ==============================
   MAIN QUERY
============================== */

$sql = "
SELECT 
    l.id,
    l.name,
    l.address,
    l.city,
    l.mobile,
    l.email,
    l.status,
    c.name AS category_name,
    c.icon AS category_icon,
    li.image_path AS image,
    li.is_external_url As isExternalUrl,
    COALESCE(AVG(r.rating), 0) AS avg_rating,
    COUNT(r.id) AS review_count
    $distanceSelect
FROM listings l
INNER JOIN categories c ON l.category_id = c.id
LEFT JOIN listing_images li 
    ON li.listing_id = l.id AND li.is_primary = 1
LEFT JOIN reviews r 
    ON r.listing_id = l.id
";

/* WHERE */
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

/* GROUP BY */
$sql .= " GROUP BY l.id";

/* HAVING */
$sql .= $havingClause;

/* ORDERING */
if ($latitude !== null && $longitude !== null) {
    $sql .= " ORDER BY distance_km ASC";
} else {
    $sql .= " ORDER BY avg_rating DESC, review_count DESC";
}

/* PAGINATION */
$sql .= " LIMIT ? OFFSET ?";
$types .= 'ii';
$params[] = $limit;
$params[] = $offset;

/* ==============================
   EXECUTE MAIN QUERY
============================== */

$stmt = $conn->prepare($sql);

if (!$stmt) {
    sendResponse(['success' => false, 'message' => 'Database error']);
    exit;
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

/* ==============================
   FORMAT RESULTS
============================== */

$listings = [];

while ($row = $result->fetch_assoc()) {

    $listing = [
        'id'          => $row['id'],
        'name'        => $row['name'],
        'category'    => $row['category_name'],
        'rating'      => round($row['avg_rating'], 1),
        'reviewCount' => $row['review_count'],
        'address'     => $row['address'],
        'city'        => $row['city'],
        'mobile'      => $row['mobile'],
        'email'       => $row['email'],
        'image' => trim($row['image'])
        ? (
            (strpos(trim($row['image']), 'http') !== false || $row['isExternalUrl'])
                ? trim($row['image']) 
                : $baseUrl . trim($row['image'])
          )
        : null,
        'status'      => $row['status']
    ];

    if (isset($row['distance_km'])) {
        $listing['distance'] = round($row['distance_km'], 2);
    }

    $listings[] = $listing;
}

/* ==============================
   COUNT QUERY
============================== */

$countSql = "
SELECT COUNT(DISTINCT l.id) AS total
FROM listings l
INNER JOIN categories c ON l.category_id = c.id
";

if (!empty($conditions)) {
    $countSql .= " WHERE " . implode(" AND ", $conditions);
}

$countStmt = $conn->prepare($countSql);

/* Remove lat,lng,lat and limit,offset */
if ($latitude !== null && $longitude !== null) {
    $countParams = array_slice($params, 3, -2);
    $countTypes = substr($types, 3, -2);
} else {
    $countParams = array_slice($params, 0, -2);
    $countTypes = substr($types, 0, -2);
}

if (!empty($countParams)) {
    $countStmt->bind_param($countTypes, ...$countParams);
}

$countStmt->execute();
$countResult = $countStmt->get_result();
$total = $countResult->fetch_assoc()['total'] ?? 0;

/* ==============================
   SPONSORED LISTINGS
============================== */

$sponsored = [];
$sponsoredSql = "
SELECT 
    l.id,
    l.name,
    l.address,
    l.city,
    l.mobile,
    l.email,
    l.status,
    c.name AS category_name,
    c.icon AS category_icon,
    li.image_path AS image,
    li.is_external_url AS isExternalUrl,
    COALESCE(AVG(r.rating), 0) AS avg_rating,
    COUNT(r.id) AS review_count,
    h.id AS highlight_id
FROM listing_highlights h
INNER JOIN listings l ON h.listing_id = l.id
INNER JOIN categories c ON l.category_id = c.id
LEFT JOIN listing_images li ON li.listing_id = l.id AND li.is_primary = 1
LEFT JOIN reviews r ON r.listing_id = l.id
WHERE h.is_active = 1 
  AND h.end_date > NOW()
  AND l.status = 'approved'
";

if ($category_id > 0) {
    $sponsoredSql .= " AND l.category_id = " . intval($category_id);
}

// Filter sponsored by target cities (set by admin when creating promotion)
// NULL or empty target_cities = show in ALL cities (backward compatible)
if (!empty($city)) {
    $escapedCity = $conn->real_escape_string($city);
    $sponsoredSql .= " AND (
        h.target_cities IS NULL 
        OR h.target_cities = '' 
        OR h.target_cities LIKE '%" . $escapedCity . "%'
    )";
}

$sponsoredSql .= " GROUP BY l.id ORDER BY h.created_at DESC LIMIT 5";

$sponsoredResult = $conn->query($sponsoredSql);
if ($sponsoredResult) {
    while ($row = $sponsoredResult->fetch_assoc()) {
        $sponsored[] = [
            'id'          => $row['id'],
            'name'        => $row['name'],
            'category'    => $row['category_name'],
            'rating'      => round($row['avg_rating'], 1),
            'reviewCount' => $row['review_count'],
            'address'     => $row['address'],
            'city'        => $row['city'],
            'mobile'      => $row['mobile'],
            'email'       => $row['email'],
            'image'       => trim($row['image'])
                ? (
                    (strpos(trim($row['image']), 'http') !== false || $row['isExternalUrl'])
                        ? trim($row['image']) 
                        : $baseUrl . trim($row['image'])
                  )
                : null,
            'status'      => $row['status'],
            'is_sponsored' => true
        ];
    }
} else {
    // Log the error but don't fail the entire request
    $sponsoredError = $conn->error;
    error_log("Sponsored listings query failed: " . $sponsoredError);
}

/* ==============================
   FINAL RESPONSE
============================== */

sendResponse([
    'success' => true,
    'data' => [
        'listings' => $listings,
        'sponsored' => $sponsored,
        'sponsored_count' => count($sponsored),
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => ceil($total / $limit)
        ],
        'filters' => [
            'category_id' => $category_id,
            'city' => $city,
            'hasLocation' => ($latitude !== null && $longitude !== null)
        ]
    ]
]);

$stmt->close();
$conn->close();
?>