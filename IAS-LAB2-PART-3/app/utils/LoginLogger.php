<?php

class LoginLogger {
    private $db;

    public function __construct() {
        $this->db = new PDO(
            "mysql:host=localhost;dbname=ias_auth",
            "root",
            "",
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );
    }

    public function logAttempt($email, $isSuccessful = false) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO login_attempts (email, ip_address, is_successful)
                VALUES (:email, :ip_address, :is_successful)
            ");

            $stmt->execute([
                ':email' => $email,
                ':ip_address' => $_SERVER['REMOTE_ADDR'],
                ':is_successful' => $isSuccessful
            ]);

            return true;
        } catch (PDOException $e) {
            error_log("Failed to log login attempt: " . $e->getMessage());
            return false;
        }
    }

    public function getRecentAttempts($email, $minutes = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM login_attempts 
                WHERE email = :email 
                AND attempt_time >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
                ORDER BY attempt_time DESC
            ");

            $stmt->execute([
                ':email' => $email,
                ':minutes' => $minutes
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to get recent attempts: " . $e->getMessage());
            return [];
        }
    }
} 