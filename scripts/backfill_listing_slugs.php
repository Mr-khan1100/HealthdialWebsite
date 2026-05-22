<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/seo.php';

$conn = getDbConnection();
if (!$conn) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

foreach (['slug', 'city_slug', 'category_slug'] as $column) {
    if (!hd_db_has_column($conn, 'listings', $column)) {
        fwrite(STDERR, "Missing listings.$column. Run db/migrations/2026_05_22_add_listing_slugs.sql first.\n");
        exit(1);
    }
}

$batchSize = isset($argv[1]) ? max(100, min(5000, intval($argv[1]))) : 1000;
$processed = 0;

$selectStmt = $conn->prepare("
    SELECT l.id, l.name, l.address, l.city, c.name AS category_name
    FROM listings l
    LEFT JOIN categories c ON c.id = l.category_id
    WHERE l.slug IS NULL OR l.slug = ''
    ORDER BY l.id
    LIMIT ?
");
$existsStmt = $conn->prepare("SELECT id FROM listings WHERE slug = ? AND id <> ? LIMIT 1");
$updateStmt = $conn->prepare("
    UPDATE listings
    SET slug = ?, city_slug = ?, category_slug = ?
    WHERE id = ?
");

if (!$selectStmt || !$existsStmt || !$updateStmt) {
    fwrite(STDERR, "Failed to prepare statements: " . $conn->error . "\n");
    exit(1);
}

function hd_backfill_slug_exists($stmt, $slug, $id)
{
    $stmt->bind_param('si', $slug, $id);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result && $result->num_rows > 0;
}

while (true) {
    $selectStmt->bind_param('i', $batchSize);
    $selectStmt->execute();
    $result = $selectStmt->get_result();
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    if (!$rows) {
        break;
    }

    foreach ($rows as $row) {
        $id = intval($row['id']);
        $baseSlug = hd_listing_slug_from_parts($row['name'], $row['address'], $row['city'], null, false);
        $slug = $baseSlug;
        $counter = 2;

        while (hd_backfill_slug_exists($existsStmt, $slug, $id)) {
            $suffix = '-' . $counter;
            $slug = substr($baseSlug, 0, 220 - strlen($suffix)) . $suffix;
            $counter++;
        }

        $citySlug = hd_city_slug($row['city']);
        $categorySlug = hd_slugify($row['category_name'] ?? 'medical', 'medical');

        $updateStmt->bind_param('sssi', $slug, $citySlug, $categorySlug, $id);
        if (!$updateStmt->execute()) {
            fwrite(STDERR, "Failed listing $id: " . $updateStmt->error . "\n");
            continue;
        }

        $processed++;
        if ($processed % 5000 === 0) {
            echo "Processed $processed listings...\n";
        }
    }
}

echo "Done. Processed $processed listings.\n";
