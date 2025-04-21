<?php
class User {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . "
                (name, email, password, firebase_uid)
                VALUES (:name, :email, :password, :firebase_uid)";

        try {
            $stmt = $this->conn->prepare($query);

            // Sanitize and bind data
            $stmt->bindParam(":name", htmlspecialchars(strip_tags($data['name'])));
            $stmt->bindParam(":email", htmlspecialchars(strip_tags($data['email'])));
            $stmt->bindParam(":password", $data['password']);
            $stmt->bindParam(":firebase_uid", htmlspecialchars(strip_tags($data['firebase_uid'])));

            if($stmt->execute()) {
                return $this->conn->lastInsertId();
            }
            return false;
        } catch(PDOException $e) {
            // Check for duplicate email
            if($e->getCode() == 23000) {
                throw new Exception("Email already exists");
            }
            throw new Exception($e->getMessage());
        }
    }

    public function findByEmail($email) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        
        $email = htmlspecialchars(strip_tags($email));
        $stmt->bindParam(":email", $email);
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByFirebaseUID($firebase_uid) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE firebase_uid = :firebase_uid LIMIT 1";
        $stmt = $this->conn->prepare($query);
        
        $firebase_uid = htmlspecialchars(strip_tags($firebase_uid));
        $stmt->bindParam(":firebase_uid", $firebase_uid);
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
} 