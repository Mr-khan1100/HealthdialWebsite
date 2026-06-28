<?php
/**
 * Server-side rate limiting for phone-OTP sends.
 *
 * IMPORTANT — what this can and cannot do:
 *   Firebase Phone Auth sends the SMS straight from the browser to Google. Our
 *   PHP server is only reached AFTER the user types the correct code. So this
 *   throttle works by gating the browser: the page must POST to
 *   phone_otp_request.php (which calls hd_otp_check_and_record) and get an OK
 *   BEFORE it is allowed to call signInWithPhoneNumber().
 *
 *   To make that gate impossible to bypass, enable **Firebase App Check**
 *   (reCAPTCHA Enterprise / v3) and enforce it for Authentication — then Firebase
 *   itself rejects SMS requests that don't come from our app. Combined with the
 *   **SMS region policy = India only** console setting (caps the worst-case cost)
 *   and the visible reCAPTCHA already on the page, this is solid defence in depth.
 *
 * Limits below are deliberately generous for a real user (1 send + a resend or
 * two) but choke automated abuse.
 */

if (!function_exists('hd_otp_check_and_record')) {

    // Cooldown between two sends for the same account/number.
    if (!defined('HD_OTP_COOLDOWN_SEC'))       define('HD_OTP_COOLDOWN_SEC', 60);
    // Per logged-in account.
    if (!defined('HD_OTP_MAX_PER_HOUR_USER'))  define('HD_OTP_MAX_PER_HOUR_USER', 5);
    if (!defined('HD_OTP_MAX_PER_DAY_USER'))   define('HD_OTP_MAX_PER_DAY_USER', 10);
    // Per destination phone number (protects a victim from being targeted).
    if (!defined('HD_OTP_MAX_PER_HOUR_PHONE')) define('HD_OTP_MAX_PER_HOUR_PHONE', 5);
    if (!defined('HD_OTP_MAX_PER_DAY_PHONE'))  define('HD_OTP_MAX_PER_DAY_PHONE', 10);
    // Per client IP (one attacker, many numbers).
    if (!defined('HD_OTP_MAX_PER_HOUR_IP'))    define('HD_OTP_MAX_PER_HOUR_IP', 15);

    /** Create the throttle table if it isn't there yet (idempotent). */
    function hd_otp_ensure_table(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS phone_otp_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            phone VARCHAR(20) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_created (user_id, created_at),
            INDEX idx_phone_created (phone, created_at),
            INDEX idx_ip_created (ip, created_at)
        )");
    }

    /** Best-effort client IP (works behind a single reverse proxy). */
    function hd_client_ip(): string
    {
        $fwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($fwd !== '') {
            $first = trim(explode(',', $fwd)[0]);
            if ($first !== '') {
                return substr($first, 0, 45);
            }
        }
        return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
    }

    /** COUNT rows for a column newer than $seconds ago. */
    function hd_otp_count(mysqli $conn, string $col, string $val, int $seconds): int
    {
        $sql  = "SELECT COUNT(*) AS c FROM phone_otp_requests
                 WHERE $col = ? AND created_at >= (NOW() - INTERVAL ? SECOND)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('si', $val, $seconds);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['c'] ?? 0);
    }

    /** Seconds since the most recent send for a column value (or null). */
    function hd_otp_seconds_since_last(mysqli $conn, string $col, string $val): ?int
    {
        $sql  = "SELECT TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) AS s
                 FROM phone_otp_requests WHERE $col = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $val);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return isset($row['s']) ? (int) $row['s'] : null;
    }

    /**
     * Check every limit and, if all pass, record the send.
     *
     * @return array{ok:bool, error?:string, retry_after?:int}
     */
    function hd_otp_check_and_record(mysqli $conn, int $userId, string $phone, string $ip): array
    {
        hd_otp_ensure_table($conn);

        $userKey = (string) $userId;

        // 1) Cooldown (per user and per phone).
        $sinceUser  = hd_otp_seconds_since_last($conn, 'user_id', $userKey);
        $sincePhone = hd_otp_seconds_since_last($conn, 'phone', $phone);
        foreach ([$sinceUser, $sincePhone] as $since) {
            if ($since !== null && $since < HD_OTP_COOLDOWN_SEC) {
                return [
                    'ok'          => false,
                    'error'       => 'Please wait a moment before requesting another code.',
                    'retry_after' => HD_OTP_COOLDOWN_SEC - $since,
                ];
            }
        }

        // 2) Hourly / daily ceilings.
        $checks = [
            ['user_id', $userKey, 3600,  HD_OTP_MAX_PER_HOUR_USER,  'Too many codes requested. Please try again later.'],
            ['user_id', $userKey, 86400, HD_OTP_MAX_PER_DAY_USER,   'Daily limit for verification codes reached. Please try again tomorrow.'],
            ['phone',   $phone,   3600,  HD_OTP_MAX_PER_HOUR_PHONE, 'Too many codes for this number. Please try again later.'],
            ['phone',   $phone,   86400, HD_OTP_MAX_PER_DAY_PHONE,  'Daily limit for this number reached. Please try again tomorrow.'],
            ['ip',      $ip,      3600,  HD_OTP_MAX_PER_HOUR_IP,    'Too many attempts from your network. Please try again later.'],
        ];
        foreach ($checks as [$col, $val, $window, $max, $msg]) {
            if (hd_otp_count($conn, $col, $val, $window) >= $max) {
                return ['ok' => false, 'error' => $msg, 'retry_after' => 3600];
            }
        }

        // 3) Passed — log the send.
        $stmt = $conn->prepare("INSERT INTO phone_otp_requests (user_id, phone, ip) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('iss', $userId, $phone, $ip);
            $stmt->execute();
            $stmt->close();
        }

        return ['ok' => true];
    }
}
