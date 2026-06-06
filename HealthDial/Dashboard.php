<?php
require_once 'connection.inc.php';
requireLogin();

// Stats
$totalUsers = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$totalListings = $conn->query("SELECT COUNT(*) as total FROM listings")->fetch_assoc()['total'];
$pendingListings = $conn->query("SELECT COUNT(*) as total FROM listings WHERE status='pending'")->fetch_assoc()['total'];
$totalReviews = $conn->query("SELECT COUNT(*) as total FROM reviews")->fetch_assoc()['total'];
$totalNews = $conn->query("SELECT COUNT(*) as total FROM news")->fetch_assoc()['total'];
$activeListings = $conn->query("SELECT COUNT(*) as total FROM listings WHERE status='approved'")->fetch_assoc()['total'];
$totalMedications = $conn->query("SELECT COUNT(*) as total FROM medications")->fetch_assoc()['total'];
$totalDocuments = $conn->query("SELECT COUNT(*) as total FROM documents")->fetch_assoc()['total'];

// Separate push notifications vs medication reminders
$totalPushNotifications = $conn->query("SELECT COUNT(*) as total FROM notification_queue WHERE notification_type = 'manual'")->fetch_assoc()['total'];
$totalMedReminders = $conn->query("SELECT COUNT(*) as total FROM notification_queue WHERE notification_type = 'medication'")->fetch_assoc()['total'];

// Users registered this week
$weekUsers = $conn->query("SELECT COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['total'];

// Recent activity logs
$activityLogs = [];
$logRes = $conn->query("SELECT al.*, au.name as admin_name FROM activity_logs al LEFT JOIN admin_users au ON al.admin_id = au.id ORDER BY al.created_at DESC LIMIT 10");
if ($logRes)
    while ($log = $logRes->fetch_assoc())
        $activityLogs[] = $log;

// Category stats for chart
$catLabels = [];
$catData = [];
$catResult = $conn->query("SELECT c.name, COUNT(l.id) as count FROM categories c LEFT JOIN listings l ON c.id = l.category_id GROUP BY c.id ORDER BY count DESC");
if ($catResult) {
    while ($catRow = $catResult->fetch_assoc()) {
        $catLabels[] = $catRow['name'];
        $catData[] = (int) $catRow['count'];
    }
}

// User growth (last 7 days)
$growthLabels = [];
$growthData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $growthLabels[] = date('d M', strtotime($date));
    $dayCount = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE DATE(created_at) = '$date'")->fetch_assoc()['cnt'];
    $growthData[] = (int) $dayCount;
}

// Recent users
$recentUsers = [];
$ruRes = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
if ($ruRes)
    while ($ru = $ruRes->fetch_assoc())
        $recentUsers[] = $ru;

// Pending reviews
// Pending reviews
$pendingReviewsCount = $conn->query("SELECT COUNT(*) as cnt FROM reviews WHERE status=0")->fetch_assoc()['cnt'];

// ===== WEBSITE ANALYTICS WIDGET DATA =====
// Top Cities by Listings
$topCities = [];
$cityRes = $conn->query("SELECT city, COUNT(*) as count FROM listings WHERE status='approved' AND city IS NOT NULL AND city != '' GROUP BY city ORDER BY count DESC LIMIT 10");
if ($cityRes)
    while ($c = $cityRes->fetch_assoc())
        $topCities[] = $c;

// Listing growth (last 30 days)
$listingGrowthLabels = [];
$listingGrowthData = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $listingGrowthLabels[] = date('d', strtotime($date));
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM listings WHERE created_at >= ? AND created_at < ?");
    $start = $date . ' 00:00:00';
    $end = $date . ' 23:59:59';
    $stmt->bind_param("ss", $start, $end);
    $stmt->execute();
    $dayCount = $stmt->get_result()->fetch_assoc()['cnt'];
    $listingGrowthData[] = (int) $dayCount;
}

