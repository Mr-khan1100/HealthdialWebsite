<?php
require_once 'includes/db.php';
require_once 'includes/seo.php';

header('Content-Type: application/xml; charset=UTF-8');

$conn = getDbConnection();
$chunkSize = 45000;
$listingCount = 0;

if ($conn) {
    $result = $conn->query("SELECT COUNT(*) AS total FROM listings WHERE status = 'approved'");
    if ($result) {
        $listingCount = intval($result->fetch_assoc()['total'] ?? 0);
    }
}

$listingChunks = max(1, (int) ceil($listingCount / $chunkSize));
$lastmod = date('c');

function hd_xml($value)
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <sitemap>
    <loc><?= hd_xml(HEALTHDIAL_BASE_URL . '/sitemap-static.php') ?></loc>
    <lastmod><?= hd_xml($lastmod) ?></lastmod>
  </sitemap>
<?php for ($page = 1; $page <= $listingChunks; $page++): ?>
  <sitemap>
    <loc><?= hd_xml(HEALTHDIAL_BASE_URL . '/sitemap-listings.php?page=' . $page) ?></loc>
    <lastmod><?= hd_xml($lastmod) ?></lastmod>
  </sitemap>
<?php endfor; ?>
</sitemapindex>
