<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../utils/LoginLogger.php';
require_once __DIR__ . '/../../config/database.php';
require_once '../../utils/RateLimiter.php';

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

// Initialize rate limiter
$rateLimiter = new RateLimiter();
$ipAddress = $_SERVER['REMOTE_ADDR'];

// Check rate limit (5 attempts per minute)
if (!$rateLimiter->checkLimit($ipAddress, 5, 60)) {
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'message' => 'Too many login attempts. Please try again later.',
        'retry_after' => $rateLimiter->getRetryAfter($ipAddress)
    ]);
    exit();
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

    if (!isset($data['email']) || !isset($data['password'])) {
        throw new Exception('Email and password are required');
    }

    $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
    if (!$email) {
        throw new Exception('Invalid email format');
    }

    // Initialize database
    $pdo = new PDO(
        "mysql:host=localhost;dbname=ias_auth",
        "root",
        "",
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );

    // Check if account is locked
    $stmt = $pdo->prepare("
        SELECT is_locked, locked_until 
        FROM users 
        WHERE email = :email
    ");
    $stmt->execute([':email' => $email]);
    $lockInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($lockInfo && $lockInfo['is_locked']) {
        if ($lockInfo['locked_until'] > date('Y-m-d H:i:s')) {
            echo json_encode([
                'success' => false, 
                'message' => 'Account is locked. Please try again later.'
            ]);
            exit;
        } else {
            // Unlock account if lock period has expired
            $stmt = $pdo->prepare("
                UPDATE users 
                SET is_locked = 0, locked_until = NULL 
                WHERE email = :email
            ");
            $stmt->execute([':email' => $email]);
        }
    }

    // Verify credentials
    $stmt = $pdo->prepare("
        SELECT id, password, is_locked 
        FROM users 
        WHERE email = :email
    ");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }

    if (password_verify($data['password'], $user['password'])) {
        echo json_encode(['success' => true]);
    } else {
        // Log failed attempt and check if account should be locked
        $logger = new LoginLogger();
        $recentAttempts = $logger->getRecentAttempts($email, 30); // Get attempts in last 30 minutes
        
        $failedAttempts = array_filter($recentAttempts, function($attempt) {
            return !$attempt['is_successful'];
        });

        if (count($failedAttempts) >= 5) {
            // Lock account for 30 minutes
            $stmt = $pdo->prepare("
                UPDATE users 
                SET is_locked = 1, 
                    locked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE) 
                WHERE email = :email
            ");
            $stmt->execute([':email' => $email]);
            
            echo json_encode([
                'success' => false, 
                'message' => 'Account locked due to too many failed attempts. Please try again in 30 minutes.'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    error_log("Login error: " . $e->getMessage());
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 