<?php
require_once __DIR__ . '/BaseModel.php';
class DiseaseModel extends BaseModel
{
    public function all($limit = 100)
    {
        if (!$this->tableExists('diseases')) return [];
        try { return $this->conn->query('SELECT * FROM diseases ORDER BY id DESC LIMIT ' . (int)$limit)->fetchAll() ?: []; }
        catch (Throwable $e) { return []; }
    }
}
