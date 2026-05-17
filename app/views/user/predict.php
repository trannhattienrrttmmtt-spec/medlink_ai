<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('e')) { function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
$fullName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Người dùng';
$results = $results ?? [];
$graph = $graph ?? ['nodes'=>[],'edges'=>[]];
$displayKeyword = $displayKeyword ?? '';
$input_type = $input_type ?? 'drug';
$selected_dataset = $selected_dataset ?? 'B-dataset';
?>
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kết quả - MedLink AI</title><link rel="stylesheet" href="assets/css/medlink-dashboard.css"></head>
<body>
<div class="ml-app">
<aside class="ml-sidebar"><div class="ml-brand"><div class="ml-logo">⚕</div><div><h1>MedLink AI</h1><p>Results</p></div></div><div class="ml-nav"><a href="index.php?action=dashboard">🏠 Tổng quan</a><a href="index.php?action=history">🕘 Lịch sử</a><a href="index.php?action=logout">🚪 Đăng xuất</a></div></aside>
<main class="ml-main">
<div class="ml-topbar"><div><h2>Kết quả dự đoán</h2><p><?= e($input_type) ?>: <b><?= e($displayKeyword) ?></b> — <?= e($selected_dataset) ?></p></div><a class="ml-btn" href="index.php?action=dashboard">← Quay lại</a></div>
<section class="ml-card">
<?php if (empty($results)): ?>
<div class="ml-muted">Không có kết quả.</div>
<?php else: ?>
<table class="ml-result-table"><thead><tr><th>#</th><th>Tên</th><th>Score</th><th>Nguồn</th></tr></thead><tbody>
<?php foreach ($results as $i => $r): ?>
<tr><td><?= $i+1 ?></td><td><b><?= e($r['name'] ?? '') ?></b><?php if(!empty($r['smiles'])): ?><br><code style="font-size:11px;color:#6b7280"><?= e($r['smiles']) ?></code><?php endif; ?></td><td><?= round(($r['score'] ?? 0) * 100) ?>%</td><td><?= e($r['source'] ?? $r['compare_group'] ?? '') ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<?php endif; ?>
</section>
</main>
</div>
</body>
</html>
