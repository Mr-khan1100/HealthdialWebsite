<?php
require_once 'connection.inc.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch($action) {
    case 'get_category_stats':
        $result = $conn->query("
            SELECT c.name, COUNT(l.id) as count 
            FROM categories c 
            LEFT JOIN listings l ON c.id = l.category_id 
            GROUP BY c.id
        ");
        
        $data = ['labels' => [], 'data' => []];
        while($row = $result->fetch_assoc()) {
            $data['labels'][] = $row['name'];
            $data['data'][] = (int)$row['count'];
        }
        
        echo json_encode($data);
        break;
        
    case 'get_user':
        $id = intval($_GET['id'] ?? 0);
        if($id <= 0) { echo json_encode(['error' => 'Invalid ID']); break; }
        
        $stmt = $conn->prepare("
            SELECT u.*, COUNT(r.id) as review_count 
            FROM users u 
            LEFT JOIN reviews r ON u.id = r.user_id 
            WHERE u.id = ?
            GROUP BY u.id
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($row = $result->fetch_assoc()) {
            $data = [
                'name' => $row['name'],
                'email' => $row['email'],
                'mobile' => $row['mobile'],
                'state' => $row['state'] ?? '',
                'google_id' => $row['google_id'] ?? '',
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
                'joined_date' => date('d M Y', strtotime($row['created_at'])),
                'review_count' => $row['review_count']
            ];
            echo json_encode($data);
        } else {
            echo json_encode(['error' => 'User not found']);
        }
        $stmt->close();
        break;
        
    case 'get_news':
        $id = intval($_GET['id'] ?? 0);
        if($id <= 0) { echo json_encode(['error' => 'Invalid ID']); break; }
        
        $stmt = $conn->prepare("SELECT * FROM news WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($row = $result->fetch_assoc()) {
            $data = [
                'title' => $row['title'],
                'short_description' => $row['short_description'],
                'full_content' => $row['full_content'],
                'image' => $row['image'],
                'publish_date' => date('d M Y', strtotime($row['publish_date']))
            ];
            echo json_encode($data);
        } else {
            echo json_encode(['error' => 'News not found']);
        }
        $stmt->close();
        break;

    case 'get_review':
        $id = intval($_GET['id'] ?? 0);
        if($id <= 0) { echo json_encode(['error' => 'Invalid ID']); break; }
        
        $stmt = $conn->prepare("
            SELECT r.*, u.name as user_name, u.email as user_email, l.name as listing_name
            FROM reviews r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN listings l ON r.listing_id = l.id
            WHERE r.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($row = $result->fetch_assoc()) {
            $statusMap = ['0' => 'pending', '1' => 'approved', '2' => 'rejected'];
            echo json_encode([
                'user_name' => $row['user_name'] ?? 'Deleted User',
                'user_email' => $row['user_email'] ?? 'N/A',
                'user_id' => $row['user_id'],
                'listing_name' => $row['listing_name'] ?? 'Deleted Listing',
                'rating' => $row['rating'],
                'review' => $row['review'],
                'status' => $statusMap[$row['status']] ?? 'unknown',
                'created_at' => date('d M Y, h:i A', strtotime($row['created_at'])),
                'updated_at' => $row['updated_at'] ? date('d M Y, h:i A', strtotime($row['updated_at'])) : null
            ]);
        } else {
            echo json_encode(['error' => 'Review not found']);
        }
        $stmt->close();
        break;

    case 'get_dashboard_stats':
        echo json_encode([
            'users' => (int)$conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'],
            'listings' => (int)$conn->query("SELECT COUNT(*) as c FROM listings")->fetch_assoc()['c'],
            'reviews' => (int)$conn->query("SELECT COUNT(*) as c FROM reviews")->fetch_assoc()['c'],
            'pending' => (int)$conn->query("SELECT COUNT(*) as c FROM listings WHERE status='pending'")->fetch_assoc()['c']
        ]);
        break;

    case 'get_medication':
        $id = intval($_GET['id'] ?? 0);
        if($id <= 0) { echo json_encode(['error' => 'Invalid ID']); break; }
        
        $stmt = $conn->prepare("
            SELECT m.*, u.name as user_name, u.mobile as user_mobile
            FROM medications m
            LEFT JOIN users u ON m.user_id = u.id
            WHERE m.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($row = $result->fetch_assoc()) {
            echo json_encode([
                'name' => $row['name'],
                'user_name' => $row['user_name'] ?? 'Deleted User',
                'user_mobile' => $row['user_mobile'] ?? '',
                'dosage' => $row['dosage'],
                'frequency' => ucfirst($row['frequency']),
                'start_date' => date('d M Y', strtotime($row['start_date'])),
                'end_date' => $row['end_date'] ? date('d M Y', strtotime($row['end_date'])) : null,
                'reminder_times' => $row['reminder_times'],
                'notes' => $row['notes'],
                'is_active' => (bool)$row['is_active'],
                'weekday' => $row['weekday'],
                'custom_dates' => $row['custom_dates']
            ]);
        } else {
            echo json_encode(['error' => 'Medication not found']);
        }
        $stmt->close();
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>