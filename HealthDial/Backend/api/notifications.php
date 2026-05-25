<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getNotifications();
        break;
    case 'POST':
        markAsRead();
        break;
    case 'DELETE':
        clearNotifications();
        break;
    default:
        sendResponse(['error' => 'Method not allowed'], 405);
}

function getNotifications() {
    global $conn;
    
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $unread_only = isset($_GET['unread']) ? $_GET['unread'] === 'true' : false;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    
    if (!$user_id) {
        sendResponse(['error' => 'User ID required'], 400);
    }
    
    $query = "SELECT * FROM notifications 
              WHERE user_id = ? 
              AND (scheduled_for IS NULL OR scheduled_for <= NOW())";
    
    if ($unread_only) {
        $query .= " AND is_read = 0";
    }
    
    $query .= " ORDER BY created_at DESC LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'message' => $row['message'],
            'type' => $row['type'],
            'data' => json_decode($row['data'], true),
            'is_read' => (bool)$row['is_read'],
            'time' => timeAgo($row['created_at'])
        ];
    }
    
    sendResponse(['notifications' => $notifications]);
}

function markAsRead() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $notification_id = $data['notification_id'] ?? 0;
    $user_id = $data['user_id'] ?? 0;
    $mark_all = $data['mark_all'] ?? false;
    
    if (!$user_id) {
        sendResponse(['error' => 'User ID required'], 400);
    }
    
    if ($mark_all) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
    } else if ($notification_id) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notification_id, $user_id);
    } else {
        sendResponse(['error' => 'Notification ID required'], 400);
    }
    
    if ($stmt->execute()) {
        sendResponse(['message' => 'Notification(s) marked as read']);
    } else {
        sendResponse(['error' => 'Failed to update notification'], 500);
    }
}

function clearNotifications() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $data['user_id'] ?? 0;
    
    if (!$user_id) {
        sendResponse(['error' => 'User ID required'], 400);
    }
    
    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        sendResponse(['message' => 'Notifications cleared']);
    } else {
        sendResponse(['error' => 'Failed to clear notifications'], 500);
    }
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff/60) . ' minutes ago';
    if ($diff < 86400) return floor($diff/3600) . ' hours ago';
    if ($diff < 604800) return floor($diff/86400) . ' days ago';
    
    return date('M d, Y', $time);
}
?>