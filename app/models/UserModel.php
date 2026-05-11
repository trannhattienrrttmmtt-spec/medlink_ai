<?php
require_once __DIR__ . '/BaseModel.php';

class UserModel extends BaseModel
{
    public function findByUsernameOrEmail($login)
    {
        if (!$this->tableExists('users')) return null;
        $hasEmail = $this->columnExists('users', 'email');
        if ($hasEmail) {
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$login, $login]);
        } else {
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$login]);
        }
        return $stmt->fetch() ?: null;
    }

    public function create($username, $fullName, $email, $password, $role = 'user')
    {
        if (!$this->tableExists('users')) return false;
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $hasEmail = $this->columnExists('users', 'email');
        $hasFullName = $this->columnExists('users', 'full_name');

        if ($hasEmail && $hasFullName) {
            $stmt = $this->conn->prepare("INSERT INTO users(username, full_name, email, password, role) VALUES(?,?,?,?,?)");
            return $stmt->execute([$username, $fullName, $email, $hash, $role]);
        }
        if ($hasFullName) {
            $stmt = $this->conn->prepare("INSERT INTO users(username, full_name, password, role) VALUES(?,?,?,?)");
            return $stmt->execute([$username, $fullName, $hash, $role]);
        }
        $stmt = $this->conn->prepare("INSERT INTO users(username, password, role) VALUES(?,?,?)");
        return $stmt->execute([$username, $hash, $role]);
    }
}
