<?php
/**
 * One-time script to insert ad settings into the database.
 * Upload to Backend/api/ and access once via browser, then delete.
 */
require_once __DIR__ . '/../config.php';

// Ad settings to insert — these control mobile app ads
$ad_settings = [
    'ads_enabled' => '1',
    'banner_enabled' => '1',
    'interstitial_enabled' => '1',
    'interstitial_interval' => '5',
    // Replace these with your actual AdMob ad unit IDs from https://admob.google.com
    'android_banner_ad_unit' => 'ca-app-pub-7493022225819381/8184119416',
    'android_interstitial_ad_unit' => 'ca-app-pub-7493022225819381/8355423427',
    'ios_banner_ad_unit' => 'ca-app-pub-7493022225819381/9040060885',
    'ios_interstitial_ad_unit' => 'ca-app-pub-7493022225819381/3037848601',
];

$inserted = 0;
$updated = 0;

foreach ($ad_settings as $key => $value) {
    // Check if setting already exists
    $check = $conn->prepare("SELECT setting_key FROM settings WHERE setting_key = ?");
    $check->bind_param("s", $key);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        // Update existing
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $value, $key);
        $stmt->execute();
        $updated++;
    } else {
        // Insert new
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->bind_param("ss", $key, $value);
        $stmt->execute();
        $inserted++;
    }
}

echo json_encode([
    'success' => true,
    'message' => "Inserted $inserted, Updated $updated ad settings",
    'settings' => $ad_settings
]);
?>
