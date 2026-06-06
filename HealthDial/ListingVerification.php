<?php
require_once 'connection.inc.php';
requireLogin();

// Handle verification actions
if(isset($_GET['action']) && isset($_GET['id'])) {
    $lid = intval($_GET['id']);
    $action = $_GET['action'];
    
    if($action === 'approve') {
        $stmt = $conn->prepare("UPDATE listings SET status='approved' WHERE id=?");
        $stmt->bind_param("i", $lid);
        $stmt->execute();
        
        // Log (safe - won't break if activity_logs doesn't exist)
        try {
            $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, action, details, ip_address) VALUES (?, 'approve_listing', ?, ?)");
            if ($logStmt) {
                $detail = "Approved listing ID: $lid";
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $logStmt->bind_param("iss", $_SESSION['admin_id'], $detail, $ip);
                $logStmt->execute();
                $logStmt->close();
            }
        } catch (Exception $e) { /* activity_logs table may not exist */ }
        
        $_SESSION['success'] = "Listing approved!";
    }
    elseif($action === 'reject') {
        $reason = trim($_GET['reason'] ?? 'No reason specified');
        $stmt = $conn->prepare("UPDATE listings SET status='rejected' WHERE id=?");
        $stmt->bind_param("i", $lid);
        $stmt->execute();
        
        try {
            $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, action, details, ip_address) VALUES (?, 'reject_listing', ?, ?)");
            if ($logStmt) {
                $detail = "Rejected listing ID: $lid. Reason: $reason";
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $logStmt->bind_param("iss", $_SESSION['admin_id'], $detail, $ip);
                $logStmt->execute();
                $logStmt->close();
            }
        } catch (Exception $e) { /* activity_logs table may not exist */ }
        
        $_SESSION['success'] = "Listing rejected!";
    }
    elseif($action === 'pending') {
        $stmt = $conn->prepare("UPDATE listings SET status='pending' WHERE id=?");
        $stmt->bind_param("i", $lid);
        $stmt->execute();
        $_SESSION['success'] = "Listing moved back to pending.";
    }
    
    header("Location: ListingVerification.php"); exit();
}

// Bulk actions
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $ids = $_POST['listing_ids'] ?? [];
    $bulkAction = $_POST['bulk_action'];
    
    if(!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        
        if($bulkAction === 'bulk_approve') {
            $stmt = $conn->prepare("UPDATE listings SET status='approved' WHERE id IN ($placeholders)");
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $_SESSION['success'] = count($ids) . " listings approved!";
        }
        elseif($bulkAction === 'bulk_reject') {
            $stmt = $conn->prepare("UPDATE listings SET status='rejected' WHERE id IN ($placeholders)");
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $_SESSION['success'] = count($ids) . " listings rejected!";
        }
    }
    header("Location: ListingVerification.php"); exit();
}

// Stats - safe queries that won't crash
$totalPending = 0; $totalApproved = 0; $totalRejected = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM listings WHERE status='pending'");
if ($r) $totalPending = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM listings WHERE status='approved'");
if ($r) $totalApproved = (int)$r->fetch_assoc()['c'];
$r = $conn->query("SELECT COUNT(*) as c FROM listings WHERE status='rejected'");
if ($r) $totalRejected = (int)$r->fetch_assoc()['c'];
$totalAll = $totalPending + $totalApproved + $totalRejected;

// Filter
$tab = $_GET['tab'] ?? 'pending';
$search = trim($_GET['search'] ?? '');

$where = "WHERE l.status = ?";
$params = [$tab];
$types = "s";

if(!empty($search)) {
    $where .= " AND (l.name LIKE ? OR l.address LIKE ? OR l.mobile LIKE ? OR l.city LIKE ?)";
    $s = "%{$search}%";
    $params = array_merge($params, [$s,$s,$s,$s]);
    $types .= "ssss";
}

// Check if reviews table exists
$hasReviews = false;
$tableCheck = $conn->query("SHOW TABLES LIKE 'reviews'");
if ($tableCheck && $tableCheck->num_rows > 0) $hasReviews = true;

