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
            http_response_code(503);
            $msg = htmlspecialchars($e->getMessage());
            die("<!DOCTYPE html><html><head><meta charset='UTF-8'>
                <title>DairyBox – DB Error</title>
                <style>body{font-family:Arial,sans-serif;background:#fff3cd;display:flex;
                align-items:center;justify-content:center;min-height:100vh;margin:0}
                .box{background:#fff;border:1px solid #ffc107;border-radius:12px;
                padding:2rem;max-width:500px;text-align:center}
                h3{color:#856404}pre{background:#f8f9fa;padding:1rem;border-radius:6px;
                font-size:.8rem;text-align:left;overflow:auto}</style></head>
                <body><div class='box'>
                <h2>🐃 DairyBox</h2>
                <h3>Database Connection Error</h3>
                <p>Could not connect to the database. Please check your credentials.</p>
                <pre>{$msg}</pre>
                <p><a href='javascript:history.back()'>← Go Back</a></p>
                </div></body></html>");
        }
    }
    return $pdo;
}
