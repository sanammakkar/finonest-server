<?php
class User {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($name, $email, $password, $mobile = null, $role = 'USER') {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        if ($mobile !== null) {
            $query = "INSERT INTO " . $this->table_name . " (name, email, password, mobile, role) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([$name, $email, $password_hash, $mobile, $role]);
        } else {
            $query = "INSERT INTO " . $this->table_name . " (name, email, password, role) VALUES (?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([$name, $email, $password_hash, $role]);
        }
        return $result ? $this->conn->lastInsertId() : false;
    }

    public function findByEmail($email) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Map 'password' column to 'password_hash' for compatibility
        if ($result && isset($result['password'])) {
            $result['password_hash'] = $result['password'];
        }
        
        return $result;
    }

    public function findById($id) {
        $query = "SELECT id, name, email, mobile, role, created_at FROM " . $this->table_name . " WHERE id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    public function emailExists($email) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$email]);
        return $stmt->rowCount() > 0;
    }

    public function getAll($limit = 10, $offset = 0) {
        $query = "SELECT id, name, email, role, created_at FROM " . $this->table_name . " ORDER BY created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateRole($id, $role) {
        $query = "UPDATE " . $this->table_name . " SET role = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$role, $id]);
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id]);
    }

    public function getTotalCount() {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
}
?>