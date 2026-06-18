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
require_once 'includes/website_banner.php';

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

<!-- ===== LANDING HERO ===== -->
<section class="landing-hero">
    <!-- Animated depth blobs -->
    <div class="hero-blob blob-1"></div>
    <div class="hero-blob blob-2"></div>
    <div class="hero-blob blob-3"></div>

    <div class="container">
        <div class="hero-inner">
            <!-- Trust badge -->
            <!-- <div class="hero-badge reveal">
                <i class="fas fa-shield-heart"></i>
                Trusted by Millions of Patients Across India
            </div> -->

            <!-- Headline with rotating word -->
            <h1 class="hero-title reveal">
                Find
                <span class="hero-rotating-wrapper">
                    <span class="hero-words">
                        <span>Hospitals</span>
                        <span>Clinics</span>
                        <span>Pharmacies</span>
                        <span>Labs</span>
                    </span>
                </span>
                <br>Near You, <span class="gradient-text">Instantly.</span>
            </h1>

            <!-- Subtitle -->
            <p class="hero-sub reveal">
                India's largest healthcare directory —
                <strong><?= number_format($counts['total_listings'] ?? 343000) ?>+</strong> verified listings
                across <strong><?= number_format($counts['total_cities'] ?? 500) ?>+</strong> cities.
            </p>

            <!-- Benefits row -->
            <div class="hero-benefits reveal">
                <span><i class="fas fa-check-circle"></i> Free to List</span>
                <span><i class="fas fa-check-circle"></i> Instant Visibility</span>
                <span><i class="fas fa-check-circle"></i> Verified Badge</span>
                <span><i class="fas fa-check-circle"></i> 50K+ Daily Searches</span>
            </div>

            <!-- CTAs -->
            <div class="hero-ctas reveal">
                <a href="add-listing.php" class="hero-cta-primary">
                    <i class="fas fa-plus-circle"></i>
                    List Your Business — Free
                    <span class="hero-cta-arrow"><i class="fas fa-arrow-right"></i></span>
                </a>
                <a href="looking.php" class="hero-cta-secondary">
                    <i class="fas fa-search"></i>
                    Find Healthcare
                </a>
            </div>

            <!-- Animated stats -->
            <div class="hero-stats reveal">
                <div class="hero-stat-item">
                    <strong data-counter="<?= intval($counts['total_listings'] ?? 343000) ?>" data-suffix="+">0</strong>
                    <span>Listings</span>
                </div>
                <div class="hero-stat-divider"></div>
                <div class="hero-stat-item">
                    <strong data-counter="<?= intval($counts['total_cities'] ?? 500) ?>" data-suffix="+">0</strong>
                    <span>Cities</span>
                </div>
                <div class="hero-stat-divider"></div>
                <div class="hero-stat-item">
                    <strong data-counter="<?= intval($counts['total_categories'] ?? 6) ?>">0</strong>
                    <span>Categories</span>
                </div>
            </div>
        </div>

        <!-- Search bar (merged into hero) -->
        <div class="hero-search-wrap">
            <form class="compact-search-form" id="homeSearchForm" onsubmit="searchListings(event)">
                <div class="compact-search-inner">
                    <i class="fas fa-search compact-search-icon"></i>
                    <input type="text" id="lookingSearchInput" placeholder="Search hospitals, clinics, pharmacies..."
                        autocomplete="off" />
                    <button type="submit" class="compact-search-btn">
                        <i class="fas fa-search"></i><span> Search</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Categories (merged into hero) -->
        <div class="hero-cats-wrap">
            <div class="home-cat-grid">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat):
                        $faIcon = $catIconMap[$cat['name']] ?? 'fa-hospital';
                        $color = $catColorMap[$cat['name']] ?? '#2563eb';
                        ?>
                        <a href="looking.php?cat=<?= $cat['id'] ?>&name=<?= urlencode($cat['name']) ?>"
                            class="home-cat-card reveal">
                            <div class="home-cat-icon" style="background: <?= $color ?>15; color: <?= $color ?>;">
                                <i class="fas <?= $faIcon ?>"></i>
                            </div>
                            <div class="home-cat-name"><?= htmlspecialchars($cat['name']) ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <a href="looking.php?cat=1&name=Hospital" class="home-cat-card reveal">
                        <div class="home-cat-icon" style="background:#2563eb15;color:#2563eb;"><i
                                class="fas fa-hospital"></i></div>
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
                        <div class="home-cat-icon" style="background:#be123c15;color:#be123c;"><i
                                class="fas fa-droplet"></i></div>
                        <div class="home-cat-name">Blood Bank</div>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scroll indicator -->
    <div class="hero-scroll-indicator">
        <div class="hero-scroll-dot"></div>
    </div>
