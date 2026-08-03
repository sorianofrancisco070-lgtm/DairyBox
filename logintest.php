<?php
/**
 * DairyBox – Login Step Test
 * Open: https://dairybox.onrender.com/logintest.php
 * DELETE after fixing!
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

$steps = [];

// Step 1 - load database.php
try {
    require_once 'config/database.php';
    $steps[] = ['pass', 'Load config/database.php', 'OK'];
} catch (Throwable $e) {
    $steps[] = ['fail', 'Load config/database.php', $e->getMessage()];
    goto output;
}

// Step 2 - getDB()
try {
    $db = getDB();
    $steps[] = ['pass', 'getDB() connection', 'Connected'];
} catch (Throwable $e) {
    $steps[] = ['fail', 'getDB() connection', $e->getMessage()];
    goto output;
}

// Step 3 - query users
try {
    $stmt = $db->prepare("SELECT id, username, role, password FROM users WHERE username='manager1' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch();
    if ($user) {
        $steps[] = ['pass', 'Query manager1', "Found: id={$user['id']} role={$user['role']}"];
    } else {
        $steps[] = ['fail', 'Query manager1', 'User not found in DB'];
    }
} catch (Throwable $e) {
    $steps[] = ['fail', 'Query users table', $e->getMessage()];
    goto output;
}

// Step 4 - password_verify
if ($user) {
    $ok = password_verify('password', $user['password']);
    $steps[] = [$ok ? 'pass' : 'fail', "password_verify('password', hash)", $ok ? 'MATCH ✅' : 'NO MATCH ❌ – wrong hash in DB'];
}

// Step 5 - session
try {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['test'] = 'ok';
    $steps[] = ['pass', 'session_start()', 'Session ID: ' . session_id()];
} catch (Throwable $e) {
    $steps[] = ['fail', 'session_start()', $e->getMessage()];
}

// Step 6 - baseUrl simulation
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$script   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$steps[]  = ['info', 'SCRIPT_NAME', $script];
$steps[]  = ['info', 'Base URL would be', $protocol . '://' . $host];
$steps[]  = ['info', 'Redirect after login', $protocol . '://' . $host . '/Farm_Managers_User/dashboard.php'];

// Step 7 - check dashboard file exists
$dashFile = __DIR__ . '/Farm_Managers_User/dashboard.php';
$steps[]  = [file_exists($dashFile) ? 'pass' : 'fail', 'Farm_Managers_User/dashboard.php exists', file_exists($dashFile) ? 'YES' : 'NOT FOUND'];

// Step 8 - check session.php
$sessFile = __DIR__ . '/config/session.php';
$steps[]  = [file_exists($sessFile) ? 'pass' : 'fail', 'config/session.php exists', file_exists($sessFile) ? 'YES' : 'NOT FOUND'];

output:
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Login Test</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f0f4f0;padding:1.5rem;margin:0}
        .card{background:#fff;border-radius:12px;padding:1.5rem;max-width:700px;margin:0 auto;box-shadow:0 2px 12px rgba(0,0,0,.1)}
        h2{color:#1a6b3c;margin-top:0}
        .row{display:flex;gap:1rem;padding:.5rem 0;border-bottom:1px solid #f0f0f0;font-size:.87rem}
        .icon{width:20px;flex-shrink:0}
        .lbl{color:#555;min-width:240px;flex-shrink:0}
        .val{font-weight:500;word-break:break-all}
        .pass{color:#28a745}.fail{color:#dc3545}.info{color:#0066cc}
        .warn{background:#fff3cd;border-radius:8px;padding:.8rem;margin-top:1rem;font-size:.83rem;color:#856404}
    </style>
</head>
<body>
<div class="card">
    <h2>🐃 DairyBox – Login Step Test</h2>
    <?php foreach ($steps as [$type, $label, $value]): ?>
    <div class="row">
        <div class="icon"><?= $type==='pass'?'✅':($type==='fail'?'❌':'ℹ️') ?></div>
        <div class="lbl"><?= htmlspecialchars($label) ?></div>
        <div class="val <?= $type ?>"><?= htmlspecialchars($value) ?></div>
    </div>
    <?php endforeach; ?>
    <div class="warn">⚠️ Delete <code>logintest.php</code> and <code>dbtest.php</code> after fixing!</div>
</div>
</body>
</html>
