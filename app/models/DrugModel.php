<?php
require_once __DIR__ . '/BaseModel.php';
class DrugModel extends BaseModel
{
    public function all($limit = 100)
    {
        if (!$this->tableExists('drugs')) return [];
        try { return $this->conn->query('SELECT * FROM drugs ORDER BY id DESC LIMIT ' . (int)$limit)->fetchAll() ?: []; }
        catch (Throwable $e) { return []; }
    }
}
