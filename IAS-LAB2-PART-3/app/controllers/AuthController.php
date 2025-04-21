<?php
require_once __DIR__ . '/../config/database.php';

class AuthController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    /**
     * Register a new user
     * @param string $name
     * @param string $email
     * @param string $password
     * @param string $firebase_uid
     * @return array
     */
    public function register($name, $email, $password, $firebase_uid) {
        try {
            // Check if email already exists
            if ($this->emailExists($email)) {
                return [
                    "success" => false,
                    "message" => "Email already exists"
                ];
            }

            // Hash the password using bcrypt
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            // Insert new user
            $query = "INSERT INTO users (name, email, password, firebase_uid) VALUES (:name, :email, :password, :firebase_uid)";
            $stmt = $this->db->prepare($query);
            
            $stmt->bindParam(":name", $name);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":password", $hashed_password);
            $stmt->bindParam(":firebase_uid", $firebase_uid);

            if($stmt->execute()) {
                return [
                    "success" => true,
                    "message" => "User registered successfully",
                    "user_id" => $this->db->lastInsertId()
                ];
            }
            
            return [
                "success" => false,
                "message" => "Failed to register user"
            ];
        } catch(PDOException $e) {
            return [
                "success" => false,
                "message" => "Database error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Check if email exists
     * @param string $email
     * @return bool
     */
    private function emailExists($email) {
        $query = "SELECT id FROM users WHERE email = :email";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Verify user password
     * @param string $email
     * @param string $password
     * @return array
     */
    public function verifyPassword($email, $password) {
        try {
            $query = "SELECT id, password FROM users WHERE email = :email";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->execute();

            if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (password_verify($password, $user['password'])) {
                    return [
                        "success" => true,
                        "user_id" => $user['id']
                    ];
                }
            }

            return [
                "success" => false,
                "message" => "Invalid email or password"
            ];
        } catch(PDOException $e) {
            return [
                "success" => false,
                "message" => "Database error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Create a new session
     * @param int $user_id
     * @param string $token
     * @return bool
     */
    public function createSession($user_id, $token) {
        try {
            // First, invalidate any existing sessions
            $this->invalidateExistingSessions($user_id);

            // Create new session
            $query = "INSERT INTO user_sessions (user_id, token, expires_at) 
                     VALUES (:user_id, :token, DATE_ADD(NOW(), INTERVAL 30 MINUTE))";
            $stmt = $this->db->prepare($query);
            
            $stmt->bindParam(":user_id", $user_id);
            $stmt->bindParam(":token", $token);

            return $stmt->execute();
        } catch(PDOException $e) {
            return false;
        }
    }

    /**
     * Invalidate existing sessions
     * @param int $user_id
     */
    private function invalidateExistingSessions($user_id) {
        $query = "UPDATE user_sessions SET expires_at = NOW() WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
    }

    /**
     * Validate session token
     * @param string $token
     * @return array|false
     */
    public function validateSession($token) {
        try {
            $query = "SELECT u.email, s.* FROM user_sessions s
                     JOIN users u ON u.id = s.user_id
                     WHERE s.token = :token AND s.expires_at > NOW()";
            $stmt = $this->db->prepare($query);
            
            $stmt->bindParam(":token", $token);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            return false;
        }
    }

    public function login() {
        require_once BASE_PATH . '/app/views/auth/login.php';
    }

    public function logout() {
        session_start();
        session_destroy();
        header('Location: /login');
        exit();
    }
}
?> 