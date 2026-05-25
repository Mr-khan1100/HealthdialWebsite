<?php
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (preg_match('#^/([A-Za-z0-9-]+)/([A-Za-z0-9-]+)/?$#', $requestPath, $matches)) {
    $_GET['city_slug'] = $matches[1];
    $_GET['slug'] = $matches[2];
    // Pre-extract the trailing numeric ID from the slug so listing-detail.php
    // can reach the API fallback without needing a DB slug-column match.
    if (!isset($_GET['id']) && preg_match('/-(\d+)$/', $matches[2], $idMatch)) {
        $_GET['id'] = $idMatch[1];
    }
    require __DIR__ . '/listing-detail.php';
    exit;
}

$currentPage = 'home';
$pageTitle = 'Digital Healthcare Management Portal | Medicine Store Near Me | Health Dial';
$pageDesc = 'Health DIAL is your One Stop Source for finding , Doctors, Pharmacies, medical labs, Hospitals, Medical Diagnostic Tests and clinics, all at your fingertips.';
require_once 'includes/icons.php';
require_once 'includes/db.php';
require_once 'includes/header.php';

// Fetch categories from API
$categories = [];
$CategoriesApiUrl = API_BASE . 'get_categories.php';
$CategoriesData = fetch_api_data($CategoriesApiUrl);

if ($CategoriesData && isset($CategoriesData['success']) && $CategoriesData['success'] && !empty($CategoriesData['data'])) {
    $categories = $CategoriesData['data'];
}

$counts = [];
$CountsApiUrl = API_BASE . 'get_counts.php';
$CountsData = fetch_api_data($CountsApiUrl);
if ($CountsData && isset($CountsData['success']) && $CountsData['success'] && !empty($CountsData['data'])) {
    $counts = $CountsData['data'][0] ?? [];
}



// Icon mapping for categories
$catIconMap = [
    'Hospital' => 'fa-hospital',
    'Clinic' => 'fa-stethoscope',
    'Labs' => 'fa-flask',
    'Medical store' => 'fa-pills',
    'Ambulance' => 'fa-truck-medical',
    'Blood bank' => 'fa-droplet',
];
$catColorMap = [
    'Hospital' => '#2563eb',
    'Clinic' => '#059669',
    'Labs' => '#7c3aed',
    'Medical store' => '#dc2626',
    'Ambulance' => '#ea580c',
    'Blood bank' => '#be123c',
];
?>

<!-- ===== SEARCH HERO ===== -->
<section class="home-hero">
    <div class="home-hero-bg"></div>
    <div class="container">
        <div class="home-hero-content">
            <h1 class="home-hero-title fade-in">Find Medical Help <span class="gradient-text">Near You</span></h1>
            <p class="home-hero-sub fade-in delay-1">Search 3,43,000+ verified hospitals, clinics, labs & pharmacies
                across India</p>
            <form class="home-search-form fade-in delay-2" id="homeSearchForm" onsubmit="searchListings(event)">
                <div class="home-search-bar">
                    <i class="fas fa-search home-search-icon"></i>
                    <input type="text" id="lookingSearchInput" placeholder="Search hospitals, clinics, doctors..."
                        autocomplete="off" />
                    <button type="submit" class="home-search-btn"><i class="fas fa-search"></i><span
                            class="search-btn-text"> Search</span></button>
                </div>
            </form>
            <div class="home-hero-stats fade-in delay-3">
                <div class="hero-stat">
                    <strong><?= number_format($counts['total_listings'] ?? 0) ?></strong><span>Listings</span>
                </div>
                <div class="hero-stat-divider"></div>
                <div class="hero-stat">
                    <strong><?= number_format($counts['total_cities'] ?? 0) ?></strong><span>Cities</span>
                </div>
                <div class="hero-stat-divider"></div>
                <div class="hero-stat"><strong>
                        <?= number_format($counts['total_categories'] ?? 0) ?>
                    </strong><span>Categories</span></div>
            </div>
        </div>
    </div>
</section>

<!-- ===== CATEGORIES ===== -->
<section class="home-categories">
    <div class="container">
        <h2 class="section-heading"><i class="fas fa-th-large"></i> Categories</h2>
        <div class="home-cat-grid">
            <?php if (!empty($categories)): ?>
            <?php foreach ($categories as $cat):
                    $faIcon = $catIconMap[$cat['name']] ?? 'fa-hospital';
                    $color = $catColorMap[$cat['name']] ?? '#2563eb';
                    ?>
            <a href="javascript:void(0)" class="home-cat-card reveal"
                onclick="setCategory(<?= $cat['id'] ?>, '<?= addslashes($cat['name']) ?>')">
                <div class="home-cat-icon" style="background: <?= $color ?>15; color: <?= $color ?>;">
                    <i class="fas <?= $faIcon ?>"></i>
                </div>
                <div class="home-cat-name"><?= htmlspecialchars($cat['name']) ?></div>
                <div class="home-cat-count"><?= number_format($cat['listing_count']) ?></div>
            </a>
            <?php endforeach; ?>
            <?php else: ?>
            <a href="looking.php?cat=1&name=Hospital" class="home-cat-card reveal">
                <div class="home-cat-icon" style="background:#2563eb15;color:#2563eb;"><i class="fas fa-hospital"></i>
                </div>
                <div class="home-cat-name">Hospital</div>
            </a>
            <a href="looking.php?cat=4&name=Clinic" class="home-cat-card reveal">
                <div class="home-cat-icon" style="background:#05966915;color:#059669;"><i
                        class="fas fa-stethoscope"></i></div>
                <div class="home-cat-name">Clinic</div>
            </a>
            <a href="looking.php?cat=3&name=Labs" class="home-cat-card reveal">
                <div class="home-cat-icon" style="background:#7c3aed15;color:#7c3aed;"><i class="fas fa-flask"></i>
                </div>
                <div class="home-cat-name">Labs</div>
            </a>
            <a href="looking.php?cat=2&name=Medical+store" class="home-cat-card reveal">
                <div class="home-cat-icon" style="background:#dc262615;color:#dc2626;"><i class="fas fa-pills"></i>
                </div>
                <div class="home-cat-name">Medical Store</div>
            </a>
            <a href="looking.php?cat=10&name=Ambulance" class="home-cat-card reveal">
                <div class="home-cat-icon" style="background:#ea580c15;color:#ea580c;"><i
                        class="fas fa-truck-medical"></i></div>
                <div class="home-cat-name">Ambulance</div>
            </a>
            <a href="looking.php?cat=9&name=Blood+bank" class="home-cat-card reveal">
                <div class="home-cat-icon" style="background:#be123c15;color:#be123c;"><i class="fas fa-droplet"></i>
                </div>
                <div class="home-cat-name">Blood Bank</div>
            </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ===== ADD LISTING CTA ===== -->
