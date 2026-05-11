<?php if (session_status() === PHP_SESSION_NONE) session_start(); function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - MedLink AI</title>
    <link rel="stylesheet" href="assets/css/medlink-dashboard.css">
</head>
<body class="ml-auth-body">
<div class="ml-auth-card">
    <div class="ml-brand center"><div class="ml-logo">⚕</div><div><h1>MedLink AI</h1><p>Đăng nhập hệ thống</p></div></div>
    <?php if (!empty($error)): ?><div class="ml-alert"><?= e($error) ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="ml-alert success"><?= e($success) ?></div><?php endif; ?>
    <form class="ml-form" method="POST" action="index.php?action=do_login">
        <div class="ml-field"><label>Tài khoản hoặc email</label><input name="username" required autofocus placeholder="admin hoặc user"></div>
        <div class="ml-field"><label>Mật khẩu</label><input type="password" name="password" required placeholder="Nhập mật khẩu"></div>
        <button class="ml-btn primary full" type="submit">Đăng nhập</button>
    </form>
    <p class="ml-footer-note">Chưa có tài khoản? <a href="index.php?action=register">Đăng ký</a></p>
    <p class="ml-footer-note">Mặc định nếu import database.sql: <b>admin / 123456</b></p>
</div>
</body>
</html>
