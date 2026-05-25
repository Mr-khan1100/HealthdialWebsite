<?php
require_once 'config.php';

// Handle preflight FIRST (before rate limiting)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['success' => false, 'message' => 'POST method required'], 405);
}

// Rate limit: 3 order creations per minute per IP (after preflight)
checkRateLimit(3, 60);

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$listing_id = intval($input['listing_id'] ?? 0);
$plan_id = intval($input['plan_id'] ?? 0);
$customer_name = trim($input['customer_name'] ?? '');
$customer_phone = preg_replace('/\D/', '', trim($input['customer_phone'] ?? ''));
$customer_email = trim($input['customer_email'] ?? '');

// Validate
if (!$listing_id) sendResponse(['success' => false, 'message' => 'Listing ID required'], 400);
if (!$plan_id) sendResponse(['success' => false, 'message' => 'Plan ID required'], 400);
if (!$customer_name) sendResponse(['success' => false, 'message' => 'Name required'], 400);
if (!$customer_phone || strlen($customer_phone) < 10) sendResponse(['success' => false, 'message' => 'Valid phone required'], 400);

// Verify listing exists
$stmt = $conn->prepare("SELECT id, name FROM listings WHERE id = ? AND status = 'approved'");
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();
if (!$listing) sendResponse(['success' => false, 'message' => 'Listing not found or not approved'], 404);

// Verify plan exists and is active
$stmt = $conn->prepare("SELECT id, name, duration_days, price FROM highlight_plans WHERE id = ? AND is_active = 1");
$stmt->bind_param("i", $plan_id);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();
if (!$plan) sendResponse(['success' => false, 'message' => 'Plan not found or inactive'], 404);

$amount = (float)$plan['price'];
$receiptId = 'PROMO_' . time() . '_' . $listing_id;

// Get Razorpay credentials from settings
$rzpKeyId = '';
$rzpKeySecret = '';

$settingsResult = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('razorpay_key_id', 'razorpay_key_secret')");
while ($s = $settingsResult->fetch_assoc()) {
    if ($s['setting_key'] === 'razorpay_key_id') $rzpKeyId = $s['setting_value'];
    if ($s['setting_key'] === 'razorpay_key_secret') $rzpKeySecret = $s['setting_value'];
}

if (!$rzpKeyId || !$rzpKeySecret) {
    sendResponse(['success' => false, 'message' => 'Payment gateway not configured. Contact admin.'], 500);
}

// Build Razorpay order request
// Razorpay expects amount in paise (INR * 100)
$orderData = [
    'amount' => intval($amount * 100),
    'currency' => 'INR',
    'receipt' => $receiptId,
    'notes' => [
        'listing_id' => (string)$listing_id,
        'plan_id' => (string)$plan_id,
        'listing_name' => $listing['name'],
        'plan_name' => $plan['name']
    ]
];

// Call Razorpay Orders API
$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($orderData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $rzpKeyId . ':' . $rzpKeySecret,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    error_log('Razorpay curl error: ' . $curlError);
    sendResponse(['success' => false, 'message' => 'Payment gateway temporarily unavailable. Please try again.'], 503);
}

$rzpResponse = json_decode($response, true);

if ($httpCode !== 200 || empty($rzpResponse['id'])) {
    $errMsg = $rzpResponse['error']['description'] ?? 'Payment gateway error';
    error_log('Razorpay order creation failed: ' . json_encode($rzpResponse));
    sendResponse(['success' => false, 'message' => 'Payment creation failed: ' . $errMsg], 500);
}

$rzpOrderId = $rzpResponse['id'];

// Save to database
$stmt = $conn->prepare("INSERT INTO promotion_payments (listing_id, plan_id, customer_name, customer_phone, customer_email, amount, cashfree_order_id, cf_order_id, payment_session_id, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')");
// Reusing existing columns: cashfree_order_id → receipt, cf_order_id → razorpay order id, payment_session_id → empty
$emptyStr = '';
$stmt->bind_param("iisssdsss", $listing_id, $plan_id, $customer_name, $customer_phone, $customer_email, $amount, $receiptId, $rzpOrderId, $emptyStr);
$stmt->execute();

sendResponse([
    'success' => true,
    'razorpay_order_id' => $rzpOrderId,
    'razorpay_key_id' => $rzpKeyId,
    'receipt_id' => $receiptId,
    'amount' => $amount,
    'amount_paise' => intval($amount * 100),
    'customer_name' => $customer_name,
    'customer_phone' => $customer_phone,
    'customer_email' => $customer_email
]);
?>
