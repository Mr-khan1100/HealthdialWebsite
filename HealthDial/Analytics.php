<?php
require_once 'connection.inc.php';
requireAdmin();

// === USER GROWTH (30 days) ===
$growthLabels = [];
$growthData = [];
for($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $growthLabels[] = date('d M', strtotime($date));
    $cnt = $conn->query("SELECT COUNT(*) as c FROM users WHERE DATE(created_at) = '$date'")->fetch_assoc()['c'];
    $growthData[] = (int)$cnt;
}

// === LISTING GROWTH (30 days) ===
$listingLabels = [];
$listingData = [];
for($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $listingLabels[] = date('d M', strtotime($date));
    $cnt = $conn->query("SELECT COUNT(*) as c FROM listings WHERE DATE(created_at) = '$date'")->fetch_assoc()['c'];
    $listingData[] = (int)$cnt;
}

// === CATEGORY DISTRIBUTION ===
$catLabels = [];
$catData = [];
$catRes = $conn->query("SELECT c.name, COUNT(l.id) as cnt FROM categories c LEFT JOIN listings l ON c.id = l.category_id GROUP BY c.id ORDER BY cnt DESC");
if($catRes) while($r = $catRes->fetch_assoc()) { $catLabels[] = $r['name']; $catData[] = (int)$r['cnt']; }

// === REVIEW RATINGS DISTRIBUTION ===
$ratingLabels = ['5 Stars','4 Stars','3 Stars','2 Stars','1 Star'];
$ratingData = [];
for($i = 5; $i >= 1; $i--) {
    $ratingData[] = (int)$conn->query("SELECT COUNT(*) as c FROM reviews WHERE rating = $i")->fetch_assoc()['c'];
}

// === USER STATES ===
$stateLabels = [];
$stateData = [];
$stateRes = $conn->query("SELECT state, COUNT(*) as cnt FROM users WHERE state != '' GROUP BY state ORDER BY cnt DESC LIMIT 10");
if($stateRes) while($r = $stateRes->fetch_assoc()) { $stateLabels[] = $r['state']; $stateData[] = (int)$r['cnt']; }

// === NOTIFICATION STATS ===
$notifSent = (int)$conn->query("SELECT COUNT(*) as c FROM notification_queue WHERE status='sent'")->fetch_assoc()['c'];
$notifPending = (int)$conn->query("SELECT COUNT(*) as c FROM notification_queue WHERE status='pending'")->fetch_assoc()['c'];
$notifFailed = (int)$conn->query("SELECT COUNT(*) as c FROM notification_queue WHERE status='failed'")->fetch_assoc()['c'];

// === MEDICATION FREQUENCY ===
$medDaily = (int)$conn->query("SELECT COUNT(*) as c FROM medications WHERE frequency='daily'")->fetch_assoc()['c'];
$medWeekly = (int)$conn->query("SELECT COUNT(*) as c FROM medications WHERE frequency='weekly'")->fetch_assoc()['c'];
$medCustom = (int)$conn->query("SELECT COUNT(*) as c FROM medications WHERE frequency='custom'")->fetch_assoc()['c'];

