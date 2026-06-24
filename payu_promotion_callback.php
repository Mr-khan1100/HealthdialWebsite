<?php
/**
 * PayU success/failure callback for the promotion flow (surl AND furl).
 * Verifies the reverse hash, activates the promotion on success, then sends the
 * user to payment-success.php which displays the stored result.
 */
require_once 'includes/db.php';
require_once 'includes/payu.php';
require_once 'includes/promotion_activate.php';

$post     = $_POST;
$txnid    = trim((string) ($post['txnid'] ?? ''));
$status   = strtolower(trim((string) ($post['status'] ?? '')));
$mihpayid = trim((string) ($post['mihpayid'] ?? ($post['payuMoneyId'] ?? '')));

$conn = getDbConnection();
if (!$conn || !$txnid) {
    header('Location: promote.php');
    exit;
}

$cfg = payu_get_config($conn);

// Reject tampered/forged callbacks.
if (!payu_is_configured($cfg) || !payu_verify_response($cfg, $post)) {
    error_log('PayU promotion callback hash verification failed for txnid ' . $txnid);
    header('Location: payment-success.php?order_id=' . urlencode($txnid) . '&status=error');
    exit;
}

if ($status === 'success') {
    payu_mark_promotion_paid($conn, $txnid, $mihpayid, 'payu');
    header('Location: payment-success.php?order_id=' . urlencode($txnid) . '&status=success');
    exit;
}

// Non-success → record failure (unless already paid via webhook race).
$upd = $conn->prepare("UPDATE promotion_payments
                       SET payment_status='FAILED', cf_payment_id=?, payment_method='payu', updated_at=NOW()
                       WHERE cashfree_order_id=? AND payment_status<>'PAID'");
$upd->bind_param('ss', $mihpayid, $txnid);
$upd->execute();
$upd->close();

header('Location: payment-success.php?order_id=' . urlencode($txnid) . '&status=failed');
exit;
