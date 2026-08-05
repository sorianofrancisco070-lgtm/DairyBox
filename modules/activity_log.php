<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$root      = '../';
$pageTitle = 'Activity Log';
$db        = getDB();
$user      = currentUser();

// Filters
$filterUser   = (int)($_GET['user_id'] ?? 0);
$filterAction = trim($_GET['action_f'] ?? '');
$filterDate   = trim($_GET['date_f'] ?? '');
$search       = trim($_GET['search'] ?? '');

$where  = "WHERE 1";
$params = [];

if ($filterUser)  { $where .= " AND al.user_id=?";        $params[] = $filterUser; }
if ($filterAction){ $where .= " AND al.action LIKE ?";     $params[] = "%$filterAction%"; }
if ($filterDate)  { $where .= " AND DATE(al.created_at)=?";$params[] = $filterDate; }
if ($search)      { $where .= " AND (al.action LIKE ? OR al.module LIKE ? OR al.details LIKE ? OR u.full_name LIKE ?)";
                    $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]); }

$logs = $db->prepare("
    SELECT al.*, u.full_name, u.role, u.username
    FROM activity_log al
    LEFT JOIN users u ON u.id = al.user_id
    $where
    ORDER BY al.created_at DESC
    LIMIT 200
");
$logs->execute($params);
$logs = $logs->fetchAll();

// All users for filter dropdown
$allUsers = $db->query("SELECT id, full_name, role FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll();

// Summary counts today
$todayCount = $db->query("SELECT COUNT(*) FROM activity_log WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$totalCount = $db->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();
$loginCount = $db->query("SELECT COUNT(*) FROM activity_log WHERE action='Login' AND DATE(created_at)=CURDATE()")->fetchColumn();

$roleLabels = ['farm_manager'=>'Farm Manager','farm_caretaker'=>'Farm Caretaker',
               'dairy_cooperative'=>'Dairy Cooperative','veterinarian'=>'Veterinarian'];

include '../includes/header.php';
?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#cce5ff"><i class="fa fa-history text-primary" style="font-size:1.4rem"></i></div>
            <div><p class="stat-label">Today's Activities</p><p class="stat-value text-primary"><?= $todayCount ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#d4edda"><i class="fa fa-sign-in-alt text-success" style="font-size:1.4rem"></i></div>
            <div><p class="stat-label">Logins Today</p><p class="stat-value text-success"><?= $loginCount ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff3cd"><i class="fa fa-users text-warning" style="font-size:1.4rem"></i></div>
            <div><p class="stat-label">Total Users</p><p class="stat-value text-warning"><?= count($allUsers) ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e2d9f3"><i class="fa fa-database" style="color:#6f42c1;font-size:1.4rem"></i></div>
            <div><p class="stat-label">Total Log Entries</p><p class="stat-value" style="color:#6f42c1"><?= number_format($totalCount) ?></p></div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card-section mb-3 no-print">
    <form class="row g-2 align-items-end" method="GET">
        <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Search</label>
            <input type="text" name="search" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($search) ?>" placeholder="Action, module, user, details…">
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">User</label>
            <select name="user_id" class="form-select form-select-sm">
                <option value="">All Users</option>
                <?php foreach ($allUsers as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $filterUser==$u['id']?'selected':'' ?>>
                    <?= htmlspecialchars($u['full_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Action</label>
            <select name="action_f" class="form-select form-select-sm">
                <option value="">All Actions</option>
                <?php foreach (['Login','Logout','Add Buffalo','Update Buffalo','Add Milk','Add Health','Update','Delete'] as $a): ?>
                <option value="<?= $a ?>" <?= $filterAction===$a?'selected':'' ?>><?= $a ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Date</label>
            <input type="date" name="date_f" class="form-control form-control-sm" value="<?= $filterDate ?>">
        </div>
        <div class="col-md-1"><button class="btn btn-success btn-sm w-100"><i class="fa fa-filter"></i></button></div>
        <div class="col-md-1"><a href="activity_log.php" class="btn btn-outline-secondary btn-sm w-100">Reset</a></div>
    </form>
</div>

<!-- Log Table -->
<div class="card-section">
    <div class="section-title">
        <i class="fa fa-history me-2"></i>Activity Log
        <span class="badge bg-secondary ms-1"><?= count($logs) ?></span>
        <button onclick="window.print()" class="btn btn-sm btn-outline-success float-end no-print">
            <i class="fa fa-print me-1"></i>Print
        </button>
    </div>

    <?php if (empty($logs)): ?>
    <div class="text-center py-4 text-muted">
        <i class="fa fa-history fa-2x mb-2 opacity-25"></i>
        <p>No activity records found.</p>
    </div>
    <?php else: ?>

    <!-- Desktop Table -->
    <div class="d-none d-md-block table-responsive">
    <table class="table table-sm table-hover mb-0">
        <thead>
            <tr><th>Date & Time</th><th>User</th><th>Role</th><th>Action</th><th>Module</th><th>Details</th><th>IP Address</th></tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log):
            $actionCls = match(true) {
                str_contains(strtolower($log['action']??''),'login')   => 'text-success',
                str_contains(strtolower($log['action']??''),'logout')  => 'text-secondary',
                str_contains(strtolower($log['action']??''),'delete')  => 'text-danger',
                str_contains(strtolower($log['action']??''),'add')     => 'text-primary',
                str_contains(strtolower($log['action']??''),'update')  => 'text-warning',
                default => '',
            };
            $roleCls = match($log['role']??'') {
                'farm_manager'      => 'bg-success',
                'farm_caretaker'    => 'bg-primary',
                'veterinarian'      => 'bg-danger',
                'dairy_cooperative' => 'bg-warning text-dark',
                default             => 'bg-secondary'
            };
        ?>
        <tr>
            <td>
                <div style="font-size:.82rem"><?= date('M d, Y', strtotime($log['created_at'])) ?></div>
                <div style="font-size:.76rem;color:#888"><?= date('h:i:s A', strtotime($log['created_at'])) ?></div>
            </td>
            <td>
                <strong style="font-size:.85rem"><?= htmlspecialchars($log['full_name'] ?? 'Unknown') ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($log['username'] ?? '') ?></small>
            </td>
            <td><span class="badge <?= $roleCls ?>" style="font-size:.65rem"><?= $roleLabels[$log['role']??'']??($log['role']??'—') ?></span></td>
            <td><span class="fw-semibold <?= $actionCls ?>"><?= htmlspecialchars($log['action']??'—') ?></span></td>
            <td><span class="badge bg-light text-dark border" style="font-size:.72rem"><?= htmlspecialchars($log['module']??'—') ?></span></td>
            <td style="font-size:.8rem;color:#555;max-width:200px"><?= htmlspecialchars($log['details']??'') ?></td>
            <td><small class="text-muted"><?= htmlspecialchars($log['ip_address']??'') ?></small></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- Mobile Cards -->
    <div class="d-md-none">
    <?php foreach ($logs as $log):
        $actionCls = match(true) {
            str_contains(strtolower($log['action']??''),'login')  => '#28a745',
            str_contains(strtolower($log['action']??''),'delete') => '#dc3545',
            str_contains(strtolower($log['action']??''),'add')    => '#0066cc',
            default => '#6c757d'
        };
    ?>
    <div class="border rounded p-2 mb-2" style="font-size:.82rem;border-left:3px solid <?= $actionCls ?>!important">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <strong><?= htmlspecialchars($log['full_name']??'Unknown') ?></strong>
                <span class="ms-1" style="color:<?= $actionCls ?>;font-weight:600"><?= htmlspecialchars($log['action']??'') ?></span>
                <span class="badge bg-light text-dark border ms-1" style="font-size:.65rem"><?= htmlspecialchars($log['module']??'') ?></span>
            </div>
            <small class="text-muted"><?= date('M d, H:i', strtotime($log['created_at'])) ?></small>
        </div>
        <?php if ($log['details']): ?>
        <div class="text-muted mt-1"><?= htmlspecialchars($log['details']) ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
