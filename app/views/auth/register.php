<?php if (session_status() === PHP_SESSION_NONE) session_start(); function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - MedLink AI</title>
    <link rel="stylesheet" href="assets/css/medlink-dashboard.css">
</head>
<body class="ml-auth-body">
<div class="ml-auth-card">
    <div class="ml-brand center"><div class="ml-logo">⚕</div><div><h1>MedLink AI</h1><p>Tạo tài khoản mới</p></div></div>
    <?php if (!empty($error)): ?><div class="ml-alert"><?= e($error) ?></div><?php endif; ?>
    <form class="ml-form" method="POST" action="index.php?action=do_register">
        <div class="ml-field"><label>Username</label><input name="username" required></div>
        <div class="ml-field"><label>Họ tên</label><input name="full_name"></div>
        <div class="ml-field"><label>Email</label><input type="email" name="email"></div>
        <div class="ml-field"><label>Mật khẩu</label><input type="password" name="password" required></div>
        <button class="ml-btn primary full" type="submit">Đăng ký</button>
    </form>
    <p class="ml-footer-note">Đã có tài khoản? <a href="index.php?action=login">Đăng nhập</a></p>
</div>
</body>
</html>
