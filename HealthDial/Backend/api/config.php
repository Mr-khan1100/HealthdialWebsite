<?php
/**
 * Settings API Endpoint
 * URL: /api/config.php
 * 
 * This file serves dual purpose:
 * 1. When accessed directly via HTTP → returns public settings JSON
 * 2. When included by other api files (require_once 'config.php') → loads main config
 * 
 * We detect "direct access" by checking if this is the entry script.
 */

// Always load the main config first
require_once __DIR__ . '/../config.php';

// Only output settings JSON if this file was accessed directly (not included)
if (basename($_SERVER['SCRIPT_FILENAME']) === 'config.php' && 
    realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    
    // Only expose safe, public-facing settings — never API keys or credentials
    $safe_keys = [
        'app_version',
        'android_store_url',
        'ios_store_url',
        'maintenance_mode',
        'app_name',
        'support_email',
        'support_phone',
        'ads_enabled',
        'interstitial_enabled',
        'banner_enabled',
        'interstitial_interval',
        'android_interstitial_ad_unit',
        'ios_interstitial_ad_unit',
        'android_banner_ad_unit',
        'ios_banner_ad_unit'
    ];

    $config = [];
    $result = $conn->query("SELECT setting_key, setting_value FROM settings");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (in_array($row['setting_key'], $safe_keys)) {
                $config[$row['setting_key']] = $row['setting_value'];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $config
    ]);
    exit();
}
// If included by another file, execution continues in that file
?>
