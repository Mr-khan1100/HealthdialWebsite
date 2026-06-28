<?php
/**
 * Listing-promotion payment — gateway dispatcher.
 *
 * Receives the promotion form (listing_id, plan_id, customer details), records a
 * pending promotion_payments row, then hands off to the active gateway
 * (Cashfree or PayU — see the "Payment Gateway" admin page / hd_active_gateway()).
 *
 * The order id is stored in promotion_payments.cashfree_order_id (+ cf_order_id);
 * both gateways key their lookups off it.
 */
require_once 'includes/db.php';
require_once 'includes/payments.php';
require_once 'includes/payu.php';
require_once 'includes/cashfree.php';
require_once 'includes/user_auth.php';

// Only logged-in, phone-verified users/vendors can pay for a promotion (2-step).
$hdUser = hd_current_user();
if (!$hdUser) {
    header('Location: login.php?return=' . urlencode('promote.php'));
    exit;
}
if (empty($hdUser['phone_verified'])) {
    header('Location: profile.php?verify=required&return=' . urlencode('promote.php'));
    exit;
}
$user_id = (int) $hdUser['id'];

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

$gateway = hd_active_gateway($conn);
$amount  = (float) $plan['price'];

// Ensure the buyer-link column exists (idempotent / self-healing).
$colChk = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'promotion_payments' AND COLUMN_NAME = 'user_id'");
if (!$colChk || $colChk->num_rows === 0) {
    @$conn->query("ALTER TABLE promotion_payments ADD COLUMN user_id INT NULL");
}

// One order id, used for both the DB row and the gateway.
$orderId = ($gateway === 'payu')
    ? payu_generate_txnid('PROMO')
    : cashfree_generate_order_id('PROMO');

// Record a pending payment (order id stored in cashfree_order_id + cf_order_id).
$stmt = $conn->prepare("INSERT INTO promotion_payments
    (user_id, listing_id, plan_id, customer_name, customer_phone, customer_email, amount,
     cashfree_order_id, cf_order_id, payment_session_id, payment_status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '', 'PENDING')");
$stmt->bind_param('iiisssdss', $user_id, $listing_id, $plan_id, $customer_name, $customer_phone,
    $customer_email, $amount, $orderId, $orderId);
$stmt->execute();
$stmt->close();

/* ---------------- PayU ---------------- */
if ($gateway === 'payu') {
    $cfg = payu_get_config($conn);
    if (!payu_is_configured($cfg)) {
        http_response_code(500);
        exit('Payment gateway is not configured yet. Please contact support.');
    }

    $firstname = preg_replace('/[^A-Za-z0-9 ]/', '', $customer_name);
    $firstname = trim($firstname) !== '' ? trim($firstname) : 'Customer';
    $email     = filter_var($customer_email, FILTER_VALIDATE_EMAIL) ? $customer_email : 'noreply@healthdial.com';
    $base      = payu_base_url();

    $params = [
        'txnid'       => $orderId,
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
    'amount'         => $amount,
    'customer_id'    => 'user_' . $user_id,
    'customer_name'  => $customer_name,
    'customer_phone' => $customer_phone,
    'customer_email' => $customer_email,
    'return_url'     => $base . '/cashfree_return.php?type=promotion&order_id={order_id}',
    'notify_url'     => $baseIsLocal ? null : ($base . '/HealthDial/Backend/api/cashfree_webhook.php'),
    'tags'           => ['type' => 'promotion', 'listing_id' => $listing_id, 'plan_id' => $plan_id],
]);

if (empty($res['ok'])) {
    http_response_code(502);
    exit('Could not start the payment: ' . htmlspecialchars($res['error'] ?? 'Unknown error') . '. Please try again.');
}

// Store the session id on the pending row.
$ps  = $res['payment_session_id'];
$upd = $conn->prepare("UPDATE promotion_payments SET payment_session_id=? WHERE cashfree_order_id=?");
$upd->bind_param('ss', $ps, $orderId);
$upd->execute();
$upd->close();

cashfree_render_checkout($cfg, $ps);
exit;
