<?php
require_once 'includes/db.php';
require_once 'includes/seo.php';

header('Content-Type: application/xml; charset=UTF-8');

function hd_static_xml($value)
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

$urls = [
    ['loc' => HEALTHDIAL_BASE_URL . '/', 'priority' => '1.00'],
    ['loc' => HEALTHDIAL_BASE_URL . '/categories.php', 'priority' => '0.80'],
    ['loc' => HEALTHDIAL_BASE_URL . '/listings.php', 'priority' => '0.80'],
    ['loc' => HEALTHDIAL_BASE_URL . '/cities.php', 'priority' => '0.80'],
    ['loc' => HEALTHDIAL_BASE_URL . '/blog.php', 'priority' => '0.70'],
    ['loc' => HEALTHDIAL_BASE_URL . '/news.php', 'priority' => '0.70'],
    ['loc' => HEALTHDIAL_BASE_URL . '/contact.php', 'priority' => '0.50'],
    ['loc' => HEALTHDIAL_BASE_URL . '/about.php', 'priority' => '0.50'],
    ['loc' => HEALTHDIAL_BASE_URL . '/download.php', 'priority' => '0.50'],
];

$conn = getDbConnection();
if ($conn) {
    $result = $conn->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY name");
    if ($result) {
        while ($category = $result->fetch_assoc()) {
            $urls[] = [
                'loc' => HEALTHDIAL_BASE_URL . '/looking.php?cat=' . intval($category['id']) . '&name=' . rawurlencode($category['name']),
                'priority' => '0.60',
            ];
        }
    }
}

$lastmod = date('c');

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url): ?>
  <url>
    <loc><?= hd_static_xml($url['loc']) ?></loc>
    <lastmod><?= hd_static_xml($lastmod) ?></lastmod>
    <priority><?= hd_static_xml($url['priority']) ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
