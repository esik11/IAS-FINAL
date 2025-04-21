<?php
session_start();
header('Content-Type: application/json');

// Prevent any HTML error output
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    echo json_encode([
        'authenticated' => isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true,
        'email' => $_SESSION['user_email'] ?? null,
        'timestamp' => time()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'authenticated' => false,
        'error' => 'Session check failed'
    ]);
} 