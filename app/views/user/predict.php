<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('e')) { function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
$fullName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Người dùng';
$results = $results ?? [];
$graph = $graph ?? ['nodes'=>[],'edges'=>[]];
$displayKeyword = $displayKeyword ?? '';
$input_type = $input_type ?? 'drug';
$selected_dataset = $selected_dataset ?? 'B-dataset';
$api = 'http://127.0.0.1:5000';
$molImg = function($smi) use ($api) {
    return $smi ? $api . '/render_smiles?smi=' . urlencode($smi) . '&w=250&h=250' : '';
};
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả dự đoán - MedLink AI</title>
    <script>document.documentElement.setAttribute('data-theme',localStorage.getItem('ml-theme')||'light')</script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/medlink-dashboard.css">
    <style>
        .ai-mol {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            border: 1.5px solid var(--line);
            object-fit: contain;
            background: linear-gradient(135deg, #fafbff, #fff);
            padding: 6px;
            cursor: zoom-in;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        .ai-mol:hover {
            transform: scale(1.1) translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.18);
            border-color: var(--primary);
        }
        .smiles {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--text-muted);
            word-break: break-all;
            max-width: 250px;
            display: inline-block;
        }
        .ai-pct {
            font-weight: 800;
            font-size: 14.5px;
            color: var(--primary);
        }
    </style>
</head>
<body>
<div class="ml-app">
    <aside class="ml-sidebar">
        <div class="ml-brand">
            <div class="ml-logo"><i class="bi bi-capsule"></i></div>
            <div>
                <h1>MedLink AI</h1>
                <p>Drug-Disease Prediction</p>
            </div>
        </div>
        <div class="ml-nav-title">Điều hướng</div>
        <div class="ml-nav">
            <a href="index.php?action=dashboard"><i class="bi bi-grid-1x2-fill"></i> Tổng quan</a>
            <a href="index.php?action=dashboard#ai-section"><i class="bi bi-search"></i> Dự đoán AI</a>
            <a href="index.php?action=dashboard#chart-section"><i class="bi bi-bar-chart-fill"></i> Biểu đồ</a>
            <a href="index.php?action=dashboard#graph-section"><i class="bi bi-diagram-3-fill"></i> Mạng liên kết</a>
            <a href="index.php?action=dashboard#gen-section"><i class="bi bi-magic"></i> Sinh thuốc</a>
            <a href="index.php?action=history"><i class="bi bi-clock-history"></i> Lịch sử</a>
            <a href="index.php?action=logout"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
        </div>
        <div class="ml-nav-title">Datasets</div>
        <div class="ml-datasets">
            <span class="ml-pill"><span class="ai-dot" style="background:#6366f1"></span>B-dataset</span>
            <span class="ml-pill"><span class="ai-dot" style="background:#10b981"></span>C-dataset</span>
            <span class="ml-pill"><span class="ai-dot" style="background:#ec4899"></span>F-dataset</span>
        </div>
    </aside>

    <main class="ml-main">
        <div class="ml-topbar">
            <div>
                <h2>Kết quả phân tích</h2>
                <p>Loại phân tích: <span style="text-transform: capitalize; font-weight: 700; color: var(--primary);"><?= e(str_replace('_', ' + ', $input_type)) ?></span> · Nguồn dữ liệu: <b><?= e($selected_dataset) ?></b></p>
            </div>
            <a class="ml-btn" href="index.php?action=dashboard" style="border-radius: 12px;"><i class="bi bi-arrow-left"></i> Quay lại Dashboard</a>
        </div>

        <section class="ml-card" style="margin-bottom: 24px;">
            <div class="ml-card-head" style="border-bottom: 1px solid var(--line); padding-bottom: 15px; margin-bottom: 20px;">
                <div>
                    <h4 style="font-size: 18px;"><i class="bi bi-clipboard2-pulse-fill" style="color:var(--primary);"></i> Kết quả cho từ khóa: <span style="color: var(--primary); font-weight: 800;"><?= e($displayKeyword) ?></span></h4>
                    <small>Bảng thống kê xếp hạng mức độ tương thích hoạt chất và bệnh học</small>
                </div>
            </div>

            <?php if (empty($results)): ?>
                <div class="empty-state">
                    <div class="ico"><i class="bi bi-clipboard2-minus"></i></div>
                    <div>Không tìm thấy kết quả phù hợp</div>
                    <small>Không tìm thấy hoạt chất nào khớp với các chỉ số protein đã nhập.</small>
                </div>
            <?php else: ?>
                <table class="ml-result-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;"># Xếp hạng</th>
                            <th>Tên hoạt chất / Chỉ dấu</th>
                            <th>Mức độ tin cậy</th>
                            <th>Nguồn dự đoán</th>
                            <th>Cấu trúc 2D</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $i => $r): ?>
                            <tr>
                                <td style="font-weight: 800; color: var(--primary); font-size: 15px;"><?= $i+1 ?></td>
                                <td>
                                    <b style="font-size: 15px; color: var(--text);"><?= e($r['name'] ?? '') ?></b>
                                    <?php if(!empty($r['smiles'])): ?>
                                        <br><code class="smiles"><?= e($r['smiles']) ?></code>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="ai-pct"><?= round(($r['score'] ?? 0) * 100) ?>%</span>
                                    <div class="ml-score" style="display:block; margin-top: 6px;">
                                        <span style="width: <?= round(($r['score'] ?? 0) * 100) ?>%;"></span>
                                    </div>
                                </td>
                                <td>
                                    <span style="display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:8px; font-size:11.5px; font-weight:700; background: var(--primary-light); color: var(--primary);">
                                        <i class="bi bi-info-circle-fill"></i> <?= e($r['source'] ?? $r['compare_group'] ?? 'Mô hình AI') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if(!empty($r['smiles'])): ?>
                                        <a href="https://molview.org/?smiles=<?= urlencode($r['smiles']) ?>" target="_blank" title="Xem cấu trúc 3D trên MolView">
                                            <img class="ai-mol" src="<?= $molImg($r['smiles']) ?>" onerror="this.outerHTML='<small style=\'color:var(--text-dim)\'>N/A</small>'">
                                        </a>
                                    <?php else: ?>
                                        <span style="color:var(--text-dim); font-size: 12.5px;">— Không có cấu trúc —</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
