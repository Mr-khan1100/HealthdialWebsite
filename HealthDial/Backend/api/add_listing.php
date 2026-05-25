<?php
require_once '../config.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['success' => false, 'message' => 'Invalid request method'], 405);
    exit;
}

// Authenticate user
$user = authenticateUser();

if (!$user) {
    sendResponse(['success' => false, 'message' => 'Authentication required'], 401);
    exit;
}

$user_id = $user['id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendResponse(['success' => false, 'message' => 'Invalid input data'], 400);
    exit;
}

// Debug: Log what we received
error_log('Add listing request received for user: ' . $user_id);
error_log('Data keys: ' . json_encode(array_keys($input)));

// Validate required fields
$required_fields = ['category_id', 'name', 'description', 'address', 'mobile'];
foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        sendResponse(['success' => false, 'message' => "Field '$field' is required"], 400);
        exit;
    }
}

// Validate images
if (empty($input['images']) || !is_array($input['images'])) {
    sendResponse(['success' => false, 'message' => 'At least one image is required'], 400);
    exit;
}

try {
    // Validate category exists and is active (status = 1)
    $category_id = intval($input['category_id']);
    $category_query = "SELECT id FROM categories WHERE id = ? AND status = 1";
    $category_stmt = $conn->prepare($category_query);
    
    if (!$category_stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $category_stmt->bind_param("i", $category_id);
    $category_stmt->execute();
    $category_result = $category_stmt->get_result();
    
    if ($category_result->num_rows === 0) {
        sendResponse(['success' => false, 'message' => 'Invalid or inactive category'], 400);
        exit;
    }
    $category_stmt->close();
    
    // Prepare listing data
    $name = trim($conn->real_escape_string($input['name']));
    $description = trim($conn->real_escape_string($input['description']));
    $address = trim($conn->real_escape_string($input['address']));
    $latitude = isset($input['latitude']) && $input['latitude'] !== '' ? floatval($input['latitude']) : 0.0;
    $longitude = isset($input['longitude']) && $input['longitude'] !== '' ? floatval($input['longitude']) : 0.0;
    $mobile = trim($conn->real_escape_string($input['mobile']));
    $whatsapp = isset($input['whatsapp']) && $input['whatsapp'] !== '' ? trim($conn->real_escape_string($input['whatsapp'])) : $mobile;
    $email = isset($input['email']) && $input['email'] !== '' ? trim($conn->real_escape_string($input['email'])) : '';
    $open_time = isset($input['open_time']) && $input['open_time'] !== '' ? $input['open_time'] : '09:00:00';
    $close_time = isset($input['close_time']) && $input['close_time'] !== '' ? $input['close_time'] : '18:00:00';
    $is_24x7 = isset($input['is_24x7']) && $input['is_24x7'] == '1' ? 1 : 0;
    $status = 'pending';
    
    // Start transaction
    $conn->begin_transaction();
    
    // Insert listing
    $insert_sql = "
        INSERT INTO listings (
            user_id, category_id, name, description, address, 
            latitude, longitude, mobile, whatsapp, email,
            open_time, close_time, is_24x7, status, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ";
    
    $insert_stmt = $conn->prepare($insert_sql);
    
    if (!$insert_stmt) {
        throw new Exception("Database prepare failed: " . $insert_stmt->error);
    }
    
    // Bind parameters
    $insert_stmt->bind_param(
        "iissddssssssis",
        $user_id,
        $category_id,
        $name,
        $description,
        $address,
        $latitude,
        $longitude,
        $mobile,
        $whatsapp,
        $email,
        $open_time,
        $close_time,
        $is_24x7,
        $status
    );
    
    if (!$insert_stmt->execute()) {
        $conn->rollback();
        throw new Exception("Failed to insert listing: " . $insert_stmt->error);
    }
    
    $listing_id = $conn->insert_id;
    $insert_stmt->close();
    
    // Handle image uploads from base64
    $upload_dir = '../../uploads/listings/';
    $uploaded_images = [];
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            $conn->rollback();
            sendResponse(['success' => false, 'message' => 'Failed to create upload directory'], 500);
            exit;
        }
    }
    
    // Process images
    $images = $input['images'];
    $image_count = count($images);
    error_log("Processing $image_count images");
    
    // Debug: Log first image structure
    if ($image_count > 0) {
        error_log("First image structure: " . json_encode(array_keys($images[0])));
        error_log("First image data sample: " . substr($images[0]['data'] ?? '', 0, 100) . "...");
    }
    
    for ($i = 0; $i < $image_count; $i++) {
        $image = $images[$i];
        
        // Debug each image
        error_log("Processing image $i: " . json_encode(array_keys($image)));
        
        // Check for image data - frontend sends 'data' not 'base64'
        if (!isset($image['data']) || empty($image['data'])) {
            error_log("Image $i has no 'data' field. Available keys: " . json_encode(array_keys($image)));
            continue;
        }
        
        // Determine if this is primary image
        $is_primary = isset($image['is_primary']) && $image['is_primary'] == '1' ? 1 : ($i === 0 ? 1 : 0);
        
        // Get filename
        $filename = isset($image['name']) ? $image['name'] : uniqid('listing_' . $listing_id . '_', true) . '.jpg';
        
        // Get base64 data - it's in 'data' field from frontend
        $base64_data = $image['data'];
        
        // Clean base64 data (remove data:image/jpeg;base64, prefix if present)
        if (strpos($base64_data, ',') !== false) {
            $base64_data = explode(',', $base64_data)[1];
        }
        
        // Decode base64
        $image_data = base64_decode($base64_data, true); // Use strict mode
        if ($image_data === false) {
            error_log("Failed to decode base64 for image $i. Base64 length: " . strlen($base64_data));
            continue;
        }
        
        // Verify it's a valid image by checking first few bytes
        $image_info = getimagesizefromstring($image_data);
        if ($image_info === false) {
            error_log("Invalid image data for image $i");
            continue;
        }
        
        // Determine file extension from MIME type
        $mime_type = $image_info['mime'];
        $mime_to_ext = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        $file_ext = $mime_to_ext[$mime_type] ?? 'jpg';
        
        // Generate unique filename
        $unique_filename = uniqid('listing_' . $listing_id . '_', true) . '.' . $file_ext;
        $filepath = $upload_dir . $unique_filename;
        
        // Save image file
        $bytes_written = file_put_contents($filepath, $image_data);
        if ($bytes_written !== false) {
            error_log("Saved image $i: $unique_filename ($bytes_written bytes)");
            
            // Insert image record - FIXED: removed sort_order from bind_param if not in query
            $image_sql = "
                INSERT INTO listing_images 
                (listing_id, image_path, is_primary, created_at, is_external_url) 
                VALUES (?, ?, ?, NOW(), 0)
            ";
            
            $image_stmt = $conn->prepare($image_sql);
            
            if (!$image_stmt) {
                error_log("Failed to prepare image statement: " . $conn->error);
                continue;
            }
            
            // FIXED: Bind only 3 parameters since SQL has 3 placeholders
            $image_stmt->bind_param("isi", $listing_id, $unique_filename, $is_primary);
            
            if ($image_stmt->execute()) {
                $uploaded_images[] = [
                    'filename' => $unique_filename,
                    'is_primary' => $is_primary,
                    'original_name' => $filename
                ];
                error_log("Successfully saved image record: $unique_filename");
            } else {
                error_log("Failed to insert image record: " . $image_stmt->error);
            }
            
            $image_stmt->close();
        } else {
            error_log("Failed to save image file: $filepath. Check permissions.");
            error_log("Upload directory: " . realpath($upload_dir));
            error_log("Directory writable: " . (is_writable($upload_dir) ? 'yes' : 'no'));
        }
    }
    
    // Check if at least one image was uploaded
    if (empty($uploaded_images)) {
        $conn->rollback();
        sendResponse([
            'success' => false, 
            'message' => 'Failed to upload images. Please check image format and try again.',
            'debug' => [
                'images_received' => $image_count,
                'uploaded_count' => count($uploaded_images),
                'upload_dir' => $upload_dir,
                'dir_exists' => file_exists($upload_dir) ? 'yes' : 'no',
                'dir_writable' => is_writable($upload_dir) ? 'yes' : 'no',
                'dir_realpath' => realpath($upload_dir)
            ]
        ], 400);
        exit;
    }
    
    // Commit transaction
    $conn->commit();
    
    // Send notification to admin
    sendAdminNotification($listing_id, $name, $user, $conn);
    
    // Prepare response
    $response_data = [
        'listing_id' => $listing_id,
        'name' => $name,
        'status' => $status,
        'images_count' => count($uploaded_images),
        'message' => 'Listing submitted successfully. It will be visible after approval.'
    ];
    
    error_log('Listing created successfully: ' . $listing_id);
    // Fetch popup config from settings
    $popupConfig = [];
    $popupKeys = ['popup_title', 'popup_message', 'popup_image', 'popup_button_text', 'popup_redirect_url'];
    $placeholders = implode(',', array_fill(0, count($popupKeys), '?'));
    $popupStmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
    $popupStmt->bind_param(str_repeat('s', count($popupKeys)), ...$popupKeys);
    $popupStmt->execute();
    $popupResult = $popupStmt->get_result();
    while ($row = $popupResult->fetch_assoc()) {
        $key = str_replace('popup_', '', $row['setting_key']);
        $popupConfig[$key] = $row['setting_value'];
    }
    $popupStmt->close();
    
    sendResponse([
        'success' => true,
        'message' => 'Listing submitted successfully. It will be visible after approval.',
        'data' => $response_data,
        'popup' => [
            'title' => $popupConfig['title'] ?? 'Listing Submitted!',
            'message' => $popupConfig['message'] ?? 'Your listing has been submitted for review.',
            'image' => $popupConfig['image'] ?? '',
            'button_text' => $popupConfig['button_text'] ?? 'OK',
            'redirect_url' => $popupConfig['redirect_url'] ?? '',
        ]
    ], 201);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->in_transaction) {
        $conn->rollback();
    }
    error_log('Error adding listing: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    sendResponse([
        'success' => false,
        'message' => 'Server error occurred: ' . $e->getMessage()
    ], 500);
}

