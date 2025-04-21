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
    
    if (!isset($data['email']) || !isset($data['code'])) {
        throw new Exception('Email and backup code are required');
    }

    $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
    $code = $data['code'];

    if (!$email) {
        throw new Exception('Invalid email format');
    }

    // Initialize database
    $database = new Database();
    $db = $database->getConnection();
    
    // Verify backup code
    $stmt = $db->prepare("
        SELECT bc.id, bc.is_used 
        FROM backup_codes bc
        JOIN users u ON bc.user_id = u.id
        WHERE u.email = :email AND bc.code = :code
    ");

    $stmt->execute([
        ':email' => $email,
        ':code' => $code
    ]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        throw new Exception('Invalid backup code');
    }

    if ($result['is_used']) {
        throw new Exception('Backup code has already been used');
    }

    // Mark backup code as used
    $stmt = $db->prepare("
        UPDATE backup_codes 
        SET is_used = 1, used_at = CURRENT_TIMESTAMP 
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $result['id']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Backup code verified successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 