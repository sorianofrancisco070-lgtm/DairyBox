<?php
// =========================================================
// DairyBox – Session & Auth Helper
// =========================================================
if (session_status() === PHP_SESSION_NONE) session_start();

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
 * Build absolute base URL of the app root.
 * Works from any subfolder depth.
 */
function appBaseUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];

    // SCRIPT_NAME example: /Farm_Managers_User/dashboard.php
    // We need everything before the last two segments
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $parts  = explode('/', trim($script, '/'));

    // Remove filename
    array_pop($parts);

    // If inside a subfolder (modules/, Farm_*/, etc.) remove that too
    $knownFolders = [
        'modules', 'auth',
        'Farm_Managers_User', 'Farm_Caretakers_USer',
        'Dairy_Cooperatives_USer', 'Veterinarians_User',
        'config', 'includes', 'database', 'assets',
    ];
    if (!empty($parts) && in_array(end($parts), $knownFolders)) {
        array_pop($parts);
    }

    $base = '/' . implode('/', array_filter($parts));
    $base = rtrim($base, '/');

    return $protocol . '://' . $host . $base;
}

function _redirectToLogin(string $error = ''): void {
    $base = appBaseUrl();
    $url  = $base . '/index.php';
    if ($error) $url .= '?error=' . $error;
    header('Location: ' . $url);
    exit;
}
