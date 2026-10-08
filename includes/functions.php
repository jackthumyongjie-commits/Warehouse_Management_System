<?php
declare(strict_types=1);

/**
 * Escape HTML output to prevent XSS.
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Generate relative URL based on BASE_PATH.
 */
function url(string $path = ''): string {
    $cleanPath = '/' . ltrim($path, '/');
    return BASE_PATH . $cleanPath;
}

/**
 * Redirect safely to a given path relative to BASE_PATH.
 */
function redirect(string $path): void {
    $targetUrl = url($path);
    header("Location: {$targetUrl}");
    exit;
}

/**
 * CSRF Token Generator
 */
function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF HTML Input Field Generator
 */
function csrf_field(): string {
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Verify CSRF Token on POST requests
 */
function verify_csrf_token(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        
        if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
            set_flash_message('danger', 'Invalid security token (CSRF). Please try submitting the form again.');
            $referer = $_SERVER['HTTP_REFERER'] ?? url('/index.php');
            header("Location: {$referer}");
            exit;
        }
    }
}

/**
 * Set flash message in session.
 */
function set_flash_message(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash messages from session.
 */
function get_flash_messages(): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Format currency or numbers safely.
 */
function format_number(int $number): string {
    return number_format($number);
}

/**
 * Format date time display.
 */
function format_datetime(?string $datetime): string {
    if (!$datetime) return 'N/A';
    return date('M d, Y H:i', strtotime($datetime));
}

/**
 * Login Rate Limiter (Brute-force protection)
 */
function check_login_rate_limit(string $username): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $attemptsKey = 'login_attempts_' . md5($username);
    $lockoutKey = 'login_lockout_' . md5($username);
    
    $lockoutTime = $_SESSION[$lockoutKey] ?? 0;
    if ($lockoutTime > time()) {
        return false;
    }
    
    return true;
}

function record_failed_login(string $username): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $attemptsKey = 'login_attempts_' . md5($username);
    $lockoutKey = 'login_lockout_' . md5($username);
    
    $attempts = ($_SESSION[$attemptsKey] ?? 0) + 1;
    $_SESSION[$attemptsKey] = $attempts;
    
    if ($attempts >= 5) {
        $_SESSION[$lockoutKey] = time() + 300; // 5 minutes lockout
        $_SESSION[$attemptsKey] = 0;
    }
}

function reset_failed_login(string $username): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['login_attempts_' . md5($username)]);
    unset($_SESSION['login_lockout_' . md5($username)]);
}
