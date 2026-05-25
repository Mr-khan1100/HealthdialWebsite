<?php
require_once '../config.php';


$user = authenticateUser();
if (!$user) {
    sendResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // =========================
    // GET DOCUMENTS
    // =========================
    case 'GET':
        $stmt = $conn->prepare(
            "SELECT id, file_path, original_name, document_type, uploaded_at, file_size, file_type
             FROM documents
             WHERE user_id = ?
             ORDER BY uploaded_at DESC"
        );
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();

        $result = $stmt->get_result();
        $documents = [];

        while ($row = $result->fetch_assoc()) {
            // Format file size
            $sizeFormatted = formatBytes($row['file_size'] ?? 0);
            
            $documents[] = [
                'id' => $row['id'],
                'file_path' => $row['file_path'],
                'original_name' => $row['original_name'] ?? basename($row['file_path']),
                'document_type' => $row['document_type'] ?? 'other',
                'uploaded_at' => $row['uploaded_at'],
                'date' => date('M d, Y', strtotime($row['uploaded_at'])),
                'size' => $sizeFormatted,
                'size_bytes' => $row['file_size'] ?? 0,
                'file_type' => $row['file_type'] ?? 'application/octet-stream'
            ];
        }

        sendResponse([
            'success' => true,
            'documents' => $documents
        ]);
        break;

    // =========================
    // UPLOAD DOCUMENT
    // =========================
   
    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['id'])) {
            sendResponse(['success' => false, 'message' => 'Document ID required'], 400);
        }
        
        $documentId = $data['id'];

        // Get file info
        $stmt = $conn->prepare(
            "SELECT file_path FROM documents
             WHERE id = ? AND user_id = ?"
        );
        $stmt->bind_param("ii", $documentId, $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            sendResponse(['success' => false, 'message' => 'Document not found'], 404);
        }

        $row = $result->fetch_assoc();
        $filePath = '../uploads/documents/' . $row['file_path'];

        // Delete from database
        $stmt = $conn->prepare(
            "DELETE FROM documents WHERE id = ? AND user_id = ?"
        );
        $stmt->bind_param("ii", $documentId, $user['id']);

        if ($stmt->execute()) {
            // Delete file
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            sendResponse([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);
        } else {
            sendResponse(['success' => false, 'message' => 'Delete failed'], 500);
        }
        break;

    default:
        sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Helper function to format bytes
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
} 

?>