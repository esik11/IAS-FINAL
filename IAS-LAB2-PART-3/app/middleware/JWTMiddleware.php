<?php
require_once __DIR__ . '/../config/jwt-config.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

class JWTMiddleware {
    public static function getToken() {
        $headers = getallheaders();
        debugLog("Checking Authorization header", isset($headers['Authorization']) ? 'Present' : 'Missing');
        
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
            debugLog("Token extracted", substr($token, 0, 20) . '...');
            return $token;
        }
        
        // Check cookies if header not present
        if (isset($_COOKIE['access_token'])) {
            debugLog("Token found in cookie", substr($_COOKIE['access_token'], 0, 20) . '...');
            return $_COOKIE['access_token'];
        }
        
        debugLog("No token found");
        return null;
    }

    public static function validateToken() {
        try {
            $token = self::getToken();
            if (!$token) {
                debugLog("Token validation failed: No token provided");
                return false;
            }

            debugLog("Attempting to decode token");
            $decoded = JWT::decode($token, new Key(JWT_SECRET_KEY, 'HS256'));
            debugLog("Token decoded successfully", [
                'email' => $decoded->email,
                'expires' => date('Y-m-d H:i:s', $decoded->exp)
            ]);
            return $decoded;
        } catch (ExpiredException $e) {
            debugLog("Token expired", $e->getMessage());
            return 'expired';
        } catch (Exception $e) {
            debugLog("Token validation error", $e->getMessage());
            return false;
        }
    }

    public static function generateTokens($user_email) {
        debugLog("Generating tokens for user", $user_email);
        
        $issuedAt = time();
        $accessExpiry = $issuedAt + JWT_ACCESS_TOKEN_EXPIRY;
        $refreshExpiry = $issuedAt + JWT_REFRESH_TOKEN_EXPIRY;
        
        // Access token
        $accessPayload = [
            'iss' => 'your-app-name',
            'aud' => 'your-app',
            'iat' => $issuedAt,
            'exp' => $accessExpiry,
            'email' => $user_email,
            'type' => 'access'
        ];
        
        // Refresh token
        $refreshPayload = [
            'iss' => 'your-app-name',
            'aud' => 'your-app',
            'iat' => $issuedAt,
            'exp' => $refreshExpiry,
            'email' => $user_email,
            'type' => 'refresh'
        ];

        debugLog("Token payloads created", [
            'access_expires' => date('Y-m-d H:i:s', $accessExpiry),
            'refresh_expires' => date('Y-m-d H:i:s', $refreshExpiry)
        ]);

        $tokens = [
            'access_token' => JWT::encode($accessPayload, JWT_SECRET_KEY, 'HS256'),
            'refresh_token' => JWT::encode($refreshPayload, JWT_SECRET_KEY, 'HS256'),
            'expires_in' => JWT_ACCESS_TOKEN_EXPIRY
        ];

        debugLog("Tokens generated successfully");
        return $tokens;
    }
} 