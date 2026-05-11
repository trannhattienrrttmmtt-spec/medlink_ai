<?php
if (session_status() === PHP_SESSION_NONE) session_start();
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$fullName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Người dùng';
$keyword = $keyword ?? $_POST['keyword'] ?? $_GET['keyword'] ?? '';
$input_type = $input_type ?? $_POST['input_type'] ?? $_GET['input_type'] ?? '';
$dataset = $dataset ?? $_POST['dataset'] ?? $_GET['dataset'] ?? 'B-dataset';
$results = $results ?? $predictions ?? [];
if (isset($apiResult['results']) && is_array($apiResult['results'])) $results = $apiResult['results'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả dự đoán - MedLink AI</title>
    <link rel="stylesheet" href="assets/css/medlink-dashboard.css">
</head>
<body>
<div class="ml-app">
    <aside class="ml-sidebar">
        <div class="ml-brand"><div class="ml-logo">⚕</div><div><h1>MedLink AI</h1><p>Drug-Disease Prediction</p></div></div>
        <div class="ml-nav">
            <div class="ml-nav-title">Điều hướng</div>
            <a href="index.php?action=dashboard">🏠 Tổng quan</a>
            <a class="active" href="index.php?action=dashboard#predict-box">🔎 Dự đoán</a>
            <a href="index.php?action=history">🕘 Lịch sử</a>
            <a href="index.php?action=logout">🚪 Đăng xuất</a>
        </div>
    </aside>
    <main class="ml-main">
        <div class="ml-topbar">
            <div><button class="ml-btn dark ml-mobile-menu" data-menu-btn>☰ Menu</button><h2>Kết quả dự đoán</h2><p>Từ khóa: <b><?= e($keyword) ?></b> • Dataset: <b><?= e($dataset) ?></b></p></div>
            <div class="ml-user"><div class="ml-avatar"><?= e(substr($fullName, 0, 1)) ?></div><div><b><?= e($fullName) ?></b><br><small>MedLink AI</small></div></div>
        </div>

        <div class="ml-card" style="margin-bottom:22px">
            <div class="ml-card-head">
                <div><h4>📌 Thông tin truy vấn</h4><small>Kiểu tìm kiếm: <?= e($input_type) ?></small></div>
                <a class="ml-btn" href="index.php?action=dashboard#predict-box">Dự đoán lại</a>
            </div>
            <div class="ml-row">
                <div class="ml-dataset-box"><small>Từ khóa</small><br><b><?= e($keyword) ?></b></div>
                <div class="ml-dataset-box"><small>Số kết quả</small><br><b><?= count($results) ?></b></div>
            </div>
        </div>

        <?php if (empty($results)): ?>
            <div class="ml-alert">Không có kết quả. Kiểm tra lại Flask API `/predict`, dataset hoặc từ khóa nhập vào.</div>
        <?php else: ?>
            <section class="ml-grid two">
                <div class="ml-card">
                    <div class="ml-card-head"><div><h4>📋 Bảng kết quả</h4><small>Điểm % là độ liên kết/dự đoán của mô hình, không phải xác suất chữa bệnh tuyệt đối.</small></div></div>
                    <table class="ml-result-table">
                        <thead><tr><th>#</th><th>Tên kết quả</th><th>Dataset</th><th>Score</th><th>SMILES</th></tr></thead>
                        <tbody>
                        <?php foreach ($results as $i => $r):
                            $name = $r['name'] ?? $r['result_name'] ?? $r['disease_name'] ?? $r['drug_name'] ?? $r['target'] ?? '';
                            $scoreRaw = $r['score'] ?? $r['probability'] ?? 0;
                            $score = is_numeric($scoreRaw) ? (float)$scoreRaw : 0;
                            if ($score <= 1) $score *= 100;
                            $ds = $r['dataset'] ?? $dataset;
                            $smiles = $r['smiles'] ?? $r['SMILES'] ?? '';
                        ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><b><?= e($name) ?></b></td>
                                <td><?= e($ds) ?></td>
                                <td><div style="display:flex;gap:8px;align-items:center"><div class="ml-score"><span style="width:<?= max(0,min(100,$score)) ?>%"></span></div><b><?= number_format($score, 2) ?>%</b></div></td>
                                <td><?= $smiles ? '<code>'.e($smiles).'</code>' : '<span class="ml-muted">Không có</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="ml-card">
                    <div class="ml-card-head"><div><h4>📊 Biểu đồ điểm</h4><small>Top kết quả dự đoán</small></div></div>
                    <div class="ml-chart">
                    <?php foreach (array_slice($results, 0, 12) as $r):
                        $name = $r['name'] ?? $r['result_name'] ?? $r['disease_name'] ?? $r['drug_name'] ?? $r['target'] ?? '';
                        $scoreRaw = $r['score'] ?? $r['probability'] ?? 0;
                        $score = is_numeric($scoreRaw) ? (float)$scoreRaw : 0;
                        if ($score <= 1) $score *= 100;
                    ?>
                        <div class="ml-bar-row" title="<?= e($name) ?>">
                            <div class="ml-bar-name"><?= e($name) ?></div>
                            <div class="ml-bar"><span style="width:<?= max(0,min(100,$score)) ?>%"></span></div>
                            <b><?= number_format($score, 1) ?>%</b>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>
<script src="assets/js/medlink-dashboard.js"></script>
</body>
</html>
