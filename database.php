<?php
class Database {
    private $conn;

    public function __construct() {
        $this->connect();
        $this->createTable();
    }

    private function connect() {
        try {
            // Check if we should use Unix socket (Cloud Run) or TCP (local)
            if (DB_UNIX_SOCKET) {
                $dsn = sprintf(
                    'mysql:unix_socket=%s;dbname=%s;charset=utf8mb4',
                    DB_UNIX_SOCKET,
                    DB_NAME
                );
            } else {
                $dsn = sprintf(
                    'mysql:host=%s;dbname=%s;charset=utf8mb4',
                    DB_HOST,
                    DB_NAME
                );
            }

            $this->conn = new PDO($dsn, DB_USER, DB_PASS);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    private function createTable() {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            google_id VARCHAR(255) UNIQUE NOT NULL,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            avatar TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        try {
            $this->conn->exec($sql);
        } catch(PDOException $e) {
            die("Table creation failed: " . $e->getMessage());
        }
    }

    public function saveUser($googleId, $email, $name, $avatar) {
        $sql = "INSERT INTO users (google_id, email, name, avatar) 
                VALUES (:google_id, :email, :name, :avatar)
                ON DUPLICATE KEY UPDATE 
                email = :email, name = :name, avatar = :avatar";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':google_id' => $googleId,
                ':email' => $email,
                ':name' => $name,
                ':avatar' => $avatar
            ]);
            
            return $this->conn->lastInsertId() ?: $this->getUserByGoogleId($googleId)['id'];
        } catch(PDOException $e) {
            error_log("Save user failed: " . $e->getMessage());
            return false;
        }
    }

    public function getUserByGoogleId($googleId) {
        $sql = "SELECT * FROM users WHERE google_id = :google_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':google_id' => $googleId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getUserById($id) {
        $sql = "SELECT * FROM users WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>