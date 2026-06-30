<?php
require_once 'connection.inc.php';
requireAdmin();

// === OVERALL ENGAGEMENT METRICS ===

// Users with push tokens (reachable)
$totalUsers = (int)$conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$withPush = (int)$conn->query("SELECT COUNT(*) as c FROM users WHERE expo_push_token IS NOT NULL AND expo_push_token != ''")->fetch_assoc()['c'];
$pushRate = $totalUsers > 0 ? round(($withPush / $totalUsers) * 100) : 0;

// Users with at least 1 review
$withReview = (int)$conn->query("SELECT COUNT(DISTINCT user_id) as c FROM reviews")->fetch_assoc()['c'];
$reviewRate = $totalUsers > 0 ? round(($withReview / $totalUsers) * 100) : 0;

// Users with at least 1 medication
$withMed = (int)$conn->query("SELECT COUNT(DISTINCT user_id) as c FROM medications")->fetch_assoc()['c'];
$medRate = $totalUsers > 0 ? round(($withMed / $totalUsers) * 100) : 0;

// Users with at least 1 document
$withDoc = (int)$conn->query("SELECT COUNT(DISTINCT user_id) as c FROM documents")->fetch_assoc()['c'];
$docRate = $totalUsers > 0 ? round(($withDoc / $totalUsers) * 100) : 0;

// Users with location data
$withLocation = (int)$conn->query("SELECT COUNT(*) as c FROM users WHERE latitude IS NOT NULL AND latitude != 0")->fetch_assoc()['c'];
$locationRate = $totalUsers > 0 ? round(($withLocation / $totalUsers) * 100) : 0;

// Users with diseases recorded
$withDiseases = (int)$conn->query("SELECT COUNT(*) as c FROM users WHERE diseases IS NOT NULL AND diseases != ''")->fetch_assoc()['c'];
$diseaseRate = $totalUsers > 0 ? round(($withDiseases / $totalUsers) * 100) : 0;

