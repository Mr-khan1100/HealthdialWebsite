<?php
require_once '../config.php';

header('Content-Type: application/json');

// Get listing ID from query parameters
$listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '1';
$offset = ($page - 1) * $limit;

if ($listing_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid listing ID']);
    exit;
}

try {
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM reviews WHERE listing_id = ? AND status = ?";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param("ii", $listing_id, $status);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_row = $count_result->fetch_assoc();
    $total_reviews = $total_row['total'];
    $total_pages = ceil($total_reviews / $limit);
    
    // Get reviews with user details
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
        WHERE r.listing_id = ? AND r.status = ?
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $reviews_stmt = $conn->prepare($reviews_query);
    $reviews_stmt->bind_param("isii", $listing_id, $status, $limit, $offset);
    $reviews_stmt->execute();
    $reviews_result = $reviews_stmt->get_result();
    
    $reviews = [];
    $colors = ['#4a148c', '#37474f', '#1565C0', '#2E7D32', '#D84315'];
    
    while($row = $reviews_result->fetch_assoc()) {
        $color_index = crc32($row['user_email']) % count($colors);
        
        $reviews[] = [
            'id' => $row['id'],
            'rating' => floatval($row['rating']),
            'review' => $row['review'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'user' => [
                'name' => $row['user_name'] ?: 'Anonymous',
                'email' => $row['user_email'],
                'initial' => strtoupper($row['user_initial'] ?: 'U'),
                'color' => $colors[$color_index]
            ]
        ];
    }
    
    // Get rating statistics
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
    $stats_stmt->bind_param("i", $listing_id);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    
    $rating_stats = [
        5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0
    ];
    $total_approved = 0;
    $average_rating = 0;
    $sum_rating = 0;
    
    while($stat = $stats_result->fetch_assoc()) {
        $rating = intval($stat['rating']);
        $count = intval($stat['count']);
        $rating_stats[$rating] = $count;
        $total_approved += $count;
        $sum_rating += ($rating * $count);
    }
    
    if ($total_approved > 0) {
        $average_rating = round($sum_rating / $total_approved, 1);
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'reviews' => $reviews,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_reviews' => $total_reviews,
                'per_page' => $limit
            ],
            'stats' => [
                'average_rating' => $average_rating,
                'total_reviews' => $total_approved,
                'distribution' => $rating_stats
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Error fetching reviews: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}

$conn->close();
?>