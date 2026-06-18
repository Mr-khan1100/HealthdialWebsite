<?php
require_once 'connection.inc.php';
requireLogin();

// Ensure guest_* columns exist so website/app guest tickets are visible here too
$cols = [];
$colRes = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'support_tickets'");
if ($colRes) { while ($c = $colRes->fetch_assoc()) $cols[] = $c['COLUMN_NAME']; }
$addCols = [];
if (!in_array('guest_name', $cols))  $addCols[] = "ADD COLUMN guest_name VARCHAR(150) NULL";
if (!in_array('guest_email', $cols)) $addCols[] = "ADD COLUMN guest_email VARCHAR(190) NULL";
if (!in_array('guest_phone', $cols)) $addCols[] = "ADD COLUMN guest_phone VARCHAR(30) NULL";
if (!in_array('source', $cols))      $addCols[] = "ADD COLUMN source VARCHAR(30) NULL";
if ($addCols) { @$conn->query("ALTER TABLE support_tickets " . implode(', ', $addCols)); }

// Fetch all tickets. LEFT JOIN + COALESCE so guest tickets (no user account,
// e.g. from the website contact form) still appear with their name/phone.
$tickets = [];
$tRes = $conn->query("
    SELECT t.*,
           COALESCE(NULLIF(u.name, ''), t.guest_name, 'Guest') AS user_name,
           COALESCE(NULLIF(u.mobile, ''), t.guest_phone, '') AS user_mobile
    FROM support_tickets t
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY t.updated_at DESC
");
if($tRes) {
    while($row = $tRes->fetch_assoc()) {
        $tickets[] = $row;
    }
}

// Handle Admin Reply Submission (Server Side)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_reply') {
    $ticket_id = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $admin_id = $_SESSION['admin_id'] ?? null;
    
    // File upload logic
    $attachment_path = null;
    $attachment_type = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/support/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_name = time() . '_' . basename($_FILES['attachment']['name']);
        $target_file = $upload_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($file_type, $allowed_types) && move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
            $attachment_path = $file_name;
            $attachment_type = 'image';
        }
    }
    
    if ($ticket_id > 0 && (!empty($message) || $attachment_path)) {
        $stmt = $conn->prepare("INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message, attachment_path, attachment_type) VALUES (?, 'admin', ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $ticket_id, $admin_id, $message, $attachment_path, $attachment_type);
        $stmt->execute();
        
        $upd = $conn->prepare("UPDATE support_tickets SET status = 'answered', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $upd->bind_param("i", $ticket_id);
        $upd->execute();
        
        // Redirect to prevent form resubmission
        header("Location: SupportTickets.php?opened=" . $ticket_id);
        exit;
    }
}

// Handle Ticket Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $ticket_id = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    if ($ticket_id > 0 && in_array($status, ['open', 'answered', 'resolved', 'closed'])) {
        $upd = $conn->prepare("UPDATE support_tickets SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $upd->bind_param("si", $status, $ticket_id);
        $upd->execute();
        header("Location: SupportTickets.php?opened=" . $ticket_id);
        exit;
    }
}

