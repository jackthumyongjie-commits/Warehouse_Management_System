<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    $user = current_user();
    if ($user['role'] === 'admin') {
        redirect('/admin/dashboard.php');
    } else {
        redirect('/user/dashboard.php');
    }
}

$error = '';
$usernameInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = trim($_POST['username'] ?? '');
    $passwordInput = $_POST['password'] ?? '';

    $result = login_user($usernameInput, $passwordInput);

    if ($result['success']) {
        set_flash_message('success', 'Welcome back to Warehouse Management System!');
        if ($result['role'] === 'admin') {
            redirect('/admin/dashboard.php');
        } else {
            redirect('/user/dashboard.php');
        }
    } else {
        $error = $result['message'];
    }
}

$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="login-body">
    <div class="login-card">
        <div class="login-header">
            <h1 class="login-title">📦 WMS Portal</h1>
            <p class="login-subtitle">Sign in to your warehouse account</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php
        $flashMessages = get_flash_messages();
        foreach ($flashMessages as $msg): ?>
            <div class="alert alert-<?= e($msg['type']) ?>">
                <?= e($msg['message']) ?>
            </div>
        <?php endforeach; ?>

        <form action="<?= url('login.php') ?>" method="POST" autocomplete="off">
            <?= csrf_field() ?>
            
            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-control" 
                    value="<?= e($usernameInput) ?>" 
                    placeholder="Enter your username" 
                    required 
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-control" 
                    placeholder="Enter your password" 
                    required
                >
            </div>

            <div class="form-group" style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    Sign In
                </button>
            </div>
        </form>

        <div class="login-accounts">
            <p class="login-accounts-label">Click an account to fill in the form</p>
            <button type="button" class="login-account js-fill-account" data-username="admin" data-password="admin123">
                <span class="login-account-role">Admin</span>
                <span class="login-account-cred">admin / admin123</span>
            </button>
            <button type="button" class="login-account js-fill-account" data-username="demo" data-password="user123">
                <span class="login-account-role">User</span>
                <span class="login-account-cred">demo / user123</span>
            </button>
        </div>
    </div>
    <script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
