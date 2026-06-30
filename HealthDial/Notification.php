<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'connection.inc.php';
require_once 'push_helper.php';
requireLogin();
requireAccess('notification');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// AJAX listing search for notification form
if (isset($_GET['ajax_listing_search'])) {
    header('Content-Type: application/json');
    $q = '%' . trim($_GET['q'] ?? '') . '%';
    $stmt = $conn->prepare("SELECT id, name, city FROM listings WHERE status = 'approved' AND (name LIKE ? OR city LIKE ?) ORDER BY name LIMIT 15");
    $stmt->bind_param("ss", $q, $q);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) $items[] = $row;
    echo json_encode($items);
    exit();
}

/* ===============================
   HANDLE FORM ACTIONS
================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ADD NOTIFICATION */
    if (isset($_POST['add_notification'])) {

        $title = trim($_POST['title']);
        $message = trim($_POST['message']);
        $scheduled_time = !empty($_POST['scheduled_time']) ? $_POST['scheduled_time'] : date('Y-m-d H:i:s');
        $status = $_POST['status'];
        $notification_type = 'manual';
        $target_type = $_POST['target_type'] ?? 'none';
        $target_id = !empty($_POST['target_id']) ? $_POST['target_id'] : null;
        $image_url = !empty($_POST['image_url']) ? trim($_POST['image_url']) : null;

        // Handle image upload (takes priority over URL)
        if (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/notifications/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
            $filename = 'notif_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            $uploadPath = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadPath)) {
                $image_url = 'https://healthdial.com/HealthDial/Backend/' . $uploadPath;
            }
        }

        // Simple validation
        if (empty($title) || empty($message)) {
            $_SESSION['error'] = "Title and message are required.";
            header("Location: Notification.php");
            exit();
        }

        try {
            // Debug: Check if connection is working
            if (!$conn) {
                throw new Exception("Database connection failed");
            }

            // Set connection collation to match your database
            $conn->set_charset("utf8mb4");
            $conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

            // First check table structure
            $check_table = $conn->query("SHOW CREATE TABLE notification_queue");
            if ($check_table) {
                $table_info = $check_table->fetch_assoc();
                // You can log this to see the actual structure
            }

            // For testing, let's check what columns exist
            $columns_check = $conn->query("SHOW COLUMNS FROM notification_queue");
            $columns = [];
            while ($col = $columns_check->fetch_assoc()) {
                $columns[$col['Field']] = $col;
            }

            // Prepare SQL based on actual columns
            if (isset($columns['user_id']) && $columns['user_id']['Null'] == 'YES') {
                // If user_id accepts NULL
                $sql = "INSERT INTO notification_queue 
                        (user_id, notification_type, title, message, target_type, target_id, image_url, scheduled_time, status, sent_at)
                        VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, IF(?='sent', NOW(), NULL))";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("sssssssss", 
                        $notification_type,
                        $title,
                        $message,
                        $target_type,
                        $target_id,
                        $image_url,
                        $scheduled_time,
                        $status,
                        $status
                    );
                }
            } else {
                // Simplified insert without user_id
                $sql = "INSERT INTO notification_queue 
                        (notification_type, title, message, target_type, target_id, image_url, scheduled_time, status, sent_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, IF(?='sent', NOW(), NULL))";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("sssssssss", 
                        $notification_type,
                        $title,
                        $message,
                        $target_type,
                        $target_id,
                        $image_url,
                        $scheduled_time,
                        $status,
                        $status
                    );
                }
            }

            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            // if ($stmt->execute()) {
            //     $_SESSION['success'] = "Notification created successfully.";
            // } else {
            //     throw new Exception("Execute failed: " . $stmt->error);
            // }
            if ($stmt->execute()) {

                $_SESSION['success'] = "Notification created successfully.";
                $stmt->close();

                // Capture push params before session is closed
                $doPush    = ($status === 'sent');
                $pushTitle = $title;
                $pushBody  = $message;
                $pushData  = [
                    "type"        => "manual",
                    "title"       => $title,
                    "target_type" => $target_type,
                    "target_id"   => $target_id,
                    "image_url"   => $image_url
                ];

                // Respond to admin immediately — do not block on thousands of push requests
                session_write_close();
                header("Location: Notification.php");
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request(); // PHP-FPM: send response, keep process alive
                } else {
                    ignore_user_abort(true);
                    header('Connection: close');
                    header('Content-Length: 0');
                    flush();
                }

                // Background: send push notifications after browser has navigated away
                if ($doPush) {
                    set_time_limit(0);
                    ignore_user_abort(true);
                    sendPushNotificationToAll($conn, $pushTitle, $pushBody, $pushData);
                }

                exit();

            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $stmt->close();
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
            // Store form data to repopulate
            $_SESSION['form_data'] = [
                'title' => $title,
                'message' => $message,
                'scheduled_time' => $scheduled_time,
                'status' => $status
            ];
        }

        header("Location: Notification.php");
        exit();
    }

    /* UPDATE STATUS */
    if (isset($_POST['update_status'])) {

        $id = intval($_POST['id']);
        $status = $_POST['status'];

        try {
            // Set collation
            $conn->set_charset("utf8mb4");
            $conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

            $stmt = $conn->prepare("
                UPDATE notification_queue 
                SET status = ?, sent_at = IF(?='sent', NOW(), sent_at)
                WHERE id = ?
            ");

            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("ssi", $status, $status, $id);
            
            // if ($stmt->execute()) {
            //     $_SESSION['success'] = "Notification status updated.";
            // } else {
            //     throw new Exception("Execute failed: " . $stmt->error);
            // }
            if ($stmt->execute()) {

                $_SESSION['success'] = "Notification status updated.";

                // Capture push params before session is closed
                $doPush   = ($status === 'sent');
                $pushTitle = '';
                $pushBody  = '';
                $pushData  = [];

                if ($doPush) {
                    $res   = $conn->query("SELECT title, message FROM notification_queue WHERE id = $id");
                    $notif = $res ? $res->fetch_assoc() : null;
                    if ($notif) {
                        $pushTitle = $notif['title'];
                        $pushBody  = $notif['message'];
                        $pushData  = ["type" => "manual", "title" => $notif['title']];
                    } else {
                        $doPush = false;
                    }
                }

                $stmt->close();

                // Respond to admin immediately — do not block on thousands of push requests
                session_write_close();
                header("Location: Notification.php");
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                } else {
                    ignore_user_abort(true);
                    header('Connection: close');
                    header('Content-Length: 0');
                    flush();
                }

                // Background: send push notifications after browser has navigated away
                if ($doPush) {
                    set_time_limit(0);
                    ignore_user_abort(true);
                    sendPushNotificationToAll($conn, $pushTitle, $pushBody, $pushData);
                }

                exit();

            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $stmt->close();
            
        } catch (Exception $e) {
            $_SESSION['error'] = "Update error: " . $e->getMessage();
        }
        
        header("Location: Notification.php");
        exit();
    }
}

