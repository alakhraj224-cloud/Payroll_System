<?php
// ============================================================
// config/config.php  –  Database & application configuration
// ============================================================

define('DB_HOST', 'localhost:3307');
define('DB_USER', 'root');        // Change to your MySQL user
define('DB_PASS', '');            // Change to your MySQL password
define('DB_NAME', 'payroll_system');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'PayrollPro');
define('APP_VERSION', '1.0');

// ── PDO connection (singleton) ──────────────────────────────
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Production: log error, show friendly message
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }

    return $pdo;
}