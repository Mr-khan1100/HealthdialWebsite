<?php
$catName = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : 'All';
$currentPage = 'listings';
$pageTitle = $catName . ' Listings';
$pageDesc = 'Find verified ' . strtolower($catName) . ' near you. Browse medical listings with ratings, contact info and directions on HealthDial.';
require_once 'includes/icons.php';
require_once 'includes/db.php';
require_once 'includes/header.php';

// Fetch categories for filter pills
$categories = [];
$apiUrl = API_BASE . 'get_categories.php';
$catData = fetch_api_data($apiUrl);

if ($catData && isset($catData['success']) && $catData['success'] && !empty($catData['data'])) {
    $categories = $catData['data'];
}

$activeCat = isset($_GET['cat']) ? intval($_GET['cat']) : 0;
$activeCity = isset($_GET['city']) ? htmlspecialchars($_GET['city']) : '';
?>

<!-- ===== SEARCH HEADER ===== -->
<section class="looking-hero">
    <div class="container">
        <div class="looking-search-wrap">
            <a href="listings.php" class="looking-back"><?= icon('arrowRight') ?></a>
            <form class="looking-search" id="lookingSearchForm" onsubmit="searchListings(event)">
                <div class="looking-search-bar">
                    <span class="search-bar-icon"><?= icon('search') ?></span>
                    <input type="text" id="lookingSearchInput" placeholder="Search <?= $catName ?>..." value="" autocomplete="off" />
                    <button type="submit" class="btn btn-primary search-btn-sm"><?= icon('search') ?></button>
                </div>
            </form>
        </div>

        <!-- Category Pills -->
        <div class="category-pills" id="categoryPills">
            <a href="looking.php" class="pill <?= $activeCat === 0 ? 'active' : '' ?>">All</a>
            <?php foreach ($categories as $cat): ?>
                <a href="looking.php?cat=<?= $cat['id'] ?>&name=<?= urlencode($cat['name']) ?><?= $activeCity ? '&city=' . urlencode($activeCity) : '' ?>" 
                   class="pill <?= $activeCat === intval($cat['id']) ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== SORT BAR ===== -->
<div class="sort-bar">
    <div class="container">
        <div class="sort-bar-inner">
            <span class="sort-bar-count" id="resultsCount">Loading...</span>
            <div class="sort-bar-right">
                <select id="sortSelect" onchange="changeSortAndReload()" class="sort-select">
                    <option value="rating">Top Rated</option>
                    <option value="nearest">Nearest</option>
                    <option value="newest">Newest</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- ===== SPONSORED ===== -->
<section id="sponsoredSection" style="display:none;">
    <div class="container" style="padding-top:24px;">
        <span class="section-label" style="margin-bottom:16px;"><?= icon('star') ?> Sponsored</span>
        <div class="listing-grid" id="sponsoredGrid"></div>
    </div>
</section>

<!-- ===== LISTINGS GRID ===== -->
<section class="section" style="padding-top:24px;">
    <div class="container">
        <div class="listing-grid" id="listingsGrid">
            <!-- Skeleton loaders -->
            <div class="listing-card skeleton"><div class="skeleton-image"></div><div class="skeleton-body"><div class="skeleton-line w70"></div><div class="skeleton-line w50"></div><div class="skeleton-line w90"></div></div></div>
            <div class="listing-card skeleton"><div class="skeleton-image"></div><div class="skeleton-body"><div class="skeleton-line w70"></div><div class="skeleton-line w50"></div><div class="skeleton-line w90"></div></div></div>
            <div class="listing-card skeleton"><div class="skeleton-image"></div><div class="skeleton-body"><div class="skeleton-line w70"></div><div class="skeleton-line w50"></div><div class="skeleton-line w90"></div></div></div>
        </div>

        <!-- Empty state -->
        <div class="empty-state" id="emptyState" style="display:none;">
            <div class="card" style="text-align:center; padding:64px 32px; max-width:500px; margin:0 auto;">
                <div class="card-icon blue" style="margin:0 auto 20px;"><?= icon('search') ?></div>
                <h3 class="card-title">No listings found</h3>
                <p class="card-text">Try a different city or category. You can also search by name.</p>
                <a href="listings.php" class="btn btn-secondary" style="margin-top:20px;">
                    <?= icon('arrowRight') ?> Browse Categories
                </a>
            </div>
        </div>

        <!-- Load More -->
        <div style="text-align:center; margin-top:40px;" id="loadMoreWrap" style="display:none;">
            <button class="btn btn-secondary" id="loadMoreBtn" onclick="loadMore()">
                Load More Listings
            </button>
        </div>
    </div>
</section>

<!-- ===== CTA ===== -->
<section class="section cta-section" id="download">
    <div class="container">
        <h2>Better Experience on the <span class="gradient-text">App</span></h2>
        <p class="cta-subtitle">GPS navigation, one-tap calling and medicine reminders — download now.</p>
        <div class="cta-buttons">
            <a href="https://play.google.com/store/apps/details?id=com.healthdial.mobile" target="_blank"><img src="assets/images/google-play.svg" alt="Get it on Google Play" class="store-badge" /></a>
            <a href="https://apps.apple.com/app/healthdial" target="_blank"><img src="assets/images/app-store.svg" alt="Download on App Store" class="store-badge" /></a>
        </div>
    </div>
</section>

<script>
    // Pass PHP values to JS
    window.LOOKING_CONFIG = {
        activeCat: <?= $activeCat ?>,
        activeCity: '<?= addslashes($activeCity) ?>',
        apiBase: '<?= API_BASE ?>'
    };
</script>
<script src="assets/js/listings.js"></script>
<?php require_once 'includes/footer.php'; ?>
