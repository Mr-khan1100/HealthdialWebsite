<?php
/**
 * PayU server-to-server webhook (promotion flow).
 * Configure this URL in the PayU dashboard as the "Webhook / S2S callback":
 *   https://healthdial.com/payu_webhook.php
 *
 * PayU POSTs the same fields it sends to surl (incl. a reverse hash). This is a
 * reliability backup for the case where the user closes the browser before the
 * surl redirect completes. Idempotent with payu_promotion_callback.php.
 */
require_once 'includes/db.php';
require_once 'includes/payu.php';
require_once 'includes/promotion_activate.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$post     = $_POST;
$txnid    = trim((string) ($post['txnid'] ?? ''));
$status   = strtolower(trim((string) ($post['status'] ?? '')));
$mihpayid = trim((string) ($post['mihpayid'] ?? ($post['payuMoneyId'] ?? '')));

$conn = getDbConnection();
if (!$conn || !$txnid) {
    http_response_code(400);
    exit('Bad request');
}

$cfg = payu_get_config($conn);
if (!payu_is_configured($cfg) || !payu_verify_response($cfg, $post)) {
    error_log('PayU webhook hash verification failed for txnid ' . $txnid);
    http_response_code(401);
    exit('Invalid signature');
}

// Best-effort log for debugging/audits.
$logDir = __DIR__ . '/HealthDial/Backend/uploads/webhook_logs/';
if (@is_dir($logDir) || @mkdir($logDir, 0755, true)) {
    @file_put_contents(
        $logDir . date('Y-m-d_H-i-s') . '_payu_' . preg_replace('/[^a-z0-9]/i', '', $txnid) . '.json',
        json_encode($post)
    );
}

if ($status === 'success') {
    payu_mark_promotion_paid($conn, $txnid, $mihpayid, 'payu');
} else {
    $upd = $conn->prepare("UPDATE promotion_payments
                           SET payment_status='FAILED', cf_payment_id=?, payment_method='payu', updated_at=NOW()
                           WHERE cashfree_order_id=? AND payment_status<>'PAID'");
    $upd->bind_param('ss', $mihpayid, $txnid);
    $upd->execute();
    $upd->close();
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
