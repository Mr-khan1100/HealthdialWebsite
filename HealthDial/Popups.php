<?php
require_once 'connection.inc.php';
requireLogin();
requireAccess('popups');
require_once __DIR__ . '/../includes/popups.php';

hd_popups_ensure_schema($conn);

$uploadDir = __DIR__ . '/uploads/popups/';
$webBase   = '/HealthDial/uploads/popups/';

/* ---------------- Save ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_popup']) && !isset($_POST['delete_popup'])) {
    $id        = intval($_POST['id'] ?? 0);
    $isBuiltin = intval($_POST['is_builtin'] ?? 0);

    $title    = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $body     = trim($_POST['body'] ?? '');
    $ctaText  = trim($_POST['cta_text'] ?? '');
    $ctaUrl   = $isBuiltin ? '' : trim($_POST['cta_url'] ?? '');
    $enabled  = isset($_POST['enabled']) ? 1 : 0;
    $audience = in_array(($_POST['audience'] ?? ''), ['owner', 'all', 'logged_in'], true) ? $_POST['audience'] : 'owner';
    $delay    = max(0, intval($_POST['delay_seconds'] ?? 3));
    $priority = intval($_POST['priority'] ?? 0);
    $frequency = in_array(($_POST['frequency'] ?? ''), ['session', 'daily', 'listing', 'always'], true) ? $_POST['frequency'] : 'session';

    // Pages (checkbox group) → comma list. 'all' wins.
    $pagesIn = (array) ($_POST['pages'] ?? []);
    $allowedPages = ['listing_detail', 'home', 'all'];
    $pagesIn = array_values(array_intersect($pagesIn, $allowedPages));
    if (in_array('all', $pagesIn, true)) {
        $pages = 'all';
    } else {
        $pages = $pagesIn ? implode(',', $pagesIn) : 'listing_detail';
    }

    // Optional schedule (datetime-local → MySQL DATETIME).
    $startAt = trim($_POST['start_at'] ?? '');
    $endAt   = trim($_POST['end_at'] ?? '');
    $startAt = $startAt !== '' ? str_replace('T', ' ', $startAt) . ':00' : null;
    $endAt   = $endAt   !== '' ? str_replace('T', ' ', $endAt) . ':00'   : null;

    // Image (custom only).
    $imagePath = trim($_POST['existing_image'] ?? '');
    if (!$isBuiltin && !empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
        $info = @getimagesize($_FILES['image']['tmp_name']);
        $map  = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
        if ($info && isset($map[$info[2]]) && $_FILES['image']['size'] <= 5 * 1024 * 1024) {
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $fname = 'popup_' . time() . '_' . mt_rand(1000, 9999) . '.' . $map[$info[2]];
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fname)) {
                $imagePath = $webBase . $fname;
            }
        } else {
            $_SESSION['error'] = 'Image must be JPG/PNG/WebP/GIF under 5MB.';
            header('Location: Popups.php');
            exit();
        }
    }

    if ($id > 0) {
        // Built-ins keep their image/cta_url null; everything else editable.
        if ($isBuiltin) {
            $stmt = $conn->prepare("UPDATE popups SET title=?, subtitle=?, body=?, cta_text=?, enabled=?, audience=?, pages=?, delay_seconds=?, priority=?, start_at=?, end_at=?, updated_at=NOW() WHERE id=? AND is_builtin=1");
            $stmt->bind_param('ssssisssissi', $title, $subtitle, $body, $ctaText, $enabled, $audience, $pages, $delay, $priority, $startAt, $endAt, $id);
        } else {
            $stmt = $conn->prepare("UPDATE popups SET title=?, subtitle=?, body=?, image_path=?, cta_text=?, cta_url=?, enabled=?, audience=?, pages=?, delay_seconds=?, priority=?, frequency=?, start_at=?, end_at=?, updated_at=NOW() WHERE id=? AND is_builtin=0");
            $stmt->bind_param(str_repeat('s', 15), $title, $subtitle, $body, $imagePath, $ctaText, $ctaUrl, $enabled, $audience, $pages, $delay, $priority, $frequency, $startAt, $endAt, $id);
        }
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = 'Popup updated.';
    } else {
        // New custom popup.
        $stmt = $conn->prepare("INSERT INTO popups (is_builtin, title, subtitle, body, image_path, cta_text, cta_url, enabled, audience, pages, delay_seconds, priority, frequency, start_at, end_at) VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(str_repeat('s', 14), $title, $subtitle, $body, $imagePath, $ctaText, $ctaUrl, $enabled, $audience, $pages, $delay, $priority, $frequency, $startAt, $endAt);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = 'Custom popup created.';
    }

    header('Location: Popups.php');
    exit();
}

/* ---------------- Delete (custom only) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_popup'])) {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM popups WHERE id = ? AND is_builtin = 0");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = 'Popup deleted.';
    }
    header('Location: Popups.php');
    exit();
}

$popups = hd_popups_all($conn);

/** Render one editable popup card. */
function hd_render_popup_card(array $p, bool $expanded = false): void
{
    $isBuiltin = (int) $p['is_builtin'] === 1;
    $pages     = array_map('trim', explode(',', (string) ($p['pages'] ?? '')));
    $pageHas   = function ($k) use ($pages) { return in_array($k, $pages, true) ? 'checked' : ''; };
    $aud       = $p['audience'] ?? 'owner';
    $sel       = function ($v) use ($aud) { return $aud === $v ? 'selected' : ''; };
    $freq      = $p['frequency'] ?? 'session';
    $fsel      = function ($v) use ($freq) { return $freq === $v ? 'selected' : ''; };
    $startVal  = !empty($p['start_at']) ? date('Y-m-d\TH:i', strtotime($p['start_at'])) : '';
    $endVal    = !empty($p['end_at'])   ? date('Y-m-d\TH:i', strtotime($p['end_at']))   : '';
    $on        = !empty($p['enabled']);

    // Human-readable summary line for the collapsed header.
    $audLabels  = ['owner' => 'Owner only', 'all' => 'Everyone', 'logged_in' => 'Logged-in'];
    $pageLabels = ['listing_detail' => 'Listing', 'home' => 'Home', 'all' => 'All pages'];
    $pageSummary = implode(', ', array_map(function ($pg) use ($pageLabels) {
        return $pageLabels[$pg] ?? $pg;
    }, array_filter($pages))) ?: '—';
    $metaBits = [$audLabels[$aud] ?? $aud, $pageSummary, 'after ' . (int) $p['delay_seconds'] . 's'];
    ?>
    <div class="pop-card <?= $on ? '' : 'is-off' ?> <?= $isBuiltin ? 'is-builtin' : '' ?> <?= $expanded ? 'pop-open' : '' ?>">
        <div class="pop-head" onclick="popToggle(this)">
            <div class="pop-head-main">
                <span class="pop-badge <?= $isBuiltin ? 'builtin' : 'custom' ?>"><?= $isBuiltin ? 'Built-in' : 'Custom' ?></span>
                <div class="pop-head-titles">
                    <strong><?= htmlspecialchars($p['title'] ?: ($p['popup_key'] ?: 'Untitled popup')) ?></strong>
                    <span class="pop-meta"><?= htmlspecialchars(implode(' · ', $metaBits)) ?></span>
                </div>
            </div>
            <div class="pop-head-side">
                <span class="pop-status <?= $on ? 'on' : 'off' ?>"><?= $on ? 'On' : 'Off' ?></span>
                <i class="fas fa-chevron-down pop-chev"></i>
            </div>
        </div>

        <div class="pop-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="save_popup" value="1">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="is_builtin" value="<?= $isBuiltin ? 1 : 0 ?>">
                <input type="hidden" name="existing_image" value="<?= htmlspecialchars($p['image_path'] ?? '') ?>">

                <!-- Enabled -->
                <div class="pop-row-toggle">
                    <label class="pop-switch">
                        <input type="checkbox" name="enabled" value="1" <?= $on ? 'checked' : '' ?>>
                        <span class="pop-slider"></span>
                    </label>
                    <div>
                        <strong>Enabled</strong>
                        <small class="pop-muted">Turn this popup on or off.</small>
                    </div>
                </div>

                <!-- Content -->
                <div class="pop-section">
                    <div class="pop-section-title"><i class="fas fa-align-left"></i> Content</div>
                    <div class="pop-grid">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($p['title'] ?? '') ?>" maxlength="255" placeholder="Popup heading">
                        </div>
                        <div class="form-group">
                            <label>Subtitle <span class="pop-opt">optional</span></label>
                            <input type="text" name="subtitle" value="<?= htmlspecialchars($p['subtitle'] ?? '') ?>" maxlength="255" placeholder="Small line above the title">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Body text</label>
                        <textarea name="body" rows="3" maxlength="1000" placeholder="The main message shown to the visitor."><?= htmlspecialchars($p['body'] ?? '') ?></textarea>
                    </div>
                    <div class="pop-grid">
                        <div class="form-group">
                            <label>Button text</label>
                            <input type="text" name="cta_text" value="<?= htmlspecialchars($p['cta_text'] ?? '') ?>" maxlength="100" placeholder="e.g. Learn more">
                        </div>
                        <?php if (!$isBuiltin): ?>
                        <div class="form-group">
                            <label>Button link</label>
                            <input type="text" name="cta_url" value="<?= htmlspecialchars($p['cta_url'] ?? '') ?>" placeholder="https://…" maxlength="500">
                        </div>
                        <?php else: ?>
                        <div class="form-group">
                            <label>Button action</label>
                            <input type="text" value="<?= $p['popup_key'] === 'qr_upsell' ? 'Unlock QR (built-in)' : 'Go to Promote (built-in)' ?>" disabled>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!$isBuiltin): ?>
                    <div class="pop-grid pop-image-row">
                        <div class="form-group">
                            <label>Image <span class="pop-opt">optional</span></label>
                            <input type="file" name="image" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label>Current image</label>
                            <?php if (!empty($p['image_path'])): ?>
                            <img src="<?= htmlspecialchars($p['image_path']) ?>" alt="" class="pop-thumb">
                            <?php else: ?><span class="pop-muted">No image uploaded</span><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Targeting -->
                <div class="pop-section">
                    <div class="pop-section-title"><i class="fas fa-bullseye"></i> Who &amp; where</div>
                    <div class="pop-grid <?= $isBuiltin ? '' : 'pop-grid-2' ?>">
                        <div class="form-group">
                            <label>Audience</label>
                            <select name="audience">
                                <option value="owner" <?= $sel('owner') ?>>Listing owner only</option>
                                <option value="all" <?= $sel('all') ?>>All visitors</option>
                                <option value="logged_in" <?= $sel('logged_in') ?>>Logged-in users</option>
                            </select>
                        </div>
                        <?php if (!$isBuiltin): ?>
                        <div class="form-group">
                            <label>Show frequency</label>
                            <select name="frequency">
                                <option value="session" <?= $fsel('session') ?>>Once per session</option>
                                <option value="daily" <?= $fsel('daily') ?>>Once per day</option>
                                <option value="listing" <?= $fsel('listing') ?>>Once per listing</option>
                                <option value="always" <?= $fsel('always') ?>>Every visit</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Show on pages</label>
                        <div class="pop-checks">
                            <label><input type="checkbox" name="pages[]" value="listing_detail" <?= $pageHas('listing_detail') ?>> Listing detail</label>
                            <label><input type="checkbox" name="pages[]" value="home" <?= $pageHas('home') ?>> Home</label>
                            <label><input type="checkbox" name="pages[]" value="all" <?= $pageHas('all') ?>> All pages</label>
                        </div>
                        <?php if ($isBuiltin): ?>
                        <small class="pop-muted">Built-in popups only work on the listing detail page.</small>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Timing -->
                <div class="pop-section">
                    <div class="pop-section-title"><i class="fas fa-clock"></i> Timing</div>
                    <div class="pop-grid pop-grid-2">
                        <div class="form-group">
                            <label>Show delay <span class="pop-opt">seconds</span></label>
                            <input type="number" name="delay_seconds" min="0" value="<?= (int) $p['delay_seconds'] ?>">
                        </div>
                        <div class="form-group">
                            <label>Order <span class="pop-opt">lower shows first</span></label>
                            <input type="number" name="priority" value="<?= (int) $p['priority'] ?>">
                        </div>
                    </div>
                    <div class="pop-grid">
                        <div class="form-group">
                            <label>Start <span class="pop-opt">optional</span></label>
                            <input type="datetime-local" name="start_at" value="<?= htmlspecialchars($startVal) ?>">
                        </div>
                        <div class="form-group">
                            <label>End <span class="pop-opt">optional</span></label>
                            <input type="datetime-local" name="end_at" value="<?= htmlspecialchars($endVal) ?>">
                        </div>
                    </div>
                </div>

                <div class="pop-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save changes</button>
                    <?php if (!$isBuiltin): ?>
                    <button type="submit" name="delete_popup" value="1" class="btn btn-danger"
                        onclick="return confirm('Delete this popup?');"><i class="fas fa-trash"></i> Delete</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Popups — HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    .pop-card { background: var(--card-bg, #fff); border: 1px solid rgba(0, 0, 0, .09); border-radius: 14px; margin-bottom: 14px; overflow: hidden; transition: box-shadow .15s; }
    .pop-card:hover { box-shadow: 0 4px 18px rgba(0, 0, 0, .06); }
    .pop-card.is-builtin { border-left: 3px solid #2563eb; }
    .pop-card.is-off { opacity: .72; }

    /* Header (click to expand) */
    .pop-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 18px; cursor: pointer; user-select: none; }
    .pop-head-main { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .pop-head-titles { display: flex; flex-direction: column; min-width: 0; }
    .pop-head-titles strong { font-size: 15px; line-height: 1.3; }
    .pop-meta { font-size: 12px; color: var(--text-muted, #94a3b8); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pop-head-side { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
    .pop-badge { font-size: 10px; font-weight: 700; padding: 3px 9px; border-radius: 99px; letter-spacing: .02em; white-space: nowrap; }
    .pop-badge.builtin { background: rgba(43, 125, 233, .12); color: #2563eb; }
    .pop-badge.custom { background: rgba(34, 197, 94, .15); color: #16a34a; }
    .pop-status { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 99px; }
    .pop-status.on { background: rgba(34, 197, 94, .15); color: #16a34a; }
    .pop-status.off { background: rgba(100, 116, 139, .15); color: #64748b; }
    .pop-chev { color: #94a3b8; transition: transform .2s; font-size: 13px; }
    .pop-card.pop-open .pop-chev { transform: rotate(180deg); }

    /* Body (collapsed by default) */
    .pop-body { display: none; padding: 4px 18px 18px; border-top: 1px solid rgba(0, 0, 0, .06); }
    .pop-card.pop-open .pop-body { display: block; }

    /* Enabled switch row */
    .pop-row-toggle { display: flex; align-items: center; gap: 12px; padding: 14px 0; }
    .pop-row-toggle strong { display: block; font-size: 13px; }
    .pop-switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
    .pop-switch input { opacity: 0; width: 0; height: 0; }
    .pop-slider { position: absolute; cursor: pointer; inset: 0; background: #cbd5e1; border-radius: 99px; transition: .2s; }
    .pop-slider:before { content: ""; position: absolute; height: 18px; width: 18px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: .2s; }
    .pop-switch input:checked + .pop-slider { background: #22c55e; }
    .pop-switch input:checked + .pop-slider:before { transform: translateX(20px); }

    /* Sections */
    .pop-section { padding: 16px 0; border-top: 1px dashed rgba(0, 0, 0, .08); }
    .pop-section-title { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin-bottom: 14px; }
    .pop-section-title i { color: #2563eb; }
    .pop-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .pop-grid-2 { grid-template-columns: 1fr 1fr; }
    .pop-image-row { align-items: center; }
    .pop-body .form-group { margin-bottom: 12px; }
    .pop-body .form-group label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 5px; }
    .pop-body .form-group input[type=text], .pop-body .form-group input[type=number],
    .pop-body .form-group input[type=datetime-local], .pop-body .form-group select,
    .pop-body .form-group textarea { width: 100%; padding: 9px 11px; border: 1px solid #d7dde6; border-radius: 8px; font-size: 13px; font-family: inherit; background: #fff; }
    .pop-body .form-group input:focus, .pop-body .form-group select:focus, .pop-body .form-group textarea:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, .12); }
    .pop-opt { font-weight: 400; color: #94a3b8; font-size: 11px; }
    .pop-checks { display: flex; gap: 18px; flex-wrap: wrap; font-size: 13px; padding-top: 4px; }
    .pop-checks label { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-weight: 500; }
    .pop-actions { display: flex; gap: 10px; padding-top: 16px; border-top: 1px solid rgba(0, 0, 0, .06); margin-top: 4px; }
    .pop-thumb { max-height: 60px; border-radius: 8px; border: 1px solid #e2e8f0; }
    .pop-muted { color: var(--text-muted, #94a3b8); font-size: 12px; }
    .btn-danger { background: #ef4444; color: #fff; }
    .pop-add-wrap { background: rgba(37, 99, 235, .04); border: 1px dashed rgba(37, 99, 235, .35); border-radius: 14px; padding: 4px; margin-top: 8px; }
    .pop-add-wrap .pop-card { margin-bottom: 0; border-style: solid; }
    .pop-list-hint { font-size: 12px; color: #94a3b8; margin: 4px 0 14px; display: flex; align-items: center; gap: 6px; }
    .pop-add-heading { margin: 28px 0 10px; font-size: 16px; display: flex; align-items: center; gap: 8px; }
    .pop-add-heading i { color: #2563eb; }
    @media (max-width: 720px) { .pop-grid, .pop-grid-2 { grid-template-columns: 1fr; } .pop-meta { display: none; } }
    </style>
</head>

<body>
    <div class="admin-wrapper">
        <?php include 'sidebar.php'; ?>

        <div class="admin-main">
            <?php include 'header.php'; ?>

            <div class="admin-content">
                <div class="page-header">
                    <h1 class="page-title">Popups</h1>
                    <p class="page-subtitle">Configure the popups that appear on the site — enable/disable, edit content
                        and timing, target an audience, choose pages, or create your own. Built-in popups (QR &amp;
                        Promote) can be turned off and replaced with custom ones.</p>
                </div>

                <?php if (!empty($_SESSION['success'])): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <div class="pop-list-hint"><i class="fas fa-hand-pointer"></i> Click any popup to expand and edit it.</div>

                <?php foreach ($popups as $p) hd_render_popup_card($p); ?>

                <h2 class="pop-add-heading"><i class="fas fa-plus-circle"></i> Add a custom popup</h2>
                <div class="pop-add-wrap">
                    <?php
                    hd_render_popup_card([
                        'id' => 0, 'is_builtin' => 0, 'popup_key' => null,
                        'title' => '', 'subtitle' => '', 'body' => '', 'image_path' => '',
                        'cta_text' => 'Learn more', 'cta_url' => '', 'enabled' => 1,
                        'audience' => 'all', 'pages' => 'listing_detail', 'delay_seconds' => 3,
                        'priority' => 5, 'frequency' => 'session', 'start_at' => null, 'end_at' => null,
                    ], true); // expanded
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    function popToggle(head) {
        head.closest('.pop-card').classList.toggle('pop-open');
    }
    </script>
</body>

</html>
