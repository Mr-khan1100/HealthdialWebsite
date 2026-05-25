<?php
require_once '../config.php';

$query = $_GET['q'] ?? '';

if (empty($query)) {
    sendResponse(['success' => false, 'message' => 'Search query required'], 400);
}

// Sanitize query
$searchQuery = '%' . $conn->real_escape_string($query) . '%';

// Check if news table has 'category' and 'source' columns
// If not, we'll need to adjust the query
$tableCheck = $conn->query("SHOW COLUMNS FROM news LIKE 'category'");
$hasCategory = $tableCheck->num_rows > 0;

$tableCheck2 = $conn->query("SHOW COLUMNS FROM news LIKE 'source'");
$hasSource = $tableCheck2->num_rows > 0;

// Build SQL query based on actual table structure
if ($hasCategory && $hasSource) {
    $sql = "SELECT n.*, 
                   DATE_FORMAT(n.publish_date, '%b %d, %Y') as formatted_date
            FROM news n 
            WHERE n.status = 1 
              AND (n.title LIKE ? 
                   OR n.short_description LIKE ? 
                   OR n.full_content LIKE ? 
                   OR n.category LIKE ?)
            ORDER BY 
              CASE 
                WHEN n.title LIKE CONCAT(?, '%') THEN 1
                WHEN n.title LIKE CONCAT('%', ?, '%') THEN 2
                ELSE 3
              END,
              n.publish_date DESC
            LIMIT 20";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $searchQuery, $searchQuery, $searchQuery, $searchQuery, $query, $query);
} else {
    // Simplified query without category and source
    $sql = "SELECT n.*, 
                   DATE_FORMAT(n.publish_date, '%b %d, %Y') as formatted_date
            FROM news n 
            WHERE n.status = 1 
              AND (n.title LIKE ? 
                   OR n.short_description LIKE ? 
                   OR n.full_content LIKE ?)
            ORDER BY 
              CASE 
                WHEN n.title LIKE CONCAT(?, '%') THEN 1
                WHEN n.title LIKE CONCAT('%', ?, '%') THEN 2
                ELSE 3
              END,
              n.publish_date DESC
            LIMIT 20";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $searchQuery, $searchQuery, $searchQuery, $query, $query);
}

$stmt->execute();
$result = $stmt->get_result();

$news = [];
while ($row = $result->fetch_assoc()) {
    // Handle image URL
    $imageUrl = null;
    if (!empty($row['image'])) {
        // Check if it's already a full URL
        if (filter_var($row['image'], FILTER_VALIDATE_URL)) {
            $imageUrl = $row['image'];
        } else {
            $imageUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/HealthDial/uploads/news/' . $row['image'];
        }
    } elseif (!empty($row['image_url'])) {
        $imageUrl = $row['image_url'];
    }
    
    $news[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'short_description' => $row['short_description'],
        'image' => $imageUrl,
        'image_url' => $imageUrl,
        'category' => $hasCategory ? ($row['category'] ?? 'General') : 'General',
        'source' => $hasSource ? ($row['source'] ?? 'HealthDial') : 'HealthDial',
        'formatted_date' => $row['formatted_date'],
        'views' => $row['views'] ?? 0,
        'publish_date' => $row['publish_date']
    ];
}

sendResponse([
    'success' => true,
    'query' => $query,
    'results' => count($news),
    'news' => $news
]);
?>