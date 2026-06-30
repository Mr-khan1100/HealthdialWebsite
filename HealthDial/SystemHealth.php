<?php
require_once 'connection.inc.php';
requireAdmin();

// === DATABASE STATS ===
$dbSizeRes = $conn->query("SELECT 
    table_name AS tbl, 
    ROUND((data_length + index_length) / 1024, 1) AS size_kb,
    table_rows AS rows_count
    FROM information_schema.tables 
    WHERE table_schema = '" . DB_NAME . "' 
    ORDER BY (data_length + index_length) DESC");
$tables = [];
$totalDbSize = 0;
if($dbSizeRes) {
    while($t = $dbSizeRes->fetch_assoc()) {
        $tables[] = $t;
        $totalDbSize += $t['size_kb'];
    }
}

// === RECORD COUNTS ===
$counts = [
    'users' => (int)$conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'],
    'listings' => (int)$conn->query("SELECT COUNT(*) as c FROM listings")->fetch_assoc()['c'],
    'reviews' => (int)$conn->query("SELECT COUNT(*) as c FROM reviews")->fetch_assoc()['c'],
    'medications' => (int)$conn->query("SELECT COUNT(*) as c FROM medications")->fetch_assoc()['c'],
    'notifications' => (int)$conn->query("SELECT COUNT(*) as c FROM notification_queue")->fetch_assoc()['c'],
    'news' => (int)$conn->query("SELECT COUNT(*) as c FROM news")->fetch_assoc()['c'],
    'documents' => (int)$conn->query("SELECT COUNT(*) as c FROM documents")->fetch_assoc()['c'],
    'enquiries' => (int)$conn->query("SELECT COUNT(*) as c FROM enquiries")->fetch_assoc()['c'],
    'activity_logs' => (int)$conn->query("SELECT COUNT(*) as c FROM activity_logs")->fetch_assoc()['c'],
    'categories' => (int)$conn->query("SELECT COUNT(*) as c FROM categories")->fetch_assoc()['c'],
];

// === PUSH TOKEN STATS ===
$totalTokens = (int)$conn->query("SELECT COUNT(*) as c FROM users WHERE expo_push_token IS NOT NULL AND expo_push_token != ''")->fetch_assoc()['c'];
$nullTokens = (int)$conn->query("SELECT COUNT(*) as c FROM users WHERE expo_push_token IS NULL OR expo_push_token = ''")->fetch_assoc()['c'];

// === SERVER INFO ===
$phpVersion = phpversion();
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$mysqlVersion = $conn->query("SELECT VERSION() as v")->fetch_assoc()['v'];
$maxUpload = ini_get('upload_max_filesize');
$maxPost = ini_get('post_max_size');
$memoryLimit = ini_get('memory_limit');

// === RECENT ERRORS (from debug.log) ===
$recentErrors = [];
$logFile = __DIR__ . '/debug.log';
if(file_exists($logFile)) {
    $lines = array_slice(file($logFile), -20);
    foreach(array_reverse($lines) as $line) {
        $line = trim($line);
        if(!empty($line)) $recentErrors[] = $line;
    }
}

// === UPLOAD FOLDER SIZE ===
function folderSize($dir) {
    $size = 0;
    if(is_dir($dir)) {
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
        }
    }
    return $size;
}
$uploadSize = 0;
if(is_dir(__DIR__ . '/uploads')) {
    $uploadSize = folderSize(__DIR__ . '/uploads');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health — HealthDial Admin</title>
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
                    <h1 class="page-title"><i class="fas fa-server" style="color:var(--primary);margin-right:8px;"></i>System Health</h1>
                    <p class="page-subtitle">Server, database and storage monitoring</p>
                </div>

                <!-- Server Info Cards -->
                <div class="stat-grid">
                    <div class="stat-card emerald fade-in">
                        <div class="stat-info">
                            <h3>Database Size</h3>
                            <p class="stat-value"><?php echo $totalDbSize >= 1024 ? round($totalDbSize / 1024, 1) . ' MB' : round($totalDbSize, 0) . ' KB'; ?></p>
                            <div class="stat-change up"><?php echo count($tables); ?> tables</div>
                        </div>
                        <div class="stat-icon emerald"><i class="fas fa-database"></i></div>
                    </div>
                    <div class="stat-card blue fade-in fade-in-delay-1">
                        <div class="stat-info">
                            <h3>Upload Storage</h3>
                            <p class="stat-value"><?php echo $uploadSize >= 1048576 ? round($uploadSize / 1048576, 1) . ' MB' : round($uploadSize / 1024, 0) . ' KB'; ?></p>
                            <div class="stat-change up">uploads folder</div>
                        </div>
                        <div class="stat-icon blue"><i class="fas fa-hdd"></i></div>
                    </div>
                    <div class="stat-card amber fade-in fade-in-delay-2">
                        <div class="stat-info">
                            <h3>Push Tokens</h3>
                            <p class="stat-value"><?php echo $totalTokens; ?></p>
                            <div class="stat-change <?php echo $nullTokens > 0 ? 'down' : 'up'; ?>"><?php echo $nullTokens; ?> without token</div>
                        </div>
                        <div class="stat-icon amber"><i class="fas fa-mobile-alt"></i></div>
                    </div>
                    <div class="stat-card purple fade-in fade-in-delay-3">
                        <div class="stat-info">
                            <h3>PHP Version</h3>
                            <p class="stat-value" style="font-size:22px;"><?php echo $phpVersion; ?></p>
                            <div class="stat-change up">MySQL <?php echo $mysqlVersion; ?></div>
                        </div>
                        <div class="stat-icon purple"><i class="fab fa-php"></i></div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                    <!-- Server Config -->
                    <div class="card fade-in">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-cogs" style="margin-right:8px;color:var(--primary);"></i>Server Configuration</h3></div>
                        <div class="card-body">
                            <div style="display:grid;gap:14px;">
                                <?php 
                                $configs = [
                                    ['Server Software', $serverSoftware, 'fa-server'],
                                    ['PHP Memory Limit', $memoryLimit, 'fa-memory'],
                                    ['Max Upload Size', $maxUpload, 'fa-upload'],
                                    ['Max POST Size', $maxPost, 'fa-file-upload'],
                                    ['Database', DB_NAME, 'fa-database'],
                                    ['Database Host', DB_HOST, 'fa-network-wired'],
                                    ['Timezone', date_default_timezone_get(), 'fa-clock'],
                                    ['Display Errors', ini_get('display_errors') ? 'ON' : 'OFF', 'fa-exclamation-triangle'],
                                ];
                                foreach($configs as $cfg): ?>
                                <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-light);">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <i class="fas <?php echo $cfg[2]; ?>" style="width:16px;text-align:center;color:var(--text-muted);font-size:12px;"></i>
                                        <span style="font-size:13px;color:var(--text-secondary);"><?php echo $cfg[0]; ?></span>
                                    </div>
                                    <span style="font-size:13px;font-weight:600;font-family:monospace;color:var(--text-primary);"><?php echo htmlspecialchars($cfg[1]); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Record Counts -->
                    <div class="card fade-in fade-in-delay-1">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-bar" style="margin-right:8px;color:var(--accent);"></i>Record Counts</h3></div>
                        <div class="card-body">
                            <div style="display:grid;gap:10px;">
                                <?php
                                $icons = ['users'=>'fa-users','listings'=>'fa-hospital','reviews'=>'fa-star','medications'=>'fa-pills','notifications'=>'fa-bell','news'=>'fa-newspaper','documents'=>'fa-file-alt','enquiries'=>'fa-envelope','activity_logs'=>'fa-history','categories'=>'fa-layer-group'];
                                foreach($counts as $key => $cnt): ?>
                                <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--bg-card-hover);border-radius:var(--radius-sm);">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <i class="fas <?php echo $icons[$key] ?? 'fa-table'; ?>" style="width:16px;text-align:center;color:var(--primary);font-size:12px;"></i>
                                        <span style="font-size:13px;text-transform:capitalize;"><?php echo str_replace('_',' ',$key); ?></span>
                                    </div>
                                    <span style="font-size:14px;font-weight:700;color:var(--text-primary);"><?php echo number_format($cnt); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <!-- Database Tables -->
                    <div class="card fade-in">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-database" style="margin-right:8px;color:var(--status-info);"></i>Database Tables</h3></div>
                        <div style="overflow-x:auto;max-height:400px;overflow-y:auto;">
                            <table class="data-table">
                                <thead><tr><th>Table</th><th>Rows</th><th>Size</th></tr></thead>
                                <tbody>
                                    <?php foreach($tables as $tbl): ?>
                                    <tr>
                                        <td style="font-family:monospace;font-size:12px;font-weight:600;"><?php echo $tbl['tbl']; ?></td>
                                        <td style="font-size:13px;"><?php echo number_format($tbl['rows_count']); ?></td>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:8px;">
                                                <div style="flex:1;height:6px;background:var(--border-light);border-radius:3px;overflow:hidden;max-width:100px;">
                                                    <div style="height:100%;background:var(--primary);border-radius:3px;width:<?php echo $totalDbSize > 0 ? min(100, ($tbl['size_kb'] / $totalDbSize) * 100) : 0; ?>%;"></div>
                                                </div>
                                                <span style="font-size:12px;color:var(--text-muted);"><?php echo $tbl['size_kb']; ?> KB</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Debug Errors -->
                    <div class="card fade-in fade-in-delay-1">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-exclamation-triangle" style="margin-right:8px;color:var(--status-danger);"></i>Recent Errors</h3>
                            <span class="badge <?php echo count($recentErrors) > 0 ? 'badge-danger' : 'badge-success'; ?>"><?php echo count($recentErrors); ?> entries</span>
                        </div>
                        <div style="max-height:400px;overflow-y:auto;padding:16px;">
                            <?php if(count($recentErrors) > 0): ?>
                            <?php foreach($recentErrors as $err): ?>
                            <div style="padding:8px 12px;background:rgba(239,68,68,0.04);border-left:3px solid var(--status-danger);border-radius:0 var(--radius-sm) var(--radius-sm) 0;margin-bottom:8px;font-size:11px;font-family:monospace;color:var(--text-secondary);word-break:break-all;">
                                <?php echo htmlspecialchars($err); ?>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <div class="empty-state" style="padding:30px;">
                                <div class="empty-state-icon" style="width:50px;height:50px;font-size:20px;background:rgba(16,185,129,0.1);color:var(--status-success);">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <h3>No recent errors</h3>
                                <p>System is running smoothly!</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
