<?php
require_once '../config.php';

$result = $conn->query("
    SELECT 
        c.*,
        COUNT(l.id) as listing_count
    FROM categories c
    LEFT JOIN listings l ON c.id = l.category_id AND l.status = 'approved'
    WHERE c.status = 1
    GROUP BY c.id
    ORDER BY c.name
");

$categories = [];
while($row = $result->fetch_assoc()) {
    $categories[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'icon' => $row['icon'],
        'listing_count' => $row['listing_count']
    ];
}

sendResponse([
    'success' => true,
    'data' => $categories
]);
?>