// Fetch messages for currently opened ticket via AJAX GET call
if (isset($_GET['ajax_messages']) && isset($_GET['ticket_id'])) {
    header('Content-Type: application/json');
    $ticket_id = (int)$_GET['ticket_id'];
    $msgs = [];
    $stmt = $conn->prepare("SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()) {
        if ($r['attachment_path']) {
            $r['attachment_url'] = 'uploads/support/' . $r['attachment_path'];
        }
        $msgs[] = $r;
    }
    echo json_encode(['success' => true, 'messages' => $msgs]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Tickets — HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .chat-container { display: flex; flex-direction: column; height: 600px; background: var(--bg-card); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); overflow: hidden; }
        .chat-header { padding: 16px; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; background: #fff; }
        .chat-messages { flex: 1; padding: 20px; overflow-y: auto; background: #f8f9fc; display: flex; flex-direction: column; gap: 16px; }
        .chat-message { max-width: 75%; padding: 12px 16px; border-radius: 16px; font-size: 14px; line-height: 1.5; position: relative; }
        .chat-message-user { align-self: flex-start; background: #fff; border: 1px solid var(--border-light); border-bottom-left-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .chat-message-admin { align-self: flex-end; background: var(--primary); color: #fff; border-bottom-right-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .chat-message-admin .msg-time { color: rgba(255,255,255,0.7); }
        .chat-input-area { padding: 16px; background: #fff; border-top: 1px solid var(--border-light); }
        .msg-time { font-size: 11px; margin-top: 6px; text-align: right; color: var(--text-muted); }
        .attachment-preview { max-width: 200px; max-height: 200px; border-radius: 8px; margin-top: 8px; cursor: pointer; border: 1px solid rgba(0,0,0,0.1); }
        
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .status-open { background: #fee2e2; color: #ef4444; }
        .status-answered { background: #dbf4ff; color: #0284c7; }
        .status-resolved { background: #d1fae5; color: #10b981; }
        .status-closed { background: #f3f4f6; color: #6b7280; }
        
        .ticket-list { max-height: 600px; overflow-y: auto; }
        .ticket-item { padding: 16px; border-bottom: 1px solid var(--border-light); cursor: pointer; transition: all 0.2s; }
        .ticket-item:hover, .ticket-item.active { background: #f0f7ff; }
        .ticket-item-header { display: flex; justify-content: space-between; margin-bottom: 6px; }
        
        .support-grid { display: grid; grid-template-columns: 350px 1fr; gap: 24px; margin-top: 20px; }
        
        @media (max-width: 900px) {
            .support-grid { grid-template-columns: 1fr; }
            .chat-container { height: 500px; }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'sidebar.php'; ?>
        <div class="admin-main">
            <?php include 'header.php'; ?>
            
            <div class="admin-content">
                <div class="page-header">
                    <h1 class="page-title"><i class="fas fa-headset" style="color:var(--primary);margin-right:8px;"></i>Support Tickets</h1>
                    <p class="page-subtitle">Manage user support requests and inquiries</p>
                </div>
                
                <div class="support-grid">
                    
                    <!-- Tickets List -->
                    <div class="card fade-in" style="padding: 0; overflow: hidden;">
                        <div class="card-header" style="border-bottom: 1px solid var(--border-light); padding: 16px;">
                            <h3 class="card-title">All Tickets</h3>
                        </div>
                        <div class="ticket-list">
                            <?php if(empty($tickets)): ?>
                                <p style="padding: 20px; text-align: center; color: var(--text-muted);">No tickets found.</p>
                            <?php else: ?>
                                <?php foreach($tickets as $t): ?>
                                    <div class="ticket-item" id="ticketList_<?php echo $t['id']; ?>" onclick="openTicket(<?php echo htmlspecialchars(json_encode($t)); ?>)">
                                        <div class="ticket-item-header">
                                            <strong style="color: var(--text-primary);">#<?php echo $t['id']; ?> - <?php echo htmlspecialchars($t['user_name']); ?></strong>
                                            <span class="status-badge status-<?php echo strtolower($t['status']); ?>"><?php echo $t['status']; ?></span>
                                        </div>
                                        <div style="font-size: 13px; color: var(--text-secondary); margin-bottom: 6px; font-weight: 500;">
                                            <?php echo htmlspecialchars(substr($t['subject'], 0, 40)) . (strlen($t['subject']) > 40 ? '...' : ''); ?>
                                        </div>
                                        <?php if (!empty($t['guest_email'])): ?>
                                        <div style="font-size: 11px; color: var(--primary, #0782ca); margin-bottom: 6px;">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($t['guest_email']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <div style="display:flex; justify-content: space-between; align-items:center; font-size: 11px; color: var(--text-muted);">
                                            <?php if (($t['source'] ?? '') === 'contact_form'): ?>
                                            <span style="background:#eef2ff; color:#4f46e5; padding:1px 8px; border-radius:10px; font-weight:600;"><i class="fas fa-globe"></i> Web</span>
                                            <?php else: ?>
                                            <span></span>
                                            <?php endif; ?>
                                            <span><?php echo date('M d, h:i A', strtotime($t['updated_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Chat Area -->
                    <div class="fade-in fade-in-delay-1">
                        <div id="noTicketSelected" style="height: 600px; display: flex; align-items: center; justify-content: center; background: #fff; border-radius: var(--radius-lg); border: 2px dashed var(--border-light); color: var(--text-muted); flex-direction: column;">
                            <i class="fas fa-comments" style="font-size: 48px; margin-bottom: 16px; color: #cbd5e1;"></i>
                            <h3>Select a ticket to view conversation</h3>
                        </div>
                        
                        <div id="ticketChat" class="chat-container" style="display: none;">
                            <div class="chat-header">
                                <div>
                                    <h3 id="chatSubject" style="margin: 0; color: var(--text-primary); font-size: 16px;">Ticket Subject</h3>
                                    <p id="chatUserInfo" style="margin: 4px 0 0; font-size: 13px; color: var(--text-secondary);">User Name</p>
                                </div>
                                <form id="statusForm" method="POST" style="display:flex; gap: 8px;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="ticket_id" id="statusTicketId">
                                    <select name="status" class="form-control" style="width: 140px; font-size: 13px; padding: 6px;" onchange="this.form.submit()">
                                        <option value="open">Open</option>
                                        <option value="answered">Answered</option>
                                        <option value="resolved">Resolved</option>
                                        <option value="closed">Closed</option>
                                    </select>
                                </form>
                            </div>
                            
                            <div class="chat-messages" id="chatMessages">
                                <!-- Messages injected here -->
                                <div style="text-align:center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading messages...</div>
                            </div>
                            
                            <div class="chat-input-area">
                                <form method="POST" enctype="multipart/form-data" id="replyForm" style="display:flex; flex-direction:column; gap: 12px;">
                                    <input type="hidden" name="action" value="admin_reply">
                                    <input type="hidden" name="ticket_id" id="replyTicketId">
                                    
                                    <textarea name="message" class="form-control" rows="3" placeholder="Type your reply here..." required></textarea>
                                    
                                    <div style="display:flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <input type="file" name="attachment" id="attachment" accept="image/png, image/jpeg, image/jpg" style="display:none;">
                                            <button type="button" class="btn btn-outline" onclick="document.getElementById('attachment').click();" style="padding: 6px 12px; font-size: 13px;">
                                                <i class="fas fa-paperclip"></i> Attach Image
                                            </button>
                                            <span id="fileName" style="font-size: 12px; margin-left: 8px; color: var(--text-muted);"></span>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i> Send Reply
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
    <!-- Image Preview Modal -->
    <div id="imageModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; align-items:center; justify-content:center;">
        <span onclick="document.getElementById('imageModal').style.display='none'" style="position:absolute; top:20px; right:30px; color:#fff; font-size:30px; cursor:pointer;">&times;</span>
        <img id="modalImg" style="max-width:90%; max-height:90%; border-radius:8px;">
    </div>

    <script>
        document.getElementById('attachment').addEventListener('change', function(e) {
            if(e.target.files.length > 0) {
                document.getElementById('fileName').innerText = e.target.files[0].name;
            } else {
                document.getElementById('fileName').innerText = '';
            }
        });
        
        function openImage(src) {
            document.getElementById('modalImg').src = src;
            document.getElementById('imageModal').style.display = 'flex';
        }

        async function openTicket(ticket) {
            // UI updates
            document.querySelectorAll('.ticket-item').forEach(el => el.classList.remove('active'));
            document.getElementById('ticketList_' + ticket.id).classList.add('active');
            
            document.getElementById('noTicketSelected').style.display = 'none';
            document.getElementById('ticketChat').style.display = 'flex';
            
            document.getElementById('chatSubject').innerText = '#' + ticket.id + ' - ' + ticket.subject;
            let _info = ticket.user_name || 'Guest';
            if (ticket.user_mobile) _info += ' | ' + ticket.user_mobile;
            if (ticket.guest_email) _info += ' | ' + ticket.guest_email;
            if (ticket.source === 'contact_form') _info += '  (via Website)';
            document.getElementById('chatUserInfo').innerText = _info;
            document.getElementById('statusTicketId').value = ticket.id;
            document.getElementById('replyTicketId').value = ticket.id;
            
            // Set correct select value
            const select = document.querySelector('select[name="status"]');
            for(let i=0; i<select.options.length; i++) {
                if(select.options[i].value === ticket.status) {
                    select.selectedIndex = i;
                    break;
                }
            }
            
            // disable reply if closed maybe? 
            // document.getElementById('replyForm').style.display = ticket.status === 'closed' ? 'none' : 'flex';
            
            // Fetch messages
            const msgContainer = document.getElementById('chatMessages');
            msgContainer.innerHTML = '<div style="text-align:center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading messages...</div>';
            
            try {
                const response = await fetch(`SupportTickets.php?ajax_messages=1&ticket_id=${ticket.id}`);
                const data = await response.json();
                
                if(data.success) {
                    msgContainer.innerHTML = '';
                    if(data.messages.length === 0) {
                        msgContainer.innerHTML = '<div style="text-align:center; padding: 20px; color: #999;">No messages</div>';
                    } else {
                        data.messages.forEach(m => {
                            const isAdmin = m.sender_type === 'admin';
                            const div = document.createElement('div');
                            div.className = 'chat-message ' + (isAdmin ? 'chat-message-admin' : 'chat-message-user');
                            
                            let html = `<div>${m.message ? m.message.replace(/\\n/g, '<br>') : ''}</div>`;
                            
                            if(m.attachment_url) {
                                html += `<img src="${m.attachment_url}" class="attachment-preview" onclick="openImage('${m.attachment_url}')">`;
                            }
                            
                            const date = new Date(m.created_at);
                            html += `<div class="msg-time">${date.toLocaleString()}</div>`;
                            
                            div.innerHTML = html;
                            msgContainer.appendChild(div);
                        });
                        // Scroll to bottom
                        msgContainer.scrollTop = msgContainer.scrollHeight;
                    }
                } else {
                    msgContainer.innerHTML = '<div style="color:red; padding:20px;">Failed to load messages</div>';
                }
            } catch(e) {
                console.error(e);
                msgContainer.innerHTML = '<div style="color:red; padding:20px;">Error connecting to server</div>';
            }
        }
        
        // Auto open if redirected after reply
        <?php if(isset($_GET['opened']) && $_GET['opened'] > 0): ?>
            // Find ticket in array to pass to openTicket
            const tickets = <?php echo json_encode($tickets); ?>;
            const targetId = <?php echo (int)$_GET['opened']; ?>;
            const tt = tickets.find(t => parseInt(t.id) === targetId);
            if(tt) openTicket(tt);
        <?php endif; ?>
    </script>
</body>
</html>
