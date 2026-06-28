<?php
/**
 * Auth bridge — turns a verified Firebase sign-in into a website session.
 *
 * login.php (browser) signs the user in with Firebase (Google popup or phone
 * OTP), then POSTs the resulting ID token here as JSON. We VERIFY the token
 * server-side, find or create the matching `users` row (shared with the mobile
 * app), issue a `user_tokens` row, and start the PHP session.
 *
 * Request JSON:
 *   { id_token, phone_id_token?, name?, state?, return? }
 * Responses:
 *   { success:true,  redirect:"<safe path>" }
 *   { success:false, need_phone:true, name, email }     // new Google user → ask for phone
 *   { success:false, message:"..." }
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/firebase_config.php';
require_once __DIR__ . '/includes/firebase_verify.php';
require_once __DIR__ . '/includes/user_auth.php';

header('Content-Type: application/json; charset=UTF-8');

function hd_bridge_json($data, int $status = 200)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hd_bridge_json(['success' => false, 'message' => 'Invalid request method'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$idToken      = trim($input['id_token'] ?? '');
$phoneIdToken = trim($input['phone_id_token'] ?? '');
$nameInput    = trim($input['name'] ?? '');
$stateInput   = trim($input['state'] ?? '');
$return       = hd_safe_return_url($input['return'] ?? 'index.php');

if ($idToken === '') {
    hd_bridge_json(['success' => false, 'message' => 'Missing sign-in token'], 400);
}

$projectId = hd_firebase_project_id();
if ($projectId === '' || strpos($projectId, 'REPLACE_WITH') === 0) {
    hd_bridge_json(['success' => false, 'message' => 'Login is not configured yet. Please contact support.'], 500);
}

$claims = hd_verify_firebase_id_token($idToken, $projectId);
if (!$claims) {
    hd_bridge_json(['success' => false, 'message' => 'Sign-in could not be verified. Please try again.'], 401);
}

$conn = getDbConnection();
if (!$conn) {
    hd_bridge_json(['success' => false, 'message' => 'Service temporarily unavailable. Please try again.'], 503);
}

// Make sure phone-verification columns exist so the session reflects 2FA state.
hd_ensure_phone_columns($conn);

/* ---------- helpers ---------------------------------------------------- */

/** Candidate stored forms for an E.164 phone (handles +91 vs 10-digit). */
function hd_phone_candidates(string $e164): array
{
    $digits = preg_replace('/\D/', '', $e164);
    $cands  = [];
    if ($digits !== '') {
        $cands[] = $digits;
        if (strlen($digits) > 10) {
            $cands[] = substr($digits, -10); // local 10-digit form
        }
    }
    return array_values(array_unique($cands));
}

/** Preferred form to store for a new user (10-digit local when possible). */
function hd_phone_canonical(string $e164): string
{
    $digits = preg_replace('/\D/', '', $e164);
    if (strlen($digits) > 10) {
        return substr($digits, -10);
    }
    return $digits;
}

