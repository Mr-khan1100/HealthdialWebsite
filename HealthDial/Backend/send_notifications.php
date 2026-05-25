<?php
// send_notifications.php

require_once 'config.php'; // Your DB connection and authenticateUser functions
require_once 'sendExpoPus.php'; // Include your sendExpoPus() function

// ---------------------
// Helper: get Expo token for user
// ---------------------
function getUserExpoToken($userId, $conn) {
    $stmt = $conn->prepare("SELECT expo_push_token FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['expo_push_token'] : null;
}

// ---------------------
// Helper: get ALL device tokens (registered users + anonymous)
// ---------------------
function getAllDeviceTokens($conn) {
    $tokens = [];
    $seen = [];

    // Source 1: Logged-in users
    $result = $conn->query("SELECT expo_push_token FROM users WHERE expo_push_token IS NOT NULL AND expo_push_token != ''");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['expo_push_token']) && !isset($seen[$row['expo_push_token']])) {
                $seen[$row['expo_push_token']] = true;
                $tokens[] = $row['expo_push_token'];
            }
        }
    }

    // Source 2: Anonymous device tokens
    $deviceResult = $conn->query("SELECT expo_push_token FROM device_push_tokens WHERE expo_push_token IS NOT NULL AND expo_push_token != ''");
    if ($deviceResult) {
        while ($row = $deviceResult->fetch_assoc()) {
            if (!empty($row['expo_push_token']) && !isset($seen[$row['expo_push_token']])) {
                $seen[$row['expo_push_token']] = true;
                $tokens[] = $row['expo_push_token'];
            }
        }
    }

    return $tokens;
}

// ---------------------
// Fetch pending notifications
// ---------------------
$stmt = $conn->prepare("
    SELECT id, user_id, title, message
    FROM notification_queue
    WHERE status='pending'
    ORDER BY scheduled_time ASC
    LIMIT 100
");
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
$notificationIds = [];

while ($row = $result->fetch_assoc()) {
    if ($row['user_id']) {
        // Targeted notification → send to specific user
        $expoToken = getUserExpoToken($row['user_id'], $conn);
        if ($expoToken) {
            $messages[] = [
                "to"    => $expoToken,
                "title" => $row['title'],
                "body"  => $row['message'],
                "data"  => ["notification_id" => $row['id']]
            ];
            $notificationIds[] = $row['id'];
        }
    } else {
        // Broadcast notification (user_id is NULL) → send to ALL devices
        $allTokens = getAllDeviceTokens($conn);
        foreach ($allTokens as $token) {
            $messages[] = [
                "to"    => $token,
                "title" => $row['title'],
                "body"  => $row['message'],
                "data"  => ["notification_id" => $row['id']]
            ];
        }
        $notificationIds[] = $row['id'];
    }
}

$stmt->close();

if (empty($messages)) {
    echo "[" . date('Y-m-d H:i:s') . "] No pending notifications\n";
    exit;
}

// ---------------------
// Send notifications
// ---------------------
$totalSent = sendExpoPus($messages);
echo "[" . date('Y-m-d H:i:s') . "] Total notifications sent: $totalSent\n";

// ---------------------
// Update notification_queue to mark as sent
// ---------------------
if (!empty($notificationIds)) {
    $idsPlaceholders = implode(',', array_fill(0, count($notificationIds), '?'));
    $types = str_repeat('i', count($notificationIds));
    $stmt = $conn->prepare("
        UPDATE notification_queue 
        SET status='sent', sent_at=NOW() 
        WHERE id IN ($idsPlaceholders)
    ");
    $stmt->bind_param($types, ...$notificationIds);
    $stmt->execute();
    $stmt->close();
}

echo "[" . date('Y-m-d H:i:s') . "] Notification statuses updated in DB\n";
?>
