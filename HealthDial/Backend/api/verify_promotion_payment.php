<?php
require_once 'config.php';

// Verify Razorpay payment and activate promotion if paid

$orderId = trim($_GET['order_id'] ?? '');
$razorpayPaymentId = trim($_GET['razorpay_payment_id'] ?? '');
$razorpaySignature = trim($_GET['razorpay_signature'] ?? '');

if (!$orderId) {
    sendResponse(['success' => false, 'message' => 'Order ID required'], 400);
}

// Get payment record (cf_order_id stores razorpay order id)
$stmt = $conn->prepare("SELECT pp.*, l.name as listing_name, hp.name as plan_name, hp.duration_days 
    FROM promotion_payments pp 
    JOIN listings l ON pp.listing_id = l.id 
    JOIN highlight_plans hp ON pp.plan_id = hp.id 
    WHERE pp.cashfree_order_id = ?");
$stmt->bind_param("s", $orderId);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    sendResponse(['success' => false, 'message' => 'Payment record not found'], 404);
}

// If already processed, return current status
if ($payment['payment_status'] === 'PAID') {
    sendResponse([
        'success' => true,
        'payment_status' => 'PAID',
        'message' => 'Payment already verified',
        'promotion' => [
            'listing_name' => $payment['listing_name'],
            'plan_name' => $payment['plan_name'],
            'duration_days' => $payment['duration_days'],
            'amount' => $payment['amount']
        ]
    ]);
}

// Get Razorpay credentials
$rzpKeyId = '';
$rzpKeySecret = '';

$settingsResult = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('razorpay_key_id', 'razorpay_key_secret')");
while ($s = $settingsResult->fetch_assoc()) {
    if ($s['setting_key'] === 'razorpay_key_id') $rzpKeyId = $s['setting_value'];
    if ($s['setting_key'] === 'razorpay_key_secret') $rzpKeySecret = $s['setting_value'];
}

$rzpOrderId = $payment['cf_order_id']; // We stored razorpay order_id here

// If payment_id and signature provided, verify signature first
if ($razorpayPaymentId && $razorpaySignature) {
    $expectedSignature = hash_hmac('sha256', $rzpOrderId . '|' . $razorpayPaymentId, $rzpKeySecret);
    
    if (hash_equals($expectedSignature, $razorpaySignature)) {
        // Signature valid — mark as paid
        $paymentMethod = 'razorpay';
        $paymentTime = date('Y-m-d H:i:s');
        
        $stmt = $conn->prepare("UPDATE promotion_payments SET payment_status='PAID', cf_payment_id=?, payment_method=?, payment_time=?, updated_at=NOW() WHERE cashfree_order_id=?");
        $stmt->bind_param("ssss", $razorpayPaymentId, $paymentMethod, $paymentTime, $orderId);
        $stmt->execute();

        // Activate the promotion
        $startDate = date('Y-m-d H:i:s');
        $endDate = date('Y-m-d H:i:s', strtotime('+' . $payment['duration_days'] . ' days'));
        $amount = $payment['amount'];
        $listingId = $payment['listing_id'];

        $check = $conn->prepare("SELECT id FROM listing_highlights WHERE listing_id = ? AND start_date = ? AND amount = ?");
        $check->bind_param("isd", $listingId, $startDate, $amount);
        $check->execute();
        
        if ($check->get_result()->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO listing_highlights (listing_id, start_date, end_date, amount, payment_status, is_active) VALUES (?, ?, ?, ?, 'paid', 1)");
            $stmt->bind_param("issd", $listingId, $startDate, $endDate, $amount);
            $stmt->execute();
        }

        sendResponse([
            'success' => true,
            'payment_status' => 'PAID',
            'message' => 'Payment verified! Listing promoted successfully.',
            'promotion' => [
                'listing_name' => $payment['listing_name'],
                'plan_name' => $payment['plan_name'],
                'duration_days' => $payment['duration_days'],
                'amount' => $payment['amount'],
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    } else {
        sendResponse(['success' => false, 'payment_status' => 'FAILED', 'message' => 'Payment signature verification failed'], 400);
    }
}

// Fallback: Check order status via Razorpay API
$ch = curl_init('https://api.razorpay.com/v1/orders/' . $rzpOrderId);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $rzpKeyId . ':' . $rzpKeySecret,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$rzpOrder = json_decode($response, true);

if ($httpCode !== 200 || !$rzpOrder) {
    sendResponse(['success' => false, 'message' => 'Could not verify payment with gateway'], 500);
}

$orderStatus = strtolower($rzpOrder['status'] ?? 'unknown');

if ($orderStatus === 'paid') {
    // Fetch payment details
    $ch2 = curl_init('https://api.razorpay.com/v1/orders/' . $rzpOrderId . '/payments');
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $rzpKeyId . ':' . $rzpKeySecret,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $paymentsResp = curl_exec($ch2);
    curl_close($ch2);
    $paymentsData = json_decode($paymentsResp, true);

    $rzpPaymentId = '';
    $paymentMethod = '';
    if (isset($paymentsData['items']) && count($paymentsData['items']) > 0) {
        foreach ($paymentsData['items'] as $p) {
            if (($p['status'] ?? '') === 'captured') {
                $rzpPaymentId = $p['id'] ?? '';
                $paymentMethod = $p['method'] ?? 'razorpay';
                break;
            }
        }
    }

    $paymentTime = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("UPDATE promotion_payments SET payment_status='PAID', cf_payment_id=?, payment_method=?, payment_time=?, updated_at=NOW() WHERE cashfree_order_id=?");
    $stmt->bind_param("ssss", $rzpPaymentId, $paymentMethod, $paymentTime, $orderId);
    $stmt->execute();

    // Activate the promotion
    $startDate = date('Y-m-d H:i:s');
    $endDate = date('Y-m-d H:i:s', strtotime('+' . $payment['duration_days'] . ' days'));
    $amount = $payment['amount'];
    $listingId = $payment['listing_id'];

    $check = $conn->prepare("SELECT id FROM listing_highlights WHERE listing_id = ? AND start_date = ? AND amount = ?");
    $check->bind_param("isd", $listingId, $startDate, $amount);
    $check->execute();
    
    if ($check->get_result()->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO listing_highlights (listing_id, start_date, end_date, amount, payment_status, is_active) VALUES (?, ?, ?, ?, 'paid', 1)");
        $stmt->bind_param("issd", $listingId, $startDate, $endDate, $amount);
        $stmt->execute();
    }

    sendResponse([
        'success' => true,
        'payment_status' => 'PAID',
        'message' => 'Payment verified! Listing promoted successfully.',
        'promotion' => [
            'listing_name' => $payment['listing_name'],
            'plan_name' => $payment['plan_name'],
            'duration_days' => $payment['duration_days'],
            'amount' => $payment['amount'],
            'start_date' => $startDate,
            'end_date' => $endDate
        ]
    ]);
} else {
    $newStatus = in_array($orderStatus, ['created', 'attempted']) ? 'PENDING' : 'FAILED';
    $stmt = $conn->prepare("UPDATE promotion_payments SET payment_status=?, updated_at=NOW() WHERE cashfree_order_id=?");
    $stmt->bind_param("ss", $newStatus, $orderId);
    $stmt->execute();

    sendResponse([
        'success' => false,
        'payment_status' => $newStatus,
        'message' => $newStatus === 'PENDING' ? 'Payment is still processing' : 'Payment failed or was cancelled'
    ]);
}
?>
