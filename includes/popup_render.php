<?php
/**
 * Frontend renderer for CUSTOM popups (the built-in QR/Promote popups are wired
 * inline by listing-detail.php because they have page-specific behaviour).
 *
 * Usage — set the context, then include this file once near the end of a page:
 *   $hdPopupPage = 'listing_detail';            // or 'home'
 *   $hdPopupCtx  = ['is_owner' => $isOwner];    // logged_in is auto-filled
 *   require __DIR__ . '/includes/popup_render.php';
 *
 * Renders each eligible popup as a hidden modal + a small controller that shows
 * them one at a time, each after its own delay, once per browser session.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/user_auth.php';
require_once __DIR__ . '/popups.php';

$hdPopupPage  = $hdPopupPage ?? 'home';
// Scope distinguishes "once per listing" — set to the listing id on detail pages.
$hdPopupScope = isset($hdPopupScope) ? (string) $hdPopupScope : $hdPopupPage;
$hdPopupCtx  = isset($hdPopupCtx) && is_array($hdPopupCtx) ? $hdPopupCtx : [];
if (!array_key_exists('logged_in', $hdPopupCtx)) {
    $hdPopupCtx['logged_in'] = hd_is_logged_in();
}
if (!array_key_exists('is_owner', $hdPopupCtx)) {
    $hdPopupCtx['is_owner'] = false;
}

$hdConnForPopups = $conn ?? getDbConnection();
if (!$hdConnForPopups) {
    return;
}

$hdCustomPopups = hd_popups_visible_custom($hdConnForPopups, $hdPopupPage, $hdPopupCtx);
if (empty($hdCustomPopups)) {
    return;
}
?>
<?php foreach ($hdCustomPopups as $cp): ?>
<div class="hdpop-overlay" id="hdpop-<?= (int) $cp['id'] ?>" style="display:none;" aria-modal="true" role="dialog"
    aria-label="<?= htmlspecialchars($cp['title'] ?: 'Notice') ?>">
    <div class="hdpop-card">
        <button type="button" class="hdpop-close" aria-label="Close">&times;</button>
        <?php if (!empty($cp['image_path'])): ?>
        <div class="hdpop-img"><img src="<?= htmlspecialchars($cp['image_path']) ?>" alt=""></div>
        <?php endif; ?>
        <div class="hdpop-body">
            <?php if (!empty($cp['subtitle'])): ?>
            <div class="hdpop-sub"><?= htmlspecialchars($cp['subtitle']) ?></div>
            <?php endif; ?>
            <?php if (!empty($cp['title'])): ?>
            <h3 class="hdpop-title"><?= htmlspecialchars($cp['title']) ?></h3>
            <?php endif; ?>
            <?php if (!empty($cp['body'])): ?>
            <p class="hdpop-text"><?= nl2br(htmlspecialchars($cp['body'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($cp['cta_text']) && !empty($cp['cta_url'])): ?>
            <a class="hdpop-cta" href="<?= htmlspecialchars($cp['cta_url']) ?>"><?= htmlspecialchars($cp['cta_text']) ?></a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<style>
.hdpop-overlay { position: fixed; inset: 0; z-index: 9997; display: flex; align-items: center; justify-content: center; background: rgba(0, 0, 0, .72); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); padding: 16px; animation: hdpopIn .3s ease both; }
.hdpop-overlay.hdpop-hiding { animation: hdpopOut .25s ease both; }
@keyframes hdpopIn { from { opacity: 0 } to { opacity: 1 } }
@keyframes hdpopOut { from { opacity: 1 } to { opacity: 0 } }
.hdpop-card { position: relative; width: 100%; max-width: 460px; border-radius: 20px; overflow: hidden; background: #0c1633; color: #f1f5f9; border: 1px solid rgba(255, 255, 255, .1); box-shadow: 0 32px 80px rgba(0, 0, 0, .55); animation: hdpopUp .35s cubic-bezier(.22, 1, .36, 1) both; }
[data-theme="light"] .hdpop-card { background: #fff; color: #0f172a; }
@keyframes hdpopUp { from { transform: translateY(36px); opacity: 0 } to { transform: translateY(0); opacity: 1 } }
.hdpop-close { position: absolute; top: 12px; right: 12px; z-index: 2; width: 34px; height: 34px; border-radius: 50%; border: none; background: rgba(0, 0, 0, .35); color: #fff; font-size: 20px; line-height: 1; cursor: pointer; }
.hdpop-img img { width: 100%; max-height: 220px; object-fit: cover; display: block; }
.hdpop-body { padding: 22px 24px 26px; text-align: center; }
.hdpop-sub { font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #60a5fa; margin-bottom: 6px; }
.hdpop-title { font-size: 1.35rem; font-weight: 800; margin: 0 0 8px; }
.hdpop-text { font-size: .92rem; line-height: 1.55; color: var(--text-secondary, #94a3b8); margin: 0 0 18px; }
.hdpop-cta { display: inline-block; padding: 12px 26px; border-radius: 12px; font-weight: 700; text-decoration: none; color: #fff; background: linear-gradient(135deg, #2563eb, #10b981); box-shadow: 0 8px 22px rgba(37, 99, 235, .35); }
.hdpop-cta:hover { filter: brightness(1.07); }
</style>

<script>
(function () {
    var SCOPE = <?= json_encode((string) $hdPopupScope) ?>;
    var pops = [
        <?php foreach ($hdCustomPopups as $cp): ?>
        { id: <?= (int) $cp['id'] ?>, delay: <?= max(0, (int) $cp['delay_seconds']) * 1000 ?>, freq: <?= json_encode($cp['frequency'] ?? 'session') ?> },
        <?php endforeach; ?>
    ];

    // Storage key per popup. "listing" frequency is scoped so it re-shows on each listing.
    function key(p) { return 'hdpop_seen_' + p.id + (p.freq === 'listing' ? '_' + SCOPE : ''); }
    function seen(p) {
        if (p.freq === 'always') return false;
        try {
            if (p.freq === 'daily') {
                var ts = parseInt(localStorage.getItem(key(p)) || '0', 10);
                return (Date.now() - ts) < 86400000;
            }
            return !!sessionStorage.getItem(key(p)); // session + listing
        } catch (e) { return false; }
    }
    function mark(p) {
        if (p.freq === 'always') return;
        try {
            if (p.freq === 'daily') localStorage.setItem(key(p), String(Date.now()));
            else sessionStorage.setItem(key(p), '1');
        } catch (e) {}
    }
    function hide(el, after) {
        el.classList.add('hdpop-hiding');
        setTimeout(function () { el.style.display = 'none'; el.classList.remove('hdpop-hiding'); if (after) after(); }, 250);
    }

    var queue = pops.filter(function (p) { return !seen(p) && document.getElementById('hdpop-' + p.id); });

    function showNext() {
        if (!queue.length) return;
        var p = queue.shift();
        var el = document.getElementById('hdpop-' + p.id);
        if (!el) { showNext(); return; }
        setTimeout(function () { el.style.display = 'flex'; mark(p); }, p.delay);
    }

    pops.forEach(function (p) {
        var el = document.getElementById('hdpop-' + p.id);
        if (!el) return;
        function close() { hide(el, function () { showNext(); }); }
        var btn = el.querySelector('.hdpop-close');
        if (btn) btn.addEventListener('click', close);
        el.addEventListener('click', function (e) { if (e.target === el) close(); });
        var cta = el.querySelector('.hdpop-cta');
        if (cta) cta.addEventListener('click', function () { mark(p); });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var open = pops.map(function (p) { return document.getElementById('hdpop-' + p.id); })
            .filter(function (el) { return el && el.style.display === 'flex'; });
        if (open.length) hide(open[0], function () { showNext(); });
    });

    showNext();
})();
</script>
