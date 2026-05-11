<?php
require_once __DIR__ . '/../models/DrugModel.php';
class DrugController
{
    public function index()
    {
        if (!isset($_SESSION['user_id'])) { header('Location: index.php?action=login'); exit; }
        $items = (new DrugModel())->all(200);
        $title = 'Danh sách thuốc';
        $type = 'drug';
        include __DIR__ . '/../views/user/list.php';
    }
}
