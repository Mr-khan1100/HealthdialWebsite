<?php
require_once 'connection.inc.php';
requireAdmin();

if (session_status() === PHP_SESSION_NONE) session_start();

// ---- Ensure table + columns exist (self-healing) ----
$conn->query("CREATE TABLE IF NOT EXISTS listing_qr_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    razorpay_order_id VARCHAR(255) NOT NULL,
    razorpay_payment_id VARCHAR(255) DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 200.00,
    status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    paid_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_listing_id (listing_id),
    INDEX idx_status (status)
)");

$dbName = $conn->query("SELECT DATABASE() AS d")->fetch_assoc()['d'];
$dbEsc  = $conn->real_escape_string($dbName);

// Add `note` column if missing.
$noteChk = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA='$dbEsc' AND TABLE_NAME='listing_qr_payments' AND COLUMN_NAME='note'");
if ($noteChk && $noteChk->num_rows === 0) {
    @$conn->query("ALTER TABLE listing_qr_payments ADD COLUMN note VARCHAR(255) NULL");
}

// Widen status enum to allow 'cancelled' (reverted purchases).
$cancelStatus = 'pending';
$enumChk = $conn->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA='$dbEsc' AND TABLE_NAME='listing_qr_payments' AND COLUMN_NAME='status'");
if ($enumChk && ($et = $enumChk->fetch_assoc())) {
    if (stripos($et['COLUMN_TYPE'], 'cancelled') === false) {
        @$conn->query("ALTER TABLE listing_qr_payments MODIFY status ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending'");
    }
    // Re-check so we only ever write a value the column actually accepts.
    $et2 = $conn->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA='$dbEsc' AND TABLE_NAME='listing_qr_payments' AND COLUMN_NAME='status'");
    if ($et2 && ($r = $et2->fetch_assoc()) && stripos($r['COLUMN_TYPE'], 'cancelled') !== false) {
        $cancelStatus = 'cancelled';
    }
}

// Current configured QR price.
function qrc_get_price($conn)
{
    $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key='qr_code_price' LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        if ($row['setting_value'] !== null && $row['setting_value'] !== '') return (float) $row['setting_value'];
    }
    return 200.0;
}

// ---- AJAX listing search ----
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $q = '%' . trim($_GET['q'] ?? '') . '%';
    $stmt = $conn->prepare("SELECT id, name, city FROM listings WHERE name LIKE ? OR city LIKE ? OR id = ? ORDER BY name LIMIT 15");
    $idGuess = intval(trim($_GET['q'] ?? ''));
    $stmt->bind_param("ssi", $q, $q, $idGuess);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) $items[] = $row;
    echo json_encode($items);
    exit();
}

