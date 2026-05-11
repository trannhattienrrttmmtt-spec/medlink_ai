<?php
require_once __DIR__ . '/../config/database.php';

class BaseModel
{
    protected $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    protected function tableExists($table)
    {
        try {
            $stmt = $this->conn->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    protected function columnExists($table, $column)
    {
        try {
            $stmt = $this->conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$column]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}