<section class="home-add-listing-banner">
    <div class="container">
        <div class="home-add-banner-inner">
            <div class="home-add-banner-left">
                <div class="home-add-banner-icon"><i class="fas fa-hospital-user"></i></div>
                <div>
                    <strong>Are you a Healthcare Provider?</strong>
                    <span>List your hospital, clinic, pharmacy or lab for free — reach thousands of patients</span>
                </div>
            </div>
            <a href="add-listing.php" class="home-add-banner-btn">
                <i class="fas fa-plus"></i> Add Your Listing
            </a>
        </div>
    </div>
</section>

<!-- ===== FILTER BAR ===== -->
<section class="home-filter-bar">
    <div class="container">
        <div class="filter-bar-inner">
            <div class="filter-pills" id="categoryPills">
                <button class="filter-pill active" onclick="setCategory(0, 'All')" data-cat="0">All</button>
                <?php foreach ($categories as $cat): ?>
                <button class="filter-pill" onclick="setCategory(<?= $cat['id'] ?>, '<?= addslashes($cat['name']) ?>')"
                    data-cat="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></button>
                <?php endforeach; ?>
            </div>
            <div class="filter-right">
                <span class="results-count" id="resultsCount">Loading...</span>
                <select id="sortSelect" onchange="changeSortAndReload()" class="filter-sort">
                    <option value="rating">Top Rated</option>
                    <option value="nearest">Nearest</option>
                    <option value="newest">Newest</option>
                </select>
            </div>
        </div>
    </div>
</section>



<!-- ===== LISTINGS GRID ===== -->
<section class="home-listings">
    <div class="container">
        <div class="listing-grid" id="listingsGrid">
            <!-- Skeleton loaders -->
            <?php for ($i = 0; $i < 6; $i++): ?>
            <div class="listing-card skeleton">
                <div class="skeleton-image"></div>
                <div class="skeleton-body">
                    <div class="skeleton-line w70"></div>
                    <div class="skeleton-line w50"></div>
                    <div class="skeleton-line w90"></div>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <!-- Empty state -->
        <div class="empty-state" id="emptyState" style="display:none;">
            <div class="empty-state-inner">
                <i class="fas fa-search-minus"></i>
                <h3>No listings found</h3>
                <p>Try a different city or category.</p>
            </div>
        </div>

        <!-- Load More -->
        <div class="load-more-wrap" id="loadMoreWrap" style="display:none;">
            <button class="btn btn-secondary load-more-btn" id="loadMoreBtn" onclick="loadMore()">
                <i class="fas fa-plus"></i> Load More Listings
            </button>
        </div>
    </div>
</section>

<!-- ===== DOWNLOAD CTA ===== -->
<section class="home-cta">
    <div class="container">
        <div class="home-cta-inner reveal">
            <div class="home-cta-text">
                <h2>Get the <span class="gradient-text">HealthDial App</span></h2>
                <p>GPS navigation, 1-tap calling, medicine reminders and more. Your complete health companion.</p>
                <div class="home-cta-badges">
                    <a href="https://play.google.com/store/apps/details?id=com.healthdial.mobile" target="_blank">
                        <img src="assets/images/google-play.svg" alt="Google Play" height="48" />
                    </a>
                    <a href="https://apps.apple.com/app/healthdial" target="_blank">
                        <img src="assets/images/app-store.svg" alt="App Store" height="48" />
                    </a>
                </div>
            </div>
            <div class="home-cta-features">
                <div class="cta-feature"><i class="fas fa-location-crosshairs"></i><span>GPS Navigation</span></div>
                <div class="cta-feature"><i class="fas fa-phone"></i><span>1-Tap Calling</span></div>
                <div class="cta-feature"><i class="fas fa-bell"></i><span>Med Reminders</span></div>
                <div class="cta-feature"><i class="fas fa-clock"></i><span>24×7 Emergency</span></div>
            </div>
        </div>
    </div>
</section>

<script>
window.LOOKING_CONFIG = {
    activeCat: 0,
    activeCity: '',
    apiBase: '<?= API_BASE ?>'
};

function setCategory(id, name) {
    window.LOOKING_CONFIG.activeCat = id;
    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
    const activeBtn = document.querySelector(`.filter-pill[data-cat="${id}"]`);
    if (activeBtn) activeBtn.classList.add('active');
    currentPage = 1;
    loadListings();
}
</script>
<script src="assets/js/listings.js?v=1.0"></script>

<?php require_once 'includes/footer.php'; ?>