function hd_find_user_by_phone(mysqli $conn, string $e164): ?array
{
    $cands = hd_phone_candidates($e164);
    if (!$cands) {
        return null;
    }
    $place = implode(',', array_fill(0, count($cands), '?'));
    $types = str_repeat('s', count($cands));
    $stmt  = $conn->prepare("SELECT * FROM users WHERE mobile IN ($place) AND status = 1 LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param($types, ...$cands);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function hd_find_user_by_email(mysqli $conn, string $email): ?array
{
    if ($email === '') {
        return null;
    }
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND status = 1 LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function hd_issue_token(mysqli $conn, int $userId): void
{
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    $stmt    = $conn->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('iss', $userId, $token, $expires);
        $stmt->execute();
        $stmt->close();
    }
}

function hd_finish_login(mysqli $conn, array $user, string $return): void
{
    hd_issue_token($conn, (int) $user['id']);
    hd_login_user($user);
    hd_bridge_json(['success' => true, 'redirect' => $return]);
}

/* ---------- branch by sign-in method ---------------------------------- */

$provider = $claims['provider'] ?? '';
$isPhone  = ($provider === 'phone') || (!empty($claims['phone_number']) && empty($claims['email']));

if ($isPhone) {
    // ---- Phone OTP sign-in ----
    $phone = $claims['phone_number'] ?? '';
    if ($phone === '') {
        hd_bridge_json(['success' => false, 'message' => 'Phone number missing from sign-in.'], 400);
    }

    $user = hd_find_user_by_phone($conn, $phone);
    if ($user) {
        hd_finish_login($conn, $user, $return);
    }

    // New phone user → create account.
    $mobile = hd_phone_canonical($phone);
    $name   = $nameInput !== '' ? $nameInput : ($claims['name'] ?? 'HealthDial User');
    $state  = $stateInput !== '' ? $stateInput : 'Not specified';
    $pwd    = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (name, mobile, state, password, status) VALUES (?, ?, ?, ?, 1)");
    if (!$stmt) {
        hd_bridge_json(['success' => false, 'message' => 'Could not create your account. Please try again.'], 500);
    }
    $stmt->bind_param('ssss', $name, $mobile, $state, $pwd);
    if (!$stmt->execute()) {
        $stmt->close();
        // Likely a race / unique clash — try to find the now-existing user.
        $existing = hd_find_user_by_phone($conn, $phone);
        if ($existing) {
            hd_finish_login($conn, $existing, $return);
        }
        hd_bridge_json(['success' => false, 'message' => 'Could not create your account. Please try again.'], 500);
    }
    $newId = $stmt->insert_id;
    $stmt->close();

    $user = ['id' => $newId, 'name' => $name, 'mobile' => $mobile, 'email' => '', 'profile_image' => ''];
    hd_finish_login($conn, $user, $return);
}

// ---- Email-based sign-in: Google or Email/Password ----
$email    = trim((string) ($claims['email'] ?? ''));
$gName    = $claims['name'] ?? '';
$gPhoto   = $claims['picture'] ?? '';
$gUid     = $claims['uid'] ?? '';
$isGoogle = ($provider === 'google.com');

// Google verifies the email itself; Email/Password must be verified by the user.
$emailVerified = !empty($claims['email_verified']) || $isGoogle;

if ($email === '') {
    hd_bridge_json(['success' => false, 'message' => 'Could not read your email. Please try another method.'], 400);
}

// Anti-fraud: never create or log in an account on an unverified email.
if (!$emailVerified) {
    hd_bridge_json([
        'success'          => false,
        'email_unverified' => true,
        'message'          => 'Please verify your email first — open the verification link we emailed you, then sign in.',
    ], 403);
}

$user = hd_find_user_by_email($conn, $email);
if ($user) {
    // Existing account — backfill google_id / profile image if empty (Google only).
    if ($isGoogle) {
        $upd = $conn->prepare("UPDATE users SET google_id = COALESCE(NULLIF(google_id,''), ?), profile_image = COALESCE(NULLIF(profile_image,''), ?) WHERE id = ?");
        if ($upd) {
            $upd->bind_param('ssi', $gUid, $gPhoto, $user['id']);
            $upd->execute();
            $upd->close();
        }
    }
    hd_finish_login($conn, $user, $return);
}

// New user — create from the verified email. No phone required (mobile stays NULL).
$name  = $nameInput !== '' ? $nameInput : ($gName !== '' ? $gName : 'HealthDial User');
$state = $stateInput !== '' ? $stateInput : 'Not specified';
$pwd   = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
$gid   = $isGoogle ? $gUid : '';

$stmt = $conn->prepare("INSERT INTO users (name, email, state, google_id, profile_image, password, status) VALUES (?, ?, ?, ?, ?, ?, 1)");
if (!$stmt) {
    hd_bridge_json(['success' => false, 'message' => 'Could not create your account. Please try again.'], 500);
}
$stmt->bind_param('ssssss', $name, $email, $state, $gid, $gPhoto, $pwd);
if (!$stmt->execute()) {
    $stmt->close();
    $existing = hd_find_user_by_email($conn, $email);
    if ($existing) {
        hd_finish_login($conn, $existing, $return);
    }
    hd_bridge_json(['success' => false, 'message' => 'Could not create your account. Please try again.'], 500);
}
$newId = $stmt->insert_id;
$stmt->close();

$user = ['id' => $newId, 'name' => $name, 'mobile' => '', 'email' => $email, 'profile_image' => $gPhoto];
hd_finish_login($conn, $user, $return);