// ---- Actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Save QR price
    if (isset($_POST['set_qr_price'])) {
        $price = max(0, (float) $_POST['qr_price']);
        $val = number_format($price, 2, '.', '');
        $chk = $conn->prepare("SELECT setting_key FROM settings WHERE setting_key='qr_code_price' LIMIT 1");
        $chk->execute();
        $exists = $chk->get_result()->num_rows > 0;
        $chk->close();
        if ($exists) {
            $u = $conn->prepare("UPDATE settings SET setting_value=? WHERE setting_key='qr_code_price'");
            $u->bind_param('s', $val);
            $u->execute();
            $u->close();
        } else {
            $i = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('qr_code_price', ?)");
            $i->bind_param('s', $val);
            $i->execute();
            $i->close();
        }
        $_SESSION['success'] = "QR code price updated to ₹" . number_format($price, 0) . ".";
        header("Location: QrCodeConfig.php");
        exit();
    }

    // Manually unlock QR for a specific listing
    if (isset($_POST['manual_activate'])) {
        $listing_id = intval($_POST['listing_id']);
        if (!$listing_id) {
            $_SESSION['error'] = "Please select or enter a valid listing ID.";
            header("Location: QrCodeConfig.php");
            exit();
        }
        $lc = $conn->prepare("SELECT name FROM listings WHERE id=? LIMIT 1");
        $lc->bind_param('i', $listing_id);
        $lc->execute();
        $lrow = $lc->get_result()->fetch_assoc();
        $lc->close();

        if (!$lrow) {
            $_SESSION['error'] = "Listing #$listing_id not found.";
            header("Location: QrCodeConfig.php");
            exit();
        }

        // Already unlocked?
        $pc = $conn->prepare("SELECT id FROM listing_qr_payments WHERE listing_id=? AND status='paid' LIMIT 1");
        $pc->bind_param('i', $listing_id);
        $pc->execute();
        $alreadyPaid = $pc->get_result()->num_rows > 0;
        $pc->close();

        if ($alreadyPaid) {
            $_SESSION['success'] = "Listing #$listing_id (" . htmlspecialchars($lrow['name']) . ") already has its QR unlocked.";
        } else {
            $price  = qrc_get_price($conn);
            $txn    = 'MANUAL' . date('ymdHis');
            $payId  = 'MANUAL';
            $now    = date('Y-m-d H:i:s');
            $note   = 'Manually activated by admin';
            $ins = $conn->prepare("INSERT INTO listing_qr_payments
                (listing_id, razorpay_order_id, razorpay_payment_id, amount, status, paid_at, note)
                VALUES (?, ?, ?, ?, 'paid', ?, ?)");
            $ins->bind_param('issdss', $listing_id, $txn, $payId, $price, $now, $note);
            $ins->execute();
            $ins->close();
            $_SESSION['success'] = "QR unlocked for listing #$listing_id (" . htmlspecialchars($lrow['name']) . ").";
        }
        header("Location: QrCodeConfig.php");
        exit();
    }

    // Revert a paid transaction → unpaid (locks the QR again)
    if (isset($_POST['mark_unpaid'])) {
        $id = intval($_POST['id']);
        $note = 'Reverted to unpaid by admin';
        $u = $conn->prepare("UPDATE listing_qr_payments SET status=?, paid_at=NULL, note=? WHERE id=?");
        $u->bind_param('ssi', $cancelStatus, $note, $id);
        $u->execute();
        $u->close();
        $_SESSION['success'] = "Transaction #$id reverted to unpaid — that listing's QR is now locked.";
        header("Location: QrCodeConfig.php");
        exit();
    }

    // Mark an existing transaction as paid (re-activate)
    if (isset($_POST['mark_paid'])) {
        $id = intval($_POST['id']);
        $now = date('Y-m-d H:i:s');
        $note = 'Marked paid by admin';
        $u = $conn->prepare("UPDATE listing_qr_payments SET status='paid', paid_at=?, note=? WHERE id=?");
        $u->bind_param('ssi', $now, $note, $id);
        $u->execute();
        $u->close();
        $_SESSION['success'] = "Transaction #$id marked as paid — QR unlocked for that listing.";
        header("Location: QrCodeConfig.php");
        exit();
    }
}

// Delete a transaction
if (isset($_GET['delete_txn'])) {
    $id = intval($_GET['delete_txn']);
    $conn->query("DELETE FROM listing_qr_payments WHERE id = $id");
    $_SESSION['success'] = "Transaction removed.";
    header("Location: QrCodeConfig.php");
    exit();
}

// ---- Data ----
$qrPrice = qrc_get_price($conn);

$totalRevenue = $conn->query("SELECT COALESCE(SUM(amount),0) t FROM listing_qr_payments WHERE status='paid'")->fetch_assoc()['t'];
$paidCount    = $conn->query("SELECT COUNT(*) c FROM listing_qr_payments WHERE status='paid'")->fetch_assoc()['c'];
$pendingCount = $conn->query("SELECT COUNT(*) c FROM listing_qr_payments WHERE status='pending'")->fetch_assoc()['c'];

