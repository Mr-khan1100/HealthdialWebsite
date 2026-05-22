<?php
require_once 'includes/db.php';
require_once 'includes/seo.php';

header('Content-Type: application/xml; charset=UTF-8');

function hd_listing_xml($value)
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function hd_listing_lastmod($value)
{
    $timestamp = !empty($value) ? strtotime($value) : false;
    return date('c', $timestamp ?: time());
}

$conn = getDbConnection();
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 45000;
$offset = ($page - 1) * $limit;
$result = null;
$stmt = null;

if ($conn) {
    $slugSelect = hd_db_has_column($conn, 'listings', 'slug') ? 'slug' : 'NULL AS slug';
    $stmt = $conn->prepare("
        SELECT id, name, address, city, updated_at, $slugSelect
        FROM listings
        WHERE status = 'approved'
        ORDER BY id
        LIMIT ? OFFSET ?
    ");

    if ($stmt) {
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php if ($result): ?>
<?php while ($listing = $result->fetch_assoc()): ?>
  <url>
    <loc><?= hd_listing_xml(hd_listing_url($listing, true)) ?></loc>
    <lastmod><?= hd_listing_xml(hd_listing_lastmod($listing['updated_at'] ?? null)) ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.70</priority>
  </url>
<?php endwhile; ?>
<?php endif; ?>
</urlset>
<?php if ($stmt) { $stmt->close(); } ?>
