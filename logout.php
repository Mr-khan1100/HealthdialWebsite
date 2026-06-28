<?php
/**
 * Log the website user out and return them to a safe local page.
 */
require_once 'includes/user_auth.php';

hd_logout();

$return = hd_safe_return_url($_GET['return'] ?? 'index.php');
header('Location: ' . $return);
exit;
