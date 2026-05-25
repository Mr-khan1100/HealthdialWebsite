<?php
require_once '../config.php';

// Only expose safe, public-facing settings — never API keys or credentials
$safe_keys = [
    'app_version',
    'android_store_url',
    'ios_store_url',
    'maintenance_mode',
    'app_name',
    'support_email',
    'support_phone'
];

$config = [];
$result = $conn->query("SELECT setting_key, setting_value FROM settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Only include whitelisted keys
        if (in_array($row['setting_key'], $safe_keys)) {
            $config[$row['setting_key']] = $row['setting_value'];
        }
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $config
]);
?>