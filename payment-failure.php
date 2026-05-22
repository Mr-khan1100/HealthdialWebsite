<?php
$pageTitle = 'Payment Failed — HealthDial';
$pageDesc = 'Your listing promotion payment could not be completed.';
require_once 'includes/icons.php';
require_once 'includes/db.php';
require_once 'includes/header.php';

$orderId = isset($_GET['order_id']) ? htmlspecialchars($_GET['order_id']) : '';
?>

<section class="payment-result-section">
    <div class="container">
        <div class="payment-result-card">
            <div class="failure-icon-ring">
                <i class="fas fa-times"></i>
            </div>
            <h2>Payment Failed</h2>
            <p class="payment-msg">Your payment could not be processed. Don't worry — no amount has been deducted from your account.</p>
            
            <?php if ($orderId): ?>
            <p style="font-size:12px;color:var(--text-muted);margin-top:8px;">Order ID: <?= $orderId ?></p>
            <?php endif; ?>

            <div class="payment-actions">
                <a href="promote.php" class="btn btn-primary"><i class="fas fa-redo"></i> Try Again</a>
                <a href="contact.php" class="btn btn-secondary"><i class="fas fa-headset"></i> Contact Support</a>
            </div>

            <div class="failure-tips">
                <h4>Common reasons for payment failure:</h4>
                <ul>
                    <li><i class="fas fa-exclamation-circle"></i> Insufficient bank balance</li>
                    <li><i class="fas fa-exclamation-circle"></i> Bank server was temporarily unavailable</li>
                    <li><i class="fas fa-exclamation-circle"></i> Transaction timed out</li>
                    <li><i class="fas fa-exclamation-circle"></i> Incorrect OTP or CVV entered</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
