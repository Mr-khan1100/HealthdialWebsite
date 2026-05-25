<?php
require_once '../config.php';

// Ensure we're handling JSON
header('Content-Type: application/json');

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    updateUserProfile();
} else {
    sendResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

function updateUserProfile() {
    global $conn;
    
    // Authenticate user
    $user = authenticateUser();
    
    if (!$user) {
        sendResponse(['success' => false, 'error' => 'Authentication required'], 401);
        return;
    }
    
    $user_id = $user['id'];
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendResponse(['success' => false, 'error' => 'Invalid input'], 400);
        return;
    }
    
    // Validate required fields
    if (empty($input['name']) || empty($input['email'])) {
        sendResponse(['success' => false, 'error' => 'Name and email are required'], 400);
        return;
    }
    
    // Validate email format
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        sendResponse(['success' => false, 'error' => 'Invalid email format'], 400);
        return;
    }
    
    // Check if email already exists (for other users)
    if ($input['email'] !== $user['email']) {
        $checkEmailQuery = "SELECT id FROM users WHERE email = ? AND id != ?";
        $checkStmt = $conn->prepare($checkEmailQuery);
        $checkStmt->bind_param("si", $input['email'], $user_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            sendResponse(['success' => false, 'error' => 'Email already in use'], 409);
            return;
        }
    }
    
    try {

    $query = "UPDATE users SET
                name = ?,
                email = ?,
                mobile = ?,
                address = ?,
                state = ?,
                diseases = ?
              WHERE id = ?";

    $stmt = $conn->prepare($query);

    $name     = $input['name'];
    $email    = $input['email'];
    $mobile   = $input['mobile']   ?? null;
    $address  = $input['address']  ?? null;
    $state    = $input['state']    ?? null;
    $diseases = $input['diseases'] ?? null;

    $stmt->bind_param(
        "ssssssi",
        $name,
        $email,
        $mobile,
        $address,
        $state,
        $diseases,
        $user_id
    );

    if ($stmt->execute()) {

        $fetchQuery = "SELECT * FROM users WHERE id = ?";
        $fetchStmt = $conn->prepare($fetchQuery);
        $fetchStmt->bind_param("i", $user_id);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $updatedUser = $result->fetch_assoc();

        sendResponse([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => $updatedUser
        ], 200);

    } else {
        sendResponse(['success' => false, 'error' => 'Failed to update profile'], 500);
    }

} catch (Exception $e) {
    error_log("Profile update error: " . $e->getMessage());
    sendResponse(['success' => false, 'error' => 'Server error'], 500);
}

}
?>