<?php
$currentPage = 'features';
$pageTitle = 'Features';
$pageDesc = 'Explore HealthDial features — GPS search, verified listings, one-tap calling, emergency services, and more.';
require_once 'includes/icons.php';
require_once 'includes/header.php';
?>

<section class="section" style="padding-top: 140px; background: linear-gradient(135deg, #f0f7ff 0%, #f0faf0 100%);">
    <div class="container">
        <div class="section-header">
            <span class="section-label">
                <?= icon('star') ?> Features
            </span>
            <h2 class="section-title">Everything You Need to Find <span class="gradient-text">Medical Help</span></h2>
            <p class="section-subtitle">HealthDial is packed with features designed to make healthcare accessible, fast,
                and reliable for every Indian.</p>
        </div>
        <div class="features-grid">
            <?php
            $features = [
                ['icon' => 'shield', 'color' => 'blue', 'title' => 'Verified Listings', 'text' => 'Every medical facility is manually verified by our team. We contact each provider, check their documents, and confirm they are operational. No fake or outdated listings.'],
                ['icon' => 'gps', 'color' => 'green', 'title' => 'GPS-Based Search', 'text' => 'Use your phone\'s GPS to find the nearest hospitals, clinics, and labs. See live distance, estimated travel time, and get turn-by-turn navigation.'],
                ['icon' => 'phone', 'color' => 'blue', 'title' => 'One-Tap Calling & WhatsApp', 'text' => 'No more digging for phone numbers. Tap once to call or WhatsApp any medical provider directly from the app with instant connection.'],
                ['icon' => 'clock', 'color' => 'green', 'title' => '24/7 Emergency Access', 'text' => 'A dedicated emergency section with ambulance services, blood banks, and 24×7 hospitals. Available when you need it most — day or night.'],
                ['icon' => 'search', 'color' => 'blue', 'title' => 'Smart Search & Filters', 'text' => 'Search by name, specialty, or category. Use filters to narrow down by distance, open status, ratings, and type of service to find exactly what you need.'],
                ['icon' => 'news', 'color' => 'green', 'title' => 'Health News Updates', 'text' => 'Stay informed with the latest health news from India. Government policies, health advisories, and medical breakthroughs delivered to you.'],
                ['icon' => 'location', 'color' => 'blue', 'title' => 'Multi-City Coverage', 'text' => 'Available in 500+ cities across India with coverage in local areas, small towns, and rural regions. We are expanding every day.'],
                ['icon' => 'compare', 'color' => 'green', 'title' => 'Compare Facilities', 'text' => 'Compare hospitals, clinics, and labs side by side. View timings, services offered, distance, and patient reviews all in one place.'],
                ['icon' => 'user', 'color' => 'blue', 'title' => 'For Patients & Providers', 'text' => 'Patients find the best care; providers reach more patients. A two-sided platform that benefits the entire healthcare ecosystem.'],
            ];
            foreach ($features as $i => $f):
                ?>
                <div class="card feature-card reveal delay-<?= ($i % 3) + 1 ?>">
                    <div class="card-icon <?= $f['color'] ?>">
                        <?= icon($f['icon']) ?>
                    </div>
                    <h3 class="card-title">
                        <?= $f['title'] ?>
                    </h3>
                    <p class="card-text">
                        <?= $f['text'] ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="section cta-section" id="download">
    <div class="container">
        <h2>Try <span class="gradient-text">HealthDial</span> Today</h2>
        <p class="cta-subtitle">Download now and experience healthcare search like never before.</p>
        <div class="cta-buttons">
            <a href="https://play.google.com/store/apps/details?id=com.healthdial" target="_blank"><img
                    src="assets/images/google-play.svg" alt="Get it on Google Play" class="store-badge" /></a>
            <a href="https://apps.apple.com/app/healthdial" target="_blank"><img src="assets/images/app-store.svg"
                    alt="Download on App Store" class="store-badge" /></a>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>