<?php
/**
 * Returns the most recently sent notification for use by the service worker.
 * Called inside the SW push event so it can show the actual title/message.
 */
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$stmt = $conn->prepare("
    SELECT title, message, target_type, target_id, image_url
    FROM notification_queue
    WHERE status IN ('sent', 'pending')
    ORDER BY created_at DESC
    LIMIT 1
");

if (!$stmt) {
    sendResponse([
        'success' => true,
        'title'   => 'HealthDial',
        'message' => 'New health updates available near you.',
        'icon'    => '/assets/images/icon.png',
        'url'     => '/',
    ]);
}

$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    sendResponse([
        'success' => true,
        'title'   => 'HealthDial',
        'message' => 'New health updates available near you.',
        'icon'    => '/assets/images/icon.png',
        'url'     => '/',
    ]);
}

// Build a deep-link URL based on notification target type
$url = '/';
if ($row['target_type'] === 'listing' && !empty($row['target_id'])) {
    $url = '/looking.php?id=' . intval($row['target_id']);
} elseif ($row['target_type'] === 'news') {
    $url = '/news.php';
} elseif ($row['target_type'] === 'screen' && !empty($row['target_id'])) {
    $url = '/' . ltrim($row['target_id'], '/');
}

sendResponse([
    'success' => true,
    'title'   => $row['title'],
    'message' => $row['message'],
    'icon'    => !empty($row['image_url']) ? $row['image_url'] : '/assets/images/icon.png',
    'url'     => $url,
]);
