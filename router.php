<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$decodedPath = urldecode($path);
$filePath = realpath(__DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $decodedPath));

if ($path !== '/' && $filePath && is_file($filePath) && strpos($filePath, __DIR__) === 0) {
    return false;
}

if ($path === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    return true;
}

if (preg_match('#^/([A-Za-z0-9-]+)/([A-Za-z0-9-]+)/?$#', $path, $matches)) {
    $_GET['city_slug'] = $matches[1];
    $_GET['slug'] = $matches[2];
    require __DIR__ . '/listing-detail.php';
    return true;
}

if ($path === '/' || $path === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}

return false;
