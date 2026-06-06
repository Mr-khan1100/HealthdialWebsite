<?php
require_once 'connection.inc.php';

if(isAdminLoggedIn()){
    header("Location: Dashboard.php");
    exit();
}

if($_SERVER['REQUEST_METHOD'] == 'POST'){

    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT id, name, email, password, role, permissions FROM admin_users WHERE email=? AND is_active=1 LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows == 1){

        $admin = $result->fetch_assoc();

        // Support both plain text (legacy) and hashed passwords
        $passwordValid = false;
        if(password_get_info($admin['password'])['algo'] !== null && password_get_info($admin['password'])['algo'] !== 0) {
            // Hashed password
            $passwordValid = password_verify($password, $admin['password']);
        } else {
            // Legacy plain text — verify and upgrade to hash
            $passwordValid = ($password === $admin['password']);
            if($passwordValid) {
                // Auto-upgrade to hashed password
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $upStmt = $conn->prepare("UPDATE admin_users SET password=? WHERE id=?");
                $upStmt->bind_param("si", $hashed, $admin['id']);
                $upStmt->execute();
                $upStmt->close();
            }
        }

        if($passwordValid){
            // Update last_login
            $conn->query("UPDATE admin_users SET last_login=NOW() WHERE id={$admin['id']}");

            $_SESSION['admin_id']    = $admin['id'];
            $_SESSION['admin_name']  = $admin['name'];
            $_SESSION['admin_role']  = $admin['role'];
            $_SESSION['admin_email'] = $admin['email'];

            // Permissions logic
            if($admin['role'] == 'admin'){
                $_SESSION['permissions'] = ['all','dashboard','listings','categories','users','reviews','notification','documents','news','enquiry','staff','settings'];
            }else{
                $_SESSION['permissions'] = explode(',', $admin['permissions']);
            }

            // Log the login
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $logStmt = $conn->prepare("INSERT INTO activity_logs (admin_id, action, details, ip_address) VALUES (?, 'login', 'Admin logged in', ?)");
            $logStmt->bind_param("is", $admin['id'], $ip);
            $logStmt->execute();
            $logStmt->close();

            header("Location: Dashboard.php");
            exit();
        }else{
            $error = "Invalid password";
        }
    }else{
        $error = "Account not found";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — HealthDial Admin</title>
    <link rel="stylesheet" href="assets/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="assets/logo.png">
</head>
<body class="login-page">
    <div class="login-card fade-in">
        <div style="text-align: center; margin-bottom: 28px;">
            <img src="assets/logo.png" alt="HealthDial" style="width:56px;height:56px;object-fit:contain;margin:0 auto 16px;display:block;">
            <h2>HealthDial</h2>
            <p class="login-subtitle">Sign in to your admin panel</p>
        </div>
        
        <?php if(isset($error)): ?>
        <div style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #fca5a5; padding: 12px 16px; border-radius: var(--radius-md); margin-bottom: 20px; font-size: 13px; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div style="position: relative;">
                    <i class="fas fa-envelope" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: rgba(148,163,184,0.5); font-size: 13px;"></i>
                    <input type="email" name="email" required class="form-input" style="padding-left: 40px;" placeholder="admin@healthdial.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Password</label>
                <div style="position: relative;">
                    <i class="fas fa-lock" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: rgba(148,163,184,0.5); font-size: 13px;"></i>
                    <input type="password" name="password" id="loginPassword" required class="form-input" style="padding-left: 40px; padding-right: 40px;" placeholder="••••••••">
                    <button type="button" onclick="togglePassword()" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; color: rgba(148,163,184,0.5); cursor: pointer; font-size: 13px;">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>
            
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: #94a3b8;">
                    <input type="checkbox" name="remember" style="accent-color: var(--primary);">
                    Remember me
                </label>
            </div>
            
            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i>
                Sign In
            </button>
        </form>
        
        <div class="login-footer">
            &copy; <?php echo date('Y'); ?> HealthDial. All rights reserved.
        </div>
    </div>

    <script>
    function togglePassword() {
        const input = document.getElementById('loginPassword');
        const icon = document.getElementById('toggleIcon');
        if(input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
    </script>
</body>
</html>