<?php
// =========================================================
// DairyBox – Session & Auth Helper
// =========================================================
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Redirect to login if not authenticated, or if wrong role.
 * Uses HTTP_HOST + SCRIPT_NAME to build an absolute redirect safely.
 */
function requireLogin(string $role = ''): void {
    if (!isset($_SESSION['user'])) {
        $root = _appRoot();
        header('Location: ' . $root . 'index.php?error=Please+login+first');
        exit;
    }
    if ($role && $_SESSION['user']['role'] !== $role) {
        $root = _appRoot();
        header('Location: ' . $root . 'index.php?error=Access+denied');
        exit;
    }
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}

function isRole(string $role): bool {
    return (currentUser()['role'] ?? '') === $role;
}

/**
 * Returns the URL path to the project root (e.g. '/dairybox/').
 * Works whether the app lives at / or at /subdir/.
 */
function _appRoot(): string {
    // Walk up from the current script to find index.php
    // The app root is always 1 or 2 levels above modules/ or dashboard dirs
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    // All pages are either in root, /modules/, or /Role_Folder/
    // So root is the parent of those directories
    $parts = explode('/', trim($scriptDir, '/'));
    // Remove last segment (the immediate folder)
    if (count($parts) > 0) array_pop($parts);
    $base = '/' . implode('/', array_filter($parts));
    $base = rtrim($base, '/') . '/';
    // Ensure it's not empty
    if ($base === '//') $base = '/';
    return $base;
}
