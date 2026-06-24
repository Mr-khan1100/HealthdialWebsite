<?php
/**
 * QR-unlock payment (₹200) — PayU hosted checkout.
 * Receives a listing_id (form POST/GET), records a pending payment, then
 * redirects the browser to PayU via a self-submitting form.
 */
require_once 'includes/db.php';
require_once 'includes/payu.php';

$listing_id = intval($_REQUEST['listing_id'] ?? 0);
if (!$listing_id) {
    http_response_code(400);
    exit('Invalid listing.');
}

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
// NOTE: the razorpay_* columns are reused to store PayU values
// (razorpay_order_id = PayU txnid, razorpay_payment_id = PayU mihpayid).

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

$cfg = payu_get_config($conn);
if (!payu_is_configured($cfg)) {
    http_response_code(500);
    exit('Payment gateway is not configured yet. Please contact support.');
}

// Admin-configurable unlock price (settings key `qr_code_price`, default ₹200).
$qrPrice = hd_qr_price($conn);

// Record a pending payment keyed by our PayU txnid.
$txnid = payu_generate_txnid('QR');
$ins = $conn->prepare("INSERT INTO listing_qr_payments (listing_id, razorpay_order_id, amount, status)
                       VALUES (?, ?, ?, 'pending')");
$ins->bind_param('isd', $listing_id, $txnid, $qrPrice);
$ins->execute();
$ins->close();

// Build PayU parameters.
$firstname = preg_replace('/[^A-Za-z0-9 ]/', '', (string) ($listing['name'] ?? 'Customer'));
$firstname = trim($firstname) !== '' ? trim($firstname) : 'Customer';
$phone     = preg_replace('/\D/', '', (string) ($listing['mobile'] ?? '')) ?: '9999999999';
$email     = 'noreply@healthdial.com';
$base      = payu_base_url();

$params = [
    'txnid'       => $txnid,
    'amount'      => payu_format_amount($qrPrice),
    'productinfo' => 'QR Code Unlock',
    'firstname'   => $firstname,
    'email'       => $email,
    'phone'       => $phone,
    'surl'        => $base . '/payu_qr_callback.php',
    'furl'        => $base . '/payu_qr_callback.php',
    'udf1'        => (string) $listing_id,
    'udf2'        => 'qr',
];
$params['hash'] = payu_request_hash($cfg, $params);

payu_render_redirect_form($cfg, $params);
