<?php
require_once __DIR__ . '/../models/DiseaseModel.php';
class DiseaseController
{
    public function index()
    {
        if (!isset($_SESSION['user_id'])) { header('Location: index.php?action=login'); exit; }
        $items = (new DiseaseModel())->all(200);
        $title = 'Danh sách bệnh';
        $type = 'disease';
        include __DIR__ . '/../views/user/list.php';
    }
}
