<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');
error_reporting(E_ALL);

// Current Expo project ID from app.json
define('EXPO_PROJECT_ID', '5bf90ba0-fdb2-4e8f-8be8-283cd0776e8b');

function sendPushNotificationToAll($conn, $title, $body, $data = []) {
    try {
        $tokens = [];
        $seenTokens = []; // Deduplicate across both sources

        // Source 1: Logged-in users with push tokens
        $result = $conn->query("SELECT id, expo_push_token FROM users WHERE expo_push_token IS NOT NULL AND expo_push_token != ''");

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if (!empty($row['expo_push_token']) && !isset($seenTokens[$row['expo_push_token']])) {
                    $seenTokens[$row['expo_push_token']] = true;
                    $tokens[] = [
                        'user_id' => $row['id'],
                        'token' => $row['expo_push_token'],
                        'source' => 'users'
                    ];
                }
            }
        }

        // Source 2: Anonymous device tokens (users who installed app + granted permission but haven't logged in)
        $deviceResult = $conn->query("SELECT id, expo_push_token FROM device_push_tokens WHERE expo_push_token IS NOT NULL AND expo_push_token != ''");

        if ($deviceResult) {
            while ($row = $deviceResult->fetch_assoc()) {
                if (!empty($row['expo_push_token']) && !isset($seenTokens[$row['expo_push_token']])) {
                    $seenTokens[$row['expo_push_token']] = true;
                    $tokens[] = [
                        'user_id' => 'device_' . $row['id'],
                        'token' => $row['expo_push_token'],
                        'source' => 'device_push_tokens'
                    ];
                }
            }
        }

        if (empty($tokens)) {
            error_log("⚠️ No push tokens found in database");
            return ['success' => false, 'error' => 'No tokens found'];
        }

        error_log("🔥 Push notification trigger started");
        error_log("Title: " . $title);
        error_log("Body: " . $body);
        error_log("Token count: " . count($tokens));

        $totalSent = 0;
        $totalFailed = 0;
        $cleanedTokens = 0;

        // Send ONE token at a time to avoid PUSH_TOO_MANY_EXPERIENCE_IDS error
        // (tokens from different Expo projects can't be batched together)
        foreach ($tokens as $tokenInfo) {
            $message = [
                "to" => $tokenInfo['token'],
                "sound" => "default",
                "title" => $title,
                "body" => $body,
                "data" => $data
            ];

            $ch = curl_init("https://exp.host/--/api/v2/push/send");

            $headers = [
                "Content-Type: application/json",
                "Accept: application/json"
            ];

            if (defined('EXPO_PROJECT_ID') && EXPO_PROJECT_ID) {
                $headers[] = "expo-project-id: " . EXPO_PROJECT_ID;
            }

            $jsonPayload = json_encode($message, JSON_UNESCAPED_UNICODE);

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $responseData = json_decode($response, true);
                
                if (isset($responseData['data'])) {
                    $ticket = $responseData['data'];
                    if (isset($ticket['status']) && $ticket['status'] === 'ok') {
                        $totalSent++;
                    } else {
                        $totalFailed++;
                        $errorDetail = $ticket['details']['error'] ?? '';
                        error_log("❌ Push failed for user {$tokenInfo['user_id']}: " . ($ticket['message'] ?? $errorDetail));
                        
                        // Clear invalid tokens from the correct source table
                        if ($errorDetail === 'DeviceNotRegistered' || $errorDetail === 'InvalidCredentials') {
                            if ($tokenInfo['source'] === 'device_push_tokens') {
                                $clearStmt = $conn->prepare("DELETE FROM device_push_tokens WHERE expo_push_token = ?");
                                $clearStmt->bind_param("s", $tokenInfo['token']);
                            } else {
                                $clearStmt = $conn->prepare("UPDATE users SET expo_push_token = NULL WHERE id = ?");
                                $clearStmt->bind_param("i", $tokenInfo['user_id']);
                            }
                            $clearStmt->execute();
                            $clearStmt->close();
                            $cleanedTokens++;
                            error_log("🗑️ Cleared invalid token for {$tokenInfo['source']}:{$tokenInfo['user_id']}");
                        }
                    }
                } else {
                    $totalSent++; // Assume success if no data field
                }
            } else {
                $totalFailed++;
                $responseData = json_decode($response, true);
                $errorMsg = '';
                
                if (isset($responseData['errors'][0])) {
                    $errorMsg = $responseData['errors'][0]['message'] ?? '';
                    $errorCode = $responseData['errors'][0]['code'] ?? '';
                    
                    // If token belongs to wrong project, clear it
                    if ($errorCode === 'PUSH_TOO_MANY_EXPERIENCE_IDS' || 
                        strpos($errorMsg, 'different project') !== false) {
                        if ($tokenInfo['source'] === 'device_push_tokens') {
                            $clearStmt = $conn->prepare("DELETE FROM device_push_tokens WHERE expo_push_token = ?");
                            $clearStmt->bind_param("s", $tokenInfo['token']);
                        } else {
                            $clearStmt = $conn->prepare("UPDATE users SET expo_push_token = NULL WHERE id = ?");
                            $clearStmt->bind_param("i", $tokenInfo['user_id']);
                        }
                        $clearStmt->execute();
                        $clearStmt->close();
                        $cleanedTokens++;
                        error_log("🗑️ Cleared wrong-project token for {$tokenInfo['source']}:{$tokenInfo['user_id']}");
                    }
                }
                
                error_log("❌ HTTP $httpCode for user {$tokenInfo['user_id']}: $errorMsg");
            }
        }

        error_log("✅ Push complete: $totalSent sent, $totalFailed failed, $cleanedTokens tokens cleaned");
        return ['success' => true, 'sent' => $totalSent, 'failed' => $totalFailed, 'cleaned' => $cleanedTokens];

    } catch (Exception $e) {
        error_log("❌ Push Exception: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}