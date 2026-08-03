<?php
// =========================================================
// DairyBox – Login Handler
// =========================================================
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = '/tmp/dairybox_sessions';
    if (!is_dir($sessionPath)) mkdir($sessionPath, 0777, true);
    ini_set('session.save_path',      $sessionPath);
    ini_set('session.gc_maxlifetime',  86400);
    ini_set('session.cookie_httponly', 1);
    session_start();
}

// Helper – get base URL
function baseUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $script   = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'])));
    $script   = rtrim($script, '/');
    return $protocol . '://' . $host . $script;
}

// Already logged in
if (isset($_SESSION['user'])) {
    $base = baseUrl();
    $role = $_SESSION['user']['role'];
    $map  = [
        'farm_manager'      => '/Farm_Managers_User/dashboard.php',
        'farm_caretaker'    => '/Farm_Caretakers_USer/dashboard.php',
        'dairy_cooperative' => '/Dairy_Cooperatives_USer/dashboard.php',
        'veterinarian'      => '/Veterinarians_User/dashboard.php',
    ];
    header('Location: ' . $base . ($map[$role] ?? '/index.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role     = trim($_POST['role'] ?? '');

if (!$username || !$password || !$role) {
    header('Location: ../index.php?error=All+fields+are+required');
    exit;
}

// Connect to DB
try {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND role = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$username, $role]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    die('<h3>DB Query Error:</h3><pre>' . htmlspecialchars($e->getMessage()) . '</pre>');
}

if ($user && password_verify($password, $user['password'])) {
    session_regenerate_id(true);

    // Clear any stale DAIRYBOX_SESS cookie left from previous attempts
    setcookie('DAIRYBOX_SESS', '', time() - 3600, '/');

    $_SESSION['user'] = [
        'id'        => $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
        'email'     => $user['email'],
    ];

    // Log activity
    try {
        $db->prepare("INSERT INTO activity_log (user_id, action, module, ip_address) VALUES (?, 'Login', 'Auth', ?)")
           ->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
    } catch (Exception $e) { /* non-fatal */ }

    $base = baseUrl();
    $map  = [
        'farm_manager'      => '/Farm_Managers_User/dashboard.php',
        'farm_caretaker'    => '/Farm_Caretakers_USer/dashboard.php',
        'dairy_cooperative' => '/Dairy_Cooperatives_USer/dashboard.php',
        'veterinarian'      => '/Veterinarians_User/dashboard.php',
    ];
    $dest = $base . ($map[$role] ?? '/index.php');
    header('Location: ' . $dest);
    exit;

} else {
    header('Location: ../index.php?error=Invalid+username%2C+password%2C+or+role');
    exit;
}