// Helper function to send admin notification
function sendAdminNotification($listing_id, $listing_name, $user, $conn) {
    try {
        // Check if admin_notifications table exists, create if not
        $table_check = $conn->query("SHOW TABLES LIKE 'admin_notifications'");
        if ($table_check->num_rows === 0) {
            $create_table_sql = "
                CREATE TABLE admin_notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    type VARCHAR(50) DEFAULT 'new_listing',
                    status ENUM('unread', 'read') DEFAULT 'unread',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ";
            $conn->query($create_table_sql);
        }
        
        // Insert notification
        $notification_sql = "
            INSERT INTO admin_notifications 
            (title, message, type, status, created_at) 
            VALUES (?, ?, 'new_listing', 'unread', NOW())
        ";
        
        $notification_stmt = $conn->prepare($notification_sql);
        
        if (!$notification_stmt) {
            return;
        }
        
        $notification_title = "New Listing Submission";
        $notification_message = "A new listing has been submitted:\n\n";
        $notification_message .= "Listing ID: " . $listing_id . "\n";
        $notification_message .= "Listing Name: " . $listing_name . "\n";
        $notification_message .= "User: " . $user['name'] . " (" . $user['email'] . ")\n";
        $notification_message .= "Please review it in the admin panel.";
        
        $notification_stmt->bind_param("ss", $notification_title, $notification_message);
        $notification_stmt->execute();
        $notification_stmt->close();
        
    } catch (Exception $e) {
        error_log('Notification error: ' . $e->getMessage());
    }
}
?>