<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$root      = '../';
$pageTitle = 'Vaccination Records';
$db        = getDB();
$user      = currentUser();
$action    = $_GET['action'] ?? 'list';
$msg = $error = '';

// Auto-update overdue + fire alerts for newly overdue vaccinations
$db->query("UPDATE vaccinations SET status='Overdue' WHERE next_due_date < CURDATE() AND status='Scheduled'");

// Auto-alert for overdue vaccinations (creates notification if not already exists unread)
try {
    $overdueNew = $db->query("
        SELECT v.*, b.tag_number, b.name
        FROM vaccinations v JOIN buffaloes b ON b.id=v.buffalo_id
        WHERE v.status='Overdue'
        AND NOT EXISTS (
            SELECT 1 FROM notifications n
            WHERE n.type='vaccination'
            AND n.buffalo_id=v.buffalo_id
            AND n.title LIKE CONCAT('Overdue Vaccine:%',v.vaccine_name,'%')
            AND n.is_read=0
        )
        LIMIT 20
    ")->fetchAll();
    foreach ($overdueNew as $ov) {
        $label = ($ov['name']?$ov['name'].' ':'').'('.$ov['tag_number'].')';
        $title = "Overdue Vaccine: {$ov['vaccine_name']} – {$ov['tag_number']}";
        $msg2  = "🚨 {$ov['vaccine_name']} for {$label} was due on {$ov['next_due_date']} and has not been administered. Risk of preventable disease. Schedule immediately.";
        foreach (['farm_manager','veterinarian','farm_caretaker'] as $tr) {
            $db->prepare("INSERT INTO notifications (type,title,message,buffalo_id,target_role,is_read,priority,due_date) VALUES ('vaccination',?,?,?,?,0,'urgent',CURDATE())")
               ->execute([$title,$msg2,$ov['buffalo_id'],$tr]);
        }
    }
} catch (Exception $e) { /* non-fatal */ }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = (int)($_POST['id'] ?? 0);
    $bufId   = (int)($_POST['buffalo_id'] ?? 0);
    $vname   = trim($_POST['vaccine_name'] ?? '');
    $vtype   = trim($_POST['vaccine_type'] ?? '');
    $adate   = $_POST['administered_date'] ?? date('Y-m-d');
    $ndate   = $_POST['next_due_date'] ?? null;
    $adBy    = trim($_POST['administered_by'] ?? '');
    $batch   = trim($_POST['batch_number'] ?? '');
    $dose    = trim($_POST['dose'] ?? '');
    $status  = $_POST['status'] ?? 'Done';
    $notes   = trim($_POST['notes'] ?? '');

    if (!$bufId || !$vname) { $error = 'Buffalo and vaccine name are required.'; $action = 'add'; }
    else {
        if ($id > 0) {
            $db->prepare("UPDATE vaccinations SET buffalo_id=?,vaccine_name=?,vaccine_type=?,administered_date=?,next_due_date=?,administered_by=?,batch_number=?,dose=?,status=?,notes=? WHERE id=?")
               ->execute([$bufId,$vname,$vtype,$adate,$ndate?:null,$adBy,$batch,$dose,$status,$notes,$id]);
            $msg = 'Vaccination record updated.';
        } else {
            $db->prepare("INSERT INTO vaccinations (buffalo_id,vaccine_name,vaccine_type,administered_date,next_due_date,administered_by,batch_number,dose,status,notes,recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$bufId,$vname,$vtype,$adate,$ndate?:null,$adBy,$batch,$dose,$status,$notes,$user['id']]);
            // Auto-create notification if scheduled
            if ($ndate) {
                $b = $db->prepare("SELECT tag_number,name FROM buffaloes WHERE id=?"); $b->execute([$bufId]); $b=$b->fetch();
                $db->prepare("INSERT INTO notifications (type,title,message,buffalo_id,target_role,priority,due_date) VALUES (?,?,?,?,?,?,?)")
                   ->execute(['vaccination',"Upcoming: $vname – {$b['tag_number']}","Vaccine due on $ndate for {$b['name']} ({$b['tag_number']})",$bufId,'farm_manager','medium',$ndate]);
            }
            $msg = 'Vaccination recorded.';
        }
        $action = 'list';
    }
}

if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM vaccinations WHERE id=?")->execute([(int)$_GET['delete']]);
    $msg = 'Deleted.'; $action = 'list';
}

$editing = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $s = $db->prepare("SELECT * FROM vaccinations WHERE id=?");
    $s->execute([(int)$_GET['id']]);
    $editing = $s->fetch();
}

$statusFilter = $_GET['sf'] ?? '';
$bufFilter    = (int)($_GET['buffalo_id'] ?? 0);
$where = "WHERE 1"; $params = [];
if ($statusFilter) { $where .= " AND v.status=?"; $params[] = $statusFilter; }
if ($bufFilter)    { $where .= " AND v.buffalo_id=?"; $params[] = $bufFilter; }

$stmt = $db->prepare("SELECT v.*, b.tag_number, b.name FROM vaccinations v JOIN buffaloes b ON b.id=v.buffalo_id $where ORDER BY v.next_due_date ASC");
$stmt->execute($params);
$records = $stmt->fetchAll();

$bufList = $db->query("SELECT id,tag_number,name FROM buffaloes WHERE status='Active' ORDER BY tag_number")->fetchAll();

$overdueCnt   = $db->query("SELECT COUNT(*) FROM vaccinations WHERE status='Overdue'")->fetchColumn();
$scheduledCnt = $db->query("SELECT COUNT(*) FROM vaccinations WHERE status='Scheduled'")->fetchColumn();
$doneCnt      = $db->query("SELECT COUNT(*) FROM vaccinations WHERE status='Done'")->fetchColumn();

