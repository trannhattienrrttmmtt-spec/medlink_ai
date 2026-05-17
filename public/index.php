<?php
session_start();

$action = $_GET['action'] ?? ($_POST['action'] ?? 'login');

// Nếu chưa login và không phải action login/register → redirect
if (!isset($_SESSION['user_id']) && !in_array($action, ['login', 'register', 'do_login', 'do_register'])) {
    $action = 'login';
}

require_once '../app/controllers/AuthController.php';
require_once '../app/controllers/PredictionController.php';

$authController = new AuthController();
$predictionController = new PredictionController();

switch ($action) {
    case 'login':
        $authController->login();
        break;
    case 'do_login':
        $authController->doLogin();
        break;
    case 'register':
        $authController->register();
        break;
    case 'do_register':
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
    case 'predict_new_disease_protein':
        $predictionController->predictNewDiseaseProtein();
        break;
    case 'generate_drug':
        if (method_exists($predictionController, 'generateDrug')) {
            $predictionController->generateDrug();
        } else {
            $predictionController->dashboard();
        }
        break;
    case 'history':
        $predictionController->history();
        break;
    case 'delete_history':
        $predictionController->deleteHistory();
        break;
    case 'delete_all_history':
        $predictionController->deleteAllHistory();
        break;
    case 'save_history':
        // AJAX save history
        if (isset($_SESSION['user_id'])) {
            require_once '../app/models/HistoryModel.php';
            $h = new HistoryModel();
            $h->add($_SESSION['user_id'], $_GET['input_type'] ?? '', $_GET['keyword'] ?? '', $_GET['dataset'] ?? '');
        }
        echo json_encode(['ok' => true]);
        break;
    case 'admin_dashboard':
        // Admin removed - redirect to dashboard
        header('Location: index.php?action=dashboard');
        break;
    default:
        $predictionController->dashboard();
        break;
}
