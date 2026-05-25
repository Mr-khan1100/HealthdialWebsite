<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$user = authenticateUser();
if (!$user) {
    sendResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"), true) ?? [];

/* ------------------ HELPERS ------------------ */
function requireFields(array $fields, array $data) {
    foreach ($fields as $field) {
        if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
            sendResponse([
                'success' => false,
                'message' => "Missing field: $field"
            ], 422);
        }
    }
}

function safeJsonDecode($value) {
    if (is_array($value)) {
        return $value;
    }

    if ($value === null || $value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
}

function normalizeTimeArray($value) {
    $arr = safeJsonDecode($value);
    $arr = array_values(array_filter($arr, function ($t) {
        return is_string($t) && trim($t) !== '';
    }));
    return $arr;
}

function normalizeDateArray($value) {
    $arr = safeJsonDecode($value);
    $arr = array_values(array_filter($arr, function ($d) {
        return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
    }));
    sort($arr);
    return $arr;
}

function frontendWeekdayToPhpN($weekday) {
    // CONNECT: frontend uses 1=Sun ... 7=Sat
    // PHP DateTime::format('N') uses 1=Mon ... 7=Sun
    $weekday = (int)$weekday;
    if ($weekday < 1 || $weekday > 7) return null;
    return ($weekday === 1) ? 7 : ($weekday - 1);
}

// function generateMedicationNotifications(mysqli $conn, array $medication, $limit = null) {
//     // This is for server-side queue generation (optional, if you use notification_queue)
//     $times = normalizeTimeArray($medication['reminder_times'] ?? []);
//     if (empty($times)) return;

//     $frequency = $medication['frequency'] ?? 'daily';
//     $user_id   = (int)($medication['user_id'] ?? 0);

//     $title   = "Medication Reminder";
//     $message = "Take {$medication['name']} ({$medication['dosage']})";

//     $stmt = $conn->prepare("
//         INSERT INTO notification_queue
//             (user_id, notification_type, title, message, scheduled_time, status, created_at)
//         VALUES (?, 'medication', ?, ?, ?, 'pending', NOW())
//     ");

//     if (!$stmt) {
//         return;
//     }

//     $insertCount = 0;

//     $insertOne = function (DateTime $dt) use ($stmt, $user_id, $title, $message, &$insertCount, $limit) {
//         $scheduledTime = $dt->format('Y-m-d H:i:s'); // CONNECT: fixed missing variable
//         $stmt->bind_param("isss", $user_id, $title, $message, $scheduledTime);
//         $stmt->execute();
//         $insertCount++;

//         if ($limit !== null && $insertCount >= $limit) {
//             return true;
//         }
//         return false;
//     };

//     if ($frequency === 'daily') {
//         $start = new DateTime($medication['start_date']);
//         $end = !empty($medication['end_date'])
//             ? new DateTime($medication['end_date'])
//             : (clone $start)->modify('+30 days');

//         $cursor = clone $start;
//         while ($cursor <= $end) {
//             foreach ($times as $time) {
//                 [$h, $m] = array_map('intval', explode(':', $time));
//                 $scheduled = (clone $cursor)->setTime($h, $m, 0);
//                 if ($scheduled->getTimestamp() <= time()) continue;

//                 if ($insertOne($scheduled)) {
//                     $stmt->close();
//                     return;
//                 }
//             }
//             $cursor->modify('+1 day');
//         }
//     }

//     if ($frequency === 'weekly') {
//         $start = new DateTime($medication['start_date']);
//         $end = !empty($medication['end_date'])
//             ? new DateTime($medication['end_date'])
//             : (clone $start)->modify('+30 days');

//         $frontendWeekday = isset($medication['weekday']) ? (int)$medication['weekday'] : null;
//         $targetPhpDow = $frontendWeekday ? frontendWeekdayToPhpN($frontendWeekday) : null;
//         if ($targetPhpDow === null) {
//             $stmt->close();
//             return;
//         }

//         $firstMatch = clone $start;
//         while ((int)$firstMatch->format('N') !== $targetPhpDow) {
//             $firstMatch->modify('+1 day');
//         }

//         $cursor = clone $firstMatch;
//         while ($cursor <= $end) {
//             foreach ($times as $time) {
//                 [$h, $m] = array_map('intval', explode(':', $time));
//                 $scheduled = (clone $cursor)->setTime($h, $m, 0);
//                 if ($scheduled->getTimestamp() <= time()) continue;

//                 if ($insertOne($scheduled)) {
//                     $stmt->close();
//                     return;
//                 }
//             }
//             $cursor->modify('+7 days');
//         }
//     }

//     if ($frequency === 'custom') {
//         $customDates = normalizeDateArray($medication['custom_dates'] ?? []);
//         foreach ($customDates as $dateStr) {
//             $base = DateTime::createFromFormat('Y-m-d', $dateStr);
//             if (!$base) continue;

//             foreach ($times as $time) {
//                 [$h, $m] = array_map('intval', explode(':', $time));
//                 $scheduled = (clone $base)->setTime($h, $m, 0);
//                 if ($scheduled->getTimestamp() <= time()) continue;

//                 if ($insertOne($scheduled)) {
//                     $stmt->close();
//                     return;
//                 }
//             }
//         }
//     }

//     $stmt->close();
// }

/* ------------------ ROUTES ------------------ */
switch ($method) {

    // ===== GET MEDICATIONS =====
    case 'GET':
        $stmt = $conn->prepare(
            "SELECT id, name, dosage, frequency, reminder_times, start_date, end_date, weekday, custom_dates, notes, is_active, created_at, alarm_ids
             FROM medications
             WHERE user_id = ?
             ORDER BY created_at DESC"
        );
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $row['reminder_times'] = normalizeTimeArray($row['reminder_times']);
            $row['weekday'] = isset($row['weekday']) ? (int)$row['weekday'] : 2;
            $row['custom_dates'] = normalizeDateArray($row['custom_dates']);
            $row['is_active'] = (bool)$row['is_active'];
            $row['alarm_ids'] = safeJsonDecode($row['alarm_ids'] ?? []);
            $data[] = $row;
        }

        sendResponse(['success' => true, 'medications' => $data]);
        break;

    // ===== ADD MEDICATION =====
    case 'POST':
        // CONNECT: frontend always sends reminder_times as an array
        requireFields(['name', 'dosage', 'frequency', 'reminder_times'], $input);

        $name = trim($input['name']);
        $dosage = trim($input['dosage']);
        $frequency = trim($input['frequency']);
        $notes = trim($input['notes'] ?? '');
        $alarm_ids = isset($input['alarm_ids']) ? json_encode($input['alarm_ids']) : null;

        if (!is_array($input['reminder_times']) || empty($input['reminder_times'])) {
            sendResponse(['success' => false, 'message' => 'reminder_times must be a non-empty array'], 422);
        }

        $reminderTimesJson = json_encode(array_values(array_filter($input['reminder_times'], function ($t) {
            return is_string($t) && trim($t) !== '';
        })));

        if (!$reminderTimesJson || $reminderTimesJson === '[]') {
            sendResponse(['success' => false, 'message' => 'Please provide at least one reminder time'], 422);
        }

        $start_date = $input['start_date'] ?? date('Y-m-d');
        $end_date = $input['end_date'] ?? null;

        $weekday = null;
        $customDatesJson = null;

        if ($frequency === 'daily') {
            requireFields(['start_date'], $input);
        }

        if ($frequency === 'weekly') {
            // CONNECT: frontend weekly picker -> backend weekday column
            requireFields(['weekday'], $input);
            $weekday = (int)$input['weekday'];
            if ($weekday < 1 || $weekday > 7) {
                sendResponse(['success' => false, 'message' => 'weekday must be between 1 and 7'], 422);
            }
        }

        if ($frequency === 'custom') {
            // CONNECT: frontend custom date chips -> backend custom_dates column
            requireFields(['custom_dates'], $input);
            if (!is_array($input['custom_dates']) || empty($input['custom_dates'])) {
                sendResponse(['success' => false, 'message' => 'custom_dates must be a non-empty array'], 422);
            }

            $customDates = array_values(array_filter($input['custom_dates'], function ($d) {
                return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
            }));

            if (empty($customDates)) {
                sendResponse(['success' => false, 'message' => 'Please provide valid custom dates'], 422);
            }

            $customDatesJson = json_encode($customDates);
        }

        $user_id = $user['id'];
        $weekdayDb = $weekday !== null ? (string)$weekday : null;

        $stmt = $conn->prepare(
            "INSERT INTO medications
                (user_id, name, dosage, frequency, reminder_times, start_date, end_date, weekday, custom_dates, notes, is_active, alarm_ids)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)"
        );

        if (!$stmt) {
            sendResponse(['success' => false, 'message' => 'Prepare failed: ' . $conn->error], 500);
        }

        $stmt->bind_param(
            "issssssssss",
            $user_id,
            $name,
            $dosage,
            $frequency,
            $reminderTimesJson,
            $start_date,
            $end_date,
            $weekdayDb,
            $customDatesJson,
            $notes,
            $alarm_ids
        );

        if (!$stmt->execute()) {
            sendResponse(['success' => false, 'message' => 'Insert failed: ' . $conn->error], 500);
        }

        $medicationId = $stmt->insert_id;

        // Optional server-side notification queue generation
        // generateMedicationNotifications($conn, [
        //     'user_id' => $user_id,
        //     'name' => $name,
        //     'dosage' => $dosage,
        //     'frequency' => $frequency,
        //     'reminder_times' => $reminderTimesJson,
        //     'start_date' => $start_date,
        //     'end_date' => $end_date,
        //     'weekday' => $weekday,
        //     'custom_dates' => $customDatesJson
        // ], 25);

        sendResponse([
            'success' => true,
            'message' => 'Medication added successfully',
            'medication_id' => $medicationId
        ]);
        break;

    // ===== UPDATE MEDICATION =====
    case 'PUT':
        requireFields(['id'], $input);

        $checkStmt = $conn->prepare("SELECT id FROM medications WHERE id = ? AND user_id = ?");
        $checkStmt->bind_param("ii", $input['id'], $user['id']);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows === 0) {
            sendResponse(['success' => false, 'message' => 'Medication not found'], 404);
        }
        $checkStmt->close();

        // CONNECT: supports both full edit save and partial toggle update from frontend
        $id = (int)$input['id'];
        $user_id = (int)$user['id'];

        $name = array_key_exists('name', $input) ? trim($input['name']) : null;
        $dosage = array_key_exists('dosage', $input) ? trim($input['dosage']) : null;
        $frequency = array_key_exists('frequency', $input) ? trim($input['frequency']) : null;
        $reminderTimesJson = null;

        if (array_key_exists('reminder_times', $input)) {
            if (!is_array($input['reminder_times'])) {
                sendResponse(['success' => false, 'message' => 'reminder_times must be an array'], 422);
            }
            $cleanTimes = array_values(array_filter($input['reminder_times'], function ($t) {
                return is_string($t) && trim($t) !== '';
            }));
            $reminderTimesJson = json_encode($cleanTimes);
        }

        $start_date = array_key_exists('start_date', $input) ? ($input['start_date'] ?: null) : null;
        $end_date = array_key_exists('end_date', $input) ? ($input['end_date'] ?: null) : null;
        $notes = array_key_exists('notes', $input) ? trim($input['notes']) : null;
        $is_active = array_key_exists('is_active', $input) ? (int)$input['is_active'] : null;

        $weekday = null;
        $customDatesJson = null;
        
        $alarmIdsJson = null;

        if (array_key_exists('alarm_ids', $input)) {
            $alarmIdsJson = json_encode($input['alarm_ids']);
        }

        if (array_key_exists('weekday', $input)) {
            $weekday = $input['weekday'] !== null && $input['weekday'] !== ''
                ? (int)$input['weekday']
                : null;

            if ($weekday !== null && ($weekday < 1 || $weekday > 7)) {
                sendResponse(['success' => false, 'message' => 'weekday must be between 1 and 7'], 422);
            }
        }

        if (array_key_exists('custom_dates', $input)) {
            if (!is_array($input['custom_dates'])) {
                sendResponse(['success' => false, 'message' => 'custom_dates must be an array'], 422);
            }

            $customDates = array_values(array_filter($input['custom_dates'], function ($d) {
                return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
            }));
            $customDatesJson = json_encode($customDates);
        }

        // If frequency was changed, clear non-applicable fields based on frontend logic
        if ($frequency === 'daily') {
            $weekday = null;
            $customDatesJson = null;
        } elseif ($frequency === 'weekly') {
            $customDatesJson = null;
        } elseif ($frequency === 'custom') {
            $weekday = null;
        }

        $weekdayDb = $weekday !== null ? (string)$weekday : null;

        $stmt = $conn->prepare(
            "UPDATE medications SET
                name = COALESCE(?, name),
                dosage = COALESCE(?, dosage),
                frequency = COALESCE(?, frequency),
                reminder_times = COALESCE(?, reminder_times),
                start_date = COALESCE(?, start_date),
                end_date = COALESCE(?, end_date),
                weekday = COALESCE(?, weekday),
                custom_dates = COALESCE(?, custom_dates),
                notes = COALESCE(?, notes),
                is_active = COALESCE(?, is_active),
                alarm_ids = COALESCE(?, alarm_ids)
             WHERE id = ? AND user_id = ?"
        );

        if (!$stmt) {
            sendResponse(['success' => false, 'message' => 'Prepare failed: ' . $conn->error], 500);
        }

        $stmt->bind_param(
            "sssssssssssii",
            $name,
            $dosage,
            $frequency,
            $reminderTimesJson,
            $start_date,
            $end_date,
            $weekdayDb,
            $customDatesJson,
            $notes,
            $is_active,
            $alarmIdsJson,
            $id,
            $user_id
        );

        if (!$stmt->execute()) {
            sendResponse(['success' => false, 'message' => 'Update failed: ' . $conn->error], 500);
        }

        sendResponse([
            'success' => true,
            'message' => 'Medication updated successfully',
            'affected_rows' => $stmt->affected_rows
        ]);
        break;

    // ===== DELETE MEDICATION =====
    case 'DELETE':
        requireFields(['id'], $input);

        $stmt = $conn->prepare("DELETE FROM medications WHERE id = ? AND user_id = ?");
        $id = (int)$input['id'];
        $user_id = (int)$user['id'];
        $stmt->bind_param("ii", $id, $user_id);

        if (!$stmt->execute()) {
            sendResponse(['success' => false, 'message' => 'Delete failed: ' . $conn->error], 500);
        }

        if ($stmt->affected_rows === 0) {
            sendResponse(['success' => false, 'message' => 'Medication not found'], 404);
        }

        sendResponse(['success' => true, 'message' => 'Medication deleted successfully']);
        break;

    default:
        sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}
?>