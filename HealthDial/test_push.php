<?php
/**
 * Push Notification Test Script
 * Upload to your server and open in browser to test
 * DELETE THIS FILE after testing!
 */
require_once 'connection.inc.php';
require_once 'push_helper.php';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>🔔 Push Notification Test</h2>";

// 1. Check tokens
$result = $conn->query("SELECT id, name, LEFT(expo_push_token, 30) as token_preview FROM users WHERE expo_push_token IS NOT NULL AND expo_push_token != ''");
echo "<h3>Step 1: Users with tokens</h3>";
if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='6'><tr><th>ID</th><th>Name</th><th>Token (preview)</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['id']}</td><td>{$row['name']}</td><td>{$row['token_preview']}...</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>❌ No tokens found!</p>";
    exit;
}

// 2. Check token format
$allTokens = $conn->query("SELECT expo_push_token FROM users WHERE expo_push_token IS NOT NULL AND expo_push_token != ''");
echo "<h3>Step 2: Token format check</h3>";
$validCount = 0;
$invalidTokens = [];
while ($row = $allTokens->fetch_assoc()) {
    $token = $row['expo_push_token'];
    if (strpos($token, 'ExponentPushToken[') === 0 || strpos($token, 'ExpoPushToken[') === 0) {
        $validCount++;
    } else {
        $invalidTokens[] = substr($token, 0, 40);
    }
}
echo "<p>✅ Valid Expo tokens: $validCount</p>";
if (!empty($invalidTokens)) {
    echo "<p style='color:red;'>❌ Invalid tokens: " . implode(', ', $invalidTokens) . "</p>";
}

// 3. Test send
echo "<h3>Step 3: Test push send</h3>";
if (isset($_GET['send'])) {
    echo "<p>Sending test notification...</p>";
    
    $pushResult = sendPushNotificationToAll(
        $conn,
        "🏥 HealthDial Test",
        "This is a test push notification from admin panel",
        [
            "type" => "manual",
            "target_type" => "none",
            "target_id" => null
        ]
    );
    
    echo "<pre>" . print_r($pushResult, true) . "</pre>";
    
    // Show debug log
    $logFile = __DIR__ . '/debug.log';
    if (file_exists($logFile)) {
        $log = file_get_contents($logFile);
        $lines = explode("\n", $log);
        $recent = array_slice($lines, -20); // last 20 lines
        echo "<h3>Recent debug.log:</h3>";
        echo "<pre style='background:#f0f0f0;padding:12px;max-height:400px;overflow:auto;'>" . htmlspecialchars(implode("\n", $recent)) . "</pre>";
    }
} else {
    echo "<a href='?send=1' style='padding:10px 20px;background:#10b981;color:white;border-radius:8px;text-decoration:none;font-weight:bold;'>🚀 Send Test Push Notification</a>";
    echo "<p style='color:#888;margin-top:8px;'>Click to send a test notification to all users with tokens</p>";
}

echo "<hr><p style='color:red;'>⚠️ Delete this file (test_push.php) after testing!</p>";
?>
