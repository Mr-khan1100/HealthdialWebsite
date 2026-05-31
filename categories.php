<?php
$currentPage = 'categories';
$pageTitle = 'Medical Categories | Find Medical Help Near Me | Labs Near Your Home | Health Dial';
$pageDesc = 'If you are searching for Top Clinics Near Your home, Doctors, hospitals, or want to Book Diagnostic Tests then health dial is the right source for you.';
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

// Icon mapping
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

<section class="section" style="padding-top: 140px;">
    <div class="container">
        <div class="section-header">
            <span class="section-label">
                <?= icon('grid') ?> Categories
            </span>
            <h2 class="section-title">Browse <span class="gradient-text">Medical Categories</span></h2>
            <p class="section-subtitle">Find exactly the type of medical service you need from our comprehensive
                categories.</p>
        </div>
        <div class="categories-grid categories-grid-listings">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $i => $cat): 
                    $iconKey = $catIcons[$cat['name']] ?? 'hospital';
                ?>
                <a href="looking.php?cat=<?= $cat['id'] ?>&name=<?= urlencode($cat['name']) ?>" class="category-card category-card-link reveal delay-<?= ($i % 5) + 1 ?>">
                    <div class="category-icon">
                        <?= icon($iconKey) ?>
                    </div>
                    <div class="category-name"><?= htmlspecialchars($cat['name']) ?></div>
                    <span class="category-count"><?= number_format($cat['listing_count']) ?> listings</span>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <?php
                // Fallback to hardcoded categories if DB fails
                $cats = [
                    ['icon' => 'hospital', 'name' => 'Hospital', 'desc' => 'Multi-specialty and general hospitals'],
                    ['icon' => 'clinic', 'name' => 'Clinic', 'desc' => 'Doctor clinics and specialist practices'],
                    ['icon' => 'lab', 'name' => 'Diagnostic Lab', 'desc' => 'Pathology and diagnostic centers'],
                    ['icon' => 'pharmacy', 'name' => 'Medical Store', 'desc' => 'Pharmacies and medical shops'],
                    ['icon' => 'ambulance', 'name' => 'Ambulance', 'desc' => '24/7 ambulance services'],
                    ['icon' => 'bloodBank', 'name' => 'Blood Bank', 'desc' => 'Blood donation and storage centers'],
                    ['icon' => 'physio', 'name' => 'Physiotherapy', 'desc' => 'Physical therapy and rehabilitation'],
                    ['icon' => 'dental', 'name' => 'Dental Care', 'desc' => 'Dental clinics and orthodontics'],
                    ['icon' => 'eyeCare', 'name' => 'Eye Care', 'desc' => 'Ophthalmology and optical shops'],
                    ['icon' => 'cardiology', 'name' => 'Cardiology', 'desc' => 'Heart care and cardiac centers'],
                ];
                foreach ($cats as $i => $c): ?>
                <a href="looking.php?name=<?= urlencode($c['name']) ?>" class="category-card category-card-link reveal delay-<?= ($i % 5) + 1 ?>">
                    <div class="category-icon">
                        <?= icon($c['icon']) ?>
                    </div>
                    <div class="category-name"><?= $c['name'] ?></div>
                    <p class="card-text" style="font-size: 12px; margin-top: 4px;"><?= $c['desc'] ?></p>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="section cta-section" id="download">
    <div class="container">
        <h2>Explore All Categories on the <span class="gradient-text">App</span></h2>
        <p class="cta-subtitle">Download HealthDial to access all medical categories with GPS search and instant
            contact.</p>
        <div class="cta-buttons">
            <a href="https://play.google.com/store/apps/details?id=com.healthdial.mobile" target="_blank"><img
                    src="assets/images/google-play.svg" alt="Get it on Google Play" class="store-badge" /></a>
            <a href="https://apps.apple.com/app/healthdial" target="_blank"><img src="assets/images/app-store.svg"
                    alt="Download on App Store" class="store-badge" /></a>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>