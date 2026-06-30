<?php
require_once 'connection.inc.php';
requireLogin();
requireAccess('claims');

// Self-heal the claims table so this page works before the migration is run.
$conn->query("CREATE TABLE IF NOT EXISTS listing_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL, user_id INT NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    claimant_name VARCHAR(150) NULL, claimant_phone VARCHAR(30) NULL,
    claimant_email VARCHAR(190) NULL, note TEXT NULL, admin_note VARCHAR(255) NULL,
    reviewed_by INT NULL, reviewed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_listing (listing_id), INDEX idx_user (user_id), INDEX idx_status (status)
)");

$flash = '';
$flashType = 'success';

// ── Handle approve / reject ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $claimId   = (int) ($_POST['claim_id'] ?? 0);
    $adminId   = $_SESSION['admin_id'] ?? null;
    $adminNote = trim((string) ($_POST['admin_note'] ?? ''));
    $filter    = $_POST['status_filter'] ?? 'pending';

    // Load the claim.
    $cStmt = $conn->prepare("SELECT * FROM listing_claims WHERE id = ? LIMIT 1");
    $cStmt->bind_param('i', $claimId);
    $cStmt->execute();
    $claim = $cStmt->get_result()->fetch_assoc();
    $cStmt->close();

    if (!$claim || $claim['status'] !== 'pending') {
        $flash = 'This claim has already been reviewed.';
        $flashType = 'error';
    } elseif ($_POST['action'] === 'approve') {
        // Ensure the listing isn't already owned by someone else.
        $lStmt = $conn->prepare("SELECT user_id FROM listings WHERE id = ? LIMIT 1");
        $lStmt->bind_param('i', $claim['listing_id']);
        $lStmt->execute();
        $listingRow = $lStmt->get_result()->fetch_assoc();
        $lStmt->close();

        if (!$listingRow) {
            $flash = 'The listing for this claim no longer exists.';
            $flashType = 'error';
        } elseif (!empty($listingRow['user_id']) && (int) $listingRow['user_id'] !== (int) $claim['user_id']) {
            $flash = 'That listing is already owned by another user. Claim not approved.';
            $flashType = 'error';
        } else {
            $conn->begin_transaction();
            try {
                $u = $conn->prepare("UPDATE listings SET user_id = ? WHERE id = ?");
                $u->bind_param('ii', $claim['user_id'], $claim['listing_id']);
                $u->execute();
                $u->close();

                $a = $conn->prepare("UPDATE listing_claims SET status = 'approved', admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                $a->bind_param('sii', $adminNote, $adminId, $claimId);
                $a->execute();
                $a->close();

                // Auto-reject other pending claims for the same listing.
                $r = $conn->prepare("UPDATE listing_claims SET status = 'rejected', admin_note = 'Listing claimed by another user', reviewed_by = ?, reviewed_at = NOW() WHERE listing_id = ? AND status = 'pending' AND id <> ?");
                $r->bind_param('iii', $adminId, $claim['listing_id'], $claimId);
                $r->execute();
                $r->close();

                $conn->commit();
                $flash = 'Claim approved — the listing is now linked to the user.';
            } catch (Exception $e) {
                $conn->rollback();
                $flash = 'Could not approve the claim. Please try again.';
                $flashType = 'error';
            }
        }
    } elseif ($_POST['action'] === 'reject') {
        $a = $conn->prepare("UPDATE listing_claims SET status = 'rejected', admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $a->bind_param('sii', $adminNote, $adminId, $claimId);
        $a->execute();
        $a->close();
        $flash = 'Claim rejected.';
    }

    // PRG redirect, preserving the active filter + a flash.
    header('Location: ListingClaims.php?status=' . urlencode($filter) . '&msg=' . urlencode($flash) . '&type=' . $flashType);
    exit;
}

if (isset($_GET['msg'])) {
    $flash = $_GET['msg'];
    $flashType = ($_GET['type'] ?? 'success') === 'error' ? 'error' : 'success';
}

// ── Filter + fetch ──
$statusFilter = $_GET['status'] ?? 'pending';
if (!in_array($statusFilter, ['pending', 'approved', 'rejected', 'all'], true)) {
    $statusFilter = 'pending';
}

$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
$cntRes = $conn->query("SELECT status, COUNT(*) c FROM listing_claims GROUP BY status");
if ($cntRes) {
    while ($r = $cntRes->fetch_assoc()) {
        $counts[$r['status']] = (int) $r['c'];
    }
}

$where = $statusFilter === 'all' ? '' : "WHERE lc.status = '" . $conn->real_escape_string($statusFilter) . "'";
$claims = [];
$sql = "SELECT lc.*, l.name AS listing_name, l.city AS listing_city, l.user_id AS listing_owner,
               u.name AS user_name, u.mobile AS user_mobile, u.email AS user_email
        FROM listing_claims lc
        LEFT JOIN listings l ON l.id = lc.listing_id
        LEFT JOIN users u ON u.id = lc.user_id
        $where
        ORDER BY (lc.status = 'pending') DESC, lc.created_at DESC";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $claims[] = $row;
    }
}

