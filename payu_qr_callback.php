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
require_once 'includes/qr_activate.php';

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

if ($status === 'success') {
    // Shared, idempotent activation (also used by the Cashfree flows).
    $listing_id = hd_mark_qr_paid($conn, $txnid, $mihpayid, $listingFromUdf);
    header('Location: listing-detail.php?id=' . $listing_id . '&qr=success');
    exit;
}

// Any non-success status → leave the row pending (so it can be retried) and send
// the user back to the listing with a failure flag.
$listing_id = $listingFromUdf;
$sel = $conn->prepare("SELECT listing_id FROM listing_qr_payments WHERE razorpay_order_id = ? LIMIT 1");
if ($sel) {
    $sel->bind_param('s', $txnid);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $sel->close();
    if (!empty($row['listing_id'])) {
        $listing_id = (int) $row['listing_id'];
    }
}
error_log('PayU QR payment not successful (status=' . $status . ') for txnid ' . $txnid);
$dest = $listing_id ? ('listing-detail.php?id=' . $listing_id . '&qr=failed') : 'index.php';
header('Location: ' . $dest);
exit;
