<?php
if (!isset($currentPage))
$currentPage = 'home';
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
<html lang="en" data-theme="light">

<head>
  <meta charset="UTF-8" />
  <base href="<?= $assetBase ?>/" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />
  <title><?= htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="description"
    content="<?= htmlspecialchars($descriptionText, ENT_QUOTES, 'UTF-8') ?>" />
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
  <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/style.css?v=2.1.0" />
  

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
  function gtag(){dataLayer.push(arguments);}
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
        <a href="<?= $assetBase ?>/blog.php" <?php if ($currentPage == 'blog')
          echo 'class="active"'; ?>>Blog</a>
        <a href="<?= $assetBase ?>/news.php" <?php if ($currentPage == 'news')
          echo 'class="active"'; ?>>News</a>
        <a href="<?= $assetBase ?>/contact.php" <?php if ($currentPage == 'contact')
          echo 'class="active"'; ?>>Contact</a>
        <a href="<?= $assetBase ?>/promote.php" <?php if ($currentPage == 'promote')
          echo 'class="active"'; ?> style="color:#f59e0b;font-weight:700;"><i class="fas fa-bolt" style="margin-right:3px;"></i>Promote</a>
        <!-- Dark Mode Toggle -->
        <button class="dark-mode-toggle" id="darkModeToggle" onClick="toggleDarkMode()" aria-label="Toggle dark mode">
          <i class="fas fa-moon"></i>
        </button>
        <a href="<?= $assetBase ?>/download.php" class="btn btn-primary nav-cta"><i class="fas fa-download"></i> Get App</a>
      </div>
      <div class="nav-right-mobile">
        <button class="dark-mode-toggle" id="darkModeToggleMobile" onClick="toggleDarkMode()"
          aria-label="Toggle dark mode">
          <i class="fas fa-moon"></i>
        </button>
        <button class="hamburger" aria-label="Menu">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </nav>

  <!-- Slide-down mobile menu -->
  <div class="mobile-nav">
    <a href="<?= $assetBase ?>/index.php">Home</a>
    <a href="<?= $assetBase ?>/categories.php">Categories</a>
    <a href="<?= $assetBase ?>/listings.php">Listings</a>
    <a href="<?= $assetBase ?>/cities.php">Cities</a>
    <a href="<?= $assetBase ?>/blog.php">Blog</a>
    <a href="<?= $assetBase ?>/news.php">News</a>
    <a href="<?= $assetBase ?>/contact.php">Contact</a>
    <a href="<?= $assetBase ?>/promote.php" style="color:#f59e0b;font-weight:600;"><i class="fas fa-bolt"></i> Promote Listing</a>
    <a href="<?= $assetBase ?>/download.php" class="btn btn-primary" style="margin-top:12px;"><i class="fas fa-download"></i> Download
      App</a>
  </div>

  <!-- ===== MOBILE BOTTOM NAVIGATION ===== -->
  <div class="mobile-bottom-nav" id="mobileBottomNav">
    <a href="<?= $assetBase ?>/index.php" class="bottom-nav-item <?= $currentPage == 'home' ? 'active' : '' ?>">
      <i class="fas fa-home"></i><span>Home</span>
    </a>
    <a href="<?= $assetBase ?>/listings.php" class="bottom-nav-item <?= $currentPage == 'listings' ? 'active' : '' ?>">
      <i class="fas fa-search"></i><span>Explore</span>
    </a>
    <a href="<?= $assetBase ?>/promote.php" class="bottom-nav-item <?= $currentPage == 'promote' ? 'active' : '' ?>" style="<?= $currentPage == 'promote' ? '' : 'color:#f59e0b;' ?>">
      <i class="fas fa-bolt"></i><span>Promote</span>
    </a>
    <a href="<?= $assetBase ?>/blog.php" class="bottom-nav-item <?= $currentPage == 'blog' ? 'active' : '' ?>">
      <i class="fas fa-heartbeat"></i><span>Health</span>
    </a>
    <a href="<?= $assetBase ?>/download.php" class="bottom-nav-item <?= $currentPage == 'download' ? 'active' : '' ?>">
      <i class="fas fa-mobile-alt"></i><span>Get App</span>
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
    (function () {
      const saved = localStorage.getItem('hd_theme');
      if (saved) {
        document.documentElement.setAttribute('data-theme', saved);
        updateDarkModeIcons(saved);
      }
    })();
  </script>
