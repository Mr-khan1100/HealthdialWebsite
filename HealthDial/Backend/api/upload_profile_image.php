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

// Check if file was uploaded
if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    sendResponse(['success' => false, 'error' => 'No image file uploaded or upload error'], 400);
    exit;
}

$file = $_FILES['profile_image'];

// Validate file type
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    sendResponse(['success' => false, 'error' => 'Invalid file type. Only JPEG, PNG, WebP, GIF allowed'], 400);
    exit;
}

// Validate file size (max 5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    sendResponse(['success' => false, 'error' => 'File too large. Maximum 5MB allowed'], 400);
    exit;
}

// Create uploads directory if not exists
$uploadDir = dirname(dirname(__DIR__)) . '/uploads/profiles/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
$filepath = $uploadDir . $filename;

// Compress image before saving
$compressedPath = compressImage($file['tmp_name'], $filepath, $mimeType, 80);

if ($compressedPath) {
    // Delete old profile image if exists
    $oldStmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
    $oldStmt->bind_param("i", $user_id);
    $oldStmt->execute();
    $oldResult = $oldStmt->get_result()->fetch_assoc();
    
    if ($oldResult && $oldResult['profile_image']) {
        $oldFile = $uploadDir . $oldResult['profile_image'];
        if (file_exists($oldFile)) {
            unlink($oldFile);
        }
    }
    
    // Update database
    $updateStmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
    $updateStmt->bind_param("si", $filename, $user_id);
    
    if ($updateStmt->execute()) {
        sendResponse([
            'success' => true,
            'message' => 'Profile image updated successfully',
            'profile_image' => $filename
        ]);
    } else {
        // Clean up uploaded file on DB error
        if (file_exists($filepath)) unlink($filepath);
        sendResponse(['success' => false, 'error' => 'Failed to update database'], 500);
    }
} else {
    sendResponse(['success' => false, 'error' => 'Failed to process image'], 500);
}

/**
 * Compress and resize image
 */
function compressImage($source, $destination, $mimeType, $quality = 80) {
    $maxWidth = 400;
    $maxHeight = 400;
    
    list($origWidth, $origHeight) = getimagesize($source);
    
    // Calculate new dimensions (square crop for profile)
    $size = min($origWidth, $origHeight);
    $srcX = ($origWidth - $size) / 2;
    $srcY = ($origHeight - $size) / 2;
    
    $newSize = min($maxWidth, $size);
    
    $newImage = imagecreatetruecolor($newSize, $newSize);
    
    switch($mimeType) {
        case 'image/jpeg':
            $srcImage = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $srcImage = imagecreatefrompng($source);
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            break;
        case 'image/webp':
            $srcImage = imagecreatefromwebp($source);
            break;
        case 'image/gif':
            $srcImage = imagecreatefromgif($source);
            break;
        default:
            return false;
    }
    
    if (!$srcImage) return false;
    
    imagecopyresampled($newImage, $srcImage, 0, 0, $srcX, $srcY, $newSize, $newSize, $size, $size);
    
    $result = false;
    switch($mimeType) {
        case 'image/jpeg':
            $result = imagejpeg($newImage, $destination, $quality);
            break;
        case 'image/png':
            $result = imagepng($newImage, $destination, 9 - round($quality / 11));
            break;
        case 'image/webp':
            $result = imagewebp($newImage, $destination, $quality);
            break;
        case 'image/gif':
            $result = imagegif($newImage, $destination);
            break;
    }
    
    imagedestroy($srcImage);
    imagedestroy($newImage);
    
    return $result ? $destination : false;
}
?>
