<?php
// =========================================================
// DairyBox – Session & Auth Helper
// =========================================================
if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = '/tmp/dairybox_sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0777, true);
    }
    ini_set('session.save_path',     $sessionPath);
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    // Do NOT set a custom session.name — use default PHPSESSID everywhere
    session_start();
}

if (!function_exists('requireLogin')) {

    function requireLogin(string $role = ''): void {
        if (!isset($_SESSION['user'])) {
            _redirectToLogin('Please+login+first');
        }
        if ($role && $_SESSION['user']['role'] !== $role) {
            _redirectToLogin('Access+denied');
        }
    }

    function currentUser(): array {
        return $_SESSION['user'] ?? [];
    }

    function isRole(string $role): bool {
        return (currentUser()['role'] ?? '') === $role;
    }

    function appBaseUrl(): string {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'];
        $script   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        $parts    = explode('/', trim($script, '/'));
        array_pop($parts); // remove filename
        $knownFolders = [
            'modules', 'auth',
            'Farm_Managers_User', 'Farm_Caretakers_USer',
            'Dairy_Cooperatives_USer', 'Veterinarians_User',
            'config', 'includes', 'database', 'assets',
        ];
        if (!empty($parts) && in_array(end($parts), $knownFolders)) {
            array_pop($parts);
        }
        $base = rtrim('/' . implode('/', array_filter($parts)), '/');
        return $protocol . '://' . $host . $base;
    }

    function _redirectToLogin(string $error = ''): void {
        $base = appBaseUrl();
        $url  = $base . '/index.php';
        if ($error) $url .= '?error=' . $error;
        header('Location: ' . $url);
        exit;
    }
}