// Top rated listings
$topRated = [];
$trRes = $conn->query("SELECT l.name, l.city, ROUND(AVG(r.rating),1) as avg_rating, COUNT(r.id) as review_count FROM listings l INNER JOIN reviews r ON l.id = r.listing_id WHERE l.status='approved' GROUP BY l.id HAVING review_count >= 2 ORDER BY avg_rating DESC LIMIT 5");
if ($trRes)
    while ($tr = $trRes->fetch_assoc())
        $topRated[] = $tr;

// Today's stats
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM listings WHERE created_at >= ? AND created_at < ?");
$start = date('Y-m-d') . ' 00:00:00';
$end = date('Y-m-d') . ' 23:59:59';
$stmt->bind_param("ss", $start, $end);
$stmt->execute();
$todayListings = $stmt->get_result()->fetch_assoc()['cnt'];
// $todayListings = $conn->query("SELECT COUNT(*) as cnt FROM listings WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['cnt'];
$todayUsers = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['cnt'];
$todayReviews = $conn->query("SELECT COUNT(*) as cnt FROM reviews WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="admin-wrapper">
        <?php include 'sidebar.php'; ?>

        <div class="admin-main">
            <?php include 'header.php'; ?>

            <div class="admin-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h1 class="page-title">Dashboard</h1>
                    <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_name']); ?>!
                        Here's your overview.</p>
                </div>

                <!-- Stat Cards -->
                <div class="stat-grid">
                    <div class="stat-card emerald fade-in">
                        <div class="stat-info">
                            <h3>Total Users</h3>
                            <p class="stat-value" data-count="<?php echo $totalUsers; ?>">0</p>
                            <div class="stat-change up">
                                <i class="fas fa-arrow-up" style="font-size:10px;"></i>
                                +<?php echo $weekUsers; ?> this week
                            </div>
                        </div>
                        <div class="stat-icon emerald">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>

                    <div class="stat-card blue fade-in fade-in-delay-1">
                        <div class="stat-info">
                            <h3>Active Listings</h3>
                            <p class="stat-value" data-count="<?php echo $activeListings; ?>">0</p>
                            <div class="stat-change up">
                                <i class="fas fa-hospital" style="font-size:10px;"></i>
                                <?php echo $totalListings; ?> total
                            </div>
                        </div>
                        <div class="stat-icon blue">
                            <i class="fas fa-hospital"></i>
                        </div>
                    </div>

                    <div class="stat-card amber fade-in fade-in-delay-2">
                        <div class="stat-info">
                            <h3>Pending Actions</h3>
                            <p class="stat-value" data-count="<?php echo $pendingListings + $pendingReviewsCount; ?>">0
                            </p>
                            <div class="stat-change down">
                                <i class="fas fa-clock" style="font-size:10px;"></i>
                                <?php echo $pendingListings; ?> listings, <?php echo $pendingReviewsCount; ?> reviews
                            </div>
                        </div>
                        <div class="stat-icon amber">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>

                    <div class="stat-card purple fade-in fade-in-delay-3">
                        <div class="stat-info">
                            <h3>Total Reviews</h3>
                            <p class="stat-value" data-count="<?php echo $totalReviews; ?>">0</p>
                            <div class="stat-change up">
                                <i class="fas fa-star" style="font-size:10px;"></i>
                                User feedback
                            </div>
                        </div>
                        <div class="stat-icon purple">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                </div>

                <!-- Secondary Stats -->
                <div
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 28px;">
                    <div class="card" style="padding: 16px 20px; display: flex; align-items: center; gap: 12px;">
                        <div
                            style="width:36px;height:36px;border-radius:8px;background:rgba(59,130,246,0.1);display:flex;align-items:center;justify-content:center;color:#3b82f6;font-size:14px;">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <div>
                            <div style="font-size:11px;color:var(--text-muted);font-weight:600;">News Articles</div>
                            <div style="font-size:20px;font-weight:800;color:var(--text-primary);">
                                <?php echo $totalNews; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card" style="padding: 16px 20px; display: flex; align-items: center; gap: 12px;">
                        <div
                            style="width:36px;height:36px;border-radius:8px;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center;color:#10b981;font-size:14px;">
                            <i class="fas fa-pills"></i>
                        </div>
                        <div>
                            <div style="font-size:11px;color:var(--text-muted);font-weight:600;">Medications</div>
                            <div style="font-size:20px;font-weight:800;color:var(--text-primary);">
                                <?php echo $totalMedications; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card" style="padding: 16px 20px; display: flex; align-items: center; gap: 12px;">
                        <div
                            style="width:36px;height:36px;border-radius:8px;background:rgba(245,158,11,0.1);display:flex;align-items:center;justify-content:center;color:#f59e0b;font-size:14px;">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div>
                            <div style="font-size:11px;color:var(--text-muted);font-weight:600;">Documents</div>
                            <div style="font-size:20px;font-weight:800;color:var(--text-primary);">
                                <?php echo $totalDocuments; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card" style="padding: 16px 20px; display: flex; align-items: center; gap: 12px;">
                        <div
                            style="width:36px;height:36px;border-radius:8px;background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;color:#6366f1;font-size:14px;">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div>
                            <div style="font-size:11px;color:var(--text-muted);font-weight:600;">Categories</div>
                            <div style="font-size:20px;font-weight:800;color:var(--text-primary);">
                                <?php echo count($catLabels); ?>
                            </div>
                        </div>
                    </div>
                    <a href="Notification.php" style="text-decoration:none;color:inherit;">
                        <div class="card"
                            style="padding: 16px 20px; display: flex; align-items: center; gap: 12px; cursor:pointer; transition:transform 0.2s;"
                            onmouseover="this.style.transform='scale(1.03)'"
                            onmouseout="this.style.transform='scale(1)'">
                            <div
                                style="width:36px;height:36px;border-radius:8px;background:rgba(239,68,68,0.1);display:flex;align-items:center;justify-content:center;color:#ef4444;font-size:14px;">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div style="flex:1;">
                                <div style="font-size:11px;color:var(--text-muted);font-weight:600;">Push Notifications
                                </div>
                                <div style="font-size:20px;font-weight:800;color:var(--text-primary);">
                                    <?php echo $totalPushNotifications; ?>
                                </div>
                            </div>
                            <div style="font-size:11px;color:#0782ca;font-weight:600;">View All →</div>
                        </div>
                    </a>
                    <a href="Notification.php" style="text-decoration:none;color:inherit;">
                        <div class="card"
                            style="padding: 16px 20px; display: flex; align-items: center; gap: 12px; cursor:pointer; transition:transform 0.2s;"
                            onmouseover="this.style.transform='scale(1.03)'"
                            onmouseout="this.style.transform='scale(1)'">
                            <div
                                style="width:36px;height:36px;border-radius:8px;background:rgba(168,85,247,0.1);display:flex;align-items:center;justify-content:center;color:#a855f7;font-size:14px;">
                                <i class="fas fa-alarm-clock"></i>
                            </div>
                            <div style="flex:1;">
                                <div style="font-size:11px;color:var(--text-muted);font-weight:600;">Med Reminders</div>
                                <div style="font-size:20px;font-weight:800;color:var(--text-primary);">
                                    <?php echo $totalMedReminders; ?>
                                </div>
                            </div>
                            <div style="font-size:11px;color:#0782ca;font-weight:600;">View All →</div>
                        </div>
                    </a>
                </div>

                <!-- Charts Row -->
                <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 20px; margin-bottom: 28px;">
                    <div class="card fade-in">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">User Growth</h3>
                                <p class="card-subtitle">New registrations — last 7 days</p>
                            </div>
                            <span class="badge badge-success"><i class="fas fa-chart-line"
                                    style="margin-right:4px;"></i> Live</span>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 260px;">
                                <canvas id="userGrowthChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="card fade-in fade-in-delay-1">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">Listings by Category</h3>
                                <p class="card-subtitle">Distribution overview</p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 260px;">
                                <canvas id="categoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Row: Recent Users + Activity -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <!-- Recent Users -->
                    <div class="card fade-in">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">Recent Users</h3>
                                <p class="card-subtitle">Latest registrations</p>
                            </div>
                            <a href="Users.php" class="btn btn-sm btn-secondary">View All</a>
                        </div>
                        <div style="padding: 0;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>State</th>
                                        <th>Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentUsers as $user): ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:10px;">
                                                <div
                                                    style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;">
                                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div
                                                        style="font-weight:600;color:var(--text-primary);font-size:13px;">
                                                        <?php echo htmlspecialchars($user['name']); ?>
                                                    </div>
                                                    <div style="font-size:11px;color:var(--text-muted);">
                                                        <?php echo htmlspecialchars($user['mobile'] ?? $user['email'] ?? ''); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span
                                                class="badge badge-info"><?php echo htmlspecialchars($user['state'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td style="font-size:12px;color:var(--text-muted);">
                                            <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Activity Log -->
                    <div class="card fade-in fade-in-delay-1">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">Activity Log</h3>
                                <p class="card-subtitle">Recent admin actions</p>
                            </div>
                            <div class="pulse-dot"></div>
                        </div>
                        <div class="card-body">
                            <?php if (count($activityLogs) > 0): ?>
                            <div class="timeline">
                                <?php foreach ($activityLogs as $log): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <strong><?php echo htmlspecialchars($log['admin_name'] ?? 'System'); ?></strong>
                                        — <?php echo htmlspecialchars($log['action']); ?>
                                        <?php if ($log['details']): ?>
                                        <br><span
                                            style="color:var(--text-muted);font-size:12px;"><?php echo htmlspecialchars($log['details']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timeline-time">
                                        <?php echo date('d M, h:i A', strtotime($log['created_at'])); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="empty-state" style="padding: 30px;">
                                <div class="empty-state-icon" style="width:50px;height:50px;font-size:20px;">
                                    <i class="fas fa-history"></i>
                                </div>
                                <p style="font-size:13px;">No activity recorded yet</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ===== WEBSITE ANALYTICS WIDGET ===== -->
                <div style="margin-top:28px;">
                    <div class="page-header" style="margin-bottom:16px;">
                        <h2 class="page-title" style="font-size:18px;"><i class="fas fa-chart-area"
                                style="color:#3b82f6;margin-right:8px;"></i>Website Analytics</h2>
                    </div>

                    <!-- Today's Quick Stats -->
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px;">
                        <div class="card"
                            style="padding:20px;text-align:center;background:linear-gradient(135deg,rgba(16,185,129,0.05),rgba(16,185,129,0.02));">
                            <div style="font-size:28px;font-weight:800;color:#10b981;"><?= $todayListings ?></div>
                            <div style="font-size:12px;color:var(--text-muted);font-weight:600;margin-top:4px;">Listings
                                Today</div>
                        </div>
                        <div class="card"
                            style="padding:20px;text-align:center;background:linear-gradient(135deg,rgba(59,130,246,0.05),rgba(59,130,246,0.02));">
                            <div style="font-size:28px;font-weight:800;color:#3b82f6;"><?= $todayUsers ?></div>
                            <div style="font-size:12px;color:var(--text-muted);font-weight:600;margin-top:4px;">Users
                                Today</div>
                        </div>
                        <div class="card"
                            style="padding:20px;text-align:center;background:linear-gradient(135deg,rgba(245,158,11,0.05),rgba(245,158,11,0.02));">
                            <div style="font-size:28px;font-weight:800;color:#f59e0b;"><?= $todayReviews ?></div>
                            <div style="font-size:12px;color:var(--text-muted);font-weight:600;margin-top:4px;">Reviews
                                Today</div>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:20px;">
                        <!-- Listing Growth Chart (30 days) -->
                        <div class="card fade-in">
                            <div class="card-header">
                                <div>
                                    <h3 class="card-title">Listing Growth</h3>
                                    <p class="card-subtitle">New listings — last 30 days</p>
                                </div>
                                <span class="badge badge-info"><i class="fas fa-chart-bar"
                                        style="margin-right:4px;"></i>30 Days</span>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height:220px;">
                                    <canvas id="listingGrowthChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Top Cities -->
                        <div class="card fade-in fade-in-delay-1">
                            <div class="card-header">
                                <div>
                                    <h3 class="card-title">Top Cities</h3>
                                    <p class="card-subtitle">By listing count</p>
                                </div>
                            </div>
                            <div style="padding:0;">
                                <?php foreach ($topCities as $idx => $city): ?>
                                <div
                                    style="display:flex;align-items:center;gap:12px;padding:10px 20px;border-bottom:1px solid rgba(0,0,0,0.04);">
                                    <div
                                        style="width:24px;height:24px;border-radius:50%;background:<?= $idx < 3 ? '#3b82f6' : '#e2e8f0' ?>;color:<?= $idx < 3 ? '#fff' : '#64748b' ?>;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;">
                                        <?= $idx + 1 ?>
                                    </div>
                                    <div style="flex:1;font-size:13px;font-weight:600;color:var(--text-primary);">
                                        <?= htmlspecialchars($city['city']) ?>
                                    </div>
                                    <div style="font-size:12px;font-weight:700;color:#3b82f6;">
                                        <?= number_format($city['count']) ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php if (empty($topCities)): ?>
                                <div style="padding:30px;text-align:center;color:var(--text-muted);font-size:13px;">No
                                    city data available</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($topRated)): ?>
                    <!-- Top Rated Listings -->
                    <div class="card fade-in" style="margin-top:20px;">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title">Top Rated Listings</h3>
                                <p class="card-subtitle">Highest rated with 2+ reviews</p>
                            </div>
                        </div>
                        <div style="padding:0;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Listing</th>
                                        <th>City</th>
                                        <th>Rating</th>
                                        <th>Reviews</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topRated as $tr): ?>
                                    <tr>
                                        <td style="font-weight:600;"><?= htmlspecialchars($tr['name']) ?></td>
                                        <td><span
                                                class="badge badge-info"><?= htmlspecialchars($tr['city'] ?? 'N/A') ?></span>
                                        </td>
                                        <td><span style="color:#f59e0b;font-weight:700;">⭐
                                                <?= $tr['avg_rating'] ?></span></td>
                                        <td><?= $tr['review_count'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <script>
    // Animated count-up for stat values
    document.querySelectorAll('.stat-value[data-count]').forEach(el => {
        const target = parseInt(el.dataset.count);
        const duration = 1200;
        const start = performance.now();

        function update(now) {
            const elapsed = now - start;
            const progress = Math.min(elapsed / duration, 1);
            const ease = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.floor(target * ease).toLocaleString();
            if (progress < 1) requestAnimationFrame(update);
        }
        requestAnimationFrame(update);
    });

    // User Growth Chart
    new Chart(document.getElementById('userGrowthChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($growthLabels); ?>,
            datasets: [{
                label: 'New Users',
                data: <?php echo json_encode($growthData); ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.08)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 11,
                            family: 'Inter'
                        },
                        color: '#94a3b8'
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.04)'
                    },
                    ticks: {
                        font: {
                            size: 11,
                            family: 'Inter'
                        },
                        color: '#94a3b8',
                        stepSize: 1
                    }
                }
            }
        }
    });

    // Category Doughnut Chart
    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($catLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($catData); ?>,
                backgroundColor: [
                    '#10b981', '#3b82f6', '#f59e0b', '#ef4444',
                    '#8b5cf6', '#06b6d4', '#f97316', '#ec4899',
                    '#14b8a6', '#6366f1'
                ],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 14,
                        font: {
                            size: 11,
                            family: 'Inter'
                        },
                        color: '#64748b',
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                }
            }
        }
    });

    // Listing Growth Chart (30 days bar)
    new Chart(document.getElementById('listingGrowthChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($listingGrowthLabels); ?>,
            datasets: [{
                label: 'New Listings',
                data: <?php echo json_encode($listingGrowthData); ?>,
                backgroundColor: 'rgba(59,130,246,0.6)',
                borderRadius: 4,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 10
                        },
                        color: '#94a3b8',
                        maxTicksLimit: 15
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.04)'
                    },
                    ticks: {
                        font: {
                            size: 11
                        },
                        color: '#94a3b8'
                    }
                }
            }
        }
    });
    </script>
</body>

</html>