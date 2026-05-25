<?php
require_once 'config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    sendResponse(['error' => 'Invalid listing ID'], 400);
}

// Get listing details
$query = "
    SELECT 
        l.*,
        c.name as category_name,
        c.icon as category_icon,
        COALESCE(AVG(r.rating), 0) as avg_rating,
        COUNT(r.id) as review_count
    FROM listings l
    LEFT JOIN categories c ON l.category_id = c.id
    LEFT JOIN reviews r ON l.id = r.listing_id
    WHERE l.id = $id
    GROUP BY l.id,c.id
";

$result = $conn->query($query);

if ($result->num_rows === 0) {
    sendResponse(['error' => 'Listing not found'], 404);
}

$row = $result->fetch_assoc();

// Get images
$images_result = $conn->query("
    SELECT image_path, is_primary, is_external_url 
    FROM listing_images 
    WHERE listing_id = $id
    ORDER BY is_primary DESC
");
$images = [];
while($img = $images_result->fetch_assoc()) {
    $imgPath = trim($img['image_path']);
    $url = (strpos($imgPath, 'http') !== false || $img['is_external_url']) 
        ? $imgPath 
        : 'https://' . $_SERVER['HTTP_HOST'] . '/HealthDial/uploads/listings/' . $imgPath;
        
    $images[] = [
        'url' => $url,
        'isPrimary' => $img['is_primary'] == 1
    ];
}

// Get reviews with user details
$reviews_result = $conn->query("
    SELECT 
        r.*,
        COALESCE(u.name, r.guest_name) as reviewer_name,
        u.email as user_email
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id AND r.user_id > 0
    WHERE r.listing_id = $id AND r.status = 'approved'
    ORDER BY r.created_at DESC
    LIMIT 20
");
$reviews = [];
while($review = $reviews_result->fetch_assoc()) {
    $reviews[] = [
        'id' => $review['id'],
        'rating' => $review['rating'],
        'review' => $review['review'],
        'createdAt' => $review['created_at'],
        'user' => [
            'name' => $review['reviewer_name'] ?: 'User',
            'email' => $review['user_email'] ?? ''
        ]
    ];
}

// Prepare response
$listing = [
    'id' => $row['id'],
    'name' => $row['name'],
    'category' => $row['category_name'],
    'categoryIcon' => $row['category_icon'],
    'description' => $row['description'],
    'address' => $row['address'],
    'rating' => round($row['avg_rating'], 1),
    'reviewCount' => $row['review_count'],
    'mobile' => $row['mobile'],
    'whatsapp' => $row['whatsapp'],
    'email' => $row['email'],
    'openTime' => date('h:i A', strtotime($row['open_time'])),
    'closeTime' => date('h:i A', strtotime($row['close_time'])),
    'is24x7' => $row['is_24x7'] == 1,
    'latitude' => $row['latitude'],
    'longitude' => $row['longitude'],
    'images' => $images,
    'reviews' => $reviews,
    'status' => $row['status'],
    'createdAt' => $row['created_at'],
    'updatedAt' => $row['updated_at']
];

sendResponse([
    'success' => true,
    'data' => $listing
]);
?>