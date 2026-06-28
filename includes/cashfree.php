<?php
/**
 * Cashfree (PG v3) helper — shared by the QR-unlock and promotion flows.
 *
 * Flow: the server creates an order (POST /orders) and gets a payment_session_id,
 * then the browser launches Cashfree's hosted checkout via the v3 JS SDK. After
 * payment Cashfree redirects to our return_url; we confirm the result server-side
 * with Get Order (GET /orders/{id}) — never trusting the browser. A webhook is the
 * server-to-server backup.
 *
 * Credentials live in the `settings` table:
 *   cashfree_app_id       — x-client-id
 *   cashfree_secret_key   — x-client-secret
 *   cashfree_environment  — 'sandbox' or 'production'
 *
 * Every function takes a mysqli $conn so it works from either DB bootstrap
 * (website includes/db.php or the admin/API config.php).
 */

if (!function_exists('cashfree_get_config')) {

    if (!defined('CASHFREE_API_VERSION')) {
        define('CASHFREE_API_VERSION', '2023-08-01');
    }

    function cashfree_get_config(mysqli $conn): array
    {
        $cfg = ['app_id' => '', 'secret' => '', 'env' => 'production'];
        $res = $conn->query("SELECT setting_key, setting_value FROM settings
                             WHERE setting_key IN
                             ('cashfree_app_id','cashfree_secret_key','cashfree_environment')");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $v = trim((string) $row['setting_value']);
                switch ($row['setting_key']) {
                    case 'cashfree_app_id':      $cfg['app_id'] = $v; break;
                    case 'cashfree_secret_key':  $cfg['secret'] = $v; break;
                    case 'cashfree_environment': $cfg['env'] = ($v === 'sandbox') ? 'sandbox' : 'production'; break;
                }
            }
        }
        return $cfg;
    }

    function cashfree_is_configured(array $cfg): bool
    {
        return $cfg['app_id'] !== '' && $cfg['secret'] !== '';
    }

    /** REST base for the selected environment. */
    function cashfree_api_base(array $cfg): string
    {
        return $cfg['env'] === 'sandbox'
            ? 'https://sandbox.cashfree.com/pg'
            : 'https://api.cashfree.com/pg';
    }

    /** JS-SDK mode string for Cashfree({mode:...}). */
    function cashfree_sdk_mode(array $cfg): string
    {
        return $cfg['env'] === 'sandbox' ? 'sandbox' : 'production';
    }

    /** Unique, alphanumeric Cashfree order id (<= 45 chars; we stay well under). */
    function cashfree_generate_order_id(string $prefix = 'HD'): string
    {
        return substr($prefix . date('ymdHis') . bin2hex(random_bytes(4)), 0, 40);
    }

    /** Common cURL headers for authenticated PG calls. */
    function cashfree_headers(array $cfg): array
    {
        return [
            'Content-Type: application/json',
            'x-api-version: ' . CASHFREE_API_VERSION,
            'x-client-id: ' . $cfg['app_id'],
            'x-client-secret: ' . $cfg['secret'],
        ];
    }

    /**
     * Create an order. $order requires:
     *   order_id, amount, customer_id, customer_name, customer_phone, customer_email,
     *   return_url, notify_url, tags (assoc array, optional).
     * Returns ['ok'=>true, 'payment_session_id'=>..., 'order_id'=>...]
     *      or ['ok'=>false, 'error'=>'human message', 'raw'=>'…'].
     */
    function cashfree_create_order(array $cfg, array $order): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'cURL is not available on the server.'];
        }

        $phone = preg_replace('/\D/', '', (string) ($order['customer_phone'] ?? ''));
        if (strlen($phone) > 10) {
            $phone = substr($phone, -10);
        }
        if (strlen($phone) < 10) {
            $phone = '9999999999';
        }

        $email = filter_var($order['customer_email'] ?? '', FILTER_VALIDATE_EMAIL)
            ? $order['customer_email'] : 'noreply@healthdial.com';

        $body = [
            'order_id'       => $order['order_id'],
            'order_amount'   => round((float) $order['amount'], 2),
            'order_currency' => 'INR',
            'customer_details' => [
                'customer_id'    => (string) ($order['customer_id'] ?? ('guest_' . substr(md5($order['order_id']), 0, 12))),
                'customer_name'  => (string) ($order['customer_name'] ?? 'Customer'),
                'customer_email' => $email,
                'customer_phone' => $phone,
            ],
            'order_meta' => [
                'return_url' => $order['return_url'],
                'notify_url' => $order['notify_url'] ?? null,
            ],
        ];
        if (!empty($order['tags']) && is_array($order['tags'])) {
            // order_tags values must be strings.
            $body['order_tags'] = array_map('strval', $order['tags']);
        }

        $ch = curl_init(cashfree_api_base($cfg) . '/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => cashfree_headers($cfg),
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            error_log('Cashfree create order cURL error: ' . $err);
            return ['ok' => false, 'error' => 'Could not reach the payment gateway. Please try again.'];
        }

        $data = json_decode($resp, true);
        if ($code >= 200 && $code < 300 && !empty($data['payment_session_id'])) {
            return [
                'ok'                 => true,
                'payment_session_id' => $data['payment_session_id'],
                'order_id'           => $data['order_id'] ?? $order['order_id'],
            ];
        }

        $msg = $data['message'] ?? ('Payment gateway error (HTTP ' . $code . ').');
        error_log('Cashfree create order failed (' . $code . '): ' . $resp);
        return ['ok' => false, 'error' => $msg, 'raw' => $resp];
    }

    /** Get an order. Returns the decoded order array, or null on failure. */
    function cashfree_get_order(array $cfg, string $orderId): ?array
    {
        if (!function_exists('curl_init') || $orderId === '') {
            return null;
        }
        $ch = curl_init(cashfree_api_base($cfg) . '/orders/' . rawurlencode($orderId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => cashfree_headers($cfg),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $code < 200 || $code >= 300) {
            error_log('Cashfree get order failed (' . $code . ') for ' . $orderId . ': ' . $resp);
            return null;
        }
        $data = json_decode($resp, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Latest successful payment for an order, or null. Used to record the
     * cf_payment_id / payment method after a successful order.
     */
    function cashfree_get_successful_payment(array $cfg, string $orderId): ?array
    {
        if (!function_exists('curl_init') || $orderId === '') {
            return null;
        }
        $ch = curl_init(cashfree_api_base($cfg) . '/orders/' . rawurlencode($orderId) . '/payments');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => cashfree_headers($cfg),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $code < 200 || $code >= 300) {
            return null;
        }
        $payments = json_decode($resp, true);
        if (!is_array($payments)) {
            return null;
        }
        foreach ($payments as $p) {
            if (strtoupper($p['payment_status'] ?? '') === 'SUCCESS') {
                return $p;
            }
        }
        return null;
    }

    /**
     * Echo a self-launching page that opens Cashfree's hosted checkout for the
     * given payment session. Analogous to payu_render_redirect_form().
     */
    function cashfree_render_checkout(array $cfg, string $paymentSessionId): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        $mode    = cashfree_sdk_mode($cfg);
        $session = json_encode($paymentSessionId);
        $modeJs  = json_encode($mode);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Redirecting to secure payment…</title>';
        echo '<script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script></head><body>';
        echo '<p style="font-family:system-ui,sans-serif;text-align:center;margin-top:60px;color:#334155;">'
           . 'Redirecting to secure payment…</p>';
        echo '<script>(function(){try{var cashfree=Cashfree({mode:' . $modeJs . '});'
           . 'cashfree.checkout({paymentSessionId:' . $session . ',redirectTarget:"_self"});'
           . '}catch(e){document.body.innerHTML="<p style=\'font-family:system-ui,sans-serif;text-align:center;margin-top:60px;color:#b91c1c;\'>'
           . 'Unable to open the payment page. Please go back and try again.</p>";}})();</script>';
        echo '</body></html>';
    }
}
