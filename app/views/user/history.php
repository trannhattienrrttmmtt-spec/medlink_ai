<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('e')) { function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
$fullName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Người dùng';

function extractResultNames($json) {
    if (empty($json)) return '—';
    $data = json_decode($json, true);
    if (!$data) return '—';
    $results = $data['results'] ?? [];
    if (empty($results)) return '—';
    $names = [];
    foreach (array_slice($results, 0, 3) as $r) {
        $name = $r['name'] ?? $r['drug_name'] ?? $r['disease_name'] ?? '';
        if ($name) $names[] = $name;
    }
    if (empty($names)) return '—';
    $out = implode(', ', $names);
    if (count($results) > 3) $out .= ' (+' . (count($results) - 3) . ')';
    return $out;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử tra cứu - MedLink AI</title>
    <script>document.documentElement.setAttribute('data-theme',localStorage.getItem('ml-theme')||'light')</script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/medlink-dashboard.css">
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
            <a class="active" href="index.php?action=history"><i class="bi bi-clock-history"></i> Lịch sử</a>
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
                <h2>Lịch sử nghiên cứu</h2>
                <p>Nhật ký tìm kiếm và dự đoán của <b><?= e($fullName) ?></b></p>
            </div>
            <div style="display:flex; gap:10px;">
                <?php if (!empty($history)): ?>
                    <a class="ml-btn" href="index.php?action=delete_all_history" onclick="return confirm('Bạn có chắc chắn muốn xóa toàn bộ lịch sử nghiên cứu? Thao tác này không thể hoàn tác.');" style="border-color: var(--red); color: var(--red); background: rgba(239, 68, 68, 0.05);"><i class="bi bi-trash3-fill"></i> Xóa tất cả</a>
                <?php endif; ?>
                <a class="ml-btn" href="index.php?action=dashboard"><i class="bi bi-arrow-left"></i> Quay lại</a>
            </div>
        </div>

        <!-- Flash messages -->
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="ml-alert success">
                <i class="bi bi-check-circle-fill"></i>
                <span><?= e($_SESSION['flash_success']) ?></span>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="ml-alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?= e($_SESSION['flash_error']) ?></span>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <section class="ml-card">
            <div class="ml-card-head" style="border-bottom: 1px solid var(--line); padding-bottom: 15px; margin-bottom: 20px;">
                <div>
                    <h4><i class="bi bi-clock-history" style="color: var(--primary);"></i> Nhật ký hoạt động chi tiết</h4>
                    <small>Xem lại các lần dự đoán mô hình và sinh hoạt chất tự động.</small>
                </div>
            </div>

            <?php if (empty($history)): ?>
                <div class="empty-state">
                    <div class="ico"><i class="bi bi-inbox-fill"></i></div>
                    <div>Nhật ký nghiên cứu trống</div>
                    <small>Hãy chạy các mô hình phân tích để tạo lịch sử lưu trữ dữ liệu.</small>
                </div>
            <?php else: ?>
                <table class="ml-result-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Từ khóa phân tích</th>
                            <th>Loại phân tích</th>
                            <th>Tập dữ liệu</th>
                            <th style="max-width: 320px;">Hoạt chất gợi ý nổi bật (Top 3)</th>
                            <th>Thời gian</th>
                            <th style="width: 80px; text-align: center;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $i => $h):
                            $resultText = extractResultNames($h['result_summary'] ?? $h['result'] ?? '');
                            $inputType = $h['input_type'] ?? 'drug';
                            
                            // Setup tag colors
                            $tagBg = $inputType === 'drug' ? 'rgba(99, 102, 241, 0.1)' : ($inputType === 'disease_protein' ? 'rgba(139, 92, 246, 0.1)' : 'rgba(16, 185, 129, 0.1)');
                            $tagColor = $inputType === 'drug' ? '#4f46e5' : ($inputType === 'disease_protein' ? '#7c3aed' : '#059669');
                            $tagIcon = $inputType === 'drug' ? 'capsule' : ($inputType === 'disease_protein' ? 'dna' : 'activity');
                            $tagLabel = $inputType === 'drug' ? 'Thuốc → Bệnh' : ($inputType === 'disease_protein' ? 'Protein → Thuốc' : 'Bệnh → Thuốc');
                        ?>
                            <tr class="fade-up" style="animation-delay: <?= $i * 20 ?>ms">
                                <td style="font-weight: 800; color: var(--text-dim);"><?= $i+1 ?></td>
                                <td><b style="font-size: 14.5px; color: var(--text);"><?= e($h['keyword'] ?? '') ?></b></td>
                                <td>
                                    <span style="display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:8px; font-size:11.5px; font-weight:700; background: <?= $tagBg ?>; color: <?= $tagColor ?>;">
                                        <i class="bi bi-<?= $tagIcon ?>"></i> <?= e($tagLabel) ?>
                                    </span>
                                </td>
                                <td><b><?= e($h['dataset'] ?? 'B-dataset') ?></b></td>
                                <td style="max-width: 320px; font-weight: 500; color: var(--text-muted); font-size: 13px;"><?= e($resultText) ?></td>
                                <td style="font-size: 12.5px; color: var(--text-muted); font-weight: 500;"><?= e($h['created_at'] ?? '') ?></td>
                                <td style="text-align: center;">
                                    <a class="ml-btn" href="index.php?action=delete_history&id=<?= e($h['id']) ?>" onclick="return confirm('Bạn có muốn xóa mục lịch sử này?');" style="padding: 6px 10px; border-radius: 8px; border-color: transparent; color: var(--red); background: rgba(239, 68, 68, 0.05); font-size: 13px;" title="Xóa dòng này">
                                        <i class="bi bi-trash3"></i>
                                    </a>
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