include '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><i class="fa fa-check me-2"></i><?= htmlspecialchars($msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card-section">
    <div class="section-title"><i class="fa fa-syringe me-2"></i><?= $editing ? 'Edit' : 'New' ?> Vaccination Record</div>
    <form method="POST">
        <input type="hidden" name="id" value="<?= $editing['id'] ?? 0 ?>">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Buffalo *</label>
                <select name="buffalo_id" class="form-select" required>
                    <option value="">-- Select Buffalo --</option>
                    <?php foreach ($bufList as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($editing['buffalo_id']??$bufFilter)==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['tag_number'].' – '.($b['name']??'')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Vaccine Name *</label>
                <input type="text" name="vaccine_name" class="form-control" value="<?= htmlspecialchars($editing['vaccine_name'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Vaccine Type</label>
                <input type="text" name="vaccine_type" class="form-control" value="<?= htmlspecialchars($editing['vaccine_type'] ?? '') ?>" placeholder="e.g. Foot and Mouth Disease">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Date Administered</label>
                <input type="date" name="administered_date" class="form-control" value="<?= $editing['administered_date'] ?? date('Y-m-d') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Next Due Date</label>
                <input type="date" name="next_due_date" class="form-control" value="<?= $editing['next_due_date'] ?? '' ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Administered By</label>
                <input type="text" name="administered_by" class="form-control" value="<?= htmlspecialchars($editing['administered_by'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <?php foreach (['Done','Scheduled','Overdue'] as $st): ?>
                    <option value="<?= $st ?>" <?= ($editing['status']??'Done')===$st?'selected':'' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Batch Number</label>
                <input type="text" name="batch_number" class="form-control" value="<?= htmlspecialchars($editing['batch_number'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Dose</label>
                <input type="text" name="dose" class="form-control" value="<?= htmlspecialchars($editing['dose'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Notes</label>
                <input type="text" name="notes" class="form-control" value="<?= htmlspecialchars($editing['notes'] ?? '') ?>">
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-success"><i class="fa fa-save me-1"></i><?= $editing ? 'Update' : 'Save' ?></button>
            <a href="vaccinations.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php else: ?>
<div class="row g-3 mb-3">
    <div class="col-4">
        <div class="stat-card"><div class="stat-icon" style="background:#f8d7da"><i class="fa fa-exclamation-circle text-danger" style="font-size:1.4rem"></i></div><div><p class="stat-label">Overdue</p><p class="stat-value text-danger"><?= $overdueCnt ?></p></div></div>
    </div>
    <div class="col-4">
        <div class="stat-card"><div class="stat-icon" style="background:#fff3cd"><i class="fa fa-clock text-warning" style="font-size:1.4rem"></i></div><div><p class="stat-label">Scheduled</p><p class="stat-value text-warning"><?= $scheduledCnt ?></p></div></div>
    </div>
    <div class="col-4">
        <div class="stat-card"><div class="stat-icon" style="background:#d4edda"><i class="fa fa-check-circle text-success" style="font-size:1.4rem"></i></div><div><p class="stat-label">Done</p><p class="stat-value text-success"><?= $doneCnt ?></p></div></div>
    </div>
</div>

<div class="card-section">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div class="section-title mb-0"><i class="fa fa-syringe me-2"></i>Vaccination Records</div>
        <a href="?action=add" class="btn btn-warning btn-sm no-print"><i class="fa fa-plus me-1"></i>New Vaccination</a>
    </div>
    <form class="row g-2 mb-3 no-print" method="GET">
        <div class="col-md-4">
            <select name="buffalo_id" class="form-select form-select-sm">
                <option value="">All Buffaloes</option>
                <?php foreach ($bufList as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $bufFilter==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['tag_number'].' – '.($b['name']??'')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="sf" class="form-select form-select-sm">
                <option value="">All Status</option>
                <?php foreach (['Done','Scheduled','Overdue'] as $st): ?>
                <option value="<?= $st ?>" <?= $statusFilter===$st?'selected':'' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-success w-100"><i class="fa fa-filter me-1"></i>Filter</button></div>
        <div class="col-md-2"><a href="vaccinations.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a></div>
    </form>
    <div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead><tr><th>#</th><th>Buffalo</th><th>Vaccine</th><th>Type</th><th>Administered</th><th>Next Due</th><th>By</th><th>Status</th><th class="no-print">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($records as $i => $r):
            $sCls = match($r['status']) {'Done'=>'badge-healthy','Overdue'=>'badge-sick','Scheduled'=>'badge-treated',default=>''};
            $rowCls = $r['status']==='Overdue' ? 'table-danger' : ($r['status']==='Scheduled' ? 'table-warning' : '');
        ?>
        <tr class="<?= $rowCls ?>">
            <td><?= $i+1 ?></td>
            <td><span class="badge bg-success"><?= htmlspecialchars($r['tag_number']) ?></span> <?= htmlspecialchars($r['name']??'') ?></td>
            <td><?= htmlspecialchars($r['vaccine_name']) ?></td>
            <td><?= htmlspecialchars($r['vaccine_type'] ?? '-') ?></td>
            <td><?= $r['administered_date'] ?></td>
            <td><?= $r['next_due_date'] ?? '-' ?></td>
            <td><?= htmlspecialchars($r['administered_by'] ?? '-') ?></td>
            <td><span class="badge-custom <?= $sCls ?>"><?= $r['status'] ?></span></td>
            <td class="no-print">
                <a href="?action=edit&id=<?= $r['id'] ?>" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem"><i class="fa fa-edit"></i></a>
                <a href="?delete=<?= $r['id'] ?>" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem" onclick="return confirm('Delete?')"><i class="fa fa-trash"></i></a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($records)): ?><tr><td colspan="9" class="text-center text-muted py-3">No vaccination records found.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
