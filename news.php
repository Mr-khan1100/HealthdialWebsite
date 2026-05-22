<?php
$currentPage = 'news';
$pageTitle = 'News Porta | Hospitals Near You | Trusted  Medical Stores In Faridabad | Health Dial';
$pageDesc = 'Healthdial’s focus on accessibility, verified healthcare providers, healthcare awareness, and user convenience positions it as one of the promising healthcare management portals in India today.';
require_once 'includes/icons.php';
require_once 'includes/db.php';
require_once 'includes/header.php';

// Fetch news directly from database
$newsData = [];
$newsError = false;

try {
    $conn = getDbConnection();

    if (!$conn) {
        $newsError = true;
    } else {
        $sql = "SELECT id, title, short_description AS shortDescription, full_content AS fullContent, image, publish_date AS date FROM news ORDER BY publish_date DESC";
        $result = $conn->query($sql);

        if ($result) {
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    if (!empty($row['image'])) {
                        $row['image'] = NEWS_IMAGE_BASE . $row['image'];
                    }
                    $row['readTime'] = "5 min read";
                    $newsData[] = $row;
                }
            }
        } else {
            $newsError = true;
        }
        $conn->close();
    }
} catch (Exception $e) {
    $newsError = true;
}
?>

<section class="section" style="padding-top: 140px; background: linear-gradient(135deg, #f0f7ff 0%, #f0faf0 100%);">
    <div class="container">
        <div class="section-header">
            <span class="section-label">
                <?= icon('news') ?> Latest News
            </span>
            <h2 class="section-title">Health <span class="gradient-text">News & Updates</span></h2>
            <p class="section-subtitle">Stay informed with the latest health news and medical updates from India.</p>
        </div>

        <?php if ($newsData && count($newsData) > 0): ?>
            <div class="news-grid" id="news-grid">
                <?php foreach ($newsData as $i => $article):
                    // Fix image URL: http → https
                    $imageUrl = isset($article['image']) ? str_replace('http://', 'https://', $article['image']) : '';
                    $safeArticle = $article;
                    $safeArticle['image'] = $imageUrl;
                    $jsonData = htmlspecialchars(json_encode($safeArticle), ENT_QUOTES, 'UTF-8');
                    ?>
                    <article class="news-card reveal delay-<?= ($i % 3) + 1 ?>" data-news='<?= $jsonData ?>'>
                        <?php if ($imageUrl): ?>
                            <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($article['title'] ?? '') ?>"
                                class="news-card-image" loading="lazy"
                                onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22200%22><rect fill=%22%23f4f7fc%22 width=%22400%22 height=%22200%22/><text fill=%22%235a6b80%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 font-size=%2216%22>HealthDial News</text></svg>'" />
                        <?php else: ?>
                            <div class="news-card-image"
                                style="display:flex;align-items:center;justify-content:center;background:var(--bg-alt);color:var(--text-muted);">
                                <?= icon('news') ?>
                            </div>
                        <?php endif; ?>
                        <div class="news-card-body">
                            <div class="news-card-meta">
                                <span class="news-card-date">
                                    <?= icon('clock') ?><span>
                                        <?= htmlspecialchars($article['date'] ?? '') ?>
                                    </span>
                                </span>
                                <span class="news-card-readtime">
                                    <?= icon('news') ?><span>
                                        <?= htmlspecialchars($article['readTime'] ?? '') ?>
                                    </span>
                                </span>
                            </div>
                            <h3 class="news-card-title">
                                <?= htmlspecialchars($article['title'] ?? '') ?>
                            </h3>
                            <p class="news-card-desc">
                                <?= htmlspecialchars($article['shortDescription'] ?? '') ?>
                            </p>
                            <button class="news-read-more" onclick="openNewsModal(this)">Read Full Article
                                <?= icon('arrowRight') ?>
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php elseif ($newsError): ?>
            <div class="card" style="text-align: center; padding: 48px; max-width: 500px; margin: 0 auto;">
                <div class="card-icon danger" style="margin: 0 auto 16px;">
                    <?= icon('alert') ?>
                </div>
                <h3 class="card-title">Unable to load news</h3>
                <p class="card-text">Please check back later for the latest health news.</p>
                <a href="news.php" class="btn btn-secondary" style="margin-top: 16px;">
                    <?= icon('arrowRight') ?> Try Again
                </a>
            </div>
        <?php else: ?>
            <div class="card" style="text-align: center; padding: 48px; max-width: 500px; margin: 0 auto;">
                <div class="card-icon blue" style="margin: 0 auto 16px;">
                    <?= icon('news') ?>
                </div>
                <h3 class="card-title">No news articles yet</h3>
                <p class="card-text">Stay tuned for the latest health news and updates.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- News Modal -->
<div class="news-modal-overlay" id="news-modal-overlay">
    <div class="news-modal">
        <button class="news-modal-close" onclick="closeNewsModal()">
            <?= icon('cross') ?>
        </button>
        <img id="modal-image" class="news-modal-image" src="" alt="" />
        <div class="news-modal-body">
            <div
                style="display:flex;align-items:center;gap:16px;margin-bottom:12px;color:var(--text-muted);font-size:var(--fs-sm);">
                <span id="modal-date"></span>
                <span id="modal-readtime"></span>
            </div>
            <h2 class="news-modal-title" id="modal-title"></h2>
            <p class="news-modal-content" id="modal-content"></p>
        </div>
    </div>
</div>

<!-- JavaScript to handle Modal Open/Close functionality -->
<script>
    // Vanilla JS — no jQuery needed
    function openNewsModal(btn) {
        try {
            var card = btn.closest('article');
            var data = JSON.parse(card.getAttribute('data-news'));
            var overlay = document.getElementById('news-modal-overlay');
            var modalImg = document.getElementById('modal-image');

            modalImg.src = data.image || '';
            modalImg.style.display = data.image ? 'block' : 'none';

            document.getElementById('modal-date').textContent = data.date || '';
            document.getElementById('modal-readtime').textContent = data.readTime || '';
            document.getElementById('modal-title').textContent = data.title || '';
            document.getElementById('modal-content').innerHTML = data.fullContent || '';

            overlay.classList.add('active');
            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        } catch (error) {
            console.error('Error parsing news data:', error);
        }
    }

    function closeNewsModal() {
        var overlay = document.getElementById('news-modal-overlay');
        if (!overlay) return;
        overlay.classList.remove('active');
        overlay.style.display = 'none';
        document.body.style.overflow = '';
    }

    // Click outside to close
    document.addEventListener('DOMContentLoaded', function() {
        var overlay = document.getElementById('news-modal-overlay');
        if (overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) closeNewsModal();
            });
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeNewsModal();
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>