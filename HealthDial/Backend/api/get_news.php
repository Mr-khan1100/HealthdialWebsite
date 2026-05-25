<?php
require_once 'config.php';

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$offset = ($page - 1) * $limit;

// Get news
$query = "
    SELECT id, title, short_description, full_content, image, publish_date
    FROM news 
    WHERE status = 1 
    ORDER BY publish_date DESC 
    LIMIT $limit OFFSET $offset
";

$result = $conn->query($query);

$news = [];
while($row = $result->fetch_assoc()) {
    $news[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'shortDescription' => $row['short_description'],
        'fullContent' => $row['full_content'],
        'date' => date('M d, Y', strtotime($row['publish_date'])),
        'readTime' => ceil(str_word_count($row['short_description']) / 200) . ' min read',
        'image' => $row['image'] ? 'http://' . $_SERVER['HTTP_HOST'] . '/Healthdial/uploads/news/' . $row['image'] : null
    ];
}

// Get total count
$count_result = $conn->query("SELECT COUNT(*) as total FROM news WHERE status = 1");
$total = $count_result->fetch_assoc()['total'];

sendResponse([
    'success' => true,
    'data' => [
        'news' => $news,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => ceil($total / $limit)
        ]
    ]
]);
?>