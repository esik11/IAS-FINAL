<?php
// Method 1: Using random_bytes (recommended)
$secretKey = bin2hex(random_bytes(32)); // Generates a 64-character hex string

// Or Method 2: Using openssl
// $secretKey = bin2hex(openssl_random_pseudo_bytes(32));

define('JWT_SECRET_KEY', $secretKey);
define('JWT_ACCESS_TOKEN_EXPIRY', 60); // 1 minute (changed from 900/15 minutes)
define('JWT_REFRESH_TOKEN_EXPIRY', 180); // 3 minutes (changed from 86400/24 hours)

// Debug function
function debugLog($message, $data = null) {
    error_log("[JWT Debug] " . $message . ($data ? ": " . print_r($data, true) : ""));
}

// Debug initial configuration
debugLog("JWT Secret Key (first 10 chars)", substr(JWT_SECRET_KEY, 0, 10) . '...'); 