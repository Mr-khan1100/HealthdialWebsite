<?php
// ajax.php
require_once 'connection.inc.php';
requireLogin();

header('Content-Type: application/json');

if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'get_review':
            if (isset($_GET['id']) && $_GET['id'] > 0) {
                $id = $_GET['id'];
                $result = $conn->query("
                    SELECT r.*, 
                           u.name as user_name, 
                           u.email as user_email,
                           l.name as listing_name,
                           l.type as listing_type
                    FROM reviews r
                    LEFT JOIN users u ON r.user_id = u.id
                    LEFT JOIN listings l ON r.listing_id = l.id
                    WHERE r.id = $id
                ");
                
                if ($row = $result->fetch_assoc()) {
                    $row['created_at'] = date('d M Y h:i A', strtotime($row['created_at']));
                    $row['updated_at'] = $row['updated_at'] ? date('d M Y h:i A', strtotime($row['updated_at'])) : null;
                    $row['status'] = ucfirst($row['status']);
                    echo json_encode($row);
                } else {
                    echo json_encode(['error' => 'Review not found']);
                }
            }
            break;
            
        case 'bulk_review_action':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $action = $data['action'];
                $ids = $data['ids'];
                
                if (!empty($ids)) {
                    $idList = implode(',', array_map('intval', $ids));
                    
                    switch ($action) {
                        case 'approve':
                            $conn->query("UPDATE reviews SET status = '1', updated_at = NOW() WHERE id IN ($idList)");
                            break;
                        case 'reject':
                            $conn->query("UPDATE reviews SET status = '2', updated_at = NOW() WHERE id IN ($idList)");
                            break;
                        case 'delete':
                            $conn->query("DELETE FROM reviews WHERE id IN ($idList)");
                            break;
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Action completed successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'No reviews selected']);
                }
            }
            break;
            
        case 'get_user':
            // Your existing get_user code
            break;
    }
}
?>