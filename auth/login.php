<?php
// =========================================================
// DairyBox – Login Handler
// =========================================================
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Helper – get base URL of the app
function baseUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    // Go one level up from /auth/ to get app root
    $script   = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'])));
    $script   = rtrim($script, '/');
    return $protocol . '://' . $host . $script;
}

// Already logged in
if (isset($_SESSION['user'])) {
    $base = baseUrl();
    $role = $_SESSION['user']['role'];
    $redirects = [
        'farm_manager'      => $base . '/Farm_Managers_User/dashboard.php',
        'farm_caretaker'    => $base . '/Farm_Caretakers_USer/dashboard.php',
        'dairy_cooperative' => $base . '/Dairy_Cooperatives_USer/dashboard.php',
        'veterinarian'      => $base . '/Veterinarians_User/dashboard.php',
    ];
    header('Location: ' . ($redirects[$role] ?? $base . '/index.php'));
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

try {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND role = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$username, $role]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    header('Location: ../index.php?error=Database+error.+Please+try+again.');
    exit;
}

if ($user && password_verify($password, $user['password'])) {
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'        => $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
        'email'     => $user['email'],
    ];

    // Log activity (non-fatal)
    try {
        $db->prepare("INSERT INTO activity_log (user_id, action, module, ip_address) VALUES (?, 'Login', 'Auth', ?)")
           ->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
    } catch (Exception $e) { /* non-fatal */ }

    $base = baseUrl();
    $redirects = [
        'farm_manager'      => $base . '/Farm_Managers_User/dashboard.php',
        'farm_caretaker'    => $base . '/Farm_Caretakers_USer/dashboard.php',
        'dairy_cooperative' => $base . '/Dairy_Cooperatives_USer/dashboard.php',
        'veterinarian'      => $base . '/Veterinarians_User/dashboard.php',
    ];
    header('Location: ' . ($redirects[$role] ?? $base . '/index.php'));
    exit;

} else {
    header('Location: ../index.php?error=Invalid+username%2C+password%2C+or+role');
    exit;
}
