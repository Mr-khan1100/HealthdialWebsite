<?php
require_once '../config.php';

// Check if request is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(['success' => false, 'message' => 'Invalid request method'], 405);
    exit;
}

// Optional auth — categories are public data
$user = authenticateUser();

try {
    // Fetch active categories (status = 1 for active in your database)
    $sql = "SELECT id, name, icon FROM categories WHERE status = 1 ORDER BY name ASC";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'icon' => $row['icon'] ?? ''
        ];
    }
    
    // Close statement
    $stmt->close();
    
    sendResponse([
        'success' => true,
        'categories' => $categories
    ], 200);
    
} catch (Exception $e) {
    error_log('Error fetching categories: ' . $e->getMessage());
    sendResponse([
        'success' => false,
        'message' => 'Failed to fetch categories',
        'error' => $e->getMessage()
    ], 500);
}

// Note: $conn is global from config.php, don't close it here
?>