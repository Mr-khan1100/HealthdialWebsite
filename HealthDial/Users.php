<?php
require_once 'connection.inc.php';
requireAdmin(); // Users section is admin-only (not staff).

/** Does a table have a given column? (avoids errors on optional/self-healed cols) */
function hd_admin_col_exists(mysqli $conn, string $table, string $col): bool
{
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($col);
    $res = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c' LIMIT 1");
    return $res && $res->num_rows > 0;
}

// Remove a user AND release their listings for claiming (admin-only, POST + confirm).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['remove_user'])) {
    $id = intval($_POST['remove_user']);
    if ($id <= 0) {
        $_SESSION['error'] = "Invalid user.";
        header("Location: Users.php");
        exit();
    }

    $conn->begin_transaction();
    try {
        // 1) Release this user's listings → user_id 0 = unclaimed, so "Claim" returns.
        $rel = $conn->prepare("UPDATE listings SET user_id = 0 WHERE user_id = ?");
        $rel->bind_param("i", $id);
        $rel->execute();
        $releasedListings = $rel->affected_rows;
        $rel->close();

        // 2) Detach payment records but keep them for audit (only if the column exists).
        foreach (['promotion_payments', 'listing_qr_payments'] as $payTbl) {
            if (hd_admin_col_exists($conn, $payTbl, 'user_id')) {
                $conn->query("UPDATE $payTbl SET user_id = NULL WHERE user_id = " . (int) $id);
            }
        }

        // 3) Remove their claim requests + login tokens.
        $conn->query("DELETE FROM listing_claims WHERE user_id = " . (int) $id);
        $conn->query("DELETE FROM user_tokens WHERE user_id = " . (int) $id);

        // 4) Delete the user record (email, phone, all details) — frees email/mobile for reuse.
        $del = $conn->prepare("DELETE FROM users WHERE id = ?");
        $del->bind_param("i", $id);
        $del->execute();
        $del->close();

        $conn->commit();

        // Log it.
        $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, action, details, ip_address) VALUES (?, 'remove_user', ?, ?)");
        $detail = "Removed user ID $id; released $releasedListings listing(s) for claiming";
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $logStmt->bind_param("iss", $_SESSION['admin_id'], $detail, $ip);
        $logStmt->execute();
        $logStmt->close();

        $_SESSION['success'] = "User removed. $releasedListings listing(s) released and available to claim again.";
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('remove_user failed: ' . $e->getMessage());
        $_SESSION['error'] = "Could not remove user (a linked record may be blocking it). Nothing was changed.";
    }
    header("Location: Users.php");
    exit();
}

// Search & Pagination
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build WHERE conditions
$where = "WHERE 1";
$params = [];
$types = "";

if(!empty($search)) {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

if($filter === 'recent') {
    $where .= " AND u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif($filter === 'active') {
    $where .= " AND u.status = 1";
}

// Count total
$countSql = "SELECT COUNT(DISTINCT u.id) as total FROM users u $where";
$countStmt = $conn->prepare($countSql);
if(!empty($params)) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalUsers = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalUsers / $perPage);

// Query users
$sql = "SELECT u.*, COUNT(r.id) as review_count 
        FROM users u 
        LEFT JOIN reviews r ON u.id = r.user_id 
        $where 
        GROUP BY u.id 
        ORDER BY u.created_at DESC 
        LIMIT ?, ?";