// === KEY METRICS ===
$totalUsers = (int)$conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$thisWeekUsers = (int)$conn->query("SELECT COUNT(*) as c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'];
$lastWeekUsers = (int)$conn->query("SELECT COUNT(*) as c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'];
$growthPct = $lastWeekUsers > 0 ? round(($thisWeekUsers - $lastWeekUsers) / $lastWeekUsers * 100, 1) : 0;

$totalListings = (int)$conn->query("SELECT COUNT(*) as c FROM listings")->fetch_assoc()['c'];
$avgRating = $conn->query("SELECT ROUND(AVG(rating), 1) as avg FROM reviews")->fetch_assoc()['avg'] ?? 0;
$totalMeds = (int)$conn->query("SELECT COUNT(*) as c FROM medications WHERE is_active=1")->fetch_assoc()['c'];

// === LISTING STATUS ===
$lsPending = (int)$conn->query("SELECT COUNT(*) as c FROM listings WHERE status='pending'")->fetch_assoc()['c'];
$lsApproved = (int)$conn->query("SELECT COUNT(*) as c FROM listings WHERE status='approved'")->fetch_assoc()['c'];
$lsRejected = (int)$conn->query("SELECT COUNT(*) as c FROM listings WHERE status='rejected'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics — HealthDial Admin</title>
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
                <div class="page-header">
                    <h1 class="page-title"><i class="fas fa-chart-bar" style="color:var(--accent);margin-right:8px;"></i>Analytics</h1>
                    <p class="page-subtitle">In-depth platform metrics and engagement data</p>
                </div>

                <!-- Key Metrics -->
                <div class="stat-grid">
                    <div class="stat-card emerald fade-in">
                        <div class="stat-info">
                            <h3>Total Users</h3>
                            <p class="stat-value"><?php echo $totalUsers; ?></p>
                            <div class="stat-change <?php echo $growthPct >= 0 ? 'up' : 'down'; ?>">
                                <i class="fas fa-arrow-<?php echo $growthPct >= 0 ? 'up' : 'down'; ?>" style="font-size:10px;"></i>
                                <?php echo abs($growthPct); ?>% vs last week
                            </div>
                        </div>
                        <div class="stat-icon emerald"><i class="fas fa-users"></i></div>
                    </div>
                    <div class="stat-card blue fade-in fade-in-delay-1">
                        <div class="stat-info">
                            <h3>Total Listings</h3>
                            <p class="stat-value"><?php echo $totalListings; ?></p>
                            <div class="stat-change up"><i class="fas fa-hospital" style="font-size:10px;"></i> <?php echo $lsApproved; ?> approved</div>
                        </div>
                        <div class="stat-icon blue"><i class="fas fa-hospital"></i></div>
                    </div>
                    <div class="stat-card amber fade-in fade-in-delay-2">
                        <div class="stat-info">
                            <h3>Avg Rating</h3>
                            <p class="stat-value"><?php echo $avgRating ?: '—'; ?></p>
                            <div class="stat-change up"><i class="fas fa-star" style="font-size:10px;"></i> out of 5</div>
                        </div>
                        <div class="stat-icon amber"><i class="fas fa-star"></i></div>
                    </div>
                    <div class="stat-card purple fade-in fade-in-delay-3">
                        <div class="stat-info">
                            <h3>Active Medications</h3>
                            <p class="stat-value"><?php echo $totalMeds; ?></p>
                            <div class="stat-change up"><i class="fas fa-pills" style="font-size:10px;"></i> being tracked</div>
                        </div>
                        <div class="stat-icon purple"><i class="fas fa-pills"></i></div>
                    </div>
                </div>

                <!-- Charts Row 1 -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                    <div class="card fade-in">
                        <div class="card-header">
                            <div><h3 class="card-title">User Growth</h3><p class="card-subtitle">New registrations — 30 days</p></div>
                        </div>
                        <div class="card-body"><div class="chart-container"><canvas id="userGrowthChart"></canvas></div></div>
                    </div>
                    <div class="card fade-in fade-in-delay-1">
                        <div class="card-header">
                            <div><h3 class="card-title">Listing Growth</h3><p class="card-subtitle">New listings — 30 days</p></div>
                        </div>
                        <div class="card-body"><div class="chart-container"><canvas id="listingGrowthChart"></canvas></div></div>
                    </div>
                </div>

                <!-- Charts Row 2 -->
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:20px;">
                    <div class="card fade-in">
                        <div class="card-header"><h3 class="card-title">Categories</h3></div>
                        <div class="card-body"><div class="chart-container" style="height:240px;"><canvas id="catChart"></canvas></div></div>
                    </div>
                    <div class="card fade-in fade-in-delay-1">
                        <div class="card-header"><h3 class="card-title">Review Ratings</h3></div>
                        <div class="card-body"><div class="chart-container" style="height:240px;"><canvas id="ratingChart"></canvas></div></div>
                    </div>
                    <div class="card fade-in fade-in-delay-2">
                        <div class="card-header"><h3 class="card-title">Users by State</h3></div>
                        <div class="card-body"><div class="chart-container" style="height:240px;"><canvas id="stateChart"></canvas></div></div>
                    </div>
                </div>

                <!-- Charts Row 3 -->
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">
                    <div class="card fade-in">
                        <div class="card-header"><h3 class="card-title">Notification Status</h3></div>
                        <div class="card-body">
                            <div class="chart-container" style="height:200px;"><canvas id="notifChart"></canvas></div>
                            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:12px;">
                                <div style="text-align:center;padding:8px;background:rgba(16,185,129,0.06);border-radius:8px;">
                                    <div style="font-size:20px;font-weight:800;color:var(--status-success);"><?php echo $notifSent; ?></div>
                                    <div style="font-size:10px;color:var(--text-muted);font-weight:600;">SENT</div>
                                </div>
                                <div style="text-align:center;padding:8px;background:rgba(245,158,11,0.06);border-radius:8px;">
                                    <div style="font-size:20px;font-weight:800;color:var(--status-warning);"><?php echo $notifPending; ?></div>
                                    <div style="font-size:10px;color:var(--text-muted);font-weight:600;">PENDING</div>
                                </div>
                                <div style="text-align:center;padding:8px;background:rgba(239,68,68,0.06);border-radius:8px;">
                                    <div style="font-size:20px;font-weight:800;color:var(--status-danger);"><?php echo $notifFailed; ?></div>
                                    <div style="font-size:10px;color:var(--text-muted);font-weight:600;">FAILED</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card fade-in fade-in-delay-1">
                        <div class="card-header"><h3 class="card-title">Medication Frequency</h3></div>
                        <div class="card-body"><div class="chart-container" style="height:240px;"><canvas id="medChart"></canvas></div></div>
                    </div>
                    <div class="card fade-in fade-in-delay-2">
                        <div class="card-header"><h3 class="card-title">Listing Status</h3></div>
                        <div class="card-body"><div class="chart-container" style="height:240px;"><canvas id="listingStatusChart"></canvas></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const colors = ['#10b981','#3b82f6','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#ec4899','#14b8a6','#6366f1'];
    const chartFont = { family: 'Inter', size: 11 };

    // User Growth
    new Chart(document.getElementById('userGrowthChart'), {
        type: 'line',
        data: { labels: <?php echo json_encode($growthLabels); ?>, datasets: [{ label: 'Users', data: <?php echo json_encode($growthData); ?>, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.06)', fill: true, tension: 0.4, borderWidth: 2, pointRadius: 2, pointHoverRadius: 5 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { font: chartFont, color: '#94a3b8', maxTicksLimit: 10 } }, y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.03)' }, ticks: { font: chartFont, color: '#94a3b8', stepSize: 1 } } } }
    });

    // Listing Growth
    new Chart(document.getElementById('listingGrowthChart'), {
        type: 'line',
        data: { labels: <?php echo json_encode($listingLabels); ?>, datasets: [{ label: 'Listings', data: <?php echo json_encode($listingData); ?>, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.06)', fill: true, tension: 0.4, borderWidth: 2, pointRadius: 2, pointHoverRadius: 5 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { font: chartFont, color: '#94a3b8', maxTicksLimit: 10 } }, y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.03)' }, ticks: { font: chartFont, color: '#94a3b8', stepSize: 1 } } } }
    });

    // Category Doughnut
    new Chart(document.getElementById('catChart'), {
        type: 'doughnut', data: { labels: <?php echo json_encode($catLabels); ?>, datasets: [{ data: <?php echo json_encode($catData); ?>, backgroundColor: colors, borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 10, font: {size:10, family:'Inter'}, usePointStyle: true, pointStyle: 'circle' } } } }
    });

    // Review Ratings Bar
    new Chart(document.getElementById('ratingChart'), {
        type: 'bar', data: { labels: <?php echo json_encode($ratingLabels); ?>, datasets: [{ data: <?php echo json_encode($ratingData); ?>, backgroundColor: ['#10b981','#34d399','#fbbf24','#f59e0b','#ef4444'], borderRadius: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.03)' }, ticks: { font: chartFont, stepSize: 1 } }, y: { grid: { display: false }, ticks: { font: chartFont } } } }
    });

    // Users by State
    new Chart(document.getElementById('stateChart'), {
        type: 'bar', data: { labels: <?php echo json_encode($stateLabels); ?>, datasets: [{ data: <?php echo json_encode($stateData); ?>, backgroundColor: '#6366f1', borderRadius: 4 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { font: {size:9, family:'Inter'}, maxRotation: 45 } }, y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.03)' }, ticks: { font: chartFont, stepSize: 1 } } } }
    });

    // Notification Status
    new Chart(document.getElementById('notifChart'), {
        type: 'doughnut', data: { labels: ['Sent','Pending','Failed'], datasets: [{ data: [<?php echo "$notifSent,$notifPending,$notifFailed"; ?>], backgroundColor: ['#10b981','#f59e0b','#ef4444'], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false } } }
    });

    // Medication Frequency
    new Chart(document.getElementById('medChart'), {
        type: 'doughnut', data: { labels: ['Daily','Weekly','Custom'], datasets: [{ data: [<?php echo "$medDaily,$medWeekly,$medCustom"; ?>], backgroundColor: ['#10b981','#3b82f6','#8b5cf6'], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 12, font: {size:11, family:'Inter'}, usePointStyle: true, pointStyle: 'circle' } } } }
    });

    // Listing Status
    new Chart(document.getElementById('listingStatusChart'), {
        type: 'doughnut', data: { labels: ['Approved','Pending','Rejected'], datasets: [{ data: [<?php echo "$lsApproved,$lsPending,$lsRejected"; ?>], backgroundColor: ['#10b981','#f59e0b','#ef4444'], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 12, font: {size:11, family:'Inter'}, usePointStyle: true, pointStyle: 'circle' } } } }
    });
    </script>
</body>
</html>
