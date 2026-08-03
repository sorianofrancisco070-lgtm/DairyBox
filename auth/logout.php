<?php
session_start();
require_once '../config/database.php';

if (isset($_SESSION['user'])) {
    $db = getDB();
    $db->prepare("INSERT INTO activity_log (user_id, action, module, ip_address) VALUES (?, 'Logout', 'Auth', ?)")
       ->execute([$_SESSION['user']['id'], $_SERVER['REMOTE_ADDR'] ?? '']);
}

session_destroy();
header('Location: ../index.php?msg=You+have+been+logged+out');
exit;
