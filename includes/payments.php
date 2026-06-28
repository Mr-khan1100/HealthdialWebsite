<?php
/**
 * Payment-gateway switch.
 *
 * One setting, `payment_gateway`, decides which gateway the website's paid flows
 * (listing promotion + QR unlock) run through. Admins flip it on the
 * "Payment Gateway" admin page — no code changes needed to switch.
 *
 *   payment_gateway = 'cashfree'  (default) | 'payu'
 *
 * Credentials for each gateway live in their own settings keys and are read by
 * includes/cashfree.php (cashfree_*) and includes/payu.php (payu_*).
 */

if (!function_exists('hd_active_gateway')) {

    /** The currently selected gateway: 'cashfree' (default) or 'payu'. */
    function hd_active_gateway(mysqli $conn): string
    {
        $gw = 'cashfree';
        $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'payment_gateway' LIMIT 1");
        if ($res && ($row = $res->fetch_assoc())) {
            $v = strtolower(trim((string) $row['setting_value']));
            if (in_array($v, ['cashfree', 'payu'], true)) {
                $gw = $v;
            }
        }
        return $gw;
    }

    /**
     * Absolute base URL (scheme://host[/subdir]) used to build gateway
     * return/callback URLs.
     *
     * Cashfree REQUIRES https return/notify URLs, and the live site is always
     * https (sometimes behind a CDN/proxy that hands PHP plain http). So:
     *   1. If the admin set a `payment_base_url` override (e.g. an https tunnel
     *      for local testing, or to pin the public domain), use it verbatim.
     *   2. Otherwise derive from the request, honouring X-Forwarded-Proto and
     *      forcing https for any non-localhost host.
     */
    function hd_payment_base_url(?mysqli $conn = null): string
    {
        // 1) Admin override (Payment Gateway page → "Payment base URL").
        if ($conn) {
            $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key='payment_base_url' LIMIT 1");
            if ($res && ($row = $res->fetch_assoc())) {
                $override = rtrim(trim((string) $row['setting_value']), '/');
                if ($override !== '') {
                    return $override;
                }
            }
        }

        // 2) Derive from the request.
        $proto = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443)
            || $proto === 'https';

        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $isLocal = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);

        // Force https for real domains even if the CDN handed us http.
        $scheme = ($isHttps || !$isLocal) ? 'https' : 'http';
        $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        return $scheme . '://' . $host . $dir;
    }
}
