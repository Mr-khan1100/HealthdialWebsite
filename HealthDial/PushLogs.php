<?php
require_once 'connection.inc.php';
requireLogin();

// Handle resend failed
if(isset($_GET['resend']) && intval($_GET['resend']) > 0) {
    $resendId = intval($_GET['resend']);
    $stmt = $conn->prepare("UPDATE notification_queue SET status='pending', sent_at=NULL WHERE id=? AND status='failed'");
    $stmt->bind_param("i", $resendId);
    $stmt->execute();
    if($stmt->affected_rows > 0) {
        $_SESSION['success'] = "Notification #$resendId re-queued!";
    }
    header("Location: PushLogs.php"); exit();
}

// Handle delete
if(isset($_GET['delete']) && intval($_GET['delete']) > 0) {
    $delId = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM notification_queue WHERE id=?");
    $stmt->bind_param("i", $delId);
    $stmt->execute();
    $_SESSION['success'] = "Notification deleted.";
    header("Location: PushLogs.php"); exit();
}

// Handle purge old
if(isset($_GET['purge']) && $_GET['purge'] === 'old') {
    $conn->query("DELETE FROM notification_queue WHERE status='sent' AND sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $_SESSION['success'] = "Old sent notifications purged!";
    header("Location: PushLogs.php"); exit();
}

// Search & filter
$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? 'all';
$filterType = $_GET['type'] ?? 'all';
$filterDate = $_GET['date'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = "WHERE 1";
$params = [];
$types = "";

if(!empty($search)) {
    $where .= " AND (nq.title LIKE ? OR nq.message LIKE ?)";
    $s = "%{$search}%";
    $params = array_merge($params, [$s, $s]);
    $types .= "ss";
}
if($filterStatus !== 'all') {
    $where .= " AND nq.status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}
if($filterType !== 'all') {
    $where .= " AND nq.notification_type = ?";
    $params[] = $filterType;
    $types .= "s";
}
if(!empty($filterDate)) {
    $where .= " AND DATE(nq.created_at) = ?";
    $params[] = $filterDate;
    $types .= "s";
}

$countSql = "SELECT COUNT(*) as total FROM notification_queue nq $where";
$cStmt = $conn->prepare($countSql);
if(!empty($params)) $cStmt->bind_param($types, ...$params);
$cStmt->execute();
$totalNotifs = $cStmt->get_result()->fetch_assoc()['total'];
$totalPages = max(1, ceil($totalNotifs / $perPage));

$sql = "SELECT nq.*, u.name as user_name FROM notification_queue nq LEFT JOIN users u ON nq.user_id = u.id $where ORDER BY nq.created_at DESC LIMIT ?, ?";
$types2 = $types . "ii";
$params2 = array_merge($params, [$offset, $perPage]);
$stmt = $conn->prepare($sql);
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$result = $stmt->get_result();

// === STATS ===
$statSent = (int)$conn->query("SELECT COUNT(*) as c FROM notification_queue WHERE status='sent'")->fetch_assoc()['c'];
$statPending = (int)$conn->query("SELECT COUNT(*) as c FROM notification_queue WHERE status='pending'")->fetch_assoc()['c'];
$statFailed = (int)$conn->query("SELECT COUNT(*) as c FROM notification_queue WHERE status='failed'")->fetch_assoc()['c'];
$totalAll = $statSent + $statPending + $statFailed;
$deliveryRate = $totalAll > 0 ? round(($statSent / $totalAll) * 100, 1) : 0;
$failRate = $totalAll > 0 ? round(($statFailed / $totalAll) * 100, 1) : 0;
$totalPushTokens = (int)$conn->query("SELECT COUNT(*) as c FROM users WHERE expo_push_token IS NOT NULL AND expo_push_token != ''")->fetch_assoc()['c'];
$totalUsers = (int)$conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$tokenRate = $totalUsers > 0 ? round(($totalPushTokens / $totalUsers) * 100) : 0;

// Today's sent
$todaySent = (int)$conn->query("SELECT COUNT(*) as c FROM notification_queue WHERE DATE(sent_at) = CURDATE() AND status='sent'")->fetch_assoc()['c'];

// Last 14 days sent+failed
$dailySentData = [];
$dailyFailData = [];
$dailyLabels = [];
for($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dailyLabels[] = date('d M', strtotime($date));
    $dailySentData[] = (int)$conn->query("SELECT COUNT(*) as c FROM notification_queue WHERE DATE(sent_at) = '$date' AND status='sent'")->fetch_assoc()['c'];
    $dailyFailData[] = (int)$conn->query("SELECT COUNT(*) as c FROM notification_queue WHERE DATE(created_at) = '$date' AND status='failed'")->fetch_assoc()['c'];
}

// By type breakdown
$typeLabels = [];
$typeData = [];
$typeRes = $conn->query("SELECT notification_type, COUNT(*) as cnt FROM notification_queue GROUP BY notification_type ORDER BY cnt DESC");
if($typeRes) while($t = $typeRes->fetch_assoc()) {
    $typeLabels[] = ucfirst($t['notification_type'] ?? 'manual');
    $typeData[] = (int)$t['cnt'];
}

// Hourly distribution (what time are sent)
$hourData = array_fill(0, 24, 0);
$hourRes = $conn->query("SELECT HOUR(sent_at) as hr, COUNT(*) as cnt FROM notification_queue WHERE status='sent' AND sent_at IS NOT NULL GROUP BY HOUR(sent_at)");
if($hourRes) while($h = $hourRes->fetch_assoc()) {
    $hourData[(int)$h['hr']] = (int)$h['cnt'];
}
$hourLabels = [];
for($h = 0; $h < 24; $h++) {
    $hourLabels[] = sprintf('%02d:00', $h);
}

// Average delivery time (seconds between created_at and sent_at)
$avgDelivery = $conn->query("SELECT ROUND(AVG(TIMESTAMPDIFF(SECOND, created_at, sent_at))) as avg_sec FROM notification_queue WHERE status='sent' AND sent_at IS NOT NULL AND created_at IS NOT NULL")->fetch_assoc()['avg_sec'];
if($avgDelivery !== null) {
    if($avgDelivery < 60) $avgDelStr = round($avgDelivery) . 's';
    elseif($avgDelivery < 3600) $avgDelStr = round($avgDelivery / 60, 1) . 'm';
    else $avgDelStr = round($avgDelivery / 3600, 1) . 'h';
} else {
    $avgDelStr = '—';
}

// Notification types for filter dropdown
$ntypes = [];
$ntRes = $conn->query("SELECT DISTINCT notification_type FROM notification_queue WHERE notification_type IS NOT NULL ORDER BY notification_type");
if($ntRes) while($n = $ntRes->fetch_assoc()) $ntypes[] = $n['notification_type'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Push Analytics — HealthDial Admin</title>
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
                <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <h1 class="page-title"><i class="fas fa-broadcast-tower" style="color:var(--primary);margin-right:8px;"></i>Push Analytics</h1>
                        <p class="page-subtitle">Delivery tracking, analytics, and notification management</p>
                    </div>
                    <a href="?purge=old" class="btn btn-secondary btn-sm" onclick="return confirm('Purge sent notifications older than 30 days?')">
                        <i class="fas fa-broom"></i> Purge Old
                    </a>
                </div>

                <!-- Stats Grid -->
                <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:20px;">
                    <div class="stat-card emerald fade-in">
                        <div class="stat-info"><h3>Sent</h3><p class="stat-value"><?php echo $statSent; ?></p></div>
                        <div class="stat-icon emerald"><i class="fas fa-check-circle"></i></div>
                    </div>
                    <div class="stat-card amber fade-in fade-in-delay-1">
                        <div class="stat-info"><h3>Pending</h3><p class="stat-value"><?php echo $statPending; ?></p></div>
                        <div class="stat-icon amber"><i class="fas fa-clock"></i></div>
                    </div>
                    <div class="stat-card rose fade-in fade-in-delay-2">
                        <div class="stat-info"><h3>Failed</h3><p class="stat-value"><?php echo $statFailed; ?></p>
                            <?php if($failRate > 0): ?><div class="stat-change down"><?php echo $failRate; ?>% fail rate</div><?php endif; ?>
                        </div>
                        <div class="stat-icon rose"><i class="fas fa-exclamation-circle"></i></div>
                    </div>
                    <div class="stat-card blue fade-in fade-in-delay-3">
                        <div class="stat-info"><h3>Delivery Rate</h3><p class="stat-value"><?php echo $deliveryRate; ?>%</p></div>
                        <div class="stat-icon blue"><i class="fas fa-percentage"></i></div>
                    </div>
                    <div class="stat-card purple fade-in">
                        <div class="stat-info"><h3>Avg Delivery</h3><p class="stat-value"><?php echo $avgDelStr; ?></p></div>
                        <div class="stat-icon purple"><i class="fas fa-stopwatch"></i></div>
                    </div>
                    <div class="stat-card emerald fade-in fade-in-delay-1">
                        <div class="stat-info"><h3>Reachable</h3><p class="stat-value"><?php echo $totalPushTokens; ?></p>
                            <div class="stat-change up"><?php echo $tokenRate; ?>% of users</div>
                        </div>
                        <div class="stat-icon emerald"><i class="fas fa-mobile-alt"></i></div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div style="display:grid;grid-template-columns:1.5fr 0.5fr;gap:20px;margin-bottom:20px;">
                    <!-- 14 Day Trend -->
                    <div class="card fade-in">
                        <div class="card-header"><div><h3 class="card-title">Delivery Trend — 14 Days</h3><p class="card-subtitle">Sent vs Failed notifications</p></div></div>
                        <div class="card-body"><div class="chart-container"><canvas id="trendChart"></canvas></div></div>
                    </div>
                    <!-- Type Breakdown -->
                    <div class="card fade-in fade-in-delay-1">
                        <div class="card-header"><h3 class="card-title">By Type</h3></div>
                        <div class="card-body"><div class="chart-container" style="height:200px;"><canvas id="typeChart"></canvas></div></div>
                    </div>
                </div>

                <!-- Hourly Heatmap -->
                <div class="card fade-in" style="margin-bottom:20px;">
                    <div class="card-header"><div><h3 class="card-title">Hourly Send Distribution</h3><p class="card-subtitle">When notifications are delivered throughout the day</p></div></div>
                    <div class="card-body"><div class="chart-container" style="height:180px;"><canvas id="hourChart"></canvas></div></div>
                </div>

                <!-- Table -->
                <div class="card fade-in">
                    <div style="padding:16px 24px;border-bottom:1px solid var(--border-light);">
                        <form method="GET" class="action-bar" style="margin:0;">
                            <div class="action-bar-left">
                                <div class="search-box"><i class="fas fa-search"></i><input type="text" name="search" class="form-input" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>"></div>
                                <select name="status" class="form-select" style="width:120px;">
                                    <option value="all">All Status</option>
                                    <option value="sent" <?php echo $filterStatus==='sent'?'selected':''; ?>>Sent</option>
                                    <option value="pending" <?php echo $filterStatus==='pending'?'selected':''; ?>>Pending</option>
                                    <option value="failed" <?php echo $filterStatus==='failed'?'selected':''; ?>>Failed</option>
                                </select>
                                <select name="type" class="form-select" style="width:130px;">
                                    <option value="all">All Types</option>
                                    <?php foreach($ntypes as $nt): ?>
                                    <option value="<?php echo htmlspecialchars($nt); ?>" <?php echo $filterType===$nt?'selected':''; ?>><?php echo ucfirst($nt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="date" name="date" class="form-input" style="width:150px;" value="<?php echo $filterDate; ?>">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                        </form>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Title &amp; Message</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Scheduled</th>
                                    <th>Delivered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()):
                                    // PHP 7.2 compatible status badge
                                    $sBadge = 'badge-gray';
                                    if($row['status'] === 'sent') $sBadge = 'badge-success';
                                    elseif($row['status'] === 'pending') $sBadge = 'badge-warning';
                                    elseif($row['status'] === 'failed') $sBadge = 'badge-danger';
                                    
                                    // Delivery time
                                    $delTime = '—';
                                    if($row['status'] === 'sent' && $row['sent_at'] && $row['created_at']) {
                                        $diff = strtotime($row['sent_at']) - strtotime($row['created_at']);
                                        if($diff < 60) $delTime = $diff . 's';
                                        elseif($diff < 3600) $delTime = round($diff/60) . 'm';
                                        else $delTime = round($diff/3600, 1) . 'h';
                                    }
                                ?>
                                <tr>
                                    <td style="font-weight:600;color:var(--text-muted);">#<?php echo $row['id']; ?></td>
                                    <td>
                                        <?php if($row['user_id'] && $row['user_name']): ?>
                                        <a href="UserDetail.php?id=<?php echo $row['user_id']; ?>" style="display:flex;align-items:center;gap:6px;text-decoration:none;color:var(--text-primary);">
                                            <div style="width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-size:9px;font-weight:700;flex-shrink:0;">
                                                <?php echo strtoupper(substr($row['user_name'],0,1)); ?>
                                            </div>
                                            <span style="font-size:12px;font-weight:600;"><?php echo htmlspecialchars($row['user_name']); ?></span>
                                        </a>
                                        <?php else: ?>
                                        <span style="font-size:12px;color:var(--text-muted);">All users</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width:250px;">
                                        <div style="font-weight:600;font-size:12px;margin-bottom:2px;"><?php echo htmlspecialchars($row['title'] ?? ''); ?></div>
                                        <div style="font-size:11px;color:var(--text-muted);display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden;">
                                            <?php echo htmlspecialchars($row['message'] ?? ''); ?>
                                        </div>
                                    </td>
                                    <td><span class="badge badge-purple" style="font-size:10px;"><?php echo ucfirst($row['notification_type'] ?? 'manual'); ?></span></td>
                                    <td>
                                        <span class="badge <?php echo $sBadge; ?>"><?php echo ucfirst($row['status']); ?></span>
                                        <?php if($row['status'] === 'sent'): ?>
                                        <div style="font-size:9px;color:var(--text-muted);margin-top:2px;"><i class="fas fa-bolt" style="color:var(--primary);"></i> <?php echo $delTime; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:11px;color:var(--text-muted);white-space:nowrap;"><?php echo $row['scheduled_time'] ? date('d M, h:i A', strtotime($row['scheduled_time'])) : '—'; ?></td>
                                    <td style="font-size:11px;color:var(--text-muted);white-space:nowrap;"><?php echo $row['sent_at'] ? date('d M, h:i A', strtotime($row['sent_at'])) : '—'; ?></td>
                                    <td>
                                        <div style="display:flex;gap:4px;">
                                            <?php if($row['status'] === 'failed'): ?>
                                            <a href="?resend=<?php echo $row['id']; ?>" class="btn btn-ghost btn-icon btn-sm" title="Re-queue" style="color:var(--primary);"><i class="fas fa-redo"></i></a>
                                            <?php endif; ?>
                                            <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-ghost btn-icon btn-sm" title="Delete" style="color:var(--status-danger);" onclick="return confirm('Delete this notification log?')"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr><td colspan="8">
                                    <div class="empty-state"><div class="empty-state-icon"><i class="fas fa-bell-slash"></i></div><h3>No notifications found</h3><p>Adjust your filters or check back later.</p></div>
                                </td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if($totalPages > 1): ?>
                    <div class="pagination" style="padding:16px 24px;">
                        <div class="pagination-info">Showing <?php echo $offset+1; ?> to <?php echo min($offset+$perPage, $totalNotifs); ?> of <?php echo $totalNotifs; ?></div>
                        <div class="pagination-links">
                            <?php if($page > 1): ?><a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $filterStatus; ?>&type=<?php echo $filterType; ?>&date=<?php echo $filterDate; ?>"><i class="fas fa-chevron-left" style="font-size:11px;"></i></a><?php endif; ?>
                            <?php for($i=max(1,$page-3); $i<=min($totalPages,$page+3); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $filterStatus; ?>&type=<?php echo $filterType; ?>&date=<?php echo $filterDate; ?>" class="<?php echo $i===$page?'active':''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <?php if($page < $totalPages): ?><a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $filterStatus; ?>&type=<?php echo $filterType; ?>&date=<?php echo $filterDate; ?>"><i class="fas fa-chevron-right" style="font-size:11px;"></i></a><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    const chartFont = {size:10, family:'Inter'};

    // 14-day Trend Chart (Sent vs Failed)
    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($dailyLabels); ?>,
            datasets: [
                { label: 'Sent', data: <?php echo json_encode($dailySentData); ?>, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.06)', fill: true, tension: 0.4, borderWidth: 2, pointRadius: 3, pointHoverRadius: 6 },
                { label: 'Failed', data: <?php echo json_encode($dailyFailData); ?>, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.06)', fill: true, tension: 0.4, borderWidth: 2, pointRadius: 3, pointHoverRadius: 6 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top', labels: { font: chartFont, usePointStyle: true, pointStyle: 'circle', padding: 14 } } },
            scales: { x: { grid: { display: false }, ticks: { font: chartFont, color: '#94a3b8', maxTicksLimit: 8 } }, y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.03)' }, ticks: { font: chartFont, color: '#94a3b8', stepSize: 1 } } }
        }
    });

    // Type Breakdown (Doughnut)
    new Chart(document.getElementById('typeChart'), {
        type: 'doughnut',
        data: { labels: <?php echo json_encode($typeLabels); ?>, datasets: [{ data: <?php echo json_encode($typeData); ?>, backgroundColor: ['#10b981','#3b82f6','#f59e0b','#6366f1','#ec4899','#06b6d4'], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { font: chartFont, usePointStyle: true, pointStyle: 'circle', padding: 10 } } } }
    });

    // Hourly Distribution (Bar)
    new Chart(document.getElementById('hourChart'), {
        type: 'bar',
        data: { labels: <?php echo json_encode($hourLabels); ?>, datasets: [{ label: 'Notifications', data: <?php echo json_encode($hourData); ?>, backgroundColor: '#6366f1', borderRadius: 3 }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { grid: { display: false }, ticks: { font: {size:9, family:'Inter'}, color:'#94a3b8', maxRotation: 0 } }, y: { beginAtZero: true, grid: { color:'rgba(0,0,0,0.03)' }, ticks: { font: chartFont, stepSize: 1 } } }
        }
    });
    </script>
</body>
</html>
