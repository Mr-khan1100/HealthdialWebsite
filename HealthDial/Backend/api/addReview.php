<?php
require_once '../config.php';

// Rate limit: 5 reviews per minute per IP
checkRateLimit(5, 60);

// Ensure we're handling JSON

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['success' => false, 'message' => 'Invalid request method'], 405);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendResponse(['success' => false, 'message' => 'Invalid input'], 400);
    exit;
}

// Check for guest or authenticated review
$user_id = 0;
$guest_name = '';

if (!empty($input['guest_name'])) {
    // Guest review from website
    $guest_name = htmlspecialchars(trim($input['guest_name']), ENT_QUOTES, 'UTF-8');
    $user_id = 0;
} else {
    // Authenticated review from app
    $user = authenticateUser();
    if (!$user) {
        sendResponse(['success' => false, 'message' => 'Authentication required'], 401);
        exit;
    }
    $user_id = $user['id'];
}

// Validate required fields
if (empty($input['listing_id']) || empty($input['rating']) || empty($input['review'])) {
    sendResponse(['success' => false, 'message' => 'All fields are required'], 400);
    exit;
}

$listing_id = intval($input['listing_id']);
$rating = floatval($input['rating']);
$review = htmlspecialchars(trim($input['review']), ENT_QUOTES, 'UTF-8');

// Validate rating
if ($rating < 1 || $rating > 5) {
    sendResponse(['success' => false, 'message' => 'Rating must be between 1 and 5'], 400);
    exit;
}

// Validate review length
if (strlen($review) < 10) {
    sendResponse(['success' => false, 'message' => 'Review must be at least 10 characters'], 400);
    exit;
}

try {
    // Check if listing exists and is active
    $listing_query = "SELECT id FROM listings WHERE id = ? AND status = 'approved'";
    $listing_stmt = $conn->prepare($listing_query);
    if (!$listing_stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $listing_stmt->bind_param("i", $listing_id);
    $listing_stmt->execute();
    $listing_result = $listing_stmt->get_result();
    
    if ($listing_result->num_rows === 0) {
        sendResponse(['success' => false, 'message' => 'Listing not found or inactive'], 404);
        exit;
    }
    
    // Check if authenticated user already reviewed this listing (skip for guests)
    if ($user_id > 0) {
        $existing_query = "SELECT id FROM reviews WHERE user_id = ? AND listing_id = ?";
        $existing_stmt = $conn->prepare($existing_query);
        if (!$existing_stmt) {
            throw new Exception("Database prepare failed: " . $conn->error);
        }
        
        $existing_stmt->bind_param("ii", $user_id, $listing_id);
        $existing_stmt->execute();
        $existing_result = $existing_stmt->get_result();
        
        if ($existing_result->num_rows > 0) {
            sendResponse(['success' => false, 'message' => 'You have already reviewed this listing'], 409);
            exit;
        }
    }
    
    // Insert the review with pending status
    $insert_query = "
        INSERT INTO reviews (user_id, listing_id, rating, review, guest_name, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
    ";
    
    $insert_stmt = $conn->prepare($insert_query);
    if (!$insert_stmt) {
        // Fallback: try without guest_name column (if column doesn't exist yet)
        $insert_query = "
            INSERT INTO reviews (user_id, listing_id, rating, review, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())
        ";
        $insert_stmt = $conn->prepare($insert_query);
        if (!$insert_stmt) {
            throw new Exception("Database prepare failed: " . $conn->error);
        }
        $insert_stmt->bind_param("iids", $user_id, $listing_id, $rating, $review);
    } else {
        $insert_stmt->bind_param("iidss", $user_id, $listing_id, $rating, $review, $guest_name);
    }
    
    if ($insert_stmt->execute()) {
        $review_id = $conn->insert_id;
        
        // Get the inserted review with user details
        $get_review_query = "
            SELECT 
                r.*,
                COALESCE(u.name, r.guest_name, 'Guest') as user_name,
                COALESCE(u.email, '') as user_email
            FROM reviews r
            LEFT JOIN users u ON r.user_id = u.id AND r.user_id > 0
            WHERE r.id = ?
        ";
        
        $get_review_stmt = $conn->prepare($get_review_query);
        if (!$get_review_stmt) {
            // If we can't fetch details, still return success
            sendResponse([
                'success' => true,
                'message' => 'Review submitted successfully. It will be visible after approval.',
                'data' => [
                    'id' => $review_id,
                    'rating' => $rating,
                    'review' => $review,
                    'status' => 'pending'
                ]
            ], 201);
            exit;
        }
        
        $get_review_stmt->bind_param("i", $review_id);
        $get_review_stmt->execute();
        $review_result = $get_review_stmt->get_result();
        
        if ($review_result->num_rows === 0) {
            sendResponse([
                'success' => true,
                'message' => 'Review submitted successfully. It will be visible after approval.',
                'data' => [
                    'id' => $review_id,
                    'rating' => $rating,
                    'review' => $review,
                    'status' => 'pending'
                ]
            ], 201);
            exit;
        }
        
        $review_data = $review_result->fetch_assoc();
        
        // Prepare response data
        $response_data = [
            'id' => $review_data['id'],
            'rating' => floatval($review_data['rating']),
            'review' => $review_data['review'],
            'status' => $review_data['status'],
            'created_at' => $review_data['created_at'],
            'user' => [
                'name' => $review_data['user_name'],
                'email' => $review_data['user_email']
            ]
        ];
        
        sendResponse([
            'success' => true,
            'message' => 'Review submitted successfully. It will be visible after approval.',
            'data' => $response_data
        ], 201);
        
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to submit review. Please try again.'], 500);
    }
    
} catch (Exception $e) {
    error_log('Error adding review: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'An error occurred. Please try again later.'], 500);
}

$conn->close();
?>