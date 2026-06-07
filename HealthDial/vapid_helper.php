<?php
/**
 * VAPID Web Push Helper
 *
 * Sends browser Web Push Notifications using VAPID authentication (RFC 8292).
 * No external libraries required — uses PHP's built-in OpenSSL extension.
 *
 * Strategy: sends a signal-only push (no encrypted payload). The service worker
 * receives the push event and fetches the latest notification content from the API.
 */

function _vp_b64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Convert a DER-encoded ECDSA signature to raw R||S format (64 bytes for P-256).
 * PHP's openssl_sign() returns DER; the VAPID JWT spec requires raw R||S.
 */
function _vp_der_to_raw($der) {
    $pos = 0;

    // SEQUENCE tag
    if (strlen($der) < 2 || ord($der[$pos++]) !== 0x30) return false;

    // SEQUENCE length (skip it)
    $seqLen = ord($der[$pos++]);
    if ($seqLen & 0x80) {
        $pos += ($seqLen & 0x7f); // skip extended length bytes
    }

    // INTEGER R
    if (!isset($der[$pos]) || ord($der[$pos++]) !== 0x02) return false;
    $rLen = ord($der[$pos++]);
    $r    = substr($der, $pos, $rLen);
    $pos += $rLen;

    // INTEGER S
    if (!isset($der[$pos]) || ord($der[$pos++]) !== 0x02) return false;
    $sLen = ord($der[$pos++]);
    $s    = substr($der, $pos, $sLen);

    // Strip leading 0x00 byte (present when high bit is set in positive integer)
    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");

    // Pad to exactly 32 bytes
    $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
    $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

    return $r . $s;
}

/**
 * Create a VAPID JWT for the given push endpoint.
 * The JWT audience is derived from the endpoint URL (scheme + host).
 */
function _vp_create_jwt($endpoint, $privateKeyPem, $publicKeyBase64, $subject) {
    $parsed   = parse_url($endpoint);
    $audience = $parsed['scheme'] . '://' . $parsed['host'];

    $header  = _vp_b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payload = _vp_b64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 43200, // 12 hours
        'sub' => $subject,
    ]));

    $signingInput = $header . '.' . $payload;

    $privateKey = openssl_pkey_get_private($privateKeyPem);
    if (!$privateKey) {
        error_log('[VAPID] openssl_pkey_get_private failed: ' . openssl_error_string());
        return false;
    }

    if (!openssl_sign($signingInput, $derSig, $privateKey, OPENSSL_ALGO_SHA256)) {
        error_log('[VAPID] openssl_sign failed: ' . openssl_error_string());
        return false;
    }

    $rawSig = _vp_der_to_raw($derSig);
    if (!$rawSig) {
        error_log('[VAPID] DER→raw signature conversion failed');
        return false;
    }

    return $signingInput . '.' . _vp_b64url_encode($rawSig);
}

/**
 * Send a signal-only (no encrypted payload) web push to a single endpoint.
 * Returns ['success' => bool, 'http_code' => int, 'response' => string].
 */
function sendSingleWebPush($endpoint, $privateKeyPem, $publicKeyBase64, $subject) {
    $jwt = _vp_create_jwt($endpoint, $privateKeyPem, $publicKeyBase64, $subject);
    if (!$jwt) {
        return ['success' => false, 'http_code' => 0, 'response' => 'JWT creation failed'];
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => '',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: vapid t=' . $jwt . ',k=' . $publicKeyBase64,
            'Content-Type: application/octet-stream',
            'Content-Length: 0',
            'TTL: 86400',
            'Urgency: normal',
        ],
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('[VAPID] cURL error for ' . substr($endpoint, 0, 60) . ': ' . $curlError);
    }

    return [
        'success'   => in_array($httpCode, [200, 201, 202]),
        'http_code' => $httpCode,
        'response'  => $response,
    ];
}

/**
 * Send web push to all subscribed browsers.
 * Called alongside sendPushNotificationToAll() when admin triggers a notification.
 */
function sendWebPushToAll($conn) {
    // Load VAPID config (kept outside web root logic, one level up)
    $configPath = __DIR__ . '/../includes/vapid_config.php';
    if (!file_exists($configPath)) {
        error_log('[VAPID] vapid_config.php not found — skipping web push');
        return ['success' => false, 'error' => 'config missing'];
    }

    require_once $configPath;

    if (!defined('VAPID_PRIVATE_KEY_PEM') || !VAPID_PRIVATE_KEY_PEM) {
        error_log('[VAPID] Keys not configured in vapid_config.php — skipping web push');
        return ['success' => false, 'error' => 'keys not configured'];
    }

    $result = $conn->query("SELECT id, endpoint FROM web_push_subscriptions");
    if (!$result) {
        error_log('[VAPID] DB query failed: ' . $conn->error);
        return ['success' => false, 'error' => 'db error'];
    }

    $rows = $result->fetch_all(MYSQLI_ASSOC);
    if (empty($rows)) {
        error_log('[VAPID] No web push subscriptions found');
        return ['success' => true, 'sent' => 0, 'failed' => 0, 'cleaned' => 0];
    }

    $privateKeyPem    = VAPID_PRIVATE_KEY_PEM;
    $publicKeyBase64  = VAPID_PUBLIC_KEY_BASE64;
    $subject          = defined('VAPID_SUBJECT') ? VAPID_SUBJECT : 'mailto:admin@healthdial.com';

    $sent = $failed = $cleaned = 0;

    foreach ($rows as $row) {
        $pushResult = sendSingleWebPush($row['endpoint'], $privateKeyPem, $publicKeyBase64, $subject);

        if ($pushResult['success']) {
            $sent++;
        } else {
            $failed++;
            $httpCode = $pushResult['http_code'];
            error_log("[VAPID] Push failed for subscription {$row['id']}: HTTP {$httpCode}");

            // 404/410 = subscription expired or unsubscribed — remove it
            if (in_array($httpCode, [404, 410])) {
                $del = $conn->prepare("DELETE FROM web_push_subscriptions WHERE id = ?");
                if ($del) {
                    $del->bind_param("i", $row['id']);
                    $del->execute();
                    $del->close();
                    $cleaned++;
                    error_log("[VAPID] Removed expired subscription: {$row['id']}");
                }
            }
        }
    }

    error_log("[VAPID] Web push complete: {$sent} sent, {$failed} failed, {$cleaned} cleaned");
    return ['success' => true, 'sent' => $sent, 'failed' => $failed, 'cleaned' => $cleaned];
}
