<?php
session_start();
require_once 'connection.inc.php';
requireLogin();

error_reporting(E_ALL);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ============ HANDLE FORM SUBMISSION ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admob'])) {
    $adFields = [
        // Master toggles
        'ads_enabled'                   => isset($_POST['ads_enabled']) ? '1' : '0',
        'banner_enabled'                => isset($_POST['banner_enabled']) ? '1' : '0',
        'interstitial_enabled'          => isset($_POST['interstitial_enabled']) ? '1' : '0',
        'interstitial_interval'         => intval($_POST['interstitial_interval'] ?? 5),

        // Android Ad Unit IDs
        'android_banner_ad_unit'        => trim($_POST['android_banner_ad_unit'] ?? ''),
        'android_interstitial_ad_unit'  => trim($_POST['android_interstitial_ad_unit'] ?? ''),

        // iOS Ad Unit IDs
        'ios_banner_ad_unit'            => trim($_POST['ios_banner_ad_unit'] ?? ''),
        'ios_interstitial_ad_unit'      => trim($_POST['ios_interstitial_ad_unit'] ?? ''),

        // Store URLs
        'android_store_url'             => trim($_POST['android_store_url'] ?? ''),
        'ios_store_url'                 => trim($_POST['ios_store_url'] ?? ''),

        // App Version & Maintenance
        'app_version'                   => trim($_POST['app_version'] ?? '1.0.0'),
        'maintenance_mode'              => isset($_POST['maintenance_mode']) ? '1' : '0',
    ];

    foreach ($adFields as $key => $value) {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->bind_param("sss", $key, $value, $value);
        $stmt->execute();
        $stmt->close();
    }

    // Activity log
    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, action, details, ip_address) VALUES (?, 'update_settings', 'Updated AdMob & App Config', ?)");
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $logStmt->bind_param("is", $_SESSION['admin_id'], $ip);
    $logStmt->execute();
    $logStmt->close();

    $_SESSION['success'] = "AdMob & App Config saved successfully! ✔";
    header("Location: mob.php");
    exit();
}

/* ============ LOAD CURRENT SETTINGS ============ */
$savedSettings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM settings");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $savedSettings[$row['setting_key']] = $row['setting_value'];
    }
}