function hd_claim_status_pill($status)
{
    $map = [
        'pending'  => ['#92400e', '#fef3c7', 'Pending'],
        'approved' => ['#065f46', '#d1fae5', 'Approved'],
        'rejected' => ['#991b1b', '#fee2e2', 'Rejected'],
    ];
    $s = $map[$status] ?? ['#374151', '#e5e7eb', ucfirst($status)];
    return '<span style="display:inline-block;padding:2px 10px;border-radius:99px;font-size:11px;font-weight:700;color:' . $s[0] . ';background:' . $s[1] . ';">' . $s[2] . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listing Claims — HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .claim-tabs { display:flex; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
        .claim-tab { padding:8px 16px; border-radius:99px; font-size:13px; font-weight:600; text-decoration:none;
            color:var(--text-secondary); background:var(--bg-card); border:1px solid var(--border-light); }
        .claim-tab.active { background:var(--primary); color:#fff; border-color:var(--primary); }
        .claim-tab .cnt { font-weight:700; }
        .claim-note { max-width:280px; font-size:12px; color:var(--text-secondary); white-space:pre-wrap; }
        .flash { padding:12px 16px; border-radius:10px; margin-bottom:18px; font-size:14px; font-weight:500; }
        .flash.success { background:#d1fae5; color:#065f46; }
        .flash.error { background:#fee2e2; color:#991b1b; }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'sidebar.php'; ?>
        <div class="admin-main">
            <?php include 'header.php'; ?>

            <div class="admin-content">
                <div class="page-header">
                    <h1 class="page-title"><i class="fas fa-handshake" style="color:var(--primary);margin-right:8px;"></i>Listing Claims</h1>
                    <p class="page-subtitle">Review requests from users claiming ownership of unclaimed listings</p>
                </div>

                <?php if ($flash): ?>
                <div class="flash <?php echo $flashType; ?>"><?php echo htmlspecialchars($flash); ?></div>
                <?php endif; ?>

                <div class="claim-tabs">
                    <?php
                    $tabs = [
                        'pending'  => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'all'      => 'All',
                    ];
                    foreach ($tabs as $key => $label):
                        $cnt = $key === 'all' ? array_sum($counts) : ($counts[$key] ?? 0);
                    ?>
                    <a href="ListingClaims.php?status=<?php echo $key; ?>"
                       class="claim-tab <?php echo $statusFilter === $key ? 'active' : ''; ?>">
                        <?php echo $label; ?> <span class="cnt">(<?php echo $cnt; ?>)</span>
                    </a>
                    <?php endforeach; ?>
                </div>

                <div class="card fade-in" style="padding:0; overflow:hidden;">
                    <div style="overflow-x:auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Listing</th>
                                    <th>Claimant</th>
                                    <th>Contact</th>
                                    <th>Message</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($claims)): ?>
                                <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted);">No claims found.</td></tr>
                                <?php else: foreach ($claims as $c): ?>
                                <tr>
                                    <td style="font-weight:600;color:var(--text-muted);">#<?php echo $c['id']; ?></td>
                                    <td>
                                        <div style="font-weight:600;color:var(--text-primary);">
                                            <?php echo htmlspecialchars($c['listing_name'] ?? ('Listing #' . $c['listing_id'])); ?>
                                        </div>
                                        <div style="font-size:11px;color:var(--text-muted);">
                                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($c['listing_city'] ?? ''); ?>
                                            · <a href="view-listing.php?id=<?php echo (int) $c['listing_id']; ?>" target="_blank">view</a>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($c['user_name'] ?? $c['claimant_name'] ?? 'User #' . $c['user_id']); ?></div>
                                        <div style="font-size:11px;color:var(--text-muted);">User #<?php echo (int) $c['user_id']; ?></div>
                                    </td>
                                    <td style="font-size:12px;">
                                        <?php echo htmlspecialchars($c['user_mobile'] ?? $c['claimant_phone'] ?? '—'); ?><br>
                                        <span style="color:var(--text-muted);"><?php echo htmlspecialchars($c['user_email'] ?? $c['claimant_email'] ?? ''); ?></span>
                                    </td>
                                    <td><div class="claim-note"><?php echo $c['note'] ? htmlspecialchars($c['note']) : '<span style="color:var(--text-muted);">—</span>'; ?></div></td>
                                    <td style="font-size:12px;color:var(--text-muted);"><?php echo date('d M Y, h:i A', strtotime($c['created_at'])); ?></td>
                                    <td>
                                        <?php echo hd_claim_status_pill($c['status']); ?>
                                        <?php if ($c['status'] !== 'pending' && !empty($c['admin_note'])): ?>
                                        <div style="font-size:10px;color:var(--text-muted);margin-top:4px;"><?php echo htmlspecialchars($c['admin_note']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($c['status'] === 'pending'): ?>
                                        <div style="display:flex;gap:6px;">
                                            <form method="POST" onsubmit="return confirm('Approve this claim and link the listing to this user?');">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="claim_id" value="<?php echo (int) $c['id']; ?>">
                                                <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">
                                                <button type="submit" class="btn btn-primary btn-sm" style="font-size:12px;"><i class="fas fa-check"></i> Approve</button>
                                            </form>
                                            <form method="POST" onsubmit="return hdRejectClaim(this);">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="claim_id" value="<?php echo (int) $c['id']; ?>">
                                                <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">
                                                <input type="hidden" name="admin_note" value="">
                                                <button type="submit" class="btn btn-ghost btn-sm" style="font-size:12px;color:#dc2626;"><i class="fas fa-times"></i> Reject</button>
                                            </form>
                                        </div>
                                        <?php else: ?>
                                        <span style="font-size:12px;color:var(--text-muted);">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function hdRejectClaim(form) {
            var reason = prompt('Optional: reason for rejecting this claim?');
            if (reason === null) return false; // cancelled
            form.querySelector('input[name="admin_note"]').value = reason;
            return true;
        }
    </script>
</body>
</html>
