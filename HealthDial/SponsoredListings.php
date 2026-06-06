<?php
require_once 'connection.inc.php';
requireLogin();

if (session_status() === PHP_SESSION_NONE) session_start();

// AJAX search endpoint
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $q = '%' . trim($_GET['q'] ?? '') . '%';
    $stmt = $conn->prepare("SELECT id, name, city FROM listings WHERE status = 'approved' AND (name LIKE ? OR city LIKE ?) ORDER BY name LIMIT 15");
    $stmt->bind_param("ss", $q, $q);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) $items[] = $row;
    echo json_encode($items);
    exit();
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Toggle highlight status
    if (isset($_POST['toggle_highlight'])) {
        $id = intval($_POST['id']);
        $active = intval($_POST['is_active']);
        $stmt = $conn->prepare("UPDATE listing_highlights SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $active, $id);
        $stmt->execute();
        $_SESSION['success'] = "Highlight " . ($active ? 'activated' : 'deactivated');
        header("Location: SponsoredListings.php");
        exit();
    }
    
    // Add manual highlight
    if (isset($_POST['add_highlight'])) {
        $listing_id = intval($_POST['listing_id']);
        $plan_id = intval($_POST['plan_id']);
        $target_cities = trim($_POST['target_cities'] ?? '');
        
        $planStmt = $conn->prepare("SELECT duration_days, price FROM highlight_plans WHERE id = ?");
        $planStmt->bind_param("i", $plan_id);
        $planStmt->execute();
        $plan = $planStmt->get_result()->fetch_assoc();
        
        if ($plan) {
            $start = date('Y-m-d H:i:s');
            $end = date('Y-m-d H:i:s', strtotime("+{$plan['duration_days']} days"));
            $amount = $plan['price'];
            
            $stmt = $conn->prepare("INSERT INTO listing_highlights (listing_id, start_date, end_date, amount, target_cities, payment_status, is_active) VALUES (?, ?, ?, ?, ?, 'paid', 1)");
            $stmt->bind_param("issds", $listing_id, $start, $end, $amount, $target_cities);
            $stmt->execute();
            
            $_SESSION['success'] = "Listing highlighted successfully!";
        }
        header("Location: SponsoredListings.php");
        exit();
    }
    
    // Save Plan (Add or Edit)
    if (isset($_POST['save_plan'])) {
        $name = trim($_POST['plan_name']);
        $days = intval($_POST['plan_days']);
        $price = floatval($_POST['plan_price']);
        $desc = trim($_POST['plan_desc']);
        $plan_id = intval($_POST['plan_id'] ?? 0);
        
        if ($plan_id > 0) {
            $stmt = $conn->prepare("UPDATE highlight_plans SET name=?, duration_days=?, price=?, description=? WHERE id=?");
            $stmt->bind_param("sidsi", $name, $days, $price, $desc, $plan_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO highlight_plans (name, duration_days, price, description) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sids", $name, $days, $price, $desc);
        }
        $stmt->execute();
        $_SESSION['success'] = "Plan saved!";
        header("Location: SponsoredListings.php#plans");
        exit();
    }
    
    // Toggle plan active/inactive
    if (isset($_POST['toggle_plan'])) {
        $id = intval($_POST['plan_id']);
        $active = intval($_POST['is_active']);
        $stmt = $conn->prepare("UPDATE highlight_plans SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $active, $id);
        $stmt->execute();
        $_SESSION['success'] = "Plan " . ($active ? 'activated' : 'deactivated');
        header("Location: SponsoredListings.php#plans");
        exit();
    }
}

// Delete highlight
if (isset($_GET['delete_highlight'])) {
    $id = intval($_GET['delete_highlight']);
    $conn->query("DELETE FROM listing_highlights WHERE id = $id");
    $_SESSION['success'] = "Highlight removed.";
    header("Location: SponsoredListings.php");
    exit();
}

// Delete plan
if (isset($_GET['delete_plan'])) {
    $id = intval($_GET['delete_plan']);
    $conn->query("DELETE FROM highlight_plans WHERE id = $id");
    $_SESSION['success'] = "Plan deleted.";
    header("Location: SponsoredListings.php#plans");
    exit();
}

// Fetch data
$highlights = $conn->query("
    SELECT h.*, l.name as listing_name, l.city, l.status as listing_status
    FROM listing_highlights h
    JOIN listings l ON h.listing_id = l.id
    ORDER BY h.created_at DESC
");

// Fetch distinct cities for autocomplete suggestions
$cityResult = $conn->query("SELECT DISTINCT city FROM listings WHERE city IS NOT NULL AND city != '' AND status='approved' ORDER BY city LIMIT 200");
$allCities = [];
if ($cityResult) {
    while ($r = $cityResult->fetch_assoc()) $allCities[] = $r['city'];
}

$plans = $conn->query("SELECT * FROM highlight_plans ORDER BY price ASC");

// Analytics summary
$totalRevenue = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM listing_highlights WHERE payment_status='paid'")->fetch_assoc()['total'];
$activeCount = $conn->query("SELECT COUNT(*) as cnt FROM listing_highlights WHERE is_active=1 AND end_date > NOW()")->fetch_assoc()['cnt'];
$totalClicks = $conn->query("SELECT COALESCE(SUM(total_clicks),0) as cnt FROM listing_highlights")->fetch_assoc()['cnt'];
$totalImpressions = $conn->query("SELECT COALESCE(SUM(total_impressions),0) as cnt FROM listing_highlights")->fetch_assoc()['cnt'];

// Payment stats
$paymentRevenue = 0;
$paymentCount = 0;
$pendingPayments = 0;
$paymentsTableExists = $conn->query("SHOW TABLES LIKE 'promotion_payments'");
if ($paymentsTableExists && $paymentsTableExists->num_rows > 0) {
    $paymentRevenue = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM promotion_payments WHERE payment_status='PAID'")->fetch_assoc()['total'];
    $paymentCount = $conn->query("SELECT COUNT(*) as cnt FROM promotion_payments WHERE payment_status='PAID'")->fetch_assoc()['cnt'];
    $pendingPayments = $conn->query("SELECT COUNT(*) as cnt FROM promotion_payments WHERE payment_status='PENDING'")->fetch_assoc()['cnt'];
}

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'sponsorships';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sponsored Listings — HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .search-dropdown { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #e5e7eb; border-radius:8px; max-height:200px; overflow-y:auto; z-index:50; display:none; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        .search-dropdown.active { display:block; }
        .search-item { padding:10px 14px; cursor:pointer; border-bottom:1px solid #f3f4f6; }
        .search-item:hover { background:#f0f7ff; }
        .search-item .city { font-size:12px; color:#9ca3af; }
        .tab-btn { padding:10px 24px; font-weight:600; border-bottom:3px solid transparent; color:#6b7280; cursor:pointer; transition:all 0.2s; }
        .tab-btn.active { color:#0782ca; border-bottom-color:#0782ca; }
        .tab-btn:hover { color:#0782ca; }
        .plan-card { transition: transform 0.2s, box-shadow 0.2s; }
        .plan-card:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,0.1); }
        .progress-bar { height:6px; border-radius:3px; background:#e5e7eb; overflow:hidden; }
        .progress-fill { height:100%; border-radius:3px; transition:width 0.5s; }
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

<!-- Analytics Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Total Revenue</p>
                <p class="text-2xl font-bold text-green-600">₹<?php echo number_format($totalRevenue, 0); ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                <i class="fas fa-rupee-sign text-green-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Active Highlights</p>
                <p class="text-2xl font-bold text-blue-600"><?php echo $activeCount; ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                <i class="fas fa-star text-blue-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Total Clicks</p>
                <p class="text-2xl font-bold text-purple-600"><?php echo number_format($totalClicks); ?></p>
            </div>
            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                <i class="fas fa-mouse-pointer text-purple-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Impressions</p>
                <p class="text-2xl font-bold text-orange-600"><?php echo number_format($totalImpressions); ?></p>
            </div>
            <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                <i class="fas fa-eye text-orange-600"></i>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="bg-white rounded-t-lg shadow-sm border-b flex mb-0">
    <div class="tab-btn <?php echo $activeTab === 'sponsorships' ? 'active' : ''; ?>" onclick="switchTab('sponsorships')">
        <i class="fas fa-star mr-2"></i>Sponsorships
    </div>
    <div class="tab-btn <?php echo $activeTab === 'plans' ? 'active' : ''; ?>" onclick="switchTab('plans')">
        <i class="fas fa-tags mr-2"></i>Plans
    </div>
    <div class="tab-btn <?php echo $activeTab === 'payments' ? 'active' : ''; ?>" onclick="switchTab('payments')">
        <i class="fas fa-credit-card mr-2"></i>Payments
        <?php if($pendingPayments > 0): ?>
        <span class="ml-1 px-1.5 py-0.5 text-xs bg-yellow-400 text-yellow-900 rounded-full"><?php echo $pendingPayments; ?></span>
        <?php endif; ?>
    </div>
</div>

<!-- Tab 1: Sponsorships -->
<div id="tab-sponsorships" class="<?php echo $activeTab !== 'sponsorships' ? 'hidden' : ''; ?>">

<!-- Add Highlight Form with AJAX Search -->
<div class="bg-white rounded-b-lg shadow p-6 mb-6">
    <h3 class="text-lg font-semibold mb-4"><i class="fas fa-plus-circle mr-2 text-blue-500"></i>Add Sponsored Listing</h3>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4" id="addHighlightForm">
        <input type="hidden" name="add_highlight" value="1">
        <input type="hidden" name="listing_id" id="selected_listing_id" value="">
        <div class="relative">
            <label class="block text-sm font-medium text-gray-700 mb-1">Search Listing</label>
            <input type="text" id="listing_search" placeholder="Type to search listings..." autocomplete="off" 
                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
            <div class="search-dropdown" id="listing_dropdown"></div>
            <div id="selected_listing_display" class="mt-1 text-sm text-green-600 font-medium hidden">
                <i class="fas fa-check-circle mr-1"></i><span></span>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Select Plan</label>
            <select name="plan_id" required class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                <option value="">-- Choose Plan --</option>
                <?php if($plans) { $plans->data_seek(0); while($p = $plans->fetch_assoc()): ?>
                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?> — ₹<?php echo $p['price']; ?> (<?php echo $p['duration_days']; ?> days)</option>
                <?php endwhile; } ?>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">
                <i class="fas fa-map-marker-alt text-red-500 mr-1"></i>Target Cities
                <span class="text-xs text-gray-400 ml-1">(comma-separated, leave empty for all cities)</span>
            </label>
            <input type="text" name="target_cities" id="target_cities_input" placeholder="e.g. Latur, Pune, Mumbai" 
                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"
                autocomplete="off">
            <div class="mt-1 flex flex-wrap gap-1" id="city_suggestions"></div>
        </div>
        <div class="flex items-end">
            <button type="submit" class="w-full bg-blue-600 text-white rounded-lg px-4 py-2 hover:bg-blue-700 transition font-medium">
                <i class="fas fa-bolt mr-1"></i> Activate Highlight
            </button>
        </div>
    </form>
</div>

<!-- Active Highlights -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="p-4 border-b bg-gray-50">
        <h3 class="text-lg font-semibold"><i class="fas fa-star mr-2 text-yellow-500"></i>Sponsored Listings</h3>
    </div>
    <?php if($highlights && $highlights->num_rows > 0): ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Listing</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Target Cities</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Progress</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stats</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php while($h = $highlights->fetch_assoc()): 
                    $isExpired = strtotime($h['end_date']) < time();
                    $totalDays = max(1, (strtotime($h['end_date']) - strtotime($h['start_date'])) / 86400);
                    $daysLeft = max(0, (strtotime($h['end_date']) - time()) / 86400);
                    $progress = min(100, max(0, (($totalDays - $daysLeft) / $totalDays) * 100));
                ?>
                <tr class="hover:bg-gray-50 <?php echo $isExpired ? 'opacity-50' : ''; ?>">
                    <td class="px-4 py-3">
                        <strong><?php echo htmlspecialchars($h['listing_name']); ?></strong><br>
                        <small class="text-gray-400"><?php echo $h['city']; ?></small>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?php if(!empty($h['target_cities'])): ?>
                            <?php foreach(explode(',', $h['target_cities']) as $tc): ?>
                                <span class="inline-block px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700 mr-1 mb-1">
                                    <i class="fas fa-map-marker-alt mr-0.5"></i><?php echo htmlspecialchars(trim($tc)); ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-xs text-gray-400">All Cities</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm"><?php echo date('d M', strtotime($h['start_date'])); ?> → <?php echo date('d M Y', strtotime($h['end_date'])); ?></td>
                    <td class="px-4 py-3" style="min-width:120px;">
                        <div class="progress-bar">
                            <div class="progress-fill <?php echo $isExpired ? 'bg-red-400' : 'bg-blue-500'; ?>" style="width:<?php echo round($progress); ?>%"></div>
                        </div>
                        <span class="text-xs text-gray-500 mt-1"><?php echo $isExpired ? 'Expired' : round($daysLeft) . ' days left'; ?></span>
                    </td>
                    <td class="px-4 py-3 text-sm font-semibold">₹<?php echo number_format($h['amount'], 0); ?></td>
                    <td class="px-4 py-3 text-sm">
                        <span class="text-purple-600"><i class="fas fa-mouse-pointer"></i> <?php echo $h['total_clicks']; ?></span>
                        <span class="text-gray-400 mx-1">|</span>
                        <span class="text-orange-500"><i class="fas fa-eye"></i> <?php echo $h['total_impressions']; ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <?php if($isExpired): ?>
                            <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-700">Expired</span>
                        <?php elseif($h['is_active']): ?>
                            <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-700">Active</span>
                        <?php else: ?>
                            <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <form method="POST" class="inline">
                            <input type="hidden" name="toggle_highlight" value="1">
                            <input type="hidden" name="id" value="<?php echo $h['id']; ?>">
                            <input type="hidden" name="is_active" value="<?php echo $h['is_active'] ? 0 : 1; ?>">
                            <button type="submit" class="text-sm px-2 py-1 rounded <?php echo $h['is_active']?'bg-yellow-100 text-yellow-700':'bg-green-100 text-green-700'; ?>">
                                <?php echo $h['is_active'] ? 'Pause' : 'Resume'; ?>
                            </button>
                        </form>
                        <a href="?delete_highlight=<?php echo $h['id']; ?>" onclick="return confirm('Delete this highlight?')" class="text-sm px-2 py-1 bg-red-100 text-red-700 rounded ml-1">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="p-8 text-center text-gray-400">
        <i class="fas fa-star text-4xl mb-3"></i>
        <p>No sponsored listings yet</p>
    </div>
    <?php endif; ?>
</div>
</div>

<!-- Tab 2: Plans -->
<div id="tab-plans" class="<?php echo $activeTab !== 'plans' ? 'hidden' : ''; ?>">
<div class="bg-white rounded-b-lg shadow p-6 mb-6">
    <h3 class="text-lg font-semibold mb-4"><i class="fas fa-plus mr-2 text-green-500"></i>Add New Plan</h3>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-3" id="planForm">
        <input type="hidden" name="save_plan" value="1">
        <input type="hidden" name="plan_id" value="0" id="edit_plan_id">
        <input type="text" name="plan_name" id="edit_plan_name" placeholder="Plan Name" required class="border rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500">
        <input type="number" name="plan_days" id="edit_plan_days" placeholder="Days" required class="border rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500">
        <input type="number" name="plan_price" id="edit_plan_price" step="0.01" placeholder="Price (₹)" required class="border rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500">
        <input type="text" name="plan_desc" id="edit_plan_desc" placeholder="Description" class="border rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500">
        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-green-600 text-white rounded-lg px-4 py-2 hover:bg-green-700 transition" id="planSubmitBtn">
                <i class="fas fa-plus mr-1"></i> Add
            </button>
            <button type="button" onclick="cancelEdit()" class="px-3 py-2 bg-gray-200 text-gray-600 rounded-lg hidden" id="cancelEditBtn">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </form>
</div>

<?php if($plans && $plans->num_rows > 0): $plans->data_seek(0); ?>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <?php while($p = $plans->fetch_assoc()): ?>
    <div class="plan-card bg-white border rounded-xl p-5 <?php echo $p['is_active']?'border-green-200':'border-gray-200 opacity-60'; ?>">
        <div class="flex justify-between items-start mb-3">
            <h4 class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($p['name']); ?></h4>
            <?php if($p['is_active']): ?>
                <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">Active</span>
            <?php else: ?>
                <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-500">Inactive</span>
            <?php endif; ?>
        </div>
        <p class="text-3xl font-bold text-green-600 mb-1">₹<?php echo number_format($p['price'], 0); ?></p>
        <p class="text-sm text-gray-500 mb-1"><i class="fas fa-calendar mr-1"></i><?php echo $p['duration_days']; ?> days</p>
        <p class="text-xs text-gray-400 mb-4"><?php echo htmlspecialchars($p['description'] ?? ''); ?></p>
        <div class="flex gap-2 border-t pt-3">
            <button onclick="editPlan(<?php echo $p['id']; ?>, '<?php echo addslashes($p['name']); ?>', <?php echo $p['duration_days']; ?>, <?php echo $p['price']; ?>, '<?php echo addslashes($p['description'] ?? ''); ?>')" 
                class="flex-1 text-sm px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition">
                <i class="fas fa-edit mr-1"></i> Edit
            </button>
            <form method="POST" class="flex-1">
                <input type="hidden" name="toggle_plan" value="1">
                <input type="hidden" name="plan_id" value="<?php echo $p['id']; ?>">
                <input type="hidden" name="is_active" value="<?php echo $p['is_active'] ? 0 : 1; ?>">
                <button type="submit" class="w-full text-sm px-3 py-1.5 <?php echo $p['is_active']?'bg-yellow-50 text-yellow-600 hover:bg-yellow-100':'bg-green-50 text-green-600 hover:bg-green-100'; ?> rounded-lg transition">
                    <i class="fas fa-<?php echo $p['is_active']?'pause':'play'; ?> mr-1"></i> <?php echo $p['is_active']?'Pause':'Enable'; ?>
                </button>
            </form>
            <a href="?delete_plan=<?php echo $p['id']; ?>" onclick="return confirm('Delete this plan?')" 
                class="text-sm px-3 py-1.5 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">
                <i class="fas fa-trash"></i>
            </a>
        </div>
    </div>
    <?php endwhile; ?>
</div>
<?php else: ?>
<div class="bg-white rounded-lg shadow p-8 text-center text-gray-400 mb-6">
    <i class="fas fa-tags text-4xl mb-3"></i>
    <p>No plans created yet. Add one above.</p>
</div>
<?php endif; ?>
</div>

<!-- Tab 3: Payments -->
<div id="tab-payments" class="<?php echo $activeTab !== 'payments' ? 'hidden' : ''; ?>">

<!-- Payment Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 mt-4">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Online Revenue</p>
                <p class="text-2xl font-bold text-green-600">₹<?php echo number_format($paymentRevenue, 0); ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                <i class="fas fa-rupee-sign text-green-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Successful Payments</p>
                <p class="text-2xl font-bold text-blue-600"><?php echo $paymentCount; ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                <i class="fas fa-check-circle text-blue-600"></i>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500">Pending Payments</p>
                <p class="text-2xl font-bold text-yellow-600"><?php echo $pendingPayments; ?></p>
            </div>
            <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                <i class="fas fa-clock text-yellow-600"></i>
            </div>
        </div>
    </div>
</div>

<!-- Payments Table -->
<div class="bg-white rounded-lg shadow overflow-hidden mb-6">
    <div class="p-4 border-b bg-gray-50 flex justify-between items-center">
        <h3 class="text-lg font-semibold"><i class="fas fa-credit-card mr-2 text-blue-500"></i>Payment Transactions</h3>
        <select id="paymentStatusFilter" onchange="filterPayments()" class="border rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
            <option value="all">All Status</option>
            <option value="PAID">Paid</option>
            <option value="PENDING">Pending</option>
            <option value="FAILED">Failed</option>
        </select>
    </div>
    <?php
    $payments = null;
    if ($paymentsTableExists && $paymentsTableExists->num_rows > 0) {
        $payments = $conn->query("
            SELECT pp.*, l.name as listing_name, l.city, hp.name as plan_name, hp.duration_days
            FROM promotion_payments pp
            LEFT JOIN listings l ON pp.listing_id = l.id
            LEFT JOIN highlight_plans hp ON pp.plan_id = hp.id
            ORDER BY pp.created_at DESC
            LIMIT 100
        ");
    }
    ?>
    <?php if($payments && $payments->num_rows > 0): ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Listing</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plan</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200" id="paymentsTableBody">
                <?php while($p = $payments->fetch_assoc()): ?>
                <tr class="hover:bg-gray-50 payment-row" data-status="<?php echo $p['payment_status']; ?>">
                    <td class="px-4 py-3 text-sm">
                        <span class="font-mono text-xs text-gray-500"><?php echo htmlspecialchars($p['cashfree_order_id'] ?? ''); ?></span>
                        <?php if($p['cf_payment_id']): ?>
                        <br><span class="text-xs text-gray-400">Pay: <?php echo $p['cf_payment_id']; ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <strong class="text-sm"><?php echo htmlspecialchars($p['listing_name'] ?? 'Unknown'); ?></strong>
                        <br><small class="text-gray-400"><?php echo $p['city'] ?? ''; ?></small>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <strong><?php echo htmlspecialchars($p['customer_name'] ?? ''); ?></strong>
                        <br><span class="text-gray-400"><?php echo $p['customer_phone'] ?? ''; ?></span>
                        <?php if($p['customer_email']): ?>
                        <br><span class="text-xs text-gray-400"><?php echo $p['customer_email']; ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?php echo htmlspecialchars($p['plan_name'] ?? 'N/A'); ?>
                        <?php if($p['duration_days']): ?>
                        <br><span class="text-xs text-gray-400"><?php echo $p['duration_days']; ?> days</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm font-semibold">₹<?php echo number_format($p['amount'], 0); ?></td>
                    <td class="px-4 py-3 text-sm text-gray-500"><?php echo $p['payment_method'] ?: '—'; ?></td>
                    <td class="px-4 py-3">
                        <?php
                        $statusClass = 'bg-gray-100 text-gray-600';
                        if($p['payment_status'] === 'PAID') $statusClass = 'bg-green-100 text-green-700';
                        elseif($p['payment_status'] === 'FAILED') $statusClass = 'bg-red-100 text-red-700';
                        elseif($p['payment_status'] === 'PENDING') $statusClass = 'bg-yellow-100 text-yellow-700';
                        ?>
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $statusClass; ?>"><?php echo $p['payment_status']; ?></span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500">
                        <?php echo date('d M Y', strtotime($p['created_at'])); ?>
                        <br><span class="text-xs"><?php echo date('h:i A', strtotime($p['created_at'])); ?></span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="p-8 text-center text-gray-400">
        <i class="fas fa-credit-card text-4xl mb-3"></i>
        <p>No payment transactions yet.</p>
        <p class="text-sm mt-1">Payments will appear here when users promote listings via the website.</p>
    </div>
    <?php endif; ?>
</div>
</div>

</main>
</div>
</div>

<script>
// Tab switching
function switchTab(tab) {
    document.getElementById('tab-sponsorships').classList.add('hidden');
    document.getElementById('tab-plans').classList.add('hidden');
    document.getElementById('tab-payments').classList.add('hidden');
    document.getElementById('tab-' + tab).classList.remove('hidden');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    event.target.closest('.tab-btn').classList.add('active');
}

// Payment status filter
function filterPayments() {
    const filter = document.getElementById('paymentStatusFilter').value;
    document.querySelectorAll('.payment-row').forEach(row => {
        if (filter === 'all' || row.dataset.status === filter) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// AJAX Listing Search
const searchInput = document.getElementById('listing_search');
const dropdown = document.getElementById('listing_dropdown');
const hiddenId = document.getElementById('selected_listing_id');
const display = document.getElementById('selected_listing_display');
let searchTimeout;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const q = this.value.trim();
        if (q.length < 2) { dropdown.classList.remove('active'); return; }
        
        searchTimeout = setTimeout(() => {
            fetch('SponsoredListings.php?ajax_search=1&q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(items => {
                    dropdown.innerHTML = '';
                    if (items.length === 0) {
                        dropdown.innerHTML = '<div class="search-item text-gray-400">No listings found</div>';
                    } else {
                        items.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'search-item';
                            div.innerHTML = '<strong>' + item.name + '</strong> <span class="city">' + (item.city || '') + '</span>';
                            div.onclick = () => selectListing(item);
                            dropdown.appendChild(div);
                        });
                    }
                    dropdown.classList.add('active');
                });
        }, 300);
    });
    
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#listing_search') && !e.target.closest('#listing_dropdown')) {
            dropdown.classList.remove('active');
        }
    });
}

function selectListing(item) {
    hiddenId.value = item.id;
    searchInput.value = item.name + ' (' + (item.city || '') + ')';
    display.classList.remove('hidden');
    display.querySelector('span').textContent = 'Selected: ' + item.name;
    dropdown.classList.remove('active');
}

// Form validation
document.getElementById('addHighlightForm')?.addEventListener('submit', function(e) {
    if (!hiddenId.value) {
        e.preventDefault();
        alert('Please search and select a listing first.');
    }
});

// Plan editing
function editPlan(id, name, days, price, desc) {
    document.getElementById('edit_plan_id').value = id;
    document.getElementById('edit_plan_name').value = name;
    document.getElementById('edit_plan_days').value = days;
    document.getElementById('edit_plan_price').value = price;
    document.getElementById('edit_plan_desc').value = desc;
    document.getElementById('planSubmitBtn').innerHTML = '<i class="fas fa-save mr-1"></i> Update';
    document.getElementById('cancelEditBtn').classList.remove('hidden');
    // Switch to plans tab and scroll to form
    switchTab('plans');
    document.getElementById('planForm').scrollIntoView({ behavior:'smooth' });
}

function cancelEdit() {
    document.getElementById('edit_plan_id').value = '0';
    document.getElementById('edit_plan_name').value = '';
    document.getElementById('edit_plan_days').value = '';
    document.getElementById('edit_plan_price').value = '';
    document.getElementById('edit_plan_desc').value = '';
    document.getElementById('planSubmitBtn').innerHTML = '<i class="fas fa-plus mr-1"></i> Add';
    document.getElementById('cancelEditBtn').classList.add('hidden');
}

// Auto-show tab from hash
var hash = window.location.hash.replace('#', '');
if (['plans', 'payments', 'sponsorships'].indexOf(hash) !== -1) {
    document.getElementById('tab-sponsorships').classList.add('hidden');
    document.getElementById('tab-plans').classList.add('hidden');
    document.getElementById('tab-payments').classList.add('hidden');
    document.getElementById('tab-' + hash).classList.remove('hidden');
    document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
    var tabIndex = hash === 'sponsorships' ? 0 : (hash === 'plans' ? 1 : 2);
    document.querySelectorAll('.tab-btn')[tabIndex].classList.add('active');
}

// City suggestions for target cities input
const allCities = <?php echo json_encode($allCities); ?>;
const cityInput = document.getElementById('target_cities_input');
const citySuggestions = document.getElementById('city_suggestions');

if (cityInput && citySuggestions) {
    cityInput.addEventListener('input', function() {
        const val = this.value;
        // Get the last typed city (after last comma)
        const parts = val.split(',');
        const current = parts[parts.length - 1].trim().toLowerCase();
        
        citySuggestions.innerHTML = '';
        if (current.length < 1) return;
        
        const alreadySelected = parts.slice(0, -1).map(p => p.trim().toLowerCase());
        const matches = allCities.filter(c => 
            c.toLowerCase().includes(current) && !alreadySelected.includes(c.toLowerCase())
        ).slice(0, 8);
        
        matches.forEach(city => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'px-2 py-1 text-xs rounded-full bg-blue-50 text-blue-600 hover:bg-blue-100 cursor-pointer border border-blue-200';
            btn.textContent = city;
            btn.onclick = () => {
                // Replace the last partial entry with the full city name
                parts[parts.length - 1] = ' ' + city;
                cityInput.value = parts.join(',').replace(/^,\s*/, '').trim() + ', ';
                citySuggestions.innerHTML = '';
                cityInput.focus();
            };
            citySuggestions.appendChild(btn);
        });
    });
}
</script>
</body>
</html>
