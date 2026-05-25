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
$listing_id          = intval($input['listing_id'] ?? 0);
$razorpay_order_id   = trim($input['razorpay_order_id'] ?? '');
$razorpay_payment_id = trim($input['razorpay_payment_id'] ?? '');
$razorpay_signature  = trim($input['razorpay_signature'] ?? '');

if (!$listing_id || !$razorpay_order_id || !$razorpay_payment_id || !$razorpay_signature) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$conn = getDbConnection();
if (!$conn) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Database unavailable']);
    exit;
}

// Ensure table exists (idempotent, in case create_order was skipped)
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

// Get Razorpay secret
$rzpKeySecret = '';
$res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'razorpay_key_secret' LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $rzpKeySecret = $row['setting_value'];
}

if (!$rzpKeySecret) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Payment gateway not configured']);
    exit;
}

// Verify Razorpay signature
$expected = hash_hmac('sha256', $razorpay_order_id . '|' . $razorpay_payment_id, $rzpKeySecret);
if (!hash_equals($expected, $razorpay_signature)) {
    error_log('QR payment signature mismatch for listing ' . $listing_id . ' order ' . $razorpay_order_id);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payment verification failed. Contact support.']);
    exit;
}

$now = date('Y-m-d H:i:s');

// Update the pending record to paid
$upd = $conn->prepare("UPDATE listing_qr_payments SET status='paid', razorpay_payment_id=?, paid_at=? WHERE listing_id=? AND razorpay_order_id=? AND status='pending'");
$upd->bind_param('ssis', $razorpay_payment_id, $now, $listing_id, $razorpay_order_id);
$upd->execute();
$affected = $upd->affected_rows;
$upd->close();

if ($affected === 0) {
    // Fallback: insert a new paid record (covers edge cases like missing pending row)
    $ins = $conn->prepare("INSERT IGNORE INTO listing_qr_payments (listing_id, razorpay_order_id, razorpay_payment_id, status, paid_at) VALUES (?, ?, ?, 'paid', ?)");
    $ins->bind_param('isss', $listing_id, $razorpay_order_id, $razorpay_payment_id, $now);
    $ins->execute();
    $ins->close();
}

echo json_encode(['success' => true, 'message' => 'QR code unlocked successfully!']);