// Active in last 7 days (reviews, meds, or docs created)
$activeWeek = (int)$conn->query("
    SELECT COUNT(DISTINCT user_id) as c FROM (
        SELECT user_id FROM reviews WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        UNION SELECT user_id FROM medications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        UNION SELECT user_id FROM documents WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ) AS active
")->fetch_assoc()['c'];
$activeRate = $totalUsers > 0 ? round(($activeWeek / $totalUsers) * 100) : 0;

// === ENGAGEMENT TIERS ===
// Calculate engagement score per user
$userScores = [];
$scoreRes = $conn->query("
    SELECT u.id, u.name, u.created_at,
        (SELECT COUNT(*) FROM reviews WHERE user_id = u.id) as review_count,
        (SELECT COUNT(*) FROM medications WHERE user_id = u.id) as med_count,
        (SELECT COUNT(*) FROM documents WHERE user_id = u.id) as doc_count,
        IF(u.expo_push_token IS NOT NULL AND u.expo_push_token != '', 1, 0) as has_push,
        IF(u.diseases IS NOT NULL AND u.diseases != '', 1, 0) as has_diseases
    FROM users u
    ORDER BY u.created_at DESC
");
$tiers = ['high'=>0,'medium'=>0,'low'=>0,'inactive'=>0];
$topUsers = [];
if($scoreRes) {
    while($u = $scoreRes->fetch_assoc()) {
        $score = min(100, $u['review_count'] * 15 + $u['med_count'] * 10 + $u['doc_count'] * 8 + $u['has_push'] * 10 + $u['has_diseases'] * 5);
        $u['score'] = $score;
        
        if($score >= 70) $tiers['high']++;
        elseif($score >= 35) $tiers['medium']++;
        elseif($score > 0) $tiers['low']++;
        else $tiers['inactive']++;
        
        $userScores[] = $u;
    }
}

// Top 10 most engaged
usort($userScores, function($a, $b) { return $b['score'] - $a['score']; });
$topUsers = array_slice($userScores, 0, 10);

// === RETENTION DATA (30 days signup cohort) ===
$retentionLabels = [];
$retentionData = [];
for($i = 4; $i >= 0; $i--) {
    $start = date('Y-m-d', strtotime("-" . ($i * 7 + 6) . " days"));
    $end = date('Y-m-d', strtotime("-" . ($i * 7) . " days"));
    $retentionLabels[] = date('d M', strtotime($start));
    
    // Users signed up in this week
    $cohort = (int)$conn->query("SELECT COUNT(*) as c FROM users WHERE DATE(created_at) BETWEEN '$start' AND '$end'")->fetch_assoc()['c'];
    // Of those, how many did something (review, med, doc)
    $active = (int)$conn->query("
        SELECT COUNT(DISTINCT u.id) as c FROM users u 
        WHERE DATE(u.created_at) BETWEEN '$start' AND '$end'
        AND (
            EXISTS (SELECT 1 FROM reviews WHERE user_id = u.id)
            OR EXISTS (SELECT 1 FROM medications WHERE user_id = u.id)
            OR EXISTS (SELECT 1 FROM documents WHERE user_id = u.id)
        )
    ")->fetch_assoc()['c'];
    
    $retentionData[] = $cohort > 0 ? round(($active / $cohort) * 100) : 0;
}

// === DAILY ACTIVE USERS (30 days) ===
$dauLabels = [];
$dauData = [];
for($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dauLabels[] = date('d M', strtotime($date));
    $dau = (int)$conn->query("
        SELECT COUNT(DISTINCT user_id) as c FROM (
            SELECT user_id FROM reviews WHERE DATE(created_at) = '$date'
            UNION ALL SELECT user_id FROM medications WHERE DATE(created_at) = '$date'
            UNION ALL SELECT user_id FROM documents WHERE DATE(uploaded_at) = '$date'
        ) AS dau
    ")->fetch_assoc()['c'];
    $dauData[] = $dau;
}

// Disease distribution
$diseases = [];
$dRes = $conn->query("SELECT diseases FROM users WHERE diseases IS NOT NULL AND diseases != ''");
if($dRes) {
    while($d = $dRes->fetch_assoc()) {
        $items = preg_split('/[,;|]/', $d['diseases']);
        foreach($items as $item) {
            $item = trim($item);
            if(!empty($item)) {
                $diseases[$item] = ($diseases[$item] ?? 0) + 1;
            }
        }
    }
}
arsort($diseases);
$diseases = array_slice($diseases, 0, 10, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Engagement — HealthDial Admin</title>
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
                <div class="page-header">
                    <h1 class="page-title"><i class="fas fa-chart-line" style="color:var(--primary);margin-right:8px;"></i>User Engagement</h1>
                    <p class="page-subtitle">Measure user adoption, retention and feature usage</p>
                </div>

                <!-- Feature Adoption Metrics -->
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;">
                    <?php
                    $metrics = [
                        ['Push Registered', $pushRate, $withPush, 'fa-mobile-alt', '#10b981'],
                        ['Reviewed', $reviewRate, $withReview, 'fa-star', '#f59e0b'],
                        ['Using Meds', $medRate, $withMed, 'fa-pills', '#3b82f6'],
                        ['Uploaded Docs', $docRate, $withDoc, 'fa-file-alt', '#6366f1'],
                        ['Has Location', $locationRate, $withLocation, 'fa-map-pin', '#06b6d4'],
                        ['Health Profile', $diseaseRate, $withDiseases, 'fa-notes-medical', '#ec4899'],
                        ['Active (7d)', $activeRate, $activeWeek, 'fa-fire', '#ef4444'],
                    ];
                    foreach($metrics as $idx => $m): ?>
                    <div class="card fade-in" style="padding:18px;<?php echo $idx >= 4 ? '' : ''; ?>">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                            <div style="width:32px;height:32px;border-radius:8px;background:<?php echo $m[4]; ?>15;display:flex;align-items:center;justify-content:center;color:<?php echo $m[4]; ?>;font-size:13px;">
                                <i class="fas <?php echo $m[3]; ?>"></i>
                            </div>
                            <span style="font-size:22px;font-weight:800;color:<?php echo $m[4]; ?>;"><?php echo $m[1]; ?>%</span>
                        </div>
                        <div style="font-size:12px;font-weight:600;color:var(--text-secondary);margin-bottom:6px;"><?php echo $m[0]; ?></div>
                        <div style="height:5px;background:var(--border-light);border-radius:3px;overflow:hidden;">
                            <div style="height:100%;width:<?php echo $m[1]; ?>%;background:<?php echo $m[4]; ?>;border-radius:3px;transition:width 1s ease;"></div>
                        </div>
                        <div style="font-size:10px;color:var(--text-muted);margin-top:4px;"><?php echo $m[2]; ?> of <?php echo $totalUsers; ?> users</div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Engagement Tier Summary -->
                    <div class="card fade-in" style="padding:18px;">
                        <div style="font-size:12px;font-weight:600;color:var(--text-secondary);margin-bottom:8px;">Engagement Tiers</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                            <div style="text-align:center;padding:6px;background:rgba(16,185,129,0.06);border-radius:6px;">
                                <div style="font-size:16px;font-weight:800;color:#10b981;"><?php echo $tiers['high']; ?></div>
                                <div style="font-size:9px;color:var(--text-muted);font-weight:600;">HIGH</div>
                            </div>
                            <div style="text-align:center;padding:6px;background:rgba(245,158,11,0.06);border-radius:6px;">
                                <div style="font-size:16px;font-weight:800;color:#f59e0b;"><?php echo $tiers['medium']; ?></div>
                                <div style="font-size:9px;color:var(--text-muted);font-weight:600;">MEDIUM</div>
                            </div>
                            <div style="text-align:center;padding:6px;background:rgba(59,130,246,0.06);border-radius:6px;">
                                <div style="font-size:16px;font-weight:800;color:#3b82f6;"><?php echo $tiers['low']; ?></div>
                                <div style="font-size:9px;color:var(--text-muted);font-weight:600;">LOW</div>
                            </div>
                            <div style="text-align:center;padding:6px;background:rgba(100,116,139,0.06);border-radius:6px;">
                                <div style="font-size:16px;font-weight:800;color:#64748b;"><?php echo $tiers['inactive']; ?></div>
                                <div style="font-size:9px;color:var(--text-muted);font-weight:600;">INACTIVE</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div style="display:grid;grid-template-columns:1.3fr 0.7fr;gap:20px;margin-bottom:20px;">
                    <div class="card fade-in">
                        <div class="card-header"><div><h3 class="card-title">Daily Active Users</h3><p class="card-subtitle">Users who took an action — 30 days</p></div></div>
                        <div class="card-body"><div class="chart-container"><canvas id="dauChart"></canvas></div></div>
                    </div>
                    <div class="card fade-in fade-in-delay-1">
                        <div class="card-header"><div><h3 class="card-title">Cohort Retention</h3><p class="card-subtitle">% of weekly signups that engaged</p></div></div>
                        <div class="card-body"><div class="chart-container"><canvas id="retentionChart"></canvas></div></div>
                    </div>
                </div>

                <!-- Bottom: Top Users + Disease Distribution -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <!-- Top Engaged Users -->
                    <div class="card fade-in">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-trophy" style="margin-right:8px;color:#f59e0b;"></i>Top Engaged Users</h3></div>
                        <div style="overflow-x:auto;">
                            <table class="data-table">
                                <thead><tr><th>#</th><th>User</th><th>Reviews</th><th>Meds</th><th>Docs</th><th>Score</th></tr></thead>
                                <tbody>
                                    <?php foreach($topUsers as $idx => $tu): 
                                        $scoreColor = $tu['score'] >= 70 ? '#10b981' : ($tu['score'] >= 35 ? '#f59e0b' : '#ef4444');
                                    ?>
                                    <tr>
                                        <td style="font-weight:700;color:<?php echo $idx < 3 ? '#f59e0b' : 'var(--text-muted)'; ?>;">
                                            <?php echo $idx < 3 ? '🏆' : ($idx + 1); ?>
                                        </td>
                                        <td>
                                            <a href="UserDetail.php?id=<?php echo $tu['id']; ?>" style="font-weight:600;font-size:13px;color:var(--text-primary);text-decoration:none;">
                                                <?php echo htmlspecialchars($tu['name']); ?>
                                            </a>
                                        </td>
                                        <td style="font-size:13px;"><?php echo $tu['review_count']; ?></td>
                                        <td style="font-size:13px;"><?php echo $tu['med_count']; ?></td>
                                        <td style="font-size:13px;"><?php echo $tu['doc_count']; ?></td>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:6px;">
                                                <div style="flex:1;height:6px;background:var(--border-light);border-radius:3px;overflow:hidden;max-width:60px;">
                                                    <div style="height:100%;width:<?php echo $tu['score']; ?>%;background:<?php echo $scoreColor; ?>;border-radius:3px;"></div>
                                                </div>
                                                <span style="font-size:12px;font-weight:700;color:<?php echo $scoreColor; ?>;"><?php echo $tu['score']; ?></span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Disease Distribution -->
                    <div class="card fade-in fade-in-delay-1">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-notes-medical" style="margin-right:8px;color:#ec4899;"></i>Reported Health Conditions</h3></div>
                        <div class="card-body">
                            <?php if(!empty($diseases)): ?>
                            <?php $maxD = max($diseases); foreach($diseases as $name => $cnt): $pctD = round(($cnt / $maxD) * 100); ?>
                            <div style="margin-bottom:10px;">
                                <div style="display:flex;justify-content:space-between;margin-bottom:3px;">
                                    <span style="font-size:12px;font-weight:600;"><?php echo htmlspecialchars($name); ?></span>
                                    <span style="font-size:11px;font-weight:700;color:var(--text-muted);"><?php echo $cnt; ?> users</span>
                                </div>
                                <div style="height:6px;background:var(--border-light);border-radius:3px;overflow:hidden;">
                                    <div style="height:100%;width:<?php echo $pctD; ?>%;background:linear-gradient(90deg,#ec4899,#f43f5e);border-radius:3px;transition:width 0.8s;"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <div class="empty-state" style="padding:20px;"><p style="color:var(--text-muted);font-size:13px;">No health condition data recorded</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // DAU Chart
    new Chart(document.getElementById('dauChart'), {
        type: 'line',
        data: { labels: <?php echo json_encode($dauLabels); ?>, datasets: [{ label: 'Active Users', data: <?php echo json_encode($dauData); ?>, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.06)', fill: true, tension: 0.4, borderWidth: 2, pointRadius: 2, pointHoverRadius: 5 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { font: {size:10, family:'Inter'}, color:'#94a3b8', maxTicksLimit: 10 } }, y: { beginAtZero: true, grid: { color:'rgba(0,0,0,0.03)' }, ticks: { font: {size:10, family:'Inter'}, color:'#94a3b8', stepSize: 1 } } } }
    });

    // Retention Chart
    new Chart(document.getElementById('retentionChart'), {
        type: 'bar',
        data: { labels: <?php echo json_encode($retentionLabels); ?>, datasets: [{ label: 'Retention %', data: <?php echo json_encode($retentionData); ?>, backgroundColor: '#6366f1', borderRadius: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { font: {size:10, family:'Inter'} } }, y: { beginAtZero: true, max: 100, grid: { color:'rgba(0,0,0,0.03)' }, ticks: { font: {size:10, family:'Inter'}, callback: v => v+'%' } } } }
    });
    </script>
</body>
</html>
