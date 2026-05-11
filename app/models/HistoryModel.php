<?php
require_once __DIR__ . '/BaseModel.php';

class HistoryModel extends BaseModel
{
    private function getTable()
    {
        if ($this->tableExists('search_history')) return 'search_history';
        if ($this->tableExists('history')) return 'history';
        return null;
    }

    public function add($userId, $inputType, $keyword, $dataset = '', $result = '')
    {
        $table = $this->getTable();
        if (!$table) return false;
        try {
            if ($table === 'search_history') {
                $cols = ['user_id', 'input_type', 'keyword'];
                $vals = [$userId, $inputType, $keyword];
                if ($this->columnExists($table, 'dataset')) { $cols[] = 'dataset'; $vals[] = $dataset; }
                if ($this->columnExists($table, 'result')) { $cols[] = 'result'; $vals[] = $result; }
                $place = implode(',', array_fill(0, count($cols), '?'));
                $stmt = $this->conn->prepare("INSERT INTO `$table`(" . implode(',', $cols) . ") VALUES($place)");
                return $stmt->execute($vals);
            }
            $stmt = $this->conn->prepare("INSERT INTO history(user_id, input_type, keyword, result) VALUES(?,?,?,?)");
            return $stmt->execute([$userId, $inputType, $keyword, $result]);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function recent($userId, $limit = 10)
    {
        $table = $this->getTable();
        if (!$table) return [];
        try {
            $userCol = $this->columnExists($table, 'user_id') ? 'user_id' : null;
            $timeCol = $this->columnExists($table, 'created_at') ? 'created_at' : ($this->columnExists($table, 'time') ? 'time' : 'id');
            if ($userCol) {
                $stmt = $this->conn->prepare("SELECT * FROM `$table` WHERE `$userCol` = ? ORDER BY `$timeCol` DESC LIMIT " . (int)$limit);
                $stmt->execute([$userId]);
            } else {
                $stmt = $this->conn->query("SELECT * FROM `$table` ORDER BY `$timeCol` DESC LIMIT " . (int)$limit);
            }
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}
