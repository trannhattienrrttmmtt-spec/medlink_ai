<?php
class Database
{
    private $host = 'localhost';
    private $db_name = 'medlink_ai';
    private $username = 'root';
    private $password = '';
    public $conn;

    public function connect()
    {
        $this->conn = null;
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die('Lỗi kết nối database: ' . $e->getMessage());
        }
        return $this->conn;
    }

    public static function getConnection()
    {
        return (new self())->connect();
    }
}
