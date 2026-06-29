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
require_once __DIR__ . '/includes/json_guard.php';
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

/**
 * Link the current (email/Google) login into an existing phone-only account.
 *
 * The person controls both factors here — a verified email session AND the phone
 * OTP they just passed — so it's safe to unify the two rows. We keep the OLDER
 * account (it holds the phone + their listings/data), fold this login's email
 * identity onto it, move over anything the new row might own, delete the new row
 * (which frees its unique email), and return the unified account row (or null).
 */
function hd_link_into_existing_account(mysqli $conn, array $me, array $other): ?array
{
    $meId    = (int) $me['id'];
    $otherId = (int) $other['id'];

    $name       = trim((string) ($other['name'] ?? '')) !== '' ? $other['name'] : ($me['name'] ?? '');
    $email      = $me['email'] ?? '';
    $password   = !empty($me['password'])   ? $me['password']   : ($other['password'] ?? '');
    $googleId   = !empty($me['google_id'])  ? $me['google_id']  : ($other['google_id'] ?? '');
    $profileImg = !empty($other['profile_image']) ? $other['profile_image'] : ($me['profile_image'] ?? '');

    $conn->begin_transaction();
    try {
        // Move anything the new row might own onto the older account (defensive —
        // a new email account is normally empty because the paid/sensitive actions
        // already require a verified phone).
        @$conn->query("UPDATE listings SET user_id = $otherId WHERE user_id = $meId");
        @$conn->query("UPDATE promotion_payments SET user_id = $otherId WHERE user_id = $meId");
        @$conn->query("UPDATE listing_qr_payments SET user_id = $otherId WHERE user_id = $meId");
        @$conn->query("UPDATE listing_claims SET user_id = $otherId WHERE user_id = $meId");
        @$conn->query("DELETE FROM user_tokens WHERE user_id = $meId");

        // Delete the new row FIRST so its unique email frees up.
        $del = $conn->prepare("DELETE FROM users WHERE id = ?");
        if (!$del) {
            throw new Exception('delete prepare failed: ' . $conn->error);
        }
        $del->bind_param('i', $meId);
        if (!$del->execute()) {
            throw new Exception('delete failed: ' . $del->error);
        }
        $del->close();

        // Fold the email identity onto the existing phone account + mark verified.
        $upd = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ?, google_id = ?, profile_image = ?, phone_verified = 1, phone_verified_at = NOW() WHERE id = ?");
        if (!$upd) {
            throw new Exception('merge prepare failed: ' . $conn->error);
        }
        $upd->bind_param('sssssi', $name, $email, $password, $googleId, $profileImg, $otherId);
        if (!$upd->execute()) {
            throw new Exception('merge update failed: ' . $upd->error);
        }
        $upd->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('phone_verify merge failed: ' . $e->getMessage());
        return null;
    }

    $sel = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $sel->bind_param('i', $otherId);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $sel->close();
    return $row ?: null;
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

// Full row for the account that's currently signed in (this login).
$meStmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$meStmt->bind_param('i', $userId);
$meStmt->execute();
$me = $meStmt->get_result()->fetch_assoc();
$meStmt->close();
if (!$me) {
    hd_pv_json(['ok' => false, 'error' => 'Your session has expired. Please log in again.'], 401);
}

// Already verified this same number on this account? Nothing to do.
if (!empty($me['phone_verified']) && preg_replace('/\D/', '', (string) ($me['mobile'] ?? '')) === $mobile) {
    hd_pv_json(['ok' => true, 'phone' => $mobile]);
}

// Does ANOTHER account already hold this number (in any stored format)?
$variants = array_values(array_unique([$mobile, '+91' . $mobile, '91' . $mobile, '0' . $mobile]));
$place    = implode(',', array_fill(0, count($variants), '?'));
$types    = str_repeat('s', count($variants)) . 'i';
$args     = array_merge($variants, [$userId]);
$other    = null;
$find = $conn->prepare("SELECT * FROM users WHERE mobile IN ($place) AND id <> ? LIMIT 1");
if ($find) {
    $find->bind_param($types, ...$args);
    $find->execute();
    $other = $find->get_result()->fetch_assoc();
    $find->close();
}

if ($other) {
    $otherEmail = trim((string) ($other['email'] ?? ''));
    $myEmail    = trim((string) ($me['email'] ?? ''));
    // Different, real email on the other account => two distinct people. Don't merge.
    if ($otherEmail !== '' && strcasecmp($otherEmail, $myEmail) !== 0) {
        hd_pv_json(['ok' => false, 'error' => 'This number is already registered to another account. Please contact support to merge them.'], 409);
    }
    // Safe link: the other (phone) account has no email — fold this login into it.
    $merged = hd_link_into_existing_account($conn, $me, $other);
    if (!$merged) {
        hd_pv_json(['ok' => false, 'error' => 'Could not link your account. Please try again or contact support.'], 500);
    }
    hd_login_user($merged); // switch the session to the unified account
    hd_pv_json(['ok' => true, 'phone' => $mobile, 'merged' => true, 'redirect' => 'profile.php']);
}

// No other account holds this number → save it on the current account.
$upd = $conn->prepare("UPDATE users SET mobile = ?, phone_verified = 1, phone_verified_at = NOW() WHERE id = ?");
if (!$upd) {
    error_log('phone_verify prepare failed: ' . $conn->error);
    // Unknown column => the phone_verified columns are missing on this DB.
    if (stripos($conn->error, 'unknown column') !== false) {
        hd_pv_json(['ok' => false, 'error' => 'Phone verification is not set up yet. Please contact support.'], 500);
    }
    hd_pv_json(['ok' => false, 'error' => 'Could not save your verification. Please try again.'], 500);
}
$upd->bind_param('si', $mobile, $userId);
if (!$upd->execute()) {
    $errno = $upd->errno;
    $err   = $upd->error;
    $upd->close();
    error_log('phone_verify update failed (#' . $errno . '): ' . $err);
    // 1062 = duplicate entry (race): another account grabbed this number.
    if ($errno === 1062) {
        hd_pv_json(['ok' => false, 'error' => 'This number is already registered to another account. Please contact support.'], 409);
    }
    hd_pv_json(['ok' => false, 'error' => 'Could not save your verification. Please try again.'], 500);
}
$upd->close();

hd_refresh_session_user($conn);

hd_pv_json(['ok' => true, 'phone' => $mobile]);
