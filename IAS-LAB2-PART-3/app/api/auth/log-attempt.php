<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../utils/LoginLogger.php';

// Get JSON input
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['email']) || !isset($data['success'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$logger = new LoginLogger();
$result = $logger->logAttempt($data['email'], $data['success']);

if ($result) {
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to log attempt']);
} 