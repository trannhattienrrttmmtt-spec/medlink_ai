<?php
session_start();

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/controllers/PredictionController.php';
require_once __DIR__ . '/../app/controllers/DrugController.php';
require_once __DIR__ . '/../app/controllers/DiseaseController.php';

$action = $_GET['action'] ?? 'login';

$authController = new AuthController();
$predictionController = new PredictionController();
$drugController = new DrugController();
$diseaseController = new DiseaseController();

switch ($action) {
    case 'login':
        $authController->login();
        break;
    case 'do_login':
    case 'doLogin':
        $authController->doLogin();
        break;
    case 'register':
        $authController->register();
        break;
    case 'do_register':
    case 'doRegister':
        $authController->doRegister();
        break;
    case 'logout':
        $authController->logout();
        break;
    case 'dashboard':
        $predictionController->dashboard();
        break;
    case 'predict':
        $predictionController->predict();
        break;
    case 'generate_drug':
    case 'generateDrug':
        $predictionController->generateDrug();
        break;
    case 'history':
        $predictionController->history();
        break;
    case 'drugs':
        $drugController->index();
        break;
    case 'diseases':
        $diseaseController->index();
        break;
    default:
        header('Location: index.php?action=login');
        exit;
}
