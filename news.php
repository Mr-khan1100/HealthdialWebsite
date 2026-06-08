<?php
$currentPage = 'news';
$pageTitle = 'Health News | HealthDial - Latest Health Updates from India';
$pageDesc = 'Stay informed with the latest health news and medical updates from Times of India, NDTV, Hindustan Times, and more.';
require_once 'includes/icons.php';
require_once 'includes/db.php';
require_once 'includes/rss_news.php';
require_once 'includes/header.php';

// ── Admin DB news ──────────────────────────────────────────────
$adminNews  = [];
$newsError  = false;

try {
    $conn = getDbConnection();
    if ($conn) {
        $sql    = "SELECT id, title, short_description AS shortDescription, full_content AS fullContent, image, publish_date AS date FROM news ORDER BY publish_date DESC";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if (!empty($row['image'])) {
                    $row['image'] = str_replace('http://', 'https://', NEWS_IMAGE_BASE . $row['image']);
                }
                $row['readTime']    = '5 min read';
                $row['source']      = 'HealthDial';
                $row['externalUrl'] = '';
                $adminNews[]        = $row;
            }
        }
        $conn->close();
    }
} catch (Exception $e) {
    $newsError = true;
}

// ── RSS / live news ────────────────────────────────────────────
$rssNews = [];
try {
    $rssNews = fetchHealthRssNews(6);
} catch (Exception $e) {
    // silently skip if RSS fails
}

// ── Merge: admin first (pinned), then RSS sorted newest-first ──
$allNews = [...$adminNews, ...$rssNews];
?>

<section class="section" style="padding-top: 140px;">
    <div class="container">
        <div class="section-header">
            <span class="section-label">
                <?= icon('news') ?> Latest News
            </span>
            <h2 class="section-title">Health <span class="gradient-text">News & Updates</span></h2>
            <p class="section-subtitle">Stay informed with the latest health news from India — curated from Times of India, NDTV, Hindustan Times and more.</p>
        </div>

        <?php if (!empty($allNews)): ?>
            <!-- Source filter tabs -->
            <div class="news-filter-tabs" id="newsFilterTabs">
                <button class="news-filter-btn active" data-source="all">All</button>
                <button class="news-filter-btn" data-source="HealthDial">HealthDial</button>
                <button class="news-filter-btn" data-source="Times of India">Times of India</button>
                <button class="news-filter-btn" data-source="NDTV">NDTV</button>
                <button class="news-filter-btn" data-source="Hindustan Times">Hindustan Times</button>
                <button class="news-filter-btn" data-source="The Hindu">The Hindu</button>
                <button class="news-filter-btn" data-source="India TV News">India TV News</button>
            </div>

            <div class="news-grid" id="news-grid">
                <?php foreach ($allNews as $i => $article):
                    $imageUrl   = isset($article['image']) ? str_replace('http://', 'https://', $article['image']) : '';
                    $safeData   = $article;
                    $safeData['image'] = $imageUrl;
                    // Remove internal timestamp key before encoding for JS
                    unset($safeData['_ts']);
                    $jsonData   = htmlspecialchars(json_encode($safeData, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                    $source     = htmlspecialchars($article['source'] ?? 'HealthDial');
                    $isExternal = !empty($article['externalUrl']);
                    $delay      = $i % 3 + 1;
                    ?>
                    <article class="news-card reveal delay-<?= $delay ?>"
                             data-news='<?= $jsonData ?>'
                             data-source="<?= $source ?>">
                        <?php if ($imageUrl): ?>
                            <img src="<?= htmlspecialchars($imageUrl) ?>"
                                 alt="<?= htmlspecialchars($article['title'] ?? '') ?>"
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
                                    <?= icon('clock') ?><span><?= htmlspecialchars($article['date'] ?? '') ?></span>
                                </span>
                                <span class="news-card-readtime">
                                    <?= icon('news') ?><span><?= htmlspecialchars($article['readTime'] ?? '') ?></span>
                                </span>
                                <span class="news-source-badge <?= $isExternal ? 'external' : 'internal' ?>">
                                    <?= $source ?>
                                </span>
                            </div>
                            <h3 class="news-card-title"><?= htmlspecialchars($article['title'] ?? '') ?></h3>
                            <p class="news-card-desc"><?= htmlspecialchars($article['shortDescription'] ?? '') ?></p>
                            <button class="news-read-more" onclick="openNewsModal(this)">
                                Read Full Article <?= icon('arrowRight') ?>
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

        <?php elseif ($newsError): ?>
            <div class="card" style="text-align:center;padding:48px;max-width:500px;margin:0 auto;">
                <div class="card-icon danger" style="margin:0 auto 16px;"><?= icon('alert') ?></div>
                <h3 class="card-title">Unable to load news</h3>
                <p class="card-text">Please check back later for the latest health news.</p>
                <a href="news.php" class="btn btn-secondary" style="margin-top:16px;"><?= icon('arrowRight') ?> Try Again</a>
            </div>
        <?php else: ?>
            <div class="card" style="text-align:center;padding:48px;max-width:500px;margin:0 auto;">
                <div class="card-icon blue" style="margin:0 auto 16px;"><?= icon('news') ?></div>
                <h3 class="card-title">No news articles yet</h3>
                <p class="card-text">Stay tuned for the latest health news and updates.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- News Modal -->
<div class="news-modal-overlay" id="news-modal-overlay">
    <div class="news-modal">
        <button class="news-modal-close" onclick="closeNewsModal()"><?= icon('cross') ?></button>
        <img id="modal-image" class="news-modal-image" src="" alt="" />
        <div class="news-modal-body">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px;color:var(--text-muted);font-size:var(--fs-sm);">
                <span id="modal-date"></span>
                <span id="modal-readtime"></span>
                <span id="modal-source-badge" class="news-source-badge external" style="display:none;"></span>
            </div>
            <h2 class="news-modal-title" id="modal-title"></h2>
            <p class="news-modal-content" id="modal-content"></p>
            <a id="modal-external-link" href="#" target="_blank" rel="noopener noreferrer"
               class="btn btn-primary"
               style="display:none;margin-top:20px;gap:8px;">
                <i class="fas fa-external-link-alt"></i> Read Full Article on <span id="modal-source-name"></span>
            </a>
        </div>
    </div>
</div>

<style>
/* ── Source badge ─────────────────────────────────────── */
.news-source-badge {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    padding: 2px 8px;
    border-radius: 20px;
    white-space: nowrap;
}
.news-source-badge.internal {
    background: rgba(16,185,129,.15);
    color: #10b981;
}
.news-source-badge.external {
    background: rgba(43,125,233,.15);
    color: var(--blue-light, #60a5fa);
}

/* ── Filter tabs ──────────────────────────────────────── */
.news-filter-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 28px;
}
.news-filter-btn {
    padding: 6px 16px;
    border-radius: 20px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-muted);
    font-size: var(--fs-sm);
    font-weight: 600;
    cursor: pointer;
    transition: all .2s;
}
.news-filter-btn:hover,
.news-filter-btn.active {
    background: var(--blue);
    color: #fff;
    border-color: var(--blue);
}

/* ── Hidden card (filter) ─────────────────────────────── */
.news-card.hidden { display: none; }
</style>

<script>
// ── Filter tabs ───────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.news-filter-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.news-filter-btn').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            var filter = btn.getAttribute('data-source');
            document.querySelectorAll('#news-grid .news-card').forEach(function (card) {
                if (filter === 'all' || card.getAttribute('data-source') === filter) {
                    card.classList.remove('hidden');
                } else {
                    card.classList.add('hidden');
                }
            });
        });
    });
});

