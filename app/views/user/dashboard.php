<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$fullName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Người dùng';
$role = $_SESSION['role'] ?? 'user';
$history = $history ?? $recentHistory ?? [];
$stats = $stats ?? [
    'totalPredictions' => 1247,
    'totalDrugs' => 2834,
    'totalDiseases' => 1456,
    'averageAccuracy' => 94.2,
    'datasetStats' => [
        'B-dataset' => ['drugs' => 269, 'diseases' => 598],
        'C-dataset' => ['drugs' => 663, 'diseases' => 409],
        'F-dataset' => ['drugs' => 592, 'diseases' => 313],
    ]
];
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedLink AI - Dashboard</title>
    <link rel="stylesheet" href="assets/css/medlink-dashboard.css">
</head>
<body>
<div class="ml-app">
    <aside class="ml-sidebar">
        <div class="ml-brand">
            <div class="ml-logo">⚕</div>
            <div><h1>MedLink AI</h1><p>Drug-Disease Prediction</p></div>
        </div>
        <div class="ml-nav">
            <div class="ml-nav-title">Điều hướng</div>
            <a class="active" href="index.php?action=dashboard">🏠 Tổng quan</a>
            <a href="#predict-box">🔎 Dự đoán</a>
            <a href="index.php?action=history">🕘 Lịch sử</a>
            <?php if ($role === 'admin'): ?>
                <a href="index.php?action=admin_dashboard">🛠 Quản trị</a>
            <?php endif; ?>
            <a href="index.php?action=logout">🚪 Đăng xuất</a>
            <div class="ml-nav-title">Dataset</div>
            <div class="ml-datasets">
                <span class="ml-pill">🔵 B-dataset</span>
                <span class="ml-pill">🟢 C-dataset</span>
                <span class="ml-pill">🟣 F-dataset</span>
            </div>
        </div>
    </aside>

    <main class="ml-main">
        <div class="ml-topbar">
            <div>
                <button class="ml-btn dark ml-mobile-menu" data-menu-btn>☰ Menu</button>
                <h2>Tổng quan</h2>
                <p>Chào mừng bạn đến với MedLink AI</p>
            </div>
            <div class="ml-user"><div class="ml-avatar"><?= e(substr($fullName, 0, 1)) ?></div><div><b><?= e($fullName) ?></b><br><small><?= e($role) ?></small></div></div>
        </div>

        <section class="ml-hero">
            <span class="ml-badge">✨ AI-Powered</span>
            <h3>Hệ thống dự đoán thuốc - bệnh thông minh</h3>
            <p>Sử dụng AMDGT và dữ liệu B/C/F dataset để phân tích mối quan hệ giữa thuốc và bệnh, hỗ trợ nghiên cứu y khoa hiệu quả hơn.</p>
            <div class="ml-actions">
                <a href="#predict-box" class="ml-btn">Bắt đầu dự đoán →</a>
                <a href="index.php?action=history" class="ml-btn ghost">Xem lịch sử</a>
            </div>
        </section>

        <section class="ml-grid cards">
            <div class="ml-card"><div class="ml-card-head"><small>Tổng dự đoán</small><span class="ml-stat-icon">📊</span></div><div class="ml-stat-number"><?= number_format($stats['totalPredictions']) ?></div><small>Lượt tra cứu</small></div>
            <div class="ml-card"><div class="ml-card-head"><small>Tổng thuốc</small><span class="ml-stat-icon">💊</span></div><div class="ml-stat-number"><?= number_format($stats['totalDrugs']) ?></div><small>Trong hệ thống</small></div>
            <div class="ml-card"><div class="ml-card-head"><small>Tổng bệnh</small><span class="ml-stat-icon">🧬</span></div><div class="ml-stat-number"><?= number_format($stats['totalDiseases']) ?></div><small>Trong dataset</small></div>
            <div class="ml-card"><div class="ml-card-head"><small>Độ tin cậy TB</small><span class="ml-stat-icon">⚡</span></div><div class="ml-stat-number"><?= e($stats['averageAccuracy']) ?>%</div><small>Điểm mô hình</small></div>
        </section>

        <section class="ml-card" style="margin-bottom:22px">
            <div class="ml-card-head"><div><h4>Phân bổ Dataset</h4><small>Số lượng thuốc và bệnh trong mỗi dataset</small></div></div>
            <div class="ml-dataset-grid">
                <?php foreach ($stats['datasetStats'] as $name => $s): ?>
                    <div class="ml-dataset-box">
                        <span class="ml-pill" style="color:#111827;background:#eef7ff;border-color:#dbeafe"><?= e($name) ?></span>
                        <div class="ml-row" style="margin-top:14px">
                            <div><b><?= number_format($s['drugs']) ?></b><br><small>Thuốc</small></div>
                            <div><b><?= number_format($s['diseases']) ?></b><br><small>Bệnh</small></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="ml-grid two" id="predict-box">
            <div class="ml-card">
                <div class="ml-card-head"><div><h4>🔎 Dự đoán thuốc - bệnh</h4><small>Chọn hướng tìm kiếm, dataset và số kết quả top-k</small></div></div>
                <form class="ml-form" data-predict-form method="POST" action="index.php?action=predict">
                    <div class="ml-row">
                        <div class="ml-field">
                            <label>Dataset</label>
                            <select name="dataset">
                                <option value="B-dataset">B-dataset</option>
                                <option value="C-dataset">C-dataset</option>
                                <option value="F-dataset">F-dataset</option>
                                <option value="all">Tất cả dataset</option>
                            </select>
                        </div>
                        <div class="ml-field">
                            <label>Kiểu tìm kiếm</label>
                            <select name="input_type">
                                <option value="drug">Thuốc → Bệnh</option>
                                <option value="disease">Bệnh → Thuốc</option>
                                <option value="symptom">Triệu chứng AI</option>
                            </select>
                        </div>
                    </div>
                    <div class="ml-field">
                        <label>Từ khóa</label>
                        <input name="keyword" required placeholder="Ví dụ: Acetaminophen, Aspirin, Diabetes, đau đầu sốt...">
                    </div>
                    <div class="ml-field">
                        <label>Số kết quả hiển thị</label>
                        <div class="ml-slider-row">
                            <input type="range" name="top_k" min="3" max="30" value="10" data-topk-slider>
                            <span class="ml-count" data-topk-value>10</span>
                        </div>
                    </div>
                    <button class="ml-btn primary" type="submit">Dự đoán ngay →</button>
                </form>
            </div>

            <div class="ml-card">
                <div class="ml-card-head"><div><h4>✨ Sinh thuốc mới</h4><small>Gọi API Flask /generate_drug để sinh SMILES</small></div></div>
                <form class="ml-form" method="POST" action="index.php?action=generate_drug">
                    <div class="ml-field"><label>Số lượng SMILES</label><input type="number" name="num_samples" min="1" max="50" value="10"></div>
                    <div class="ml-field"><label>Điều kiện bệnh / mô tả</label><input name="condition" placeholder="Ví dụ: cancer, diabetes, inflammation..."></div>
                    <button class="ml-btn dark" type="submit">Sinh thuốc mới</button>
                </form>
                <p class="ml-footer-note">Gợi ý: sau khi sinh SMILES, nên lọc hợp lệ bằng RDKit rồi chấm điểm lại bằng AMDGT.</p>
            </div>
        </section>

        <section class="ml-card" style="margin-top:22px">
            <div class="ml-card-head"><div><h4>📈 Hoạt động gần đây</h4><small>Các tra cứu mới nhất của bạn</small></div><a class="ml-btn" href="index.php?action=history">Xem tất cả</a></div>
            <?php if (empty($history)): ?>
                <div class="ml-muted">Chưa có hoạt động nào.</div>
            <?php else: ?>
                <table class="ml-result-table"><thead><tr><th>Từ khóa</th><th>Kiểu</th><th>Dataset</th><th>Thời gian</th></tr></thead><tbody>
                <?php foreach (array_slice($history, 0, 5) as $item): ?>
                    <tr><td><b><?= e($item['keyword'] ?? $item['input_name'] ?? '') ?></b></td><td><?= e($item['input_type'] ?? $item['type'] ?? '') ?></td><td><?= e($item['dataset'] ?? '') ?></td><td><?= e($item['created_at'] ?? $item['time'] ?? '') ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </section>
    </main>
</div>
<script src="assets/js/medlink-dashboard.js"></script>
</body>
</html>
