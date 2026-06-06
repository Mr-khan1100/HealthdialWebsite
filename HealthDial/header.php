<?php
// Get real unread admin notification count
if(!isset($unreadCount)) {
    $unreadCount = 0;
    $nRes = $conn->query("SELECT COUNT(*) as cnt FROM admin_notifications WHERE status='unread'");
    if($nRes && $r = $nRes->fetch_assoc()) $unreadCount = (int)$r['cnt'];
}

// Get recent notifications for dropdown
$recentNotifs = [];
$rnRes = $conn->query("SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT 5");
if($rnRes) {
    while($rn = $rnRes->fetch_assoc()) $recentNotifs[] = $rn;
}

$pageName = basename($_SERVER['PHP_SELF'], '.php');
$pageName = str_replace(['-', '_'], ' ', $pageName);
$pageName = ucwords($pageName);
if($pageName === 'Index') $pageName = 'Login';

$profileInitials = '';
if(isset($_SESSION['admin_name'])) {
    $parts = explode(' ', $_SESSION['admin_name']);
    $profileInitials = strtoupper(substr($parts[0], 0, 1));
    if(count($parts) > 1) $profileInitials .= strtoupper(substr(end($parts), 0, 1));
}
?>

<!-- Header -->
<header class="admin-header">
    <div class="header-left">
        <button class="header-toggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <div>
            <div class="header-breadcrumb">
                <span>Admin</span>
                <i class="fas fa-chevron-right" style="font-size:9px; color: var(--text-muted);"></i>
                <span style="color: var(--text-primary); font-weight: 600;"><?php echo $pageName; ?></span>
            </div>
        </div>
    </div>
    
    <div class="header-right">
        <!-- Notification Bell -->
        <div class="dropdown">
            <button class="header-icon-btn" onclick="toggleDropdown('notifDropdown')">
                <i class="fas fa-bell"></i>
                <?php if($unreadCount > 0): ?>
                <span class="header-badge"><?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu notification-dropdown" id="notifDropdown">
                <div class="notification-dropdown-header">
                    <h4>Notifications</h4>
                    <?php if($unreadCount > 0): ?>
                    <span class="badge badge-info"><?php echo $unreadCount; ?> new</span>
                    <?php endif; ?>
                </div>
                <?php if(count($recentNotifs) > 0): ?>
                    <?php foreach($recentNotifs as $notif): ?>
                    <div class="notification-item <?php echo $notif['status'] === 'unread' ? 'unread' : ''; ?>">
                        <div class="notification-icon" style="background: rgba(16,185,129,0.1); color: var(--primary);">
                            <i class="fas fa-<?php 
                                $nType = $notif['type'] ?? 'info';
                                if($nType === 'new_listing') echo 'hospital';
                                elseif($nType === 'new_user') echo 'user-plus';
                                elseif($nType === 'new_review') echo 'star';
                                else echo 'bell';
                            ?>"></i>
                        </div>
                        <div class="notification-content">
                            <p><strong><?php echo htmlspecialchars($notif['title']); ?></strong></p>
                            <p class="notification-time"><?php echo date('d M, h:i A', strtotime($notif['created_at'])); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 24px; text-align: center; color: var(--text-muted); font-size: 13px;">
                        <i class="fas fa-bell-slash" style="font-size: 24px; margin-bottom: 8px; display: block;"></i>
                        No notifications yet
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Profile Dropdown -->
        <div class="dropdown">
            <button class="header-profile" onclick="toggleDropdown('profileDropdown')">
                <div class="header-profile-avatar"><?php echo $profileInitials; ?></div>
                <div style="text-align: left; line-height: 1.3;">
                    <div class="header-profile-name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></div>
                    <div class="header-profile-role"><?php echo ucfirst($_SESSION['admin_role'] ?? 'admin'); ?></div>
                </div>
                <i class="fas fa-chevron-down" style="font-size: 10px; color: var(--text-muted); margin-left: 4px;"></i>
            </button>
            <div class="dropdown-menu" id="profileDropdown">
                <a href="Settings.php" class="dropdown-item">
                    <i class="fas fa-user-circle"></i>
                    My Profile
                </a>
                <a href="Settings.php" class="dropdown-item">
                    <i class="fas fa-cog"></i>
                    Settings
                </a>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="dropdown-item danger">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer">
    <?php if(isset($_SESSION['success'])): ?>
    <div class="toast toast-success" id="successToast">
        <div class="toast-icon"><i class="fas fa-check"></i></div>
        <span class="toast-message"><?php echo htmlspecialchars($_SESSION['success']); ?></span>
        <button class="toast-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    </div>
    <?php unset($_SESSION['success']); endif; ?>
    
    <?php if(isset($_SESSION['error'])): ?>
    <div class="toast toast-error" id="errorToast">
        <div class="toast-icon"><i class="fas fa-exclamation-circle"></i></div>
        <span class="toast-message"><?php echo htmlspecialchars($_SESSION['error']); ?></span>
        <button class="toast-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    </div>
    <?php unset($_SESSION['error']); endif; ?>
</div>

<script>
// Sidebar toggle for mobile
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
}

// Dropdown toggle
function toggleDropdown(id) {
    const menu = document.getElementById(id);
    // Close all other dropdowns first
    document.querySelectorAll('.dropdown-menu.show').forEach(el => {
        if(el.id !== id) el.classList.remove('show');
    });
    menu.classList.toggle('show');
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if(!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu.show').forEach(el => {
            el.classList.remove('show');
        });
    }
});

// Auto-dismiss toasts after 5 seconds
document.querySelectorAll('.toast').forEach(toast => {
    setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
});

// Inject favicon if not already present
if (!document.querySelector('link[rel="icon"]')) {
    var fav = document.createElement('link');
    fav.rel = 'icon'; fav.type = 'image/png'; fav.href = 'assets/logo.png';
    document.head.appendChild(fav);
}
</script>