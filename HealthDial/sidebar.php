<?php if(!isset($conn)) require_once 'connection.inc.php'; ?>
<?php
if(!isset($_SESSION)) session_start();

function canAccess($key){
    if(isset($_SESSION['admin_role']) && $_SESSION['admin_role'] == 'admin'){
        return true;
    }
    if(isset($_SESSION['permissions'])){
        return in_array($key, $_SESSION['permissions']);
    }
    return false;
}

$currentPage = basename($_SERVER['PHP_SELF']);
function isActive($page) {
    global $currentPage;
    if(is_array($page)) {
        return in_array($currentPage, $page) ? 'active' : '';
    }
    return ($currentPage === $page) ? 'active' : '';
}

// Get admin initials for avatar
$adminInitials = '';
if(isset($_SESSION['admin_name'])) {
    $parts = explode(' ', $_SESSION['admin_name']);
    $adminInitials = strtoupper(substr($parts[0], 0, 1));
    if(count($parts) > 1) $adminInitials .= strtoupper(substr(end($parts), 0, 1));
}

// Get unread notification count
$unreadCount = 0;
$notifResult = $conn->query("SELECT COUNT(*) as cnt FROM admin_notifications WHERE status='unread'");
if($notifResult && $row = $notifResult->fetch_assoc()) {
    $unreadCount = (int)$row['cnt'];
}

