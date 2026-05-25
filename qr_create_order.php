<?php
require_once 'includes/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$listing_id = intval($input['listing_id'] ?? 0);

if (!$listing_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'listing_id required']);
    exit;
}

$conn = getDbConnection();
if (!$conn) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Database unavailable']);
    exit;
}

// Create table on first use
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

// Already paid? Return early
$paidCheck = $conn->prepare("SELECT id FROM listing_qr_payments WHERE listing_id = ? AND status = 'paid' LIMIT 1");
$paidCheck->bind_param('i', $listing_id);
$paidCheck->execute();
if ($paidCheck->get_result()->num_rows > 0) {
    $paidCheck->close();
    echo json_encode(['success' => true, 'already_paid' => true]);
    exit;
}
$paidCheck->close();

// Verify listing exists
$listingCheck = $conn->prepare("SELECT id FROM listings WHERE id = ? LIMIT 1");
$listingCheck->bind_param('i', $listing_id);
$listingCheck->execute();
if ($listingCheck->get_result()->num_rows === 0) {
    $listingCheck->close();
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Listing not found']);
    exit;
}
$listingCheck->close();

// Get Razorpay credentials
$rzpKeyId = '';
$rzpKeySecret = '';
$res = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('razorpay_key_id','razorpay_key_secret')");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if ($row['setting_key'] === 'razorpay_key_id')     $rzpKeyId     = $row['setting_value'];
        if ($row['setting_key'] === 'razorpay_key_secret') $rzpKeySecret = $row['setting_value'];
    }
}

if (!$rzpKeyId || !$rzpKeySecret) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Payment gateway not configured. Contact support.']);
    exit;
}

$amount_paise = 20000; // Rs 200
$receipt = 'QR_' . $listing_id . '_' . time();

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        'amount'   => $amount_paise,
        'currency' => 'INR',
        'receipt'  => $receipt,
        'notes'    => ['listing_id' => (string)$listing_id, 'purpose' => 'qr_unlock'],
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD        => $rzpKeyId . ':' . $rzpKeySecret,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || $httpCode !== 200) {
    error_log('QR order curl error: ' . $curlError . ' HTTP ' . $httpCode);
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Payment gateway temporarily unavailable. Please try again.']);
    exit;
}

$rzpOrder = json_decode($response, true);
if (empty($rzpOrder['id'])) {
    error_log('QR order creation failed: ' . $response);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not create payment order. Please try again.']);
    exit;
}

$rzpOrderId = $rzpOrder['id'];

$ins = $conn->prepare("INSERT INTO listing_qr_payments (listing_id, razorpay_order_id, amount) VALUES (?, ?, 200.00)");
$ins->bind_param('is', $listing_id, $rzpOrderId);
$ins->execute();
$ins->close();

echo json_encode([
    'success'           => true,
    'razorpay_order_id' => $rzpOrderId,
    'razorpay_key_id'   => $rzpKeyId,
    'amount_paise'      => $amount_paise,
]);
