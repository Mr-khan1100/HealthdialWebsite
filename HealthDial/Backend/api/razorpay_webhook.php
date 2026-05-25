<?php
/**
 * Razorpay Webhook Handler
 * URL: https://healthdial.com/HealthDial/Backend/api/razorpay_webhook.php
 * Configure this URL in Razorpay Dashboard > Settings > Webhooks
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

if (!$data || !isset($data['event'])) {
    http_response_code(400);
    exit('Invalid payload');
}

// Verify Razorpay webhook signature
$webhookSignature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
if ($webhookSignature) {
    // Get webhook secret from settings
    $webhookSecret = '';
    $secRes = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'razorpay_webhook_secret'");
    if ($secRes && $secRow = $secRes->fetch_assoc()) {
        $webhookSecret = $secRow['setting_value'];
    }
    
    if ($webhookSecret) {
        $expectedSignature = hash_hmac('sha256', $rawBody, $webhookSecret);
        if (!hash_equals($expectedSignature, $webhookSignature)) {
            error_log('Razorpay webhook signature mismatch! Possible forgery attempt.');
            http_response_code(401);
            exit('Invalid signature');
        }
    }
} else {
    error_log('Warning: Razorpay webhook received without signature header');
}

// Log webhook for debugging
$logDir = __DIR__ . '/../uploads/webhook_logs/';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
file_put_contents($logDir . date('Y-m-d_H-i-s') . '_' . str_replace('.', '_', $data['event']) . '.json', $rawBody);

// Handle different event types
$event = $data['event'];

if ($event === 'payment.captured' || $event === 'payment.failed' || $event === 'order.paid') {
    $payload = $data['payload'] ?? [];
    
    $paymentEntity = $payload['payment']['entity'] ?? [];
    $orderEntity = $payload['order']['entity'] ?? ($paymentEntity['order_id'] ? [] : []);
    
    $rzpOrderId = $paymentEntity['order_id'] ?? ($orderEntity['id'] ?? '');
    $rzpPaymentId = $paymentEntity['id'] ?? '';
    $paymentStatus = $paymentEntity['status'] ?? '';
    $paymentMethod = $paymentEntity['method'] ?? 'razorpay';

    if (!$rzpOrderId) {
        http_response_code(400);
        exit('Missing order_id');
    }

    // Get payment record (cf_order_id stores razorpay order id)
    $stmt = $conn->prepare("SELECT pp.*, hp.duration_days FROM promotion_payments pp JOIN highlight_plans hp ON pp.plan_id = hp.id WHERE pp.cf_order_id = ?");
    $stmt->bind_param("s", $rzpOrderId);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();

    if (!$payment) {
        http_response_code(404);
        exit('Payment record not found for order: ' . $rzpOrderId);
    }

    if ($event === 'payment.captured' || $event === 'order.paid') {
        // Mark as paid
        $paymentTime = date('Y-m-d H:i:s');
        $receiptId = $payment['cashfree_order_id'];
        
        $stmt = $conn->prepare("UPDATE promotion_payments SET payment_status='PAID', cf_payment_id=?, payment_method=?, payment_time=?, updated_at=NOW() WHERE cashfree_order_id=?");
        $stmt->bind_param("ssss", $rzpPaymentId, $paymentMethod, $paymentTime, $receiptId);
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
        $notifMsg = "₹" . number_format($amount, 0) . " payment received for promoting '$listingName' via Razorpay.";
        $notifStmt = $conn->prepare("INSERT INTO admin_notifications (title, message, type) VALUES (?, ?, 'promotion_payment')");
        $notifStmt->bind_param("ss", $notifTitle, $notifMsg);
        $notifStmt->execute();

    } else {
        // Mark as failed
        $receiptId = $payment['cashfree_order_id'];
        $stmt = $conn->prepare("UPDATE promotion_payments SET payment_status='FAILED', cf_payment_id=?, payment_method=?, updated_at=NOW() WHERE cashfree_order_id=?");
        $stmt->bind_param("sss", $rzpPaymentId, $paymentMethod, $receiptId);
        $stmt->execute();
    }

    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit();
}

// Unknown event type
http_response_code(200);
echo json_encode(['status' => 'ignored', 'event' => $event]);
?>
