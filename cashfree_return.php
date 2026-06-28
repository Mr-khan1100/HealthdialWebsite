<?php
/**
 * Cashfree browser return handler (return_url) for both paid flows.
 *
 * Cashfree redirects the customer here after checkout with ?type=promotion|qr and
 * ?order_id=<order>. We confirm the result SERVER-SIDE via Get Order (never trust
 * the browser), then activate and redirect. The webhook is the server-to-server
 * backup; this return path is what makes localhost / no-webhook setups work.
 */
require_once 'includes/db.php';
require_once 'includes/cashfree.php';
require_once 'includes/promotion_activate.php';
require_once 'includes/qr_activate.php';

$type    = strtolower(trim((string) ($_GET['type'] ?? 'promotion')));
$orderId = trim((string) ($_GET['order_id'] ?? ''));

$conn = getDbConnection();
if (!$conn || $orderId === '') {
    header('Location: index.php');
    exit;
}

$cfg    = cashfree_get_config($conn);
$order  = cashfree_is_configured($cfg) ? cashfree_get_order($cfg, $orderId) : null;
$status = strtoupper((string) ($order['order_status'] ?? ''));
$isPaid = ($status === 'PAID');
// States that definitively mean "not paid" (so we can record a failure).
$isDefinitiveUnpaid = in_array($status, ['ACTIVE', 'EXPIRED', 'TERMINATED', 'TERMINATION_REQUESTED'], true);

// Payment id / method (best-effort) for the record.
$payId  = '';
if ($isPaid) {
    $pay = cashfree_get_successful_payment($cfg, $orderId);
    if ($pay) {
        $payId = (string) ($pay['cf_payment_id'] ?? '');
    }
}

/* ---------------- QR unlock ---------------- */
if ($type === 'qr') {
    if ($isPaid) {
        $listingId = hd_mark_qr_paid($conn, $orderId, $payId);
        header('Location: listing-detail.php?id=' . $listingId . '&qr=success');
        exit;
    }

    // Find the listing for a sensible redirect.
    $lid = 0;
    $sel = $conn->prepare("SELECT listing_id FROM listing_qr_payments WHERE razorpay_order_id = ? LIMIT 1");
    if ($sel) {
        $sel->bind_param('s', $orderId);
        $sel->execute();
        $row = $sel->get_result()->fetch_assoc();
        $sel->close();
        $lid = (int) ($row['listing_id'] ?? 0);
    }
    $dest = $lid ? ('listing-detail.php?id=' . $lid . '&qr=failed') : 'index.php';
    header('Location: ' . $dest);
    exit;
}

/* ---------------- Promotion ---------------- */
if ($isPaid) {
    payu_mark_promotion_paid($conn, $orderId, $payId, 'cashfree');
    header('Location: payment-success.php?order_id=' . urlencode($orderId) . '&status=success');
    exit;
}

// Only record a failure when the gateway says it's definitively unpaid; if we
// couldn't verify, leave the row PENDING so the webhook can still confirm it.
if ($isDefinitiveUnpaid) {
    $upd = $conn->prepare("UPDATE promotion_payments
                           SET payment_status='FAILED', payment_method='cashfree', updated_at=NOW()
                           WHERE cashfree_order_id=? AND payment_status<>'PAID'");
    $upd->bind_param('s', $orderId);
    $upd->execute();
    $upd->close();
}

header('Location: payment-success.php?order_id=' . urlencode($orderId) . '&status=failed');
exit;