</section>

<?php render_website_banner('home', 'top'); ?>

<!-- ===== TOP FACILITIES ===== -->
<section class="top-facilities-section section">
    <div class="container">

        <?php
        $facilityRows = [
            ['id' => 'hospitals', 'catId' => 1, 'label' => 'Top Hospitals', 'icon' => 'fa-hospital', 'color' => '#2563eb', 'bg' => '#2563eb15'],
            ['id' => 'clinics', 'catId' => 4, 'label' => 'Top Clinics', 'icon' => 'fa-stethoscope', 'color' => '#059669', 'bg' => '#05966915'],
            ['id' => 'labs', 'catId' => 3, 'label' => 'Top Labs', 'icon' => 'fa-flask', 'color' => '#7c3aed', 'bg' => '#7c3aed15'],
            ['id' => 'medical-stores', 'catId' => 2, 'label' => 'Top Medical Stores', 'icon' => 'fa-pills', 'color' => '#dc2626', 'bg' => '#dc262615'],
        ];
        foreach ($facilityRows as $row):
            ?>
            <div class="facility-row" data-cat-id="<?= $row['catId'] ?>">
                <div class="facility-row-header">
                    <div class="facility-row-title">
                        <span class="facility-icon" style="background:<?= $row['bg'] ?>;color:<?= $row['color'] ?>;"><i
                                class="fas <?= $row['icon'] ?>"></i></span>
                        <div>
                            <h2><?= $row['label'] ?></h2>
                            <span class="facility-row-sub" id="facSub-<?= $row['id'] ?>">Top rated globally</span>
                        </div>
                    </div>
                    <a href="looking.php?cat=<?= $row['catId'] ?>&name=<?= urlencode(str_replace('Top ', '', $row['label'])) ?>"
                        class="facility-view-all">
                        View All <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <div class="facility-scroll-wrap">
                    <div class="facility-scroll-track" id="facTrack-<?= $row['id'] ?>">
                        <?php for ($s = 0; $s < 5; $s++): ?>
                            <div class="listing-card skeleton fac-skeleton">
                                <div class="skeleton-image"></div>
                                <div class="skeleton-body">
                                    <div class="skeleton-line w70"></div>
                                    <div class="skeleton-line w50"></div>
                                    <div class="skeleton-line w90"></div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    </div>
</section>

<script>
    /* Category IDs for facility rows */
    window.HD_FACILITY_ROWS = [{
        id: 'hospitals',
        catId: 1,
        label: 'Hospitals'
    },
    {
        id: 'clinics',
        catId: 4,
        label: 'Clinics'
    },
    {
        id: 'labs',
        catId: 3,
        label: 'Labs'
    },
    {
        id: 'medical-stores',
        catId: 2,
        label: 'Medical Stores'
    },
    ];
</script>

<?php render_website_banner('home', 'bottom'); ?>

