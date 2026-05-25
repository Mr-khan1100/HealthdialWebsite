<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');


define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('MAX_USER_STORAGE', 50 * 1024 * 1024 * 1024); // 50GB

// Authenticate user
$user = authenticateUser();
if (!$user) {
    sendResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Get POST data
$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['file_data'])) {
    sendResponse(['success' => false, 'message' => 'No file data received'], 400);
}

try {
    // Get input values
    $fileData = $input['file_data'];
    $fileName = $input['file_name'] ?? 'document_' . time();
    $documentType = $input['document_type'] ?? 'other';
    $user_id = $user['id'];

    // ================================
    // 🔥 STEP 1: Extract MIME + Base64
    // ================================
    // if (preg_match('/^data:(.*);base64,/', $fileData, $matches)) {
    //     $mimeType = $matches[1]; // ✅ correct MIME
    //     $fileData = substr($fileData, strpos($fileData, ',') + 1);
    // } else {
    //     $mimeType = 'application/octet-stream';
    // }
    $mimeType = $input['file_type'] ?? 'application/octet-stream';

    // ================================
    // 🔥 STEP 2: Decode Base64
    // ================================
    $fileData = base64_decode($fileData);

    if (!$fileData) {
        throw new Exception('Invalid base64 data');
    }
    
    // ================================
    // 🔥 STEP 2.1: FILE SIZE CHECK (5MB)
    // ================================
    $fileSize = strlen($fileData); 

    if ($fileSize > MAX_FILE_SIZE) {
        throw new Exception('File exceeds maximum allowed size of 5MB');
    }
    
    // ================================
    // 🔥 STEP 2.2: USER STORAGE CHECK (50GB)
    // ================================
    $storageQuery = "SELECT COALESCE(SUM(file_size), 0) as total FROM documents WHERE user_id = ?";
    $stmtStorage = $conn->prepare($storageQuery);
    $stmtStorage->bind_param("i", $user_id);
    $stmtStorage->execute();
    $resultStorage = $stmtStorage->get_result();
    $rowStorage = $resultStorage->fetch_assoc();

    $currentUsage = $rowStorage['total'] ?? 0;

    if (($currentUsage + $fileSize) > MAX_USER_STORAGE) {
        throw new Exception('Storage limit exceeded (50GB max)');
    }

    // ================================
    // 🔥 STEP 3: MIME → Extension (images only)
    // ================================
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/bmp'  => 'bmp',
        'application/pdf' => 'pdf',
    ];

    if (!array_key_exists($mimeType, $allowedMimeTypes)) {
        throw new Exception('Only image files (JPG, PNG, GIF, WEBP, BMP) and PDF files are allowed. Received: ' . $mimeType);
    }

    $extension = $allowedMimeTypes[$mimeType];

    // ================================
    // 🔥 STEP 4: Generate Safe Filename
    // ================================
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
    $uniqueFileName = uniqid() . '_' . time() . '_' . $safeName . '.' . $extension;

    // ================================
    // 🔥 STEP 5: Save File
    // ================================
    $uploadDir = '../uploads/documents/';

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filePath = $uploadDir . $uniqueFileName;

    if (!file_put_contents($filePath, $fileData)) {
        throw new Exception('Failed to save file');
    }

    // ================================
    // 🔥 STEP 6: File Size
    // ================================
    $fileSize = filesize($filePath);

    // ================================
    // 🔥 STEP 7: Save to DB
    // ================================
    $stmt = $conn->prepare(
        "INSERT INTO documents (user_id, file_path, original_name, document_type, file_size, file_type)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "isssis",
        $user_id,
        $uniqueFileName,
        $fileName,
        $documentType,
        $fileSize,
        $mimeType
    );

    if ($stmt->execute()) {
        sendResponse([
            'success' => true,
            'message' => 'Document uploaded successfully',
            'document_id' => $stmt->insert_id,
            'file_path' => $uniqueFileName,
            'original_name' => $fileName,
            'document_type' => $documentType,
            'file_type' => $mimeType,
            'size' => formatBytes($fileSize)
        ]);
    } else {
        // rollback file if DB fails
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        throw new Exception('Database error: ' . $conn->error);
    }

} catch (Exception $e) {
    sendResponse([
        'success' => false,
        'message' => 'Upload failed: ' . $e->getMessage()
    ], 500);
}

// ================================
// Helper function
// ================================
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>