<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        getMedicines();
        break;
    case 'POST':
        handlePost();
        break;
    case 'PUT':
        updateMedicine();
        break;
    case 'DELETE':
        deleteMedicine();
        break;
    default:
        sendResponse(['error' => 'Method not allowed'], 405);
}

function getMedicines() {
    global $conn;
    
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    if (!$user_id) {
        sendResponse(['error' => 'User ID required'], 400);
    }
    
    // Get today's reminders with times
    $query = "SELECT mr.*, 
              GROUP_CONCAT(rt.reminder_time ORDER BY rt.reminder_time) as reminder_times
              FROM medicine_reminders mr
              LEFT JOIN reminder_times rt ON mr.id = rt.reminder_id AND rt.is_active = 1
              WHERE mr.user_id = ? 
              AND mr.status = 'active'
              AND (? BETWEEN mr.start_date AND IFNULL(mr.end_date, ?))
              GROUP BY mr.id
              ORDER BY mr.medicine_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $user_id, $date, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $medicines = [];
    while ($row = $result->fetch_assoc()) {
        // Check if today is in days_of_week
        $day_of_week = strtolower(date('D', strtotime($date)));
        $days = explode(',', $row['days_of_week']);
        
        if (empty($row['days_of_week']) || in_array($day_of_week, $days)) {
            $medicines[] = [
                'id' => $row['id'],
                'medicine_name' => $row['medicine_name'],
                'dosage' => $row['dosage'],
                'frequency' => $row['frequency'],
                'times' => $row['reminder_times'] ? explode(',', $row['reminder_times']) : [],
                'instructions' => $row['instructions'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date']
            ];
        }
    }
    
    // Get today's history
    $history_query = "SELECT mh.*, mr.medicine_name 
                     FROM medicine_history mh
                     JOIN medicine_reminders mr ON mh.reminder_id = mr.id
                     WHERE mr.user_id = ? 
                     AND DATE(mh.taken_at) = ?
                     ORDER BY mh.taken_at DESC";
    
    $stmt = $conn->prepare($history_query);
    $stmt->bind_param("is", $user_id, $date);
    $stmt->execute();
    $history_result = $stmt->get_result();
    
    $history = [];
    while ($row = $history_result->fetch_assoc()) {
        $history[] = $row;
    }
    
    sendResponse([
        'medicines' => $medicines,
        'history' => $history,
        'date' => $date
    ]);
}

function handlePost() {
    $action = $_POST['action'] ?? 'create';
    
    switch ($action) {
        case 'create':
            createMedicine();
            break;
        case 'mark_taken':
            markMedicineTaken();
            break;
        case 'snooze':
            snoozeMedicine();
            break;
        default:
            sendResponse(['error' => 'Invalid action'], 400);
    }
}

function createMedicine() {
    global $conn;
    
    $user_id = $_POST['user_id'] ?? 0;
    $medicine_name = $_POST['medicine_name'] ?? '';
    $dosage = $_POST['dosage'] ?? '';
    $frequency = $_POST['frequency'] ?? 'daily';
    $days_of_week = $_POST['days_of_week'] ?? '';
    $times = isset($_POST['times']) ? json_decode($_POST['times'], true) : [];
    $start_date = $_POST['start_date'] ?? date('Y-m-d');
    $end_date = $_POST['end_date'] ?? null;
    $instructions = $_POST['instructions'] ?? '';
    
    if (!$user_id || !$medicine_name || empty($times)) {
        sendResponse(['error' => 'Required fields missing'], 400);
    }
    
    $conn->begin_transaction();
    
    try {
        // Insert medicine reminder
        $stmt = $conn->prepare("INSERT INTO medicine_reminders 
                               (user_id, medicine_name, dosage, frequency, days_of_week, 
                                start_date, end_date, instructions) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssss", $user_id, $medicine_name, $dosage, $frequency, 
                         $days_of_week, $start_date, $end_date, $instructions);
        $stmt->execute();
        
        $reminder_id = $stmt->insert_id;
        
        // Insert reminder times
        $time_stmt = $conn->prepare("INSERT INTO reminder_times (reminder_id, reminder_time) VALUES (?, ?)");
        foreach ($times as $time) {
            $time_stmt->bind_param("is", $reminder_id, $time);
            $time_stmt->execute();
        }
        
        // Create notification for each time
        foreach ($times as $time) {
            $notification_title = "Medicine Reminder";
            $notification_msg = "Time to take $medicine_name";
            $scheduled_time = date('Y-m-d H:i:s', strtotime("$start_date $time"));
            
            $notif_stmt = $conn->prepare("INSERT INTO notifications 
                                         (user_id, title, message, type, scheduled_for, data) 
                                         VALUES (?, ?, ?, 'medicine_reminder', ?, ?)");
            $data = json_encode([
                'reminder_id' => $reminder_id,
                'medicine_name' => $medicine_name,
                'dosage' => $dosage
            ]);
            $notif_stmt->bind_param("issss", $user_id, $notification_title, $notification_msg, $scheduled_time, $data);
            $notif_stmt->execute();
        }
        
        $conn->commit();
        
        sendResponse([
            'message' => 'Medicine reminder created successfully',
            'reminder_id' => $reminder_id
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        sendResponse(['error' => 'Failed to create reminder: ' . $e->getMessage()], 500);
    }
}

function markMedicineTaken() {
    global $conn;
    
    $reminder_id = $_POST['reminder_id'] ?? 0;
    $user_id = $_POST['user_id'] ?? 0;
    $time = $_POST['time'] ?? date('H:i:s');
    $notes = $_POST['notes'] ?? '';
    
    if (!$reminder_id || !$user_id) {
        sendResponse(['error' => 'Reminder ID and User ID required'], 400);
    }
    
    // Verify ownership
    $stmt = $conn->prepare("SELECT id FROM medicine_reminders WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $reminder_id, $user_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        sendResponse(['error' => 'Reminder not found or unauthorized'], 404);
    }
    
    // Insert history
    $stmt = $conn->prepare("INSERT INTO medicine_history (reminder_id, status, notes) VALUES (?, 'taken', ?)");
    $stmt->bind_param("is", $reminder_id, $notes);
    
    if ($stmt->execute()) {
        sendResponse(['message' => 'Medicine marked as taken']);
    } else {
        sendResponse(['error' => 'Failed to update history'], 500);
    }
}

function updateMedicine() {
    // Similar to create but with UPDATE
    // Implement as needed
}

function deleteMedicine() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $reminder_id = $data['reminder_id'] ?? 0;
    $user_id = $data['user_id'] ?? 0;
    
    if (!$reminder_id || !$user_id) {
        sendResponse(['error' => 'Reminder ID and User ID required'], 400);
    }
    
    $stmt = $conn->prepare("UPDATE medicine_reminders SET status = 'cancelled' WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $reminder_id, $user_id);
    
    if ($stmt->execute()) {
        sendResponse(['message' => 'Medicine reminder cancelled']);
    } else {
        sendResponse(['error' => 'Failed to cancel reminder'], 500);
    }
}

function snoozeMedicine() {
    global $conn;
    
    $reminder_id = $_POST['reminder_id'] ?? 0;
    $user_id = $_POST['user_id'] ?? 0;
    $snooze_minutes = $_POST['snooze_minutes'] ?? 10;
    
    if (!$reminder_id || !$user_id) {
        sendResponse(['error' => 'Reminder ID and User ID required'], 400);
    }
    
    // Get medicine info
    $stmt = $conn->prepare("SELECT medicine_name, dosage FROM medicine_reminders WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $reminder_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendResponse(['error' => 'Reminder not found'], 404);
    }
    
    $medicine = $result->fetch_assoc();
    
    // Create snooze notification
    $snooze_time = date('Y-m-d H:i:s', strtotime("+$snooze_minutes minutes"));
    $notification_title = "Medicine Reminder (Snoozed)";
    $notification_msg = "Snoozed: Time to take {$medicine['medicine_name']}";
    
    $stmt = $conn->prepare("INSERT INTO notifications 
                           (user_id, title, message, type, scheduled_for, data) 
                           VALUES (?, ?, ?, 'medicine_reminder', ?, ?)");
    $data = json_encode([
        'reminder_id' => $reminder_id,
        'medicine_name' => $medicine['medicine_name'],
        'dosage' => $medicine['dosage'],
        'snoozed' => true
    ]);
    $stmt->bind_param("issss", $user_id, $notification_title, $notification_msg, $snooze_time, $data);
    
    if ($stmt->execute()) {
        // Add to history as snoozed
        $history_stmt = $conn->prepare("INSERT INTO medicine_history (reminder_id, status) VALUES (?, 'snoozed')");
        $history_stmt->bind_param("i", $reminder_id);
        $history_stmt->execute();
        
        sendResponse(['message' => "Reminder snoozed for $snooze_minutes minutes"]);
    } else {
        sendResponse(['error' => 'Failed to snooze reminder'], 500);
    }
}
?>