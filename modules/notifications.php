<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$root      = '../';
$pageTitle = 'Notifications & Alerts';
$db        = getDB();
$user      = currentUser();

// Mark read
if (isset($_GET['mark_all'])) {
    $db->prepare("UPDATE notifications SET is_read=1 WHERE target_role=? OR target_role IS NULL")->execute([$user['role']]);
    header('Location: notifications.php?msg=All+marked+as+read');
    exit;
}
if (isset($_GET['read'])) {
    $db->prepare("UPDATE notifications SET is_read=1 WHERE id=?")->execute([(int)$_GET['read']]);
}
if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM notifications WHERE id=?")->execute([(int)$_GET['delete']]);
    header('Location: notifications.php');
    exit;
}

$msg = $_GET['msg'] ?? '';

$filter = $_GET['filter'] ?? 'all';
$where = "WHERE (target_role=? OR target_role IS NULL)";
$params = [$user['role']];
if ($filter === 'unread') { $where .= " AND is_read=0"; }
elseif ($filter === 'urgent') { $where .= " AND priority='urgent'"; }

$notifs = $db->prepare("SELECT n.*, b.tag_number, b.name as buffalo_name FROM notifications n LEFT JOIN buffaloes b ON b.id=n.buffalo_id $where ORDER BY n.is_read ASC, FIELD(n.priority,'urgent','high','medium','low'), n.created_at DESC");
$notifs->execute($params);
$notifs = $notifs->fetchAll();

$unreadCnt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE (target_role=? OR target_role IS NULL) AND is_read=0");
$unreadCnt->execute([$user['role']]);
$unreadCnt = $unreadCnt->fetchColumn();

include '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card-section">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div class="section-title mb-0">
            <i class="fa fa-bell me-2"></i>Notifications
            <?php if ($unreadCnt > 0): ?><span class="badge bg-danger"><?= $unreadCnt ?> unread</span><?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <a href="?mark_all=1" class="btn btn-sm btn-outline-success no-print"><i class="fa fa-check-double me-1"></i>Mark All Read</a>
        </div>
    </div>

    <div class="d-flex gap-2 mb-3 no-print flex-wrap">
        <a href="?filter=all"    class="btn btn-sm <?= $filter==='all'   ?'btn-success':'btn-outline-secondary' ?>">All</a>
        <a href="?filter=unread" class="btn btn-sm <?= $filter==='unread'?'btn-warning':'btn-outline-secondary' ?>">Unread</a>
        <a href="?filter=urgent" class="btn btn-sm <?= $filter==='urgent'?'btn-danger' :'btn-outline-secondary' ?>">Urgent</a>
    </div>

    <?php if (empty($notifs)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fa fa-bell-slash fa-3x mb-3 opacity-25"></i>
            <p>No notifications found.</p>
        </div>
    <?php else: ?>
    <?php foreach ($notifs as $n):
        $borderCls = match($n['priority']) {'urgent'=>'#dc3545','high'=>'#ffc107','medium'=>'#28a745',default=>'#6c757d'};
        $typeIcon  = match($n['type']) {'vaccination'=>'fa-syringe','breeding'=>'fa-venus-mars','calving'=>'fa-baby','health'=>'fa-heartbeat','production'=>'fa-tint',default=>'fa-bell'};
        $opacity   = $n['is_read'] ? 'opacity-50' : '';
    ?>
    <div class="notif-item <?= match($n['priority']) {'urgent'=>'urgent','high'=>'warning',default=>''} ?> mb-2 <?= $opacity ?>" style="border-left-color:<?= $borderCls ?>;position:relative">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <strong><i class="fa <?= $typeIcon ?> me-2" style="color:<?= $borderCls ?>"></i><?= htmlspecialchars($n['title']) ?></strong>
                <?php if (!$n['is_read']): ?><span class="badge bg-danger ms-1" style="font-size:.65rem">NEW</span><?php endif; ?>
                <br>
                <span class="text-muted" style="font-size:.82rem"><?= htmlspecialchars($n['message']) ?></span><br>
                <small class="text-muted">
                    <?php if ($n['buffalo_name']): ?><span class="badge bg-success"><?= htmlspecialchars($n['tag_number']) ?></span> <?php endif; ?>
                    <?php if ($n['due_date']): ?><i class="fa fa-calendar me-1"></i>Due: <?= $n['due_date'] ?> | <?php endif; ?>
                    <?= date('M d, Y h:i A', strtotime($n['created_at'])) ?>
                </small>
            </div>
            <div class="d-flex gap-1 no-print">
                <?php if (!$n['is_read']): ?>
                <a href="?read=<?= $n['id'] ?>&filter=<?= $filter ?>" class="btn btn-xs btn-outline-success" style="padding:.2rem .5rem;font-size:.7rem" title="Mark Read"><i class="fa fa-check"></i></a>
                <?php endif; ?>
                <a href="?delete=<?= $n['id'] ?>" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.7rem" title="Delete" onclick="return confirm('Delete notification?')"><i class="fa fa-trash"></i></a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
