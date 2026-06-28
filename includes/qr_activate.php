<?php
/**
 * Shared QR-unlock activation — single source of truth for marking a
 * listing_qr_payments row paid. Used by the PayU callback
 * (payu_qr_callback.php), the Cashfree return handler (cashfree_return.php),
 * and the Cashfree webhook. Idempotent: safe to call more than once.
 *
 * The order id is stored in `razorpay_order_id` and the gateway payment id in
 * `razorpay_payment_id` (legacy column names reused across gateways).
 *
 * Returns the listing_id on success (so the caller can redirect back), or 0.
 */

if (!function_exists('hd_mark_qr_paid')) {

    function hd_mark_qr_paid(mysqli $conn, string $orderId, string $paymentId, ?int $fallbackListingId = null): int
    {
        if ($orderId === '') {
            return (int) ($fallbackListingId ?? 0);
        }

        $sel = $conn->prepare("SELECT listing_id, status FROM listing_qr_payments WHERE razorpay_order_id = ? LIMIT 1");
        $sel->bind_param('s', $orderId);
        $sel->execute();
        $row = $sel->get_result()->fetch_assoc();
        $sel->close();

        $listingId = (int) ($row['listing_id'] ?? ($fallbackListingId ?? 0));

        // Already processed → idempotent no-op.
        if (($row['status'] ?? '') === 'paid') {
            return $listingId;
        }

        $now = date('Y-m-d H:i:s');
        $upd = $conn->prepare("UPDATE listing_qr_payments
                               SET status='paid', razorpay_payment_id=?, paid_at=?
                               WHERE razorpay_order_id=? AND status='pending'");
        $upd->bind_param('sss', $paymentId, $now, $orderId);
        $upd->execute();
        $affected = $upd->affected_rows;
        $upd->close();

        // Fallback insert if the pending row was missing (covers edge cases).
        if ($affected === 0 && $listingId && (($row['status'] ?? '') !== 'paid')) {
            $amt = function_exists('hd_qr_price') ? hd_qr_price($conn) : 200;
            $ins = $conn->prepare("INSERT INTO listing_qr_payments
                                   (listing_id, razorpay_order_id, razorpay_payment_id, amount, status, paid_at)
                                   VALUES (?, ?, ?, ?, 'paid', ?)");
            $ins->bind_param('issds', $listingId, $orderId, $paymentId, $amt, $now);
            $ins->execute();
            $ins->close();
        }

        return $listingId;
    }
}
