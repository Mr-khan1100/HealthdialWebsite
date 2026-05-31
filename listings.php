<?php
$currentPage = 'listings';
$pageTitle = 'Browse Medical Listings | Best Hospitals Near You in Faridabad | Health Dial';
$pageDesc = 'Health dial helps you to find the verified hospitals, clinics, labs & more across 500+ cities in India. Download Health dial app for registration.';
require_once 'includes/icons.php';
require_once 'includes/db.php';
require_once 'includes/header.php';

// Fetch categories from API
$categories = [];
$apiUrl = API_BASE . 'get_categories.php';
$data = fetch_api_data($apiUrl);

if ($data && isset($data['success']) && $data['success'] && !empty($data['data'])) {
    $categories = $data['data'];
}

// Category icon mapping
$catIcons = [
    'Hospital' => 'hospital',
    'Clinic' => 'clinic',
    'Diagnostic Lab' => 'lab',
    'Medical Store' => 'pharmacy',
    'Ambulance' => 'ambulance',
    'Blood Bank' => 'bloodBank',
    'Physiotherapy' => 'physio',
    'Dental Care' => 'dental',
    'Eye Care' => 'eyeCare',
    'Cardiology' => 'cardiology',
];
?>

<!-- ===== HERO WITH SEARCH ===== -->
<section class="listings-hero">
    <div class="hero-bg-shapes">
        <div class="hero-shape hero-shape-1"></div>
        <div class="hero-shape hero-shape-2"></div>
    </div>
    <div class="container">
        <div class="listings-hero-content">
            <span class="section-label"><?= icon('search') ?> Find Medical Help</span>
            <h1>Browse <span class="gradient-text">Medical Listings</span></h1>
            <p class="hero-subtitle">Search verified hospitals, clinics, labs & more across 500+ cities in India.</p>

            <form class="search-hero" id="listingsSearchForm" onsubmit="handleSearch(event)">
                <div class="search-bar">
                    <span class="search-bar-icon"><?= icon('search') ?></span>
                    <input type="text" id="searchInput" placeholder="Search hospitals, clinics, doctors..."
                        autocomplete="off" />
                    <input type="text" id="cityInput" placeholder="City or area" autocomplete="off" />
                    <button type="submit" class="btn btn-primary search-btn">
                        <?= icon('search') ?> Search
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- ===== LOCATION BANNER ===== -->
<div class="location-banner" id="locationBanner" style="display:none;">
    <div class="container">
        <div class="location-banner-inner">
            <span class="location-banner-icon"><?= icon('gps') ?></span>
            <span class="location-banner-text">Enable location to find listings near you</span>
            <button class="btn btn-primary location-banner-btn" onclick="requestLocation()">
                <?= icon('gps') ?> Enable Location
            </button>
            <button class="location-banner-close" onclick="dismissLocationBanner()">&times;</button>
        </div>
    </div>
</div>

<!-- ===== CATEGORIES GRID ===== -->
<section class="section" style="background: var(--bg);">
    <div class="container">
        <div class="section-header">
            <span class="section-label"><?= icon('grid') ?> Categories</span>
            <h2 class="section-title">Choose a <span class="gradient-text">Category</span></h2>
            <p class="section-subtitle">Select a category to browse verified medical listings near you.</p>
        </div>
        <div class="categories-grid categories-grid-listings">
            <?php if (!empty($categories)): ?>
            <?php foreach ($categories as $i => $cat):
                    $iconKey = $catIcons[$cat['name']] ?? 'hospital';
                    ?>
            <a href="looking.php?cat=<?= $cat['id'] ?>&name=<?= urlencode($cat['name']) ?>"
                class="category-card category-card-link reveal delay-<?= ($i % 5) + 1 ?>">
                <div class="category-icon">
                    <?= icon($iconKey) ?>
                </div>
                <div class="category-name"><?= htmlspecialchars($cat['name']) ?></div>
                <span class="category-count"><?= number_format($cat['listing_count']) ?> listings</span>
            </a>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="card" style="text-align:center; padding:48px; grid-column: 1/-1;">
                <p class="card-text">Unable to load categories. Please try again later.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ===== NEAR YOU (shown when location granted) ===== -->
<section class="section" id="nearYouSection" style="background: var(--bg-alt); display:none;">
    <div class="container">
        <div class="section-header">
            <span class="section-label"><?= icon('gps') ?> Near You</span>
            <h2 class="section-title">Listings <span class="gradient-text">Near You</span></h2>
            <p class="section-subtitle" id="nearYouSubtitle">Top-rated medical services in your area.</p>
        </div>
        <div class="listing-grid" id="nearYouGrid">
            <!-- Populated via JS -->
        </div>
        <div style="text-align:center; margin-top:32px;" id="nearYouMore" style="display:none;">
            <a href="looking.php" class="btn btn-secondary" id="nearYouLink">
                <?= icon('arrowRight') ?> View All Nearby
            </a>
        </div>
    </div>
</section>

<!-- ===== CTA ===== -->
<section class="section cta-section" id="download">
    <div class="container">
        <h2>Get the Full Experience on the <span class="gradient-text">App</span></h2>
        <p class="cta-subtitle">GPS navigation, medicine reminders, and instant contact — all in one app.</p>
        <div class="cta-buttons">
            <a href="https://play.google.com/store/apps/details?id=com.healthdial.mobile" target="_blank"><img
                    src="assets/images/google-play.svg" alt="Get it on Google Play" class="store-badge" /></a>
            <a href="https://apps.apple.com/app/healthdial" target="_blank"><img src="assets/images/app-store.svg"
                    alt="Download on App Store" class="store-badge" /></a>
        </div>
    </div>
</section>

<script src="assets/js/listings.js?v=2.4.0"></script>
<?php require_once 'includes/footer.php'; ?>