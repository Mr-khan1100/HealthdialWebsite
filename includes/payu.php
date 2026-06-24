<?php
/**
 * PayU (hosted checkout) helper — shared by the QR-unlock and promotion flows.
 *
 * PayU works by a server-signed form POST that redirects the browser to PayU's
 * hosted page; PayU then POSTs back to a success (surl) / failure (furl) URL
 * where we verify a reverse hash. There is no "create order" API call.
 *
 * Credentials live in the `settings` table:
 *   payu_merchant_key   — your PayU Merchant Key
 *   payu_salt           — your PayU Salt
 *   payu_mode           — 'test' (test.payu.in) or 'production' (secure.payu.in)
 *   payu_salt_version   — '1' = classic SHA-512 (default), '2' = HMAC-SHA-512
 *
 * Every function takes a mysqli $conn so it works from either DB bootstrap
 * (website includes/db.php or the admin/API config.php).
 */

if (!function_exists('payu_get_config')) {

    function payu_get_config(mysqli $conn): array
    {
        $cfg = ['key' => '', 'salt' => '', 'mode' => 'test', 'salt_version' => '1'];
        $res = $conn->query("SELECT setting_key, setting_value FROM settings
                             WHERE setting_key IN
                             ('payu_merchant_key','payu_salt','payu_mode','payu_salt_version')");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $v = trim((string) $row['setting_value']);
                switch ($row['setting_key']) {
                    case 'payu_merchant_key': $cfg['key']  = $v; break;
                    case 'payu_salt':         $cfg['salt'] = $v; break;
                    case 'payu_mode':         $cfg['mode'] = ($v === 'production') ? 'production' : 'test'; break;
                    case 'payu_salt_version': $cfg['salt_version'] = ($v === '2') ? '2' : '1'; break;
                }
            }
        }
        return $cfg;
    }

    function payu_is_configured(array $cfg): bool
    {
        return $cfg['key'] !== '' && $cfg['salt'] !== '';
    }

    function payu_payment_url(array $cfg): string
    {
        return $cfg['mode'] === 'production'
            ? 'https://secure.payu.in/_payment'
            : 'https://test.payu.in/_payment';
    }

    /** Absolute base URL (scheme://host[/subdir]) of the directory this script lives in. */
    function payu_base_url(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        return $scheme . '://' . $host . $dir;
    }

    /** Unique, alphanumeric PayU transaction id (max 25 chars). */
    function payu_generate_txnid(string $prefix = 'HD'): string
    {
        return substr($prefix . date('ymdHis') . bin2hex(random_bytes(4)), 0, 25);
    }

    /** Amount formatted exactly as it must appear in both the form and the hash. */
    function payu_format_amount($amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Request hash. Classic (salt v1):
     *   sha512(key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5||||||SALT)
     * v2 uses HMAC-SHA-512 over the same string (without the trailing salt), keyed by the salt.
     */
    function payu_request_hash(array $cfg, array $p): string
    {
        $core = implode('|', [
            $cfg['key'],
            $p['txnid'], $p['amount'], $p['productinfo'], $p['firstname'], $p['email'],
            $p['udf1'] ?? '', $p['udf2'] ?? '', $p['udf3'] ?? '', $p['udf4'] ?? '', $p['udf5'] ?? '',
            '', '', '', '', '',
        ]);
        if ($cfg['salt_version'] === '2') {
            return strtolower(hash_hmac('sha512', $core, $cfg['salt']));
        }
        return strtolower(hash('sha512', $core . '|' . $cfg['salt']));
    }

    /**
     * Verify the reverse hash PayU posts to surl/furl:
     *   sha512([additionalCharges|]SALT|status||||||udf5|udf4|udf3|udf2|udf1|email|firstname|productinfo|amount|txnid|key)
     * Uses the values exactly as PayU echoed them back.
     */
    function payu_verify_response(array $cfg, array $post): bool
    {
        $posted = strtolower(trim((string) ($post['hash'] ?? '')));
        if ($posted === '') {
            return false;
        }

        $core = implode('|', [
            $cfg['salt'],
            $post['status'] ?? '',
            '', '', '', '', '',
            $post['udf5'] ?? '', $post['udf4'] ?? '', $post['udf3'] ?? '', $post['udf2'] ?? '', $post['udf1'] ?? '',
            $post['email'] ?? '', $post['firstname'] ?? '', $post['productinfo'] ?? '',
            $post['amount'] ?? '', $post['txnid'] ?? '', $cfg['key'],
        ]);

        // PayU prefixes the reverse hash with additionalCharges when present.
        if (isset($post['additionalCharges']) && $post['additionalCharges'] !== '') {
            $core = $post['additionalCharges'] . '|' . $core;
        }

        if ($cfg['salt_version'] === '2') {
            // v2 reverse hash drops the leading salt (it becomes the HMAC key instead).
            $v2 = preg_replace('/^' . preg_quote($cfg['salt'], '/') . '\|/', '', $core);
            $computed = strtolower(hash_hmac('sha512', $v2, $cfg['salt']));
        } else {
            $computed = strtolower(hash('sha512', $core));
        }
        return hash_equals($computed, $posted);
    }

    /**
     * Echo a self-submitting HTML form that redirects the browser to PayU.
     * $params must already include a valid 'hash' from payu_request_hash().
     */
    function payu_render_redirect_form(array $cfg, array $params): void
    {
        $action = payu_payment_url($cfg);
        $fields = [
            'key'         => $cfg['key'],
            'txnid'       => $params['txnid'],
            'amount'      => $params['amount'],
            'productinfo' => $params['productinfo'],
            'firstname'   => $params['firstname'],
            'email'       => $params['email'],
            'phone'       => $params['phone'],
            'surl'        => $params['surl'],
            'furl'        => $params['furl'],
            'hash'        => $params['hash'],
            'udf1'        => $params['udf1'] ?? '',
            'udf2'        => $params['udf2'] ?? '',
            'udf3'        => $params['udf3'] ?? '',
            'udf4'        => $params['udf4'] ?? '',
            'udf5'        => $params['udf5'] ?? '',
        ];

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Redirecting to secure payment…</title></head><body>';
        echo '<form id="payuForm" method="post" action="' . htmlspecialchars($action, ENT_QUOTES) . '">';
        foreach ($fields as $k => $v) {
            echo '<input type="hidden" name="' . htmlspecialchars($k, ENT_QUOTES)
               . '" value="' . htmlspecialchars((string) $v, ENT_QUOTES) . '">';
        }
        echo '</form>';
        echo '<p style="font-family:system-ui,sans-serif;text-align:center;margin-top:60px;color:#334155;">'
           . 'Redirecting to secure payment…</p>';
        echo '<script>document.getElementById("payuForm").submit();</script>';
        echo '</body></html>';
    }
}
