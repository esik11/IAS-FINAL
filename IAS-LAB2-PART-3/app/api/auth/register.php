<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../php-error.log');

require_once '../../config/database.php';

function debugLog($message, $data = null) {
    error_log(sprintf(
        "[Debug] %s %s",
        $message,
        $data ? json_encode($data) : ''
    ));
}

try {
    // Log incoming request
    debugLog('Received registration request', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
    ]);

    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get POST data
    $input = file_get_contents('php://input');
    debugLog('Raw input received', $input);
    
    $data = json_decode($input, true);
    debugLog('Parsed input data', $data);
    
    if (!isset($data['name']) || !isset($data['email']) || !isset($data['password']) || 
        !isset($data['firebase_uid']) || !isset($data['backup_codes'])) {
        debugLog('Missing required fields', [
            'has_name' => isset($data['name']),
            'has_email' => isset($data['email']),
            'has_password' => isset($data['password']),
            'has_firebase_uid' => isset($data['firebase_uid']),
            'has_backup_codes' => isset($data['backup_codes'])
        ]);
        throw new Exception('Missing required fields');
    }

    // Sanitize inputs
    $name = filter_var($data['name'], FILTER_SANITIZE_STRING);
    $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
    $password = password_hash($data['password'], PASSWORD_DEFAULT);
    $firebase_uid = filter_var($data['firebase_uid'], FILTER_SANITIZE_STRING);
    $backup_codes = $data['backup_codes'];

    if (!$email) {
        throw new Exception('Invalid email format');
    }

    debugLog('Sanitized data', [
        'name' => $name,
        'email' => $email,
        'firebase_uid' => $firebase_uid,
        'backup_codes_count' => count($backup_codes)
    ]);

    // Initialize database
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        debugLog('Database connection failed');
        throw new Exception('Database connection failed');
    }
    
    debugLog('Database connection established');
    
    // Begin transaction
    $db->beginTransaction();

    try {
        // Insert user
        $stmt = $db->prepare("
            INSERT INTO users (name, email, password, firebase_uid)
            VALUES (:name, :email, :password, :firebase_uid)
        ");

        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => $password,
            ':firebase_uid' => $firebase_uid
        ]);

        $userId = $db->lastInsertId();
        debugLog('User inserted', ['user_id' => $userId]);

        // Insert backup codes
        $stmt = $db->prepare("
            INSERT INTO backup_codes (user_id, code)
            VALUES (:user_id, :code)
        ");

        foreach ($backup_codes as $code) {
            $stmt->execute([
                ':user_id' => $userId,
                ':code' => $code
            ]);
        }
        debugLog('Backup codes inserted');

        // Commit transaction
        $db->commit();
        debugLog('Transaction committed');

        echo json_encode([
            'success' => true,
            'message' => 'User registered successfully',
            'user_id' => $userId
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        debugLog('Database error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }

} catch (Exception $e) {
    debugLog('Registration error', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 