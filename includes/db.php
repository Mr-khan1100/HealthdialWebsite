<?php
// Shared database configuration for healthdial.com website
// Keep credentials in one place — never hardcode in page files

$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "u961861187_HealthDialNew";

function getDbConnection()
{
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    // This codebase checks return values (if (!$stmt) …) and uses @ on optional
    // DDL. PHP 8.1+ defaults mysqli to THROW exceptions, which those guards don't
    // catch — an uncaught exception then prints HTML and corrupts JSON responses.
    // Force the classic return-false behaviour so the guards work everywhere.
    if (function_exists('mysqli_report')) {
        mysqli_report(MYSQLI_REPORT_OFF);
    }
    try {
        $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
        if ($conn->connect_error) {
            return null;
        }
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        return null;
    }
}

// Base URL for listing images (stay on the live CDN — static files).
define('LISTING_IMAGE_BASE', 'https://healthdial.com/HealthDial/uploads/listings/');
define('NEWS_IMAGE_BASE', 'https://healthdial.com/HealthDial/uploads/news/');

// API base URL.
// On localhost we call the LOCAL backend (HealthDial/Backend/api/) so dev work
// doesn't depend on the live site — the live Hostinger WAF blocks cross-origin /
// flagged-IP requests with a 403 challenge page. In production we use the live API.
if (!defined('API_BASE')) {
    $__hd_host = $_SERVER['HTTP_HOST'] ?? '';
    $__hd_is_local = ($__hd_host !== '') &&
        (strpos($__hd_host, 'localhost') !== false || strpos($__hd_host, '127.0.0.1') !== false);
    if ($__hd_is_local) {
        $__hd_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        define('API_BASE', $__hd_scheme . '://' . $__hd_host . '/HealthDial/Backend/api/');
    } else {
        define('API_BASE', 'https://healthdial.com/HealthDial/Backend/api/');
    }
}

/**
 * Fetch data from HealthDial API using cURL.
 * This correctly handles the concatenated JSON (config + data) payload
 * and handles SSL issues better than file_get_contents on local dev.
 */
function fetch_api_data($url)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'HealthDial/1.0'
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => ['timeout' => 15],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);
        $response = @file_get_contents($url, false, $ctx);
    }

    if (!$response)
        return null;

    // Check for concatenated JSON blocks (e.g. {config}{data})
    if (strpos($response, '}{') !== false) {
        $parts = explode('}{', $response);
        if (count($parts) >= 2) {
            $lastPart = '{' . end($parts);
            $data = json_decode($lastPart, true);
        } else {
            $data = json_decode($response, true);
        }
    } else {
        $data = json_decode($response, true);
    }

    // Safely recursively sanitize any doubled URLs from the backend API
    if ($data) {
        array_walk_recursive($data, function (&$item) {
            if (is_string($item) && strpos($item, 'http') === 0) {
                // Fix case: /healthdial/ -> /HealthDial/
                $item = str_ireplace('/healthdial/', '/HealthDial/', $item);
                // If URL contains another http/https right after the uploads/ path
                if (preg_match('/^(https?:\/\/[^\/]+\/.*?uploads\/.*?\/?)(https?:\/\/.*)$/i', $item, $matches)) {
                    $item = $matches[2];
                }
            }
        });
    }

    return $data;
}

/**
 * Returns the id of an existing listing that looks like a duplicate of the given
 * business, or null if none exists.
 *
 * Match rule: same name + same mobile, AND (roughly) the same location. GPS
 * readings jitter between submissions, so locations are matched within ~150 m
 * rather than on exact coordinates. If either record has no coordinates (0,0),
 * name + mobile alone is treated as the duplicate signal.
 */
function hd_find_duplicate_listing($conn, $name, $mobile, $latitude, $longitude)
{
    $lat = (float) $latitude;
    $lng = (float) $longitude;
    // ~0.0015 degrees ≈ 150 m — enough to absorb GPS jitter for the same place.
    $sql = "SELECT id FROM listings
            WHERE name = ? AND mobile = ?
              AND (
                    (? = 0 AND ? = 0)                       /* new submission has no GPS */
                 OR (latitude = 0 AND longitude = 0)        /* existing has no GPS       */
                 OR (ABS(latitude - ?) <= 0.0015 AND ABS(longitude - ?) <= 0.0015)
              )
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null; // fail open — don't block a real submission on a transient error
    }
    $stmt->bind_param('ssdddd', $name, $mobile, $lat, $lng, $lat, $lng);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ? (int) $row['id'] : null;
}

/**
 * Read a single value from the `settings` table, returning $default when the
 * key is missing or empty.
 */
function hd_get_setting($conn, $key, $default = null)
{
    if (!$conn) {
        return $default;
    }
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
    if (!$stmt) {
        return $default;
    }
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ($row && $row['setting_value'] !== null && $row['setting_value'] !== '') ? $row['setting_value'] : $default;
}

/**
 * QR-code unlock price in ₹ (admin-configurable via settings key `qr_code_price`).
 */
function hd_qr_price($conn)
{
    return (float) hd_get_setting($conn, 'qr_code_price', 200);
}
?>