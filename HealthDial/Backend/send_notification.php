<?php
function sendExpoPus($messages) {
    if (empty($messages)) {
        error_log("No messages to send");
        return 0;
    }

    $url = 'https://exp.host/--/api/v2/push/send';
    $batchSize = 100;
    $batches = array_chunk($messages, $batchSize);
    $totalSent = 0;

    foreach ($batches as $batch) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json', 
                'Accept: application/json',
                'Accept-Encoding: gzip, deflate',
                'expo-project-id: 08113abe-dc1d-449d-8e30-ef15806e4c0a'
            ],
            CURLOPT_POSTFIELDS => json_encode($batch),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            error_log("cURL Error: " . curl_error($ch));
        }
        
        
        $decoded = json_decode($response, true);
if (isset($decoded['data'])) {
    foreach ($decoded['data'] as $item) {
        if (isset($item['status']) && $item['status'] !== 'ok') {
            error_log("Failed for token: {$item['to']} | {$item['message']}");
        }
    }
}

        curl_close($ch);

        // Log the response
        $logMessage = "[" . date('Y-m-d H:i:s') . "] Sent " . count($batch) . " notifications | HTTP Code: $httpCode\n";
        if ($response) {
            $logMessage .= "Response: " . $response . "\n";
        }
        
        file_put_contents(__DIR__ . '/push_log.log', $logMessage, FILE_APPEND);
        
        $totalSent += count($batch);
    }

    return $totalSent;
}
?>