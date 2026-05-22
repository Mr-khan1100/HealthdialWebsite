<?php
$currentPage = 'compare';
$pageTitle = 'Compare Listings';
$pageDesc = 'Compare hospitals, clinics and medical facilities side by side on HealthDial.';
require_once 'includes/icons.php';
require_once 'includes/db.php';
require_once 'includes/header.php';
?>

<section class="section" style="padding-top:120px;">
    <div class="container">
        <div class="section-head" style="text-align:center;margin-bottom:32px;">
            <h1>Compare <span class="gradient-text">Listings</span></h1>
            <p style="color:var(--text-muted);margin-top:8px;">Select 2-3 listings from the homepage to compare them side by side</p>
        </div>

        <div id="compareContent">
            <div id="compareEmpty" style="text-align:center;padding:80px 20px;">
                <i class="fas fa-columns" style="font-size:64px;color:var(--text-muted);opacity:0.2;margin-bottom:20px;display:block;"></i>
                <h3 style="margin-bottom:8px;">No listings to compare</h3>
                <p style="color:var(--text-muted);margin-bottom:24px;">Go to the homepage, click the <i class="fas fa-chart-bar"></i> compare icon on listing cards, then return here.</p>
                <a href="index.php" class="btn btn-primary"><i class="fas fa-home"></i> Browse Listings</a>
            </div>

            <div id="compareTableWrap" style="display:none;overflow-x:auto;">
                <table class="compare-table" id="compareTable">
                    <thead><tr id="compareHeader"><th>Feature</th></tr></thead>
                    <tbody id="compareBody"></tbody>
                </table>
                <div style="text-align:center;margin-top:24px;">
                    <button class="btn btn-secondary" onclick="clearComparison()"><i class="fas fa-trash"></i> Clear All</button>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const items = JSON.parse(localStorage.getItem('hd_compare') || '[]');
    if (items.length < 2) return;

    document.getElementById('compareEmpty').style.display = 'none';
    document.getElementById('compareTableWrap').style.display = 'block';

    const header = document.getElementById('compareHeader');
    const body = document.getElementById('compareBody');

    items.forEach(item => {
        header.innerHTML += `<th>${item.name}</th>`;
    });

    const rows = [
        { label: 'Category', key: 'category' },
        { label: 'Rating', key: 'rating', format: v => v + ' ⭐' },
        { label: 'Reviews', key: 'reviewCount' },
        { label: 'Address', key: 'address' },
        { label: 'City', key: 'city' },
        { label: 'Phone', key: 'mobile', format: v => v ? `<a href="tel:${v}">${v}</a>` : 'N/A' },
    ];

    rows.forEach(r => {
        let tr = `<tr><td>${r.label}</td>`;
        items.forEach(item => {
            let val = item[r.key] || 'N/A';
            if (r.format) val = r.format(val);
            tr += `<td>${val}</td>`;
        });
        tr += '</tr>';
        body.innerHTML += tr;
    });
});

function clearComparison() {
    localStorage.removeItem('hd_compare');
    location.reload();
}
</script>

<?php require_once 'includes/footer.php'; ?>
