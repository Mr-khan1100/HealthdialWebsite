<?php
require_once 'connection.inc.php';
requireAdmin();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        
        // Update password only if provided
        if (!empty($_POST['password'])) {
            if($_POST['password'] !== $_POST['confirm_password']) {
                $_SESSION['error'] = "Passwords do not match!";
                header("Location: Settings.php");
                exit();
            }
            // Use password_hash — login now supports both formats
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admin_users SET name=?, email=?, password=? WHERE id=?");
            $stmt->bind_param("sssi", $name, $email, $password, $_SESSION['admin_id']);
        } else {
            $stmt = $conn->prepare("UPDATE admin_users SET name=?, email=? WHERE id=?");
            $stmt->bind_param("ssi", $name, $email, $_SESSION['admin_id']);
        }
        
        if ($stmt->execute()) {
            $_SESSION['admin_name'] = $name;
            $_SESSION['admin_email'] = $email;
            
            // Log activity
            $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, action, details, ip_address) VALUES (?, 'update_profile', 'Updated admin profile', ?)");
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $logStmt->bind_param("is", $_SESSION['admin_id'], $ip);
            $logStmt->execute();
            
            $_SESSION['success'] = "Profile updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating profile: " . $conn->error;
        }
        header("Location: Settings.php");
        exit();
    }
    
    if (isset($_POST['update_settings'])) {
        $settings = [
            'site_name' => trim($_POST['site_name'] ?? 'HealthDial'),
            'site_email' => trim($_POST['site_email'] ?? ''),
            'contact_number' => trim($_POST['contact_number'] ?? ''),
            'default_status' => $_POST['default_status'] ?? 'pending',
            'auto_approve' => isset($_POST['auto_approve']) ? '1' : '0',
            'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
            'featured_category_id' => trim($_POST['featured_category_id'] ?? ''),
            'android_store_url' => trim($_POST['android_store_url'] ?? ''),
            'ios_store_url' => trim($_POST['ios_store_url'] ?? ''),
        ];
        
        foreach($settings as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param("sss", $key, $value, $value);
            $stmt->execute();
            $stmt->close();
        }
        
        // Log activity
        $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, action, details, ip_address) VALUES (?, 'update_settings', 'Updated system settings', ?)");
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $logStmt->bind_param("is", $_SESSION['admin_id'], $ip);
        $logStmt->execute();
        
        $_SESSION['success'] = "Settings saved successfully!";
        header("Location: Settings.php");
        exit();
    }
    
    // Handle About Us settings update
    if (isset($_POST['update_about'])) {
        $aboutFields = ['about_tagline','about_mission','about_vision','about_stats_years','about_stats_users',
            'about_email','about_phone','about_website','about_address',
            'about_facebook','about_twitter','about_instagram','about_linkedin'];
        foreach($aboutFields as $key) {
            $value = trim($_POST[$key] ?? '');
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param("sss", $key, $value, $value);
            $stmt->execute();
            $stmt->close();
        }
        $_SESSION['success'] = "About Us settings saved!";
        header("Location: Settings.php");
        exit();
    }
    
    // Handle Listing Popup settings update
    if (isset($_POST['update_popup'])) {
        $popupFields = ['popup_title','popup_message','popup_image','popup_button_text','popup_redirect_url'];
        foreach($popupFields as $key) {
            $value = trim($_POST[$key] ?? '');
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param("sss", $key, $value, $value);
            $stmt->execute();
            $stmt->close();
        }
        $_SESSION['success'] = "Listing popup settings saved!";
        header("Location: Settings.php");
        exit();
    }
    
    // NOTE: Payment-gateway settings (Cashfree + PayU + active-gateway switch)
    // now live on their own admin page: PaymentGateway.php
}

