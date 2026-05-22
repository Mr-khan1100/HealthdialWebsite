<?php
// Review submission proxy
// Uses cURL server-to-server to avoid browser CORS/auth issues
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

require_once 'includes/db.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$listing_id = intval($input['listing_id'] ?? 0);
$rating = floatval($input['rating'] ?? 0);
$review = trim($input['review'] ?? '');
$guest_name = trim($input['guest_name'] ?? '');

// Validate locally first
if (!$listing_id || !$rating || !$review || !$guest_name) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be 1-5']);
    exit;
}
if (strlen($review) < 10) {
    echo json_encode(['success' => false, 'message' => 'Review must be at least 10 characters']);
    exit;
}

// Try direct DB first (works on deployed server)
$conn = getDbConnection();
if ($conn) {
    try {
        $stmt = $conn->prepare(
            "INSERT INTO reviews (user_id, listing_id, rating, review, guest_name, status, created_at, updated_at)
             VALUES (0, ?, ?, ?, ?, 'pending', NOW(), NOW())"
        );
        if (!$stmt) {
            // Try without guest_name column
            $stmt = $conn->prepare(
                "INSERT INTO reviews (user_id, listing_id, rating, review, status, created_at, updated_at)
                 VALUES (0, ?, ?, ?, 'pending', NOW(), NOW())"
            );
            if ($stmt) {
                $stmt->bind_param("ids", $listing_id, $rating, $review);
            }
        } else {
            $stmt->bind_param("idss", $listing_id, $rating, $review, $guest_name);
        }
        
        if ($stmt && $stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Review submitted successfully!',
                'data' => ['id' => $conn->insert_id]
            ]);
            $conn->close();
            exit;
        }
    } catch (Exception $e) {
        // DB failed, fall through to cURL
    }
    $conn->close();
}

// Fallback: POST via cURL to remote API
$apiUrl = API_BASE . 'addReview.php';
$postData = json_encode([
    'listing_id' => $listing_id,
    'rating' => $rating,
    'review' => $review,
    'guest_name' => $guest_name
]);

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postData)
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_USERAGENT => 'HealthDial/1.0'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['success' => false, 'message' => 'Connection error: ' . $curlError]);
    exit;
}

// Parse response (handle concatenated JSON from backend)
if ($response && strpos($response, '}{') !== false) {
    $parts = explode('}{', $response);
    $response = '{' . end($parts);
}

$result = json_decode($response, true);
if ($result) {
    echo json_encode($result);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'API error (HTTP ' . $httpCode . '). Your review has been noted.',
        'debug_response' => substr($response, 0, 200)
    ]);
}
?>
