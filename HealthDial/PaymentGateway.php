<?php
require_once 'connection.inc.php';
requireLogin();

// Save handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $gateway = ($_POST['payment_gateway'] ?? 'cashfree') === 'payu' ? 'payu' : 'cashfree';

    $payFields = [
        'payment_gateway'      => $gateway,
        // Optional public base URL for gateway redirects (https tunnel for local
        // testing, or to pin the live domain behind a CDN). Blank = auto-detect.
        'payment_base_url'     => rtrim(trim($_POST['payment_base_url'] ?? ''), '/'),
        // Cashfree
        'cashfree_app_id'      => trim($_POST['cashfree_app_id'] ?? ''),
        'cashfree_secret_key'  => trim($_POST['cashfree_secret_key'] ?? ''),
        'cashfree_environment' => ($_POST['cashfree_environment'] ?? 'production') === 'sandbox' ? 'sandbox' : 'production',
        // PayU
        'payu_merchant_key'    => trim($_POST['payu_merchant_key'] ?? ''),
        'payu_salt'            => trim($_POST['payu_salt'] ?? ''),
        'payu_mode'            => ($_POST['payu_mode'] ?? 'production') === 'production' ? 'production' : 'test',
        'payu_salt_version'    => ($_POST['payu_salt_version'] ?? '1') === '2' ? '2' : '1',
    ];

    foreach ($payFields as $key => $value) {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->bind_param("sss", $key, $value, $value);
        $stmt->execute();
        $stmt->close();
    }

    $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, action, details, ip_address) VALUES (?, 'update_payment_gateway', ?, ?)");
    $details = 'Active payment gateway set to ' . strtoupper($gateway);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $logStmt->bind_param("iss", $_SESSION['admin_id'], $details, $ip);
    $logStmt->execute();
    $logStmt->close();

    $_SESSION['success'] = "Payment gateway settings saved! Active gateway: " . strtoupper($gateway);
    header("Location: PaymentGateway.php");
    exit();
}

// Load saved settings
$savedSettings = [];
$settingsRes = $conn->query("SELECT setting_key, setting_value FROM settings");
if ($settingsRes) {
    while ($s = $settingsRes->fetch_assoc()) {
        $savedSettings[$s['setting_key']] = $s['setting_value'];
    }
}
function pgSetting($key, $default = '')
{
    global $savedSettings;
    return $savedSettings[$key] ?? $default;
}

$activeGateway = (pgSetting('payment_gateway', 'cashfree') === 'payu') ? 'payu' : 'cashfree';

