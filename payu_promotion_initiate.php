<?php
/**
 * Listing promotion payment — PayU hosted checkout.
 * Receives the promotion form (listing_id, plan_id, customer details), records a
 * pending promotion_payments row, then redirects the browser to PayU.
 */
require_once 'includes/db.php';
require_once 'includes/payu.php';

$listing_id     = intval($_REQUEST['listing_id'] ?? 0);
$plan_id        = intval($_REQUEST['plan_id'] ?? 0);
$customer_name  = trim($_REQUEST['customer_name'] ?? '');
$customer_phone = preg_replace('/\D/', '', trim($_REQUEST['customer_phone'] ?? ''));
$customer_email = trim($_REQUEST['customer_email'] ?? '');

if (!$listing_id || !$plan_id || !$customer_name || strlen($customer_phone) < 10) {
    http_response_code(400);
    exit('Missing or invalid details. Please go back and try again.');
}

$conn = getDbConnection();
if (!$conn) {
    http_response_code(503);
    exit('Payment service is temporarily unavailable. Please try again.');
}

// Validate listing + plan.
$stmt = $conn->prepare("SELECT id, name FROM listings WHERE id = ? AND status = 'approved' LIMIT 1");
$stmt->bind_param('i', $listing_id);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$listing) {
    http_response_code(404);
    exit('Listing not found or not approved.');
}

$stmt = $conn->prepare("SELECT id, name, duration_days, price FROM highlight_plans WHERE id = ? AND is_active = 1 LIMIT 1");
$stmt->bind_param('i', $plan_id);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$plan) {
    http_response_code(404);
    exit('Promotion plan not found or inactive.');
}

$cfg = payu_get_config($conn);
if (!payu_is_configured($cfg)) {
    http_response_code(500);
    exit('Payment gateway is not configured yet. Please contact support.');
}

$amount = (float) $plan['price'];
$txnid  = payu_generate_txnid('PROMO');

// Record a pending payment. cashfree_order_id/cf_order_id reused to store our txnid.
$stmt = $conn->prepare("INSERT INTO promotion_payments
    (listing_id, plan_id, customer_name, customer_phone, customer_email, amount,
     cashfree_order_id, cf_order_id, payment_session_id, payment_status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', 'PENDING')");
$stmt->bind_param('iisssdss', $listing_id, $plan_id, $customer_name, $customer_phone,
    $customer_email, $amount, $txnid, $txnid);
$stmt->execute();
$stmt->close();

// Build PayU parameters.
$firstname = preg_replace('/[^A-Za-z0-9 ]/', '', $customer_name);
$firstname = trim($firstname) !== '' ? trim($firstname) : 'Customer';
$email     = filter_var($customer_email, FILTER_VALIDATE_EMAIL) ? $customer_email : 'noreply@healthdial.com';
$base      = payu_base_url();

$params = [
    'txnid'       => $txnid,
    'amount'      => payu_format_amount($amount),
    'productinfo' => 'Listing Promotion - ' . $plan['name'],
    'firstname'   => $firstname,
    'email'       => $email,
    'phone'       => $customer_phone,
    'surl'        => $base . '/payu_promotion_callback.php',
    'furl'        => $base . '/payu_promotion_callback.php',
    'udf1'        => (string) $listing_id,
    'udf2'        => (string) $plan_id,
    'udf3'        => 'promotion',
];
$params['hash'] = payu_request_hash($cfg, $params);

payu_render_redirect_form($cfg, $params);
