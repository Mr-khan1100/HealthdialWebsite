<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

trigger_error("This is a test error", E_USER_WARNING);
echo "OK";
?>