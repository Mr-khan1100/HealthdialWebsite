<?php require_once 'connection.inc.php'; requireLogin(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Banners - HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .wb-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin: 20px 0 0; }
        .wb-tab {
            padding: 8px 18px; border-radius: 10px; border: 1.5px solid #E8ECF0;
            background: #fff; font-size: 13px; font-weight: 600; color: #4B5563;
            cursor: pointer; transition: all .15s;
        }
        .wb-tab.active { background: #0782ca; color: #fff; border-color: #0782ca; }
        .wb-tab-panel { display: none; }
        .wb-tab-panel.active { display: block; }

        .wb-form-card { background: #fff; border-radius: 14px; padding: 24px; border: 1px solid #E8ECF0; margin-top: 20px; }
        .upload-area {
            border: 2px dashed #D1D5DB; border-radius: 14px; padding: 32px;
            text-align: center; cursor: pointer; transition: all .3s; background: #F9FAFB;
        }
        .upload-area:hover, .upload-area.dragover { border-color: #0782ca; background: #E8F4FD; }
        .upload-area i { font-size: 36px; color: #9CA3AF; margin-bottom: 10px; display: block; }
        .upload-area p { color: #6B7280; margin: 0; font-size: 14px; }
        .upload-area .sub { font-size: 11px; color: #9CA3AF; margin-top: 4px; }
        .preview-img { max-height: 160px; border-radius: 10px; margin-top: 12px; display: none; }

        .pos-toggle { display: flex; gap: 8px; margin-top: 4px; }
        .pos-btn {
            flex: 1; padding: 8px; border-radius: 8px; border: 1.5px solid #E8ECF0;
            background: #F9FAFB; font-size: 13px; font-weight: 600; color: #4B5563;
            cursor: pointer; text-align: center; transition: all .15s;
        }
        .pos-btn.selected { background: #0782ca; color: #fff; border-color: #0782ca; }
        #position-field { display: none; }

        .wb-banner-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px; margin-top: 20px;
        }
        .wb-banner-card { background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,.06); border: 1px solid #E8ECF0; }
        .wb-banner-card img { width: 100%; height: 160px; object-fit: cover; }
        .wb-banner-card-body { padding: 14px; }
        .wb-card-title { font-weight: 700; font-size: 14px; color: #1A1D26; margin-bottom: 4px; }
        .wb-card-meta { font-size: 11px; color: #9CA3AF; margin-bottom: 10px; }
        .wb-card-actions { display: flex; gap: 8px; }
        .banner-status { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .banner-status.active { background: #ECFDF5; color: #059669; }
        .banner-status.inactive { background: #FEF2F2; color: #DC2626; }
        .page-badge {
            display: inline-block; padding: 2px 8px; border-radius: 6px;
            font-size: 11px; font-weight: 700; background: #EFF6FF; color: #2563eb;
        }
        .pos-badge {
            display: inline-block; padding: 2px 8px; border-radius: 6px;
            font-size: 11px; font-weight: 700; background: #FFF7ED; color: #D97706;
        }
        .section-label-row {
            display: flex; align-items: center; gap: 10px;
            font-size: 13px; font-weight: 700; color: #4B5563; margin: 24px 0 4px;
        }
        .section-label-row::after { content: ''; flex: 1; height: 1px; background: #E8ECF0; }
        .empty-state { text-align: center; padding: 48px; color: #9CA3AF; grid-column: 1/-1; }
        .page-header-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .stat-pills { display: flex; gap: 12px; }
        .stat-pill { background: #fff; border: 1px solid #E8ECF0; border-radius: 10px; padding: 8px 16px; font-size: 13px; font-weight: 600; color: #4B5563; }
        .stat-pill span { color: #0782ca; font-weight: 700; }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="admin-main">
        <?php include 'header.php'; ?>
        <div class="admin-content">

        <?php
        // Stats
        $totalWb = 0; $activeWb = 0;
        $statsRes = $conn->query("SELECT COUNT(*) as total, SUM(status=1) as active FROM website_banners");
        if ($statsRes && $r = $statsRes->fetch_assoc()) { $totalWb = (int)$r['total']; $activeWb = (int)($r['active'] ?? 0); }

        // Fetch all banners grouped for display
        $allBanners = [];
        $bRes = $conn->query("SELECT * FROM website_banners ORDER BY page ASC, position ASC, sort_order ASC, id DESC");
        if ($bRes) { while ($b = $bRes->fetch_assoc()) $allBanners[] = $b; }

        $pages = ['home' => 'Home', 'explore' => 'Explore', 'promotion' => 'Promotion', 'add_listing' => 'Add Listing'];
        $pageIcons = ['home' => 'fa-house', 'explore' => 'fa-magnifying-glass', 'promotion' => 'fa-bolt', 'add_listing' => 'fa-circle-plus'];
        ?>

        <div class="page-header-row">
            <div>
                <h2 style="margin:0; font-weight:800; color:#1A1D26;">
                    <i class="fas fa-globe" style="color:#0782ca;"></i> Website Banners
                </h2>
                <p style="color:#9CA3AF; margin-top:4px;">Manage banner images shown on website pages</p>
            </div>
            <div class="stat-pills">
                <div class="stat-pill">Total: <span><?= $totalWb ?></span></div>
                <div class="stat-pill">Active: <span><?= $activeWb ?></span></div>
            </div>
        </div>

        <!-- Page Tabs -->
        <div class="wb-tabs">
            <?php foreach ($pages as $key => $label): ?>
            <button class="wb-tab <?= $key === 'home' ? 'active' : '' ?>"
                    onclick="switchTab('<?= $key ?>')">
                <i class="fas <?= $pageIcons[$key] ?>"></i> <?= $label ?>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Per-page panels -->
        <?php foreach ($pages as $pageKey => $pageLabel): ?>
        <div class="wb-tab-panel <?= $pageKey === 'home' ? 'active' : '' ?>" id="panel-<?= $pageKey ?>">

            <!-- Upload Form -->
            <div class="wb-form-card">
                <h5 style="font-weight:700; margin-bottom:16px;">
                    <i class="fas fa-cloud-upload-alt" style="color:#0782ca;"></i>
                    Add Banner — <?= $pageLabel ?>
                </h5>
                <form class="wb-upload-form" enctype="multipart/form-data" data-page="<?= $pageKey ?>">
                    <input type="hidden" name="page" value="<?= $pageKey ?>">

                    <?php if ($pageKey === 'home'): ?>
                    <div style="margin-bottom:16px;">
                        <label style="font-size:13px; font-weight:600; color:#4B5563; display:block; margin-bottom:6px;">
                            Banner Position
                        </label>
                        <div class="pos-toggle">
                            <div class="pos-btn selected" data-pos="top" onclick="selectPos(this,'<?= $pageKey ?>')">
                                <i class="fas fa-arrow-up"></i> Top Banner
                            </div>
                            <div class="pos-btn" data-pos="bottom" onclick="selectPos(this,'<?= $pageKey ?>')">
                                <i class="fas fa-arrow-down"></i> Bottom Banner
                            </div>
                        </div>
                        <input type="hidden" name="position" class="pos-input" value="top">
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="position" value="top">
                    <?php endif; ?>

                    <!-- Upload area -->
                    <div class="upload-area" onclick="this.nextElementSibling.click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Click or drag &amp; drop image here</p>
                        <p class="sub">JPG, PNG, WebP — Max 5MB — Recommended: 1200×400px</p>
                        <img class="preview-img" />
                    </div>
                    <input type="file" name="image" accept="image/*" style="display:none"
                           onchange="previewFile(this)">

                    <div style="display:flex; gap:12px; margin-top:14px; flex-wrap:wrap;">
                        <div style="flex:2; min-width:180px;">
                            <label style="font-size:13px; font-weight:600; color:#4B5563;">Title <span style="color:#9CA3AF;">(optional)</span></label>
                            <input type="text" name="title" class="form-input" placeholder="e.g. Summer Health Offer"
                                   style="border-radius:10px; width:100%; margin-top:4px;">
                        </div>
                        <div style="flex:2; min-width:180px;">
                            <label style="font-size:13px; font-weight:600; color:#4B5563;">Image URL <span style="color:#9CA3AF;">(if no upload)</span></label>
                            <input type="text" name="image_url" class="form-input" placeholder="https://…"
                                   style="border-radius:10px; width:100%; margin-top:4px;">
                        </div>
                        <div style="flex:2; min-width:180px;">
                            <label style="font-size:13px; font-weight:600; color:#4B5563;">Click URL <span style="color:#9CA3AF;">(optional)</span></label>
                            <input type="text" name="link_url" class="form-input" placeholder="https://…"
                                   style="border-radius:10px; width:100%; margin-top:4px;">
                        </div>
                        <div style="flex:1; min-width:90px;">
                            <label style="font-size:13px; font-weight:600; color:#4B5563;">Order</label>
                            <input type="number" name="sort_order" class="form-input" value="0" min="0"
                                   style="border-radius:10px; width:100%; margin-top:4px;">
                        </div>
                        <div style="display:flex; align-items:flex-end;">
                            <button type="submit" class="btn btn-primary wb-submit-btn"
                                    style="border-radius:10px; font-weight:600;">
                                <i class="fas fa-upload"></i> Upload
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Banner list -->
            <?php
            $pageBanners = array_filter($allBanners, fn($b) => $b['page'] === $pageKey);
            $byPosition  = ['top' => [], 'bottom' => []];
            foreach ($pageBanners as $b) $byPosition[$b['position']][] = $b;

            $positions = $pageKey === 'home' ? ['top', 'bottom'] : ['top'];
            $posLabels = ['top' => 'Top Banner', 'bottom' => 'Bottom Banner'];

            foreach ($positions as $pos):
                $posBanners = $byPosition[$pos];
            ?>
            <?php if ($pageKey === 'home'): ?>
            <div class="section-label-row">
                <i class="fas <?= $pos === 'top' ? 'fa-arrow-up' : 'fa-arrow-down' ?>" style="color:#0782ca;"></i>
                <?= $posLabels[$pos] ?> (<?= count($posBanners) ?> configured)
            </div>
            <?php endif; ?>

            <div class="wb-banner-grid">
                <?php if (empty($posBanners)): ?>
                <div class="empty-state">
                    <i class="fas fa-image" style="font-size:40px; margin-bottom:10px; display:block;"></i>
                    <p style="font-weight:600;">No banners yet for <?= $posLabels[$pos] ?></p>
                    <p>Use the form above to add one.</p>
                </div>
                <?php else: foreach ($posBanners as $b): ?>
                <div class="wb-banner-card" data-id="<?= $b['id'] ?>">
                    <img src="<?= htmlspecialchars($b['image_url']) ?>"
                         alt="<?= htmlspecialchars($b['title'] ?: 'Banner') ?>" loading="lazy">
                    <div class="wb-banner-card-body">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                            <div class="wb-card-title"><?= htmlspecialchars($b['title'] ?: 'Untitled') ?></div>
                            <span class="banner-status <?= $b['status'] ? 'active' : 'inactive' ?>">
                                <?= $b['status'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        <div class="wb-card-meta">
                            <span class="page-badge"><?= $pages[$b['page']] ?></span>
                            <span class="pos-badge"><?= $posLabels[$b['position']] ?></span>
                            &bull; Order: <?= $b['sort_order'] ?>
                            &bull; <?= date('M d, Y', strtotime($b['created_at'])) ?>
                            <?php if ($b['link_url']): ?>
                            &bull; <a href="<?= htmlspecialchars($b['link_url']) ?>" target="_blank" style="color:#0782ca;">Link ↗</a>
                            <?php endif; ?>
                        </div>
                        <div class="wb-card-actions">
                            <button class="btn btn-secondary btn-sm"
                                    onclick="toggleWb(<?= $b['id'] ?>, <?= $b['status'] ? 0 : 1 ?>)">
                                <i class="fas fa-<?= $b['status'] ? 'eye-slash' : 'eye' ?>"></i>
                                <?= $b['status'] ? 'Disable' : 'Enable' ?>
                            </button>
                            <button class="btn btn-sm"
                                    style="color:#ef4444; border:1px solid rgba(239,68,68,.3);"
                                    onclick="deleteWb(<?= $b['id'] ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <?php endforeach; ?>
        </div><!-- /panel -->
        <?php endforeach; ?>

        </div>
    </div>
</div>

<script>
const WB_API = 'Backend/api/manage_website_banners.php';

function switchTab(page) {
    document.querySelectorAll('.wb-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.wb-tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelector('[onclick="switchTab(\'' + page + '\')"]').classList.add('active');
    document.getElementById('panel-' + page).classList.add('active');
}

function selectPos(el, page) {
    const panel = document.getElementById('panel-' + page);
    panel.querySelectorAll('.pos-btn').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    panel.querySelector('.pos-input').value = el.dataset.pos;
}

function previewFile(input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = ev => {
        const img = input.closest('.wb-form-card').querySelector('.preview-img');
        img.src = ev.target.result;
        img.style.display = 'block';
    };
    reader.readAsDataURL(file);
    // Also setup drag-drop
    const area = input.previousElementSibling;
    area.querySelector('p').textContent = file.name;
}

// Drag & drop for all upload areas
document.querySelectorAll('.upload-area').forEach(area => {
    ['dragenter','dragover'].forEach(ev => area.addEventListener(ev, e => { e.preventDefault(); area.classList.add('dragover'); }));
    ['dragleave','drop'].forEach(ev => area.addEventListener(ev, e => { e.preventDefault(); area.classList.remove('dragover'); }));
    area.addEventListener('drop', e => {
        const fileInput = area.nextElementSibling;
        const dt = new DataTransfer();
        dt.items.add(e.dataTransfer.files[0]);
        fileInput.files = dt.files;
        previewFile(fileInput);
    });
});

// Upload form submit
document.querySelectorAll('.wb-upload-form').forEach(form => {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('.wb-submit-btn');
        const fileInput = this.querySelector('input[type=file]');
        const imageUrlInput = this.querySelector('input[name=image_url]');

        if ((!fileInput.files || !fileInput.files[0]) && !imageUrlInput.value.trim()) {
            alert('Please upload an image or provide an image URL.');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading…';

        const fd = new FormData(this);
        fd.append('action', 'upload');

        try {
            const res  = await fetch(WB_API, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.message || 'Upload failed');
        } catch (err) {
            alert('Network error: ' + err.message);
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-upload"></i> Upload';
    });
});

async function toggleWb(id, newStatus) {
    const fd = new FormData();
    fd.append('action', 'update');
    fd.append('id', id);
    fd.append('status', newStatus);
    const res  = await fetch(WB_API, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) location.reload();
    else alert(data.message);
}

async function deleteWb(id) {
    if (!confirm('Delete this banner? This cannot be undone.')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    const res  = await fetch(WB_API, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) location.reload();
    else alert(data.message);
}
</script>
</body>
</html>
