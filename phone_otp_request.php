<?php
/**
 * OTP-send gate (rate limiter).
 *
 * The profile page MUST call this and get {ok:true} BEFORE it asks Firebase to
 * send an SMS (signInWithPhoneNumber). Only logged-in accounts can reach it, and
 * every send is rate-limited per user / per phone / per IP (see
 * includes/otp_throttle.php). This is how we cap Firebase SMS cost + abuse.
 *
 * Request  (JSON): { phone: "<10-digit>" }
 * Response (JSON): { ok:true } | { ok:false, error, retry_after? }
 */
require_once __DIR__ . '/includes/json_guard.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/otp_throttle.php';

header('Content-Type: application/json; charset=UTF-8');

function hd_otp_json($data, int $status = 200)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hd_otp_json(['ok' => false, 'error' => 'Invalid request method.'], 405);
}

// Logged-in only — anonymous visitors can never trigger an SMS.
$user = hd_current_user();
if (!$user) {
    hd_otp_json(['ok' => false, 'error' => 'Please log in first.', 'auth_required' => true], 401);
}

// Already verified? Nothing to send.
if (!empty($user['phone_verified'])) {
    hd_otp_json(['ok' => false, 'error' => 'Your phone is already verified.', 'already_verified' => true], 409);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$phone = preg_replace('/\D/', '', (string) ($input['phone'] ?? ''));
if (strlen($phone) !== 10) {
    hd_otp_json(['ok' => false, 'error' => 'Enter a valid 10-digit mobile number.'], 400);
}

$conn = getDbConnection();
if (!$conn) {
    hd_otp_json(['ok' => false, 'error' => 'Service temporarily unavailable. Please try again.'], 503);
}

// Don't let one number be verified on two accounts.
hd_ensure_phone_columns($conn);
$chk = $conn->prepare("SELECT id FROM users WHERE mobile = ? AND phone_verified = 1 AND id <> ? LIMIT 1");
if ($chk) {
    $uid = (int) $user['id'];
    $chk->bind_param('si', $phone, $uid);
    $chk->execute();
    $taken = $chk->get_result()->num_rows > 0;
    $chk->close();
    if ($taken) {
        hd_otp_json(['ok' => false, 'error' => 'This number is already verified on another account.'], 409);
    }
}

$result = hd_otp_check_and_record($conn, (int) $user['id'], $phone, hd_client_ip());
if (empty($result['ok'])) {
    hd_otp_json([
        'ok'          => false,
        'error'       => $result['error'] ?? 'Too many requests. Please try again later.',
        'retry_after' => $result['retry_after'] ?? 60,
    ], 429);
}

hd_otp_json(['ok' => true]);
