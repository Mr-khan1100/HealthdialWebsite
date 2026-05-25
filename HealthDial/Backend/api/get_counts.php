<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '../config.php';

$result = $conn->query("
    SELECT 
    COUNT(DISTINCT c.id) AS total_categories, 
    COUNT(l.id) AS total_listings, 
    COUNT(DISTINCT l.city) AS total_cities 
    FROM categories c 
    LEFT JOIN listings l ON c.id = l.category_id AND l.status = 'approved';
");

$counts = [];
while ($row = $result->fetch_assoc()) {
    $counts[] = [
        'total_categories' => $row['total_categories'],
        'total_listings' => $row['total_listings'],
        'total_cities' => $row['total_cities']
    ];
}

sendResponse([
    'success' => true,
    'data' => $counts
]);
?>