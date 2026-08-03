<?php
// Called at top of every page with $pageTitle and $activeNav set
$user = currentUser();
$roleLabels = [
    'farm_manager'      => 'Farm Manager',
    'farm_caretaker'    => 'Farm Caretaker',
    'dairy_cooperative' => 'Dairy Cooperative',
    'veterinarian'      => 'Veterinarian',
];
$roleLabel = $roleLabels[$user['role']] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'DairyBox') ?> | DairyBox</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $root ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
</head>
<body class="app-body">

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <h5>🐃 DairyBox</h5>
        <p>Production & Herd Health</p>
    </div>
    <nav>
        <?php include $root . 'includes/nav_' . $user['role'] . '.php'; ?>
    </nav>
    <div class="sidebar-footer">
        <i class="fa fa-user-circle me-1"></i>
        <strong><?= htmlspecialchars($user['full_name']) ?></strong><br>
        <span class="badge bg-success mt-1"><?= $roleLabel ?></span>
    </div>
</aside>

<!-- Topbar -->
<header class="topbar">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-outline-secondary d-md-none" onclick="document.getElementById('sidebar').classList.toggle('open')">
            <i class="fa fa-bars"></i>
        </button>
        <span class="page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></span>
    </div>
    <div class="topbar-right">
        <?php
        $db = getDB();
        $notifCount = $db->prepare("SELECT COUNT(*) FROM notifications WHERE is_read=0 AND (target_role=? OR target_role IS NULL)");
        $notifCount->execute([$user['role']]);
        $nc = $notifCount->fetchColumn();
        ?>
        <div class="position-relative">
            <button class="btn btn-sm btn-outline-success position-relative" onclick="window.location='<?= $root ?>modules/notifications.php'">
                <i class="fa fa-bell"></i>
                <?php if ($nc > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem"><?= $nc ?></span>
                <?php endif; ?>
            </button>
        </div>
        <span class="user-badge"><i class="fa fa-circle text-success me-1" style="font-size:.5rem"></i><?= htmlspecialchars($user['full_name']) ?></span>
        <a href="<?= $root ?>auth/logout.php" class="btn btn-sm btn-outline-danger" title="Logout">
            <i class="fa fa-sign-out-alt"></i>
        </a>
    </div>
</header>

<!-- Main Content -->
<main class="main-content">
<div class="content-body">