// Get current admin details
$stmt = $conn->prepare("SELECT * FROM admin_users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

// Load saved settings
$savedSettings = [];
$settingsRes = $conn->query("SELECT setting_key, setting_value FROM settings");
if($settingsRes) {
    while($s = $settingsRes->fetch_assoc()) {
        $savedSettings[$s['setting_key']] = $s['setting_value'];
    }
}

function getSetting($key, $default = '') {
    global $savedSettings;
    return $savedSettings[$key] ?? $default;
}

// Get categories for featured dropdown
$categoriesList = [];
$catListRes = $conn->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY name");
if($catListRes) while($c = $catListRes->fetch_assoc()) $categoriesList[] = $c;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — HealthDial Admin</title>
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
                    <h1 class="page-title">Settings</h1>
                    <p class="page-subtitle">Manage your profile and system settings</p>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <!-- Profile Settings -->
                    <div class="card fade-in">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title"><i class="fas fa-user-circle" style="margin-right:8px;color:var(--primary);"></i>Profile Settings</h3>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="update_profile" value="1">
                                
                                <div class="form-group">
                                    <label class="form-label">Name *</label>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($admin['name']); ?>" required class="form-input">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Email *</label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required class="form-input">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">New Password <span style="font-weight:400;text-transform:none;">(leave blank to keep current)</span></label>
                                    <input type="password" name="password" class="form-input" placeholder="••••••••" minlength="6">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Confirm Password</label>
                                    <input type="password" name="confirm_password" class="form-input" placeholder="••••••••">
                                </div>
                                
                                <button type="submit" class="btn btn-primary" style="width:100%;">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- System Settings -->
                    <div class="card fade-in fade-in-delay-1">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title"><i class="fas fa-cog" style="margin-right:8px;color:var(--accent);"></i>System Settings</h3>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="update_settings" value="1">
                                
                                <div class="form-group">
                                    <label class="form-label">Site Name</label>
                                    <input type="text" name="site_name" value="<?php echo htmlspecialchars(getSetting('site_name', 'HealthDial')); ?>" class="form-input">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Site Email</label>
                                    <input type="email" name="site_email" value="<?php echo htmlspecialchars(getSetting('site_email', 'info@healthdial.com')); ?>" class="form-input">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Contact Number</label>
                                    <input type="tel" name="contact_number" value="<?php echo htmlspecialchars(getSetting('contact_number')); ?>" class="form-input">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Default Listing Status</label>
                                    <select name="default_status" class="form-select">
                                        <option value="pending" <?php echo getSetting('default_status','pending')==='pending'?'selected':''; ?>>Pending</option>
                                        <option value="approved" <?php echo getSetting('default_status')==='approved'?'selected':''; ?>>Approved</option>
                                    </select>
                                </div>
                                
                                <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:18px;">
                                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
                                        <input type="checkbox" name="auto_approve" <?php echo getSetting('auto_approve')==='1'?'checked':''; ?> style="accent-color:var(--primary);">
                                        Auto-approve new listings
                                    </label>
                                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
                                        <input type="checkbox" name="email_notifications" <?php echo getSetting('email_notifications','1')==='1'?'checked':''; ?> style="accent-color:var(--primary);">
                                        Enable email notifications
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Featured Category (Home Screen)</label>
                                    <select name="featured_category_id" class="form-select">
                                        <option value="">-- Use is_featured flag --</option>
                                        <?php foreach($categoriesList as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo getSetting('featured_category_id')===$cat['id']?'selected':''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small style="color:var(--text-muted);font-size:11px;">Select which category appears as "Featured" on the home screen</small>
                                </div>

                                <div style="border-top:1px solid rgba(0,0,0,0.06);padding-top:16px;margin-top:8px;">
                                    <label class="form-label" style="margin-bottom:12px;font-size:13px;color:var(--text-muted);"><i class="fas fa-store" style="margin-right:4px;"></i> App Store URLs</label>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fab fa-google-play" style="margin-right:4px;color:#3DDC84;"></i> Play Store URL</label>
                                        <input type="text" name="android_store_url" value="<?php echo htmlspecialchars(getSetting('android_store_url', 'market://details?id=com.healthdial.mobile')); ?>" class="form-input" placeholder="market://details?id=com.healthdial.mobile">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label"><i class="fab fa-apple" style="margin-right:4px;color:#333;"></i> App Store URL</label>
                                        <input type="text" name="ios_store_url" value="<?php echo htmlspecialchars(getSetting('ios_store_url', 'https://apps.apple.com/app/id123456789')); ?>" class="form-input" placeholder="https://apps.apple.com/app/id...">
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-secondary" style="width:100%;background:var(--text-primary);color:#fff;">
                                    <i class="fas fa-save"></i> Save Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- About Us Settings -->
                <div class="card fade-in fade-in-delay-2" style="margin-top:20px;">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title"><i class="fas fa-info-circle" style="margin-right:8px;color:var(--accent);"></i>About Us Settings</h3>
                            <p style="font-size:12px;color:var(--text-muted);margin:0;">Editable content shown on the app's About screen</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="update_about" value="1">
                            
                            <div class="form-group">
                                <label class="form-label">Tagline</label>
                                <input type="text" name="about_tagline" value="<?php echo htmlspecialchars(getSetting('about_tagline', 'Your Trusted Healthcare Discovery Platform')); ?>" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Mission</label>
                                <textarea name="about_mission" rows="3" class="form-input"><?php echo htmlspecialchars(getSetting('about_mission', 'HealthDial aims to improve healthcare accessibility by helping users discover nearby hospitals, clinics, and medical professionals.')); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Vision</label>
                                <textarea name="about_vision" rows="3" class="form-input"><?php echo htmlspecialchars(getSetting('about_vision', 'To become a reliable digital healthcare directory empowering users to make informed healthcare decisions.')); ?></textarea>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <div class="form-group">
                                    <label class="form-label">Product Years</label>
                                    <input type="text" name="about_stats_years" value="<?php echo htmlspecialchars(getSetting('about_stats_years', '50+')); ?>" class="form-input">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Users Count</label>
                                    <input type="text" name="about_stats_users" value="<?php echo htmlspecialchars(getSetting('about_stats_users', '1000+')); ?>" class="form-input">
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="about_email" value="<?php echo htmlspecialchars(getSetting('about_email', 'contact@healthdial.com')); ?>" class="form-input">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" name="about_phone" value="<?php echo htmlspecialchars(getSetting('about_phone')); ?>" class="form-input">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="width:100%;">
                                <i class="fas fa-save"></i> Save About Us
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Listing Popup Settings -->
                <div class="card fade-in fade-in-delay-3" style="margin-top:20px;">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title"><i class="fas fa-window-restore" style="margin-right:8px;color:var(--accent);"></i>Add Listing Success Popup</h3>
                            <p style="font-size:12px;color:var(--text-muted);margin:0;">Customize the success popup shown after a user submits a listing</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="update_popup" value="1">
                            
                            <div class="form-group">
                                <label class="form-label">Popup Title</label>
                                <input type="text" name="popup_title" value="<?php echo htmlspecialchars(getSetting('popup_title', 'Listing Submitted!')); ?>" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Popup Message</label>
                                <textarea name="popup_message" rows="3" class="form-input"><?php echo htmlspecialchars(getSetting('popup_message', 'Your listing has been submitted for review. It will be visible once approved by our team.')); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Popup Image URL (optional)</label>
                                <input type="url" name="popup_image" value="<?php echo htmlspecialchars(getSetting('popup_image')); ?>" class="form-input" placeholder="https://...">
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <div class="form-group">
                                    <label class="form-label">Button Text</label>
                                    <input type="text" name="popup_button_text" value="<?php echo htmlspecialchars(getSetting('popup_button_text', 'OK')); ?>" class="form-input">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Button URL (optional)</label>
                                    <input type="url" name="popup_redirect_url" value="<?php echo htmlspecialchars(getSetting('popup_redirect_url')); ?>" class="form-input" placeholder="https://...">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="width:100%;">
                                <i class="fas fa-save"></i> Save Popup Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Payment Gateway Settings -->
                <div class="card fade-in fade-in-delay-2" style="margin-top:20px;">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title"><i class="fas fa-credit-card" style="margin-right:8px;color:#22c55e;"></i>Payment Gateway</h3>
                            <p style="font-size:12px;color:var(--text-muted);margin:0;">Choose the active gateway (Cashfree / PayU) and manage credentials on the dedicated page.</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <a href="PaymentGateway.php" class="btn btn-primary" style="width:100%;background:#22c55e;">
                            <i class="fas fa-credit-card"></i> Open Payment Gateway Settings
                        </a>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="card fade-in fade-in-delay-2" style="margin-top:20px;border:1px solid rgba(239,68,68,0.2);">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title" style="color:var(--status-danger);"><i class="fas fa-exclamation-triangle" style="margin-right:8px;"></i>Data Export</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">Download data exports as CSV files. All exports are logged in the Activity Log.</p>
                        <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:10px;">
                            <a href="export.php?type=users" class="btn btn-secondary btn-sm" style="justify-content:center;">
                                <i class="fas fa-users"></i> Users
                            </a>
                            <a href="export.php?type=listings" class="btn btn-secondary btn-sm" style="justify-content:center;">
                                <i class="fas fa-hospital"></i> Listings
                            </a>
                            <a href="export.php?type=reviews" class="btn btn-secondary btn-sm" style="justify-content:center;">
                                <i class="fas fa-star"></i> Reviews
                            </a>
                            <a href="export.php?type=medications" class="btn btn-secondary btn-sm" style="justify-content:center;">
                                <i class="fas fa-pills"></i> Medications
                            </a>
                            <a href="export.php?type=notifications" class="btn btn-secondary btn-sm" style="justify-content:center;">
                                <i class="fas fa-bell"></i> Notifications
                            </a>
                            <!-- Enquiries export hidden -->

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>