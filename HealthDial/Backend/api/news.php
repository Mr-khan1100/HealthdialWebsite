<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get news with pagination
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        $offset = ($page - 1) * $limit;
        $category = isset($_GET['category']) ? $_GET['category'] : null;
        
        // Base query
        $query = "SELECT n.*, 
                         DATE_FORMAT(n.publish_date, '%b %d, %Y') as formatted_date,
                         CASE 
                           WHEN n.publish_date = CURDATE() THEN 'Today'
                           WHEN n.publish_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 'Yesterday'
                           ELSE CONCAT(FLOOR(DATEDIFF(CURDATE(), n.publish_date)), ' days ago')
                         END as time_ago
                  FROM news n 
                  WHERE n.status = 1";
        
        // Add category filter if specified
        if ($category && $category !== 'all') {
            $query .= " AND n.category = ?";
        }
        
        $query .= " ORDER BY n.publish_date DESC LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($query);
        
        if ($category && $category !== 'all') {
            $stmt->bind_param("sii", $category, $limit, $offset);
        } else {
            $stmt->bind_param("ii", $limit, $offset);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $news = [];
        while ($row = $result->fetch_assoc()) {
            $news[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'short_description' => $row['short_description'],
                'full_content' => $row['full_content'],
                'image' => $row['image'] ? 'https://healthdial.com/HealthDial/uploads/news/' . $row['image'] : null,
                // 'image_url' => $row['image_url'], // For external images
                'category' => $row['category'],
                'source' => $row['source'] ?? 'HealthDial',
                'publish_date' => $row['publish_date'],
                // 'formatted_date' => $row['formatted_date'],
                // 'time_ago' => $row['time_ago'],
                'author' => $row['author'] ?? 'HealthDial Team',
                'views' => $row['views'] ?? 0,
                'is_featured' => (bool)($row['is_featured'] ?? 0)
            ];
        }
        
        // Get total count for pagination
        $countQuery = "SELECT COUNT(*) as total FROM news WHERE status = 1";
        if ($category && $category !== 'all') {
            $countQuery .= " AND category = ?";
        }
        
        $stmt = $conn->prepare($countQuery);
        if ($category && $category !== 'all') {
            $stmt->bind_param("s", $category);
        }
        
        $stmt->execute();
        $countResult = $stmt->get_result();
        $total = $countResult->fetch_assoc()['total'];
        
        // Get categories for filter
        $categoriesQuery = "SELECT category, COUNT(*) as count 
                           FROM news 
                           WHERE status = 1 
                           GROUP BY category 
                           ORDER BY count DESC";
        $categoriesResult = $conn->query($categoriesQuery);
        $categories = [];
        while ($cat = $categoriesResult->fetch_assoc()) {
            $categories[] = [
                'name' => $cat['category'],
                'count' => $cat['count']
            ];
        }
        
        sendResponse([
            'success' => true,
            'news' => $news,
            'total' => $total,
            'page' => $page,
            'total_pages' => ceil($total / $limit),
            'categories' => $categories
        ]);
        break;
        
    case 'POST':
        // Authenticate user (admin only)
        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
            sendResponse(['success' => false, 'message' => 'Authentication required'], 401);
        }
        
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
        $adminCheck = $conn->prepare("SELECT id FROM admin_users WHERE api_token = ?");
        $adminCheck->bind_param("s", $token);
        $adminCheck->execute();
        
        if ($adminCheck->get_result()->num_rows === 0) {
            sendResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        // Create new news article
        $data = json_decode(file_get_contents('php://input'), true);
        
        $required = ['title', 'short_description', 'full_content'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                sendResponse(['success' => false, 'message' => "Field '$field' is required"], 400);
            }
        }
        
        $title = $conn->real_escape_string($data['title']);
        $shortDescription = $conn->real_escape_string($data['short_description']);
        $fullContent = $conn->real_escape_string($data['full_content']);
        $category = $conn->real_escape_string($data['category'] ?? 'General');
        $source = $conn->real_escape_string($data['source'] ?? 'HealthDial');
        $author = $conn->real_escape_string($data['author'] ?? 'Admin');
        $publishDate = $data['publish_date'] ?? date('Y-m-d');
        $isFeatured = $data['is_featured'] ?? 0;
        $imageUrl = $data['image_url'] ?? null;
        $image = null;
        
        // Handle image upload if provided
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            $fileType = mime_content_type($file['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                sendResponse(['success' => false, 'message' => 'Only JPG, PNG, and WebP images are allowed'], 400);
            }
            
            // Generate unique filename
            $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFileName = uniqid() . '_' . time() . '.' . $fileExt;
            $uploadDir = '../uploads/news/';
            
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $uploadPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $image = $newFileName;
            }
        }
        
        $stmt = $conn->prepare("
            INSERT INTO news 
            (title, short_description, full_content, category, source, author, publish_date, image, image_url, is_featured, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $stmt->bind_param("sssssssssi", $title, $shortDescription, $fullContent, $category, $source, $author, $publishDate, $image, $imageUrl, $isFeatured);
        
        if ($stmt->execute()) {
            $newsId = $stmt->insert_id;
            
            // Log the action
            $adminId = $adminCheck->get_result()->fetch_assoc()['id'];
            $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, action, details) VALUES (?, 'add_news', ?)");
            $logDetails = "Added news: " . substr($title, 0, 50) . "...";
            $logStmt->bind_param("is", $adminId, $logDetails);
            $logStmt->execute();
            
            sendResponse([
                'success' => true,
                'message' => 'News article added successfully',
                'news_id' => $newsId
            ]);
        } else {
            sendResponse(['success' => false, 'message' => 'Failed to add news article'], 500);
        }
        break;
        
    case 'PUT':
        // Update news article
        $data = json_decode(file_get_contents('php://input'), true);
        $newsId = $data['id'] ?? null;
        
        if (!$newsId) {
            sendResponse(['success' => false, 'message' => 'News ID required'], 400);
        }
        
        $updates = [];
        $params = [];
        $types = '';
        
        if (isset($data['title'])) {
            $updates[] = "title = ?";
            $params[] = $data['title'];
            $types .= 's';
        }
        
        if (isset($data['short_description'])) {
            $updates[] = "short_description = ?";
            $params[] = $data['short_description'];
            $types .= 's';
        }
        
        if (isset($data['full_content'])) {
            $updates[] = "full_content = ?";
            $params[] = $data['full_content'];
            $types .= 's';
        }
        
        if (isset($data['category'])) {
            $updates[] = "category = ?";
            $params[] = $data['category'];
            $types .= 's';
        }
        
        if (isset($data['status'])) {
            $updates[] = "status = ?";
            $params[] = $data['status'];
            $types .= 'i';
        }
        
        if (isset($data['views'])) {
            $updates[] = "views = ?";
            $params[] = $data['views'];
            $types .= 'i';
        }
        
        if (empty($updates)) {
            sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
        }
        
        $params[] = $newsId;
        $types .= 'i';
        
        $query = "UPDATE news SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            sendResponse(['success' => true, 'message' => 'News article updated successfully']);
        } else {
            sendResponse(['success' => false, 'message' => 'Failed to update news article'], 500);
        }
        break;
        
    case 'DELETE':
        // Delete news article (soft delete)
        $data = json_decode(file_get_contents('php://input'), true);
        $newsId = $data['id'] ?? null;
        
        if (!$newsId) {
            sendResponse(['success' => false, 'message' => 'News ID required'], 400);
        }
        
        $stmt = $conn->prepare("UPDATE news SET status = 0 WHERE id = ?");
        $stmt->bind_param("i", $newsId);
        
        if ($stmt->execute()) {
            sendResponse(['success' => true, 'message' => 'News article deleted successfully']);
        } else {
            sendResponse(['success' => false, 'message' => 'Failed to delete news article'], 500);
        }
        break;
        
    default:
        sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}
?>