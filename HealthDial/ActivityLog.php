<?php
require_once 'connection.inc.php';
requireLogin();

if($_SESSION['admin_role'] !== 'admin') {
    header("Location: Dashboard.php");
    exit();
}

// Clear old logs (optional — keep last 90 days)
if(isset($_GET['clear']) && $_GET['clear'] === 'old') {
    $conn->query("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $_SESSION['success'] = "Old logs cleared!";
    header("Location: ActivityLog.php");
    exit();
}

// Filters
$search = trim($_GET['search'] ?? '');
$filterAction = $_GET['action_type'] ?? 'all';
$filterDate = $_GET['date'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = "WHERE 1";
$params = [];
$types = "";

if(!empty($search)) {
    $where .= " AND (al.action LIKE ? OR al.details LIKE ? OR au.name LIKE ?)";
    $s = "%{$search}%";
    $params = array_merge($params, [$s,$s,$s]);
    $types .= "sss";
}
if($filterAction !== 'all') {
    $where .= " AND al.action = ?";
    $params[] = $filterAction;
    $types .= "s";
}
if(!empty($filterDate)) {
    $where .= " AND DATE(al.created_at) = ?";
    $params[] = $filterDate;
    $types .= "s";
}

// Count
$countSql = "SELECT COUNT(*) as total FROM activity_logs al LEFT JOIN admin_users au ON al.admin_id = au.id $where";
$cStmt = $conn->prepare($countSql);
if(!empty($params)) $cStmt->bind_param($types, ...$params);
$cStmt->execute();
$totalLogs = $cStmt->get_result()->fetch_assoc()['total'];
$totalPages = max(1, ceil($totalLogs / $perPage));

// Fetch
$sql = "SELECT al.*, au.name as admin_name, au.role as admin_role 
        FROM activity_logs al 
        LEFT JOIN admin_users au ON al.admin_id = au.id 
        $where 
        ORDER BY al.created_at DESC 
        LIMIT ?, ?";
$types2 = $types . "ii";
$params2 = array_merge($params, [$offset, $perPage]);
$stmt = $conn->prepare($sql);
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$result = $stmt->get_result();

// Action types for filter
$actions = [];
$actRes = $conn->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
if($actRes) while($a = $actRes->fetch_assoc()) $actions[] = $a['action'];

// Stats
$todayLogs = (int)$conn->query("SELECT COUNT(*) as c FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'];
$weekLogs = (int)$conn->query("SELECT COUNT(*) as c FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'];
$uniqueAdmins = (int)$conn->query("SELECT COUNT(DISTINCT admin_id) as c FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log — HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'sidebar.php'; ?>
        <div class="admin-main">
            <?php include 'header.php'; ?>
            <div class="admin-content">
                <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <h1 class="page-title"><i class="fas fa-history" style="color:var(--accent);margin-right:8px;"></i>Activity Log</h1>
                        <p class="page-subtitle">Complete audit trail of admin actions</p>
                    </div>
                    <a href="?clear=old" class="btn btn-secondary btn-sm" onclick="return confirm('Delete logs older than 90 days?')">
                        <i class="fas fa-broom"></i> Clear Old Logs
                    </a>
                </div>

                <div class="stat-grid" style="grid-template-columns:repeat(3,1fr);">
                    <div class="stat-card emerald fade-in">
                        <div class="stat-info"><h3>Today</h3><p class="stat-value"><?php echo $todayLogs; ?></p></div>
                        <div class="stat-icon emerald"><i class="fas fa-calendar-day"></i></div>
                    </div>
                    <div class="stat-card blue fade-in fade-in-delay-1">
                        <div class="stat-info"><h3>This Week</h3><p class="stat-value"><?php echo $weekLogs; ?></p></div>
                        <div class="stat-icon blue"><i class="fas fa-calendar-week"></i></div>
                    </div>
                    <div class="stat-card purple fade-in fade-in-delay-2">
                        <div class="stat-info"><h3>Active Admins</h3><p class="stat-value"><?php echo $uniqueAdmins; ?></p></div>
                        <div class="stat-icon purple"><i class="fas fa-user-shield"></i></div>
                    </div>
                </div>

                <div class="card fade-in">
                    <div style="padding:16px 24px;border-bottom:1px solid var(--border-light);">
                        <form method="GET" class="action-bar" style="margin:0;">
                            <div class="action-bar-left">
                                <div class="search-box">
                                    <i class="fas fa-search"></i>
                                    <input type="text" name="search" class="form-input" placeholder="Search actions..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <select name="action_type" class="form-select" style="width:160px;">
                                    <option value="all">All Actions</option>
                                    <?php foreach($actions as $act): ?>
                                    <option value="<?php echo htmlspecialchars($act); ?>" <?php echo $filterAction===$act?'selected':''; ?>><?php echo ucfirst(str_replace('_',' ',$act)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="date" name="date" class="form-input" style="width:160px;" value="<?php echo $filterDate; ?>">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                        </form>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Admin</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($result->num_rows > 0): ?>
                                <?php while($log = $result->fetch_assoc()):
                                    $iconMap = [
                                        'login'=>'fa-sign-in-alt','logout'=>'fa-sign-out-alt',
                                        'delete_user'=>'fa-user-times','update_profile'=>'fa-user-edit',
                                        'update_settings'=>'fa-cog','delete_listing'=>'fa-trash',
                                        'approve_listing'=>'fa-check','reject_listing'=>'fa-times'
                                    ];
                                    $colorMap = [
                                        'login'=>'badge-success','logout'=>'badge-gray',
                                        'delete_user'=>'badge-danger','update_profile'=>'badge-info',
                                        'update_settings'=>'badge-purple','delete_listing'=>'badge-danger'
                                    ];
                                    $icon = $iconMap[$log['action']] ?? 'fa-circle';
                                    $badgeClass = $colorMap[$log['action']] ?? 'badge-gray';
                                ?>
                                <tr>
                                    <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
                                        <?php echo date('d M Y', strtotime($log['created_at'])); ?><br>
                                        <span style="font-weight:600;"><?php echo date('h:i:s A', strtotime($log['created_at'])); ?></span>
                                    </td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700;">
                                                <?php echo strtoupper(substr($log['admin_name'] ?? 'S', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:600;font-size:13px;"><?php echo htmlspecialchars($log['admin_name'] ?? 'System'); ?></div>
                                                <div style="font-size:11px;color:var(--text-muted);"><?php echo ucfirst($log['admin_role'] ?? ''); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badgeClass; ?>">
                                            <i class="fas <?php echo $icon; ?>" style="font-size:10px;"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                        </span>
                                    </td>
                                    <td style="font-size:13px;max-width:300px;"><?php echo htmlspecialchars($log['details'] ?? '—'); ?></td>
                                    <td style="font-size:12px;color:var(--text-muted);font-family:monospace;"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr><td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fas fa-history"></i></div>
                                        <h3>No activity logs</h3>
                                        <p>Admin actions will be logged here automatically.</p>
                                    </div>
                                </td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if($totalPages > 1): ?>
                    <div class="pagination" style="padding:16px 24px;">
                        <div class="pagination-info">Showing <?php echo $offset+1; ?> to <?php echo min($offset+$perPage, $totalLogs); ?> of <?php echo $totalLogs; ?></div>
                        <div class="pagination-links">
                            <?php if($page > 1): ?><a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&action_type=<?php echo $filterAction; ?>&date=<?php echo $filterDate; ?>"><i class="fas fa-chevron-left" style="font-size:11px;"></i></a><?php endif; ?>
                            <?php for($i = max(1,$page-3); $i <= min($totalPages,$page+3); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&action_type=<?php echo $filterAction; ?>&date=<?php echo $filterDate; ?>" class="<?php echo $i===$page?'active':''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <?php if($page < $totalPages): ?><a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&action_type=<?php echo $filterAction; ?>&date=<?php echo $filterDate; ?>"><i class="fas fa-chevron-right" style="font-size:11px;"></i></a><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
