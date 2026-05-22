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
?>