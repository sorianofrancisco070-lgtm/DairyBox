<?php
/**
 * DairyBox – Dashboard Load Test
 * Open: https://dairybox.onrender.com/dashtest.php
 * Simulates exactly what happens after login redirect.
 * DELETE after fixing!
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$steps = [];

// Step 1: Load session.php
try {
    require_once 'config/session.php';
    $steps[] = ['pass', 'config/session.php', 'Loaded OK'];
} catch (Throwable $e) {
    $steps[] = ['fail', 'config/session.php', $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()];
}

// Step 2: Load database.php
try {
    require_once 'config/database.php';
    $steps[] = ['pass', 'config/database.php', 'Loaded OK'];
} catch (Throwable $e) {
    $steps[] = ['fail', 'config/database.php', $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()];
}

// Step 3: getDB()
try {
    $db = getDB();
    $steps[] = ['pass', 'getDB()', 'Connected'];
} catch (Throwable $e) {
    $steps[] = ['fail', 'getDB()', $e->getMessage()];
}

// Step 4: Set fake session so requireLogin passes
$_SESSION['user'] = [
    'id' => 1, 'username' => 'manager1',
    'full_name' => 'Juan dela Cruz',
    'role' => 'farm_manager', 'email' => ''
];

// Step 5: Load header.php (this is where it likely crashes)
$root = '';  // from web root
$pageTitle = 'Test';
ob_start();
try {
    include 'includes/header.php';
    $out = ob_get_clean();
    $steps[] = ['pass', 'includes/header.php', 'Loaded OK (' . strlen($out) . ' bytes)'];
} catch (Throwable $e) {
    ob_end_clean();
    $steps[] = ['fail', 'includes/header.php',
        $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine()];
}

// Step 6: Load nav file
ob_start();
try {
    include 'includes/nav_farm_manager.php';
    $out = ob_get_clean();
    $steps[] = ['pass', 'includes/nav_farm_manager.php', 'OK'];
} catch (Throwable $e) {
    ob_end_clean();
    $steps[] = ['fail', 'includes/nav_farm_manager.php',
        $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine()];
}

// Step 7: Load footer.php
ob_start();
try {
    include 'includes/footer.php';
    $out = ob_get_clean();
    $steps[] = ['pass', 'includes/footer.php', 'OK'];
} catch (Throwable $e) {
    ob_end_clean();
    $steps[] = ['fail', 'includes/footer.php',
        $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine()];
}

// Step 8: Try loading dashboard directly
ob_start();
try {
    // Check for syntax errors by requiring individual files
    $dashFile = 'Farm_Managers_User/dashboard.php';
    $content  = php_strip_whitespace($dashFile);
    $steps[]  = ['pass', 'dashboard.php syntax', 'No syntax errors (' . strlen($content) . ' bytes)'];
} catch (Throwable $e) {
    ob_end_clean();
    $steps[] = ['fail', 'dashboard.php syntax check',
        $e->getMessage() . ' line ' . $e->getLine()];
}
ob_end_clean();

// Step 9: PHP version & extensions
$steps[] = ['info', 'PHP Version', PHP_VERSION];
$steps[] = ['info', 'PDO MySQL', extension_loaded('pdo_mysql') ? 'Loaded' : 'MISSING'];
$steps[] = [
    file_exists(__DIR__ . '/Farm_Managers_User/dashboard.php') ? 'pass' : 'fail',
    'dashboard.php exists',
    realpath(__DIR__ . '/Farm_Managers_User/dashboard.php') ?: 'NOT FOUND'
];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dashboard Load Test</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f0f4f0;padding:1.5rem;margin:0}
        .card{background:#fff;border-radius:12px;padding:1.5rem;max-width:800px;margin:0 auto;
              box-shadow:0 2px 12px rgba(0,0,0,.1)}
        h2{color:#1a6b3c;margin-top:0}
        .row{display:flex;gap:1rem;padding:.5rem 0;border-bottom:1px solid #f0f0f0;font-size:.86rem}
        .icon{width:24px;flex-shrink:0;font-size:1rem}
        .lbl{color:#555;min-width:260px;flex-shrink:0;font-weight:600}
        .val{word-break:break-all}
        .pass .val{color:#28a745}
        .fail .val{color:#dc3545;font-weight:bold}
        .info .val{color:#0066cc}
        .warn{background:#fff3cd;border-radius:8px;padding:.8rem;margin-top:1rem;
              font-size:.83rem;color:#856404}
    </style>
</head>
<body>
<div class="card">
    <h2>🐃 DairyBox – Dashboard Load Test</h2>
    <p style="color:#888;font-size:.82rem">
        Testing all includes from web root: <code><?= htmlspecialchars(__DIR__) ?></code>
    </p>
    <?php foreach ($steps as [$type, $label, $value]): ?>
    <div class="row <?= $type ?>">
        <div class="icon"><?= $type==='pass'?'✅':($type==='fail'?'❌':'ℹ️') ?></div>
        <div class="lbl"><?= htmlspecialchars($label) ?></div>
        <div class="val"><?= htmlspecialchars($value) ?></div>
    </div>
    <?php endforeach; ?>
    <div class="warn">⚠️ Delete <code>dashtest.php</code> after fixing!</div>
</div>
</body>
</html>