if ($hasReviews) {
    $sql = "SELECT l.*, c.name as category_name, 
            (SELECT image_path FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image,
            (SELECT COUNT(*) FROM reviews WHERE listing_id = l.id) as review_count,
            (SELECT ROUND(AVG(rating),1) FROM reviews WHERE listing_id = l.id) as avg_rating
            FROM listings l 
            LEFT JOIN categories c ON l.category_id = c.id 
            $where 
            ORDER BY l.created_at DESC";
} else {
    $sql = "SELECT l.*, c.name as category_name, 
            (SELECT image_path FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) as primary_image,
            0 as review_count,
            NULL as avg_rating
            FROM listings l 
            LEFT JOIN categories c ON l.category_id = c.id 
            $where 
            ORDER BY l.created_at DESC";
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    // SQL error — show empty result
    error_log("ListingVerification SQL error: " . $conn->error);
    $result = new class { public $num_rows = 0; public function fetch_assoc() { return null; } };
} else {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listing Verification — HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .verify-tabs { display:flex;gap:4px;background:var(--bg-card-hover);padding:4px;border-radius:var(--radius-md); }
        .verify-tab { padding:8px 20px;border-radius:var(--radius-sm);font-size:13px;font-weight:600;color:var(--text-muted);text-decoration:none;transition:all var(--transition-fast);display:flex;align-items:center;gap:6px; }
        .verify-tab:hover { color:var(--text-primary); }
        .verify-tab.active { background:var(--bg-card);color:var(--text-primary);box-shadow:var(--shadow-sm); }
        .verify-tab .tab-count { background:var(--border-light);color:var(--text-muted);padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700; }
        .verify-tab.active .tab-count { background:var(--primary);color:#fff; }
        
        .listing-card { border:1px solid var(--border-light);border-radius:var(--radius-lg);padding:20px;margin-bottom:14px;transition:all var(--transition-fast);background:var(--bg-card); }
        .listing-card:hover { border-color:var(--primary);box-shadow:0 4px 15px rgba(16,185,129,0.08); }
        .listing-card-header { display:flex;gap:16px;align-items:flex-start; }
        .listing-img { width:90px;height:70px;border-radius:var(--radius-md);object-fit:cover;background:var(--bg-card-hover);flex-shrink:0; }
        .listing-img-placeholder { width:90px;height:70px;border-radius:var(--radius-md);background:var(--bg-card-hover);display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:24px;flex-shrink:0; }
        
        .checklist { display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:12px; }
        .check-item { display:flex;align-items:center;gap:6px;font-size:11px;padding:4px 8px;border-radius:4px;background:var(--bg-card-hover); }
        .check-item.pass { color:#10b981; }
        .check-item.fail { color:#ef4444; }
        .check-item.warn { color:#f59e0b; }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'sidebar.php'; ?>
        <div class="admin-main">
            <?php include 'header.php'; ?>
            <div class="admin-content">
                <div class="page-header">
                    <h1 class="page-title"><i class="fas fa-clipboard-check" style="color:var(--primary);margin-right:8px;"></i>Listing Verification</h1>
                    <p class="page-subtitle">Review, verify, and approve/reject listings</p>
                </div>

                <!-- Stats -->
                <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);">
                    <div class="stat-card blue fade-in">
                        <div class="stat-info"><h3>Total</h3><p class="stat-value"><?php echo $totalAll; ?></p></div>
                        <div class="stat-icon blue"><i class="fas fa-list"></i></div>
                    </div>
                    <div class="stat-card amber fade-in fade-in-delay-1">
                        <div class="stat-info"><h3>Pending</h3><p class="stat-value"><?php echo $totalPending; ?></p></div>
                        <div class="stat-icon amber"><i class="fas fa-clock"></i></div>
                    </div>
                    <div class="stat-card emerald fade-in fade-in-delay-2">
                        <div class="stat-info"><h3>Approved</h3><p class="stat-value"><?php echo $totalApproved; ?></p></div>
                        <div class="stat-icon emerald"><i class="fas fa-check-circle"></i></div>
                    </div>
                    <div class="stat-card rose fade-in fade-in-delay-3">
                        <div class="stat-info"><h3>Rejected</h3><p class="stat-value"><?php echo $totalRejected; ?></p></div>
                        <div class="stat-icon rose"><i class="fas fa-times-circle"></i></div>
                    </div>
                </div>

                <div class="card fade-in">
                    <!-- Tabs + Search -->
                    <div style="padding:16px 24px;border-bottom:1px solid var(--border-light);">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                            <div class="verify-tabs">
                                <a href="?tab=pending&search=<?php echo urlencode($search); ?>" class="verify-tab <?php echo $tab==='pending'?'active':''; ?>">
                                    <i class="fas fa-clock"></i> Pending <span class="tab-count"><?php echo $totalPending; ?></span>
                                </a>
                                <a href="?tab=approved&search=<?php echo urlencode($search); ?>" class="verify-tab <?php echo $tab==='approved'?'active':''; ?>">
                                    <i class="fas fa-check"></i> Approved <span class="tab-count"><?php echo $totalApproved; ?></span>
                                </a>
                                <a href="?tab=rejected&search=<?php echo urlencode($search); ?>" class="verify-tab <?php echo $tab==='rejected'?'active':''; ?>">
                                    <i class="fas fa-times"></i> Rejected <span class="tab-count"><?php echo $totalRejected; ?></span>
                                </a>
                            </div>
                            <form method="GET" style="display:flex;gap:8px;">
                                <input type="hidden" name="tab" value="<?php echo $tab; ?>">
                                <div class="search-box"><i class="fas fa-search"></i><input type="text" name="search" class="form-input" placeholder="Search listings..." value="<?php echo htmlspecialchars($search); ?>"></div>
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
                            </form>
                        </div>
                    </div>

                    <!-- Bulk Actions Bar -->
                    <?php if($tab === 'pending' && $totalPending > 0): ?>
                    <div id="bulkBar" style="display:none;padding:12px 24px;background:var(--primary-50);border-bottom:1px solid var(--border-light);">
                        <form method="POST" style="display:flex;align-items:center;gap:12px;">
                            <span id="selectedCount" style="font-size:13px;font-weight:600;">0 selected</span>
                            <button type="submit" name="bulk_action" value="bulk_approve" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Approve Selected</button>
                            <button type="submit" name="bulk_action" value="bulk_reject" class="btn btn-secondary btn-sm" style="color:var(--status-danger);"><i class="fas fa-times"></i> Reject Selected</button>
                            <div id="bulkIds"></div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Listing Cards -->
                    <div style="padding:20px 24px;">
                        <?php if($result->num_rows > 0): ?>
                        <?php while($listing = $result->fetch_assoc()):
                            // Verification checks
                            $checks = [];
                            $checks[] = ['Name', !empty($listing['name']) && strlen($listing['name']) >= 3, 'pass'];
                            $checks[] = ['Address', !empty($listing['address']), empty($listing['address']) ? 'fail' : 'pass'];
                            $checks[] = ['Mobile', !empty($listing['mobile']), empty($listing['mobile']) ? 'warn' : 'pass'];
                            $checks[] = ['Email', !empty($listing['email']) && filter_var($listing['email'], FILTER_VALIDATE_EMAIL), empty($listing['email']) ? 'warn' : (filter_var($listing['email'], FILTER_VALIDATE_EMAIL) ? 'pass' : 'fail')];
                            $checks[] = ['Location', ($listing['latitude'] && $listing['longitude']), (!$listing['latitude'] || !$listing['longitude']) ? 'fail' : 'pass'];
                            $checks[] = ['Category', !empty($listing['category_name']), empty($listing['category_name']) ? 'fail' : 'pass'];
                            $checks[] = ['Hours', ($listing['is_24x7'] || (!empty($listing['open_time']) && !empty($listing['close_time']))), 'pass'];
                            $checks[] = ['Image', !empty($listing['primary_image']), empty($listing['primary_image']) ? 'warn' : 'pass'];
                            
                            $passCount = count(array_filter($checks, function($c) { return $c[2] === 'pass'; }));
                            $totalChecks = count($checks);
                            $checkPct = round(($passCount / $totalChecks) * 100);
                        ?>
                        <div class="listing-card">
                            <div class="listing-card-header">
                                <?php if($tab === 'pending'): ?>
                                <input type="checkbox" class="listing-cb" value="<?php echo $listing['id']; ?>" style="accent-color:var(--primary);margin-top:4px;">
                                <?php endif; ?>
                                
                                <?php if($listing['primary_image']): ?>
                                <img src="./uploads/listings/<?php echo $listing['primary_image']; ?>" class="listing-img" alt="Listing">
                                <?php else: ?>
                                <div class="listing-img-placeholder"><i class="fas fa-hospital"></i></div>
                                <?php endif; ?>
                                
                                <div style="flex:1;min-width:0;">
                                    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
                                        <div>
                                            <h4 style="font-size:15px;font-weight:700;margin:0 0 4px;"><?php echo htmlspecialchars($listing['name']); ?></h4>
                                            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:6px;">
                                                <span class="badge badge-info"><?php echo htmlspecialchars($listing['category_name'] ?? 'Uncategorized'); ?></span>
                                                <?php if($listing['city']): ?><span class="badge badge-gray"><i class="fas fa-map-pin" style="margin-right:3px;font-size:9px;"></i><?php echo htmlspecialchars($listing['city']); ?></span><?php endif; ?>
                                                <?php if($listing['is_24x7']): ?><span class="badge badge-success">24×7</span><?php endif; ?>
                                                <?php if($listing['avg_rating']): ?><span class="badge badge-warning"><i class="fas fa-star" style="margin-right:3px;font-size:9px;"></i><?php echo $listing['avg_rating']; ?> (<?php echo $listing['review_count']; ?>)</span><?php endif; ?>
                                            </div>
                                            <p style="font-size:12px;color:var(--text-muted);margin:0;"><?php echo htmlspecialchars($listing['address'] ?? 'No address'); ?></p>
                                            <div style="font-size:11px;color:var(--text-muted);margin-top:6px;">
                                                <?php if($listing['mobile']): ?><i class="fas fa-phone" style="margin-right:3px;"></i><?php echo htmlspecialchars($listing['mobile']); ?>&nbsp;&nbsp;<?php endif; ?>
                                                <?php if($listing['email']): ?><i class="fas fa-envelope" style="margin-right:3px;"></i><?php echo htmlspecialchars($listing['email']); ?>&nbsp;&nbsp;<?php endif; ?>
                                                <i class="far fa-calendar" style="margin-right:3px;"></i><?php echo date('d M Y, h:i A', strtotime($listing['created_at'])); ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Verification Score -->
                                        <div style="text-align:center;min-width:60px;">
                                            <div style="width:48px;height:48px;border-radius:50%;border:3px solid <?php echo $checkPct >= 80 ? '#10b981' : ($checkPct >= 50 ? '#f59e0b' : '#ef4444'); ?>;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:<?php echo $checkPct >= 80 ? '#10b981' : ($checkPct >= 50 ? '#f59e0b' : '#ef4444'); ?>;">
                                                <?php echo $checkPct; ?>%
                                            </div>
                                            <div style="font-size:9px;color:var(--text-muted);margin-top:4px;font-weight:600;">QUALITY</div>
                                        </div>
                                    </div>

                                    <!-- Verification Checklist -->
                                    <div class="checklist">
                                        <?php foreach($checks as $check): ?>
                                        <div class="check-item <?php echo $check[2]; ?>">
                                            <i class="fas <?php echo $check[2]==='pass' ? 'fa-check-circle' : ($check[2]==='warn' ? 'fa-exclamation-triangle' : 'fa-times-circle'); ?>"></i>
                                            <?php echo $check[0]; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div style="display:flex;gap:8px;margin-top:14px;">
                                        <?php if($tab !== 'approved'): ?>
                                        <a href="?action=approve&id=<?php echo $listing['id']; ?>" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Approve</a>
                                        <?php endif; ?>
                                        <?php if($tab !== 'rejected'): ?>
                                        <button onclick="rejectListing(<?php echo $listing['id']; ?>)" class="btn btn-secondary btn-sm" style="color:var(--status-danger);border-color:rgba(239,68,68,0.3);"><i class="fas fa-times"></i> Reject</button>
                                        <?php endif; ?>
                                        <?php if($tab !== 'pending'): ?>
                                        <a href="?action=pending&id=<?php echo $listing['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-undo"></i> Revert to Pending</a>
                                        <?php endif; ?>
                                        <a href="view-listing.php?id=<?php echo $listing['id']; ?>" class="btn btn-ghost btn-sm"><i class="fas fa-eye"></i> View Full</a>
                                        <?php if($listing['latitude'] && $listing['longitude']): ?>
                                        <a href="https://maps.google.com/?q=<?php echo $listing['latitude']; ?>,<?php echo $listing['longitude']; ?>" target="_blank" class="btn btn-ghost btn-sm"><i class="fas fa-map-marker-alt"></i> Map</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="fas fa-clipboard-check"></i></div>
                            <h3>No <?php echo $tab; ?> listings</h3>
                            <p>All clear! No listings in the <?php echo $tab; ?> queue.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function rejectListing(id) {
        const reason = prompt('Rejection reason (optional):');
        if(reason !== null) {
            window.location = '?action=reject&id=' + id + '&reason=' + encodeURIComponent(reason);
        }
    }

    // Bulk selection
    const cbs = document.querySelectorAll('.listing-cb');
    const bulkBar = document.getElementById('bulkBar');
    const bulkIds = document.getElementById('bulkIds');
    const selectedCount = document.getElementById('selectedCount');

    if(cbs.length) {
        cbs.forEach(cb => {
            cb.addEventListener('change', updateBulk);
        });
    }

    function updateBulk() {
        const checked = document.querySelectorAll('.listing-cb:checked');
        if(bulkBar) {
            bulkBar.style.display = checked.length > 0 ? 'block' : 'none';
            if(selectedCount) selectedCount.textContent = checked.length + ' selected';
            if(bulkIds) {
                bulkIds.innerHTML = '';
                checked.forEach(cb => {
                    const inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'listing_ids[]';
                    inp.value = cb.value;
                    bulkIds.appendChild(inp);
                });
            }
        }
    }
    </script>
</body>
</html>