$txns = $conn->query("
    SELECT q.*, l.name AS listing_name, l.city
    FROM listing_qr_payments q
    LEFT JOIN listings l ON q.listing_id = l.id
    ORDER BY q.created_at DESC
    LIMIT 300
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Config — HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .search-dropdown { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #e5e7eb; border-radius:8px; max-height:220px; overflow-y:auto; z-index:50; display:none; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        .search-dropdown.active { display:block; }
        .search-item { padding:10px 14px; cursor:pointer; border-bottom:1px solid #f3f4f6; }
        .search-item:hover { background:#f0f7ff; }
        .search-item .city { font-size:12px; color:#9ca3af; }
    </style>
</head>
<body class="bg-gray-100">
<div class="flex h-screen">
<?php include 'sidebar.php'; ?>
<div class="flex-1 flex flex-col overflow-hidden">
<?php include 'header.php'; ?>
<main class="flex-1 overflow-y-auto p-6">

<?php if(isset($_SESSION['success'])): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
    <i class="fas fa-check-circle mr-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
</div>
<?php endif; ?>
<?php if(isset($_SESSION['error'])): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
    <i class="fas fa-exclamation-circle mr-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800"><i class="fas fa-qrcode mr-2 text-blue-500"></i>QR Code Configuration</h2>
    <p class="text-gray-500 text-sm mt-1">Set the unlock price, view QR purchases, and manually activate or revert a listing's QR.</p>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div><p class="text-sm text-gray-500">QR Revenue</p>
                <p class="text-2xl font-bold text-green-600">₹<?php echo number_format($totalRevenue, 0); ?></p></div>
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center"><i class="fas fa-rupee-sign text-green-600"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div><p class="text-sm text-gray-500">QR Unlocked</p>
                <p class="text-2xl font-bold text-blue-600"><?php echo $paidCount; ?></p></div>
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center"><i class="fas fa-unlock text-blue-600"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div><p class="text-sm text-gray-500">Pending / Abandoned</p>
                <p class="text-2xl font-bold text-yellow-600"><?php echo $pendingCount; ?></p></div>
            <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center"><i class="fas fa-clock text-yellow-600"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div><p class="text-sm text-gray-500">Current Price</p>
                <p class="text-2xl font-bold text-purple-600">₹<?php echo number_format($qrPrice, 0); ?></p></div>
            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center"><i class="fas fa-tag text-purple-600"></i></div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <!-- Price config -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4"><i class="fas fa-tag mr-2 text-purple-500"></i>QR Unlock Price</h3>
        <form method="POST" class="flex items-end gap-3">
            <input type="hidden" name="set_qr_price" value="1">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Price (₹) charged to unlock a QR code</label>
                <input type="number" name="qr_price" min="0" step="1" value="<?php echo htmlspecialchars(number_format($qrPrice, 0, '.', '')); ?>" required
                    class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple-500">
            </div>
            <button type="submit" class="bg-purple-600 text-white rounded-lg px-5 py-2 hover:bg-purple-700 transition font-medium">
                <i class="fas fa-save mr-1"></i> Save
            </button>
        </form>
        <p class="text-xs text-gray-400 mt-2">Applies to all new QR purchases on the website and add-listing flow.</p>
    </div>

    <!-- Manual activate -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4"><i class="fas fa-unlock-alt mr-2 text-green-500"></i>Manually Unlock QR for a Listing</h3>
        <form method="POST" id="manualForm" class="space-y-3">
            <input type="hidden" name="manual_activate" value="1">
            <input type="hidden" name="listing_id" id="manual_listing_id" value="">
            <div class="relative">
                <label class="block text-sm font-medium text-gray-700 mb-1">Search by name / city, or type the listing ID</label>
                <input type="text" id="manual_search" placeholder="Type listing name, city or ID…" autocomplete="off"
                    class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500">
                <div class="search-dropdown" id="manual_dropdown"></div>
                <div id="manual_selected" class="mt-1 text-sm text-green-600 font-medium hidden">
                    <i class="fas fa-check-circle mr-1"></i><span></span>
                </div>
            </div>
            <button type="submit" class="w-full bg-green-600 text-white rounded-lg px-4 py-2 hover:bg-green-700 transition font-medium">
                <i class="fas fa-unlock mr-1"></i> Unlock QR (mark as paid)
            </button>
        </form>
        <p class="text-xs text-gray-400 mt-2">Use this to fix an accidental payment: revert the wrong listing below, then unlock the correct one here.</p>
    </div>
</div>

<!-- Transactions -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="p-4 border-b bg-gray-50 flex justify-between items-center">
        <h3 class="text-lg font-semibold"><i class="fas fa-receipt mr-2 text-blue-500"></i>QR Transactions</h3>
        <select id="statusFilter" onchange="filterRows()" class="border rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
            <option value="all">All Status</option>
            <option value="paid">Paid</option>
            <option value="pending">Pending</option>
            <option value="cancelled">Reverted/Cancelled</option>
        </select>
    </div>
    <?php if($txns && $txns->num_rows > 0): ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Listing</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Txn / Payment ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php while($t = $txns->fetch_assoc()):
                    $st = $t['status'];
                    $stClass = 'bg-gray-100 text-gray-600';
                    if ($st === 'paid') $stClass = 'bg-green-100 text-green-700';
                    elseif ($st === 'pending') $stClass = 'bg-yellow-100 text-yellow-700';
                    elseif ($st === 'cancelled') $stClass = 'bg-red-100 text-red-700';
                ?>
                <tr class="hover:bg-gray-50 qr-row" data-status="<?php echo htmlspecialchars($st); ?>">
                    <td class="px-4 py-3 text-sm text-gray-500"><?php echo $t['id']; ?></td>
                    <td class="px-4 py-3">
                        <strong class="text-sm"><?php echo htmlspecialchars($t['listing_name'] ?? 'Unknown'); ?></strong>
                        <br><small class="text-gray-400">ID #<?php echo $t['listing_id']; ?><?php echo $t['city'] ? ' · ' . htmlspecialchars($t['city']) : ''; ?></small>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <span class="font-mono text-xs text-gray-500"><?php echo htmlspecialchars($t['razorpay_order_id']); ?></span>
                        <?php if(!empty($t['razorpay_payment_id'])): ?>
                        <br><span class="text-xs text-gray-400">Pay: <?php echo htmlspecialchars($t['razorpay_payment_id']); ?></span>
                        <?php endif; ?>
                        <?php if(!empty($t['note'])): ?>
                        <br><span class="text-xs text-blue-500"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($t['note']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm font-semibold">₹<?php echo number_format($t['amount'], 0); ?></td>
                    <td class="px-4 py-3"><span class="px-2 py-1 text-xs rounded-full <?php echo $stClass; ?>"><?php echo strtoupper($st); ?></span></td>
                    <td class="px-4 py-3 text-sm text-gray-500">
                        <?php echo date('d M Y', strtotime($t['created_at'])); ?>
                        <?php if(!empty($t['paid_at'])): ?>
                        <br><span class="text-xs text-green-500">Paid: <?php echo date('d M, h:i A', strtotime($t['paid_at'])); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <?php if($st === 'paid'): ?>
                        <form method="POST" class="inline" onsubmit="return confirm('Revert this payment to UNPAID? The QR will lock again for this listing.');">
                            <input type="hidden" name="mark_unpaid" value="1">
                            <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                            <button type="submit" class="text-sm px-2 py-1 rounded bg-yellow-100 text-yellow-700 hover:bg-yellow-200"><i class="fas fa-lock"></i> Mark Unpaid</button>
                        </form>
                        <?php else: ?>
                        <form method="POST" class="inline" onsubmit="return confirm('Mark this transaction as PAID and unlock the QR for this listing?');">
                            <input type="hidden" name="mark_paid" value="1">
                            <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                            <button type="submit" class="text-sm px-2 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200"><i class="fas fa-unlock"></i> Mark Paid</button>
                        </form>
                        <?php endif; ?>
                        <a href="?delete_txn=<?php echo $t['id']; ?>" onclick="return confirm('Permanently delete this transaction record?')" class="text-sm px-2 py-1 bg-red-100 text-red-700 rounded ml-1"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="p-8 text-center text-gray-400">
        <i class="fas fa-qrcode text-4xl mb-3"></i>
        <p>No QR transactions yet.</p>
    </div>
    <?php endif; ?>
</div>

</main>
</div>
</div>

<script>
function filterRows() {
    const f = document.getElementById('statusFilter').value;
    document.querySelectorAll('.qr-row').forEach(row => {
        row.style.display = (f === 'all' || row.dataset.status === f) ? '' : 'none';
    });
}

// AJAX listing search for manual unlock
const sInput = document.getElementById('manual_search');
const sDrop = document.getElementById('manual_dropdown');
const sId = document.getElementById('manual_listing_id');
const sSel = document.getElementById('manual_selected');
let sTimer;

if (sInput) {
    sInput.addEventListener('input', function() {
        clearTimeout(sTimer);
        const q = this.value.trim();
        sId.value = '';
        sSel.classList.add('hidden');
        if (q.length < 1) { sDrop.classList.remove('active'); return; }
        sTimer = setTimeout(() => {
            fetch('QrCodeConfig.php?ajax_search=1&q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(items => {
                    sDrop.innerHTML = '';
                    if (!items.length) {
                        sDrop.innerHTML = '<div class="search-item text-gray-400">No listings found</div>';
                    } else {
                        items.forEach(it => {
                            const d = document.createElement('div');
                            d.className = 'search-item';
                            d.innerHTML = '<strong>#' + it.id + ' ' + it.name + '</strong> <span class="city">' + (it.city || '') + '</span>';
                            d.onclick = () => {
                                sId.value = it.id;
                                sInput.value = it.name + ' (#' + it.id + ')';
                                sSel.classList.remove('hidden');
                                sSel.querySelector('span').textContent = 'Selected: ' + it.name + ' (ID ' + it.id + ')';
                                sDrop.classList.remove('active');
                            };
                            sDrop.appendChild(d);
                        });
                    }
                    sDrop.classList.add('active');
                });
        }, 300);
    });
    document.addEventListener('click', e => {
        if (!e.target.closest('#manual_search') && !e.target.closest('#manual_dropdown')) sDrop.classList.remove('active');
    });
}

// Allow submitting a raw numeric ID even without picking from the dropdown.
document.getElementById('manualForm')?.addEventListener('submit', function(e) {
    if (!sId.value) {
        const raw = sInput.value.trim();
        if (/^\d+$/.test(raw)) {
            sId.value = raw;
        } else {
            e.preventDefault();
            alert('Search and select a listing, or type a numeric listing ID.');
        }
    }
});
</script>
</body>
</html>
