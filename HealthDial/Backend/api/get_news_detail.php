<?php
require_once 'config.php';

$newsId = $_GET['id'] ?? null;

if (!$newsId) {
    sendResponse(['success' => false, 'message' => 'News ID required'], 400);
}

// Get news detail and increment view count
$conn->begin_transaction();

try {
    // Get news detail
    $stmt = $conn->prepare("
        SELECT n.*, 
               DATE_FORMAT(n.publish_date, '%M %d, %Y') as formatted_date,
               TIMESTAMPDIFF(HOUR, n.created_at, NOW()) as hours_ago
        FROM news n 
        WHERE n.id = ? AND n.status = 1
    ");
    $stmt->bind_param("i", $newsId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendResponse(['success' => false, 'message' => 'News article not found'], 404);
    }
    
    $news = $result->fetch_assoc();
    
    // Increment view count
    $updateStmt = $conn->prepare("UPDATE news SET views = views + 1 WHERE id = ?");
    $updateStmt->bind_param("i", $newsId);
    $updateStmt->execute();
    
    // Get related news (same category)
    $relatedStmt = $conn->prepare("
        SELECT id, title, short_description, image, publish_date 
        FROM news 
        WHERE category = ? AND id != ? AND status = 1 
        ORDER BY publish_date DESC 
        LIMIT 3
    ");
    $relatedStmt->bind_param("si", $news['category'], $newsId);
    $relatedStmt->execute();
    $relatedResult = $relatedStmt->get_result();
    
    $relatedNews = [];
    while ($row = $relatedResult->fetch_assoc()) {
        $relatedNews[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'short_description' => substr($row['short_description'], 0, 100) . '...',
            'image' => $row['image'] ? 'http://your-server-url/uploads/news/' . $row['image'] : null,
            'publish_date' => date('M d, Y', strtotime($row['publish_date']))
        ];
    }
    
    $conn->commit();
    
    sendResponse([
        'success' => true,
        'news' => [
            'id' => $news['id'],
            'title' => $news['title'],
            'short_description' => $news['short_description'],
            'full_content' => $news['full_content'],
            'image' => $news['image'] ? 'http://your-server-url/uploads/news/' . $news['image'] : null,
            'image_url' => $news['image_url'],
            'category' => $news['category'],
            'source' => $news['source'],
            'author' => $news['author'],
            'publish_date' => $news['publish_date'],
            'formatted_date' => $news['formatted_date'],
            'views' => $news['views'] + 1,
            'hours_ago' => $news['hours_ago'],
            'is_featured' => (bool)$news['is_featured']
        ],
        'related_news' => $relatedNews
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log('News detail error: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
}
?>