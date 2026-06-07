<?php
/**
 * VAPID Key Generator — run ONCE, then delete this file from the server.
 * Access: /HealthDial/generate_vapid_keys.php?key=healthdial_vapid_setup
 */

$accessPassword = 'healthdial_vapid_setup';
if (!isset($_GET['key']) || $_GET['key'] !== $accessPassword) {
    http_response_code(403);
    exit('Access denied. Add ?key=healthdial_vapid_setup to the URL.');
}

if (!function_exists('openssl_pkey_new')) {
    exit('OpenSSL PHP extension is required but not found.');
}

// Generate EC P-256 key pair (required for VAPID / Web Push)
$privateKey = openssl_pkey_new([
    'curve_name'         => 'prime256v1',
    'private_key_type'   => OPENSSL_KEYTYPE_EC,
]);

if (!$privateKey) {
    exit('Failed to generate key pair. OpenSSL error: ' . openssl_error_string());
}

openssl_pkey_export($privateKey, $privateKeyPem);
$details = openssl_pkey_get_details($privateKey);

// x and y coordinates of the public key point
$x = str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
$y = str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);

// Uncompressed EC point: 0x04 || x || y
$uncompressedPoint = "\x04" . $x . $y;

function b64url($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$publicKeyBase64 = b64url($uncompressedPoint);

// Escape PEM for PHP single-quoted string
$pemEscaped = addslashes($privateKeyPem);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>VAPID Key Generator — HealthDial</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Courier New', monospace; background: #0f172a; color: #e2e8f0; padding: 40px 20px; max-width: 860px; margin: 0 auto; }
  h1  { color: #38bdf8; font-size: 1.5rem; margin-bottom: 6px; }
  p   { color: #94a3b8; font-size: 0.9rem; margin-bottom: 28px; }
  h2  { color: #7dd3fc; font-size: 1rem; margin: 24px 0 8px; }
  .box { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 16px; word-break: break-all; font-size: 12.5px; line-height: 1.7; white-space: pre-wrap; position: relative; }
  .warn { background: #78350f40; border-color: #f59e0b; color: #fbbf24; padding: 14px 16px; border-radius: 8px; font-family: sans-serif; font-size: 0.88rem; margin-bottom: 28px; }
  .step { background: #1e293b; border-left: 3px solid #38bdf8; padding: 12px 16px; margin-bottom: 10px; font-family: sans-serif; font-size: 0.85rem; color: #cbd5e1; }
  .step strong { color: #f1f5f9; }
  code { background: #0f172a; padding: 2px 6px; border-radius: 4px; font-size: 0.85em; }
</style>
</head>
<body>

<h1>HealthDial — VAPID Key Generator</h1>
<p>Generated a new EC P-256 key pair for Web Push Notifications (VAPID). Follow the steps below.</p>

<div class="warn">
  ⚠️ <strong>Security:</strong> Delete this file from the server after completing setup.
  The private key below must never be shared or committed to version control.
</div>

<h2>Step 1 — Copy this block into <code>includes/vapid_config.php</code></h2>
<div class="box">&lt;?php
define('VAPID_SUBJECT', 'mailto:admin@healthdial.com');
define('VAPID_PRIVATE_KEY_PEM', '<?= htmlspecialchars($pemEscaped) ?>');
define('VAPID_PUBLIC_KEY_BASE64', '<?= htmlspecialchars($publicKeyBase64) ?>');</div>

<h2>Step 2 — Run the SQL migration on your database</h2>
<div class="box">-- File: db/migrations/create_web_push_subscriptions.sql
-- Run this in phpMyAdmin or via CLI on <?= htmlspecialchars('u961861187_HealthDialNew') ?></div>

<h2>Step 3 — Verify</h2>
<div class="step"><strong>Public key (65 bytes, base64url):</strong><br><?= htmlspecialchars($publicKeyBase64) ?></div>
<div class="step">This public key is automatically injected into <code>window.HD_PUSH_CONFIG.vapidPublicKey</code> via <code>includes/footer.php</code>.</div>

<h2>Step 4 — Delete this file</h2>
<div class="step">SSH into the server and run: <code>rm public_html/HealthDial/generate_vapid_keys.php</code></div>

</body>
</html>
