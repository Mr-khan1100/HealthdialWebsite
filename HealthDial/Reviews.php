<?php
require_once 'connection.inc.php';
requireLogin();

// Handle approval/rejection with prepared statements
if (isset($_GET['approve']) && intval($_GET['approve']) > 0) {
    $id = intval($_GET['approve']);
    $stmt = $conn->prepare("UPDATE reviews SET status = 1, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $_SESSION['success'] = "Review approved!";
    header("Location: Reviews.php"); exit();
}

if (isset($_GET['reject']) && intval($_GET['reject']) > 0) {
    $id = intval($_GET['reject']);
    $stmt = $conn->prepare("UPDATE reviews SET status = 2, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $_SESSION['success'] = "Review rejected!";
    header("Location: Reviews.php"); exit();
}

if (isset($_GET['delete']) && intval($_GET['delete']) > 0) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $_SESSION['success'] = "Review deleted!";
    header("Location: Reviews.php"); exit();
}

// Stats
$totalR = (int)$conn->query("SELECT COUNT(*) as c FROM reviews")->fetch_assoc()['c'];
$pendingR = (int)$conn->query("SELECT COUNT(*) as c FROM reviews WHERE status = 0")->fetch_assoc()['c'];
$approvedR = (int)$conn->query("SELECT COUNT(*) as c FROM reviews WHERE status = 1")->fetch_assoc()['c'];
$rejectedR = (int)$conn->query("SELECT COUNT(*) as c FROM reviews WHERE status = 2")->fetch_assoc()['c'];

$result = $conn->query("
    SELECT r.*, u.name as user_name, u.email as user_email, l.name as listing_name
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN listings l ON r.listing_id = l.id
    ORDER BY r.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews — HealthDial Admin</title>
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
                    <h1 class="page-title">Reviews</h1>
                    <p class="page-subtitle">Moderate user reviews for listings</p>
                </div>

                <!-- Stats -->
                <div class="stat-grid" style="grid-template-columns: repeat(4, 1fr);">
                    <div class="stat-card blue fade-in">
                        <div class="stat-info"><h3>Total</h3><p class="stat-value"><?php echo $totalR; ?></p></div>
                        <div class="stat-icon blue"><i class="fas fa-comments"></i></div>
                    </div>
                    <div class="stat-card amber fade-in fade-in-delay-1">
                        <div class="stat-info"><h3>Pending</h3><p class="stat-value"><?php echo $pendingR; ?></p></div>
                        <div class="stat-icon amber"><i class="fas fa-clock"></i></div>
                    </div>
                    <div class="stat-card emerald fade-in fade-in-delay-2">
                        <div class="stat-info"><h3>Approved</h3><p class="stat-value"><?php echo $approvedR; ?></p></div>
                        <div class="stat-icon emerald"><i class="fas fa-check"></i></div>
                    </div>
                    <div class="stat-card rose fade-in fade-in-delay-3">
                        <div class="stat-info"><h3>Rejected</h3><p class="stat-value"><?php echo $rejectedR; ?></p></div>
                        <div class="stat-icon rose"><i class="fas fa-times"></i></div>
                    </div>
                </div>

                <div class="card fade-in">
                    <div style="padding:16px 24px;border-bottom:1px solid var(--border-light);">
                        <div class="action-bar" style="margin:0;">
                            <div class="action-bar-left">
                                <div class="search-box">
                                    <i class="fas fa-search"></i>
                                    <input type="text" id="searchInput" class="form-input" placeholder="Search reviews...">
                                </div>
                                <select id="statusFilter" class="form-select" style="width:140px;">
                                    <option value="all">All Status</option>
                                    <option value="0">Pending</option>
                                    <option value="1">Approved</option>
                                    <option value="2">Rejected</option>
                                </select>
                                <select id="ratingFilter" class="form-select" style="width:130px;">
                                    <option value="all">All Ratings</option>
                                    <option value="5">5 Stars</option>
                                    <option value="4">4 Stars</option>
                                    <option value="3">3 Stars</option>
                                    <option value="2">2 Stars</option>
                                    <option value="1">1 Star</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width:40px;"><input type="checkbox" id="selectAll" style="accent-color:var(--primary);"></th>
                                    <th>User & Review</th>
                                    <th>Listing</th>
                                    <th>Rating</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="reviewsBody">
                                <?php while($row = $result->fetch_assoc()): 
                                    $statusMap = ['0'=>'pending','1'=>'approved','2'=>'rejected'];
                                    $badgeMap = ['0'=>'badge-warning','1'=>'badge-success','2'=>'badge-danger'];
                                    $st = (string)$row['status'];
                                ?>
                                <tr class="review-row" data-status="<?php echo $st; ?>" data-rating="<?php echo $row['rating']; ?>">
                                    <td><input type="checkbox" class="review-cb" value="<?php echo $row['id']; ?>" style="accent-color:var(--primary);"></td>
                                    <td>
                                        <div style="display:flex;align-items:flex-start;gap:10px;max-width:320px;">
                                            <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:700;flex-shrink:0;">
                                                <?php echo strtoupper(substr($row['user_name'] ?? '?', 0, 1)); ?>
                                            </div>
                                            <div style="min-width:0;">
                                                <div style="font-weight:600;font-size:13px;"><?php echo htmlspecialchars($row['user_name'] ?: 'Deleted User'); ?></div>
                                                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                                    <?php echo htmlspecialchars($row['review']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-size:13px;font-weight:500;"><?php echo htmlspecialchars($row['listing_name'] ?: 'Deleted'); ?></td>
                                    <td>
                                        <div class="star-rating" style="font-size:12px;">
                                            <?php for($i=1;$i<=5;$i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $row['rating'] ? '' : 'empty'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td><span class="badge <?php echo $badgeMap[$st] ?? 'badge-gray'; ?>"><?php echo ucfirst($statusMap[$st] ?? 'unknown'); ?></span></td>
                                    <td style="font-size:12px;color:var(--text-muted);">
                                        <?php echo date('d M Y', strtotime($row['created_at'])); ?><br>
                                        <?php echo date('h:i A', strtotime($row['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:4px;">
                                            <?php if($st === '0'): ?>
                                            <a href="?approve=<?php echo $row['id']; ?>" class="btn btn-ghost btn-icon btn-sm" style="color:var(--status-success);" title="Approve"><i class="fas fa-check"></i></a>
                                            <a href="?reject=<?php echo $row['id']; ?>" class="btn btn-ghost btn-icon btn-sm" style="color:var(--status-warning);" title="Reject"><i class="fas fa-times"></i></a>
                                            <?php endif; ?>
                                            <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-ghost btn-icon btn-sm" style="color:var(--status-danger);" title="Delete" onclick="return confirm('Delete this review?')"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php if($totalR === 0): ?>
                                <tr><td colspan="7">
                                    <div class="empty-state"><div class="empty-state-icon"><i class="fas fa-comments"></i></div><h3>No reviews yet</h3><p>Reviews will appear here when users submit them.</p></div>
                                </td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Select all
    document.getElementById('selectAll').addEventListener('change', e => {
        document.querySelectorAll('.review-cb').forEach(cb => cb.checked = e.target.checked);
    });

    // Filters
    document.getElementById('statusFilter').addEventListener('change', filterReviews);
    document.getElementById('ratingFilter').addEventListener('change', filterReviews);
    document.getElementById('searchInput').addEventListener('input', filterReviews);

    function filterReviews() {
        const status = document.getElementById('statusFilter').value;
        const rating = document.getElementById('ratingFilter').value;
        const search = document.getElementById('searchInput').value.toLowerCase();
        document.querySelectorAll('.review-row').forEach(row => {
            const s = status === 'all' || row.dataset.status === status;
            const r = rating === 'all' || row.dataset.rating === rating;
            const t = !search || row.textContent.toLowerCase().includes(search);
            row.style.display = (s && r && t) ? '' : 'none';
        });
    }
    </script>
</body>
</html>