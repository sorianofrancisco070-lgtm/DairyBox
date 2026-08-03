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

// Bottom nav items per role
$bottomNavItems = [
    'farm_manager' => [
        ['icon'=>'fa-tachometer-alt', 'label'=>'Dashboard', 'href'=>$root.'Farm_Managers_User/dashboard.php'],
        ['icon'=>'fa-paw',            'label'=>'Buffaloes',  'href'=>$root.'modules/buffaloes.php'],
        ['icon'=>'fa-tint',           'label'=>'Milk',       'href'=>$root.'modules/milk_production.php'],
        ['icon'=>'fa-bell',           'label'=>'Alerts',     'href'=>$root.'modules/notifications.php'],
        ['icon'=>'fa-sign-out-alt',   'label'=>'Logout',     'href'=>$root.'auth/logout.php', 'danger'=>true],
    ],
    'farm_caretaker' => [
        ['icon'=>'fa-tachometer-alt', 'label'=>'Dashboard', 'href'=>$root.'Farm_Caretakers_USer/dashboard.php'],
        ['icon'=>'fa-tint',           'label'=>'Milk',       'href'=>$root.'modules/milk_production.php'],
        ['icon'=>'fa-heartbeat',      'label'=>'Health',     'href'=>$root.'modules/health_records.php'],
        ['icon'=>'fa-qrcode',         'label'=>'QR Scan',    'href'=>$root.'modules/qr_scan.php'],
        ['icon'=>'fa-sign-out-alt',   'label'=>'Logout',     'href'=>$root.'auth/logout.php', 'danger'=>true],
    ],
    'dairy_cooperative' => [
        ['icon'=>'fa-tachometer-alt', 'label'=>'Dashboard', 'href'=>$root.'Dairy_Cooperatives_USer/dashboard.php'],
        ['icon'=>'fa-tint',           'label'=>'Production', 'href'=>$root.'modules/milk_production.php'],
        ['icon'=>'fa-chart-line',     'label'=>'Analytics',  'href'=>$root.'modules/production_analytics.php'],
        ['icon'=>'fa-file-alt',       'label'=>'Reports',    'href'=>$root.'modules/reports.php'],
        ['icon'=>'fa-sign-out-alt',   'label'=>'Logout',     'href'=>$root.'auth/logout.php', 'danger'=>true],
    ],
    'veterinarian' => [
        ['icon'=>'fa-tachometer-alt',      'label'=>'Dashboard', 'href'=>$root.'Veterinarians_User/dashboard.php'],
        ['icon'=>'fa-heartbeat',           'label'=>'Health',    'href'=>$root.'modules/health_records.php'],
        ['icon'=>'fa-syringe',             'label'=>'Vaccines',  'href'=>$root.'modules/vaccinations.php'],
        ['icon'=>'fa-venus-mars',          'label'=>'Breeding',  'href'=>$root.'modules/breeding.php'],
        ['icon'=>'fa-sign-out-alt',        'label'=>'Logout',    'href'=>$root.'auth/logout.php', 'danger'=>true],
    ],
];
$myBottomNav = $bottomNavItems[$user['role']] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#1a6b3c">
    <meta name="build" content="<?= date('YmdHis') ?>">
    <title><?= htmlspecialchars($pageTitle ?? 'DairyBox') ?> | DairyBox</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $root ?>assets/css/style.css?v=<?= date('YmdHis') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
</head>
<body class="app-body">

<!-- Mobile Sidebar Backdrop -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

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
        <span class="badge bg-success mt-1"><?= $roleLabel ?></span><br>
        <a href="<?= $root ?>auth/logout.php"
           onclick="return confirm('Log out of DairyBox?')"
           class="btn btn-sm btn-outline-danger mt-2 w-100"
           style="font-size:.78rem">
            <i class="fa fa-sign-out-alt me-1"></i> Logout
        </a>
    </div>
</aside>

<!-- Topbar -->
<header class="topbar">
    <div class="d-flex align-items-center gap-2">
        <!-- Hamburger (always visible, opens sidebar) -->
        <button class="btn btn-sm btn-outline-secondary"
                id="sidebarToggle"
                onclick="toggleSidebar()"
                aria-label="Menu"
                style="border:none;background:none;padding:.3rem .4rem;font-size:1.15rem;color:var(--green-dark)">
            <i class="fa fa-bars"></i>
        </button>
        <span class="page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></span>
    </div>
    <div class="topbar-right">
        <?php
        $db2 = getDB();
        $nc2 = $db2->prepare("SELECT COUNT(*) FROM notifications WHERE is_read=0 AND (target_role=? OR target_role IS NULL)");
        $nc2->execute([$user['role']]);
        $nc2 = $nc2->fetchColumn();
        ?>
        <a href="<?= $root ?>modules/notifications.php"
           class="btn btn-sm position-relative"
           style="border:none;background:none;padding:.3rem .4rem;font-size:1.1rem;color:var(--green-dark)">
            <i class="fa fa-bell"></i>
            <?php if ($nc2 > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                  style="font-size:.55rem;padding:.25em .4em"><?= $nc2 ?></span>
            <?php endif; ?>
        </a>
        <span class="user-badge d-none d-md-inline">
            <i class="fa fa-circle text-success me-1" style="font-size:.45rem"></i><?= htmlspecialchars($user['full_name']) ?>
        </span>
        <a href="<?= $root ?>auth/logout.php"
           class="btn btn-sm btn-outline-danger d-none d-md-inline-flex align-items-center gap-1"
           style="font-size:.8rem;padding:.25rem .6rem">
            <i class="fa fa-sign-out-alt"></i>
            <span class="d-none d-lg-inline">Logout</span>
        </a>
    </div>
</header>

<!-- Main Content -->
<main class="main-content">
<div class="content-body">

<!-- Mobile Bottom Navigation -->
<?php if (!empty($myBottomNav)): ?>
<nav class="mobile-bottom-nav" aria-label="Mobile navigation">
    <div class="nav-items">
        <?php foreach ($myBottomNav as $item):
            $isDanger = !empty($item['danger']);
        ?>
        <a href="<?= $item['href'] ?>"
           class="nav-item <?= $isDanger ? 'nav-logout' : '' ?>"
           <?= $isDanger ? 'onclick="return confirm(\'Log out of DairyBox?\')"' : '' ?>>
            <i class="fa <?= $item['icon'] ?>"></i>
            <span><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</nav>
<?php endif; ?>
