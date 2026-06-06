<?php
require_once __DIR__ . '/db.php';

function hd_fetch_listing_detail_from_db($conn, $listingId)
{
    $listingId = intval($listingId);
    if (!$conn || $listingId <= 0) {
        return null;
    }

    $slugSelect = function_exists('hd_db_has_column') && hd_db_has_column($conn, 'listings', 'slug') ? 'l.slug' : "NULL AS slug";
    $citySlugSelect = function_exists('hd_db_has_column') && hd_db_has_column($conn, 'listings', 'city_slug') ? 'l.city_slug' : "NULL AS city_slug";
    $categorySlugSelect = function_exists('hd_db_has_column') && hd_db_has_column($conn, 'listings', 'category_slug') ? 'l.category_slug' : "NULL AS category_slug";

    $stmt = $conn->prepare("
        SELECT
            l.*,
            c.name AS category_name,
            c.icon AS category_icon,
            $slugSelect,
            $citySlugSelect,
            $categorySlugSelect,
            COALESCE(AVG(CASE WHEN r.status = 'approved' THEN r.rating END), 0) AS avg_rating,
            COUNT(CASE WHEN r.status = 'approved' THEN r.id END) AS review_count
        FROM listings l
        LEFT JOIN categories c ON c.id = l.category_id
        LEFT JOIN reviews r ON r.listing_id = l.id
        WHERE l.id = ? AND l.status = 'approved'
        GROUP BY l.id, c.id
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $listingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    $listing = [
        'id' => $row['id'],
        'category_id' => $row['category_id'],
        'name' => $row['name'],
        'category_name' => $row['category_name'],
        'description' => $row['description'],
        'address' => $row['address'],
        'city' => $row['city'],
        'slug' => $row['slug'] ?? null,
        'city_slug' => $row['city_slug'] ?? null,
        'category_slug' => $row['category_slug'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
        'avg_rating' => round(floatval($row['avg_rating']), 1),
        'review_count' => intval($row['review_count']),
        'mobile' => $row['mobile'],
        'whatsapp' => $row['whatsapp'],
        'email' => $row['email'],
        'open_time' => $row['open_time'],
        'close_time' => $row['close_time'],
        'is_24x7' => intval($row['is_24x7']),
        'latitude' => $row['latitude'],
        'longitude' => $row['longitude'],
    ];

    $images = [];
    $imageStmt = $conn->prepare("
        SELECT image_path, is_primary, is_external_url
        FROM listing_images
        WHERE listing_id = ?
        ORDER BY is_primary DESC, id ASC
    ");

    if ($imageStmt) {
        $imageStmt->bind_param('i', $listingId);
        $imageStmt->execute();
        $imageResult = $imageStmt->get_result();
        while ($image = $imageResult->fetch_assoc()) {
            $imagePath = trim($image['image_path'] ?? '');
            if ($imagePath === '') {
                continue;
            }

            $imageUrl = (strpos($imagePath, 'http') === 0 || intval($image['is_external_url']) === 1)
                ? $imagePath
                : LISTING_IMAGE_BASE . $imagePath;

            $images[] = [
                'image_path' => str_replace(['/healthdial/', '/healthDial/'], '/HealthDial/', $imageUrl),
                'is_external_url' => true,
            ];
        }
        $imageStmt->close();
    }

    $reviews = [];
    $reviewStmt = $conn->prepare("
        SELECT r.rating, r.review, r.created_at, COALESCE(u.name, r.guest_name, 'Anonymous') AS reviewer_name
        FROM reviews r
        LEFT JOIN users u ON u.id = r.user_id AND r.user_id > 0
        WHERE r.listing_id = ? AND r.status = 'approved'
        ORDER BY r.created_at DESC
        LIMIT 20
    ");

    if ($reviewStmt) {
        $reviewStmt->bind_param('i', $listingId);
        $reviewStmt->execute();
        $reviewResult = $reviewStmt->get_result();
        while ($review = $reviewResult->fetch_assoc()) {
            $reviews[] = [
                'user_name' => $review['reviewer_name'] ?: 'Anonymous',
                'rating' => $review['rating'],
                'comment' => $review['review'],
                'created_at' => $review['created_at'],
            ];
        }
        $reviewStmt->close();
    }

    return [
        'listing' => $listing,
        'images' => $images,
        'reviews' => $reviews,
    ];
}

function hd_fetch_similar_listings($conn, $listing, $limit = 6)
{
    if (!$conn || empty($listing['id'])) {
        return [];
    }

    $limit = max(1, min(12, intval($limit)));
    $listingId = intval($listing['id']);
    $categoryId = intval($listing['category_id'] ?? 0);
    $cityLabel = function_exists('hd_city_label') ? hd_city_label($listing['city'] ?? '') : trim(explode(',', $listing['city'] ?? '')[0]);
    $cityLike = '%' . $cityLabel . '%';
    $slugSelect = function_exists('hd_db_has_column') && hd_db_has_column($conn, 'listings', 'slug') ? 'l.slug' : "NULL AS slug";

    $distanceSelect = '';
    $distanceOrder = '';

    if (!empty($listing['latitude']) && !empty($listing['longitude'])) {
        $distanceSelect = ",
            (6371 * acos(
                cos(radians(?)) *
                cos(radians(l.latitude)) *
                cos(radians(l.longitude) - radians(?)) +
                sin(radians(?)) *
                sin(radians(l.latitude))
            )) AS distance_km";
        $distanceOrder = ', distance_km ASC';
    }

    $categoryCondition = '';
    if ($categoryId > 0) {
        $categoryCondition = 'AND l.category_id = ?';
    }

    $sql = "
        SELECT
            l.id,
            l.name,
            l.address,
            l.city,
            l.mobile,
            l.latitude,
            l.longitude,
            c.name AS category_name,
            $slugSelect,
            li.image_path AS image,
            li.is_external_url,
            COALESCE(AVG(CASE WHEN r.status = 'approved' THEN r.rating END), 0) AS avg_rating,
            COUNT(CASE WHEN r.status = 'approved' THEN r.id END) AS review_count,
            CASE WHEN l.city LIKE ? OR l.address LIKE ? THEN 1 ELSE 0 END AS same_city
            $distanceSelect
        FROM listings l
        LEFT JOIN categories c ON c.id = l.category_id
        LEFT JOIN listing_images li ON li.listing_id = l.id AND li.is_primary = 1
        LEFT JOIN reviews r ON r.listing_id = l.id
        WHERE l.status = 'approved'
          AND l.id <> ?
          $categoryCondition
        GROUP BY l.id
        HAVING same_city = 1
        ORDER BY same_city DESC, avg_rating DESC, review_count DESC $distanceOrder, l.updated_at DESC
        LIMIT ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    if (!empty($listing['latitude']) && !empty($listing['longitude'])) {
        $orderedParams = [
            $cityLike,
            $cityLike,
            floatval($listing['latitude']),
            floatval($listing['longitude']),
            floatval($listing['latitude']),
            $listingId,
        ];
        $orderedTypes = 'ssdddi';
        if ($categoryId > 0) {
            $orderedParams[] = $categoryId;
            $orderedTypes .= 'i';
        }
        $orderedParams[] = $limit;
        $orderedTypes .= 'i';
    } else {
        $orderedParams = [$cityLike, $cityLike, $listingId];
        $orderedTypes = 'ssi';
        if ($categoryId > 0) {
            $orderedParams[] = $categoryId;
            $orderedTypes .= 'i';
        }
        $orderedParams[] = $limit;
        $orderedTypes .= 'i';
    }

    $stmt->bind_param($orderedTypes, ...$orderedParams);
    $stmt->execute();
    $result = $stmt->get_result();
    $similar = [];

    while ($row = $result->fetch_assoc()) {
        $imagePath = trim($row['image'] ?? '');
        $imageUrl = null;
        if ($imagePath !== '') {
            $imageUrl = (strpos($imagePath, 'http') === 0 || intval($row['is_external_url']) === 1)
                ? $imagePath
                : LISTING_IMAGE_BASE . $imagePath;
        }

        $row['category'] = $row['category_name'];
        $row['avg_rating'] = round(floatval($row['avg_rating']), 1);
        $row['review_count'] = intval($row['review_count']);
        $row['image'] = $imageUrl;
        $row['distance'] = isset($row['distance_km']) ? round(floatval($row['distance_km']), 1) : null;
        $similar[] = $row;
    }

    $stmt->close();

    return $similar;
}
?>
