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
            $msg  = htmlspecialchars($e->getMessage());
            $host = htmlspecialchars(DB_HOST);
            $port = htmlspecialchars(DB_PORT);
            $name = htmlspecialchars(DB_NAME);
            $user = htmlspecialchars(DB_USER);
            die("<!DOCTYPE html><html><head><meta charset='UTF-8'>
                <title>DairyBox – DB Error</title>
                <style>body{font-family:Arial,sans-serif;background:#fff3cd;display:flex;
                align-items:center;justify-content:center;min-height:100vh;margin:0}
                .box{background:#fff;border:1px solid #ffc107;border-radius:12px;
                padding:2rem;max-width:560px;width:95%}
                h3{color:#856404}pre{background:#f8f9fa;padding:1rem;border-radius:6px;
                font-size:.82rem;text-align:left;overflow:auto;white-space:pre-wrap;word-break:break-all}
                table{width:100%;font-size:.85rem;border-collapse:collapse}
                td{padding:.3rem .5rem;border-bottom:1px solid #eee}
                td:first-child{color:#888;width:40%}</style></head>
                <body><div class='box'>
                <h2>🐃 DairyBox</h2>
                <h3>Database Connection Error</h3>
                <table>
                  <tr><td>Host</td><td><strong>{$host}</strong></td></tr>
                  <tr><td>Port</td><td><strong>{$port}</strong></td></tr>
                  <tr><td>Database</td><td><strong>{$name}</strong></td></tr>
                  <tr><td>User</td><td><strong>{$user}</strong></td></tr>
                </table>
                <pre style='margin-top:1rem'>{$msg}</pre>
                <p style='text-align:center'><a href='javascript:history.back()'>← Go Back</a></p>
                </div></body></html>");
        }
    }
    return $pdo;
}
