<?php
/**
 * QR-unlock payment — gateway dispatcher.
 *
 * Receives a listing_id, records a pending listing_qr_payments row, then hands
 * off to the active gateway (Cashfree or PayU — see hd_active_gateway()).
 *
 * The order id is stored in listing_qr_payments.razorpay_order_id (legacy column
 * name reused across gateways).
 */
require_once 'includes/db.php';
require_once 'includes/payments.php';
require_once 'includes/payu.php';
require_once 'includes/cashfree.php';
require_once 'includes/user_auth.php';

$listing_id = intval($_REQUEST['listing_id'] ?? 0);
if (!$listing_id) {
    http_response_code(400);
    exit('Invalid listing.');
}

// Only logged-in, phone-verified users/vendors can unlock the QR code (2-step).
$hdUser = hd_current_user();
if (!$hdUser) {
    header('Location: login.php?return=' . urlencode('listing-detail.php?id=' . $listing_id));
    exit;
}
if (empty($hdUser['phone_verified'])) {
    header('Location: profile.php?verify=required&return=' . urlencode('listing-detail.php?id=' . $listing_id));
    exit;
}
$user_id = (int) $hdUser['id'];

$conn = getDbConnection();
if (!$conn) {
    http_response_code(503);
    exit('Payment service is temporarily unavailable. Please try again.');
}

// Ensure the QR payments table exists (idempotent).
$conn->query("CREATE TABLE IF NOT EXISTS listing_qr_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    razorpay_order_id VARCHAR(255) NOT NULL,
    razorpay_payment_id VARCHAR(255) DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 200.00,
    status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    paid_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_listing_id (listing_id),
    INDEX idx_status (status)
)");
// NOTE: razorpay_order_id stores the gateway order id (PayU txnid / Cashfree order id),
// razorpay_payment_id stores the gateway payment id.

// Ensure the buyer-link column exists (idempotent / self-healing).
$qrColChk = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'listing_qr_payments' AND COLUMN_NAME = 'user_id'");
if (!$qrColChk || $qrColChk->num_rows === 0) {
    @$conn->query("ALTER TABLE listing_qr_payments ADD COLUMN user_id INT NULL");
}

// Already unlocked? Go straight back to the listing.
$paid = $conn->prepare("SELECT id FROM listing_qr_payments WHERE listing_id = ? AND status = 'paid' LIMIT 1");
$paid->bind_param('i', $listing_id);
$paid->execute();
if ($paid->get_result()->num_rows > 0) {
    $paid->close();
    header('Location: listing-detail.php?id=' . $listing_id . '&qr=already');
    exit;
}
$paid->close();

// Verify listing exists and grab prefill details.
$ls = $conn->prepare("SELECT name, mobile FROM listings WHERE id = ? LIMIT 1");
$ls->bind_param('i', $listing_id);
$ls->execute();
$listing = $ls->get_result()->fetch_assoc();
$ls->close();
if (!$listing) {
    http_response_code(404);
    exit('Listing not found.');
}

$gateway = hd_active_gateway($conn);

// Admin-configurable unlock price (settings key `qr_code_price`, default ₹200).
$qrPrice = hd_qr_price($conn);

// One order id, used for both the DB row and the gateway.
$orderId = ($gateway === 'payu')
    ? payu_generate_txnid('QR')
    : cashfree_generate_order_id('QR');

// Record a pending payment keyed by the order id.
$ins = $conn->prepare("INSERT INTO listing_qr_payments (user_id, listing_id, razorpay_order_id, amount, status)
                       VALUES (?, ?, ?, ?, 'pending')");
$ins->bind_param('iisd', $user_id, $listing_id, $orderId, $qrPrice);
$ins->execute();
$ins->close();

$custName  = preg_replace('/[^A-Za-z0-9 ]/', '', (string) ($listing['name'] ?? 'Customer'));
$custName  = trim($custName) !== '' ? trim($custName) : 'Customer';
$custPhone = preg_replace('/\D/', '', (string) ($listing['mobile'] ?? '')) ?: '9999999999';

/* ---------------- PayU ---------------- */
if ($gateway === 'payu') {
    $cfg = payu_get_config($conn);
    if (!payu_is_configured($cfg)) {
        http_response_code(500);
        exit('Payment gateway is not configured yet. Please contact support.');
    }
    $base = payu_base_url();
    $params = [
        'txnid'       => $orderId,
        'amount'      => payu_format_amount($qrPrice),
        'productinfo' => 'QR Code Unlock',
        'firstname'   => $custName,
        'email'       => 'noreply@healthdial.com',
        'phone'       => $custPhone,
        'surl'        => $base . '/payu_qr_callback.php',
        'furl'        => $base . '/payu_qr_callback.php',
        'udf1'        => (string) $listing_id,
        'udf2'        => 'qr',
    ];
    $params['hash'] = payu_request_hash($cfg, $params);
    payu_render_redirect_form($cfg, $params);
    exit;
}

/* ---------------- Cashfree ---------------- */
$cfg = cashfree_get_config($conn);
if (!cashfree_is_configured($cfg)) {
    http_response_code(500);
    exit('Payment gateway is not configured yet. Please contact support.');
}

$base = hd_payment_base_url($conn);
// Cashfree can't reach a localhost notify_url; only send one for a public base.
$baseIsLocal = (strpos($base, 'localhost') !== false || strpos($base, '127.0.0.1') !== false);

$res = cashfree_create_order($cfg, [
    'order_id'       => $orderId,
    'amount'         => $qrPrice,
    'customer_id'    => 'user_' . $user_id,
    'customer_name'  => $custName,
    'customer_phone' => $custPhone,
    'customer_email' => 'noreply@healthdial.com',
    'return_url'     => $base . '/cashfree_return.php?type=qr&order_id={order_id}',
    'notify_url'     => $baseIsLocal ? null : ($base . '/HealthDial/Backend/api/cashfree_webhook.php'),
    'tags'           => ['type' => 'qr', 'listing_id' => $listing_id],
]);

if (empty($res['ok'])) {
    http_response_code(502);
    exit('Could not start the payment: ' . htmlspecialchars($res['error'] ?? 'Unknown error') . '. Please try again.');
}

cashfree_render_checkout($cfg, $res['payment_session_id']);
exit;
