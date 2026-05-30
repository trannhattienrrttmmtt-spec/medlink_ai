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
            $cols = ['user_id', 'input_type', 'keyword'];
            $vals = [$userId, $inputType, $keyword];
            if ($this->columnExists($table, 'dataset')) { $cols[] = 'dataset'; $vals[] = $dataset; }
            if ($this->columnExists($table, 'result_summary')) { $cols[] = 'result_summary'; $vals[] = $result; }
            elseif ($this->columnExists($table, 'result')) { $cols[] = 'result'; $vals[] = $result; }
            $place = implode(',', array_fill(0, count($cols), '?'));
            $stmt = $this->conn->prepare("INSERT INTO `$table`(" . implode(',', $cols) . ") VALUES($place)");
            return $stmt->execute($vals);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function recent($userId, $limit = 10, $dataset = '')
    {
        $table = $this->getTable();
        if (!$table) return [];
        try {
            $userCol = $this->columnExists($table, 'user_id') ? 'user_id' : null;
            $hasDataset = $dataset !== '' && $this->columnExists($table, 'dataset');
            $timeCol = $this->columnExists($table, 'created_at') ? 'created_at' : ($this->columnExists($table, 'time') ? 'time' : 'id');
            if ($userCol && $hasDataset) {
                $stmt = $this->conn->prepare("SELECT * FROM `$table` WHERE `$userCol` = ? AND `dataset` = ? ORDER BY `$timeCol` DESC LIMIT " . (int)$limit);
                $stmt->execute([$userId, $dataset]);
            } elseif ($userCol) {
                $stmt = $this->conn->prepare("SELECT * FROM `$table` WHERE `$userCol` = ? ORDER BY `$timeCol` DESC LIMIT " . (int)$limit);
                $stmt->execute([$userId]);
            } elseif ($hasDataset) {
                $stmt = $this->conn->prepare("SELECT * FROM `$table` WHERE `dataset` = ? ORDER BY `$timeCol` DESC LIMIT " . (int)$limit);
                $stmt->execute([$dataset]);
            } else {
                $stmt = $this->conn->query("SELECT * FROM `$table` ORDER BY `$timeCol` DESC LIMIT " . (int)$limit);
            }
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function save($userId, $inputType, $keyword, $result = '')
    {
        return $this->add($userId, $inputType, $keyword, '', $result);
    }

    public function getByUser($userId, $limit = 50)
    {
        return $this->recent($userId, $limit);
    }

    public function deleteById($id, $userId)
    {
        $table = $this->getTable();
        if (!$table) return false;
        try {
            $stmt = $this->conn->prepare("DELETE FROM `$table` WHERE id = ? AND user_id = ?");
            return $stmt->execute([$id, $userId]);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function deleteAllByUser($userId)
    {
        $table = $this->getTable();
        if (!$table) return false;
        try {
            $stmt = $this->conn->prepare("DELETE FROM `$table` WHERE user_id = ?");
            return $stmt->execute([$userId]);
        } catch (Throwable $e) {
            return false;
        }
    }
}
