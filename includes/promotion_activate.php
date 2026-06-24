<?php
/**
 * Shared promotion activation — used by both the PayU browser callback
 * (payu_promotion_callback.php) and the server-to-server webhook
 * (payu_webhook.php). Idempotent: safe to call more than once for the same txnid.
 *
 * Returns the promotion_payments row (with plan duration) on success, or null
 * if no matching pending/paid record exists.
 */

if (!function_exists('payu_mark_promotion_paid')) {

    function payu_mark_promotion_paid(mysqli $conn, string $txnid, string $mihpayid, string $method = 'payu'): ?array
    {
        // Look up the payment by our txnid (stored in cashfree_order_id).
        $stmt = $conn->prepare("SELECT pp.*, hp.duration_days
                                FROM promotion_payments pp
                                JOIN highlight_plans hp ON pp.plan_id = hp.id
                                WHERE pp.cashfree_order_id = ? LIMIT 1");
        $stmt->bind_param('s', $txnid);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$payment) {
            return null;
        }

        // Already processed → return as-is (idempotent).
        if ($payment['payment_status'] === 'PAID') {
            return $payment;
        }

        $now = date('Y-m-d H:i:s');
        $upd = $conn->prepare("UPDATE promotion_payments
                               SET payment_status='PAID', cf_payment_id=?, payment_method=?, payment_time=?, updated_at=NOW()
                               WHERE cashfree_order_id=? AND payment_status<>'PAID'");
        $upd->bind_param('ssss', $mihpayid, $method, $now, $txnid);
        $upd->execute();
        $upd->close();

        // Activate the highlight (idempotency guard against duplicate active rows).
        $listingId    = (int) $payment['listing_id'];
        $amount       = (float) $payment['amount'];
        $durationDays = (int) $payment['duration_days'];
        $startDate    = $now;
        $endDate      = date('Y-m-d H:i:s', strtotime('+' . $durationDays . ' days'));

        $check = $conn->prepare("SELECT id FROM listing_highlights
                                 WHERE listing_id = ? AND amount = ? AND payment_status = 'paid' AND end_date > NOW()
                                 ORDER BY id DESC LIMIT 1");
        $check->bind_param('id', $listingId, $amount);
        $check->execute();
        $hasActive = $check->get_result()->num_rows > 0;
        $check->close();

        if (!$hasActive) {
            $ins = $conn->prepare("INSERT INTO listing_highlights
                                   (listing_id, start_date, end_date, amount, payment_status, is_active)
                                   VALUES (?, ?, ?, ?, 'paid', 1)");
            $ins->bind_param('issd', $listingId, $startDate, $endDate, $amount);
            $ins->execute();
            $ins->close();

            // Admin notification (best-effort).
            $listingName = 'Unknown';
            $nameStmt = $conn->prepare("SELECT name FROM listings WHERE id = ? LIMIT 1");
            if ($nameStmt) {
                $nameStmt->bind_param('i', $listingId);
                $nameStmt->execute();
                $nameRow = $nameStmt->get_result()->fetch_assoc();
                $listingName = $nameRow['name'] ?? 'Unknown';
                $nameStmt->close();
            }
            $notifTitle = 'New Promotion Payment';
            $notifMsg   = '₹' . number_format($amount, 0) . " payment received for promoting '$listingName' via PayU.";
            $notif = $conn->prepare("INSERT INTO admin_notifications (title, message, type) VALUES (?, ?, 'promotion_payment')");
            if ($notif) {
                $notif->bind_param('ss', $notifTitle, $notifMsg);
                @$notif->execute();
                $notif->close();
            }
        }

        $payment['payment_status'] = 'PAID';
        $payment['start_date'] = $startDate;
        $payment['end_date']   = $endDate;
        return $payment;
    }
}
