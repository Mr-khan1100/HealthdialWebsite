<?php
$pageTitle = 'Payment Successful — HealthDial';
$pageDesc = 'Your listing promotion payment was successful.';
require_once 'includes/icons.php';
require_once 'includes/db.php';
require_once 'includes/header.php';

$orderId = isset($_GET['order_id']) ? htmlspecialchars($_GET['order_id']) : '';
?>

<section class="payment-result-section">
    <div class="container">
        <div class="payment-result-card" id="paymentResultCard">
            <div class="payment-loader" id="paymentLoader">
                <div class="payment-spinner"></div>
                <h2>Verifying Payment...</h2>
                <p>Please wait while we confirm your payment with Razorpay.</p>
            </div>

            <!-- Success State (hidden initially) -->
            <div class="payment-success" id="paymentSuccess" style="display:none;">
                <div class="success-icon-ring">
                    <i class="fas fa-check"></i>
                </div>
                <h2>Payment Successful!</h2>
                <p class="payment-msg">Your listing has been promoted and is now visible to thousands of patients.</p>
                
                <div class="promotion-details" id="promotionDetails">
                    <!-- Filled by JS -->
                </div>

                <div class="payment-actions">
                    <a href="index.php" class="btn btn-primary"><i class="fas fa-home"></i> Go to Home</a>
                    <a href="promote.php" class="btn btn-secondary"><i class="fas fa-bolt"></i> Promote Another</a>
                </div>
            </div>

            <!-- Failure State (hidden initially) -->
            <div class="payment-failure" id="paymentFailure" style="display:none;">
                <div class="failure-icon-ring">
                    <i class="fas fa-times"></i>
                </div>
                <h2>Payment Not Completed</h2>
                <p class="payment-msg" id="failureMsg">Your payment could not be processed. No amount was deducted.</p>
                
                <div class="payment-actions">
                    <a href="promote.php" class="btn btn-primary"><i class="fas fa-redo"></i> Try Again</a>
                    <a href="contact.php" class="btn btn-secondary"><i class="fas fa-headset"></i> Contact Support</a>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const orderId = '<?= addslashes($orderId) ?>';
    if (!orderId) {
        document.getElementById('paymentLoader').style.display = 'none';
        document.getElementById('paymentFailure').style.display = 'block';
        document.getElementById('failureMsg').textContent = 'No order ID found. Please try promoting again.';
        return;
    }

    // Get Razorpay params from URL
    const urlParams = new URLSearchParams(window.location.search);
    const razorpayPaymentId = urlParams.get('razorpay_payment_id') || '';
    const razorpaySignature = urlParams.get('razorpay_signature') || '';

    // Verify payment
    let verifyUrl = '<?= API_BASE ?>verify_promotion_payment.php?order_id=' + encodeURIComponent(orderId);
    if (razorpayPaymentId) verifyUrl += '&razorpay_payment_id=' + encodeURIComponent(razorpayPaymentId);
    if (razorpaySignature) verifyUrl += '&razorpay_signature=' + encodeURIComponent(razorpaySignature);

    fetch(verifyUrl)
        .then(r => r.json())
        .then(data => {
            document.getElementById('paymentLoader').style.display = 'none';

            if (data.success && data.payment_status === 'PAID') {
                document.getElementById('paymentSuccess').style.display = 'block';
                const promo = data.promotion || {};
                document.getElementById('promotionDetails').innerHTML = `
                    <div class="promo-detail-row"><span>Listing</span><strong>${promo.listing_name || ''}</strong></div>
                    <div class="promo-detail-row"><span>Plan</span><strong>${promo.plan_name || ''}</strong></div>
                    <div class="promo-detail-row"><span>Duration</span><strong>${promo.duration_days || ''} days</strong></div>
                    <div class="promo-detail-row"><span>Amount Paid</span><strong>₹${parseInt(promo.amount || 0).toLocaleString('en-IN')}</strong></div>
                    <div class="promo-detail-row"><span>Order ID</span><strong style="font-size:12px;color:var(--text-muted);">${orderId}</strong></div>
                `;
            } else {
                document.getElementById('paymentFailure').style.display = 'block';
                if (data.payment_status === 'PENDING') {
                    document.getElementById('failureMsg').textContent = 'Payment is still processing. Please check back in a few minutes.';
                }
            }
        })
        .catch(err => {
            console.error('Verification error:', err);
            document.getElementById('paymentLoader').style.display = 'none';
            document.getElementById('paymentFailure').style.display = 'block';
        });
});
</script>

<?php require_once 'includes/footer.php'; ?>
