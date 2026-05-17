<?php if (session_status() === PHP_SESSION_NONE) session_start(); if (!function_exists('e')) { function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } } $fullName=$_SESSION['full_name']??$_SESSION['username']??'Người dùng';

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
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Lịch sử - MedLink AI</title>
<script>document.documentElement.setAttribute('data-theme',localStorage.getItem('ml-theme')||'light')</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/medlink-dashboard.css">
</head>
<body>
<div class="ml-app">
<aside class="ml-sidebar">
    <div class="ml-brand"><div class="ml-logo"><i class="bi bi-capsule"></i></div><div><h1>MedLink AI</h1><p>History</p></div></div>
    <div class="ml-nav">
        <a href="index.php?action=dashboard"><i class="bi bi-grid-1x2-fill"></i> Tổng quan</a>
        <a class="active" href="index.php?action=history"><i class="bi bi-clock-history"></i> Lịch sử</a>
        <a href="index.php?action=logout"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
    </div>
</aside>
<main class="ml-main">
<div class="ml-topbar"><div><h2>Lịch sử tra cứu</h2><p><?= e($fullName) ?></p></div><a class="ml-btn" href="index.php?action=dashboard"><i class="bi bi-arrow-left"></i> Quay lại</a></div>
<section class="ml-card">
<div class="ml-card-head"><div><h4><i class="bi bi-clock-history"></i> Danh sách lịch sử</h4><small>Các lần dự đoán và sinh thuốc</small></div></div>
<?php if (empty($history)): ?>
<div style="text-align:center;padding:40px;color:var(--text-muted)"><i class="bi bi-inbox" style="font-size:48px;display:block;margin-bottom:8px;color:var(--text-dim)"></i>Chưa có lịch sử.</div>
<?php else: ?>
<table class="ml-result-table">
<thead><tr><th>#</th><th>Từ khóa</th><th>Kiểu</th><th>Dataset</th><th>Kết quả dự đoán</th><th>Thời gian</th></tr></thead>
<tbody>
<?php foreach ($history as $i=>$h):
    $resultText = extractResultNames($h['result_summary'] ?? $h['result'] ?? '');
?>
<tr>
    <td><?= $i+1 ?></td>
    <td><b><?= e($h['keyword']??'') ?></b></td>
    <td><span style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;font-size:11px;font-weight:600;background:<?= ($h['input_type']??'')==='drug'?'#eef2ff':'#d1fae5' ?>;color:<?= ($h['input_type']??'')==='drug'?'#4338ca':'#065f46' ?>"><i class="bi bi-<?= ($h['input_type']??'')==='drug'?'capsule':'activity' ?>"></i> <?= e($h['input_type']??'') ?></span></td>
    <td><?= e($h['dataset']??'B-dataset') ?></td>
    <td style="max-width:250px"><span style="font-size:12px;color:var(--text)"><?= e($resultText) ?></span></td>
    <td style="font-size:12px;color:var(--text-muted)"><?= e($h['created_at']??'') ?></td>
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
