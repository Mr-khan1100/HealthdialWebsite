<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['endpoint']) || empty($input['p256dh']) || empty($input['auth'])) {
    sendResponse(['success' => false, 'error' => 'Missing required fields'], 400);
}

$endpoint  = trim($input['endpoint']);
$p256dh    = trim($input['p256dh']);
$auth      = trim($input['auth']);
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

// Basic sanity check: endpoint must be a valid HTTPS URL
if (!filter_var($endpoint, FILTER_VALIDATE_URL) || strpos($endpoint, 'https://') !== 0) {
    sendResponse(['success' => false, 'error' => 'Invalid endpoint'], 400);
}

// Upsert: update keys if endpoint already exists (subscription may have been refreshed)
$stmt = $conn->prepare("
    INSERT INTO web_push_subscriptions (endpoint, p256dh, auth, user_agent)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        p256dh     = VALUES(p256dh),
        auth       = VALUES(auth),
        last_seen  = NOW()
");

if (!$stmt) {
    error_log('[save_web_push] Prepare failed: ' . $conn->error);
    sendResponse(['success' => false, 'error' => 'DB error'], 500);
}

$stmt->bind_param("ssss", $endpoint, $p256dh, $auth, $userAgent);
$ok = $stmt->execute();
$stmt->close();

sendResponse(['success' => $ok]);
