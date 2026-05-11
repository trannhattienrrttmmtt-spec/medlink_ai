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

        include '../app/views/user/dashboard.php';
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
            $this->setFlashError('Vui lòng nhập từ khóa.');
            $this->redirectDashboard();
        }

        if (!in_array($input_type, ['drug', 'disease', 'symptom'], true)) {
            $this->setFlashError('Kiểu tra cứu không hợp lệ.');
            $this->redirectDashboard();
        }

        if ($input_type === 'symptom' && $model_type !== 'current') {
            $this->setFlashError('Model gốc chỉ so sánh cho drug/disease. Triệu chứng chỉ dùng model hiện tại.');
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
            $data = $this->callAiApi($currentApi, $payload, 'model hiện tại');

            $results = $this->normalizeResults($data['results'] ?? [], 'Model hiện tại - ' . $dataset);
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
            $data = $this->callAiApi($originalApi, $payload, 'model gốc AMDGT');

            $results = $this->normalizeResults($data['results'] ?? [], 'Model gốc AMDGT - ' . $dataset);
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
            $currentData = $this->callAiApi($currentApi, $payload, 'model hiện tại');
            $originalData = $this->callAiApi($originalApi, $payload, 'model gốc AMDGT');

            $currentResults = $this->normalizeResults(
                $currentData['results'] ?? [],
                'Model hiện tại - ' . $dataset
            );

            $originalResults = $this->normalizeResults(
                $originalData['results'] ?? [],
                'Model gốc AMDGT - ' . $dataset
            );

            $results = [];

            foreach ($currentResults as $item) {
                $item['compare_group'] = 'Model hiện tại';
                $results[] = $item;
            }

            foreach ($originalResults as $item) {
                $item['compare_group'] = 'Model gốc AMDGT';
                $results[] = $item;
            }

            if (empty($results)) {
                $this->setFlashError('Không có kết quả so sánh.');
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

            $this->setFlashError('Lỗi gọi ' . $label . ': ' . $error);
            $this->redirectDashboard();
        }

        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode !== 200 || !$data || empty($data['success'])) {
            $apiMessage = $data['message'] ?? 'Không lấy được kết quả từ ' . $label;
            $this->setFlashError('Lỗi ' . $label . ': ' . $apiMessage);
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
                'source'  => 'Symptom → Disease (' . $dataset . ')',
                'smiles'  => '',
                'dataset' => $dataset
            ];
        }

        foreach ($drug_results as $item) {
            $results[] = [
                'name'    => $item['name'] ?? '',
                'code'    => $item['code'] ?? '',
                'score'   => $item['score'] ?? 0,
                'source'  => $item['source'] ?? ('Disease → Drug (' . $dataset . ')'),
                'smiles'  => $item['smiles'] ?? '',
                'dataset' => $dataset
            ];
        }

        return $results;
    }

    private function saveAndShow($input_type, $displayKeyword, $results, $graph, $dataset, $model_type, $top_k)
    {
        if (empty($results)) {
            $this->setFlashError('Không tìm thấy dữ liệu phù hợp cho từ khóa: ' . $displayKeyword);
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
            $historyModel->save($_SESSION['user_id'], $input_type, $displayKeyword, $summary);
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
            $this->setFlashError('Vui lòng nhập tên bệnh mới.');
            $this->redirectDashboard();
        }

        if ($protein_input === '') {
            $this->setFlashError('Vui lòng nhập ít nhất một protein ID.');
            $this->redirectDashboard();
        }

        $protein_list = preg_split('/[\r\n,;]+/', $protein_input);
        $protein_list = array_values(array_filter(array_map('trim', $protein_list)));

        if (empty($protein_list)) {
            $this->setFlashError('Danh sách protein không hợp lệ.');
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
            $this->setFlashError('Không tìm thấy thuốc phù hợp từ protein đã nhập.');
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
            $historyModel->save($_SESSION['user_id'], $input_type, $displayKeyword, $summary);
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
            $items = $historyModel->getByUser($_SESSION['user_id']);
        } catch (Exception $e) {
            $items = [];
        }

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
                $_SESSION['flash_success'] = 'Đã xóa mục lịch sử.';
            } catch (Exception $e) {
                $_SESSION['flash_error'] = 'Không thể xóa mục lịch sử.';
            }
        } else {
            $_SESSION['flash_error'] = 'ID lịch sử không hợp lệ.';
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
            $_SESSION['flash_success'] = 'Đã xóa toàn bộ lịch sử.';
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Không thể xóa toàn bộ lịch sử.';
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