<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role     = $_POST['role'] ?? '';

if (!$username || !$password || !$role) {
    header('Location: ../index.php?error=All+fields+are+required');
    exit;
}

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND role = ? AND is_active = 1");
$stmt->execute([$username, $role]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    $_SESSION['user'] = [
        'id'        => $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
        'email'     => $user['email'],
    ];

    // Log activity
    $db->prepare("INSERT INTO activity_log (user_id, action, module, ip_address) VALUES (?, 'Login', 'Auth', ?)")
       ->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? '']);

    $redirects = [
        'farm_manager'      => '../Farm_Managers_User/dashboard.php',
        'farm_caretaker'    => '../Farm_Caretakers_USer/dashboard.php',
        'dairy_cooperative' => '../Dairy_Cooperatives_USer/dashboard.php',
        'veterinarian'      => '../Veterinarians_User/dashboard.php',
    ];
    header('Location: ' . ($redirects[$role] ?? '../index.php'));
    exit;
} else {
    header('Location: ../index.php?error=Invalid+username,+password,+or+role');
    exit;
}
