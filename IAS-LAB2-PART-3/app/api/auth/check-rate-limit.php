<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';
require_once '../../config/RateLimiter.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../php-error.log');

function debugLog($message, $data = null) {
    error_log(sprintf(
        "[Debug] %s %s",
        $message,
        $data ? json_encode($data) : ''
    ));
}

try {
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get POST data
    $input = file_get_contents('php://input');
    debugLog('Raw input received', $input);
    
    $data = json_decode($input, true);
    debugLog('Parsed input data', $data);

    if (!isset($data['email'])) {
        throw new Exception('Email is required');
    }

    $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
    if (!$email) {
        throw new Exception('Invalid email format');
    }

    // Initialize database
    $database = new Database();
    $db = $database->getConnection();

    // Initialize rate limiter
    $rateLimiter = new RateLimiter($db);

    // Check rate limit
    $rateLimiter->checkRateLimit($email);

    // If we get here, rate limit check passed
    echo json_encode([
        'success' => true,
        'message' => 'Rate limit check passed'
    ]);

} catch (Exception $e) {
    debugLog('Rate limit check error', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 