// Public URLs to show admins (for dashboard configuration).
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'healthdial.com';
$siteRoot = $scheme . '://' . $host;
$webhookUrl = $siteRoot . '/HealthDial/Backend/api/cashfree_webhook.php';
$returnUrl  = $siteRoot . '/cashfree_return.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Gateway — HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    .pg-toggle {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        margin-bottom: 8px;
    }

    .pg-option {
        position: relative;
        border: 2px solid rgba(0, 0, 0, 0.08);
        border-radius: 12px;
        padding: 18px 16px;
        cursor: pointer;
        transition: all .15s;
        display: flex;
        gap: 12px;
        align-items: flex-start;
    }

    .pg-option:hover {
        border-color: rgba(34, 197, 94, 0.45);
    }

    .pg-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .pg-option.is-active {
        border-color: #22c55e;
        background: rgba(34, 197, 94, 0.06);
        box-shadow: 0 4px 14px rgba(34, 197, 94, 0.12);
    }

    .pg-option .pg-radio {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 2px solid #cbd5e1;
        flex: 0 0 auto;
        margin-top: 2px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .pg-option.is-active .pg-radio {
        border-color: #22c55e;
    }

    .pg-option.is-active .pg-radio::after {
        content: '';
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #22c55e;
    }

    .pg-option h4 {
        margin: 0 0 4px;
        font-size: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .pg-option p {
        margin: 0;
        font-size: 12px;
        color: var(--text-muted);
        line-height: 1.4;
    }

    .pg-tag {
        font-size: 10px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 99px;
        background: rgba(34, 197, 94, 0.15);
        color: #16a34a;
    }

    .pg-tag.upi {
        background: rgba(43, 125, 233, 0.12);
        color: #2563eb;
    }

    .pg-url {
        font-size: 11px;
        background: rgba(0, 0, 0, 0.05);
        padding: 8px 10px;
        border-radius: 6px;
        display: block;
        word-break: break-all;
        margin-top: 4px;
    }
    </style>
</head>

<body>
    <div class="admin-wrapper">
        <?php include 'sidebar.php'; ?>

        <div class="admin-main">
            <?php include 'header.php'; ?>

            <div class="admin-content">
                <div class="page-header">
                    <h1 class="page-title">Payment Gateway</h1>
                    <p class="page-subtitle">Choose the active gateway and manage credentials. Switching is instant — no
                        code changes needed.</p>
                </div>

                <form method="POST">
                    <input type="hidden" name="update_payment" value="1">

                    <!-- Active gateway -->
                    <div class="card fade-in">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title"><i class="fas fa-toggle-on"
                                        style="margin-right:8px;color:#22c55e;"></i>Active Gateway</h3>
                                <p style="font-size:12px;color:var(--text-muted);margin:0;">All website promotion & QR
                                    payments run through the selected gateway.</p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="pg-toggle">
                                <label class="pg-option <?php echo $activeGateway === 'cashfree' ? 'is-active' : ''; ?>"
                                    data-gw="cashfree">
                                    <input type="radio" name="payment_gateway" value="cashfree" <?php echo $activeGateway === 'cashfree' ? 'checked' : ''; ?>>
                                    <span class="pg-radio"></span>
                                    <span>
                                        <h4>Cashfree <span class="pg-tag upi">UPI</span></h4>
                                        <p>Supports UPI, cards, netbanking & wallets. Recommended.</p>
                                    </span>
                                </label>
                                <label class="pg-option <?php echo $activeGateway === 'payu' ? 'is-active' : ''; ?>"
                                    data-gw="payu">
                                    <input type="radio" name="payment_gateway" value="payu" <?php echo $activeGateway === 'payu' ? 'checked' : ''; ?>>
                                    <span class="pg-radio"></span>
                                    <span>
                                        <h4>PayU</h4>
                                        <p>Hosted checkout (cards/netbanking). Kept available to switch back anytime.</p>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">
                        <!-- Cashfree credentials -->
                        <div class="card fade-in">
                            <div class="card-header">
                                <div>
                                    <h3 class="card-title"><i class="fas fa-credit-card"
                                            style="margin-right:8px;color:#22c55e;"></i>Cashfree Credentials</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-key"
                                            style="margin-right:4px;color:#f59e0b;"></i> App ID (x-client-id)</label>
                                    <input type="text" name="cashfree_app_id"
                                        value="<?php echo htmlspecialchars(pgSetting('cashfree_app_id')); ?>"
                                        class="form-input" placeholder="Enter Cashfree App ID">
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-lock"
                                            style="margin-right:4px;color:#ef4444;"></i> Secret Key
                                        (x-client-secret)</label>
                                    <input type="password" name="cashfree_secret_key"
                                        value="<?php echo htmlspecialchars(pgSetting('cashfree_secret_key')); ?>"
                                        class="form-input" placeholder="Enter Cashfree Secret Key">
                                    <small style="color:var(--text-muted);font-size:11px;">Never share this key
                                        publicly.</small>
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-server"
                                            style="margin-right:4px;"></i> Environment</label>
                                    <select name="cashfree_environment" class="form-select">
                                        <option value="sandbox" <?php echo pgSetting('cashfree_environment', 'production') === 'sandbox' ? 'selected' : ''; ?>>Sandbox (Testing)</option>
                                        <option value="production" <?php echo pgSetting('cashfree_environment', 'production') === 'production' ? 'selected' : ''; ?>>Production (Live)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- PayU credentials -->
                        <div class="card fade-in fade-in-delay-1">
                            <div class="card-header">
                                <div>
                                    <h3 class="card-title"><i class="fas fa-credit-card"
                                            style="margin-right:8px;color:#6366f1;"></i>PayU Credentials</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-key"
                                            style="margin-right:4px;color:#f59e0b;"></i> Merchant Key</label>
                                    <input type="text" name="payu_merchant_key"
                                        value="<?php echo htmlspecialchars(pgSetting('payu_merchant_key')); ?>"
                                        class="form-input" placeholder="Enter PayU Merchant Key">
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-lock"
                                            style="margin-right:4px;color:#ef4444;"></i> Salt</label>
                                    <input type="password" name="payu_salt"
                                        value="<?php echo htmlspecialchars(pgSetting('payu_salt')); ?>"
                                        class="form-input" placeholder="Enter PayU Salt">
                                </div>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                    <div class="form-group">
                                        <label class="form-label">Mode</label>
                                        <select name="payu_mode" class="form-select">
                                            <option value="test" <?php echo pgSetting('payu_mode', 'production') === 'test' ? 'selected' : ''; ?>>Test</option>
                                            <option value="production" <?php echo pgSetting('payu_mode', 'production') === 'production' ? 'selected' : ''; ?>>Production</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Salt Version</label>
                                        <select name="payu_salt_version" class="form-select">
                                            <option value="1" <?php echo pgSetting('payu_salt_version', '1') === '1' ? 'selected' : ''; ?>>v1 (SHA-512)</option>
                                            <option value="2" <?php echo pgSetting('payu_salt_version', '1') === '2' ? 'selected' : ''; ?>>v2 (HMAC)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Webhook / return URLs -->
                    <div class="card fade-in fade-in-delay-2" style="margin-top:20px;">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title"><i class="fas fa-link"
                                        style="margin-right:8px;color:var(--accent);"></i>Cashfree Dashboard URLs</h3>
                                <p style="font-size:12px;color:var(--text-muted);margin:0;">Add the webhook URL in
                                    Cashfree Dashboard → Developers → Webhooks. The return URL is set automatically per
                                    order.</p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-globe" style="margin-right:4px;"></i> Payment
                                    base URL <span style="font-weight:400;text-transform:none;">(optional)</span></label>
                                <input type="text" name="payment_base_url"
                                    value="<?php echo htmlspecialchars(pgSetting('payment_base_url')); ?>"
                                    class="form-input" placeholder="<?php echo htmlspecialchars($siteRoot); ?>">
                                <small style="color:var(--text-muted);font-size:11px;">Leave blank in production
                                    (auto-detected). For local testing set an <strong>https</strong> tunnel URL (e.g.
                                    ngrok) — Cashfree rejects http return URLs.</small>
                            </div>
                            <label class="form-label" style="font-size:12px;">Webhook URL</label>
                            <code class="pg-url"><?php echo htmlspecialchars($webhookUrl); ?></code>
                            <label class="form-label" style="font-size:12px;margin-top:12px;">Return URL (auto)</label>
                            <code class="pg-url"><?php echo htmlspecialchars($returnUrl); ?>?type=promotion&amp;order_id={order_id}</code>
                        </div>
                    </div>

                    <div style="margin-top:20px;">
                        <button type="submit" class="btn btn-primary" style="width:100%;background:#22c55e;">
                            <i class="fas fa-save"></i> Save Payment Gateway Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Reflect the selected gateway visually.
    document.querySelectorAll('.pg-option input[name="payment_gateway"]').forEach(function(inp) {
        inp.addEventListener('change', function() {
            document.querySelectorAll('.pg-option').forEach(function(o) {
                o.classList.toggle('is-active', o.querySelector('input').checked);
            });
        });
    });
    </script>
</body>

</html>
