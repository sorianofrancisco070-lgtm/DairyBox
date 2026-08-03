<?php
// =========================================================
// DairyBox – Logout Handler
// =========================================================
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (isset($_SESSION['user'])) {
    try {
        $db = getDB();
        $db->prepare("INSERT INTO activity_log (user_id, action, module, ip_address) VALUES (?, 'Logout', 'Auth', ?)")
           ->execute([$_SESSION['user']['id'], $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
    } catch (Exception $e) {
        // Non-fatal
    }
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

header('Location: ../index.php?msg=You+have+been+logged+out');
exit;
