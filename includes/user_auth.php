<?php
/**
 * End-user (website visitor / vendor) authentication via a PHP session.
 *
 * Accounts live in the SAME `users` + `user_tokens` tables the mobile app uses,
 * so a person has one identity across the app and the website. Session keys are
 * namespaced under `hd_user` so they never collide with the admin panel session
 * (which uses `admin_id` on the same cookie).
 *
 * The actual sign-in happens with Firebase on login.php; auth-bridge.php verifies
 * the Firebase ID token server-side and then calls hd_login_user().
 */

require_once __DIR__ . '/db.php';

function hd_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // Harden the session cookie. Secure flag only when actually on HTTPS.
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443);
        @session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        @session_start();
    }
}

/**
 * The logged-in website user, or null. Returns a small profile array:
 * ['id','name','mobile','email','profile_image'].
 */
function hd_current_user(): ?array
{
    hd_session_start();
    $u = $_SESSION['hd_user'] ?? null;
    return (is_array($u) && !empty($u['id'])) ? $u : null;
}

function hd_is_logged_in(): bool
{
    return hd_current_user() !== null;
}

function hd_user_id(): int
{
    $u = hd_current_user();
    return $u ? (int) $u['id'] : 0;
}

/**
 * Per-session CSRF token for website POST actions. Echo hd_csrf_token() into a
 * hidden form field and verify it on submit with hd_verify_csrf($_POST['csrf']).
 */
function hd_csrf_token(): string
{
    hd_session_start();
    if (empty($_SESSION['hd_csrf'])) {
        $_SESSION['hd_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['hd_csrf'];
}

function hd_verify_csrf(?string $token): bool
{
    hd_session_start();
    return !empty($_SESSION['hd_csrf']) && is_string($token)
        && hash_equals($_SESSION['hd_csrf'], $token);
}

/**
 * Store the user in the session after a verified sign-in. Accepts a full DB row
 * and keeps only the fields the website needs.
 */
function hd_login_user(array $userRow): void
{
    hd_session_start();
    session_regenerate_id(true); // prevent session fixation
    $_SESSION['hd_user'] = [
        'id'             => (int) $userRow['id'],
        'name'           => $userRow['name'] ?? '',
        'mobile'         => $userRow['mobile'] ?? '',
        'email'          => $userRow['email'] ?? '',
        'profile_image'  => $userRow['profile_image'] ?? '',
        'phone_verified' => !empty($userRow['phone_verified']) ? 1 : 0,
    ];
}

/**
 * Re-read the logged-in user from the DB and refresh the session copy. Call this
 * after changing stored fields (e.g. linking a verified phone) so the session
 * reflects the new state immediately. Returns the refreshed profile or null.
 */
function hd_refresh_session_user(mysqli $conn): ?array
{
    $current = hd_current_user();
    if (!$current) {
        return null;
    }
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return $current;
    }
    $stmt->bind_param('i', $current['id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        // hd_login_user regenerates the id; here we only want to refresh fields.
        $_SESSION['hd_user'] = [
            'id'             => (int) $row['id'],
            'name'           => $row['name'] ?? '',
            'mobile'         => $row['mobile'] ?? '',
            'email'          => $row['email'] ?? '',
            'profile_image'  => $row['profile_image'] ?? '',
            'phone_verified' => !empty($row['phone_verified']) ? 1 : 0,
        ];
        return $_SESSION['hd_user'];
    }
    return $current;
}

/**
 * Whether the logged-in user has an OTP-verified phone (the 2nd factor).
 */
function hd_phone_verified(): bool
{
    $u = hd_current_user();
    return $u !== null && !empty($u['phone_verified']);
}

/**
 * Ensure the users table has the phone-verification columns (idempotent /
 * self-healing, mirrors the rest of the codebase). Safe to call before reading
 * or writing phone_verified.
 */
function hd_ensure_phone_columns(mysqli $conn): void
{
    $res = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
          AND COLUMN_NAME IN ('phone_verified','phone_verified_at')");
    $have = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $have[] = $row['COLUMN_NAME'];
        }
    }
    $add = [];
    if (!in_array('phone_verified', $have, true)) {
        $add[] = "ADD COLUMN phone_verified TINYINT(1) NOT NULL DEFAULT 0";
    }
    if (!in_array('phone_verified_at', $have, true)) {
        $add[] = "ADD COLUMN phone_verified_at DATETIME NULL";
    }
    if ($add) {
        @$conn->query("ALTER TABLE users " . implode(', ', $add));
    }
}

function hd_logout(): void
{
    hd_session_start();
    unset($_SESSION['hd_user']);
}

/**
 * Restrict a return URL to a same-site relative path, to avoid open redirects.
 */
function hd_safe_return_url(?string $raw, string $default = 'index.php'): string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return $default;
    }
    // Reject absolute URLs and protocol-relative URLs.
    if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $raw) || strpos($raw, '//') === 0) {
        return $default;
    }
    // Must look like a local path/query.
    if ($raw[0] !== '/' && !preg_match('~^[A-Za-z0-9_\-./?&=%#]+$~', $raw)) {
        return $default;
    }
    return $raw;
}

function hd_current_request_url(): string
{
    return $_SERVER['REQUEST_URI'] ?? 'index.php';
}

function hd_login_url(?string $returnUrl = null): string
{
    $returnUrl = $returnUrl ?? hd_current_request_url();
    return 'login.php?return=' . urlencode($returnUrl);
}

/**
 * Gate a page: if not logged in, redirect to the login page with a return URL
 * and stop. Call this BEFORE any output (before including the header).
 */
function hd_require_login(?string $returnUrl = null): array
{
    $user = hd_current_user();
    if ($user) {
        return $user;
    }
    header('Location: ' . hd_login_url($returnUrl));
    exit;
}

/**
 * Gate a page on BOTH factors: logged in AND phone-verified. If not logged in,
 * go to login; if logged in but the phone isn't verified, send them to the
 * profile page's "verify phone" step (with a return URL so they come back).
 * Call BEFORE any output. Returns the user array once both factors are met.
 */
function hd_require_phone_verified(?string $returnUrl = null): array
{
    $user = hd_require_login($returnUrl);
    if (!empty($user['phone_verified'])) {
        return $user;
    }
    $returnUrl = $returnUrl ?? hd_current_request_url();
    header('Location: profile.php?verify=required&return=' . urlencode($returnUrl));
    exit;
}
