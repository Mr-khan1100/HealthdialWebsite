<?php
require_once '../config.php';

header('Content-Type: application/json');

// Get listing ID from query parameters
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$baseUrl = "https://healthdial.com/HealthDial/uploads/listings/";

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid listing ID'], 400);
    exit;
}

function formatImage($path, $isExternal, $baseUrl) {
    if (!$path) return null;
    $path = trim($path);
    return (strpos($path, 'http') !== false || $isExternal) ? $path : $baseUrl . $path;
}

try {
    // Get listing details with category and rating info
    $query = "
        SELECT 
            l.*,
            c.name as category_name,
            c.icon as category_icon,
            COALESCE(AVG(CASE WHEN r.status = 'approved' THEN r.rating END), 0) as avg_rating,
            COUNT(CASE WHEN r.status = 'approved' THEN 1 END) as review_count
        FROM listings l
        LEFT JOIN categories c ON l.category_id = c.id
        LEFT JOIN reviews r ON l.id = r.listing_id
        WHERE l.id = ? AND l.status = 'approved'
        GROUP BY l.id
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Listing not found'], 404);
        exit;
    }
    
    $row = $result->fetch_assoc();
    
    // Get primary image
    $image_query = "SELECT image_path, is_external_url 
        FROM listing_images 
        WHERE listing_id = ? AND is_primary = 1 
        LIMIT 1";
    $img_stmt = $conn->prepare($image_query);
    $img_stmt->bind_param("i", $id);
    $img_stmt->execute();
    $image_result = $img_stmt->get_result();
    $image_row = $image_result->fetch_assoc();
    
    $image_url = null;
    if ($image_row) {
        $image_url = formatImage(
            $image_row['image_path'],
            $image_row['is_external_url'],
            $baseUrl
        );
    } else {
        // Fallback to first image or default
        $fallback_query = "SELECT image_path, is_external_url 
        FROM listing_images 
        WHERE listing_id = ?
        LIMIT 1";
        $fallback_stmt = $conn->prepare($fallback_query);
        $fallback_stmt->bind_param("i", $id);
        $fallback_stmt->execute();
        $fallback_result = $fallback_stmt->get_result();
        $fallback_row = $fallback_result->fetch_assoc();
        
        if ($fallback_row) {
           $image_url = formatImage(
                $fallback_row['image_path'],
                $fallback_row['is_external_url'],
                $baseUrl
            );
        }
    }
    
    // Get all images
    $all_images_query = "
        SELECT image_path, is_primary, is_external_url 
        FROM listing_images 
        WHERE listing_id = ? 
        ORDER BY is_primary DESC
    ";
    $all_img_stmt = $conn->prepare($all_images_query);
    $all_img_stmt->bind_param("i", $id);
    $all_img_stmt->execute();
    $all_images_result = $all_img_stmt->get_result();
    
    $images = [];
    while ($img = $all_images_result->fetch_assoc()) {
        $images[] = [
            'url' => formatImage(
                $img['image_path'],
                $img['is_external_url'],
                $baseUrl
            ),
            'isPrimary' => $img['is_primary'] == 1
        ];
    }
    
    // Get review statistics
    $stats_query = "
        SELECT 
            rating,
            COUNT(*) as count
        FROM reviews 
        WHERE listing_id = ? AND status = 'approved'
        GROUP BY rating
        ORDER BY rating DESC
    ";
    $stats_stmt = $conn->prepare($stats_query);
    $stats_stmt->bind_param("i", $id);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    
    $rating_stats = [
        5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0
    ];
    $total_reviews = 0;
    
    while($stat = $stats_result->fetch_assoc()) {
        $rating = intval($stat['rating']);
        $rating_stats[$rating] = intval($stat['count']);
        $total_reviews += $stat['count'];
    }
    
    // Get approved reviews with user details (limit to recent 10)
    $reviews_query = "
        SELECT 
            r.id,
            r.rating,
            r.review,
            r.status,
            r.created_at,
            u.name as user_name,
            u.email as user_email,
            SUBSTRING(u.name, 1, 1) as user_initial
        FROM reviews r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.listing_id = ? AND r.status = 'approved'
        ORDER BY r.created_at DESC
        LIMIT 10
    ";
    
    $reviews_stmt = $conn->prepare($reviews_query);
    $reviews_stmt->bind_param("i", $id);
    $reviews_stmt->execute();
    $reviews_result = $reviews_stmt->get_result();
    
    $reviews = [];
    while($review = $reviews_result->fetch_assoc()) {
        // Generate random color for user avatar
        $colors = ['#4a148c', '#37474f', '#1565C0', '#2E7D32', '#D84315'];
        $color_index = crc32($review['user_email']) % count($colors);
        
        $reviews[] = [
            'id' => $review['id'],
            'rating' => floatval($review['rating']),
            'review' => $review['review'],
            'created_at' => $review['created_at'],
            'user' => [
                'name' => $review['user_name'] ?: 'Anonymous',
                'email' => $review['user_email'],
                'initial' => strtoupper($review['user_initial'] ?: 'U'),
                'color' => $colors[$color_index]
            ]
        ];
    }
    
    // Parse services if stored as JSON
    $services = [];
    if (!empty($row['services'])) {
        $services = json_decode($row['services'], true);
        if (!is_array($services)) {
            $services = explode(',', $row['services']);
        }
    }
    
    // Prepare the listing data
    $listing = [
        'id' => $row['id'],
        'name' => $row['name'],
        'category' => $row['category_name'] ?: $row['type'],
        'description' => $row['description'] ?: 'No description available',
        'address' => $row['address'],
        'latitude' => $row['latitude'] ? floatval($row['latitude']) : null,
        'longitude' => $row['longitude'] ? floatval($row['longitude']) : null,
        'rating' => round(floatval($row['avg_rating']), 1),
        'reviewCount' => intval($row['review_count']),
        'mobile' => $row['mobile'],
        'whatsapp' => $row['whatsapp'],
        'email' => $row['email'],
        'openTime' => $row['open_time'] ? date('h:i A', strtotime($row['open_time'])) : null,
        'closeTime' => $row['close_time'] ? date('h:i A', strtotime($row['close_time'])) : null,
        'is24x7' => $row['is_24x7'] == 1,
        'image' => $image_url,
        'images' => $images,
        'services' => $services,
        'reviewStats' => [
            'average' => round(floatval($row['avg_rating']), 1),
            'total' => $total_reviews,
            'distribution' => $rating_stats
        ],
        'reviews' => $reviews,
        'status' => $row['status'],
        'createdAt' => $row['created_at']
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $listing
    ]);
    
} catch (Exception $e) {
    error_log('Error fetching listing: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred'], 500);
}

$conn->close();
?>