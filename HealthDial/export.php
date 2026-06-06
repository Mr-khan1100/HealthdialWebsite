<?php
require_once 'connection.inc.php';
requireLogin();

$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';

if(empty($type)) {
    die("Missing type parameter");
}

// Log the export
$logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, action, details, ip_address) VALUES (?, 'data_export', ?, ?)");
$detail = "Exported $type data as $format";
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$logStmt->bind_param("iss", $_SESSION['admin_id'], $detail, $ip);
$logStmt->execute();

switch($type) {
    case 'users':
        $result = $conn->query("SELECT id, name, email, mobile, state, address, diseases, status, created_at FROM users ORDER BY id");
        $headers = ['ID','Name','Email','Mobile','State','Address','Diseases','Status','Created At'];
        $filename = 'healthdial_users_' . date('Y-m-d');
        break;
        
    case 'listings':
        $result = $conn->query("SELECT l.id, l.name, c.name as category, l.address, l.city, l.mobile, l.email, l.status, l.created_at FROM listings l LEFT JOIN categories c ON l.category_id = c.id ORDER BY l.id");
        $headers = ['ID','Name','Category','Address','City','Mobile','Email','Status','Created At'];
        $filename = 'healthdial_listings_' . date('Y-m-d');
        break;
        
    case 'reviews':
        $result = $conn->query("SELECT r.id, u.name as user_name, l.name as listing_name, r.rating, r.review, CASE r.status WHEN 0 THEN 'pending' WHEN 1 THEN 'approved' WHEN 2 THEN 'rejected' END as status, r.created_at FROM reviews r LEFT JOIN users u ON r.user_id = u.id LEFT JOIN listings l ON r.listing_id = l.id ORDER BY r.id");
        $headers = ['ID','User','Listing','Rating','Review','Status','Created At'];
        $filename = 'healthdial_reviews_' . date('Y-m-d');
        break;
        
    case 'medications':
        $result = $conn->query("SELECT m.id, u.name as user_name, m.name as medicine, m.dosage, m.frequency, m.start_date, m.end_date, IF(m.is_active,'Active','Inactive') as status FROM medications m LEFT JOIN users u ON m.user_id = u.id ORDER BY m.id");
        $headers = ['ID','User','Medicine','Dosage','Frequency','Start Date','End Date','Status'];
        $filename = 'healthdial_medications_' . date('Y-m-d');
        break;
        
    case 'notifications':
        $result = $conn->query("SELECT id, notification_type, title, message, status, scheduled_time, sent_at, created_at FROM notification_queue ORDER BY id");
        $headers = ['ID','Type','Title','Message','Status','Scheduled','Sent At','Created At'];
        $filename = 'healthdial_notifications_' . date('Y-m-d');
        break;
        
    case 'enquiries':
        $result = $conn->query("SELECT * FROM enquiries ORDER BY id");
        $headers = ['ID','Name','Email','Mobile','Subject','Message','Created At'];
        $filename = 'healthdial_enquiries_' . date('Y-m-d');
        break;
        
    default:
        die("Invalid export type");
}

if(!$result) die("Query error: " . $conn->error);

// Output CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// BOM for Excel UTF-8 support
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Headers
fputcsv($output, $headers);

// Data rows
while($row = $result->fetch_row()) {
    fputcsv($output, $row);
}

fclose($output);
exit();
