<?php
// Test different methods to fetch the API

$url = 'https://healthdial.com/HealthDial/Backend/api/get_listing_detail.php?id=2644129';

// Method 1: file_get_contents with SSL bypass
echo "=== Method 1: file_get_contents ===" . PHP_EOL;
$ctx = stream_context_create([
    'http' => ['timeout' => 15, 'ignore_errors' => true],
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
]);
$r = @file_get_contents($url, false, $ctx);
echo "Length: " . strlen($r) . PHP_EOL;
if (!$r) {
    echo "Error: " . error_get_last()['message'] . PHP_EOL;
}

// Method 2: cURL 
echo PHP_EOL . "=== Method 2: cURL ===" . PHP_EOL;
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
    $r2 = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "HTTP Code: " . $code . PHP_EOL;
    echo "Length: " . strlen($r2) . PHP_EOL;
    if ($err) echo "cURL Error: " . $err . PHP_EOL;
    if ($r2) {
        echo "First 300: " . substr($r2, 0, 300) . PHP_EOL;
        // Try parsing
        $parts = explode('}{', $r2);
        if (count($parts) >= 2) {
            $last = '{' . end($parts);
            $d = json_decode($last, true);
            echo "Parsed name: " . ($d['data']['name'] ?? 'N/A') . PHP_EOL;
        }
    }
} else {
    echo "cURL not available" . PHP_EOL;
}
