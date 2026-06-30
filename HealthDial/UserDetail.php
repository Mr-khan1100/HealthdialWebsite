<?php
require_once 'connection.inc.php';
requireAdmin(); // Users section is admin-only (not staff).

$id = intval($_GET['id'] ?? 0);
if($id <= 0) { header("Location: Users.php"); exit(); }

// Handle push notification
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_push'])) {
    $pushTitle = trim($_POST['push_title']);
    $pushBody = trim($_POST['push_body']);
    $tokenRes = $conn->prepare("SELECT expo_push_token FROM users WHERE id = ?");
    $tokenRes->bind_param("i", $id);
    $tokenRes->execute();
    $tokenRow = $tokenRes->get_result()->fetch_assoc();
    if($tokenRow && !empty($tokenRow['expo_push_token'])) {
        $ch = curl_init("https://exp.host/--/api/v2/push/send");
        $payload = json_encode([["to" => $tokenRow['expo_push_token'], "sound" => "default", "title" => $pushTitle, "body" => $pushBody]]);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ["Content-Type: application/json","Accept: application/json","expo-project-id: 08113abe-dc1d-449d-8e30-ef15806e4c0a"],
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Also log to notification_queue
        $logStmt = $conn->prepare("INSERT INTO notification_queue (user_id, notification_type, title, message, status, sent_at, created_at) VALUES (?, 'manual', ?, ?, ?, NOW(), NOW())");
        $status = ($httpCode >= 200 && $httpCode < 300) ? 'sent' : 'failed';
        $logStmt->bind_param("isss", $id, $pushTitle, $pushBody, $status);
        $logStmt->execute();
        
        // Log activity
        $aStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, action, details, ip_address) VALUES (?, 'send_push', ?, ?)");
        $det = "Sent push to user #$id: $pushTitle";
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $aStmt->bind_param("iss", $_SESSION['admin_id'], $det, $ip);
        $aStmt->execute();
        
        $_SESSION['success'] = ($status === 'sent') ? "Push notification sent!" : "Push notification may have failed (HTTP $httpCode).";
    } else {
        $_SESSION['error'] = "User has no push token registered.";
    }
    header("Location: UserDetail.php?id=$id"); exit();
}

// Handle edit user
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $newName = trim($_POST['name']);
    $newEmail = trim($_POST['email']);
    $newMobile = trim($_POST['mobile']);
    $newState = trim($_POST['state']);
    $newAddress = trim($_POST['address']);
    $newDiseases = trim($_POST['diseases']);
    $newStatus = intval($_POST['user_status']);
    
    $stmt = $conn->prepare("UPDATE users SET name=?, email=?, mobile=?, state=?, address=?, diseases=?, status=? WHERE id=?");
    $stmt->bind_param("ssssssii", $newName, $newEmail, $newMobile, $newState, $newAddress, $newDiseases, $newStatus, $id);
    $stmt->execute();
    
    $aStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, action, details, ip_address) VALUES (?, 'edit_user', ?, ?)");
    $det = "Edited user #$id: $newName";
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $aStmt->bind_param("iss", $_SESSION['admin_id'], $det, $ip);
    $aStmt->execute();
    
    $_SESSION['success'] = "User updated!";
    header("Location: UserDetail.php?id=$id"); exit();
}

