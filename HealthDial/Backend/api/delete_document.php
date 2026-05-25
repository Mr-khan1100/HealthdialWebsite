<?php
require_once '../config.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id']) || empty($input['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Document ID is required'
    ]);
    exit;
}

$id = intval($input['id']);

$user = authenticateUser();
$user_id = $user['id'];

// 🔹 Step 1: Get document info (including file path)
$query = "SELECT file_path FROM documents WHERE id=$id AND user_id=$user_id";
$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => 'Database query failed: ' . mysqli_error($conn)
    ]);
    exit;
}

if (mysqli_num_rows($result) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Document not found'
    ]);
    exit;
}

$row = mysqli_fetch_assoc($result);
$filePath = $row['file_path'];

// 🔹 Step 2: Delete file from server
$fullPath = "../uploads/documents/" . $filePath;

if (!empty($filePath) && file_exists($fullPath)) {
    if (!unlink($fullPath)) {
        // optional: log error but don't block DB delete
        error_log("Failed to delete file: " . $fullPath);
    }
}

// 🔹 Step 3: Delete DB record
$deleteQuery = "DELETE FROM documents WHERE id=$id AND user_id=$user_id";
$deleteResult = mysqli_query($conn, $deleteQuery);

if ($deleteResult) {
    echo json_encode([
        'success' => true,
        'message' => 'Document and file deleted successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete document: ' . mysqli_error($conn)
    ]);
}
?>