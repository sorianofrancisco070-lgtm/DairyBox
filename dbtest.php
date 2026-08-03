<?php
/**
 * DairyBox – Database Connection Test
 * Open: https://dairybox.onrender.com/dbtest.php
 * DELETE this file after fixing the connection!
 */

$host = 'sakura.proxy.rlwy.net';
$port = '52723';
$user = 'root';
$pass = 'XrpDTsTYMBkAWcoFtkafnPRgPaadICZh';
$name = 'railway';

$tests = [];

// Test 1 – TCP socket reachable
$socket = @fsockopen($host, (int)$port, $errno, $errstr, 5);
if ($socket) {
    fclose($socket);
    $tests[] = ['pass', "TCP connection to {$host}:{$port}", 'Reachable ✅'];
} else {
    $tests[] = ['fail', "TCP connection to {$host}:{$port}", "FAILED – {$errstr} (errno {$errno})"];
}

// Test 2 – PDO connect without DB
try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]
    );
    $tests[] = ['pass', 'MySQL login (no database)', 'Connected ✅'];

    // Test 3 – List databases
    $dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
    $tests[] = ['info', 'Available databases', implode(', ', $dbs)];

    // Test 4 – Connect to specific DB
    try {
        $pdo2 = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $tests[] = ['pass', "Connect to database '{$name}'", 'Connected ✅'];

        // Test 5 – Check tables
        $tables = $pdo2->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (count($tables) > 0) {
            $tests[] = ['pass', 'Tables found', implode(', ', $tables)];

            // Test 6 – Check users
            $users = $pdo2->query("SELECT username, role FROM users LIMIT 4")->fetchAll();
            $uList = array_map(fn($u) => $u['username'].'('.$u['role'].')', $users);
            $tests[] = ['pass', 'Sample users', implode(', ', $uList)];
        } else {
            $tests[] = ['fail', 'Tables found', 'NO TABLES – database is empty! Run setup.'];
        }
    } catch (PDOException $e2) {
        $tests[] = ['fail', "Connect to database '{$name}'", $e2->getMessage()];
    }

} catch (PDOException $e) {
    $tests[] = ['fail', 'MySQL login', $e->getMessage()];
}

// SSL test
try {
    $pdo3 = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user, $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            PDO::MYSQL_ATTR_SSL_CA => '',
        ]
    );
    $tests[] = ['info', 'SSL connection test', 'Works with SSL disabled'];
} catch (PDOException $e) {
    $tests[] = ['info', 'SSL connection test', 'SSL error: ' . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DairyBox – DB Test</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f4f0; padding: 1.5rem; margin: 0; }
        .card { background: #fff; border-radius: 12px; padding: 1.5rem; max-width: 700px; margin: 0 auto; box-shadow: 0 2px 12px rgba(0,0,0,.1); }
        h2 { color: #1a6b3c; margin-top: 0; }
        .row { display: flex; gap: 1rem; align-items: flex-start; padding: .6rem 0; border-bottom: 1px solid #f0f0f0; font-size: .88rem; }
        .icon { font-size: 1.1rem; flex-shrink: 0; width: 24px; }
        .label { color: #555; min-width: 220px; flex-shrink: 0; }
        .value { color: #222; font-weight: 500; word-break: break-all; }
        .value.fail { color: #dc3545; }
        .value.pass { color: #28a745; }
        .value.info { color: #0c63e4; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: .8rem 1rem; margin-top: 1rem; font-size: .85rem; color: #856404; }
        h4 { color: #1a6b3c; margin-bottom: .5rem; }
    </style>
</head>
<body>
<div class="card">
    <h2>🐃 DairyBox – Database Test</h2>
    <p style="color:#888;font-size:.85rem;margin-top:-.5rem">
        Testing: <strong><?= $host ?>:<?= $port ?></strong> / DB: <strong><?= $name ?></strong>
    </p>

    <?php foreach ($tests as [$type, $label, $value]): ?>
    <div class="row">
        <div class="icon"><?= $type === 'pass' ? '✅' : ($type === 'fail' ? '❌' : 'ℹ️') ?></div>
        <div class="label"><?= htmlspecialchars($label) ?></div>
        <div class="value <?= $type ?>"><?= htmlspecialchars($value) ?></div>
    </div>
    <?php endforeach; ?>

    <div class="warning">
        ⚠️ <strong>Security:</strong> Delete <code>dbtest.php</code> from your server after fixing the issue!
    </div>
</div>
</body>
</html>
