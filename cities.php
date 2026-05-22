<?php
$currentPage = 'cities';
$pageTitle = 'Browse by City';
$pageDesc = 'Find hospitals, clinics, labs and medical services in 500+ cities across India.';
require_once 'includes/icons.php';
require_once 'includes/db.php';
require_once 'includes/header.php';

$cities = [
    ['name'=>'Mumbai','state'=>'Maharashtra','count'=>28500],['name'=>'Delhi','state'=>'Delhi','count'=>32100],
    ['name'=>'Bangalore','state'=>'Karnataka','count'=>22400],['name'=>'Hyderabad','state'=>'Telangana','count'=>18900],
    ['name'=>'Chennai','state'=>'Tamil Nadu','count'=>16800],['name'=>'Kolkata','state'=>'West Bengal','count'=>15200],
    ['name'=>'Pune','state'=>'Maharashtra','count'=>14600],['name'=>'Ahmedabad','state'=>'Gujarat','count'=>12300],
    ['name'=>'Jaipur','state'=>'Rajasthan','count'=>9800],['name'=>'Lucknow','state'=>'Uttar Pradesh','count'=>8900],
    ['name'=>'Chandigarh','state'=>'Punjab','count'=>5400],['name'=>'Bhopal','state'=>'Madhya Pradesh','count'=>6200],
    ['name'=>'Patna','state'=>'Bihar','count'=>5800],['name'=>'Indore','state'=>'Madhya Pradesh','count'=>7100],
    ['name'=>'Nagpur','state'=>'Maharashtra','count'=>6500],['name'=>'Visakhapatnam','state'=>'Andhra Pradesh','count'=>5200],
    ['name'=>'Coimbatore','state'=>'Tamil Nadu','count'=>4800],['name'=>'Kochi','state'=>'Kerala','count'=>4500],
    ['name'=>'Thiruvananthapuram','state'=>'Kerala','count'=>3900],['name'=>'Guwahati','state'=>'Assam','count'=>3200],
    ['name'=>'Vadodara','state'=>'Gujarat','count'=>4100],['name'=>'Surat','state'=>'Gujarat','count'=>5600],
    ['name'=>'Ranchi','state'=>'Jharkhand','count'=>2800],['name'=>'Bhubaneswar','state'=>'Odisha','count'=>3100],
    ['name'=>'Dehradun','state'=>'Uttarakhand','count'=>2400],['name'=>'Raipur','state'=>'Chhattisgarh','count'=>2600],
    ['name'=>'Mysore','state'=>'Karnataka','count'=>3400],['name'=>'Jodhpur','state'=>'Rajasthan','count'=>2900],
    ['name'=>'Amritsar','state'=>'Punjab','count'=>2700],['name'=>'Varanasi','state'=>'Uttar Pradesh','count'=>3600],
    ['name'=>'Agra','state'=>'Uttar Pradesh','count'=>3100],['name'=>'Noida','state'=>'Uttar Pradesh','count'=>4800],
    ['name'=>'Gurgaon','state'=>'Haryana','count'=>5200],['name'=>'Thane','state'=>'Maharashtra','count'=>4900],
    ['name'=>'Nashik','state'=>'Maharashtra','count'=>3200],['name'=>'Kanpur','state'=>'Uttar Pradesh','count'=>3800],
];
?>

<section class="cities-hero">
    <div class="container" style="text-align:center;">
        <h1>Browse by <span class="gradient-text">City</span></h1>
        <p style="color:var(--text-muted);margin-top:8px;" data-translate>Find medical services in 500+ cities across India</p>
        <div class="cities-search">
            <i class="fas fa-search"></i>
            <input type="text" id="citySearchInput" placeholder="Search city..." oninput="filterCities()" data-translate-placeholder />
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="cities-grid" id="citiesGrid">
            <?php foreach ($cities as $city): ?>
            <a href="looking.php?city=<?= urlencode($city['name']) ?>" class="city-card reveal" data-city="<?= strtolower($city['name']) ?>">
                <i class="fas fa-map-marker-alt"></i>
                <div class="city-card-info">
                    <strong><?= $city['name'] ?></strong>
                    <span><?= $city['state'] ?> · <?= number_format($city['count']) ?> listings</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <div id="noCityResult" style="display:none;text-align:center;padding:40px;color:var(--text-muted);">
            <i class="fas fa-search" style="font-size:32px;opacity:0.3;margin-bottom:12px;display:block;"></i>
            <p>No cities found. Try a different search.</p>
        </div>
    </div>
</section>

<script>
function filterCities() {
    const q = document.getElementById('citySearchInput').value.toLowerCase();
    const cards = document.querySelectorAll('.city-card');
    let found = 0;
    cards.forEach(c => {
        const match = c.dataset.city.includes(q);
        c.style.display = match ? '' : 'none';
        if (match) found++;
    });
    document.getElementById('noCityResult').style.display = found ? 'none' : 'block';
}
</script>

<?php require_once 'includes/footer.php'; ?>
