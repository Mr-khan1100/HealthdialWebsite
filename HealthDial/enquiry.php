<?php
require_once 'connection.inc.php';
requireAdmin();

// Handle delete
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM enquiries WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Enquiry deleted!";
    } else {
        $_SESSION['error'] = "Error deleting enquiry.";
    }
    $stmt->close();
    header("Location: enquiry.php");
    exit();
}

$enquiries = $conn->query("SELECT * FROM enquiries ORDER BY created_at DESC");
$totalEnquiries = $enquiries->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enquiries — HealthDial Admin</title>
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
                    <h1 class="page-title">Enquiries</h1>
                    <p class="page-subtitle"><?php echo $totalEnquiries; ?> enquir<?php echo $totalEnquiries !== 1 ? 'ies' : 'y'; ?> received</p>
                </div>

                <div class="card fade-in">
                    <div style="padding:16px 24px;border-bottom:1px solid var(--border-light);">
                        <div class="action-bar" style="margin:0;">
                            <div class="action-bar-left">
                                <div class="search-box">
                                    <i class="fas fa-search"></i>
                                    <input type="text" id="searchInput" class="form-input" placeholder="Search enquiries...">
                                </div>
                            </div>
                            <span class="badge badge-info"><?php echo $totalEnquiries; ?> total</span>
                        </div>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Subject</th>
                                    <th>Message</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="enquiryBody">
                                <?php if($totalEnquiries > 0): ?>
                                <?php while($row = $enquiries->fetch_assoc()): ?>
                                <tr class="enquiry-row">
                                    <td style="font-weight:600;color:var(--text-muted);">#<?php echo $row['id']; ?></td>
                                    <td>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($row['name']); ?></div>
                                        <div style="font-size:11px;color:var(--text-muted);"><?php echo htmlspecialchars($row['email']); ?></div>
                                    </td>
                                    <td style="font-size:13px;"><?php echo htmlspecialchars($row['mobile']); ?></td>
                                    <td><span class="badge badge-purple"><?php echo htmlspecialchars($row['subject'] ?? 'General'); ?></span></td>
                                    <td style="max-width:250px;font-size:12px;color:var(--text-muted);">
                                        <div style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                                            <?php echo htmlspecialchars($row['message']); ?>
                                        </div>
                                    </td>
                                    <td style="font-size:12px;color:var(--text-muted);"><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                                    <td>
                                        <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-ghost btn-icon btn-sm" style="color:var(--status-danger);" onclick="return confirm('Delete this enquiry?')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr><td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fas fa-envelope-open-text"></i></div>
                                        <h3>No enquiries yet</h3>
                                        <p>Enquiries from the app will appear here.</p>
                                    </div>
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
    document.getElementById('searchInput').addEventListener('input', function() {
        const search = this.value.toLowerCase();
        document.querySelectorAll('.enquiry-row').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(search) ? '' : 'none';
        });
    });
    </script>
</body>
</html>