// Get pending reviews count
$pendingReviews = 0;
$prResult = $conn->query("SELECT COUNT(*) as cnt FROM reviews WHERE status = 0");
if($prResult && $row = $prResult->fetch_assoc()) {
    $pendingReviews = (int)$row['cnt'];
}
?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="Dashboard.php" class="sidebar-logo">
            <div class="sidebar-logo-icon" style="background:none;box-shadow:none;">
                <img src="assets/logo.png" alt="HealthDial" style="width:36px;height:36px;object-fit:contain;">
            </div>
            <div class="sidebar-logo-text">
                <h1>HealthDial</h1>
                <p>Admin Panel</p>
            </div>
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <div class="sidebar-section-label">Main</div>
        
        <?php if(canAccess('dashboard')): ?>
        <a href="Dashboard.php" class="sidebar-link <?php echo isActive('Dashboard.php'); ?>">
            <i class="fas fa-th-large"></i>
            Dashboard
        </a>
        <?php endif; ?>

        <?php if(canAccess('listings')): ?>
        <a href="Listing-Management.php" class="sidebar-link <?php echo isActive(['Listing-Management.php','add-listing.php','update-listing.php','view-listing.php','bulk-upload.php']); ?>">
            <i class="fas fa-hospital"></i>
            Listings
        </a>
        <?php endif; ?>

        <?php if(canAccess('listings')): ?>
        <a href="ListingVerification.php" class="sidebar-link <?php echo isActive('ListingVerification.php'); ?>">
            <i class="fas fa-clipboard-check"></i>
            Verification
            <?php 
            $pendingListings = (int)$conn->query("SELECT COUNT(*) as c FROM listings WHERE status='pending'")->fetch_assoc()['c'];
            if($pendingListings > 0): ?>
            <span class="sidebar-link-badge"><?php echo $pendingListings; ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if(canAccess('categories')): ?>
        <a href="Categories.php" class="sidebar-link <?php echo isActive('Categories.php'); ?>">
            <i class="fas fa-layer-group"></i>
            Categories
        </a>
        <?php endif; ?>
        
        <div class="sidebar-section-label">Management</div>

        <a href="SupportTickets.php" class="sidebar-link <?php echo isActive('SupportTickets.php'); ?>">
            <i class="fas fa-headset"></i>
            Support Tickets
            <?php 
            $openTickets = (int)$conn->query("SELECT COUNT(*) as c FROM support_tickets WHERE status='open'")->fetch_assoc()['c'];
            if($openTickets > 0): ?>
            <span class="sidebar-link-badge"><?php echo $openTickets; ?></span>
            <?php endif; ?>
        </a>

        <?php if(canAccess('users')): ?>
        <a href="Users.php" class="sidebar-link <?php echo isActive('Users.php'); ?>">
            <i class="fas fa-users"></i>
            Users
        </a>
        <?php endif; ?>

        <?php if(canAccess('reviews')): ?>
        <a href="Reviews.php" class="sidebar-link <?php echo isActive('Reviews.php'); ?>">
            <i class="fas fa-star"></i>
            Reviews
            <?php if($pendingReviews > 0): ?>
            <span class="sidebar-link-badge"><?php echo $pendingReviews; ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if(canAccess('notification')): ?>
        <a href="Notification.php" class="sidebar-link <?php echo isActive('Notification.php'); ?>">
            <i class="fas fa-bell"></i>
            Notifications
        </a>
        <?php endif; ?>

        <?php if(canAccess('documents')): ?>
        <a href="Documents.php" class="sidebar-link <?php echo isActive('Documents.php'); ?>">
            <i class="fas fa-file-medical"></i>
            Documents
        </a>
        <?php endif; ?>

        <?php if(canAccess('news')): ?>
        <a href="News.php" class="sidebar-link <?php echo isActive('News.php'); ?>">
            <i class="fas fa-newspaper"></i>
            News
        </a>
        <?php endif; ?>

        <a href="SponsoredListings.php" class="sidebar-link <?php echo isActive('SponsoredListings.php'); ?>">
            <i class="fas fa-bolt"></i>
            Sponsored Listings
        </a>

        <a href="Banners.php" class="sidebar-link <?php echo isActive('Banners.php'); ?>">
            <i class="fas fa-images"></i>
            Banners
        </a>

        <a href="WebsiteBanners.php" class="sidebar-link <?php echo isActive('WebsiteBanners.php'); ?>">
            <i class="fas fa-globe"></i>
            Website Banners
        </a>

        <?php /* Enquiries hidden from sidebar
        if(canAccess('enquiry')): ?>
        <a href="enquiry.php" class="sidebar-link <?php echo isActive('enquiry.php'); ?>">
            <i class="fas fa-envelope-open-text"></i>
            Enquiries
        </a>
        <?php endif; */ ?>

        <a href="Medications.php" class="sidebar-link <?php echo isActive('Medications.php'); ?>">
            <i class="fas fa-pills"></i>
            Medications
        </a>

        <div class="sidebar-section-label">Insights</div>

        <a href="Analytics.php" class="sidebar-link <?php echo isActive('Analytics.php'); ?>">
            <i class="fas fa-chart-bar"></i>
            Analytics
        </a>

        <a href="PushLogs.php" class="sidebar-link <?php echo isActive('PushLogs.php'); ?>">
            <i class="fas fa-broadcast-tower"></i>
            Push Logs
        </a>

        <a href="UserEngagement.php" class="sidebar-link <?php echo isActive('UserEngagement.php'); ?>">
            <i class="fas fa-chart-line"></i>
            Engagement
        </a>

        <a href="GeoMap.php" class="sidebar-link <?php echo isActive('GeoMap.php'); ?>">
            <i class="fas fa-map-marked-alt"></i>
            Geo Map
        </a>

        <div class="sidebar-section-label">System</div>

        <?php if(canAccess('staff')): ?>
        <a href="staff.php" class="sidebar-link <?php echo isActive('staff.php'); ?>">
            <i class="fas fa-user-tie"></i>
            Staff
        </a>
        <?php endif; ?>

        <?php if($_SESSION['admin_role'] == 'admin'): ?>
        <a href="AdminUsers.php" class="sidebar-link <?php echo isActive('AdminUsers.php'); ?>">
            <i class="fas fa-user-shield"></i>
            Admin Users
        </a>
        <?php endif; ?>

        <?php if($_SESSION['admin_role'] == 'admin'): ?>
        <a href="ActivityLog.php" class="sidebar-link <?php echo isActive('ActivityLog.php'); ?>">
            <i class="fas fa-history"></i>
            Activity Log
        </a>
        <a href="SystemHealth.php" class="sidebar-link <?php echo isActive('SystemHealth.php'); ?>">
            <i class="fas fa-server"></i>
            System Health
        </a>
        <?php endif; ?>

        <?php if(canAccess('settings')): ?>
        <a href="Settings.php" class="sidebar-link <?php echo isActive('Settings.php'); ?>">
            <i class="fas fa-cog"></i>
            Settings
        </a>
        <?php endif; ?>

        <a href="mob.php" class="sidebar-link <?php echo isActive('mob.php'); ?>">
            <i class="fas fa-mobile-alt"></i>
            Mobile Config
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar"><?php echo $adminInitials; ?></div>
            <div class="sidebar-user-info">
                <p class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></p>
                <p class="sidebar-user-role"><?php echo ucfirst($_SESSION['admin_role'] ?? 'admin'); ?></p>
            </div>
        </div>
        <a href="logout.php" class="sidebar-logout" style="margin-top: 12px;">
            <i class="fas fa-sign-out-alt"></i>
            Logout
        </a>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>