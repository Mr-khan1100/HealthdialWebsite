<?php
$pageTitle = 'Payment Status — HealthDial';
$pageDesc = 'Your listing promotion payment status.';
require_once 'includes/icons.php';
require_once 'includes/db.php';

$orderId = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';
$urlStatus = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : '';

// Look up the stored payment result (PayU callback already verified + activated it).
$payment = null;
if ($orderId) {
    $conn = getDbConnection();
    if ($conn) {
        $stmt = $conn->prepare("SELECT pp.*, l.name AS listing_name, hp.name AS plan_name, hp.duration_days
                                FROM promotion_payments pp
                                JOIN listings l ON pp.listing_id = l.id
                                JOIN highlight_plans hp ON pp.plan_id = hp.id
                                WHERE pp.cashfree_order_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $orderId);
            $stmt->execute();
            $payment = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }
}

$isPaid    = $payment && $payment['payment_status'] === 'PAID';
$isPending = $payment && $payment['payment_status'] === 'PENDING' && $urlStatus !== 'failed';

require_once 'includes/header.php';
?>

<section class="payment-result-section">
    <div class="container">
        <div class="payment-result-card" id="paymentResultCard">

            <?php if ($isPaid): ?>
            <!-- Success -->
            <div class="payment-success">
                <div class="success-icon-ring">
                    <i class="fas fa-check"></i>
                </div>
                <h2>Payment Successful!</h2>
                <p class="payment-msg">Your listing has been promoted and is now visible to thousands of patients.</p>

                <div class="promotion-details">
                    <div class="promo-detail-row"><span>Listing</span><strong><?= htmlspecialchars($payment['listing_name']) ?></strong></div>
                    <div class="promo-detail-row"><span>Plan</span><strong><?= htmlspecialchars($payment['plan_name']) ?></strong></div>
                    <div class="promo-detail-row"><span>Duration</span><strong><?= intval($payment['duration_days']) ?> days</strong></div>
                    <div class="promo-detail-row"><span>Amount Paid</span><strong>₹<?= number_format((float)$payment['amount'], 0) ?></strong></div>
                    <div class="promo-detail-row"><span>Order ID</span><strong style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($orderId) ?></strong></div>
                </div>

                <div class="payment-actions">
                    <a href="index.php" class="btn btn-primary"><i class="fas fa-home"></i> Go to Home</a>
                    <a href="promote.php" class="btn btn-secondary"><i class="fas fa-bolt"></i> Promote Another</a>
                </div>
            </div>

            <?php elseif ($isPending): ?>
            <!-- Pending -->
            <div class="payment-failure">
                <div class="failure-icon-ring" style="background:#f59e0b;">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <h2>Payment Processing</h2>
                <p class="payment-msg">Your payment is still being confirmed. This can take a few minutes — we'll
                    activate your promotion automatically once the payment gateway confirms it.</p>
                <div class="payment-actions">
                    <a href="promote.php" class="btn btn-secondary"><i class="fas fa-bolt"></i> Back to Promote</a>
                    <a href="contact.php" class="btn btn-secondary"><i class="fas fa-headset"></i> Contact Support</a>
                </div>
            </div>

            <?php else: ?>
            <!-- Failure -->
            <div class="payment-failure">
                <div class="failure-icon-ring">
                    <i class="fas fa-times"></i>
                </div>
                <h2>Payment Not Completed</h2>
                <p class="payment-msg">Your payment could not be processed. If any amount was deducted, it will be
                    refunded automatically within 5–7 business days.</p>
                <div class="payment-actions">
                    <a href="promote.php" class="btn btn-primary"><i class="fas fa-redo"></i> Try Again</a>
                    <a href="contact.php" class="btn btn-secondary"><i class="fas fa-headset"></i> Contact Support</a>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
