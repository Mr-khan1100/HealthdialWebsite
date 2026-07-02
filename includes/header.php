<?php
if (!isset($currentPage))
    $currentPage = 'home';

// Website user session (shared with the mobile app's users table).
require_once __DIR__ . '/user_auth.php';
$hdUser = hd_current_user();
$hdReturn = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');

$titleText = isset($pageTitle) ? $pageTitle . ' | HealthDial' : 'HealthDial - Find Medical Help Instantly';
$descriptionText = isset($pageDesc) ? $pageDesc : 'Find Hospitals, Clinics, Labs & Doctors Near You - Instantly.';
$assetBase = '';
if (function_exists('hd_asset_base')) {
    $assetBase = hd_asset_base();
} elseif (defined('HEALTHDIAL_ASSET_BASE')) {
    $assetBase = HEALTHDIAL_ASSET_BASE;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8" />
    <base href="<?= $assetBase ?>/" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />
    <title><?= htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($descriptionText, ENT_QUOTES, 'UTF-8') ?>" />
    <?php if (!empty($canonicalUrl)): ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>" />
    <?php endif; ?>
    <meta name="theme-color" content="#2B7DE9" />
    <link rel="icon" href="<?= $assetBase ?>/assets/images/icon.png" />
    <link rel="manifest" href="<?= $assetBase ?>/manifest.json" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/style.css?v=3.1.6" />
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/revamp.css?v=3.1.9" />


    <meta name="google-site-verification" content="zUHx3csIxzeg0V6z14P2BJEJMLX8d8sse1grih5Kk2Y" />
    <meta name="msvalidate.01" content="E74031E6EB42FD1D035C6E3E5B67136F" />
    <meta name="robots" content="index, follow">
    <meta name="robots" content="noodp, noydir" />
    <?php
    if (!empty($structuredData)) {
        $jsonLdItems = isset($structuredData['@context']) ? [$structuredData] : $structuredData;
        foreach ($jsonLdItems as $jsonLdItem) {
            if (!is_array($jsonLdItem)) {
                continue;
            }
            echo '<script type="application/ld+json">' . json_encode($jsonLdItem, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . PHP_EOL;
        }
    }
    ?>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-MC8HC3L38F"></script>
    <script>
    window.dataLayer = window.dataLayer || [];

    function gtag() {
        dataLayer.push(arguments);
    }
    gtag('js', new Date());

    gtag('config', 'G-MC8HC3L38F');
    </script>
</head>

<body>

    <!-- ===== TOP NAVBAR ===== -->
    <nav class="navbar" id="navbar">
        <div class="container">
            <a href="<?= $assetBase ?>/index.php" class="nav-logo">
                <img src="<?= $assetBase ?>/assets/images/logo.png" alt="HealthDial" />
            </a>
            <div class="nav-links">
                <a href="<?= $assetBase ?>/index.php" <?php if ($currentPage == 'home')
                      echo 'class="active"'; ?>>Home</a>
                <a href="<?= $assetBase ?>/categories.php" <?php if ($currentPage == 'categories')
                      echo 'class="active"'; ?>>Categories</a>
                <a href="<?= $assetBase ?>/listings.php" <?php if ($currentPage == 'listings')
                      echo 'class="active"'; ?>>Listings</a>
                <a href="<?= $assetBase ?>/cities.php" <?php if ($currentPage == 'cities')
                      echo 'class="active"'; ?>>Cities</a>
                <a href="<?= $assetBase ?>/news.php" <?php if ($currentPage == 'news')
                      echo 'class="active"'; ?>>News</a>
                <a href="<?= $assetBase ?>/contact.php" <?php if ($currentPage == 'contact')
                      echo 'class="active"'; ?>>Contact</a>
                <a href="<?= $assetBase ?>/promote.php" <?php if ($currentPage == 'promote')
                      echo 'class="active"'; ?> style="color:#f59e0b;font-weight:700;"><i class="fas fa-bolt"
                        style="margin-right:3px;"></i>Promote</a>
                <?php if ($hdUser): ?>
                <a href="<?= $assetBase ?>/profile.php" class="nav-user-icon"
                    title="My Profile — <?= htmlspecialchars($hdUser['name'] ?: 'Account') ?>" aria-label="My Profile">
                    <?php if (!empty($hdUser['profile_image'])): ?>
                    <img src="<?= htmlspecialchars($hdUser['profile_image']) ?>" alt="" />
                    <?php else: ?>
                    <span><?= strtoupper(substr(trim($hdUser['name']) !== '' ? $hdUser['name'] : 'U', 0, 1)) ?></span>
                    <?php endif; ?>
                </a>
                <?php else: ?>
                <a href="<?= $assetBase ?>/login.php?return=<?= $hdReturn ?>" class="nav-user-icon nav-user-icon--guest"
                    title="Login / Sign Up" aria-label="Login or Sign Up">
                    <i class="fas fa-user"></i>
                </a>
                <?php endif; ?>
                <!-- Dark Mode Toggle -->
                <button class="dark-mode-toggle" id="darkModeToggle" onClick="toggleDarkMode()"
                    aria-label="Toggle dark mode">
                    <i class="fas fa-moon"></i>
                </button>
                <a href="<?= $assetBase ?>/add-listing.php"
                    class="btn nav-cta <?= $currentPage == 'add-listing' ? 'btn-primary' : '' ?>"
                    style="background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;"><i
                        class="fas fa-plus"></i>
                    Add Listing</a>
                <a href="<?= $assetBase ?>/download.php" class="btn btn-primary nav-cta" style="margin-left:4px;"><i
                        class="fas fa-download"></i> Get App</a>
            </div>
            <div class="nav-right-mobile">
                <a href="<?= $assetBase ?>/add-listing.php" class="nav-mobile-add-btn">
                    <i class="fas fa-plus"></i> Add Free Listing
                </a>
                <button class="dark-mode-toggle" id="darkModeToggleMobile" onClick="toggleDarkMode()"
                    aria-label="Toggle dark mode">
                    <i class="fas fa-moon"></i>
                </button>

                <?php if ($hdUser): ?>
                <a href="<?= $assetBase ?>/profile.php" class="nav-user-icon nav-user-icon--mobile"
                    aria-label="My Profile">
                    <?php if (!empty($hdUser['profile_image'])): ?>
                    <img src="<?= htmlspecialchars($hdUser['profile_image']) ?>" alt="" />
                    <?php else: ?>
                    <span><?= strtoupper(substr(trim($hdUser['name']) !== '' ? $hdUser['name'] : 'U', 0, 1)) ?></span>
                    <?php endif; ?>
                </a>
                <?php else: ?>
                <a href="<?= $assetBase ?>/login.php?return=<?= $hdReturn ?>"
                    class="nav-user-icon nav-user-icon--guest nav-user-icon--mobile" aria-label="Login or Sign Up">
                    <i class="fas fa-user"></i>
                </a>
                <?php endif; ?>

                <button class="hamburger" aria-label="Menu">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </nav>

    <!-- Slide-down hamburger menu -->
    <div class="mobile-nav more-drawer">
        <span class="more-drawer-label">Menu</span>
        <a href="<?= $assetBase ?>/categories.php" <?php if ($currentPage == 'categories')
              echo 'class="active"'; ?>><i class="fas fa-layer-group"></i> Categories</a>
        <a href="<?= $assetBase ?>/cities.php" <?php if ($currentPage == 'cities')
              echo 'class="active"'; ?>><i class="fas fa-city"></i> Cities</a>
        <a href="<?= $assetBase ?>/contact.php" <?php if ($currentPage == 'contact')
              echo 'class="active"'; ?>><i class="fas fa-headset"></i> Contact</a>
        <!-- <?php if ($hdUser): ?>
        <a href="<?= $assetBase ?>/profile.php" <?php if ($currentPage == 'profile')
              echo 'class="active"'; ?>><i
                class="fas fa-circle-user"></i> My Profile</a>
        <a href="<?= $assetBase ?>/logout.php?return=<?= $hdReturn ?>"><i class="fas fa-right-from-bracket"></i>
            Logout (<?= htmlspecialchars(strtok($hdUser['name'] ?: 'Account', ' ')) ?>)</a>
        <?php else: ?>
        <a href="<?= $assetBase ?>/login.php?return=<?= $hdReturn ?>"><i class="fas fa-right-to-bracket"></i> Login /
            Sign Up</a>
        <?php endif; ?> -->
        <a href="<?= $assetBase ?>/download.php" class="more-drawer-cta"><i class="fas fa-download"></i> Get App</a>
    </div>

    <!-- Account / login user-icon styling -->
    <style>
    .nav-user-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        padding: 0;
        border-radius: 50%;
        overflow: hidden;
        flex: 0 0 auto;
        margin-left: 4px;
        font-weight: 800;
        font-size: .95rem;
        text-decoration: none;
        border: 2px solid var(--glass-border, rgba(255, 255, 255, 0.18));
        transition: transform .15s, box-shadow .15s;
    }

    .nav-user-icon img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .nav-user-icon--mobile {
        width: 34px;
        height: 34px;
        margin-left: 0;
    }

    .nav-user-icon:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
    }

    /* color/background scoped to also beat the generic ".nav-links a" rule */
    .nav-user-icon,
    .nav-links a.nav-user-icon,
    .nav-links a.nav-user-icon:hover {
        padding: 0;
        color: #fff;
        background: linear-gradient(135deg, #2563eb, #10b981);
    }

    .nav-user-icon--guest,
    .nav-links a.nav-user-icon--guest,
    .nav-links a.nav-user-icon--guest:hover {
        color: var(--text-secondary, #cbd5e1);
        background: var(--glass, rgba(255, 255, 255, 0.06));
        border-color: var(--glass-border, rgba(255, 255, 255, 0.14));
    }

    [data-theme="light"] .nav-user-icon--guest,
    [data-theme="light"] .nav-links a.nav-user-icon--guest {
        color: #334155;
        background: #eef2f7;
    }
    </style>
    <script>
    // Expose auth state for inline gating (QR unlock, claim, etc.)
    window.HD_AUTH = {
        loggedIn: <?= $hdUser ? 'true' : 'false' ?>,
        phoneVerified: <?= ($hdUser && !empty($hdUser['phone_verified'])) ? 'true' : 'false' ?>,
        loginUrl: '<?= $assetBase ?>/login.php',
        verifyUrl: '<?= $assetBase ?>/profile.php?verify=required'
    };
    </script>

    <!-- ===== MOBILE BOTTOM NAVIGATION ===== -->
    <div class="mobile-bottom-nav" id="mobileBottomNav">
        <a href="<?= $assetBase ?>/index.php" class="bottom-nav-item <?= $currentPage == 'home' ? 'active' : '' ?>">
            <i class="fas fa-home"></i><span>Home</span>
        </a>
        <a href="<?= $assetBase ?>/looking.php"
            class="bottom-nav-item <?= $currentPage == 'looking' ? 'active' : '' ?>">
            <i class="fas fa-search"></i><span>Explore</span>
        </a>
        <a href="<?= $assetBase ?>/promote.php" class="bottom-nav-item <?= $currentPage == 'promote' ? 'active' : '' ?>"
            style="<?= $currentPage == 'promote' ? '' : 'color:#f59e0b;' ?>">
            <i class="fas fa-bolt"></i><span>Promote</span>
        </a>
        <a href="<?= $assetBase ?>/add-listing.php"
            class="bottom-nav-item <?= $currentPage == 'add-listing' ? 'active' : '' ?>"
            style="<?= $currentPage == 'add-listing' ? '' : 'color:#059669;' ?>">
            <i class="fas fa-plus-circle"></i><span>Add</span>
        </a>
        <a href="<?= $assetBase ?>/news.php" class="bottom-nav-item <?= $currentPage == 'news' ? 'active' : '' ?>">
            <i class="fas fa-newspaper"></i><span>News</span>
        </a>
    </div>



    <!-- Dark Mode Script (must run early) -->
    <script>
    function toggleDarkMode() {
        const html = document.documentElement;
        const current = html.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('hd_theme', next);
        updateDarkModeIcons(next);
    }

    function updateDarkModeIcons(theme) {
        document.querySelectorAll('.dark-mode-toggle i').forEach(i => {
            i.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        });
    }
    // Apply saved theme
    (function() {
        const saved = localStorage.getItem('hd_theme');
        if (saved) {
            document.documentElement.setAttribute('data-theme', saved);
            updateDarkModeIcons(saved);
        }
    })();
    </script>