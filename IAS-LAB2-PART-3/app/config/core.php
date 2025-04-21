<?php
// Show error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set default timezone
date_default_timezone_set('Asia/Manila');

// Variables used for JWT
define('SECRET_KEY', 'Your-Secret-Key-Here');
define('ALGORITHM', 'HS256');

// Set session timeout (in seconds)
define('SESSION_TIMEOUT', 1800); // 30 minutes
?> 