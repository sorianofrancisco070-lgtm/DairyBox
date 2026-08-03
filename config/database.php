<?php
// =========================================================
// DairyBox – Database Configuration
// =========================================================
define('DB_HOST', 'sakura.proxy.rlwy.net');
define('DB_USER', 'root');
define('DB_PASS', 'XrpDTsTYMBkAWcoFtkafnPRgPaadICZh');
define('DB_NAME', 'railway');
define('DB_PORT', '52723');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}
