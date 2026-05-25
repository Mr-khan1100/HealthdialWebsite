<?php
require_once '../config.php';
/* ==========================
   INPUT PARAMETERS
========================== */
$latitude   = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$longitude  = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
$radius     = isset($_GET['radius']) ? floatval($_GET['radius']) : 50;
$search     = isset($_GET['q']) ? trim($_GET['q']) : null;
$min_rating = isset($_GET['min_rating']) ? floatval($_GET['min_rating']) : null;

$response = [];

/* ==========================
   DISTANCE FORMULA
========================== */
$distance_calc = "";
if ($latitude && $longitude) {
    $distance_calc = ",
    (6371 * acos(
        cos(radians($latitude)) *
        cos(radians(l.latitude)) *
        cos(radians(l.longitude) - radians($longitude)) +
        sin(radians($latitude)) *
        sin(radians(l.latitude))
    )) AS distance_km";
}

/* ==========================
   CATEGORIES
========================== */
$categories = [];
$catRes = $conn->query("
    SELECT c.id, c.name, c.icon, COUNT(l.id) as listing_count
    FROM categories c
    LEFT JOIN listings l ON l.category_id = c.id AND l.status = 'approved'
    WHERE c.status = 1 
    GROUP BY c.id
    ORDER BY c.id
");

$colors = ['#0782ca', '#38bd64', '#6C5CE7', '#F59E0B', '#EF4444', '#EC4899'];
while ($row = $catRes->fetch_assoc()) {
    $idx = count($categories);
    $categories[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'icon' => $row['icon'],
        'iconType' => getIconType($row['icon']),
        'listing_count' => (int)$row['listing_count'],
        'color' => $colors[$idx % count($colors)]
    ];
}
$response['categories'] = $categories;

/* ==========================
   BANNERS
========================== */
$banners = [];
$bannerRes = $conn->query("
    SELECT id, title, image_url, link_url, sort_order
    FROM banners
    WHERE status = 1
    ORDER BY sort_order ASC, id DESC
");
if ($bannerRes) {
    while ($row = $bannerRes->fetch_assoc()) {
        $banners[] = [
            'id'        => (int)$row['id'],
            'title'     => $row['title'],
            'image_url' => $row['image_url'],
            'link_url'  => $row['link_url'],
        ];
    }
}
$response['banners'] = $banners;

/* ==========================
   FEATURED LISTINGS
   - Now reads featured_category_id from settings, or uses is_featured flag
   - includes primary image_path
========================== */
// Check for featured_category_id setting
$featuredCatId = null;
$fcRes = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'featured_category_id'");
if ($fcRes && $fcRow = $fcRes->fetch_assoc()) {
    $featuredCatId = (int)$fcRow['setting_value'];
}

$where = "WHERE l.status = 'approved'";
if ($featuredCatId) {
    $where .= " AND c.id = $featuredCatId";
} else {
    $where .= " AND l.is_featured = 1";
}
if ($latitude && $longitude) {
    $where .= " AND l.latitude IS NOT NULL AND l.longitude IS NOT NULL";
}

$featuredQuery = "
    SELECT l.*, c.name AS category_name,
    COALESCE(AVG(r.rating),0) AS avg_rating,
    COUNT(r.id) AS review_count
    $distance_calc
    FROM listings l
    LEFT JOIN categories c ON c.id = l.category_id
    LEFT JOIN reviews r ON r.listing_id = l.id
    $where
    GROUP BY l.id
";

// build HAVING clause(s)
$featuredHaving = [];
if ($latitude && $longitude) {
    $featuredHaving[] = "distance_km <= $radius";
}
if ($min_rating !== null) {
    // apply rating filter only for items that actually have rating > 0
    $min = (float)$min_rating;
    $featuredHaving[] = "COALESCE(AVG(r.rating),0) >= $min AND COALESCE(AVG(r.rating),0) > 0";
}

if (!empty($featuredHaving)) {
    $featuredQuery .= " HAVING " . implode(" AND ", $featuredHaving);
}

// ordering
if ($latitude && $longitude) {
    $featuredQuery .= " ORDER BY distance_km ASC, COALESCE(AVG(r.rating),0) DESC";
} else {
    $featuredQuery .= " ORDER BY COALESCE(AVG(r.rating),0) DESC";
}

$featuredQuery .= " LIMIT 10";

$featured = [];
$res = $conn->query($featuredQuery);
while ($row = $res->fetch_assoc()) {

    $img = null;
    $imgRes = $conn->query("
        SELECT image_path FROM listing_images
        WHERE listing_id = {$row['id']} AND is_primary = 1 LIMIT 1
    ");
    if ($imgRes && $imgRes->num_rows > 0) {
        $img = $imgRes->fetch_assoc()['image_path'];
        // if ($img) {
        //     // prefix with BASE_URL like your news block
        //     $img = BASE_URL . '/healthdial/uploads/listings/' . $img;
        // }
    }

    $item = [
        'id' => $row['id'],
        'name' => $row['name'],
        'category' => $row['category_name'],
        'address' => $row['address'],
        'rating' => round($row['avg_rating'],1),
        'reviewCount' => $row['review_count'],
        'image' => $img
    ];

    if (isset($row['distance_km'])) {
        $item['distance'] = round($row['distance_km'],2);
    }

    $featured[] = $item;
}
$response['featured'] = $featured;

/* ==========================
   🔍 SEARCH SYSTEM
========================== */
$searchResults = [];

if ($search && strlen($search) >= 2) {

    $searchParam = '%' . $search . '%';

    $searchQuery = "
        SELECT l.id, l.name, l.address,
        c.name AS category_name,
        COALESCE(AVG(r.rating),0) AS avg_rating
        $distance_calc
        FROM listings l
        LEFT JOIN categories c ON c.id = l.category_id
        LEFT JOIN reviews r ON r.listing_id = l.id
        WHERE l.status = 'approved'
        AND (
            l.name LIKE ? OR
            l.address LIKE ? OR
            c.name LIKE ?
        )
        GROUP BY l.id
        ORDER BY avg_rating DESC
        LIMIT 15
    ";

    $searchStmt = $conn->prepare($searchQuery);
    if ($searchStmt) {
        $searchStmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
        $searchStmt->execute();
        $res = $searchStmt->get_result();
        while ($row = $res->fetch_assoc()) {

            $img = null;
            $imgStmt = $conn->prepare("
                SELECT image_path FROM listing_images
                WHERE listing_id = ? AND is_primary = 1 LIMIT 1
            ");
            $imgStmt->bind_param("i", $row['id']);
            $imgStmt->execute();
            $imgRes = $imgStmt->get_result();
            if ($imgRes->num_rows > 0) {
                $img = $imgRes->fetch_assoc()['image_path'];
            }
            $imgStmt->close();

            $item = [
                'id' => $row['id'],
                'name' => $row['name'],
                'category' => $row['category_name'],
                'address' => $row['address'],
                'rating' => round($row['avg_rating'],1),
                'image' => $img
            ];

            if (isset($row['distance_km'])) {
                $item['distance'] = round($row['distance_km'],2);
            }

            $searchResults[] = $item;
        }
        $searchStmt->close();
    }
}

$response['searchResults'] = $searchResults;

/* ==========================
   CATEGORY LISTINGS
   - Fetches top listings for each active category
   - Also keeps 'hospitals' key for backward compatibility
========================== */
$category_listings = [];
$hospitals = [];

// Get all active categories
$activeCats = $conn->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY id ASC");
while ($cat = $activeCats->fetch_assoc()) {
    $catId = (int)$cat['id'];
    $catName = $cat['name'];

    $catQuery = "
        SELECT l.id, l.name, l.address,
               c.name AS category_name,
               COALESCE(AVG(r.rating),0) AS avg_rating
               $distance_calc
        FROM listings l
        LEFT JOIN categories c ON c.id = l.category_id
        LEFT JOIN reviews r ON r.listing_id = l.id
        WHERE l.status = 'approved'
          AND c.id = $catId";

    if ($latitude && $longitude) {
        $catQuery .= " AND l.latitude IS NOT NULL AND l.longitude IS NOT NULL";
    }

    $catQuery .= " GROUP BY l.id";

    // HAVING clauses
    $catHaving = [];
    if ($latitude && $longitude) {
        $catHaving[] = "distance_km <= $radius";
    }
    if ($min_rating !== null) {
        $min = (float)$min_rating;
        $catHaving[] = "COALESCE(AVG(r.rating),0) >= $min AND COALESCE(AVG(r.rating),0) > 0";
    }
    if (!empty($catHaving)) {
        $catQuery .= " HAVING " . implode(" AND ", $catHaving);
    }

    // Ordering
    if ($latitude && $longitude) {
        $catQuery .= " ORDER BY distance_km ASC, COALESCE(AVG(r.rating),0) DESC";
    } else {
        $catQuery .= " ORDER BY COALESCE(AVG(r.rating),0) DESC";
    }

    $catQuery .= " LIMIT 6";

    $listings = [];
    $catRes = $conn->query($catQuery);
    if ($catRes) {
        while ($row = $catRes->fetch_assoc()) {
            $img = null;
            $imgRes2 = $conn->query("
                SELECT image_path FROM listing_images
                WHERE listing_id = {$row['id']} AND is_primary = 1 LIMIT 1
            ");
            if ($imgRes2 && $imgRes2->num_rows > 0) {
                $img = $imgRes2->fetch_assoc()['image_path'];
            }

            $item = [
                'id'       => $row['id'],
                'name'     => $row['name'],
                'category' => $row['category_name'],
                'rating'   => round($row['avg_rating'], 1),
                'address'  => $row['address'],
                'image'    => $img,
            ];
            if (isset($row['distance_km'])) {
                $item['distance'] = round($row['distance_km'], 2);
            }
            $listings[] = $item;
        }
    }

    if (!empty($listings)) {
        $category_listings[] = [
            'category_id'   => $catId,
            'category_name' => $catName,
            'listings'      => $listings,
        ];

        // Backward compat: first category (Hospital, id=1) also goes to 'hospitals'
        if ($catId === 1) {
            $hospitals = $listings;
        }
    }
}
$response['category_listings'] = $category_listings;
$response['hospitals'] = $hospitals;

/* ==========================
   NEWS
========================== */
$news = [];
$res = $conn->query("
    SELECT id, title, short_description, image, publish_date
    FROM news
    WHERE status = 1
    ORDER BY publish_date DESC
    LIMIT 6
");
while ($row = $res->fetch_assoc()) {
    $news[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'description' => $row['short_description'],
        'date' => date('M d, Y', strtotime($row['publish_date'])),
        'image' => $row['image']
            // ? BASE_URL . '/healthdial/uploads/news/' . $row['image']
            // : null
    ];
}
$response['news'] = $news;



/* ==========================
   BANNERS (managed from dashboard)
========================== */
$bannerList = [];
$bannerRes = $conn->query("
    SELECT id, title, image_url, link_url
    FROM banners
    WHERE status = 1
    ORDER BY sort_order ASC, id DESC
");
if ($bannerRes) {
    while ($row = $bannerRes->fetch_assoc()) {
        $bannerList[] = [
            'id'        => (int)$row['id'],
            'title'     => $row['title'],
            'image_url' => $row['image_url'],
            'link_url'  => $row['link_url']
        ];
    }
}
$response['banners'] = $bannerList;

/* ==========================
   FINAL RESPONSE
========================== */
sendResponse([
    'success' => true,
    'data' => $response,
    'timestamp' => date('Y-m-d H:i:s')
]);

/* ==========================
   ICON HELPER
========================== */
function getIconType($icon) {
    if (strpos($icon, 'fa-') !== false) return 'fa';
    if (strpos($icon, 'medical') !== false) return 'mc';
    if (strpos($icon, 'pharmacy') !== false) return 'mi';
    return 'fa';
}

?>