// Toggle status
if(isset($_GET['toggle_status'])) {
    $stmt = $conn->prepare("UPDATE users SET status = IF(status=1,0,1) WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $_SESSION['success'] = "User status toggled!";
    header("Location: UserDetail.php?id=$id"); exit();
}

// Get user
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if(!$user) { $_SESSION['error'] = "User not found"; header("Location: Users.php"); exit(); }

// Reviews
$reviews = [];
$reviewRes = $conn->prepare("SELECT r.*, l.name as listing_name FROM reviews r LEFT JOIN listings l ON r.listing_id = l.id WHERE r.user_id = ? ORDER BY r.created_at DESC");
$reviewRes->bind_param("i", $id);
$reviewRes->execute();
$res1 = $reviewRes->get_result();
while($r = $res1->fetch_assoc()) $reviews[] = $r;

// Average rating given
$avgRating = 0;
if(count($reviews) > 0) {
    $sum = array_sum(array_column($reviews, 'rating'));
    $avgRating = round($sum / count($reviews), 1);
}

// Medications
$medications = [];
$medRes = $conn->prepare("SELECT * FROM medications WHERE user_id = ? ORDER BY is_active DESC, created_at DESC");
$medRes->bind_param("i", $id);
$medRes->execute();
$res2 = $medRes->get_result();
while($m = $res2->fetch_assoc()) $medications[] = $m;

// Documents
$documents = [];
$docRes = $conn->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY uploaded_at DESC");
$docRes->bind_param("i", $id);
$docRes->execute();
$res3 = $docRes->get_result();
while($d = $res3->fetch_assoc()) $documents[] = $d;

// Medicine reminders
$reminders = [];
$remRes = $conn->prepare("SELECT * FROM medicine_reminders WHERE user_id = ? ORDER BY created_at DESC");
$remRes->bind_param("i", $id);
$remRes->execute();
$res4 = $remRes->get_result();
while($rm = $res4->fetch_assoc()) $reminders[] = $rm;

// Medicine history (adherence)
$histRes = $conn->prepare("SELECT mh.status, COUNT(*) as cnt FROM medicine_history mh JOIN medicine_reminders mr ON mh.reminder_id = mr.id WHERE mr.user_id = ? GROUP BY mh.status");
$histRes->bind_param("i", $id);
$histRes->execute();
$adherence = ['taken'=>0,'missed'=>0,'snoozed'=>0];
$res5 = $histRes->get_result();
while($h = $res5->fetch_assoc()) $adherence[$h['status']] = (int)$h['cnt'];
$totalDoses = array_sum($adherence);
$adherenceRate = $totalDoses > 0 ? round(($adherence['taken'] / $totalDoses) * 100) : 0;

// Notifications sent to this user
$notifs = [];
$notifRes = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$notifRes->bind_param("i", $id);
$notifRes->execute();
$res6 = $notifRes->get_result();
while($n = $res6->fetch_assoc()) $notifs[] = $n;

// Push notifications from queue for this user
$pushHistory = [];
$pushRes = $conn->prepare("SELECT * FROM notification_queue WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$pushRes->bind_param("i", $id);
$pushRes->execute();
$res7 = $pushRes->get_result();
while($p = $res7->fetch_assoc()) $pushHistory[] = $p;

// Last activity (most recent action timestamp)
$lastActivity = null;
$laRes = $conn->prepare("
    SELECT MAX(ts) as last_ts FROM (
        SELECT MAX(created_at) as ts FROM reviews WHERE user_id = ?
        UNION ALL SELECT MAX(created_at) FROM medications WHERE user_id = ?
        UNION ALL SELECT MAX(uploaded_at) FROM documents WHERE user_id = ?
    ) AS t
");
$laRes->bind_param("iii", $id, $id, $id);
$laRes->execute();
$laRow = $laRes->get_result()->fetch_assoc();
$lastActivity = $laRow['last_ts'] ?? null;

// Timeline
$timeline = [];
foreach($reviews as $rev) {
    $timeline[] = ['icon'=>'fa-star','color'=>'#f59e0b','title'=>'Reviewed '.htmlspecialchars($rev['listing_name'] ?? 'a listing'),'detail'=>$rev['rating'].' stars','time'=>$rev['created_at']];
}
foreach($medications as $med) {
    $timeline[] = ['icon'=>'fa-pills','color'=>'#10b981','title'=>'Added: '.htmlspecialchars($med['name']),'detail'=>$med['dosage'].' · '.ucfirst($med['frequency']),'time'=>$med['created_at']];
}
foreach($documents as $doc) {
    $timeline[] = ['icon'=>'fa-file-alt','color'=>'#6366f1','title'=>'Uploaded: '.htmlspecialchars($doc['original_name']),'detail'=>$doc['file_size'],'time'=>$doc['uploaded_at']];
}
foreach($pushHistory as $ph) {
    $ps = $ph['status'] === 'sent' ? '#10b981' : ($ph['status'] === 'failed' ? '#ef4444' : '#f59e0b');
    $timeline[] = ['icon'=>'fa-bell','color'=>$ps,'title'=>'Push: '.htmlspecialchars($ph['title'] ?? 'Notification'),'detail'=>ucfirst($ph['status']),'time'=>$ph['created_at']];
}
$timeline[] = ['icon'=>'fa-user-plus','color'=>'#3b82f6','title'=>'Account created','detail'=>'Registered on HealthDial','time'=>$user['created_at']];

usort($timeline, function($a, $b) { return strtotime($b['time']) - strtotime($a['time']); });
$timeline = array_slice($timeline, 0, 20);

// Account age
$dt = new DateTime($user['created_at']);
$diff = $dt->diff(new DateTime());
if($diff->y > 0) $ageStr = $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
elseif($diff->m > 0) $ageStr = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
else $ageStr = max(1, $diff->d) . ' day' . ($diff->d > 1 ? 's' : '');

// Initials
$initials = strtoupper(substr($user['name'] ?? 'U', 0, 1));
$parts = explode(' ', $user['name'] ?? 'User');
if(count($parts) > 1) $initials .= strtoupper(substr(end($parts), 0, 1));

// Engagement Score
$engScore = min(100, count($reviews) * 15 + count($medications) * 10 + count($documents) * 8 + count($reminders) * 5 + (!empty($user['expo_push_token']) ? 10 : 0) + (!empty($user['diseases']) ? 5 : 0));
$engLevel = $engScore >= 70 ? 'High' : ($engScore >= 35 ? 'Medium' : 'Low');
$engColor = $engScore >= 70 ? '#10b981' : ($engScore >= 35 ? '#f59e0b' : '#ef4444');

$hasLocation = !empty($user['latitude']) && !empty($user['longitude']) && $user['latitude'] != 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['name']); ?> — HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php if($hasLocation): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <?php endif; ?>
</head>
<body>
    <div class="admin-wrapper">
        <?php include 'sidebar.php'; ?>
        <div class="admin-main">
            <?php include 'header.php'; ?>
            <div class="admin-content">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <a href="Users.php" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text-muted);text-decoration:none;font-weight:500;">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </a>
                    <div style="display:flex;gap:8px;">
                        <button onclick="document.getElementById('editModal').classList.add('show')" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i> Edit</button>
                        <a href="?id=<?php echo $id; ?>&toggle_status=1" class="btn btn-secondary btn-sm" onclick="return confirm('Toggle user status?')">
                            <i class="fas fa-power-off"></i> <?php echo $user['status'] ? 'Deactivate' : 'Activate'; ?>
                        </a>
                        <button onclick="document.getElementById('pushModal').classList.add('show')" class="btn btn-primary btn-sm"><i class="fas fa-bell"></i> Send Push</button>
                    </div>
                </div>

                <!-- Profile Card -->
                <div class="card fade-in" style="margin-bottom:20px;overflow:hidden;">
                    <div style="height:100px;background:linear-gradient(135deg,#0f172a 0%,#10b981 50%,#6366f1 100%);"></div>
                    <div style="padding:0 32px 28px;margin-top:-48px;">
                        <div style="display:flex;align-items:flex-end;gap:24px;flex-wrap:wrap;">
                            <div style="width:96px;height:96px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-size:32px;font-weight:800;border:5px solid var(--bg-card);flex-shrink:0;box-shadow:0 4px 15px rgba(0,0,0,0.15);">
                                <?php echo $initials; ?>
                            </div>
                            <div style="flex:1;min-width:200px;padding-top:52px;">
                                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                                    <h2 style="font-size:24px;font-weight:800;margin:0;"><?php echo htmlspecialchars($user['name']); ?></h2>
                                    <span class="badge <?php echo $user['status'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo $user['status'] ? 'Active' : 'Inactive'; ?></span>
                                    <?php if(!empty($user['expo_push_token'])): ?>
                                    <span class="badge badge-info" title="Push notifications enabled"><i class="fas fa-bell" style="margin-right:3px;"></i>Push Enabled</span>
                                    <?php endif; ?>
                                    <?php if(!empty($user['google_id'])): ?>
                                    <span class="badge badge-purple" title="Signed in with Google" style="display:inline-flex;align-items:center;gap:4px;"><i class="fab fa-google" style="font-size:10px;"></i>Google Account</span>
                                    <?php endif; ?>
                                </div>
                                <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:8px;font-size:13px;color:var(--text-muted);">
                                    <span><i class="fas fa-envelope" style="margin-right:5px;color:var(--primary);"></i><?php echo htmlspecialchars($user['email'] ?? 'No email'); ?></span>
                                    <span><i class="fas fa-phone" style="margin-right:5px;color:var(--primary);"></i><?php echo htmlspecialchars($user['mobile'] ?? 'No mobile'); ?></span>
                                    <?php if($user['state']): ?><span><i class="fas fa-map-pin" style="margin-right:5px;color:var(--primary);"></i><?php echo htmlspecialchars($user['state']); ?></span><?php endif; ?>
                                    <span><i class="fas fa-calendar" style="margin-right:5px;color:var(--primary);"></i>Member for <?php echo $ageStr; ?></span>
                                    <?php if($lastActivity): ?>
                                    <span><i class="fas fa-clock" style="margin-right:5px;color:var(--status-warning);"></i>Last active: <?php echo date('d M Y', strtotime($lastActivity)); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Engagement Ring -->
                            <div style="padding-top:52px;text-align:center;">
                                <div style="position:relative;width:80px;height:80px;">
                                    <svg width="80" height="80" viewBox="0 0 80 80" style="transform:rotate(-90deg);">
                                        <circle cx="40" cy="40" r="34" fill="none" stroke="var(--border-light)" stroke-width="6"/>
                                        <circle cx="40" cy="40" r="34" fill="none" stroke="<?php echo $engColor; ?>" stroke-width="6" stroke-dasharray="<?php echo round(213.6 * $engScore / 100); ?> 213.6" stroke-linecap="round"/>
                                    </svg>
                                    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:18px;font-weight:800;color:<?php echo $engColor; ?>;"><?php echo $engScore; ?></div>
                                </div>
                                <div style="font-size:10px;font-weight:700;color:<?php echo $engColor; ?>;margin-top:4px;"><?php echo $engLevel; ?> Engagement</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stat Cards -->
                <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:20px;">
                    <div class="card fade-in" style="padding:16px;text-align:center;">
                        <div style="font-size:24px;font-weight:800;color:var(--primary);"><?php echo count($reviews); ?></div>
                        <div style="font-size:10px;color:var(--text-muted);font-weight:600;">REVIEWS</div>
                        <?php if($avgRating > 0): ?><div style="font-size:10px;color:#f59e0b;margin-top:2px;"><i class="fas fa-star"></i> <?php echo $avgRating; ?> avg</div><?php endif; ?>
                    </div>
                    <div class="card fade-in fade-in-delay-1" style="padding:16px;text-align:center;">
                        <div style="font-size:24px;font-weight:800;color:#3b82f6;"><?php echo count($medications); ?></div>
                        <div style="font-size:10px;color:var(--text-muted);font-weight:600;">MEDICATIONS</div>
                        <div style="font-size:10px;color:var(--primary);margin-top:2px;"><?php echo count(array_filter($medications, function($m) { return $m['is_active']; })); ?> active</div>
                    </div>
                    <div class="card fade-in fade-in-delay-2" style="padding:16px;text-align:center;">
                        <div style="font-size:24px;font-weight:800;color:#6366f1;"><?php echo count($documents); ?></div>
                        <div style="font-size:10px;color:var(--text-muted);font-weight:600;">DOCUMENTS</div>
                    </div>
                    <div class="card fade-in fade-in-delay-3" style="padding:16px;text-align:center;">
                        <div style="font-size:24px;font-weight:800;color:<?php echo $adherenceRate >= 70 ? '#10b981' : ($adherenceRate >= 40 ? '#f59e0b' : '#ef4444'); ?>;"><?php echo $adherenceRate; ?>%</div>
                        <div style="font-size:10px;color:var(--text-muted);font-weight:600;">ADHERENCE</div>
                        <div style="font-size:10px;color:var(--text-muted);margin-top:2px;"><?php echo $adherence['taken']; ?>/<?php echo $totalDoses; ?></div>
                    </div>
                    <div class="card fade-in" style="padding:16px;text-align:center;">
                        <div style="font-size:24px;font-weight:800;color:#ec4899;"><?php echo count($reminders); ?></div>
                        <div style="font-size:10px;color:var(--text-muted);font-weight:600;">REMINDERS</div>
                    </div>
                    <div class="card fade-in fade-in-delay-1" style="padding:16px;text-align:center;">
                        <div style="font-size:24px;font-weight:800;color:#f59e0b;"><?php echo count($pushHistory); ?></div>
                        <div style="font-size:10px;color:var(--text-muted);font-weight:600;">PUSH SENT</div>
                    </div>
                </div>

                <!-- First Row: Details + Charts + Map/Adherence -->
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:20px;">
                    <!-- Personal Info -->
                    <div class="card fade-in">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-id-card" style="margin-right:8px;color:var(--primary);"></i>Personal Info</h3></div>
                        <div class="card-body">
                            <?php
                            $fields = [
                                ['Address', $user['address'] ?? 'Not provided', 'fa-home'],
                                ['Health Conditions', $user['diseases'] ?? 'None recorded', 'fa-notes-medical'],
                                ['Auth Method', !empty($user['google_id']) ? 'Google Sign-In' : 'Mobile + Password', !empty($user['google_id']) ? 'fa-google fab' : 'fa-mobile-alt'],
                                ['Google ID', !empty($user['google_id']) ? $user['google_id'] : 'Not linked', 'fa-id-badge'],
                                ['Push Token', !empty($user['expo_push_token']) ? substr($user['expo_push_token'], 0, 25) . '...' : 'Not registered', 'fa-bell'],
                                ['Joined', date('d M Y, h:i A', strtotime($user['created_at'])), 'fa-calendar'],
                            ];
                            foreach($fields as $f): ?>
                            <div style="display:flex;gap:10px;padding:10px 0;border-bottom:1px solid var(--border-light);">
                                <i class="fas <?php echo $f[2]; ?>" style="width:16px;text-align:center;margin-top:2px;color:var(--text-muted);font-size:11px;"></i>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:10px;color:var(--text-muted);font-weight:600;text-transform:uppercase;"><?php echo $f[0]; ?></div>
                                    <div style="font-size:12px;margin-top:2px;word-break:break-word;"><?php echo htmlspecialchars($f[1]); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Activity Breakdown Chart -->
                    <div class="card fade-in fade-in-delay-1">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-pie" style="margin-right:8px;color:var(--accent);"></i>Activity Breakdown</h3></div>
                        <div class="card-body"><div class="chart-container" style="height:200px;"><canvas id="engChart"></canvas></div></div>
                    </div>

                    <!-- Map or Adherence -->
                    <?php if($hasLocation): ?>
                    <div class="card fade-in fade-in-delay-2">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-map-marker-alt" style="margin-right:8px;color:var(--status-danger);"></i>Location</h3></div>
                        <div id="userMap" style="height:250px;border-radius:0 0 var(--radius-lg) var(--radius-lg);"></div>
                    </div>
                    <?php else: ?>
                    <div class="card fade-in fade-in-delay-2">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-heartbeat" style="margin-right:8px;color:var(--status-danger);"></i>Med Adherence</h3></div>
                        <div class="card-body">
                            <div class="chart-container" style="height:160px;"><canvas id="adherenceChart"></canvas></div>
                            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;margin-top:8px;">
                                <div style="text-align:center;padding:6px;background:rgba(16,185,129,0.06);border-radius:6px;">
                                    <div style="font-size:18px;font-weight:800;color:#10b981;"><?php echo $adherence['taken']; ?></div>
                                    <div style="font-size:9px;color:var(--text-muted);font-weight:600;">TAKEN</div>
                                </div>
                                <div style="text-align:center;padding:6px;background:rgba(239,68,68,0.06);border-radius:6px;">
                                    <div style="font-size:18px;font-weight:800;color:#ef4444;"><?php echo $adherence['missed']; ?></div>
                                    <div style="font-size:9px;color:var(--text-muted);font-weight:600;">MISSED</div>
                                </div>
                                <div style="text-align:center;padding:6px;background:rgba(245,158,11,0.06);border-radius:6px;">
                                    <div style="font-size:18px;font-weight:800;color:#f59e0b;"><?php echo $adherence['snoozed']; ?></div>
                                    <div style="font-size:9px;color:var(--text-muted);font-weight:600;">SNOOZED</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Timeline + Medications -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                    <div class="card fade-in">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-stream" style="margin-right:8px;color:var(--primary);"></i>Activity Timeline</h3><div class="pulse-dot"></div></div>
                        <div class="card-body" style="max-height:380px;overflow-y:auto;">
                            <?php if(count($timeline) > 0): ?>
                            <div class="timeline">
                                <?php foreach($timeline as $ev): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot" style="background:<?php echo $ev['color']; ?>;"></div>
                                    <div class="timeline-content">
                                        <div style="display:flex;align-items:center;gap:6px;">
                                            <i class="fas <?php echo $ev['icon']; ?>" style="font-size:10px;color:<?php echo $ev['color']; ?>;"></i>
                                            <strong style="font-size:12px;"><?php echo $ev['title']; ?></strong>
                                        </div>
                                        <span style="font-size:11px;color:var(--text-muted);"><?php echo $ev['detail']; ?></span>
                                    </div>
                                    <div class="timeline-time"><?php echo date('d M Y', strtotime($ev['time'])); ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="empty-state" style="padding:24px;"><p style="color:var(--text-muted);">No activity yet</p></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card fade-in fade-in-delay-1">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-pills" style="margin-right:8px;color:var(--status-info);"></i>Medications (<?php echo count($medications); ?>)</h3></div>
                        <div style="max-height:380px;overflow-y:auto;">
                            <?php if(count($medications) > 0): foreach($medications as $med): ?>
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 24px;border-bottom:1px solid var(--border-light);">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div style="width:32px;height:32px;border-radius:8px;background:<?php echo $med['is_active'] ? 'rgba(16,185,129,0.1)' : 'rgba(100,116,139,0.1)'; ?>;display:flex;align-items:center;justify-content:center;color:<?php echo $med['is_active'] ? 'var(--primary)' : 'var(--text-muted)'; ?>;font-size:12px;"><i class="fas fa-capsules"></i></div>
                                    <div>
                                        <div style="font-weight:600;font-size:12px;"><?php echo htmlspecialchars($med['name']); ?></div>
                                        <div style="font-size:11px;color:var(--text-muted);"><?php echo $med['dosage']; ?> · <?php echo ucfirst($med['frequency']); ?> · Since <?php echo date('d M', strtotime($med['start_date'])); ?></div>
                                    </div>
                                </div>
                                <span class="badge <?php echo $med['is_active'] ? 'badge-success' : 'badge-gray'; ?>" style="font-size:10px;"><?php echo $med['is_active'] ? 'Active' : 'Done'; ?></span>
                            </div>
                            <?php endforeach; else: ?>
                            <div style="padding:30px;text-align:center;color:var(--text-muted);font-size:13px;">No medications</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Reviews + Documents + Push History -->
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">
                    <div class="card fade-in">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-star" style="margin-right:8px;color:#f59e0b;"></i>Reviews (<?php echo count($reviews); ?>)</h3></div>
                        <div style="max-height:280px;overflow-y:auto;">
                            <?php if(count($reviews) > 0): foreach($reviews as $rev): ?>
                            <div style="padding:12px 24px;border-bottom:1px solid var(--border-light);">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px;">
                                    <span style="font-weight:600;font-size:11px;"><?php echo htmlspecialchars($rev['listing_name'] ?? 'Deleted'); ?></span>
                                    <div class="star-rating" style="font-size:9px;"><?php for($i=1;$i<=5;$i++): ?><i class="fas fa-star <?php echo $i <= $rev['rating'] ? '' : 'empty'; ?>"></i><?php endfor; ?></div>
                                </div>
                                <p style="font-size:11px;color:var(--text-muted);margin:0;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?php echo htmlspecialchars($rev['review']); ?></p>
                                <div style="font-size:10px;color:var(--text-muted);margin-top:3px;"><?php echo date('d M Y', strtotime($rev['created_at'])); ?></div>
                            </div>
                            <?php endforeach; else: ?>
                            <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:12px;">No reviews</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card fade-in fade-in-delay-1">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-file-medical" style="margin-right:8px;color:var(--accent);"></i>Documents (<?php echo count($documents); ?>)</h3></div>
                        <div style="max-height:280px;overflow-y:auto;">
                            <?php if(count($documents) > 0): foreach($documents as $doc): ?>
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 24px;border-bottom:1px solid var(--border-light);">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <i class="fas fa-file-alt" style="color:var(--accent);font-size:11px;"></i>
                                    <div>
                                        <div style="font-size:11px;font-weight:600;"><?php echo htmlspecialchars($doc['original_name']); ?></div>
                                        <div style="font-size:10px;color:var(--text-muted);"><?php echo $doc['file_size']; ?> · <?php echo date('d M Y', strtotime($doc['uploaded_at'])); ?></div>
                                    </div>
                                </div>
                                <?php if($doc['file_path']): ?><a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="btn btn-ghost btn-icon btn-sm"><i class="fas fa-download"></i></a><?php endif; ?>
                            </div>
                            <?php endforeach; else: ?>
                            <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:12px;">No documents</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card fade-in fade-in-delay-2">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-bell" style="margin-right:8px;color:#3b82f6;"></i>Push History (<?php echo count($pushHistory); ?>)</h3></div>
                        <div style="max-height:280px;overflow-y:auto;">
                            <?php if(count($pushHistory) > 0): foreach($pushHistory as $ph):
                                $phBadge = 'badge-gray';
                                if($ph['status'] === 'sent') $phBadge = 'badge-success';
                                elseif($ph['status'] === 'failed') $phBadge = 'badge-danger';
                                elseif($ph['status'] === 'pending') $phBadge = 'badge-warning';
                            ?>
                            <div style="padding:10px 24px;border-bottom:1px solid var(--border-light);">
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <span style="font-size:11px;font-weight:600;"><?php echo htmlspecialchars($ph['title'] ?? 'Notification'); ?></span>
                                    <span class="badge <?php echo $phBadge; ?>" style="font-size:9px;"><?php echo ucfirst($ph['status']); ?></span>
                                </div>
                                <div style="font-size:10px;color:var(--text-muted);margin-top:2px;"><?php echo date('d M Y, h:i A', strtotime($ph['created_at'])); ?></div>
                            </div>
                            <?php endforeach; else: ?>
                            <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:12px;">No push history</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal-overlay" id="editModal" onclick="if(event.target===this)this.classList.remove('show')">
        <div class="modal" style="max-width:500px;">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-user-edit" style="color:var(--primary);margin-right:8px;"></i>Edit User</h3>
                <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('show')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="edit_user" value="1">
                <div style="padding:20px;display:grid;gap:12px;">
                    <div class="form-group" style="margin:0;"><label class="form-label">Name</label><input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required class="form-input"></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group" style="margin:0;"><label class="form-label">Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" class="form-input"></div>
                        <div class="form-group" style="margin:0;"><label class="form-label">Mobile</label><input type="text" name="mobile" value="<?php echo htmlspecialchars($user['mobile'] ?? ''); ?>" class="form-input"></div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group" style="margin:0;"><label class="form-label">State</label><input type="text" name="state" value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>" class="form-input"></div>
                        <div class="form-group" style="margin:0;"><label class="form-label">Status</label>
                            <select name="user_status" class="form-select"><option value="1" <?php echo $user['status'] ? 'selected' : ''; ?>>Active</option><option value="0" <?php echo !$user['status'] ? 'selected' : ''; ?>>Inactive</option></select>
                        </div>
                    </div>
                    <div class="form-group" style="margin:0;"><label class="form-label">Address</label><textarea name="address" class="form-textarea" rows="2"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea></div>
                    <div class="form-group" style="margin:0;"><label class="form-label">Health Conditions</label><textarea name="diseases" class="form-textarea" rows="2"><?php echo htmlspecialchars($user['diseases'] ?? ''); ?></textarea></div>
                    <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Push Modal -->
    <div class="modal-overlay" id="pushModal" onclick="if(event.target===this)this.classList.remove('show')">
        <div class="modal" style="max-width:420px;">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-bell" style="color:var(--primary);margin-right:8px;"></i>Send Push</h3>
                <button class="modal-close" onclick="document.getElementById('pushModal').classList.remove('show')"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="send_push" value="1">
                <div style="padding:20px;">
                    <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">Send to <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                        <?php if(empty($user['expo_push_token'])): ?>
                        <br><span style="color:var(--status-danger);font-size:11px;"><i class="fas fa-exclamation-triangle"></i> No push token — notification won't be delivered</span>
                        <?php endif; ?>
                    </p>
                    <div class="form-group"><label class="form-label">Title *</label><input type="text" name="push_title" required class="form-input" placeholder="Notification title"></div>
                    <div class="form-group"><label class="form-label">Message *</label><textarea name="push_body" required class="form-textarea" rows="3" placeholder="Notification message"></textarea></div>
                    <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fas fa-paper-plane"></i> Send Notification</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Activity Breakdown
    new Chart(document.getElementById('engChart'), {
        type: 'doughnut',
        data: { labels: ['Reviews','Medications','Documents','Reminders','Push'], datasets: [{ data: [<?php echo count($reviews); ?>,<?php echo count($medications); ?>,<?php echo count($documents); ?>,<?php echo count($reminders); ?>,<?php echo count($pushHistory); ?>], backgroundColor: ['#f59e0b','#10b981','#6366f1','#3b82f6','#ec4899'], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 8, font: {size:10,family:'Inter'}, usePointStyle: true, pointStyle: 'circle' } } } }
    });

    <?php if(!$hasLocation): ?>
    // Adherence Chart
    new Chart(document.getElementById('adherenceChart'), {
        type: 'doughnut',
        data: { labels: ['Taken','Missed','Snoozed'], datasets: [{ data: [<?php echo $adherence['taken']; ?>,<?php echo $adherence['missed']; ?>,<?php echo $adherence['snoozed']; ?>], backgroundColor: ['#10b981','#ef4444','#f59e0b'], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false } } }
    });
    <?php endif; ?>

    <?php if($hasLocation): ?>
    // Leaflet Map
    const map = L.map('userMap').setView([<?php echo $user['latitude']; ?>, <?php echo $user['longitude']; ?>], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OSM', maxZoom: 18 }).addTo(map);
    L.marker([<?php echo $user['latitude']; ?>, <?php echo $user['longitude']; ?>])
        .addTo(map)
        .bindPopup('<strong><?php echo htmlspecialchars($user['name']); ?></strong><br><?php echo htmlspecialchars($user['state'] ?? ''); ?>')
        .openPopup();
    <?php endif; ?>
    </script>
</body>
</html>
