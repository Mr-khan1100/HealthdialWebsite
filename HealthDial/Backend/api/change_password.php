<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['success' => false, 'error' => 'Method not allowed'], 405);
    exit;
}

// Authenticate user
$user = authenticateUser();

if (!$user) {
    sendResponse(['success' => false, 'error' => 'Authentication required'], 401);
    exit;
}

$user_id = $user['id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendResponse(['success' => false, 'error' => 'Invalid input'], 400);
    exit;
}

$current_password = $input['current_password'] ?? '';
$new_password = $input['new_password'] ?? '';
$confirm_password = $input['confirm_password'] ?? '';

// Validate
if (empty($current_password) || empty($new_password)) {
    sendResponse(['success' => false, 'error' => 'Current and new password are required'], 400);
    exit;
}

if (strlen($new_password) < 6) {
    sendResponse(['success' => false, 'error' => 'New password must be at least 6 characters'], 400);
    exit;
}

if ($new_password !== $confirm_password) {
    sendResponse(['success' => false, 'error' => 'New password and confirmation do not match'], 400);
    exit;
}

// Get current password hash from DB
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    sendResponse(['success' => false, 'error' => 'User not found'], 404);
    exit;
}

// Verify current password
if (!password_verify($current_password, $row['password'])) {
    // Also check plain text match for legacy passwords
    if ($current_password !== $row['password']) {
        sendResponse(['success' => false, 'error' => 'Current password is incorrect'], 403);
        exit;
    }
}

// Hash new password and update
$hashed = password_hash($new_password, PASSWORD_DEFAULT);

$updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$updateStmt->bind_param("si", $hashed, $user_id);

if ($updateStmt->execute()) {
    sendResponse([
        'success' => true,
        'message' => 'Password changed successfully'
    ]);
} else {
    sendResponse(['success' => false, 'error' => 'Failed to update password'], 500);
}
?>
