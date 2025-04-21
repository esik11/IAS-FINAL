<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config/jwt-config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

function debugLog($message, $data = null) {
    error_log("[Token Refresh] " . $message . ($data ? ": " . print_r($data, true) : ""));
}

try {
    debugLog("Starting token refresh");
    $refreshToken = $_COOKIE['refresh_token'] ?? null;
    
    if (!$refreshToken) {
        throw new Exception('No refresh token provided');
    }

    debugLog("Refresh token found");

    // Verify refresh token
    try {
        $decoded = JWT::decode($refreshToken, new Key(JWT_SECRET_KEY, 'HS256'));
        debugLog("Refresh token decoded", ['email' => $decoded->email]);
        
        // Check if it's actually a refresh token
        if (!isset($decoded->type) || $decoded->type !== 'refresh') {
            throw new Exception('Invalid token type');
        }

        // Generate new access token
        $accessPayload = [
            'iat' => time(),
            'exp' => time() + JWT_ACCESS_TOKEN_EXPIRY,
            'email' => $decoded->email,
            'type' => 'access'
        ];

        $newAccessToken = JWT::encode($accessPayload, JWT_SECRET_KEY, 'HS256');
        debugLog("New access token generated");

        // Set new access token in cookie
        setcookie('access_token', $newAccessToken, [
            'expires' => time() + JWT_ACCESS_TOKEN_EXPIRY,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure' => false // Set to true in production
        ]);

        debugLog("Token refresh completed successfully");
        echo json_encode([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'expires_in' => JWT_ACCESS_TOKEN_EXPIRY
        ]);
    } catch (ExpiredException $e) {
        debugLog("Refresh token expired");
        // Refresh token expired, require re-authentication
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Refresh token expired',
            'require_login' => true
        ]);
    }
} catch (Exception $e) {
    debugLog("Error during refresh", $e->getMessage());
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 