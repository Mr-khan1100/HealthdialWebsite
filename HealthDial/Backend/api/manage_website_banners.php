<?php
require_once '../config.php';

/* ============================================================
   WEBSITE BANNER MANAGEMENT API
   GET  ?page=home&position=top  → active banners for page/pos
   POST action=upload|update|delete
============================================================ */

$conn->query("CREATE TABLE IF NOT EXISTS website_banners (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255)  DEFAULT NULL,
    page        ENUM('home','explore','promotion','add_listing') NOT NULL,
    position    ENUM('top','bottom') NOT NULL DEFAULT 'top',
    image_url   VARCHAR(500)  NOT NULL,
    link_url    VARCHAR(500)  DEFAULT NULL,
    sort_order  INT           NOT NULL DEFAULT 0,
    status      TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP
)");

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: public endpoint used by website pages ──
if ($method === 'GET') {
    $page     = trim($_GET['page'] ?? '');
    $position = trim($_GET['position'] ?? 'top');

    $validPages = ['home', 'explore', 'promotion', 'add_listing'];
    if (!in_array($page, $validPages)) {
        sendResponse(['success' => false, 'message' => 'Invalid page'], 400);
    }
    if (!in_array($position, ['top', 'bottom'])) {
        $position = 'top';
    }

    $stmt = $conn->prepare(
        "SELECT id, title, image_url, link_url, sort_order
         FROM website_banners
         WHERE page = ? AND position = ? AND status = 1
         ORDER BY sort_order ASC, id DESC"
    );
    $stmt->bind_param("ss", $page, $position);
    $stmt->execute();
    $rows = $stmt->get_result();
    $banners = [];
    while ($r = $rows->fetch_assoc()) {
        $banners[] = [
            'id'         => (int)$r['id'],
            'title'      => $r['title'],
            'image_url'  => $r['image_url'],
            'link_url'   => $r['link_url'],
            'sort_order' => (int)$r['sort_order'],
        ];
    }
    $stmt->close();
    sendResponse(['success' => true, 'data' => $banners]);
}

// ── POST: admin CRUD ──
if ($method === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {

        case 'upload':
            $page     = trim($_POST['page'] ?? '');
            $position = trim($_POST['position'] ?? 'top');
            $validPages = ['home', 'explore', 'promotion', 'add_listing'];
            if (!in_array($page, $validPages)) {
                sendResponse(['success' => false, 'message' => 'Invalid page'], 400);
            }
            if (!in_array($position, ['top', 'bottom'])) $position = 'top';

            // Image: uploaded file takes priority, then image_url field
            $imageUrl = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $file    = $_FILES['image'];
                $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if (!in_array($file['type'], $allowed)) {
                    sendResponse(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, WebP, GIF'], 400);
                }
                if ($file['size'] > 5 * 1024 * 1024) {
                    sendResponse(['success' => false, 'message' => 'File too large. Max 5MB'], 400);
                }
                $uploadDir = '../../uploads/website_banners/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = 'wb_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
                if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    sendResponse(['success' => false, 'message' => 'Failed to save file'], 500);
                }
                $imageUrl = BASE_URL . '/HealthDial/uploads/website_banners/' . $filename;
            } elseif (!empty($_POST['image_url'])) {
                $imageUrl = trim($_POST['image_url']);
            } else {
                sendResponse(['success' => false, 'message' => 'Upload an image file or provide an image URL'], 400);
            }

            $title     = trim($_POST['title'] ?? '');
            $linkUrl   = trim($_POST['link_url'] ?? '');
            $sortOrder = (int)($_POST['sort_order'] ?? 0);

            $stmt = $conn->prepare(
                "INSERT INTO website_banners (title, page, position, image_url, link_url, sort_order, status)
                 VALUES (?, ?, ?, ?, ?, ?, 1)"
            );
            $stmt->bind_param("sssssi", $title, $page, $position, $imageUrl, $linkUrl, $sortOrder);
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();

            sendResponse(['success' => true, 'message' => 'Banner added', 'data' => ['id' => $newId, 'image_url' => $imageUrl]]);
            break;

        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);

            $fields = []; $types = ''; $values = [];
            if (isset($_POST['status']))     { $fields[] = 'status = ?';     $types .= 'i'; $values[] = (int)$_POST['status']; }
            if (isset($_POST['sort_order'])) { $fields[] = 'sort_order = ?'; $types .= 'i'; $values[] = (int)$_POST['sort_order']; }
            if (isset($_POST['title']))      { $fields[] = 'title = ?';      $types .= 's'; $values[] = trim($_POST['title']); }
            if (empty($fields)) sendResponse(['success' => false, 'message' => 'Nothing to update'], 400);

            $types .= 'i'; $values[] = $id;
            $stmt = $conn->prepare("UPDATE website_banners SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            $stmt->close();
            sendResponse(['success' => true, 'message' => 'Updated']);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);

            $res = $conn->query("SELECT image_url FROM website_banners WHERE id = $id");
            if ($res && $row = $res->fetch_assoc()) {
                $urlPath   = parse_url($row['image_url'], PHP_URL_PATH);
                $localPath = $_SERVER['DOCUMENT_ROOT'] . $urlPath;
                if ($urlPath && strpos($urlPath, 'website_banners') !== false && file_exists($localPath)) {
                    @unlink($localPath);
                }
            }
            $conn->query("DELETE FROM website_banners WHERE id = $id");
            sendResponse(['success' => true, 'message' => 'Deleted']);
            break;

        default:
            sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
    exit;
}

sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
?>