/* DELETE */
if (isset($_GET['delete']) && $_GET['delete'] > 0) {
    $id = intval($_GET['delete']);
    
    try {
        // Set collation
        $conn->set_charset("utf8mb4");
        $conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

        $stmt = $conn->prepare("DELETE FROM notification_queue WHERE id = ?");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Notification deleted.";
        } else {
            throw new Exception("Delete failed: " . $stmt->error);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Delete error: " . $e->getMessage();
    }
    
    header("Location: Notification.php");
    exit();
}

/* FETCH DATA */
try {
    // Set collation before querying
    $conn->set_charset("utf8mb4");
    $conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    $notifications = $conn->query("
        SELECT * FROM notification_queue
        ORDER BY created_at DESC
    ");
    
    if (!$notifications) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
} catch (Exception $e) {
    $notifications = false;
    $_SESSION['error'] = "Fetch error: " . $e->getMessage();
}

// Get form data from session if exists (for repopulation)
$form_title = isset($_SESSION['form_data']['title']) ? $_SESSION['form_data']['title'] : '';
$form_message = isset($_SESSION['form_data']['message']) ? $_SESSION['form_data']['message'] : '';
$form_scheduled_time = isset($_SESSION['form_data']['scheduled_time']) ? $_SESSION['form_data']['scheduled_time'] : '';
$form_status = isset($_SESSION['form_data']['status']) ? $_SESSION['form_data']['status'] : 'sent';

// Clear form data after use
unset($_SESSION['form_data']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>

<body class="bg-gray-100">
<div class="loading-overlay" id="loading">
    <div class="spinner"></div>
</div>

<div class="flex h-screen">

<?php include 'sidebar.php'; ?>

<div class="flex-1 flex flex-col overflow-hidden">
<?php include 'header.php'; ?>

<main class="flex-1 overflow-y-auto p-6">

<?php if(isset($_SESSION['success'])): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
    <i class="fas fa-check-circle mr-2"></i>
    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
</div>
<?php endif; ?>

<?php if(isset($_SESSION['error'])): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
    <i class="fas fa-exclamation-circle mr-2"></i>
    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
</div>
<?php endif; ?>

<!-- ADD NOTIFICATION -->
<style>
.notif-search-dropdown { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #e5e7eb; border-radius:8px; max-height:200px; overflow-y:auto; z-index:50; display:none; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
.notif-search-dropdown.active { display:block; }
.notif-search-item { padding:10px 14px; cursor:pointer; border-bottom:1px solid #f3f4f6; }
.notif-search-item:hover { background:#f0f7ff; }
</style>
<div class="bg-white rounded-lg shadow p-6 mb-6">
<h3 class="text-lg font-semibold mb-4 text-gray-800">
    <i class="fas fa-bell mr-2"></i>Send Notification
</h3>

<form method="POST" id="notificationForm" class="grid grid-cols-1 md:grid-cols-4 gap-4" onsubmit="showLoading()" enctype="multipart/form-data">
<input type="hidden" name="add_notification" value="1">

<div class="md:col-span-2">
    <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
    <input type="text" name="title" placeholder="Enter notification title" required
    class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
    value="<?php echo htmlspecialchars($form_title); ?>">
</div>

<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Scheduled Time</label>
    <input type="datetime-local" name="scheduled_time"
    class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
    value="<?php echo htmlspecialchars($form_scheduled_time); ?>"
    id="scheduled_time">
</div>

<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
    <select name="status" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
    <option value="pending" <?php echo ($form_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
    <option value="sent" <?php echo ($form_status == 'sent') ? 'selected' : ''; ?>>Send Now</option>
    </select>
</div>

<div class="md:col-span-4">
    <label class="block text-sm font-medium text-gray-700 mb-1">Message *</label>
    <textarea name="message" placeholder="Enter your notification message here..." required rows="3"
    class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"><?php echo htmlspecialchars($form_message); ?></textarea>
</div>

<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Deep Link Target</label>
    <select name="target_type" id="target_type" onchange="toggleTargetFields()" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
        <option value="none">None</option>
        <option value="listing">Listing</option>
        <option value="news">News Article</option>
        <option value="screen">App Screen</option>
    </select>
</div>

<!-- Screen Selector (shown when target_type = screen) -->
<div id="screen_selector_wrap" style="display:none;">
    <label class="block text-sm font-medium text-gray-700 mb-1">Select Screen</label>
    <select name="target_id" id="screen_select" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
        <option value="">-- Choose Screen --</option>
        <option value="HomeScreen">Home</option>
        <option value="NewsScreen">News</option>
        <option value="MedicationScreen">Reminders / Medication</option>
        <option value="DocumentScreen">Documents</option>
        <option value="ProfileScreen">Profile</option>
        <option value="AddListingTab">Add Listing</option>
        <option value="LookingScreen">Looking / Search</option>
    </select>
</div>

<!-- Listing Search (shown when target_type = listing) -->
<div id="listing_search_wrap" style="display:none; position:relative;">
    <label class="block text-sm font-medium text-gray-700 mb-1">Search Listing</label>
    <input type="text" id="notif_listing_search" placeholder="Type to search listings..." autocomplete="off"
    class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
    <input type="hidden" name="target_id" id="notif_listing_id" value="">
    <div class="notif-search-dropdown" id="notif_listing_dropdown"></div>
    <div id="notif_selected_display" class="mt-1 text-sm text-green-600 font-medium" style="display:none;">
        <i class="fas fa-check-circle mr-1"></i><span></span>
    </div>
</div>

<!-- News ID (shown when target_type = news) -->
<div id="news_id_wrap" style="display:none;">
    <label class="block text-sm font-medium text-gray-700 mb-1">News Article ID</label>
    <input type="text" name="target_id" id="news_target_id" placeholder="e.g. 42"
    class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
</div>

<!-- Image Upload -->
<div class="md:col-span-2">
    <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-image mr-1 text-blue-500"></i>Upload Image</label>
    <input type="file" name="image_file" accept="image/*"
    class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 text-sm file:mr-3 file:py-1 file:px-3 file:rounded-full file:border-0 file:bg-blue-50 file:text-blue-600 file:font-medium file:cursor-pointer">
    <p class="text-xs text-gray-400 mt-1">Or provide a URL below</p>
</div>

<div class="md:col-span-2">
    <label class="block text-sm font-medium text-gray-700 mb-1">Image URL (optional)</label>
    <input type="url" name="image_url" placeholder="https://example.com/image.jpg"
    class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
</div>

<div class="md:col-span-4">
    <input type="hidden" name="status" value="sent">
    <button type="submit" class="w-full bg-blue-600 text-white rounded-lg px-4 py-3 hover:bg-blue-700 transition duration-300 font-medium">
    <i class="fas fa-paper-plane mr-2"></i> Send Notification Now
    </button>
</div>
</form>
</div>

<script>
function toggleTargetFields() {
    var type = document.getElementById('target_type').value;
    document.getElementById('screen_selector_wrap').style.display = (type === 'screen') ? '' : 'none';
    document.getElementById('listing_search_wrap').style.display = (type === 'listing') ? '' : 'none';
    document.getElementById('news_id_wrap').style.display = (type === 'news') ? '' : 'none';
    // Disable unused target_id fields
    document.getElementById('screen_select').disabled = (type !== 'screen');
    document.getElementById('notif_listing_id').disabled = (type !== 'listing');
    document.getElementById('news_target_id').disabled = (type !== 'news');
}
toggleTargetFields();

// Listing search autocomplete
var notifSearch = document.getElementById('notif_listing_search');
var notifDropdown = document.getElementById('notif_listing_dropdown');
var notifHiddenId = document.getElementById('notif_listing_id');
var notifDisplay = document.getElementById('notif_selected_display');
var notifTimeout;

if (notifSearch) {
    notifSearch.addEventListener('input', function() {
        clearTimeout(notifTimeout);
        var q = this.value.trim();
        if (q.length < 2) { notifDropdown.classList.remove('active'); return; }
        notifTimeout = setTimeout(function() {
            fetch('Notification.php?ajax_listing_search=1&q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(items) {
                    notifDropdown.innerHTML = '';
                    if (items.length === 0) {
                        notifDropdown.innerHTML = '<div class="notif-search-item" style="color:#9ca3af">No listings found</div>';
                    } else {
                        items.forEach(function(item) {
                            var div = document.createElement('div');
                            div.className = 'notif-search-item';
                            div.innerHTML = '<strong>' + item.name + '</strong> <small style="color:#9ca3af">' + (item.city || '') + '</small>';
                            div.onclick = function() {
                                notifHiddenId.value = item.id;
                                notifSearch.value = item.name + ' (' + (item.city || '') + ')';
                                notifDisplay.style.display = '';
                                notifDisplay.querySelector('span').textContent = 'Selected: ' + item.name + ' (ID: ' + item.id + ')';
                                notifDropdown.classList.remove('active');
                            };
                            notifDropdown.appendChild(div);
                        });
                    }
                    notifDropdown.classList.add('active');
                });
        }, 300);
    });
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#notif_listing_search') && !e.target.closest('#notif_listing_dropdown')) {
            notifDropdown.classList.remove('active');
        }
    });
}
</script>

<!-- LIST -->
<div class="bg-white rounded-lg shadow overflow-hidden">
<div class="p-4 border-b bg-gray-50">
    <h3 class="text-lg font-semibold text-gray-800">
        <i class="fas fa-list mr-2"></i>Notifications List
    </h3>
    <p class="text-sm text-gray-600 mt-1">
        Total: <span class="font-semibold"><?php echo $notifications ? $notifications->num_rows : 0; ?></span> notification(s)
    </p>
</div>

<?php if($notifications && $notifications->num_rows > 0): ?>
<div class="overflow-x-auto">
<table class="min-w-full divide-y divide-gray-200">
<thead class="bg-gray-50">
<tr>
<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scheduled</th>
<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-gray-200">
<?php while($row = $notifications->fetch_assoc()): ?>
<tr class="hover:bg-gray-50 transition duration-150">
<td class="px-4 py-3 text-sm font-medium text-gray-900">#<?php echo $row['id']; ?></td>
<td class="px-4 py-3 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['title']); ?></td>
<td class="px-4 py-3 text-sm text-gray-600">
    <div class="max-w-xs truncate"><?php echo htmlspecialchars($row['message']); ?></div>
</td>
<td class="px-4 py-3 text-sm">
    <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
        <?php echo htmlspecialchars($row['notification_type']); ?>
    </span>
</td>
<td class="px-4 py-3 text-sm">
    <form method="POST" class="m-0">
    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
    <input type="hidden" name="update_status" value="1">
    <select name="status" onchange="showLoading(); this.form.submit()"
        class="border rounded px-2 py-1 text-sm <?php echo $row['status'] == 'sent' ? 'bg-green-100 text-green-800 border-green-300' : 'bg-yellow-100 text-yellow-800 border-yellow-300'; ?>">
        <option value="pending" <?php if($row['status']=='pending') echo 'selected'; ?>>Pending</option>
        <option value="sent" <?php if($row['status']=='sent') echo 'selected'; ?>>Sent</option>
    </select>
    </form>
</td>
<td class="px-4 py-3 text-sm text-gray-500">
    <?php echo !empty($row['scheduled_time']) ? date('d M Y H:i', strtotime($row['scheduled_time'])) : 'Immediate'; ?>
</td>
<td class="px-4 py-3 text-sm text-gray-500">
    <?php echo date('d M Y H:i', strtotime($row['created_at'])); ?>
</td>
<td class="px-4 py-3 text-sm">
    <div class="flex space-x-2">
        <a href="?delete=<?php echo $row['id']; ?>"
        onclick="return confirmDelete()"
        class="inline-flex items-center px-3 py-1 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition duration-300 text-sm">
        <i class="fas fa-trash mr-1"></i> Delete
        </a>
    </div>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="p-8 text-center">
    <i class="fas fa-bell-slash text-4xl text-gray-300 mb-4"></i>
    <p class="text-gray-500">No notifications found.</p>
    <p class="text-sm text-gray-400 mt-2">Create your first notification using the form above.</p>
</div>
<?php endif; ?>
</div>

</main>
</div>
</div>

<script>
function showLoading() {
    document.getElementById('loading').style.display = 'flex';
}

function confirmDelete() {
    if (confirm('Are you sure you want to delete this notification?')) {
        showLoading();
        return true;
    }
    return false;
}

// Set default datetime to current time for the form
document.addEventListener('DOMContentLoaded', function() {
    const datetimeInput = document.getElementById('scheduled_time');
    
    if (datetimeInput && !datetimeInput.value) {
        const now = new Date();
        // Add 5 minutes to current time as default
        now.setMinutes(now.getMinutes() + 5);
        const localDateTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000)
            .toISOString()
            .slice(0, 16);
        datetimeInput.value = localDateTime;
    }
    
    // Hide loading when page is fully loaded
    document.getElementById('loading').style.display = 'none';
});

// Hide loading if there's a form error
window.addEventListener('pageshow', function(event) {
    document.getElementById('loading').style.display = 'none';
});
</script>
</body>
</html>