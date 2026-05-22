<?php
$currentPage = 'blog';
$pageTitle = 'Our Blog | Book For Diagnostic Tests From Nearest Lab | Doctor Appointments by health dial | Health Dial';
$pageDesc = 'Healthdial is emerging as a trusted and rapidly growing online health management portal in Faridabad and across India. Download Health dial app for registration.';
require_once 'includes/icons.php';
require_once 'includes/db.php';
require_once 'includes/header.php';

$articles = [
    ['title' => 'First Aid: What to Do in a Medical Emergency', 'tag' => 'First Aid', 'excerpt' => 'Learn essential first aid steps that can save lives in emergencies. From CPR to choking relief, these basic skills are something everyone should know.', 'date' => 'Apr 15, 2026', 'read' => '5 min', 'icon' => 'fa-kit-medical', 'color' => '#dc2626'],
    ['title' => 'How to Choose the Right Hospital Near You', 'tag' => 'Guide', 'excerpt' => 'Finding the right hospital can be overwhelming. Here are key factors to consider: specializations, ratings, distance, emergency availability, and insurance acceptance.', 'date' => 'Apr 12, 2026', 'read' => '4 min', 'icon' => 'fa-hospital', 'color' => '#2563eb'],
    ['title' => '10 Warning Signs You Shouldn\'t Ignore', 'tag' => 'Health Awareness', 'excerpt' => 'Persistent headaches, unexplained weight loss, chest pain — learn which symptoms need immediate medical attention and when to rush to the hospital.', 'date' => 'Apr 10, 2026', 'read' => '6 min', 'icon' => 'fa-triangle-exclamation', 'color' => '#ea580c'],
    ['title' => 'Understanding Your Blood Test Report', 'tag' => 'Lab Tests', 'excerpt' => 'CBC, LFT, KFT, Thyroid Panel — confused by your blood test results? This guide explains what each value means and what normal ranges look like.', 'date' => 'Apr 8, 2026', 'read' => '7 min', 'icon' => 'fa-flask', 'color' => '#7c3aed'],
    ['title' => 'Monsoon Health: Diseases to Watch Out For', 'tag' => 'Seasonal', 'excerpt' => 'Dengue, malaria, typhoid, and leptospirosis are common during monsoons. Learn prevention tips and early symptoms to stay safe this rainy season.', 'date' => 'Apr 5, 2026', 'read' => '5 min', 'icon' => 'fa-cloud-rain', 'color' => '#0891b2'],
    ['title' => 'Medicine Reminder: Why It\'s Important', 'tag' => 'Medication', 'excerpt' => 'Missing doses of prescribed medication can have serious consequences. Learn how HealthDial\'s medicine reminder feature helps you stay on track.', 'date' => 'Apr 2, 2026', 'read' => '3 min', 'icon' => 'fa-pills', 'color' => '#059669'],
    ['title' => 'When to Visit a Clinic vs Hospital vs ER', 'tag' => 'Guide', 'excerpt' => 'Not sure whether to go to a clinic, hospital, or emergency room? This guide helps you decide based on your symptoms and urgency level.', 'date' => 'Mar 28, 2026', 'read' => '4 min', 'icon' => 'fa-stethoscope', 'color' => '#2563eb'],
    ['title' => 'Mental Health: Breaking the Stigma in India', 'tag' => 'Mental Health', 'excerpt' => 'Depression, anxiety, and stress affect millions. Learn about available resources, how to find therapists near you, and why mental health matters.', 'date' => 'Mar 25, 2026', 'read' => '6 min', 'icon' => 'fa-brain', 'color' => '#9333ea'],
    ['title' => 'Vaccination Schedule for Children in India', 'tag' => 'Pediatric', 'excerpt' => 'From BCG at birth to HPV vaccine — a complete guide to the Indian vaccination schedule for children aged 0-18 years with timelines and dosage info.', 'date' => 'Mar 20, 2026', 'read' => '8 min', 'icon' => 'fa-syringe', 'color' => '#0d9488'],
];
?>

<section class="section" style="padding-top: 120px;">
    <div class="container">
        <div class="section-head" style="text-align:center; margin-bottom:40px;">
            <h1>Health Tips & <span class="gradient-text">Blog</span></h1>
            <p style="color:var(--text-muted); margin-top:8px;">Medical awareness, first aid guides, and wellness tips</p>
        </div>

        <div class="blog-grid">
            <?php foreach ($articles as $i => $art): ?>
            <div class="blog-card reveal" style="cursor:pointer;" onclick="openBlogArticle(<?= $i ?>)">
                <div class="blog-card-image" style="display:flex;align-items:center;justify-content:center;background:<?= $art['color'] ?>10;">
                    <i class="fas <?= $art['icon'] ?>" style="font-size:48px;color:<?= $art['color'] ?>;opacity:0.6;"></i>
                </div>
                <div class="blog-card-body">
                    <span class="blog-card-tag"><?= $art['tag'] ?></span>
                    <h3 class="blog-card-title" data-translate><?= $art['title'] ?></h3>
                    <p class="blog-card-excerpt" data-translate><?= $art['excerpt'] ?></p>
                    <div class="blog-card-meta">
                        <span><i class="far fa-calendar"></i> <?= $art['date'] ?></span>
                        <span><i class="far fa-clock"></i> <?= $art['read'] ?> read</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Blog Article Modal -->
<div class="apt-modal-overlay" id="blogModal">
    <div class="apt-modal" style="max-width:640px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <span class="blog-card-tag" id="blogModalTag"></span>
            <button onclick="document.getElementById('blogModal').classList.remove('active')" style="background:none;border:none;font-size:24px;cursor:pointer;color:var(--text-muted);">&times;</button>
        </div>
        <h2 id="blogModalTitle" style="margin-bottom:12px;"></h2>
        <div class="blog-card-meta" id="blogModalMeta" style="margin-bottom:20px;"></div>
        <div id="blogModalContent" style="line-height:1.8;color:var(--text-secondary);"></div>
        <div style="margin-top:24px;padding-top:16px;border-top:1px solid var(--border);text-align:center;">
            <p style="color:var(--text-muted);font-size:var(--fs-sm);">Get more health features on the app</p>
            <a href="download.php" class="btn btn-primary" style="margin-top:8px;"><i class="fas fa-download"></i> Download HealthDial</a>
        </div>
    </div>
</div>

<script>
const articles = <?= json_encode($articles) ?>;
function openBlogArticle(i) {
    const a = articles[i];
    document.getElementById('blogModalTag').textContent = a.tag;
    document.getElementById('blogModalTitle').textContent = a.title;
    document.getElementById('blogModalMeta').innerHTML = `<span><i class="far fa-calendar"></i> ${a.date}</span><span><i class="far fa-clock"></i> ${a.read} read</span>`;
    document.getElementById('blogModalContent').innerHTML = `<p>${a.excerpt}</p><p style="margin-top:16px;">This is a preview. Full article content will be available soon. Stay tuned for detailed health guides and medical awareness content on HealthDial.</p>`;
    document.getElementById('blogModal').classList.add('active');
}
</script>

<?php require_once 'includes/footer.php'; ?>