<!-- ===== DOWNLOAD CTA ===== -->
<section class="home-cta">
    <div class="container">
        <div class="home-cta-inner reveal">
            <div class="home-cta-text">
                <h2>Get the <span class="gradient-text">HealthDial App</span></h2>
                <p>GPS navigation, 1-tap calling, medicine reminders and more. Your complete health partner.</p>
                <div class="home-cta-badges">
                    <a href="https://play.google.com/store/apps/details?id=com.healthdial.mobile" target="_blank">
                        <img src="assets/images/google-play.svg" alt="Google Play" class="store-badge" />
                    </a>
                    <a href="https://apps.apple.com/app/healthdial" target="_blank">
                        <img src="assets/images/app-store.svg" alt="App Store" class="store-badge" />
                    </a>
                </div>
            </div>
            <div class="home-cta-features">
                <div class="cta-feature"><i class="fas fa-location-crosshairs"></i><span>GPS Navigation</span></div>
                <div class="cta-feature"><i class="fas fa-phone"></i><span>1-Tap Calling</span></div>
                <div class="cta-feature"><i class="fas fa-bell"></i><span>Med Reminders</span></div>
                <div class="cta-feature"><i class="fas fa-clock"></i><span>24×7 Emergency</span></div>
                <div class="cta-feature"><i class="fas fa-folder-open"></i><span>Save Digital Record</span></div>
                <div class="cta-feature"><i class="fas fa-newspaper"></i><span>Check Health News</span></div>
            </div>
        </div>
    </div>
</section>

<!-- ===== PROMOTE INTERSTITIAL ===== -->
<div id="promo-interstitial" class="promo-overlay" aria-modal="true" role="dialog" aria-label="Promote your business">
    <div class="promo-card">
        <!-- Close -->
        <button class="promo-close" onclick="closePromoInterstitial()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>

        <!-- Visual header -->
        <div class="promo-visual">
            <div class="promo-blob p-blob1"></div>
            <div class="promo-blob p-blob2"></div>
            <div class="promo-icon-grid">
                <div class="promo-ico promo-ico--blue"><i class="fas fa-hospital"></i></div>
                <div class="promo-ico promo-ico--green"><i class="fas fa-stethoscope"></i></div>
                <div class="promo-ico promo-ico--purple"><i class="fas fa-flask"></i></div>
                <div class="promo-ico promo-ico--red"><i class="fas fa-pills"></i></div>
                <div class="promo-ico promo-ico--center"><i class="fas fa-chart-line"></i></div>
                <div class="promo-ico promo-ico--orange"><i class="fas fa-truck-medical"></i></div>
                <div class="promo-ico promo-ico--pink"><i class="fas fa-droplet"></i></div>
                <div class="promo-ico promo-ico--teal"><i class="fas fa-user-doctor"></i></div>
                <div class="promo-ico promo-ico--yellow"><i class="fas fa-star"></i></div>
            </div>
            <div class="promo-badge-tag">
                <i class="fas fa-bolt"></i> Premium Visibility
            </div>
        </div>

        <!-- Body -->
        <div class="promo-body">
            <h2 class="promo-title">Grow Your Practice with <span class="gradient-text">HealthDial</span></h2>
            <p class="promo-sub">Put your hospital, clinic, or pharmacy in front of thousands of patients searching for
                care nearby — every single day.</p>

            <!-- Stats row -->
            <div class="promo-stats">
                <div class="promo-stat">
                    <strong>50K+</strong>
                    <span>Daily Searches</span>
                </div>
                <div class="promo-stat-divider"></div>
                <div class="promo-stat">
                    <strong>343K+</strong>
                    <span>Verified Listings</span>
                </div>
                <div class="promo-stat-divider"></div>
                <div class="promo-stat">
                    <strong>500+</strong>
                    <span>Cities Covered</span>
                </div>
            </div>

            <!-- Benefits -->
            <div class="promo-benefits">
                <div class="promo-benefit"><i class="fas fa-arrow-up"></i> Top of search results</div>
                <div class="promo-benefit"><i class="fas fa-check-circle"></i> Verified badge</div>
                <div class="promo-benefit"><i class="fas fa-users"></i> More patient reach</div>
                <div class="promo-benefit"><i class="fas fa-chart-bar"></i> Analytics dashboard</div>
            </div>

            <!-- CTAs -->
            <div class="promo-ctas">
                <a href="promote.php" class="promo-btn-primary" onclick="closePromoInterstitial()">
                    <i class="fas fa-rocket"></i> Promote My Business
                </a>
                <button class="promo-btn-skip" onclick="closePromoInterstitial()">Maybe Later</button>
            </div>
        </div>
    </div>
