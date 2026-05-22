<?php
define('HEALTHDIAL_BASE_URL', 'https://healthdial.com');

function hd_slugify($value, $fallback = 'item')
{
    $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) {
            $value = $ascii;
        }
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim(preg_replace('/-+/', '-', $value), '-');

    if ($value === '') {
        return $fallback;
    }

    return substr($value, 0, 180);
}

function hd_city_label($city)
{
    $city = trim((string) $city);
    if ($city === '') {
        return 'India';
    }

    $parts = preg_split('/[,|]/', $city);
    $label = trim($parts[0] ?? $city);

    return $label !== '' ? $label : 'India';
}

function hd_city_slug($city)
{
    return hd_slugify(hd_city_label($city), 'india');
}

function hd_address_slug($address, $city = '')
{
    $address = preg_replace('/\b\d{6}\b/', '', (string) $address);
    $citySlug = hd_city_slug($city);
    $skip = array_filter([
        $citySlug,
        'india',
        'maharashtra',
        'delhi',
        'uttar-pradesh',
        'haryana',
        'gujarat',
        'karnataka',
        'tamil-nadu',
        'telangana',
        'west-bengal',
        'rajasthan',
        'madhya-pradesh',
        'punjab',
        'bihar',
    ]);

    $parts = array_filter(array_map('trim', explode(',', $address)));
    $slugs = [];

    foreach ($parts as $part) {
        $slug = hd_slugify($part, '');
        if ($slug === '' || in_array($slug, $skip, true) || $slug === 'ho') {
            continue;
        }

        $slugs[] = $slug;
        if (count($slugs) >= 2) {
            break;
        }
    }

    return implode('-', $slugs);
}

function hd_listing_slug_from_parts($name, $address = '', $city = '', $id = null, $includeId = false)
{
    $slug = hd_slugify($name, 'listing');
    $areaSlug = hd_address_slug($address, $city);

    if ($areaSlug !== '' && strpos($slug, $areaSlug) === false) {
        $slug .= '-' . $areaSlug;
    }

    $slug = trim(substr($slug, 0, 190), '-');

    if ($includeId && $id) {
        $slug .= '-' . intval($id);
    }

    return $slug;
}

function hd_listing_slug($listing, $includeIdFallback = true)
{
    if (!empty($listing['slug'])) {
        return hd_slugify($listing['slug'], 'listing');
    }

    return hd_listing_slug_from_parts(
        $listing['name'] ?? 'listing',
        $listing['address'] ?? '',
        $listing['city'] ?? '',
        $listing['id'] ?? null,
        $includeIdFallback
    );
}

function hd_listing_path($listing)
{
    $city = hd_city_slug($listing['city'] ?? '');
    $slug = hd_listing_slug($listing, empty($listing['slug']));

    return '/' . $city . '/' . $slug;
}

function hd_listing_url($listing, $absolute = true)
{
    $path = hd_listing_path($listing);

    return $absolute ? HEALTHDIAL_BASE_URL . $path : $path;
}

function hd_db_has_column($conn, $table, $column)
{
    static $cache = [];
    $key = $table . '.' . $column;

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $columnEscaped = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$columnEscaped'");
    $cache[$key] = $result && $result->num_rows > 0;

    return $cache[$key];
}

