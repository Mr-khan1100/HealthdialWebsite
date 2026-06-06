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
?>