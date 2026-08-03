<?php
// =========================================================
// DairyBox – Database Configuration
// =========================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('DB_HOST', 'sakura.proxy.rlwy.net');
define('DB_USER', 'root');
define('DB_PASS', 'XrpDTsTYMBkAWcoFtkafnPRgPaadICZh');
define('DB_NAME', 'railway');
define('DB_PORT', '52723');

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
        } catch (PDOException $e) {
            // Show full error for debugging
            http_response_code(503);
            die('<h2>DB Connection Failed</h2><pre>'
                . 'Host: ' . DB_HOST . "\n"
                . 'Port: ' . DB_PORT . "\n"
                . 'Name: ' . DB_NAME . "\n"
                . 'User: ' . DB_USER . "\n\n"
                . htmlspecialchars($e->getMessage())
                . '</pre>');
        }
    }
    return $pdo;
}
