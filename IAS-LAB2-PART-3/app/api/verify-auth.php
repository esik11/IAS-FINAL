<?php
// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable HTML errors
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../php-error.log');

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Debug function
function debugLog($message, $data = null) {
    error_log(sprintf(
        "[Debug] %s %s",
        $message,
        $data ? json_encode($data) : ''
    ));
}

try {
    // Log request
    debugLog('Received request', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'input' => file_get_contents('php://input')
    ]);

    // Verify method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Parse input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    debugLog('Parsed input', $data);

    if (isset($data['email'])) {
        // Create session
        session_start();
        $_SESSION['authenticated'] = true;
        $_SESSION['user_email'] = $data['email'];
        
        // Set a simple token in session
        $_SESSION['auth_token'] = bin2hex(random_bytes(32));
        
        debugLog('Session created', [
            'email' => $data['email'],
            'session_id' => session_id()
        ]);

        // Return success
        echo json_encode([
            'success' => true,
            'message' => 'Authentication successful'
        ]);
    } else {
        throw new Exception('Email not provided');
    }
} catch (Exception $e) {
    debugLog('Error occurred', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    error_log("Auth Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 