$mainStmt = $conn->prepare($sql);
$types2 = $types . "ii";
$params2 = array_merge($params, [$offset, $perPage]);
$mainStmt->bind_param($types2, ...$params2);
$mainStmt->execute();
$result = $mainStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users — HealthDial Admin</title>
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
                        <h1 class="page-title">Users</h1>
                        <p class="page-subtitle"><?php echo number_format($totalUsers); ?> registered users</p>
                    </div>
                    <a href="#" class="btn btn-secondary" onclick="exportCSV()">
                        <i class="fas fa-download"></i> Export CSV
                    </a>
                </div>

                <div class="card fade-in">
                    <!-- Filters -->
                    <div style="padding: 16px 24px; border-bottom: 1px solid var(--border-light);">
                        <form method="GET" class="action-bar" style="margin-bottom:0;">
                            <div class="action-bar-left">
                                <div class="search-box">
                                    <i class="fas fa-search"></i>
                                    <input type="text" name="search" class="form-input" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <select name="filter" class="form-select" style="width:160px;" onchange="this.form.submit()">
                                    <option value="all" <?php echo $filter==='all'?'selected':''; ?>>All Users</option>
                                    <option value="recent" <?php echo $filter==='recent'?'selected':''; ?>>Recent (7 days)</option>
                                    <option value="active" <?php echo $filter==='active'?'selected':''; ?>>Active</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </form>
                    </div>

                    <!-- Table -->
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Contact</th>
                                    <th>Auth</th>
                                    <th>State</th>
                                    <th>Location</th>
                                    <th>Joined</th>
                                    <th>Reviews</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight:600;color:var(--text-muted);">#<?php echo $row['id']; ?></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:700;flex-shrink:0;">
                                                <?php echo strtoupper(substr($row['name'],0,1)); ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:600;color:var(--text-primary);"><?php echo htmlspecialchars($row['name']); ?></div>
                                                <div style="font-size:11px;color:var(--text-muted);"><?php echo htmlspecialchars($row['email'] ?? ''); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-size:13px;"><?php echo htmlspecialchars($row['mobile'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if(!empty($row['google_id'])): ?>
                                        <span class="badge badge-info" style="font-size:10px;gap:3px;display:inline-flex;align-items:center;"><i class="fab fa-google" style="font-size:9px;"></i>Google</span>
                                        <?php else: ?>
                                        <span class="badge badge-gray" style="font-size:10px;"><i class="fas fa-mobile-alt" style="font-size:9px;margin-right:2px;"></i>Mobile</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge badge-info"><?php echo htmlspecialchars($row['state'] ?? 'N/A'); ?></span></td>
                                    <td>
                                        <?php if($row['latitude'] && $row['longitude']): ?>
                                        <a href="https://maps.google.com/?q=<?php echo $row['latitude']; ?>,<?php echo $row['longitude']; ?>" target="_blank" class="btn btn-ghost btn-sm" style="font-size:12px;">
                                            <i class="fas fa-map-marker-alt"></i> View
                                        </a>
                                        <?php else: ?>
                                        <span style="font-size:12px;color:var(--text-muted);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:12px;color:var(--text-muted);"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                                    <td><span class="badge badge-purple"><?php echo $row['review_count']; ?></span></td>
                                    <td>
                                        <div style="display:flex;gap:6px;">
                                            <a href="UserDetail.php?id=<?php echo $row['id']; ?>" class="btn btn-ghost btn-icon btn-sm" title="Full Profile">
                                                <i class="fas fa-id-card"></i>
                                            </a>
                                            <button onclick="viewUser(<?php echo $row['id']; ?>)" class="btn btn-ghost btn-icon btn-sm" title="Quick View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <form method="POST" style="display:inline;margin:0;"
                                                onsubmit="return confirm('Remove this user AND release their listings for claiming?\n\nThis permanently deletes their account (email, phone, details) and frees those for reuse. Their listings are NOT deleted — they become claimable again. This cannot be undone.');">
                                                <input type="hidden" name="remove_user" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="btn btn-ghost btn-icon btn-sm" style="color:var(--status-danger);" title="Remove user & release listings">
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="9">
                                        <div class="empty-state">
                                            <div class="empty-state-icon"><i class="fas fa-users"></i></div>
                                            <h3>No users found</h3>
                                            <p>Try adjusting your search or filter criteria.</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if($totalPages > 1): ?>
                    <div class="pagination" style="padding: 16px 24px;">
                        <div class="pagination-info">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalUsers); ?> of <?php echo $totalUsers; ?> users
                        </div>
                        <div class="pagination-links">
                            <?php if($page > 1): ?>
                            <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo $filter; ?>">
                                <i class="fas fa-chevron-left" style="font-size:11px;"></i>
                            </a>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $page - 3);
                            $end = min($totalPages, $start + 6);
                            for($i = $start; $i <= $end; $i++):
                            ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo $filter; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>

                            <?php if($page < $totalPages): ?>
                            <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo $filter; ?>">
                                <i class="fas fa-chevron-right" style="font-size:11px;"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- User View Modal -->
    <div class="modal-overlay" id="userModalOverlay" onclick="if(event.target===this) closeModal()">
        <div class="modal" style="max-width:480px;">
            <div class="modal-header">
                <h3 class="modal-title">User Details</h3>
                <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
            </div>
            <div id="userDetails">
                <div style="text-align:center;padding:20px;"><div class="loading-spinner" style="width:30px;height:30px;"></div></div>
            </div>
        </div>
    </div>

    <script>
    function viewUser(id) {
        document.getElementById('userModalOverlay').classList.add('show');
        fetch('ajax.php?action=get_user&id=' + id)
            .then(r => r.json())
            .then(data => {
                document.getElementById('userDetails').innerHTML = `
                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
                        <div style="width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;font-weight:700;">
                            ${data.name ? data.name.charAt(0).toUpperCase() : '?'}
                        </div>
                        <div>
                            <h4 style="font-size:18px;font-weight:700;margin:0;">${data.name}</h4>
                            <p style="font-size:13px;color:var(--text-muted);margin:0;">${data.email || 'No email'}</p>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="card" style="padding:14px;">
                            <div style="font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Mobile</div>
                            <div style="font-size:14px;font-weight:600;margin-top:4px;">${data.mobile || 'N/A'}</div>
                        </div>
                        <div class="card" style="padding:14px;">
                            <div style="font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Joined</div>
                            <div style="font-size:14px;font-weight:600;margin-top:4px;">${data.joined_date}</div>
                        </div>
                        <div class="card" style="padding:14px;">
                            <div style="font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Reviews</div>
                            <div style="font-size:14px;font-weight:600;margin-top:4px;">${data.review_count}</div>
                        </div>
                        <div class="card" style="padding:14px;">
                            <div style="font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Auth Method</div>
                            <div style="font-size:13px;font-weight:600;margin-top:4px;">${data.google_id ? '<span class="badge badge-info" style="font-size:10px;"><i class="fab fa-google"></i> Google</span>' : '<span class="badge badge-gray" style="font-size:10px;"><i class="fas fa-mobile-alt"></i> Mobile</span>'}</div>
                        </div>
                        ${data.latitude ? `
                        <div class="card" style="padding:14px;">
                            <div style="font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Location</div>
                            <a href="https://maps.google.com/?q=${data.latitude},${data.longitude}" target="_blank" class="btn btn-ghost btn-sm" style="margin-top:4px;font-size:12px;">
                                <i class="fas fa-map-marker-alt"></i> View Map
                            </a>
                        </div>` : ''}
                    </div>
                `;
            });
    }

    function closeModal() {
        document.getElementById('userModalOverlay').classList.remove('show');
    }

    function exportCSV() {
        // Build CSV of visible data
        const rows = document.querySelectorAll('.data-table tbody tr');
        let csv = 'ID,Name,Email,Mobile,State,Joined,Reviews\\n';
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if(cells.length >= 7) {
                const id = cells[0].textContent.trim().replace('#','');
                const name = cells[1].querySelector('div > div > div')?.textContent.trim() || '';
                const email = cells[1].querySelector('div > div > div:last-child')?.textContent.trim() || '';
                const mobile = cells[2].textContent.trim();
                const state = cells[3].textContent.trim();
                const joined = cells[5].textContent.trim();
                const reviews = cells[6].textContent.trim();
                csv += `"${id}","${name}","${email}","${mobile}","${state}","${joined}","${reviews}"\\n`;
            }
        });
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'healthdial_users_' + new Date().toISOString().slice(0,10) + '.csv';
        a.click();
        URL.revokeObjectURL(url);
    }
    </script>
</body>
</html>