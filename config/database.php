<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Get PDO Database Connection
 *
 * @return PDO
 */
function getDBConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log technical details internally, don't expose sensitive info to users
            error_log('Database Connection Error: ' . $e->getMessage());
            die('Database connection failed. Please ensure the MySQL database is running and configured correctly.');
        }
    }

    return $pdo;
}
