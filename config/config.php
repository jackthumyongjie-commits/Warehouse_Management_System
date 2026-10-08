<?php
declare(strict_types=1);

// Application Configuration

define('APP_NAME', 'Warehouse Management System');

// Automatically calculate base path relative to domain root, e.g., '/project/warehouse_management_system'
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$dirName = dirname($scriptName);
$basePath = str_replace('\\', '/', $dirName);
if ($basePath === '/' || $basePath === '.') {
    $basePath = '';
}
// Normalize base path for subfolders like /admin, /user, /api
$basePath = preg_replace('#/(admin|user|api|includes|config|database)$#', '', $basePath);
define('BASE_PATH', rtrim($basePath, '/'));

// Database account lives in config/local.php (not committed).
// Copy config/local.example.php to config/local.php on a new machine.
$localConfig = __DIR__ . '/local.php';
if (is_file($localConfig)) {
    require_once $localConfig;
}

defined('DB_HOST') || define('DB_HOST', '127.0.0.1');
defined('DB_PORT') || define('DB_PORT', '3306');
defined('DB_NAME') || define('DB_NAME', 'warehouse_management');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

// Security Settings
define('SESSION_LIFETIME', 86400); // 24 hours
