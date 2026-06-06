<?php
require_once 'connection.inc.php';
requireLogin();

// Handle delete medication
if (isset($_GET['delete']) && intval($_GET['delete']) > 0) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM medications WHERE id = ?");
    $stmt->bind_param("i", $id);
    if($stmt->execute()) {
        $_SESSION['success'] = "Medication deleted!";
    }
    header("Location: Medications.php");
    exit();
}

// Handle toggle active/inactive
if (isset($_GET['toggle']) && intval($_GET['toggle']) > 0) {
    $id = intval($_GET['toggle']);
    $stmt = $conn->prepare("UPDATE medications SET is_active = IF(is_active=1,0,1) WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $_SESSION['success'] = "Status updated!";
    header("Location: Medications.php");
    exit();
}

// Search & filter
$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? 'all';
$filterFreq = $_GET['freq'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build query
$where = "WHERE 1";
$params = [];
$types = "";

if(!empty($search)) {
    $where .= " AND (m.name LIKE ? OR u.name LIKE ? OR u.mobile LIKE ?)";
    $s = "%{$search}%";
    $params = array_merge($params, [$s,$s,$s]);
    $types .= "sss";
}
if($filterStatus === 'active') { $where .= " AND m.is_active = 1"; }
elseif($filterStatus === 'inactive') { $where .= " AND m.is_active = 0"; }

if($filterFreq !== 'all') {
    $where .= " AND m.frequency = ?";
    $params[] = $filterFreq;
    $types .= "s";
}

// Count
$countSql = "SELECT COUNT(*) as total FROM medications m LEFT JOIN users u ON m.user_id = u.id $where";
$countStmt = $conn->prepare($countSql);
if(!empty($params)) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalMeds = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = max(1, ceil($totalMeds / $perPage));

// Fetch medications
$sql = "SELECT m.*, u.name as user_name, u.mobile as user_mobile, u.email as user_email
        FROM medications m 
        LEFT JOIN users u ON m.user_id = u.id 
        $where 
        ORDER BY m.created_at DESC 
        LIMIT ?, ?";
$types2 = $types . "ii";
$params2 = array_merge($params, [$offset, $perPage]);
$mainStmt = $conn->prepare($sql);
$mainStmt->bind_param($types2, ...$params2);
$mainStmt->execute();
$result = $mainStmt->get_result();

// Stats
$totalAll = (int)$conn->query("SELECT COUNT(*) as c FROM medications")->fetch_assoc()['c'];
$totalActive = (int)$conn->query("SELECT COUNT(*) as c FROM medications WHERE is_active=1")->fetch_assoc()['c'];
$totalInactive = (int)$conn->query("SELECT COUNT(*) as c FROM medications WHERE is_active=0")->fetch_assoc()['c'];
$totalDaily = (int)$conn->query("SELECT COUNT(*) as c FROM medications WHERE frequency='daily'")->fetch_assoc()['c'];
$totalWeekly = (int)$conn->query("SELECT COUNT(*) as c FROM medications WHERE frequency='weekly'")->fetch_assoc()['c'];
$uniqueUsers = (int)$conn->query("SELECT COUNT(DISTINCT user_id) as c FROM medications")->fetch_assoc()['c'];

// Top medicines
$topMeds = [];
$topRes = $conn->query("SELECT name, COUNT(*) as cnt FROM medications GROUP BY name ORDER BY cnt DESC LIMIT 5");
if($topRes) while($r = $topRes->fetch_assoc()) $topMeds[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medications — HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'sidebar.php'; ?>
        <div class="admin-main">
            <?php include 'header.php'; ?>
            <div class="admin-content">
                <div class="page-header">
                    <h1 class="page-title"><i class="fas fa-pills" style="color:var(--primary);margin-right:8px;"></i>Medications</h1>
                    <p class="page-subtitle">View and manage all user medication schedules</p>
                </div>

                <!-- Stats -->
                <div class="stat-grid">
                    <div class="stat-card emerald fade-in">
                        <div class="stat-info">
                            <h3>Total Medications</h3>
                            <p class="stat-value"><?php echo $totalAll; ?></p>
                        </div>
                        <div class="stat-icon emerald"><i class="fas fa-pills"></i></div>
                    </div>
                    <div class="stat-card blue fade-in fade-in-delay-1">
                        <div class="stat-info">
                            <h3>Active</h3>
                            <p class="stat-value"><?php echo $totalActive; ?></p>
                        </div>
                        <div class="stat-icon blue"><i class="fas fa-check-circle"></i></div>
                    </div>
                    <div class="stat-card amber fade-in fade-in-delay-2">
                        <div class="stat-info">
                            <h3>Unique Users</h3>
                            <p class="stat-value"><?php echo $uniqueUsers; ?></p>
                        </div>
                        <div class="stat-icon amber"><i class="fas fa-user-friends"></i></div>
                    </div>
                    <div class="stat-card purple fade-in fade-in-delay-3">
                        <div class="stat-info">
                            <h3>Daily Schedules</h3>
                            <p class="stat-value"><?php echo $totalDaily; ?></p>
                        </div>
                        <div class="stat-icon purple"><i class="fas fa-calendar-day"></i></div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 280px;gap:20px;">
                    <!-- Main Table -->
                    <div class="card fade-in">
                        <div style="padding:16px 24px;border-bottom:1px solid var(--border-light);">
                            <form method="GET" class="action-bar" style="margin:0;">
                                <div class="action-bar-left">
                                    <div class="search-box">
                                        <i class="fas fa-search"></i>
                                        <input type="text" name="search" class="form-input" placeholder="Search medicine or user..." value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                    <select name="status" class="form-select" style="width:130px;">
                                        <option value="all" <?php echo $filterStatus==='all'?'selected':''; ?>>All Status</option>
                                        <option value="active" <?php echo $filterStatus==='active'?'selected':''; ?>>Active</option>
                                        <option value="inactive" <?php echo $filterStatus==='inactive'?'selected':''; ?>>Inactive</option>
                                    </select>
                                    <select name="freq" class="form-select" style="width:130px;">
                                        <option value="all" <?php echo $filterFreq==='all'?'selected':''; ?>>All Freq</option>
                                        <option value="daily" <?php echo $filterFreq==='daily'?'selected':''; ?>>Daily</option>
                                        <option value="weekly" <?php echo $filterFreq==='weekly'?'selected':''; ?>>Weekly</option>
                                        <option value="custom" <?php echo $filterFreq==='custom'?'selected':''; ?>>Custom</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                            </form>
                        </div>

                        <div style="overflow-x:auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Medicine</th>
                                        <th>User</th>
                                        <th>Dosage</th>
                                        <th>Frequency</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($result->num_rows > 0): ?>
                                    <?php while($row = $result->fetch_assoc()): 
                                        $reminderTimes = json_decode($row['reminder_times'], true);
                                        $timesStr = is_array($reminderTimes) ? implode(', ', $reminderTimes) : ($row['reminder_times'] ?? '—');
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:10px;">
                                                <div style="width:36px;height:36px;border-radius:8px;background:rgba(16,185,129,0.1);display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:14px;flex-shrink:0;">
                                                    <i class="fas fa-capsules"></i>
                                                </div>
                                                <div>
                                                    <div style="font-weight:600;font-size:13px;color:var(--text-primary);"><?php echo htmlspecialchars($row['name']); ?></div>
                                                    <div style="font-size:11px;color:var(--text-muted);"><?php echo htmlspecialchars($timesStr); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight:500;font-size:13px;"><?php echo htmlspecialchars($row['user_name'] ?? 'Deleted'); ?></div>
                                            <div style="font-size:11px;color:var(--text-muted);"><?php echo htmlspecialchars($row['user_mobile'] ?? ''); ?></div>
                                        </td>
                                        <td style="font-size:13px;font-weight:500;"><?php echo htmlspecialchars($row['dosage']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $row['frequency']==='daily' ? 'badge-success' : ($row['frequency']==='weekly' ? 'badge-info' : 'badge-purple'); ?>">
                                                <?php echo ucfirst($row['frequency']); ?>
                                            </span>
                                        </td>
                                        <td style="font-size:12px;color:var(--text-muted);">
                                            <?php echo date('d M Y', strtotime($row['start_date'])); ?>
                                            <?php if($row['end_date']): ?>
                                            <br>→ <?php echo date('d M Y', strtotime($row['end_date'])); ?>
                                            <?php else: ?>
                                            <br><span style="color:var(--primary);font-weight:500;">Ongoing</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $row['is_active'] ? 'badge-success' : 'badge-gray'; ?>">
                                                <?php echo $row['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display:flex;gap:4px;">
                                                <button onclick="viewMed(<?php echo $row['id']; ?>)" class="btn btn-ghost btn-icon btn-sm" title="View Details"><i class="fas fa-eye"></i></button>
                                                <a href="?toggle=<?php echo $row['id']; ?>" class="btn btn-ghost btn-icon btn-sm" style="color:var(--status-warning);" title="Toggle Status"><i class="fas fa-power-off"></i></a>
                                                <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-ghost btn-icon btn-sm" style="color:var(--status-danger);" title="Delete" onclick="return confirm('Delete this medication record?')"><i class="fas fa-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr><td colspan="7">
                                        <div class="empty-state">
                                            <div class="empty-state-icon"><i class="fas fa-pills"></i></div>
                                            <h3>No medications found</h3>
                                            <p>Medication data from the app will appear here.</p>
                                        </div>
                                    </td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if($totalPages > 1): ?>
                        <div class="pagination" style="padding:16px 24px;">
                            <div class="pagination-info">Showing <?php echo $offset+1; ?> to <?php echo min($offset+$perPage, $totalMeds); ?> of <?php echo $totalMeds; ?></div>
                            <div class="pagination-links">
                                <?php if($page > 1): ?><a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $filterStatus; ?>&freq=<?php echo $filterFreq; ?>"><i class="fas fa-chevron-left" style="font-size:11px;"></i></a><?php endif; ?>
                                <?php for($i = max(1, $page-3); $i <= min($totalPages, $page+3); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $filterStatus; ?>&freq=<?php echo $filterFreq; ?>" class="<?php echo $i===$page?'active':''; ?>"><?php echo $i; ?></a>
                                <?php endfor; ?>
                                <?php if($page < $totalPages): ?><a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $filterStatus; ?>&freq=<?php echo $filterFreq; ?>"><i class="fas fa-chevron-right" style="font-size:11px;"></i></a><?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right Sidebar: Top Medicines -->
                    <div>
                        <div class="card fade-in fade-in-delay-2">
                            <div class="card-header"><h3 class="card-title" style="font-size:14px;">Top Medicines</h3></div>
                            <div class="card-body" style="padding:16px;">
                                <?php if(count($topMeds) > 0): ?>
                                <?php foreach($topMeds as $idx => $med): ?>
                                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;<?php echo $idx < count($topMeds)-1 ? 'border-bottom:1px solid var(--border-light);' : ''; ?>">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div style="width:28px;height:28px;border-radius:6px;background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;color:var(--accent);font-size:11px;font-weight:700;"><?php echo $idx+1; ?></div>
                                        <span style="font-size:13px;font-weight:500;"><?php echo htmlspecialchars($med['name']); ?></span>
                                    </div>
                                    <span class="badge badge-info"><?php echo $med['cnt']; ?> users</span>
                                </div>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <p style="text-align:center;color:var(--text-muted);font-size:13px;padding:16px 0;">No data yet</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card fade-in fade-in-delay-3" style="margin-top:16px;">
                            <div class="card-header"><h3 class="card-title" style="font-size:14px;">Quick Stats</h3></div>
                            <div class="card-body" style="padding:16px;">
                                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-light);">
                                    <span style="font-size:12px;color:var(--text-muted);">Weekly Schedules</span>
                                    <span style="font-size:13px;font-weight:700;"><?php echo $totalWeekly; ?></span>
                                </div>
                                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-light);">
                                    <span style="font-size:12px;color:var(--text-muted);">Inactive</span>
                                    <span style="font-size:13px;font-weight:700;"><?php echo $totalInactive; ?></span>
                                </div>
                                <div style="display:flex;justify-content:space-between;padding:8px 0;">
                                    <span style="font-size:12px;color:var(--text-muted);">Avg per User</span>
                                    <span style="font-size:13px;font-weight:700;"><?php echo $uniqueUsers > 0 ? round($totalAll / $uniqueUsers, 1) : 0; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Medication Modal -->
    <div class="modal-overlay" id="medModalOverlay" onclick="if(event.target===this)document.getElementById('medModalOverlay').classList.remove('show')">
        <div class="modal" style="max-width:500px;">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-capsules" style="color:var(--primary);margin-right:8px;"></i>Medication Details</h3>
                <button class="modal-close" onclick="document.getElementById('medModalOverlay').classList.remove('show')"><i class="fas fa-times"></i></button>
            </div>
            <div id="medDetails"><div style="text-align:center;padding:30px;"><div class="loading-spinner" style="width:30px;height:30px;"></div></div></div>
        </div>
    </div>

    <script>
    function viewMed(id) {
        document.getElementById('medModalOverlay').classList.add('show');
        fetch('ajax.php?action=get_medication&id=' + id)
            .then(r => r.json())
            .then(d => {
                if(d.error) { document.getElementById('medDetails').innerHTML = '<p style="color:var(--status-danger);">'+d.error+'</p>'; return; }
                let times = '';
                try { const t = JSON.parse(d.reminder_times); times = Array.isArray(t) ? t.join(', ') : d.reminder_times; } catch(e) { times = d.reminder_times || '—'; }
                document.getElementById('medDetails').innerHTML = `
                    <div style="display:flex;align-items:center;gap:14px;padding:16px;background:var(--primary-50);border-radius:var(--radius-md);margin-bottom:16px;">
                        <div style="width:48px;height:48px;border-radius:12px;background:rgba(16,185,129,0.15);display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:20px;"><i class="fas fa-capsules"></i></div>
                        <div>
                            <div style="font-size:18px;font-weight:700;">${d.name}</div>
                            <div style="font-size:13px;color:var(--text-muted);">Prescribed to ${d.user_name || 'Unknown'}</div>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="card" style="padding:12px;"><div style="font-size:10px;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Dosage</div><div style="font-size:15px;font-weight:600;margin-top:4px;">${d.dosage}</div></div>
                        <div class="card" style="padding:12px;"><div style="font-size:10px;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Frequency</div><div style="font-size:15px;font-weight:600;margin-top:4px;">${d.frequency}</div></div>
                        <div class="card" style="padding:12px;"><div style="font-size:10px;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Start Date</div><div style="font-size:14px;font-weight:600;margin-top:4px;">${d.start_date}</div></div>
                        <div class="card" style="padding:12px;"><div style="font-size:10px;color:var(--text-muted);font-weight:600;text-transform:uppercase;">End Date</div><div style="font-size:14px;font-weight:600;margin-top:4px;">${d.end_date || 'Ongoing'}</div></div>
                    </div>
                    <div class="card" style="padding:12px;margin-top:12px;"><div style="font-size:10px;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Reminder Times</div><div style="font-size:14px;font-weight:500;margin-top:4px;">${times}</div></div>
                    ${d.notes ? '<div class="card" style="padding:12px;margin-top:12px;"><div style="font-size:10px;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Notes</div><div style="font-size:13px;margin-top:4px;">'+d.notes+'</div></div>' : ''}
                `;
            });
    }
    </script>
</body>
</html>
