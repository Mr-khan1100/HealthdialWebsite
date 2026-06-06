<?php require_once 'connection.inc.php'; requireLogin(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banner Management - HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .banner-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .banner-card {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid #E8ECF0;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .banner-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        .banner-card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        .banner-card-body { padding: 16px; }
        .banner-card-title { font-weight: 700; font-size: 15px; color: #1A1D26; margin-bottom: 6px; }
        .banner-card-meta { font-size: 12px; color: #9CA3AF; margin-bottom: 10px; }
        .banner-card-actions { display: flex; gap: 8px; }
        .banner-card-actions .btn { font-size: 12px; padding: 6px 12px; border-radius: 8px; }
        .banner-status { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .banner-status.active { background: #ECFDF5; color: #059669; }
        .banner-status.inactive { background: #FEF2F2; color: #DC2626; }
        .upload-area {
            border: 2px dashed #D1D5DB; border-radius: 14px; padding: 40px;
            text-align: center; cursor: pointer; transition: all 0.3s;
            background: #F9FAFB; margin-bottom: 20px;
        }
        .upload-area:hover, .upload-area.dragover { border-color: #0782ca; background: #E8F4FD; }
        .upload-area i { font-size: 40px; color: #9CA3AF; margin-bottom: 12px; }
        .upload-area:hover i { color: #0782ca; }
        .upload-area p { color: #6B7280; margin: 0; }
        .upload-area .sub { font-size: 12px; color: #9CA3AF; margin-top: 4px; }
        #preview-img { max-height: 200px; border-radius: 12px; margin-top: 12px; display: none; }
        .page-header-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .stat-pills { display: flex; gap: 12px; }
        .stat-pill { background: #fff; border: 1px solid #E8ECF0; border-radius: 10px; padding: 8px 16px; font-size: 13px; font-weight: 600; color: #4B5563; }
        .stat-pill span { color: #0782ca; font-weight: 700; }
        .sort-handle { cursor: grab; color: #9CA3AF; }
        .sort-handle:active { cursor: grabbing; }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'sidebar.php'; ?>
        <div class="admin-main">
            <?php include 'header.php'; ?>
            <div class="admin-content">

        <div class="page-header-row">
            <div>
                <h2 style="margin:0; font-weight:800; color:#1A1D26;">
                    <i class="fas fa-images" style="color:#0782ca;"></i> Banner Management
                </h2>
                <p style="color:#9CA3AF; margin-top:4px;">Manage featured service banners shown on the home screen</p>
            </div>
            <div class="stat-pills">
                <?php
                $totalBanners = 0;
                $activeBanners = 0;
                $res = $conn->query("SELECT COUNT(*) as total, SUM(status=1) as active FROM banners");
                if ($res && $row = $res->fetch_assoc()) {
                    $totalBanners  = (int)$row['total'];
                    $activeBanners = (int)($row['active'] ?? 0);
                }
                ?>
                <div class="stat-pill">Total: <span><?php echo $totalBanners; ?></span></div>
                <div class="stat-pill">Active: <span><?php echo $activeBanners; ?></span></div>
            </div>
        </div>
        
        <!-- Upload Form -->
        <div class="card" style="margin-top:20px; padding:24px; border-radius:14px;">
            <h5 style="font-weight:700; margin-bottom:16px;">
                <i class="fas fa-cloud-upload-alt" style="color:#0782ca;"></i> Upload New Banner
            </h5>
            <form id="upload-form" enctype="multipart/form-data">
                <div class="upload-area" id="drop-area" onclick="document.getElementById('banner-file').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Click or drag &amp; drop banner image here</p>
                    <p class="sub">JPG, PNG, WebP, GIF — Max 5MB — Recommended: 1200×400px</p>
                    <img id="preview-img" />
                </div>
                <input type="file" id="banner-file" name="image" accept="image/*" style="display:none">
                
                <div style="display:flex; gap:12px; margin-top:12px; flex-wrap:wrap;">
                    <div style="flex:2; min-width:200px;">
                        <label style="font-size:13px; font-weight:600; color:#4B5563;">Title (optional)</label>
                        <input type="text" name="title" class="form-input" placeholder="e.g. Summer Health Checkup" style="border-radius:10px; width:100%; margin-top:4px;">
                    </div>
                    <div style="flex:2; min-width:200px;">
                        <label style="font-size:13px; font-weight:600; color:#4B5563;">Link URL (optional)</label>
                        <input type="text" name="link_url" class="form-input" placeholder="e.g. https://healthdial.com/offer" style="border-radius:10px; width:100%; margin-top:4px;">
                    </div>
                    <div style="flex:1; min-width:100px;">
                        <label style="font-size:13px; font-weight:600; color:#4B5563;">Sort Order</label>
                        <input type="number" name="sort_order" class="form-input" value="0" min="0" style="border-radius:10px; width:100%; margin-top:4px;">
                    </div>
                    <div style="display:flex; align-items:flex-end;">
                        <button type="submit" class="btn btn-primary" style="border-radius:10px; font-weight:600;" id="upload-btn">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Banner Grid -->
        <div class="banner-grid" id="banner-grid">
            <?php
            $banners = $conn->query("SELECT * FROM banners ORDER BY sort_order ASC, id DESC");
            if ($banners && $banners->num_rows > 0):
                while ($b = $banners->fetch_assoc()):
            ?>
            <div class="banner-card" data-id="<?php echo $b['id']; ?>">
                <img src="<?php echo htmlspecialchars($b['image_url']); ?>" alt="<?php echo htmlspecialchars($b['title'] ?: 'Banner'); ?>" loading="lazy">
                <div class="banner-card-body">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div class="banner-card-title">
                            <i class="fas fa-grip-vertical sort-handle"></i>
                            <?php echo htmlspecialchars($b['title'] ?: 'Untitled Banner'); ?>
                        </div>
                        <span class="banner-status <?php echo $b['status'] ? 'active' : 'inactive'; ?>">
                            <?php echo $b['status'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                    <div class="banner-card-meta">
                        Order: <?php echo $b['sort_order']; ?> &bull; 
                        <?php echo date('M d, Y', strtotime($b['created_at'])); ?>
                        <?php if ($b['link_url']): ?>
                            &bull; <a href="<?php echo htmlspecialchars($b['link_url']); ?>" target="_blank" style="color:#0782ca;">Link ↗</a>
                        <?php endif; ?>
                    </div>
                    <div class="banner-card-actions">
                        <button class="btn btn-secondary btn-sm" onclick="toggleBanner(<?php echo $b['id']; ?>, <?php echo $b['status'] ? 0 : 1; ?>)">
                            <i class="fas fa-<?php echo $b['status'] ? 'eye-slash' : 'eye'; ?>"></i>
                            <?php echo $b['status'] ? 'Disable' : 'Enable'; ?>
                        </button>
                        <button class="btn btn-sm" style="color:#ef4444; border:1px solid rgba(239,68,68,0.3);" onclick="deleteBanner(<?php echo $b['id']; ?>)">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
            <?php 
                endwhile;
            else: 
            ?>
            <div style="grid-column: 1/-1; text-align:center; padding:60px; color:#9CA3AF;">
                <i class="fas fa-images" style="font-size:48px; margin-bottom:12px; display:block;"></i>
                <p style="font-size:16px; font-weight:600;">No banners yet</p>
                <p>Upload your first banner above to get started</p>
            </div>
            <?php endif; ?>
        </div>

            </div>
        </div>
    </div>
    
    <script>
    const API = 'Backend/api/manage_banners.php';
    
    // Preview image on file select
    document.getElementById('banner-file').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                const img = document.getElementById('preview-img');
                img.src = ev.target.result;
                img.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Drag & drop
    const dropArea = document.getElementById('drop-area');
    ['dragenter', 'dragover'].forEach(ev => {
        dropArea.addEventListener(ev, e => { e.preventDefault(); dropArea.classList.add('dragover'); });
    });
    ['dragleave', 'drop'].forEach(ev => {
        dropArea.addEventListener(ev, e => { e.preventDefault(); dropArea.classList.remove('dragover'); });
    });
    dropArea.addEventListener('drop', e => {
        const file = e.dataTransfer.files[0];
        if (file) {
            document.getElementById('banner-file').files = e.dataTransfer.files;
            const reader = new FileReader();
            reader.onload = ev => {
                const img = document.getElementById('preview-img');
                img.src = ev.target.result;
                img.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Upload form
    document.getElementById('upload-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('upload-btn');
        const fileInput = document.getElementById('banner-file');
        
        if (!fileInput.files[0]) {
            alert('Please select an image first');
            return;
        }
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        
        const formData = new FormData(this);
        formData.append('action', 'upload');
        
        try {
            const res = await fetch(API, { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Upload failed');
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        }
        
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-upload"></i> Upload';
    });
    
    // Toggle banner status
    async function toggleBanner(id, newStatus) {
        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('id', id);
        formData.append('status', newStatus);
        
        const res = await fetch(API, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.message);
    }
    
    // Delete banner
    async function deleteBanner(id) {
        if (!confirm('Delete this banner? This cannot be undone.')) return;
        
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        
        const res = await fetch(API, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.message);
    }
    </script>
</body>
</html>
