<?php
// Enable all error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1); // Enable error display during development
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../php-otp-errors.log');

// Add CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Debug logging function
function debug_log($message, $data = null) {
    $log_path = __DIR__ . '/../../php-otp-errors.log';
    $log_message = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($data) {
        $log_message .= ' ' . json_encode($data);
    }
    file_put_contents($log_path, $log_message . PHP_EOL, FILE_APPEND);
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Create a log entry for debugging
debug_log("OTP API endpoint called - HTTP METHOD: " . $_SERVER['REQUEST_METHOD']);

// Include the EmailController
require_once '../controllers/EmailController.php';

// We need to use the App\Controllers namespace
use App\Controllers\EmailController;

try {
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get POST data
    $inputData = file_get_contents('php://input');
    debug_log("Raw input", $inputData);
    
    $data = json_decode($inputData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    debug_log("Decoded data", $data);
    
    if (empty($data['email']) || empty($data['otp'])) {
        throw new Exception('Email and OTP are required');
    }

    $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
    if (!$email) {
        throw new Exception('Invalid email format: ' . $data['email']);
    }

    $otp = $data['otp'];
    if (!preg_match('/^\d{6}$/', $otp)) {
        throw new Exception('Invalid OTP format: ' . $otp);
    }

    // Initialize EmailController and send OTP
    debug_log("Creating EmailController instance");
    $emailController = new EmailController();
    
    debug_log("Sending OTP to $email");
    try {
        $result = $emailController->sendOTP($email, $otp);
        
        if ($result) {
            debug_log("OTP sent successfully to $email");
            echo json_encode([
                'success' => true,
                'message' => 'OTP sent successfully'
            ]);
        } else {
            throw new Exception('Failed to send OTP');
        }
    } catch (Exception $e) {
        debug_log("Email sending error", $e->getMessage());
        throw new Exception('Failed to send OTP: ' . $e->getMessage());
    }

} catch (Exception $e) {
    debug_log("OTP API error", $e->getMessage());
    http_response_code(500); // Set appropriate status code
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 