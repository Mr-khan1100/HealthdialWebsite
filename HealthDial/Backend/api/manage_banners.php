<?php
require_once '../config.php';

/* ==========================
   BANNER MANAGEMENT API
   GET  → List active banners
   POST → Upload / Update / Delete / Reorder
========================== */

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: Return active banners (used by mobile app) ──
if ($method === 'GET') {
    $banners = [];
    $res = $conn->query("
        SELECT id, title, image_url, link_url, sort_order
        FROM banners
        WHERE status = 1
        ORDER BY sort_order ASC, id DESC
    ");
    
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $banners[] = [
                'id'        => (int)$row['id'],
                'title'     => $row['title'],
                'image_url' => $row['image_url'],
                'link_url'  => $row['link_url'],
                'sort_order'=> (int)$row['sort_order']
            ];
        }
    }
    
    sendResponse([
        'success' => true,
        'data'    => $banners
    ]);
}

// ── POST: Admin actions ──
if ($method === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    switch ($action) {
        
        // ── Upload new banner ──
        case 'upload':
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                sendResponse(['success' => false, 'message' => 'No image uploaded or upload error'], 400);
                exit;
            }
            
            $file = $_FILES['image'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            
            if (!in_array($file['type'], $allowed)) {
                sendResponse(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, WebP, GIF'], 400);
                exit;
            }
            
            if ($file['size'] > 5 * 1024 * 1024) {
                sendResponse(['success' => false, 'message' => 'File too large. Max 5MB'], 400);
                exit;
            }
            
            // Create upload directory — relative to this file (Backend/api/)
            // URL is BASE_URL/HealthDial/uploads/banners/ so file must be at ../../uploads/banners/
            $uploadDir = '../../uploads/banners/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    sendResponse(['success' => false, 'message' => 'Failed to create upload directory'], 500);
                    exit;
                }
            }
            
            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'banner_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
            $filepath = $uploadDir . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                sendResponse(['success' => false, 'message' => 'Failed to save file'], 500);
                exit;
            }
            
            // Build full URL
            $imageUrl = BASE_URL . '/HealthDial/uploads/banners/' . $filename;
            
            $title    = isset($_POST['title']) ? trim($_POST['title']) : null;
            $linkUrl  = isset($_POST['link_url']) ? trim($_POST['link_url']) : null;
            $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
            
            $stmt = $conn->prepare("
                INSERT INTO banners (title, image_url, link_url, sort_order, status)
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->bind_param("sssi", $title, $imageUrl, $linkUrl, $sortOrder);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();
            
            sendResponse([
                'success' => true,
                'message' => 'Banner uploaded successfully',
                'data'    => ['id' => $newId, 'image_url' => $imageUrl]
            ]);
            break;
        
        // ── Update banner ──
        case 'update':
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id <= 0) {
                sendResponse(['success' => false, 'message' => 'Invalid banner ID'], 400);
                exit;
            }
            
            $fields = [];
            $types  = '';
            $values = [];
            
            if (isset($_POST['title'])) {
                $fields[] = 'title = ?';
                $types   .= 's';
                $values[] = trim($_POST['title']);
            }
            if (isset($_POST['link_url'])) {
                $fields[] = 'link_url = ?';
                $types   .= 's';
                $values[] = trim($_POST['link_url']);
            }
            if (isset($_POST['sort_order'])) {
                $fields[] = 'sort_order = ?';
                $types   .= 'i';
                $values[] = (int)$_POST['sort_order'];
            }
            if (isset($_POST['status'])) {
                $fields[] = 'status = ?';
                $types   .= 'i';
                $values[] = (int)$_POST['status'];
            }
            
            if (empty($fields)) {
                sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
                exit;
            }
            
            $types  .= 'i';
            $values[] = $id;
            
            $sql = "UPDATE banners SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            $stmt->close();
            
            sendResponse(['success' => true, 'message' => 'Banner updated']);
            break;
        
        // ── Delete banner ──
        case 'delete':
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id <= 0) {
                sendResponse(['success' => false, 'message' => 'Invalid banner ID'], 400);
                exit;
            }
            
            // Get image path to delete file
            $res = $conn->query("SELECT image_url FROM banners WHERE id = $id");
            if ($res && $row = $res->fetch_assoc()) {
                // Try to delete the physical file
                $urlPath = parse_url($row['image_url'], PHP_URL_PATH);
                $localPath = $_SERVER['DOCUMENT_ROOT'] . $urlPath;
                if (file_exists($localPath)) {
                    @unlink($localPath);
                }
            }
            
            $conn->query("DELETE FROM banners WHERE id = $id");
            
            sendResponse(['success' => true, 'message' => 'Banner deleted']);
            break;
        
        // ── Reorder banners ──
        case 'reorder':
            $order = isset($_POST['order']) ? json_decode($_POST['order'], true) : [];
            if (!is_array($order) || empty($order)) {
                sendResponse(['success' => false, 'message' => 'Invalid order data'], 400);
            }
            
            foreach ($order as $pos => $bannerId) {
                $stmt = $conn->prepare("UPDATE banners SET sort_order = ? WHERE id = ?");
                $stmt->bind_param("ii", $pos, $bannerId);
                $stmt->execute();
                $stmt->close();
            }
            
            sendResponse(['success' => true, 'message' => 'Order updated']);
            break;
        
        default:
            sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
            exit;
    }
    exit;
}

sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
?>
