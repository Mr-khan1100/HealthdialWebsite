<?php
/**
 * Link a verified phone number to the logged-in account (the 2nd factor).
 *
 * After the profile page confirms the OTP with Firebase, it POSTs the resulting
 * phone ID token here. We verify the token SERVER-SIDE (never trust the browser),
 * make sure the number isn't already owned by another account, then set
 * users.mobile + phone_verified and refresh the session.
 *
 * Request  (JSON): { id_token: "<firebase phone token>" }
 * Response (JSON): { ok:true, phone } | { ok:false, error }
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/firebase_config.php';
require_once __DIR__ . '/includes/firebase_verify.php';
require_once __DIR__ . '/includes/user_auth.php';

header('Content-Type: application/json; charset=UTF-8');

function hd_pv_json($data, int $status = 200)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hd_pv_json(['ok' => false, 'error' => 'Invalid request method.'], 405);
}

$user = hd_current_user();
if (!$user) {
    hd_pv_json(['ok' => false, 'error' => 'Please log in first.', 'auth_required' => true], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}
$idToken = trim((string) ($input['id_token'] ?? ''));
if ($idToken === '') {
    hd_pv_json(['ok' => false, 'error' => 'Missing verification token.'], 400);
}

$projectId = hd_firebase_project_id();
if ($projectId === '' || strpos($projectId, 'REPLACE_WITH') === 0) {
    hd_pv_json(['ok' => false, 'error' => 'Phone verification is not configured yet.'], 500);
}

$claims = hd_verify_firebase_id_token($idToken, $projectId);
if (!$claims) {
    hd_pv_json(['ok' => false, 'error' => 'Could not verify the code. Please try again.'], 401);
}

// The token MUST be a phone sign-in carrying a phone number.
$provider = $claims['provider'] ?? '';
$rawPhone = (string) ($claims['phone_number'] ?? '');
if ($rawPhone === '' || ($provider !== '' && $provider !== 'phone')) {
    hd_pv_json(['ok' => false, 'error' => 'This is not a valid phone verification.'], 400);
}

// Canonical 10-digit (strip +91 / country code).
$digits = preg_replace('/\D/', '', $rawPhone);
$mobile = strlen($digits) > 10 ? substr($digits, -10) : $digits;
if (strlen($mobile) !== 10) {
    hd_pv_json(['ok' => false, 'error' => 'Unsupported phone number format.'], 400);
}

$conn = getDbConnection();
if (!$conn) {
    hd_pv_json(['ok' => false, 'error' => 'Service temporarily unavailable. Please try again.'], 503);
}

hd_ensure_phone_columns($conn);

$userId = (int) $user['id'];

// Reject if another account already verified this number.
$chk = $conn->prepare("SELECT id FROM users WHERE mobile = ? AND phone_verified = 1 AND id <> ? LIMIT 1");
if ($chk) {
    $chk->bind_param('si', $mobile, $userId);
    $chk->execute();
    $taken = $chk->get_result()->num_rows > 0;
    $chk->close();
    if ($taken) {
        hd_pv_json(['ok' => false, 'error' => 'This number is already verified on another account.'], 409);
    }
}

$upd = $conn->prepare("UPDATE users SET mobile = ?, phone_verified = 1, phone_verified_at = NOW() WHERE id = ?");
if (!$upd) {
    hd_pv_json(['ok' => false, 'error' => 'Could not save your verification. Please try again.'], 500);
}
$upd->bind_param('si', $mobile, $userId);
if (!$upd->execute()) {
    $upd->close();
    hd_pv_json(['ok' => false, 'error' => 'Could not save your verification. Please try again.'], 500);
}
$upd->close();

hd_refresh_session_user($conn);

hd_pv_json(['ok' => true, 'phone' => $mobile]);
