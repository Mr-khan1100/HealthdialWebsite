<?php
require_once '../config.php';

$latitude = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$longitude = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
$radius = isset($_GET['radius']) ? floatval($_GET['radius']) : 10; // Default 10km radius

if (!$latitude || !$longitude) {
    sendResponse(['error' => 'Location coordinates required'], 400);
}

// Haversine formula for distance calculation
$query = "
    SELECT 
        l.*,
        c.name as category_name,
        c.icon as category_icon,
        COALESCE(AVG(r.rating), 0) as avg_rating,
        COUNT(r.id) as review_count,
        (6371 * acos(
            cos(radians($latitude)) * 
            cos(radians(l.latitude)) * 
            cos(radians(l.longitude) - radians($longitude)) + 
            sin(radians($latitude)) * 
            sin(radians(l.latitude))
        )) as distance_km
    FROM listings l
    LEFT JOIN categories c ON l.category_id = c.id
    LEFT JOIN reviews r ON l.id = r.listing_id
    WHERE l.status = 'approved' 
        AND l.latitude IS NOT NULL 
        AND l.longitude IS NOT NULL
    GROUP BY l.id
    HAVING distance_km <= $radius
    ORDER BY distance_km ASC
    LIMIT 20
";

$result = $conn->query($query);

$listings = [];
while($row = $result->fetch_assoc()) {
    // Get primary image
    $image_result = $conn->query("
        SELECT image_path 
        FROM listing_images 
        WHERE listing_id = {$row['id']} AND is_primary = 1 
        LIMIT 1
    ");
    $image = '';
    if($image_result->num_rows > 0) {
        $image_row = $image_result->fetch_assoc();
        $image = 'http://' . $_SERVER['HTTP_HOST'] . '/healthdial/uploads/listings/' . $image_row['image_path'];
    }
    
    $listings[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'category' => $row['category_name'],
        'description' => $row['description'],
        'address' => $row['address'],
        'rating' => round($row['avg_rating'], 1),
        'reviewCount' => $row['review_count'],
        'mobile' => $row['mobile'],
        'whatsapp' => $row['whatsapp'],
        'email' => $row['email'],
        'openTime' => $row['open_time'],
        'closeTime' => $row['close_time'],
        'is24x7' => $row['is_24x7'] == 1,
        'distance' => round($row['distance_km'], 2),
        'latitude' => $row['latitude'],
        'longitude' => $row['longitude'],
        'image' => $image
    ];
}

sendResponse([
    'success' => true,
    'data' => [
        'listings' => $listings,
        'count' => count($listings),
        'radius' => $radius,
        'userLocation' => [
            'latitude' => $latitude,
            'longitude' => $longitude
        ]
    ]
]);
?>