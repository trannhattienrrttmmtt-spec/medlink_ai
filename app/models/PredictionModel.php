<?php
require_once __DIR__ . '/BaseModel.php';

class PredictionModel extends BaseModel
{
    public function savePrediction($inputType, $inputName, $resultName, $score, $dataset = '')
    {
        if (!$this->tableExists('predictions')) return false;
        try {
            $cols = ['input_type', 'input_name', 'result_name', 'score'];
            $vals = [$inputType, $inputName, $resultName, $score];
            if ($this->columnExists('predictions', 'dataset')) { $cols[] = 'dataset'; $vals[] = $dataset; }
            $place = implode(',', array_fill(0, count($cols), '?'));
            $stmt = $this->conn->prepare("INSERT INTO predictions(" . implode(',', $cols) . ") VALUES($place)");
            return $stmt->execute($vals);
        } catch (Throwable $e) {
            return false;
        }
    }
}
