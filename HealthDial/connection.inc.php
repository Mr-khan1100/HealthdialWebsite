<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Database Configuration
|--------------------------------------------------------------------------
*/

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'u961861187_HealthDialNew');

/*
|--------------------------------------------------------------------------
| Create Database Connection
|--------------------------------------------------------------------------
*/

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Set charset (IMPORTANT for security & UTF-8 support)
$conn->set_charset("utf8mb4");

// Set MySQL session timezone to match PHP
$conn->query("SET time_zone = '+05:30'");

/*
|--------------------------------------------------------------------------
| Set Default Timezone
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Kolkata');

/*
|--------------------------------------------------------------------------
| Admin Authentication Functions
|--------------------------------------------------------------------------
*/

// Check if admin is logged in
function isAdminLoggedIn()
{
    return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_role']);
}

// Redirect if not logged in
function requireLogin()
{
    if (!isAdminLoggedIn()) {
        header("Location: Dashboard.php");
        exit();
    }
}

// Check specific role
function requireRole($role)
{
    if (!isAdminLoggedIn() || $_SESSION['admin_role'] !== $role) {
        header("Location: Dashboard.php");
        exit();
    }
}

// True only for the full 'admin' role (not staff).
function isAdmin()
{
    return isAdminLoggedIn() && (($_SESSION['admin_role'] ?? '') === 'admin');
}

// Gate an entire page to admins only. Staff are bounced to the Dashboard.
// Use on admin-only sections (Users, Payment Gateway, Settings, Staff, etc.).
function requireAdmin()
{
    requireLogin();
    if (!isAdmin()) {
        $_SESSION['error'] = 'You do not have permission to access that section.';
        header("Location: Dashboard.php");
        exit();
    }
}

/*
|--------------------------------------------------------------------------
| Per-staff section permissions
|--------------------------------------------------------------------------
| Section keys are defined once in admin_sections.php. A staff member's
| granted keys are loaded into $_SESSION['permissions'] at login (index.php).
*/
require_once __DIR__ . '/admin_sections.php';

// Can the logged-in admin/staff access a given sidebar section key?
// Full admins can access everything; staff are limited to their granted keys.
function canAccess($key)
{
    if (($_SESSION['admin_role'] ?? '') === 'admin') {
        return true;
    }
    $perms = $_SESSION['permissions'] ?? [];
    if (!is_array($perms)) {
        $perms = explode(',', (string)$perms);
    }
    $perms = array_map('trim', $perms);
    return in_array('all', $perms, true) || in_array($key, $perms, true);
}

// Gate a staff-assignable page to admins + staff who hold the section key.
// Others are bounced to the Dashboard (which is always reachable, so no loop).
function requireAccess($key)
{
    requireLogin();
    if (!canAccess($key)) {
        $_SESSION['error'] = 'You do not have permission to access that section.';
        header("Location: Dashboard.php");
        exit();
    }
}
?>