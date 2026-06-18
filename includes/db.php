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

// Base URL for listing images
define('LISTING_IMAGE_BASE', 'https://healthdial.com/HealthDial/uploads/listings/');
define('NEWS_IMAGE_BASE', 'https://healthdial.com/HealthDial/uploads/news/');
define('API_BASE', 'https://healthdial.com/HealthDial/Backend/api/');

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
?>