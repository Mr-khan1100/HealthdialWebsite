<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');
error_reporting(E_ALL);

require_once __DIR__ . '/vapid_helper.php';

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

        $totalSent     = 0;
        $totalFailed   = 0;
        $cleanedTokens = 0;

        // Expo supports up to 100 messages per request — batch to cut 3000 requests → 30
        $batches = array_chunk($tokens, 100);

        $curlHeaders = ["Content-Type: application/json", "Accept: application/json"];
        if (defined('EXPO_PROJECT_ID') && EXPO_PROJECT_ID) {
            $curlHeaders[] = "expo-project-id: " . EXPO_PROJECT_ID;
        }

        foreach ($batches as $batch) {
            $messages = [];
            foreach ($batch as $tokenInfo) {
                $messages[] = [
                    "to"    => $tokenInfo['token'],
                    "sound" => "default",
                    "title" => $title,
                    "body"  => $body,
                    "data"  => $data
                ];
            }

            $ch = curl_init("https://exp.host/--/api/v2/push/send");
            curl_setopt($ch, CURLOPT_HTTPHEADER,     $curlHeaders);
            curl_setopt($ch, CURLOPT_POST,            1);
            curl_setopt($ch, CURLOPT_POSTFIELDS,      json_encode($messages, JSON_UNESCAPED_UNICODE));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,  true);
            curl_setopt($ch, CURLOPT_ENCODING,        '');
            curl_setopt($ch, CURLOPT_TIMEOUT,         30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,  true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                $totalFailed += count($batch);
                $errData  = json_decode($response, true);
                $errMsg   = $errData['errors'][0]['message'] ?? '';
                $errCode  = $errData['errors'][0]['code']    ?? '';
                error_log("❌ Expo batch HTTP $httpCode: $errMsg");

                if ($errCode === 'PUSH_TOO_MANY_EXPERIENCE_IDS' || strpos($errMsg, 'different project') !== false) {
                    error_log("⚠️ Mixed-project tokens in batch — clean stale tokens and resend");
                }
                continue;
            }

            $responseData = json_decode($response, true);
            $tickets      = $responseData['data'] ?? [];

            foreach ($tickets as $i => $ticket) {
                $tokenInfo   = $batch[$i];
                $ticketStatus = $ticket['status'] ?? '';

                if ($ticketStatus === 'ok') {
                    $totalSent++;
                } else {
                    $totalFailed++;
                    $errorDetail = $ticket['details']['error'] ?? '';
                    error_log("❌ Push failed for {$tokenInfo['user_id']}: " . ($ticket['message'] ?? $errorDetail));

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
            }
        }

        error_log("✅ Push complete: $totalSent sent, $totalFailed failed, $cleanedTokens tokens cleaned");

        // Also fire web push to browser subscribers
        sendWebPushToAll($conn);

        return ['success' => true, 'sent' => $totalSent, 'failed' => $totalFailed, 'cleaned' => $cleanedTokens];

    } catch (Exception $e) {
        error_log("❌ Push Exception: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}