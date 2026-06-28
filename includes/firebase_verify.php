<?php
/**
 * Server-side verification of Firebase ID tokens.
 *
 * Firebase phone-OTP and Google sign-in both produce a signed (RS256) JWT.
 * We verify the signature against Google's public x509 certificates and check
 * the standard claims, so the server never has to trust unverified data from
 * the browser. No external libraries are required (uses openssl + curl).
 *
 * Primary path : verify the JWT signature against Google's secure-token certs.
 * Fallback path: if openssl/cert fetch is unavailable, validate the token via
 *                Google's Identity Toolkit `accounts:lookup` REST endpoint.
 */

require_once __DIR__ . '/firebase_config.php';

const HD_FB_CERT_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

function hd_b64url_decode(string $data): string
{
    $data = strtr($data, '-_', '+/');
    $pad  = strlen($data) % 4;
    if ($pad) {
        $data .= str_repeat('=', 4 - $pad);
    }
    return base64_decode($data) ?: '';
}

/**
 * Fetch (and cache) Google's secure-token public certs as [kid => PEM].
 */
function hd_firebase_certs(): array
{
    $cacheFile = sys_get_temp_dir() . '/hd_firebase_certs.json';

    if (is_readable($cacheFile)) {
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached['expires']) && $cached['expires'] > time() && !empty($cached['certs'])) {
            return $cached['certs'];
        }
    }

    if (!function_exists('curl_init')) {
        return [];
    }

    $ch = curl_init(HD_FB_CERT_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        curl_close($ch);
        return [];
    }
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $rawHeaders = substr($resp, 0, $headerSize);
    $body       = substr($resp, $headerSize);
    $certs      = json_decode($body, true);
    if (!is_array($certs) || !$certs) {
        return [];
    }

    // Honour Cache-Control: max-age so we re-fetch when Google rotates keys.
    $maxAge = 3600;
    if (preg_match('/max-age=(\d+)/i', $rawHeaders, $m)) {
        $maxAge = max(60, (int) $m[1]);
    }
    @file_put_contents($cacheFile, json_encode(['expires' => time() + $maxAge, 'certs' => $certs]));

    return $certs;
}

/**
 * Verify a Firebase ID token. Returns the decoded claims on success, or null.
 *
 * @return array{
 *   uid:string, email:?string, name:?string, phone_number:?string,
 *   picture:?string, provider:?string
 * }|null
 */
function hd_verify_firebase_id_token(string $idToken, string $projectId): ?array
{
    $idToken   = trim($idToken);
    $projectId = trim($projectId);
    if ($idToken === '' || $projectId === '') {
        return null;
    }

    $parts = explode('.', $idToken);
    if (count($parts) !== 3) {
        return null;
    }
    [$h64, $p64, $s64] = $parts;

    $header  = json_decode(hd_b64url_decode($h64), true);
    $payload = json_decode(hd_b64url_decode($p64), true);
    if (!is_array($header) || !is_array($payload)) {
        return null;
    }

    // --- Signature verification (primary) ---------------------------------
    $signatureOk = false;
    if (
        function_exists('openssl_verify')
        && ($header['alg'] ?? '') === 'RS256'
        && !empty($header['kid'])
    ) {
        $certs = hd_firebase_certs();
        $pem   = $certs[$header['kid']] ?? null;
        if ($pem) {
            $pubKey = openssl_pkey_get_public($pem);
            if ($pubKey) {
                $ok = openssl_verify($h64 . '.' . $p64, hd_b64url_decode($s64), $pubKey, OPENSSL_ALGO_SHA256);
                $signatureOk = ($ok === 1);
            }
        }
    }

    if ($signatureOk) {
        // --- Claim validation ---------------------------------------------
        $now = time();
        if (($payload['exp'] ?? 0) < $now - 60) {
            return null; // expired
        }
        if (($payload['iat'] ?? 0) > $now + 300) {
            return null; // issued in the future (clock skew guard)
        }
        if (($payload['aud'] ?? '') !== $projectId) {
            return null;
        }
        if (($payload['iss'] ?? '') !== 'https://securetoken.google.com/' . $projectId) {
            return null;
        }
        if (empty($payload['sub'])) {
            return null;
        }
        return hd_firebase_claims_from_payload($payload);
    }

    // --- Fallback: Identity Toolkit lookup --------------------------------
    return hd_firebase_lookup_fallback($idToken);
}

function hd_firebase_claims_from_payload(array $payload): array
{
    $provider = $payload['firebase']['sign_in_provider'] ?? null;
    return [
        'uid'            => (string) ($payload['sub'] ?? ''),
        'email'          => $payload['email'] ?? null,
        'email_verified' => !empty($payload['email_verified']),
        'name'           => $payload['name'] ?? null,
        'phone_number'   => $payload['phone_number'] ?? null,
        'picture'        => $payload['picture'] ?? null,
        'provider'       => $provider,
    ];
}

/**
 * Validate the token by asking Google to resolve it. Used only when local
 * signature verification is not possible (e.g. openssl missing). Requires the
 * Web API key from firebase_config.php.
 */
function hd_firebase_lookup_fallback(string $idToken): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $cfg    = hd_firebase_config();
    $apiKey = $cfg['apiKey'] ?? '';
    if ($apiKey === '' || strpos($apiKey, 'REPLACE_WITH') === 0) {
        return null;
    }

    $ch = curl_init('https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . urlencode($apiKey));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode(['idToken' => $idToken]),
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$resp) {
        return null;
    }
    $data = json_decode($resp, true);
    $u    = $data['users'][0] ?? null;
    if (!is_array($u) || empty($u['localId'])) {
        return null;
    }

    $provider = null;
    if (!empty($u['providerUserInfo'][0]['providerId'])) {
        $provider = $u['providerUserInfo'][0]['providerId'];
    }
    return [
        'uid'          => (string) $u['localId'],
        'email'        => $u['email'] ?? null,
        'name'         => $u['displayName'] ?? null,
        'phone_number' => $u['phoneNumber'] ?? null,
        'picture'      => $u['photoUrl'] ?? null,
        'provider'     => $provider,
    ];
}
