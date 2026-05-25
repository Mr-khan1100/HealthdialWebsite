<?php
/**
 * Cashfree Webhook Handler
 * URL: https://healthdial.com/HealthDial/Backend/api/cashfree_webhook.php
 * Configure this URL in Cashfree Dashboard > Developers > Webhooks
 */

require_once 'config.php';

// Webhooks are POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Read raw body
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!$data || !isset($data['type'])) {
    http_response_code(400);
    exit('Invalid payload');
}

// Verify Cashfree webhook signature
$cfSignature = $_SERVER['HTTP_X_CASHFREE_SIGNATURE'] ?? $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
if ($cfSignature) {
    // Get secret key for signature verification
    $cfSecret = '';
    $secRes = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'cashfree_secret_key'");
    if ($secRes && $secRow = $secRes->fetch_assoc()) {
        $cfSecret = $secRow['setting_value'];
    }
    
    if ($cfSecret) {
        $computedSignature = base64_encode(hash_hmac('sha256', $rawBody, $cfSecret, true));
        if (!hash_equals($computedSignature, $cfSignature)) {
            error_log('Cashfree webhook signature mismatch! Possible forgery attempt.');
            http_response_code(401);
            exit('Invalid signature');
        }
    }
} else {
    error_log('Warning: Cashfree webhook received without signature header');
}

// Log webhook for debugging
$logDir = __DIR__ . '/../uploads/webhook_logs/';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
file_put_contents($logDir . date('Y-m-d_H-i-s') . '_' . $data['type'] . '.json', $rawBody);

// Handle different event types
$eventType = $data['type'];

if ($eventType === 'PAYMENT_SUCCESS_WEBHOOK' || $eventType === 'PAYMENT_FAILED_WEBHOOK') {
    $orderData = $data['data']['order'] ?? [];
    $paymentData = $data['data']['payment'] ?? [];

    $orderId = $orderData['order_id'] ?? '';
    $orderStatus = strtoupper($orderData['order_status'] ?? '');
    
    $cfPaymentId = $paymentData['cf_payment_id'] ?? '';
    $paymentMethod = $paymentData['payment_group'] ?? $paymentData['payment_method'] ?? '';
    $paymentTime = $paymentData['payment_time'] ?? null;
    $paymentStatus = strtoupper($paymentData['payment_status'] ?? '');

    if (!$orderId) {
        http_response_code(400);
        exit('Missing order_id');
    }

    // Get payment record
    $stmt = $conn->prepare("SELECT pp.*, hp.duration_days FROM promotion_payments pp JOIN highlight_plans hp ON pp.plan_id = hp.id WHERE pp.cashfree_order_id = ?");
    $stmt->bind_param("s", $orderId);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();

    if (!$payment) {
        http_response_code(404);
        exit('Payment record not found');
    }

    if ($paymentStatus === 'SUCCESS' || $orderStatus === 'PAID') {
        // Mark as paid
        $stmt = $conn->prepare("UPDATE promotion_payments SET payment_status='PAID', cf_payment_id=?, payment_method=?, payment_time=?, updated_at=NOW() WHERE cashfree_order_id=?");
        $stmt->bind_param("ssss", $cfPaymentId, $paymentMethod, $paymentTime, $orderId);
        $stmt->execute();

        // Activate promotion
        $startDate = date('Y-m-d H:i:s');
        $endDate = date('Y-m-d H:i:s', strtotime('+' . $payment['duration_days'] . ' days'));
        $amount = $payment['amount'];
        $listingId = $payment['listing_id'];

        // Idempotency check
        $check = $conn->prepare("SELECT id FROM listing_highlights WHERE listing_id = ? AND amount = ? AND payment_status = 'paid' AND end_date > NOW() ORDER BY id DESC LIMIT 1");
        $check->bind_param("id", $listingId, $amount);
        $check->execute();
        
        if ($check->get_result()->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO listing_highlights (listing_id, start_date, end_date, amount, payment_status, is_active) VALUES (?, ?, ?, ?, 'paid', 1)");
            $stmt->bind_param("issd", $listingId, $startDate, $endDate, $amount);
            $stmt->execute();
        }

        // Create admin notification
        $nameStmt = $conn->prepare("SELECT name FROM listings WHERE id = ?");
        $nameStmt->bind_param("i", $listingId);
        $nameStmt->execute();
        $nameResult = $nameStmt->get_result()->fetch_assoc();
        $listingName = $nameResult['name'] ?? 'Unknown';
        $nameStmt->close();
        $notifTitle = "New Promotion Payment";
        $notifMsg = "₹" . number_format($amount, 0) . " payment received for promoting '$listingName' via Cashfree.";
        $notifStmt = $conn->prepare("INSERT INTO admin_notifications (title, message, type) VALUES (?, ?, 'promotion_payment')");
        $notifStmt->bind_param("ss", $notifTitle, $notifMsg);
        $notifStmt->execute();

    } else {
        // Mark as failed
        $stmt = $conn->prepare("UPDATE promotion_payments SET payment_status='FAILED', cf_payment_id=?, payment_method=?, updated_at=NOW() WHERE cashfree_order_id=?");
        $stmt->bind_param("sss", $cfPaymentId, $paymentMethod, $orderId);
        $stmt->execute();
    }

    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit();
}

// Unknown event type
http_response_code(200);
echo json_encode(['status' => 'ignored', 'type' => $eventType]);
?>