function getSetting($key, $default = '') {
    global $savedSettings;
    return $savedSettings[$key] ?? $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AdMob & App Config — HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .config-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .toggle-row { display: flex; align-items: center; gap: 10px; padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.06); }
        .toggle-row:last-child { border-bottom: none; }
        .toggle-label { flex: 1; font-size: 14px; font-weight: 500; color: #374151; }
        .toggle-desc { font-size: 11px; color: #9CA3AF; font-weight: 400; display: block; margin-top: 2px; }
        .toggle-switch { position: relative; width: 44px; height: 24px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #D1D5DB; border-radius: 24px; transition: 0.3s; }
        .toggle-slider:before { content: ""; position: absolute; height: 18px; width: 18px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: 0.3s; }
        .toggle-switch input:checked + .toggle-slider { background: #0782ca; }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }
        .section-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-android { background: #E8F5E9; color: #2E7D32; }
        .badge-ios { background: #E3F2FD; color: #1565C0; }
        .badge-general { background: #FFF3E0; color: #E65100; }
        .ad-input { font-family: 'Courier New', monospace; font-size: 13px; letter-spacing: 0.3px; }
        .hint { font-size: 11px; color: #9CA3AF; margin-top: 4px; }
        .live-preview { background: #F9FAFB; border: 1px dashed #D1D5DB; border-radius: 8px; padding: 16px; margin-top: 12px; }
        .live-preview h4 { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #6B7280; margin: 0 0 8px; }
        .preview-item { font-size: 13px; color: #374151; padding: 4px 0; }
        .preview-item span { font-weight: 600; color: #0782ca; }
        @media (max-width: 768px) { .config-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'sidebar.php'; ?>

        <div class="admin-main">
            <?php include 'header.php'; ?>

            <div class="admin-content">
                <div class="page-header">
                    <h1 class="page-title"><i class="fas fa-ad" style="margin-right:8px;color:#0782ca;"></i>AdMob & App Config</h1>
                    <p class="page-subtitle">Manage ad units, store URLs, app version, and maintenance mode</p>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div style="background:#ECFDF5;color:#065F46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px;border:1px solid #A7F3D0;">
                        <i class="fas fa-check-circle" style="margin-right:6px;"></i>
                        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div style="background:#FEF2F2;color:#991B1B;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px;border:1px solid #FECACA;">
                        <i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>
                        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="update_admob" value="1">

                    <div class="config-grid">
                        <!-- ========== LEFT COLUMN ========== -->
                        <div>
                            <!-- Master Toggles -->
                            <div class="card fade-in">
                                <div class="card-header">
                                    <h3 class="card-title"><i class="fas fa-sliders-h" style="margin-right:8px;color:#0782ca;"></i>Ad Controls</h3>
                                </div>
                                <div class="card-body">
                                    <div class="toggle-row">
                                        <div class="toggle-label">
                                            Enable Ads
                                            <span class="toggle-desc">Master switch — disables ALL ads when OFF</span>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="ads_enabled" <?= getSetting('ads_enabled', '1') === '1' ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    <div class="toggle-row">
                                        <div class="toggle-label">
                                            Banner Ads
                                            <span class="toggle-desc">Bottom banner on listing screens</span>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="banner_enabled" <?= getSetting('banner_enabled', '1') === '1' ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    <div class="toggle-row">
                                        <div class="toggle-label">
                                            Interstitial Ads
                                            <span class="toggle-desc">Full-screen ads between tab switches</span>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="interstitial_enabled" <?= getSetting('interstitial_enabled', '1') === '1' ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    <div class="toggle-row" style="border-bottom:none;">
                                        <div class="toggle-label">
                                            Interstitial Interval
                                            <span class="toggle-desc">Show interstitial every N tab switches</span>
                                        </div>
                                        <input type="number" name="interstitial_interval" min="1" max="50"
                                               value="<?= htmlspecialchars(getSetting('interstitial_interval', '5')); ?>"
                                               class="form-input" style="width:80px;text-align:center;">
                                    </div>
                                </div>
                            </div>

                            <!-- Android Ad Units -->
                            <div class="card fade-in fade-in-delay-1" style="margin-top:20px;">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fab fa-android" style="margin-right:8px;color:#3DDC84;"></i>Android Ad Units
                                        <span class="section-badge badge-android">Android</span>
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label class="form-label">Banner Ad Unit ID</label>
                                        <input type="text" name="android_banner_ad_unit"
                                               value="<?= htmlspecialchars(getSetting('android_banner_ad_unit')); ?>"
                                               class="form-input ad-input" placeholder="ca-app-pub-XXXXXXXX/YYYYYYYYYY">
                                        <p class="hint">Format: ca-app-pub-XXXXXXXX/YYYYYYYYYY</p>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Interstitial Ad Unit ID</label>
                                        <input type="text" name="android_interstitial_ad_unit"
                                               value="<?= htmlspecialchars(getSetting('android_interstitial_ad_unit')); ?>"
                                               class="form-input ad-input" placeholder="ca-app-pub-XXXXXXXX/YYYYYYYYYY">
                                        <p class="hint">Format: ca-app-pub-XXXXXXXX/YYYYYYYYYY</p>
                                    </div>
                                </div>
                            </div>

                            <!-- iOS Ad Units -->
                            <div class="card fade-in fade-in-delay-2" style="margin-top:20px;">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fab fa-apple" style="margin-right:8px;color:#333;"></i>iOS Ad Units
                                        <span class="section-badge badge-ios">iOS</span>
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label class="form-label">Banner Ad Unit ID</label>
                                        <input type="text" name="ios_banner_ad_unit"
                                               value="<?= htmlspecialchars(getSetting('ios_banner_ad_unit')); ?>"
                                               class="form-input ad-input" placeholder="ca-app-pub-XXXXXXXX/YYYYYYYYYY">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Interstitial Ad Unit ID</label>
                                        <input type="text" name="ios_interstitial_ad_unit"
                                               value="<?= htmlspecialchars(getSetting('ios_interstitial_ad_unit')); ?>"
                                               class="form-input ad-input" placeholder="ca-app-pub-XXXXXXXX/YYYYYYYYYY">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ========== RIGHT COLUMN ========== -->
                        <div>
                            <!-- App Version & Maintenance -->
                            <div class="card fade-in fade-in-delay-1">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-mobile-alt" style="margin-right:8px;color:#F59E0B;"></i>App Config
                                        <span class="section-badge badge-general">General</span>
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label class="form-label">Latest App Version</label>
                                        <input type="text" name="app_version"
                                               value="<?= htmlspecialchars(getSetting('app_version', '1.0.0')); ?>"
                                               class="form-input" placeholder="2.0.0">
                                        <p class="hint">Used to trigger mandatory update prompts on older versions</p>
                                    </div>
                                    <div class="toggle-row">
                                        <div class="toggle-label">
                                            Maintenance Mode
                                            <span class="toggle-desc">Show a maintenance screen to all users</span>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="maintenance_mode" <?= getSetting('maintenance_mode', '0') === '1' ? 'checked' : ''; ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Store URLs -->
                            <div class="card fade-in fade-in-delay-2" style="margin-top:20px;">
                                <div class="card-header">
                                    <h3 class="card-title"><i class="fas fa-store" style="margin-right:8px;color:#8B5CF6;"></i>Store URLs</h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label class="form-label"><i class="fab fa-google-play" style="margin-right:4px;color:#3DDC84;"></i> Android Store URL</label>
                                        <input type="text" name="android_store_url"
                                               value="<?= htmlspecialchars(getSetting('android_store_url', 'market://details?id=com.healthdial.mobile')); ?>"
                                               class="form-input" placeholder="market://details?id=com.healthdial.mobile">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fab fa-apple" style="margin-right:4px;color:#333;"></i> iOS Store URL</label>
                                        <input type="text" name="ios_store_url"
                                               value="<?= htmlspecialchars(getSetting('ios_store_url', 'https://apps.apple.com/app/id123456789')); ?>"
                                               class="form-input" placeholder="https://apps.apple.com/app/id...">
                                    </div>
                                </div>
                            </div>

                            <!-- Live Preview -->
                            <div class="card fade-in fade-in-delay-3" style="margin-top:20px;">
                                <div class="card-header">
                                    <h3 class="card-title"><i class="fas fa-eye" style="margin-right:8px;color:#10B981;"></i>Current Config Preview</h3>
                                </div>
                                <div class="card-body">
                                    <div class="live-preview">
                                        <h4>Active Configuration</h4>
                                        <div class="preview-item">Ads Master: <span><?= getSetting('ads_enabled', '1') === '1' ? '✅ ON' : '❌ OFF'; ?></span></div>
                                        <div class="preview-item">Banners: <span><?= getSetting('banner_enabled', '1') === '1' ? '✅ ON' : '❌ OFF'; ?></span></div>
                                        <div class="preview-item">Interstitials: <span><?= getSetting('interstitial_enabled', '1') === '1' ? '✅ ON' : '❌ OFF'; ?></span></div>
                                        <div class="preview-item">Interval: <span>Every <?= htmlspecialchars(getSetting('interstitial_interval', '5')); ?> tabs</span></div>
                                        <div class="preview-item">App Version: <span>v<?= htmlspecialchars(getSetting('app_version', '1.0.0')); ?></span></div>
                                        <div class="preview-item">Maintenance: <span><?= getSetting('maintenance_mode', '0') === '1' ? '🔴 ON' : '🟢 OFF'; ?></span></div>
                                    </div>
                                    <div class="live-preview" style="margin-top:12px;">
                                        <h4>Android Ad Units</h4>
                                        <div class="preview-item" style="word-break:break-all;">Banner: <span><?= htmlspecialchars(getSetting('android_banner_ad_unit', '—')); ?></span></div>
                                        <div class="preview-item" style="word-break:break-all;">Interstitial: <span><?= htmlspecialchars(getSetting('android_interstitial_ad_unit', '—')); ?></span></div>
                                    </div>
                                    <div class="live-preview" style="margin-top:12px;">
                                        <h4>iOS Ad Units</h4>
                                        <div class="preview-item" style="word-break:break-all;">Banner: <span><?= htmlspecialchars(getSetting('ios_banner_ad_unit', '—')); ?></span></div>
                                        <div class="preview-item" style="word-break:break-all;">Interstitial: <span><?= htmlspecialchars(getSetting('ios_interstitial_ad_unit', '—')); ?></span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div style="margin-top:24px;text-align:center;">
                        <button type="submit" class="btn btn-primary" style="padding:12px 48px;font-size:15px;">
                            <i class="fas fa-save" style="margin-right:6px;"></i> Save All Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>