<?php if (session_status() === PHP_SESSION_NONE) session_start(); function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } $fullName=$_SESSION['full_name']??$_SESSION['username']??'Người dùng'; ?>
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Lịch sử - MedLink AI</title><link rel="stylesheet" href="assets/css/medlink-dashboard.css"></head>
<body>
<div class="ml-app">
<aside class="ml-sidebar"><div class="ml-brand"><div class="ml-logo">⚕</div><div><h1>MedLink AI</h1><p>History</p></div></div><div class="ml-nav"><a href="index.php?action=dashboard">🏠 Tổng quan</a><a class="active" href="index.php?action=history">🕘 Lịch sử</a><a href="index.php?action=logout">🚪 Đăng xuất</a></div></aside>
<main class="ml-main"><div class="ml-topbar"><div><h2>Lịch sử tra cứu</h2><p><?= e($fullName) ?></p></div><a class="ml-btn" href="index.php?action=dashboard">Quay lại</a></div>
<section class="ml-card"><div class="ml-card-head"><div><h4>Danh sách lịch sử</h4><small>Các lần dự đoán và sinh thuốc</small></div></div>
<?php if (empty($history)): ?><div class="ml-muted">Chưa có lịch sử.</div><?php else: ?>
<table class="ml-result-table"><thead><tr><th>#</th><th>Từ khóa</th><th>Kiểu</th><th>Dataset</th><th>Kết quả</th><th>Thời gian</th></tr></thead><tbody>
<?php foreach ($history as $i=>$h): ?><tr><td><?= $i+1 ?></td><td><b><?= e($h['keyword']??$h['input_name']??'') ?></b></td><td><?= e($h['input_type']??$h['type']??'') ?></td><td><?= e($h['dataset']??'') ?></td><td><code><?= e(substr($h['result']??$h['result_name']??'',0,80)) ?></code></td><td><?= e($h['created_at']??$h['time']??'') ?></td></tr><?php endforeach; ?>
</tbody></table><?php endif; ?></section></main></div><script src="assets/js/medlink-dashboard.js"></script></body></html>