function hd_get_listing_meta_by_id($conn, $id)
{
    $slugSelect = hd_db_has_column($conn, 'listings', 'slug') ? 'l.slug' : "NULL AS slug";
    $citySlugSelect = hd_db_has_column($conn, 'listings', 'city_slug') ? 'l.city_slug' : "NULL AS city_slug";
    $categorySlugSelect = hd_db_has_column($conn, 'listings', 'category_slug') ? 'l.category_slug' : "NULL AS category_slug";

    $stmt = $conn->prepare("
        SELECT
            l.id,
            l.category_id,
            l.name,
            l.address,
            l.city,
            l.status,
            l.updated_at,
            c.name AS category_name,
            $slugSelect,
            $citySlugSelect,
            $categorySlugSelect
        FROM listings l
        LEFT JOIN categories c ON c.id = l.category_id
        WHERE l.id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function hd_get_listing_id_by_slug($conn, $slug)
{
    $slug = hd_slugify($slug, '');
    if ($slug === '') {
        return 0;
    }

    if (hd_db_has_column($conn, 'listings', 'slug')) {
        $stmt = $conn->prepare("SELECT id FROM listings WHERE slug = ? AND status = 'approved' LIMIT 1");
        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($row) {
            return intval($row['id']);
        }
    }

    if (preg_match('/-(\d+)$/', $slug, $matches)) {
        return intval($matches[1]);
    }

    return 0;
}

function hd_is_production_request()
{
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    return in_array($host, ['healthdial.com', 'www.healthdial.com'], true);
}

function hd_request_path()
{
    return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
}

function hd_should_redirect_to_canonical($canonicalUrl)
{
    if (!hd_is_production_request()) {
        return false;
    }

    $canonicalPath = parse_url($canonicalUrl, PHP_URL_PATH) ?: '/';
    $currentPath = hd_request_path();

    return isset($_GET['id']) || rtrim($currentPath, '/') !== rtrim($canonicalPath, '/');
}

function hd_phone_for_schema($phone)
{
    $digits = preg_replace('/\D+/', '', (string) $phone);
    if ($digits === '') {
        return null;
    }

    if (strlen($digits) === 10) {
        return '+91' . $digits;
    }

    return '+' . $digits;
}

function hd_time_24h($time)
{
    if (empty($time)) {
        return null;
    }

    $timestamp = strtotime($time);
    return $timestamp ? date('H:i', $timestamp) : null;
}

function hd_time_label($time)
{
    $timestamp = !empty($time) ? strtotime($time) : false;
    return $timestamp ? date('g:i A', $timestamp) : null;
}

function hd_listing_hours_info($listing)
{
    if (!empty($listing['is_24x7'])) {
        return [
            'label' => 'Open 24 hours',
            'status' => 'Open now',
            'is_open' => true,
        ];
    }

    $openLabel = hd_time_label($listing['open_time'] ?? '');
    $closeLabel = hd_time_label($listing['close_time'] ?? '');

    if (!$openLabel || !$closeLabel) {
        return [
            'label' => 'Timings not available',
            'status' => null,
            'is_open' => null,
        ];
    }

    $open = hd_time_24h($listing['open_time']);
    $close = hd_time_24h($listing['close_time']);
    $isOpen = null;

    if ($open && $close) {
        $timezone = new DateTimeZone('Asia/Kolkata');
        $now = new DateTime('now', $timezone);
        [$openHour, $openMinute] = array_map('intval', explode(':', $open));
        [$closeHour, $closeMinute] = array_map('intval', explode(':', $close));
        $nowMinutes = intval($now->format('G')) * 60 + intval($now->format('i'));
        $openMinutes = $openHour * 60 + $openMinute;
        $closeMinutes = $closeHour * 60 + $closeMinute;

        if ($closeMinutes <= $openMinutes) {
            $isOpen = $nowMinutes >= $openMinutes || $nowMinutes <= $closeMinutes;
        } else {
            $isOpen = $nowMinutes >= $openMinutes && $nowMinutes <= $closeMinutes;
        }
    }

    return [
        'label' => $openLabel . ' - ' . $closeLabel,
        'status' => $isOpen === null ? null : ($isOpen ? 'Open now' : 'Closed now'),
        'is_open' => $isOpen,
    ];
}

function hd_schema_type_for_category($category)
{
    $category = strtolower((string) $category);

    if (strpos($category, 'hospital') !== false) {
        return 'Hospital';
    }
    if (strpos($category, 'clinic') !== false) {
        return 'MedicalClinic';
    }
    if (strpos($category, 'pharm') !== false || strpos($category, 'medical store') !== false) {
        return 'Pharmacy';
    }

    return 'MedicalBusiness';
}

function hd_array_without_empty($value)
{
    if (!is_array($value)) {
        return $value;
    }

    $clean = [];
    foreach ($value as $key => $item) {
        $item = hd_array_without_empty($item);
        if ($item === null || $item === '' || $item === []) {
            continue;
        }
        $clean[$key] = $item;
    }

    return $clean;
}

function hd_listing_structured_data($listing, $images, $canonicalUrl)
{
    $type = hd_schema_type_for_category($listing['category_name'] ?? '');
    $schemaTypes = $type === 'LocalBusiness' ? ['LocalBusiness'] : [$type, 'LocalBusiness'];
    $imageUrls = [];

    foreach ($images as $image) {
        $url = $image['image_path'] ?? '';
        if ($url !== '') {
            $imageUrls[] = $url;
        }
        if (count($imageUrls) >= 5) {
            break;
        }
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@type' => $schemaTypes,
        '@id' => $canonicalUrl . '#business',
        'name' => $listing['name'] ?? '',
        'url' => $canonicalUrl,
        'image' => $imageUrls,
        'description' => $listing['description'] ?? '',
        'telephone' => hd_phone_for_schema($listing['mobile'] ?? ''),
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $listing['address'] ?? '',
            'addressLocality' => hd_city_label($listing['city'] ?? ''),
            'addressCountry' => 'IN',
        ],
    ];

    if (!empty($listing['latitude']) && !empty($listing['longitude'])) {
        $schema['geo'] = [
            '@type' => 'GeoCoordinates',
            'latitude' => $listing['latitude'],
            'longitude' => $listing['longitude'],
        ];
    }

    $rating = floatval($listing['avg_rating'] ?? 0);
    $reviewCount = intval($listing['review_count'] ?? 0);
    if ($rating > 0 && $reviewCount > 0) {
        $schema['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => round($rating, 1),
            'reviewCount' => $reviewCount,
        ];
    }

    if (!empty($listing['is_24x7'])) {
        $schema['openingHours'] = 'Mo-Su 00:00-23:59';
    } else {
        $opens = hd_time_24h($listing['open_time'] ?? '');
        $closes = hd_time_24h($listing['close_time'] ?? '');
        if ($opens && $closes) {
            $schema['openingHoursSpecification'] = [[
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                'opens' => $opens,
                'closes' => $closes,
            ]];
        }
    }

    return hd_array_without_empty($schema);
}

function hd_listing_breadcrumb_structured_data($listing, $canonicalUrl)
{
    $cityLabel = hd_city_label($listing['city'] ?? '');

    return [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'HealthDial',
                'item' => HEALTHDIAL_BASE_URL . '/',
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $cityLabel,
                'item' => HEALTHDIAL_BASE_URL . '/looking.php?city=' . rawurlencode($cityLabel),
            ],
            [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $listing['name'] ?? '',
                'item' => $canonicalUrl,
            ],
        ],
    ];
}

function hd_listing_meta_description($listing)
{
    $name = trim($listing['name'] ?? 'Medical facility');
    $category = trim($listing['category_name'] ?? 'medical service');
    $city = hd_city_label($listing['city'] ?? '');
    $address = trim($listing['address'] ?? '');

    $description = trim($listing['description'] ?? '');
    if ($description !== '') {
        return substr(preg_replace('/\s+/', ' ', $description), 0, 155);
    }

    return substr("$name is a verified $category in $city. View address, contact number, timings, reviews and directions on HealthDial. $address", 0, 155);
}
?>
