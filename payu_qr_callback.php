<?php
/**
 * PayU success/failure callback for the QR-unlock flow.
 * PayU POSTs the payment result here (this is the surl AND furl).
 * We verify the reverse hash, mark the payment paid, then redirect back to
 * the listing (which renders the unlocked QR because $qrPaid is computed
 * server-side from listing_qr_payments).
 */
require_once 'includes/db.php';
require_once 'includes/payu.php';

$post   = $_POST;
$txnid  = trim((string) ($post['txnid'] ?? ''));
$status = strtolower(trim((string) ($post['status'] ?? '')));
$mihpayid = trim((string) ($post['mihpayid'] ?? ($post['payuMoneyId'] ?? '')));
$listingFromUdf = intval($post['udf1'] ?? 0);

$conn = getDbConnection();
if (!$conn || !$txnid) {
    header('Location: index.php');
    exit;
}

$cfg = payu_get_config($conn);

// Reject tampered/forged callbacks.
if (!payu_is_configured($cfg) || !payu_verify_response($cfg, $post)) {
    error_log('PayU QR callback hash verification failed for txnid ' . $txnid);
    $dest = $listingFromUdf ? ('listing-detail.php?id=' . $listingFromUdf . '&qr=error') : 'index.php';
    header('Location: ' . $dest);
    exit;
}

// Find the pending payment for this txnid.
$sel = $conn->prepare("SELECT listing_id, status FROM listing_qr_payments WHERE razorpay_order_id = ? LIMIT 1");
$sel->bind_param('s', $txnid);
$sel->execute();
$row = $sel->get_result()->fetch_assoc();
$sel->close();

$listing_id = intval($row['listing_id'] ?? $listingFromUdf);

if ($status === 'success') {
    $now = date('Y-m-d H:i:s');
    $upd = $conn->prepare("UPDATE listing_qr_payments
                           SET status='paid', razorpay_payment_id=?, paid_at=?
                           WHERE razorpay_order_id=? AND status='pending'");
    $upd->bind_param('sss', $mihpayid, $now, $txnid);
    $upd->execute();
    $affected = $upd->affected_rows;
    $upd->close();

    // Fallback insert if the pending row was missing (covers edge cases).
    if ($affected === 0 && $listing_id && (($row['status'] ?? '') !== 'paid')) {
        $amt = function_exists('hd_qr_price') ? hd_qr_price($conn) : 200;
        $ins = $conn->prepare("INSERT INTO listing_qr_payments
                               (listing_id, razorpay_order_id, razorpay_payment_id, amount, status, paid_at)
                               VALUES (?, ?, ?, ?, 'paid', ?)");
        $ins->bind_param('issds', $listing_id, $txnid, $mihpayid, $amt, $now);
        $ins->execute();
        $ins->close();
    }

    header('Location: listing-detail.php?id=' . $listing_id . '&qr=success');
    exit;
}

// Any non-success status → mark the pending row as failed-ish (leave as pending so it can be retried).
error_log('PayU QR payment not successful (status=' . $status . ') for txnid ' . $txnid);
$dest = $listing_id ? ('listing-detail.php?id=' . $listing_id . '&qr=failed') : 'index.php';
header('Location: ' . $dest);
exit;
