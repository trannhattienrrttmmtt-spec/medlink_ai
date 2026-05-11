<?php
if (session_status() === PHP_SESSION_NONE) session_start();
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$fullName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Người dùng';
$smilesList = $smilesList ?? $generated ?? $results ?? [];
if (isset($apiResult['smiles']) && is_array($apiResult['smiles'])) $smilesList = $apiResult['smiles'];
if (isset($apiResult['generated']) && is_array($apiResult['generated'])) $smilesList = $apiResult['generated'];
?>
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Sinh thuốc mới - MedLink AI</title><link rel="stylesheet" href="assets/css/medlink-dashboard.css"></head>
<body>
<div class="ml-app">
    <aside class="ml-sidebar"><div class="ml-brand"><div class="ml-logo">⚕</div><div><h1>MedLink AI</h1><p>AI Drug Generator</p></div></div><div class="ml-nav"><a href="index.php?action=dashboard">🏠 Tổng quan</a><a class="active" href="index.php?action=dashboard#predict-box">✨ Sinh thuốc</a><a href="index.php?action=history">🕘 Lịch sử</a><a href="index.php?action=logout">🚪 Đăng xuất</a></div></aside>
    <main class="ml-main">
        <div class="ml-topbar"><div><button class="ml-btn dark ml-mobile-menu" data-menu-btn>☰ Menu</button><h2>SMILES được sinh mới</h2><p>Kết quả từ Flask API /generate_drug</p></div><div class="ml-user"><div class="ml-avatar"><?= e(mb_substr($fullName,0,1)) ?></div><div><b><?= e($fullName) ?></b><br><small>MedLink AI</small></div></div></div>
        <div class="ml-card">
            <div class="ml-card-head"><div><h4>✨ Danh sách SMILES</h4><small>Nên lọc hợp lệ bằng RDKit trước khi dùng để chấm điểm AMDGT.</small></div><a href="index.php?action=dashboard#predict-box" class="ml-btn">Quay lại</a></div>
            <?php if (empty($smilesList)): ?>
                <div class="ml-alert">Chưa có SMILES. Kiểm tra API /generate_drug hoặc controller PHP.</div>
            <?php else: ?>
                <table class="ml-result-table"><thead><tr><th>#</th><th>SMILES</th><th>Score</th><th>Trạng thái</th></tr></thead><tbody>
                <?php foreach ($smilesList as $i => $item):
                    $smiles = is_array($item) ? ($item['smiles'] ?? $item['SMILES'] ?? json_encode($item, JSON_UNESCAPED_UNICODE)) : $item;
                    $score = is_array($item) ? ($item['score'] ?? '') : '';
                    $valid = is_array($item) ? ($item['valid'] ?? '') : '';
                ?>
                    <tr><td><?= $i+1 ?></td><td><div class="ml-generated"><?= e($smiles) ?></div></td><td><?= e($score) ?></td><td><?= $valid === '' ? 'Chưa lọc' : e($valid ? 'Hợp lệ' : 'Không hợp lệ') ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="assets/js/medlink-dashboard.js"></script>
</body>
</html>