// ── Modal ─────────────────────────────────────────────────────
function openNewsModal(btn) {
    try {
        var card    = btn.closest('article');
        var data    = JSON.parse(card.getAttribute('data-news'));
        var overlay = document.getElementById('news-modal-overlay');
        var modalImg = document.getElementById('modal-image');

        modalImg.src = data.image || '';
        modalImg.style.display = data.image ? 'block' : 'none';

        document.getElementById('modal-date').textContent     = data.date      || '';
        document.getElementById('modal-readtime').textContent = data.readTime  || '';
        document.getElementById('modal-title').textContent    = data.title     || '';
        document.getElementById('modal-content').innerHTML    = data.fullContent || '';

        // Source badge
        var sourceBadge = document.getElementById('modal-source-badge');
        if (data.source && data.source !== 'HealthDial') {
            sourceBadge.textContent  = data.source;
            sourceBadge.style.display = 'inline-block';
        } else {
            sourceBadge.style.display = 'none';
        }

        // External link button
        var extLink    = document.getElementById('modal-external-link');
        var sourceName = document.getElementById('modal-source-name');
        if (data.externalUrl) {
            extLink.href           = data.externalUrl;
            sourceName.textContent = data.source || 'Source';
            extLink.style.display  = 'inline-flex';
        } else {
            extLink.style.display  = 'none';
        }

        overlay.classList.add('active');
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    } catch (e) {
        console.error('Error opening news modal:', e);
    }
}

function closeNewsModal() {
    var overlay = document.getElementById('news-modal-overlay');
    if (!overlay) return;
    overlay.classList.remove('active');
    overlay.style.display = 'none';
    document.body.style.overflow = '';
}

document.addEventListener('DOMContentLoaded', function () {
    var overlay = document.getElementById('news-modal-overlay');
    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeNewsModal();
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeNewsModal();
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
