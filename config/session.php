<?php
// =========================================================
// DairyBox – Session & Auth Helper
// =========================================================
if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = '/tmp/dairybox_sessions';
    if (!is_dir($sessionPath)) @mkdir($sessionPath, 0777, true);
    ini_set('session.save_path',      $sessionPath);
    ini_set('session.gc_maxlifetime',  86400);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
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

    /**
     * Build the absolute base URL of the app root.
     * Uses HTTP_HOST + a simple depth calculation.
     * Works on Render (app at /), XAMPP (app at /DairyBox/), etc.
     */
    function appBaseUrl(): string {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host  = $_SERVER['HTTP_HOST'];

        // SCRIPT_NAME example: /modules/breeding.php  or  /DairyBox/modules/breeding.php
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $parts  = explode('/', trim($script, '/'));

        // Remove the filename (last part)
        array_pop($parts);

        // Remove known subfolder if the current dir is one level deep
        $knownFolders = [
            'modules', 'auth',
            'Farm_Managers_User', 'Farm_Caretakers_USer',
            'Dairy_Cooperatives_USer', 'Veterinarians_User',
            'config', 'includes', 'database', 'assets',
        ];

        if (!empty($parts) && in_array(end($parts), $knownFolders, true)) {
            array_pop($parts);
        }

        // Build base — if no parts left, app is at root
        $base = empty($parts) ? '' : '/' . implode('/', $parts);

        return $proto . '://' . $host . $base;
    }

    function _redirectToLogin(string $error = ''): void {
        // Use ob_get_level to clean any buffered output before redirecting
        while (ob_get_level() > 0) ob_end_clean();

        $base = appBaseUrl();
        $url  = $base . '/index.php';
        if ($error) $url .= '?error=' . $error;

        header('Location: ' . $url);
        exit;
    }
}
