<?php
// =========================================================
// DairyBox – Session & Auth Helper
// =========================================================
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Redirect to login if not authenticated or wrong role.
 */
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
 * Builds redirect URL to index.php relative to current script depth.
 * Works for files in:  /index.php  /modules/  /Farm_Managers_User/  etc.
 */
function _redirectToLogin(string $error = ''): void {
    // Count how deep we are from web root
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $depth  = substr_count(trim($script, '/'), '/');

    // depth 0 = root (index.php itself)
    // depth 1 = one folder deep (modules/, Farm_Managers_User/, etc.)
    $prefix = $depth >= 1 ? '../' : './';

    $url = $prefix . 'index.php';
    if ($error) $url .= '?error=' . $error;

    header('Location: ' . $url);
    exit;
}
