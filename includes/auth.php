<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Configure session security options prior to session_start
if (session_status() === PHP_SESSION_NONE) {
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    $cookieDomain = '';
    if ($host !== '' && !in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true) && filter_var($host, FILTER_VALIDATE_IP) === false) {
        $cookieDomain = $host;
    }
    
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => BASE_PATH ?: '/',
        'domain'   => $cookieDomain,
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
}

/**
 * Check if a user is currently logged in.
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current authenticated user array from session.
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'],
        'full_name' => $_SESSION['user_fullname'] ?? '',
        'username'  => $_SESSION['user_username'] ?? '',
        'role'      => $_SESSION['user_role'] ?? ''
    ];
}

/**
 * Require user to be logged in, otherwise redirect to login page.
 */
function require_login(): void {
    if (!is_logged_in()) {
        set_flash_message('warning', 'Please login to access this page.');
        redirect('/login.php');
    }
}

/**
 * Check if the logged-in user is an admin.
 */
function is_admin(): bool {
    $user = current_user();
    return $user && $user['role'] === 'admin';
}

/**
 * Require a specific role (e.g. 'admin' or 'user').
 * If user does not have permission, redirect to appropriate default dashboard.
 */
function require_role(string $requiredRole): void {
    require_login();
    $user = current_user();

    if ($requiredRole === 'admin' && $user['role'] !== 'admin') {
        set_flash_message('danger', 'Access denied. You do not have permission to view that page.');
        redirect('/user/dashboard.php');
    }
}

/**
 * Authenticate user credentials and start session.
 */
function login_user(string $username, string $password): array {
    $username = trim($username);
    
    if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'Please fill in all required fields.'];
    }

    if (!check_login_rate_limit($username)) {
        return ['success' => false, 'message' => 'Too many failed attempts. Account temporarily locked for 5 minutes.'];
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT id, full_name, username, password_hash, role, is_active FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_failed_login($username);
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    if ((int)$user['is_active'] !== 1) {
        return ['success' => false, 'message' => 'Your account has been deactivated. Please contact an administrator.'];
    }

    // Reset failed login counter on success
    reset_failed_login($username);

    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);

    $_SESSION['user_id']       = (int)$user['id'];
    $_SESSION['user_fullname'] = $user['full_name'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['user_role']     = $user['role'];

    return ['success' => true, 'role' => $user['role']];
}

/**
 * Log out user and destroy session cleanly.
 */
function logout_user(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}
