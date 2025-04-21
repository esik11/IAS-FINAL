<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['name']) || !isset($data['email']) || !isset($data['password']) || 
        !isset($data['firebase_uid']) || !isset($data['backup_codes'])) {
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

    // Initialize database
    $database = new Database();
    $db = $database->getConnection();
    
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

        // Commit transaction
        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'User registered successfully',
            'user_id' => $userId
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 