<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';

// Action router
$action = isset($_GET['action']) ? $_GET['action'] : '';

if (!$action && isset($_POST['action'])) {
    $action = $_POST['action'];
}

switch ($action) {
    case 'list_tickets':
        listTickets();
        break;
    case 'create_ticket':
        createTicket();
        break;
    case 'get_messages':
        getMessages();
        break;
    case 'reply_ticket':
        replyTicket();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function listTickets() {
    global $conn;
    $user = authenticateUser();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Authentication required. Please login again.']);
        http_response_code(401);
        return;
    }

    $user_id = $user['id'];
    
    $stmt = $conn->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tickets = [];
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'tickets' => $tickets
    ]);
}

function createTicket() {
    global $conn;
    
    // Auth is optional — guests can also submit tickets (e.g. forgot password)
    $user = authenticateUser();
    $user_id = $user ? $user['id'] : null;

    $data = json_decode(file_get_contents('php://input'), true);
    
    $subject = isset($data['subject']) ? trim($data['subject']) : '';
    $message = isset($data['message']) ? trim($data['message']) : '';
    
    if (empty($subject) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Subject and message are required']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        if ($user_id) {
            $stmt = $conn->prepare("INSERT INTO support_tickets (user_id, subject, status) VALUES (?, ?, 'open')");
            $stmt->bind_param("is", $user_id, $subject);
        } else {
            $stmt = $conn->prepare("INSERT INTO support_tickets (user_id, subject, status) VALUES (NULL, ?, 'open')");
            $stmt->bind_param("s", $subject);
        }
        $stmt->execute();
        
        $ticket_id = $conn->insert_id;
        
        $msg_stmt = $conn->prepare("INSERT INTO ticket_messages (ticket_id, sender_type, message) VALUES (?, 'user', ?)");
        $msg_stmt->bind_param("is", $ticket_id, $message);
        $msg_stmt->execute();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Ticket created successfully',
            'ticket_id' => $ticket_id
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getMessages() {
    global $conn;
    $user = authenticateUser();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Authentication required. Please login again.']);
        http_response_code(401);
        return;
    }

    $user_id = $user['id'];
    $ticket_id = isset($_GET['ticket_id']) ? intval($_GET['ticket_id']) : 0;
    
    if ($ticket_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ticket ID']);
        return;
    }
    
    // Validate ownership
    $check = $conn->prepare("SELECT id FROM support_tickets WHERE id = ? AND user_id = ?");
    $check->bind_param("ii", $ticket_id, $user_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Ticket not found or unauthorized']);
        return;
    }
    
    // Fetch messages
    $stmt = $conn->prepare("SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/HealthDial/healthdial-backend/uploads/support/";
    
    while ($row = $result->fetch_assoc()) {
        if ($row['attachment_path']) {
            $row['attachment_url'] = $base_url . $row['attachment_path'];
        }
        $messages[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
}

function replyTicket() {
    global $conn;
    $user = authenticateUser();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Authentication required. Please login again.']);
        http_response_code(401);
        return;
    }

    $user_id = $user['id'];
    
    // We expect a form-data request because of potential image uploads
    $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    
    if ($ticket_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ticket ID']);
        return;
    }
    
    // Validate ownership
    $check = $conn->prepare("SELECT id FROM support_tickets WHERE id = ? AND user_id = ?");
    $check->bind_param("ii", $ticket_id, $user_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Ticket not found or unauthorized']);
        return;
    }
    
    // Handle file upload
    $attachment_path = null;
    $attachment_type = null;
    
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/support/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['attachment']['name']);
        $target_file = $upload_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // Only allow images as per new requirement
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($file_type, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Only images are allowed.']);
            return;
        }
        
        // Basic Mimetype validation
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $_FILES['attachment']['tmp_name']);
        finfo_close($finfo);
        
        if (strpos($mime_type, 'image/') !== 0) {
             echo json_encode(['success' => false, 'message' => 'Invalid file content. Must be an image.']);
             return;
        }
        
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
            $attachment_path = $file_name;
            $attachment_type = 'image';
        } else {
             echo json_encode(['success' => false, 'message' => 'Failed to save attachment.']);
             return;
        }
    }
    
    if (empty($message) && !$attachment_path) {
        echo json_encode(['success' => false, 'message' => 'Message or attachment is required']);
        return;
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO ticket_messages (ticket_id, sender_type, message, attachment_path, attachment_type) VALUES (?, 'user', ?, ?, ?)");
        $stmt->bind_param("isss", $ticket_id, $message, $attachment_path, $attachment_type);
        $stmt->execute();
        
        // Update ticket updated_at and potentially status if it was answered/resolved
        $upd_stmt = $conn->prepare("UPDATE support_tickets SET updated_at = CURRENT_TIMESTAMP, status = 'open' WHERE id = ?");
        $upd_stmt->bind_param("i", $ticket_id);
        $upd_stmt->execute();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Reply sent successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
