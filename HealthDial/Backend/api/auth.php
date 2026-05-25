<?php
require_once '../config.php';

// Set additional headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Log request for debugging
    error_log("Auth API Request: " . $method . " - " . json_encode($_POST));
    
    switch ($method) {
        case 'POST':
            // Get input data
            $input = json_decode(file_get_contents('php://input'), true);
            
            // If json_decode fails, try form data
            if (!$input) {
                $input = $_POST;
            }
            
            $action = $input['action'] ?? '';
            
            // Log action
            error_log("Auth Action: " . $action);
            
            switch ($action) {
                case 'login':
                    // Mobile/Password login
                    $mobile = $input['mobile'] ?? '';
                    $password = $input['password'] ?? '';
                    
                    if (empty($mobile) || empty($password)) {
                        sendResponse(['success' => false, 'message' => 'Mobile and password are required'], 400);
                    }
                    
                    // Validate mobile format
                    if (!preg_match('/^\d{10,15}$/', $mobile)) {
                        sendResponse(['success' => false, 'message' => 'Invalid mobile number format'], 400);
                    }
                    
                    $stmt = $conn->prepare("SELECT id, name, mobile, password FROM users WHERE mobile = ? AND status = 1");
                    if (!$stmt) {
                        sendResponse(['success' => false, 'message' => 'Database prepare failed: ' . $conn->error], 500);
                    }
                    
                    $stmt->bind_param("s", $mobile);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 0) {
                        sendResponse(['success' => false, 'message' => 'User not found'], 404);
                    }
                    
                    $user = $result->fetch_assoc();
                    
                    // Verify password
                    if (password_verify($password, $user['password'])) {
                        // Generate auth token
                        $token = bin2hex(random_bytes(32));
                        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
                        
                        // Store token
                        $tokenStmt = $conn->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                        if (!$tokenStmt) {
                            sendResponse(['success' => false, 'message' => 'Token creation failed: ' . $conn->error], 500);
                        }
                        
                        $tokenStmt->bind_param("iss", $user['id'], $token, $expires_at);
                        $tokenStmt->execute();
                        
                        // Remove password from response
                        unset($user['password']);
                        
                        sendResponse([
                            'success' => true,
                            'message' => 'Login successful',
                            'token' => $token,
                            'user' => $user
                        ]);
                    } else {
                        sendResponse(['success' => false, 'message' => 'Invalid password'], 401);
                    }
                    break;
                    
                case 'register':
                    // Register new user
                    $name = $input['name'] ?? '';
                    $mobile = $input['mobile'] ?? '';
                    $email = $input['email'] ?? '';
                    $password = $input['password'] ?? '';
                    $state = $input['state'] ?? '';
                    
                    if (empty($mobile) || empty($password) || empty($name) || empty($state)) {
                        sendResponse(['success' => false, 'message' => 'Name, mobile, state and password are required'], 400);
                    }
                    
                    // Validate mobile format
                    if (!preg_match('/^\d{10,15}$/', $mobile)) {
                        sendResponse(['success' => false, 'message' => 'Invalid mobile number format'], 400);
                    }
                    
                    if (strlen($password) < 6) {
                        sendResponse(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
                    }

                    // Validate email format if provided
                    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        sendResponse(['success' => false, 'message' => 'Invalid email address format'], 400);
                    }
                    
                    // Check if mobile already exists
                    $checkStmt = $conn->prepare("SELECT id FROM users WHERE mobile = ?");
                    if (!$checkStmt) {
                        sendResponse(['success' => false, 'message' => 'Database check failed: ' . $conn->error], 500);
                    }
                    
                    $checkStmt->bind_param("s", $mobile);
                    $checkStmt->execute();
                    
                    if ($checkStmt->get_result()->num_rows > 0) {
                        sendResponse(['success' => false, 'message' => 'Mobile number already registered'], 409);
                    }
                    
                    // Hash password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $conn->prepare("INSERT INTO users (name, mobile, email, state, password, status) VALUES (?, ?, ?, ?, ?, 1)");
                    if (!$stmt) {
                        sendResponse(['success' => false, 'message' => 'Database prepare failed: ' . $conn->error], 500);
                    }

                    $emailVal = !empty($email) ? $email : null;
                    $stmt->bind_param("sssss", $name, $mobile, $emailVal, $state, $hashedPassword);
                    
                    if ($stmt->execute()) {
                        $userId = $stmt->insert_id;
                        
                        // Generate auth token
                        $token = bin2hex(random_bytes(32));
                        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
                        
                        $tokenStmt = $conn->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                        if (!$tokenStmt) {
                            sendResponse(['success' => false, 'message' => 'Token creation failed: ' . $conn->error], 500);
                        }
                        
                        $tokenStmt->bind_param("iss", $userId, $token, $expires_at);
                        $tokenStmt->execute();
                        
                        sendResponse([
                            'success' => true,
                            'message' => 'Registration successful',
                            'token' => $token,
                            'user' => [
                                'id' => $userId,
                                'name' => $name,
                                'mobile' => $mobile,
                                'email' => $email,
                                'state' => $state
                                
                            ]
                        ]);
                    } else {
                        sendResponse(['success' => false, 'message' => 'Registration failed: ' . $conn->error], 500);
                    }
                    break;
                    
                case 'forgot_password':
                    // Send password reset
                    $mobile = $input['mobile'] ?? '';
                    
                    if (empty($mobile)) {
                        sendResponse(['success' => false, 'message' => 'Mobile number is required'], 400);
                    }
                    
                    // Check if user exists
                    $checkStmt = $conn->prepare("SELECT id FROM users WHERE mobile = ?");
                    if (!$checkStmt) {
                        sendResponse(['success' => false, 'message' => 'Database check failed: ' . $conn->error], 500);
                    }
                    
                    $checkStmt->bind_param("s", $mobile);
                    $checkStmt->execute();
                    
                    if ($checkStmt->get_result()->num_rows === 0) {
                        sendResponse(['success' => false, 'message' => 'Mobile number not registered'], 404);
                    }
                    
                    // Generate 6-digit OTP
                    $otp = rand(100000, 999999);
                    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    
                    $stmt = $conn->prepare("UPDATE users SET reset_otp = ?, reset_otp_expires = ? WHERE mobile = ?");
                    if (!$stmt) {
                        sendResponse(['success' => false, 'message' => 'Database update failed: ' . $conn->error], 500);
                    }
                    
                    $stmt->bind_param("sss", $otp, $expires_at, $mobile);
                    
                    if ($stmt->execute()) {
                        sendResponse([
                            'success' => true,
                            'message' => 'OTP sent to your mobile',
                            'reset_token' => $otp, // For compatibility
                            'mobile' => $mobile
                        ]);
                    } else {
                        sendResponse(['success' => false, 'message' => 'Failed to send OTP: ' . $conn->error], 500);
                    }
                    break;
                    
                case 'reset_password':
                    // Reset password with OTP
                    $otp = $input['token'] ?? '';
                    $newPassword = $input['new_password'] ?? '';
                    $mobile = $input['mobile'] ?? '';
                    
                    if (empty($otp) || empty($newPassword) || empty($mobile)) {
                        sendResponse(['success' => false, 'message' => 'OTP, new password and mobile are required'], 400);
                    }
                    
                    if (strlen($newPassword) < 6) {
                        sendResponse(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
                    }
                    
                    $stmt = $conn->prepare("SELECT id FROM users WHERE mobile = ? AND reset_otp = ? AND reset_otp_expires > NOW()");
                    if (!$stmt) {
                        sendResponse(['success' => false, 'message' => 'Database prepare failed: ' . $conn->error], 500);
                    }
                    
                    $stmt->bind_param("ss", $mobile, $otp);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 0) {
                        sendResponse(['success' => false, 'message' => 'Invalid or expired OTP'], 401);
                    }
                    
                    $user = $result->fetch_assoc();
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    
                    $updateStmt = $conn->prepare("UPDATE users SET password = ?, reset_otp = NULL, reset_otp_expires = NULL WHERE id = ?");
                    if (!$updateStmt) {
                        sendResponse(['success' => false, 'message' => 'Database update failed: ' . $conn->error], 500);
                    }
                    
                    $updateStmt->bind_param("si", $hashedPassword, $user['id']);
                    
                    if ($updateStmt->execute()) {
                        sendResponse(['success' => true, 'message' => 'Password reset successfully']);
                    } else {
                        sendResponse(['success' => false, 'message' => 'Failed to reset password: ' . $conn->error], 500);
                    }
                    break;
                    
                case 'google_login':
                    // Google Sign-In: login existing users, flag new users
                    $google_id = $input['google_id'] ?? '';
                    $name = $input['name'] ?? '';
                    $email = $input['email'] ?? '';
                    $profile_image = $input['profile_image'] ?? '';
                    
                    if (empty($google_id) || empty($email)) {
                        sendResponse(['success' => false, 'message' => 'Google ID and email are required'], 400);
                    }
                    
                    // Check if user exists by google_id or email
                    $checkStmt = $conn->prepare("SELECT id, name, mobile, email, state, profile_image, created_at FROM users WHERE (google_id = ? OR email = ?) AND status = 1");
                    if (!$checkStmt) {
                        sendResponse(['success' => false, 'message' => 'Database error'], 500);
                    }
                    
                    $checkStmt->bind_param("ss", $google_id, $email);
                    $checkStmt->execute();
                    $existingUser = $checkStmt->get_result();
                    
                    if ($existingUser->num_rows > 0) {
                        // User exists — login
                        $user = $existingUser->fetch_assoc();
                        
                        // Update google_id and profile_image if not set
                        $updateStmt = $conn->prepare("UPDATE users SET google_id = ?, profile_image = COALESCE(NULLIF(profile_image, ''), ?) WHERE id = ?");
                        if ($updateStmt) {
                            $updateStmt->bind_param("ssi", $google_id, $profile_image, $user['id']);
                            $updateStmt->execute();
                        }
                        
                        // Generate token
                        $token = bin2hex(random_bytes(32));
                        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
                        
                        $tokenStmt = $conn->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                        if (!$tokenStmt) {
                            sendResponse(['success' => false, 'message' => 'Token creation failed'], 500);
                        }
                        
                        $tokenStmt->bind_param("iss", $user['id'], $token, $expires_at);
                        $tokenStmt->execute();
                        
                        sendResponse([
                            'success' => true,
                            'message' => 'Login successful',
                            'token' => $token,
                            'user' => $user
                        ]);
                    } else {
                        // New user — ask for phone number
                        sendResponse([
                            'success' => false,
                            'is_new_user' => true,
                            'message' => 'Please provide your mobile number to complete registration',
                            'google_data' => [
                                'google_id' => $google_id,
                                'name' => $name,
                                'email' => $email,
                                'profile_image' => $profile_image
                            ]
                        ]);
                    }
                    break;
                
                case 'google_register':
                    // Google Register: complete registration with phone number
                    $google_id = $input['google_id'] ?? '';
                    $name = $input['name'] ?? '';
                    $email = $input['email'] ?? '';
                    $mobile = $input['mobile'] ?? '';
                    $state = $input['state'] ?? '';
                    $profile_image = $input['profile_image'] ?? '';
                    
                    if (empty($google_id) || empty($email) || empty($mobile)) {
                        sendResponse(['success' => false, 'message' => 'Google ID, email and mobile are required'], 400);
                    }
                    
                    if (!preg_match('/^\d{10,15}$/', $mobile)) {
                        sendResponse(['success' => false, 'message' => 'Invalid mobile number format'], 400);
                    }
                    
                    // Check if mobile already exists
                    $checkMobile = $conn->prepare("SELECT id FROM users WHERE mobile = ?");
                    if ($checkMobile) {
                        $checkMobile->bind_param("s", $mobile);
                        $checkMobile->execute();
                        if ($checkMobile->get_result()->num_rows > 0) {
                            sendResponse(['success' => false, 'message' => 'This mobile number is already registered with another account'], 409);
                        }
                    }
                    
                    // Check if google_id or email already exists
                    $checkExists = $conn->prepare("SELECT id FROM users WHERE google_id = ? OR email = ?");
                    if ($checkExists) {
                        $checkExists->bind_param("ss", $google_id, $email);
                        $checkExists->execute();
                        if ($checkExists->get_result()->num_rows > 0) {
                            sendResponse(['success' => false, 'message' => 'Account already exists. Please login instead.'], 409);
                        }
                    }
                    
                    // Create user
                    $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                    $stateVal = !empty($state) ? $state : 'Not specified';
                    
                    $stmt = $conn->prepare("INSERT INTO users (name, email, mobile, state, google_id, profile_image, password, status) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                    if (!$stmt) {
                        sendResponse(['success' => false, 'message' => 'Registration failed: ' . $conn->error], 500);
                    }
                    
                    $stmt->bind_param("sssssss", $name, $email, $mobile, $stateVal, $google_id, $profile_image, $randomPassword);
                    
                    if ($stmt->execute()) {
                        $userId = $stmt->insert_id;
                        
                        // Generate token
                        $token = bin2hex(random_bytes(32));
                        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
                        
                        $tokenStmt = $conn->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                        if (!$tokenStmt) {
                            sendResponse(['success' => false, 'message' => 'Token creation failed'], 500);
                        }
                        
                        $tokenStmt->bind_param("iss", $userId, $token, $expires_at);
                        $tokenStmt->execute();
                        
                        sendResponse([
                            'success' => true,
                            'message' => 'Registration successful',
                            'token' => $token,
                            'user' => [
                                'id' => $userId,
                                'name' => $name,
                                'email' => $email,
                                'mobile' => $mobile,
                                'state' => $stateVal,
                                'profile_image' => $profile_image
                            ]
                        ]);
                    } else {
                        sendResponse(['success' => false, 'message' => 'Registration failed: ' . $conn->error], 500);
                    }
                    break;
                    
                case 'otp_login':
                    // OTP Login: Firebase already verified the phone — just find user and issue token
                    $mobile = $input['mobile'] ?? '';
                    
                    if (empty($mobile)) {
                        sendResponse(['success' => false, 'message' => 'Mobile number is required'], 400);
                    }
                    
                    if (!preg_match('/^\d{10,15}$/', $mobile)) {
                        sendResponse(['success' => false, 'message' => 'Invalid mobile number format'], 400);
                    }
                    
                    $stmt = $conn->prepare("SELECT id, name, mobile, email, state FROM users WHERE mobile = ? AND status = 1");
                    if (!$stmt) {
                        sendResponse(['success' => false, 'message' => 'Database error'], 500);
                    }
                    
                    $stmt->bind_param("s", $mobile);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 0) {
                        sendResponse(['success' => false, 'message' => 'No account found with this mobile number. Please register first.'], 404);
                    }
                    
                    $user = $result->fetch_assoc();
                    
                    // Generate token
                    $token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    $tokenStmt = $conn->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                    if (!$tokenStmt) {
                        sendResponse(['success' => false, 'message' => 'Token creation failed'], 500);
                    }
                    
                    $tokenStmt->bind_param("iss", $user['id'], $token, $expires_at);
                    $tokenStmt->execute();
                    
                    sendResponse([
                        'success' => true,
                        'message' => 'Login successful',
                        'token' => $token,
                        'user' => $user
                    ]);
                    break;
                    
                case 'otp_register':
                    // OTP Register: Firebase already verified the phone — create user without password
                    $name = $input['name'] ?? '';
                    $mobile = $input['mobile'] ?? '';
                    $email = $input['email'] ?? '';
                    $state = $input['state'] ?? '';
                    
                    if (empty($name) || empty($mobile) || empty($state)) {
                        sendResponse(['success' => false, 'message' => 'Name, mobile and state are required'], 400);
                    }
                    
                    if (!preg_match('/^\d{10,15}$/', $mobile)) {
                        sendResponse(['success' => false, 'message' => 'Invalid mobile number format'], 400);
                    }
                    
                    // Check if mobile already exists
                    $checkStmt = $conn->prepare("SELECT id FROM users WHERE mobile = ?");
                    if (!$checkStmt) {
                        sendResponse(['success' => false, 'message' => 'Database error'], 500);
                    }
                    
                    $checkStmt->bind_param("s", $mobile);
                    $checkStmt->execute();
                    
                    if ($checkStmt->get_result()->num_rows > 0) {
                        sendResponse(['success' => false, 'message' => 'Mobile number already registered. Please login instead.'], 409);
                    }
                    
                    // Create user with a random password (OTP users don't need password)
                    $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                    $emailVal = !empty($email) ? $email : null;
                    
                    $stmt = $conn->prepare("INSERT INTO users (name, mobile, email, state, password, status) VALUES (?, ?, ?, ?, ?, 1)");
                    if (!$stmt) {
                        sendResponse(['success' => false, 'message' => 'Registration failed: ' . $conn->error], 500);
                    }
                    
                    $stmt->bind_param("sssss", $name, $mobile, $emailVal, $state, $randomPassword);
                    
                    if ($stmt->execute()) {
                        $userId = $stmt->insert_id;
                        
                        // Generate token
                        $token = bin2hex(random_bytes(32));
                        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
                        
                        $tokenStmt = $conn->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                        if (!$tokenStmt) {
                            sendResponse(['success' => false, 'message' => 'Token creation failed'], 500);
                        }
                        
                        $tokenStmt->bind_param("iss", $userId, $token, $expires_at);
                        $tokenStmt->execute();
                        
                        sendResponse([
                            'success' => true,
                            'message' => 'Registration successful',
                            'token' => $token,
                            'user' => [
                                'id' => $userId,
                                'name' => $name,
                                'mobile' => $mobile,
                                'email' => $email,
                                'state' => $state
                            ]
                        ]);
                    } else {
                        sendResponse(['success' => false, 'message' => 'Registration failed: ' . $conn->error], 500);
                    }
                    break;
                    
                case 'otp_change_password':
                    // OTP Password Change: Firebase already verified the phone — just update password
                    $mobile = $input['mobile'] ?? '';
                    $newPassword = $input['new_password'] ?? '';
                    
                    if (empty($mobile) || empty($newPassword)) {
                        sendResponse(['success' => false, 'message' => 'Mobile and new password are required'], 400);
                    }
                    
                    if (strlen($newPassword) < 6) {
                        sendResponse(['success' => false, 'message' => 'Password must be at least 6 characters'], 400);
                    }
                    
                    // Find user
                    $stmt = $conn->prepare("SELECT id FROM users WHERE mobile = ? AND status = 1");
                    if (!$stmt) {
                        sendResponse(['success' => false, 'message' => 'Database error'], 500);
                    }
                    
                    $stmt->bind_param("s", $mobile);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 0) {
                        sendResponse(['success' => false, 'message' => 'User not found'], 404);
                    }
                    
                    $user = $result->fetch_assoc();
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    
                    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if (!$updateStmt) {
                        sendResponse(['success' => false, 'message' => 'Database error'], 500);
                    }
                    
                    $updateStmt->bind_param("si", $hashedPassword, $user['id']);
                    
                    if ($updateStmt->execute()) {
                        sendResponse(['success' => true, 'message' => 'Password changed successfully']);
                    } else {
                        sendResponse(['success' => false, 'message' => 'Failed to change password'], 500);
                    }
                    break;
                    
                default:
                    sendResponse(['success' => false, 'message' => 'Invalid action: ' . $action], 400);
            }
            break;
            
        case 'GET':
            // Check token validity
            $headers = getallheaders();
            $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';
            
            if (empty($token)) {
                sendResponse(['success' => false, 'message' => 'Token required'], 401);
            }
            
            $stmt = $conn->prepare("SELECT u.id, u.name, u.mobile FROM users u 
                                    INNER JOIN user_tokens ut ON u.id = ut.user_id 
                                    WHERE ut.token = ? AND ut.expires_at > NOW()");
            if (!$stmt) {
                sendResponse(['success' => false, 'message' => 'Database prepare failed: ' . $conn->error], 500);
            }
            
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                sendResponse(['success' => false, 'message' => 'Invalid or expired token'], 401);
            }
            
            $user = $result->fetch_assoc();
            sendResponse(['success' => true, 'user' => $user]);
            break;
            
        default:
            sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    
} catch (Exception $e) {
    // Log the full error
    error_log("Auth API Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    sendResponse([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'error' => $e->getTraceAsString()
    ], 500);
}
?>