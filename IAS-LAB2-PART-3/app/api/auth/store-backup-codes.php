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
    
    if (!isset($data['user_id']) || !isset($data['codes']) || !is_array($data['codes'])) {
        throw new Exception('Missing required fields');
    }

    $userId = filter_var($data['user_id'], FILTER_VALIDATE_INT);
    $codes = $data['codes'];

    if (!$userId) {
        throw new Exception('Invalid user ID');
    }

    // Initialize database
    $database = new Database();
    $db = $database->getConnection();
    
    // Begin transaction
    $db->beginTransaction();

    try {
        // Prepare statement
        $stmt = $db->prepare("
            INSERT INTO backup_codes (user_id, code)
            VALUES (:user_id, :code)
        ");

        // Insert each backup code
        foreach ($codes as $code) {
            $stmt->execute([
                ':user_id' => $userId,
                ':code' => $code
            ]);
        }

        // Commit transaction
        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Backup codes stored successfully'
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