<?php
// =========================================================
// DairyBox – Database Configuration
// =========================================================
if (!defined('DB_HOST')) {
    define('DB_HOST', 'sakura.proxy.rlwy.net');
    define('DB_USER', 'root');
    define('DB_PASS', 'XrpDTsTYMBkAWcoFtkafnPRgPaadICZh');
    define('DB_NAME', 'railway');
    define('DB_PORT', '52723');
}

if (!function_exists('getDB')) {
    function getDB(): PDO {
        static $pdo = null;
        if ($pdo === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST
                     . ";port=" . DB_PORT
                     . ";dbname=" . DB_NAME
                     . ";charset=utf8mb4";
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
                // Fix Railway MySQL only_full_group_by restriction
                $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            } catch (PDOException $e) {
                http_response_code(503);
                die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>DB Error</title>
                    <style>body{font-family:Arial;display:flex;align-items:center;justify-content:center;
                    min-height:100vh;margin:0;background:#fff3cd}
                    .b{background:#fff;border-radius:12px;padding:2rem;max-width:500px;border:1px solid #ffc107}
                    pre{background:#f8f9fa;padding:1rem;border-radius:6px;font-size:.82rem;
                    white-space:pre-wrap;word-break:break-all}</style></head><body><div class="b">
                    <h2>🐃 DairyBox</h2><h3 style="color:#856404">Database Error</h3>
                    <pre>Host: ' . DB_HOST . "\nPort: " . DB_PORT . "\nDB: " . DB_NAME
                    . "\n\n" . htmlspecialchars($e->getMessage()) . '</pre>
                    </div></body></html>');
            }
        }
        return $pdo;
    }
}
