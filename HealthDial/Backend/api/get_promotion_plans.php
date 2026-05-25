<?php
require_once 'config.php';

// Public API — no auth required
// Returns active promotion plans

$result = $conn->query("SELECT id, name, duration_days, price, description FROM highlight_plans WHERE is_active = 1 ORDER BY price ASC");

$plans = [];
while ($row = $result->fetch_assoc()) {
    $plans[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'duration_days' => (int)$row['duration_days'],
        'price' => (float)$row['price'],
        'description' => $row['description']
    ];
}

sendResponse([
    'success' => true,
    'data' => $plans
]);
?>
