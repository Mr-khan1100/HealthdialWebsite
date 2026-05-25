<?php
// Include database configuration
require_once '../config.php';

// Rate limit: 10 enquiries per minute per IP
checkRateLimit(10, 60);

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['success' => false, 'message' => 'Invalid request method'], 405);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendResponse(['success' => false, 'message' => 'Invalid input data'], 400);
    exit;
}

// Debug: Log what we received
error_log('Enquiry submission request received');
error_log('Data received: ' . json_encode($input));

// Validate required field
if (empty($input['mobile_number'])) {
    sendResponse(['success' => false, 'message' => 'Mobile number is required'], 400);
    exit;
}

try {
    // Clean and validate mobile number
    $mobile_number = preg_replace('/\D/', '', $input['mobile_number']);
    
    if (strlen($mobile_number) !== 10) {
        sendResponse(['success' => false, 'message' => 'Mobile number must be 10 digits'], 400);
        exit;
    }

    // Check if number starts with 6,7,8,9 (Indian numbers)
    $first_digit = substr($mobile_number, 0, 1);
    if (!in_array($first_digit, ['6', '7', '8', '9'])) {
        sendResponse(['success' => false, 'message' => 'Invalid Indian mobile number'], 400);
        exit;
    }

    // Check if enquiry table exists, create if not
    $table_check = $conn->query("SHOW TABLES LIKE 'enquiry'");
    if ($table_check->num_rows === 0) {
        $create_table_sql = "
            CREATE TABLE enquiry (
                id INT AUTO_INCREMENT PRIMARY KEY,
                mobile_number VARCHAR(10) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_mobile_number (mobile_number)
            )
        ";
        if ($conn->query($create_table_sql)) {
            error_log('Created enquiry table');
        } else {
            throw new Exception("Failed to create enquiry table: " . $conn->error);
        }
    }

    // Check if mobile number already submitted recently (within 24 hours)
    $duplicate_check_query = "
        SELECT id, created_at FROM enquiry 
        WHERE mobile_number = ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at DESC 
        LIMIT 1
    ";
    $duplicate_stmt = $conn->prepare($duplicate_check_query);
    
    if (!$duplicate_stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $duplicate_stmt->bind_param("s", $mobile_number);
    $duplicate_stmt->execute();
    $duplicate_result = $duplicate_stmt->get_result();
    
    if ($duplicate_result->num_rows > 0) {
        $existing_data = $duplicate_result->fetch_assoc();
        $formatted_time = date('h:i A, d M Y', strtotime($existing_data['created_at']));
        sendResponse([
            'success' => false, 
            'message' => 'You have already submitted an enquiry today at ' . $formatted_time . '. Please try again tomorrow.',
            'last_submission' => $formatted_time
        ], 400);
        exit;
    }
    $duplicate_stmt->close();

    // Prepare and execute insert query
    $insert_sql = "INSERT INTO enquiry (mobile_number) VALUES (?)";
    $insert_stmt = $conn->prepare($insert_sql);
    
    if (!$insert_stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $insert_stmt->bind_param("s", $mobile_number);
    
    if (!$insert_stmt->execute()) {
        throw new Exception("Failed to insert enquiry: " . $insert_stmt->error);
    }
    
    $enquiry_id = $conn->insert_id;
    $insert_stmt->close();
    
    // Log successful submission
    error_log('Enquiry created successfully: ID=' . $enquiry_id . ', Mobile=' . $mobile_number);
    
    // Prepare response data
    $response_data = [
        'success' => true,
        'message' => 'Thank you! Your enquiry has been submitted successfully.',
        'data' => [
            'enquiry_id' => $enquiry_id,
            'mobile_number' => $mobile_number,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
    
    // Return success response
    sendResponse($response_data, 201);
    
} catch (Exception $e) {
    // Log error
    error_log('Error submitting enquiry: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Return error response
    sendResponse([
        'success' => false,
        'message' => 'An error occurred while submitting your enquiry. Please try again.'
    ], 500);
}
?>