<?php

require_once '../app/models/PredictionModel.php';
require_once '../app/models/HistoryModel.php';

class PredictionController
{
    public function dashboard()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $history = [];
        try {
            $historyModel = new HistoryModel();
            $history = $historyModel->recent($_SESSION['user_id'], 10);
        } catch (Exception $e) {}

        include '../app/views/user/dashboard.php';
    }

    public function catalog()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $type = $_GET['type'] ?? 'drug';
        if (!in_array($type, ['drug', 'disease', 'protein'], true)) {
            $type = 'drug';
        }

        $dataset = $_GET['dataset'] ?? 'B-dataset';
        if (!in_array($dataset, ['B-dataset', 'C-dataset', 'F-dataset'], true)) {
            $dataset = 'B-dataset';
        }

        include '../app/views/user/catalog.php';
    }

    public function predict()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $input_type = $_POST['input_type'] ?? '';
        $keyword = trim($_POST['keyword'] ?? '');
        $displayKeyword = $keyword;
        $top_k = (int)($_POST['top_k'] ?? 5);
        $dataset = $_POST['dataset'] ?? 'B-dataset';
        $model_type = $_POST['model_type'] ?? 'current';

        if (!in_array($dataset, ['B-dataset', 'C-dataset', 'F-dataset'], true)) {
            $dataset = 'B-dataset';
        }

        if (!in_array($model_type, ['current', 'original', 'compare'], true)) {
            $model_type = 'current';
        }

        if ($top_k <= 0) {
            $top_k = 5;
        }

        if ($top_k > 10) {
            $top_k = 10;
        }

        if ($keyword === '') {
            $this->setFlashError('Vui lÃ²ng nháº­p tá»« khÃ³a.');
            $this->redirectDashboard();
        }

        if (!in_array($input_type, ['drug', 'disease', 'symptom'], true)) {
            $this->setFlashError('Kiá»ƒu tra cá»©u khÃ´ng há»£p lá»‡.');
            $this->redirectDashboard();
        }

        if ($input_type === 'symptom' && $model_type !== 'current') {
            $this->setFlashError('Model gá»‘c chá»‰ so sÃ¡nh cho drug/disease. Triá»‡u chá»©ng chá»‰ dÃ¹ng model hiá»‡n táº¡i.');
            $this->redirectDashboard();
        }

        if ($input_type === 'disease') {
            $keyword = $this->normalizeVietnameseDisease($keyword);
        }

        $payload = [
            'input_type' => $input_type,
            'keyword'    => $keyword,
            'top_k'      => $top_k,
            'dataset'    => $dataset
        ];

        $currentApi = 'http://127.0.0.1:5000/predict';
        $originalApi = 'http://127.0.0.1:5001/predict';

        if ($model_type === 'current') {
            $data = $this->callAiApi($currentApi, $payload, 'model hiá»‡n táº¡i');

            $results = $this->normalizeResults($data['results'] ?? [], 'Model hiá»‡n táº¡i - ' . $dataset);
            $graph = $data['graph'] ?? [
                'nodes' => [],
                'edges' => [],
                'dataset' => $dataset
            ];

            if ($input_type === 'symptom') {
                $results = $this->buildSymptomResults($data, $dataset);
            }

            $this->saveAndShow(
                $input_type,
                $displayKeyword,
                $results,
                $graph,
                $dataset,
                $model_type,
                $top_k
            );
            return;
        }

        if ($model_type === 'original') {
            $data = $this->callAiApi($originalApi, $payload, 'model gá»‘c AMDGT');

            $results = $this->normalizeResults($data['results'] ?? [], 'Model gá»‘c AMDGT - ' . $dataset);
            $graph = $data['graph'] ?? [
                'nodes' => [],
                'edges' => [],
                'dataset' => $dataset
            ];

            $this->saveAndShow(
                $input_type,
                $displayKeyword,
                $results,
                $graph,
                $dataset,
                $model_type,
                $top_k
            );
            return;
        }

        if ($model_type === 'compare') {
            $currentData = $this->callAiApi($currentApi, $payload, 'model hiá»‡n táº¡i');
            $originalData = $this->callAiApi($originalApi, $payload, 'model gá»‘c AMDGT');

            $currentResults = $this->normalizeResults(
                $currentData['results'] ?? [],
                'Model hiá»‡n táº¡i - ' . $dataset
            );

            $originalResults = $this->normalizeResults(
                $originalData['results'] ?? [],
                'Model gá»‘c AMDGT - ' . $dataset
            );

            $results = [];

            foreach ($currentResults as $item) {
                $item['compare_group'] = 'Model hiá»‡n táº¡i';
                $results[] = $item;
            }

            foreach ($originalResults as $item) {
                $item['compare_group'] = 'Model gá»‘c AMDGT';
                $results[] = $item;
            }

            if (empty($results)) {
                $this->setFlashError('KhÃ´ng cÃ³ káº¿t quáº£ so sÃ¡nh.');
                $this->redirectDashboard();
            }

            $graph = $currentData['graph'] ?? [
                'nodes' => [],
                'edges' => [],
                'dataset' => $dataset
            ];

            $this->saveAndShow(
                $input_type,
                $displayKeyword,
                $results,
                $graph,
                $dataset,
                $model_type,
                $top_k
            );
            return;
        }
    }

    private function callAiApi($url, $payloadArray, $label = 'AI API')
    {
        $payload = json_encode($payloadArray, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);

            $this->setFlashError('Lá»—i gá»i ' . $label . ': ' . $error);
            $this->redirectDashboard();
        }

        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode !== 200 || !$data || empty($data['success'])) {
            $apiMessage = $data['message'] ?? 'KhÃ´ng láº¥y Ä‘Æ°á»£c káº¿t quáº£ tá»« ' . $label;
            $this->setFlashError('Lá»—i ' . $label . ': ' . $apiMessage);
            $this->redirectDashboard();
        }

        return $data;
    }

    private function normalizeResults($results, $sourceLabel)
    {
        $normalized = [];

        foreach ($results as $item) {
            $normalized[] = [
                'name'    => $item['name'] ?? '',
                'code'    => $item['code'] ?? '',
                'score'   => $item['score'] ?? 0,
                'source'  => $item['source'] ?? $sourceLabel,
                'smiles'  => $item['smiles'] ?? '',
                'dataset' => $item['dataset'] ?? ''
            ];
        }

        return $normalized;
    }

    private function buildSymptomResults($data, $dataset)
    {
        $results = [];

        $disease_results = $data['disease_results'] ?? [];
        $drug_results = $data['drug_results'] ?? [];

        foreach ($disease_results as $item) {
            $results[] = [
                'name'    => $item['name_vi'] ?? ($item['name'] ?? ''),
                'code'    => $item['name'] ?? '',
                'score'   => $item['score'] ?? 0,
                'source'  => 'Symptom â†’ Disease (' . $dataset . ')',
                'smiles'  => '',
                'dataset' => $dataset
            ];
        }

        foreach ($drug_results as $item) {
            $results[] = [
                'name'    => $item['name'] ?? '',
                'code'    => $item['code'] ?? '',
                'score'   => $item['score'] ?? 0,
                'source'  => $item['source'] ?? ('Disease â†’ Drug (' . $dataset . ')'),
                'smiles'  => $item['smiles'] ?? '',
                'dataset' => $dataset
            ];
        }

        return $results;
    }

    private function saveAndShow($input_type, $displayKeyword, $results, $graph, $dataset, $model_type, $top_k)
    {
        if (empty($results)) {
            $this->setFlashError('KhÃ´ng tÃ¬m tháº¥y dá»¯ liá»‡u phÃ¹ há»£p cho tá»« khÃ³a: ' . $displayKeyword);
            $this->redirectDashboard();
        }

        try {
            $predictionModel = new PredictionModel();

            foreach ($results as $item) {
                $predictionModel->save(
                    $input_type,
                    $displayKeyword,
                    $item['name'] ?? '',
                    isset($item['score']) ? $item['score'] * 100 : 0
                );
            }

            $summary = json_encode([
                'dataset'    => $dataset,
                'model_type' => $model_type,
                'results'    => $results,
                'graph'      => $graph
            ], JSON_UNESCAPED_UNICODE);

            $historyModel = new HistoryModel();
            $historyModel->add($_SESSION['user_id'], $input_type, $displayKeyword, $summary);
        } catch (Exception $e) {
        }

        $selected_top_k = $top_k;
        $selected_dataset = $dataset;
        $selected_model_type = $model_type;

        include '../app/views/user/predict.php';
    }

    public function predictNewDiseaseProtein()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $new_disease_name = trim($_POST['new_disease_name'] ?? '');
        $protein_input = trim($_POST['protein_ids'] ?? '');
        $top_k = (int)($_POST['top_k'] ?? 5);
        $dataset = $_POST['dataset'] ?? 'B-dataset';

        if (!in_array($dataset, ['B-dataset', 'C-dataset', 'F-dataset'], true)) {
            $dataset = 'B-dataset';
        }

        if ($top_k <= 0) {
            $top_k = 5;
        }

        if ($top_k > 10) {
            $top_k = 10;
        }

        if ($new_disease_name === '') {
            $this->setFlashError('Vui lÃ²ng nháº­p tÃªn bá»‡nh má»›i.');
            $this->redirectDashboard();
        }

        if ($protein_input === '') {
            $this->setFlashError('Vui lÃ²ng nháº­p Ã­t nháº¥t má»™t protein ID.');
            $this->redirectDashboard();
        }

        $protein_list = preg_split('/[\r\n,;]+/', $protein_input);
        $protein_list = array_values(array_filter(array_map('trim', $protein_list)));

        if (empty($protein_list)) {
            $this->setFlashError('Danh sÃ¡ch protein khÃ´ng há»£p lá»‡.');
            $this->redirectDashboard();
        }

        $payloadArray = [
            'new_disease_name' => $new_disease_name,
            'protein_ids'      => $protein_list,
            'top_k'            => $top_k,
            'dataset'          => $dataset
        ];

        $data = $this->callAiApi(
            'http://127.0.0.1:5000/predict_new_disease_protein',
            $payloadArray,
            'AI API protein'
        );

        $results = $data['results'] ?? [];
        $graph = $data['graph'] ?? [
            'nodes'   => [],
            'edges'   => [],
            'dataset' => $dataset
        ];

        if (empty($results)) {
            $this->setFlashError('KhÃ´ng tÃ¬m tháº¥y thuá»‘c phÃ¹ há»£p tá»« protein Ä‘Ã£ nháº­p.');
            $this->redirectDashboard();
        }

        foreach ($results as &$item) {
            $item['dataset'] = $data['dataset'] ?? $dataset;

            if (empty($item['source'])) {
                $item['source'] = 'Disease + Protein (' . $dataset . ')';
            }
        }
        unset($item);

        $displayKeyword = $new_disease_name;
        $input_type = 'disease_protein';

        $summary = json_encode([
            'dataset'     => $dataset,
            'model_type'  => 'current',
            'results'     => $results,
            'graph'       => $graph,
            'protein_ids' => $protein_list
        ], JSON_UNESCAPED_UNICODE);

        try {
            $historyModel = new HistoryModel();
            $historyModel->add($_SESSION['user_id'], $input_type, $displayKeyword, $summary);
        } catch (Exception $e) {
        }

        $selected_top_k = $top_k;
        $selected_dataset = $dataset;
        $selected_model_type = 'current';

        include '../app/views/user/predict.php';
    }

    public function history()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $items = [];

        try {
            $historyModel = new HistoryModel();
            $items = $historyModel->recent($_SESSION['user_id'], 50);
        } catch (Exception $e) {
            $items = [];
        }

        $history = $items;
        include '../app/views/user/history.php';
    }

    public function deleteHistory()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $id = (int)($_GET['id'] ?? 0);

        if ($id > 0) {
            try {
                $historyModel = new HistoryModel();
                $historyModel->deleteById($id, $_SESSION['user_id']);
                $_SESSION['flash_success'] = 'ÄÃ£ xÃ³a má»¥c lá»‹ch sá»­.';
            } catch (Exception $e) {
                $_SESSION['flash_error'] = 'KhÃ´ng thá»ƒ xÃ³a má»¥c lá»‹ch sá»­.';
            }
        } else {
            $_SESSION['flash_error'] = 'ID lá»‹ch sá»­ khÃ´ng há»£p lá»‡.';
        }

        header('Location: /MEDLINK_AI/public/index.php?action=history');
        exit;
    }

    public function deleteAllHistory()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        try {
            $historyModel = new HistoryModel();
            $historyModel->deleteAllByUser($_SESSION['user_id']);
            $_SESSION['flash_success'] = 'ÄÃ£ xÃ³a toÃ n bá»™ lá»‹ch sá»­.';
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'KhÃ´ng thá»ƒ xÃ³a toÃ n bá»™ lá»‹ch sá»­.';
        }

        header('Location: /MEDLINK_AI/public/index.php?action=history');
        exit;
    }

    private function setFlashError($message)
    {
        $_SESSION['flash_error'] = $message;
    }

    private function redirectDashboard()
    {
        header('Location: /MEDLINK_AI/public/index.php?action=dashboard');
        exit;
    }

    private function normalizeVietnameseDisease($keyword)
    {
        $mapFile = '../app/data/disease_vi_map_598.php';

        if (!file_exists($mapFile)) {
            return $keyword;
        }

        $diseaseMap = include $mapFile;

        if (!is_array($diseaseMap)) {
            return $keyword;
        }

        $normalizedKeyword = mb_strtolower(trim($keyword), 'UTF-8');

        foreach ($diseaseMap as $en => $vi) {
            if (mb_strtolower(trim($vi), 'UTF-8') === $normalizedKeyword) {
                return $en;
            }
        }

        return $keyword;
    }
}
?>
