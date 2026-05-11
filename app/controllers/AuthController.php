<?php
require_once __DIR__ . '/../models/UserModel.php';

class AuthController
{
    private $userModel;
    public function __construct() { $this->userModel = new UserModel(); }

    public function login()
    {
        if (isset($_SESSION['user_id'])) { header('Location: index.php?action=dashboard'); exit; }
        include __DIR__ . '/../views/auth/login.php';
    }

    public function doLogin()
    {
        $login = trim($_POST['username'] ?? $_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $user = $this->userModel->findByUsernameOrEmail($login);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'] ?? $login;
            $_SESSION['full_name'] = $user['full_name'] ?? ($user['username'] ?? $login);
            $_SESSION['role'] = $user['role'] ?? 'user';
            header('Location: index.php?action=dashboard'); exit;
        }
        $error = 'Sai tài khoản hoặc mật khẩu.';
        include __DIR__ . '/../views/auth/login.php';
    }

    public function do_login() { return $this->doLogin(); }

    public function register()
    {
        include __DIR__ . '/../views/auth/register.php';
    }

    public function doRegister()
    {
        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? $username);
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($username === '' || $password === '') {
            $error = 'Vui lòng nhập đầy đủ tài khoản và mật khẩu.';
            include __DIR__ . '/../views/auth/register.php';
            return;
        }
        try {
            $ok = $this->userModel->create($username, $fullName, $email, $password, 'user');
            if ($ok) { $success = 'Đăng ký thành công. Bạn có thể đăng nhập.'; include __DIR__ . '/../views/auth/login.php'; return; }
            $error = 'Không thể đăng ký. Kiểm tra database users.';
        } catch (Throwable $e) { $error = 'Lỗi đăng ký: ' . $e->getMessage(); }
        include __DIR__ . '/../views/auth/register.php';
    }

    public function do_register() { return $this->doRegister(); }

    public function logout()
    {
        session_destroy();
        header('Location: index.php?action=login'); exit;
    }
}