</div>

<style>
    /* ===== HOMEPAGE BANNER ===== */
    .hp-banner-wrap {
        max-width: 1200px;
        margin: 0 auto 40px;
        padding: 0 20px;
    }

    .hp-banner-wrap a {
        display: block;
    }

    .hp-banner-img {
        width: 100%;
        height: 360px;
        object-fit: fill;
        border-radius: 16px;
        display: block;
        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.10);
    }

    .hp-footer-banner-wrap {
        max-width: 1200px;
        margin: 0 auto 40px;
        padding: 0 20px;
    }

    .hp-footer-banner-img {
        width: 100%;
        height: 360px;
        object-fit: fill;
        border-radius: 16px;
        display: block;
        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.10);
    }

    @media (max-width: 768px) {
        .hp-banner-wrap {
            padding: 0 12px;
            margin-bottom: 28px;
        }

        .hp-banner-img {
            height: 110px;
            object-fit: cover;
            border-radius: 12px;
        }

        .hp-footer-banner-img {
            height: 110px;
            border-radius: 12px;
        }
    }

    /* ===== PROMOTE INTERSTITIAL ===== */
    .promo-overlay {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.72);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        padding: 16px;
        animation: promoFadeIn 0.35s ease both;
    }

    .promo-overlay.hiding {
        animation: promoFadeOut 0.28s ease both;
    }

    @keyframes promoFadeIn {
        from {
            opacity: 0
        }

        to {
            opacity: 1
        }
    }

    @keyframes promoFadeOut {
        from {
            opacity: 1
        }

        to {
            opacity: 0
        }
    }

    .promo-card {
        position: relative;
        width: 100%;
        max-width: 540px;
        border-radius: 24px;
        overflow: hidden;
        background: var(--glass, rgba(8, 16, 40, 0.96));
        border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.09));
        backdrop-filter: blur(28px);
        -webkit-backdrop-filter: blur(28px);
        box-shadow: 0 32px 80px rgba(0, 0, 0, 0.55);
        animation: promoSlideUp 0.38s cubic-bezier(.22, 1, .36, 1) both;
    }

    [data-theme="light"] .promo-card {
        background: rgba(255, 255, 255, 0.97);
    }

    @keyframes promoSlideUp {
        from {
            transform: translateY(40px);
            opacity: 0
        }

        to {
            transform: translateY(0);
            opacity: 1
        }
    }

    /* Close button */
    .promo-close {
        position: absolute;
        top: 14px;
        right: 14px;
        z-index: 10;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.12));
        background: rgba(255, 255, 255, 0.08);
        color: var(--text-muted, #94a3b8);
        font-size: 0.85rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .promo-close:hover {
        background: rgba(239, 68, 68, 0.18);
        color: #f87171;
        border-color: rgba(239, 68, 68, 0.35);
    }

    /* Visual header */
    .promo-visual {
        position: relative;
        height: 180px;
        overflow: hidden;
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.18) 0%, rgba(16, 185, 129, 0.12) 50%, rgba(139, 92, 246, 0.15) 100%);
        border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.09));
    }

    .promo-blob {
        position: absolute;
        border-radius: 50%;
        filter: blur(50px);
        opacity: 0.45;
    }

    .p-blob1 {
        width: 220px;
        height: 220px;
        background: #2563eb;
        top: -60px;
        left: -40px;
        animation: blobFloat 7s ease-in-out infinite;
    }

    .p-blob2 {
        width: 180px;
        height: 180px;
        background: #10b981;
        bottom: -50px;
        right: -20px;
        animation: blobFloat 9s ease-in-out infinite reverse;
    }

    @keyframes blobFloat {

        0%,
        100% {
            transform: translate(0, 0) scale(1)
        }

        50% {
            transform: translate(14px, -12px) scale(1.08)
        }
    }

    .promo-icon-grid {
        position: absolute;
        inset: 0;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        grid-template-rows: repeat(3, 1fr);
        gap: 0;
        padding: 24px;
    }

    .promo-ico {
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        border-radius: 14px;
        width: 46px;
        height: 46px;
        margin: auto;
        transition: transform 0.3s;
        animation: icoFloat 4s ease-in-out infinite;
    }

    .promo-ico:nth-child(2) {
        animation-delay: .4s
    }

    .promo-ico:nth-child(3) {
        animation-delay: .8s
    }

    .promo-ico:nth-child(4) {
        animation-delay: .2s
    }

    .promo-ico:nth-child(5) {
        animation-delay: .6s
    }

    .promo-ico:nth-child(6) {
        animation-delay: 1s
    }

    .promo-ico:nth-child(7) {
        animation-delay: .3s
    }

    .promo-ico:nth-child(8) {
        animation-delay: .7s
    }

    .promo-ico:nth-child(9) {
        animation-delay: .5s
    }

    @keyframes icoFloat {

        0%,
        100% {
            transform: translateY(0)
        }

        50% {
            transform: translateY(-6px)
        }
    }

    .promo-ico--blue {
        background: rgba(37, 99, 235, 0.22);
        color: #60a5fa;
    }

    .promo-ico--green {
        background: rgba(16, 185, 129, 0.22);
        color: #34d399;
    }

    .promo-ico--purple {
        background: rgba(139, 92, 246, 0.22);
        color: #c084fc;
    }

    .promo-ico--red {
        background: rgba(220, 38, 38, 0.22);
        color: #f87171;
    }

    .promo-ico--orange {
        background: rgba(234, 88, 12, 0.22);
        color: #fb923c;
    }

    .promo-ico--pink {
        background: rgba(225, 29, 72, 0.22);
        color: #fb7185;
    }

    .promo-ico--teal {
        background: rgba(20, 184, 166, 0.22);
        color: #2dd4bf;
    }

    .promo-ico--yellow {
        background: rgba(234, 179, 8, 0.22);
        color: #fbbf24;
    }

    .promo-ico--center {
        background: linear-gradient(135deg, #2563eb, #10b981);
        color: #fff;
        font-size: 1.6rem;
        width: 54px;
        height: 54px;
        box-shadow: 0 8px 24px rgba(37, 99, 235, 0.45);
        border-radius: 16px;
    }

    .promo-badge-tag {
        position: absolute;
        bottom: 14px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, #2563eb, #10b981);
        color: #fff;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        padding: 5px 16px;
        border-radius: 99px;
        white-space: nowrap;
        box-shadow: 0 4px 16px rgba(37, 99, 235, 0.4);
    }

    /* Body */
    .promo-body {
        padding: 24px 28px 28px;
        z-index: 900;
    }

    .promo-title {
        font-size: 1.35rem;
        font-weight: 800;
        color: var(--text, #f1f5f9);
        line-height: 1.3;
        margin-bottom: 8px;
    }

    .promo-sub {
        font-size: 0.88rem;
        color: var(--text-secondary, #94a3b8);
        line-height: 1.6;
        margin-bottom: 18px;
    }

    /* Stats */
    .promo-stats {
        display: flex;
        align-items: center;
        gap: 0;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.08));
        border-radius: 14px;
        padding: 14px 0;
        margin-bottom: 18px;
    }

    [data-theme="light"] .promo-stats {
        background: rgba(37, 99, 235, 0.05);
    }

    .promo-stat {
        flex: 1;
        text-align: center;
    }

    .promo-stat strong {
        display: block;
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--text, #f1f5f9);
        line-height: 1.1;
    }

    .promo-stat span {
        font-size: 0.72rem;
        color: var(--text-muted, #64748b);
        font-weight: 600;
    }

    .promo-stat-divider {
        width: 1px;
        height: 36px;
        background: var(--glass-border, rgba(255, 255, 255, 0.1));
    }

    /* Benefits */
    .promo-benefits {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-bottom: 22px;
    }

    .promo-benefit {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-secondary, #94a3b8);
    }

    .promo-benefit i {
        color: #34d399;
        font-size: 0.8rem;
    }

    /* CTAs */
    .promo-ctas {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .promo-btn-primary {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 14px 24px;
        border-radius: 99px;
        background: linear-gradient(135deg, #2563eb, #10b981);
        color: #fff;
        font-weight: 700;
        font-size: 0.95rem;
        text-decoration: none;
        border: none;
        cursor: pointer;
        box-shadow: 0 6px 24px rgba(37, 99, 235, 0.38);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .promo-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 32px rgba(37, 99, 235, 0.5);
    }

    .promo-btn-skip {
        background: none;
        border: none;
        color: var(--text-muted, #64748b);
        font-size: 0.82rem;
        cursor: pointer;
        padding: 6px;
        text-align: center;
        font-weight: 600;
        transition: color 0.2s;
        font-family: inherit;
    }

    .promo-btn-skip:hover {
        color: var(--text-secondary, #94a3b8);
    }

    @media (max-width: 480px) {
        .promo-body {
            padding: 20px 20px 24px;
        }

        .promo-title {
            font-size: 1.15rem;
        }

        .promo-visual {
            height: 150px;
        }

        .promo-ico {
            width: 38px;
            height: 38px;
            font-size: 1.1rem;
        }

        .promo-ico--center {
            width: 46px;
            height: 46px;
            font-size: 1.3rem;
        }
    }

    /* Search dropdown stacking — must sit above hero-cats-wrap */
    .hero-search-wrap {
        position: relative;
        z-index: 10;
    }

    .hero-cats-wrap {
        position: relative;
        z-index: 1;
    }
</style>

<script>
    (function () {
        var KEY = 'hd_promo_seen';
        var overlay = document.getElementById('promo-interstitial');
        if (!overlay) return;
        if (sessionStorage.getItem(KEY)) {
            overlay.style.display = 'none';
            return;
        }
        sessionStorage.setItem(KEY, '1');
    })();

    function closePromoInterstitial() {
        var overlay = document.getElementById('promo-interstitial');
        if (!overlay) return;
        overlay.classList.add('hiding');
        setTimeout(function () {
            overlay.style.display = 'none';
        }, 300);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closePromoInterstitial();
    });
    document.getElementById('promo-interstitial').addEventListener('click', function (e) {
        if (e.target === this) closePromoInterstitial();
    });
</script>

<script>
    window.LOOKING_CONFIG = {
        activeCat: 0,
        activeCity: '',
        apiBase: '<?= API_BASE ?>'
    };
</script>
<script src="assets/js/listings.js?v=2.7.0"></script>

<?php
// Web push — homepage only
$_hd_vapid_public = '';
$_hd_vapid_config = __DIR__ . '/includes/vapid_config.php';
if (file_exists($_hd_vapid_config)) {
    require_once $_hd_vapid_config;
    if (defined('VAPID_PUBLIC_KEY_BASE64') && VAPID_PUBLIC_KEY_BASE64) {
        $_hd_vapid_public = VAPID_PUBLIC_KEY_BASE64;
    }
}
?>
<script>
    window.HD_PUSH_CONFIG = {
        vapidPublicKey: '<?= htmlspecialchars($_hd_vapid_public, ENT_QUOTES, 'UTF-8') ?>',
        saveEndpoint: '/HealthDial/Backend/api/save_web_push.php'
    };
</script>
<script src="assets/js/push-notifications.js?v=1.3.0"></script>

<?php require_once 'includes/footer.php'; ?>