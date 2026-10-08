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

// Database Credentials
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'warehouse_management');
define('DB_USER', 'root');
define('DB_PASS', 'jack005432');
define('DB_CHARSET', 'utf8mb4');

// Security Settings
define('SESSION_LIFETIME', 86400); // 24 hours
