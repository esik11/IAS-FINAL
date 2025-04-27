<?php
class RateLimiter {
    private $db;
    private $max_attempts = 3;
    private $lockout_time = 60; // 1 minute (in seconds)

    public function __construct($db) {
        $this->db = $db;
    }

    public function checkRateLimit($email) {
        try {
            // Clean up old attempts (older than lockout time)
            $this->cleanupOldAttempts($email);

            // Check if account is locked
            $stmt = $this->db->prepare("
                SELECT locked_until 
                FROM users 
                WHERE email = :email AND is_locked = 1 AND locked_until > NOW()
            ");
            $stmt->execute([':email' => $email]);
            $lockInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($lockInfo) {
                $remainingTime = strtotime($lockInfo['locked_until']) - time();
                throw new Exception("Account is locked. Try again in {$remainingTime} seconds.");
            }

            // Count recent failed attempts
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as attempt_count 
                FROM login_attempts 
                WHERE email = :email 
                AND is_successful = 0 
                AND attempt_time > DATE_SUB(NOW(), INTERVAL :lockout_time SECOND)
            ");
            $stmt->execute([
                ':email' => $email,
                ':lockout_time' => $this->lockout_time
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['attempt_count'] >= $this->max_attempts) {
                // Lock the account
                $this->lockAccount($email);
                throw new Exception("Too many failed attempts. Account locked for {$this->lockout_time} seconds.");
            }

            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function recordAttempt($email, $isSuccessful = false) {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (email, ip_address, is_successful)
            VALUES (:email, :ip_address, :is_successful)
        ");
        
        $stmt->execute([
            ':email' => $email,
            ':ip_address' => $_SERVER['REMOTE_ADDR'],
            ':is_successful' => $isSuccessful
        ]);

        if ($isSuccessful) {
            // On successful login, unlock the account if it was locked
            $this->unlockAccount($email);
        }
    }

    private function lockAccount($email) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET is_locked = 1, locked_until = DATE_ADD(NOW(), INTERVAL :lockout_time SECOND)
            WHERE email = :email
        ");
        
        $stmt->execute([
            ':email' => $email,
            ':lockout_time' => $this->lockout_time
        ]);
    }

    private function unlockAccount($email) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET is_locked = 0, locked_until = NULL
            WHERE email = :email
        ");
        
        $stmt->execute([':email' => $email]);
    }

    private function cleanupOldAttempts($email) {
        // Remove attempts older than lockout time
        $stmt = $this->db->prepare("
            DELETE FROM login_attempts 
            WHERE email = :email 
            AND attempt_time < DATE_SUB(NOW(), INTERVAL :lockout_time SECOND)
        ");
        
        $stmt->execute([
            ':email' => $email,
            ':lockout_time' => $this->lockout_time
        ]);
    }
} 