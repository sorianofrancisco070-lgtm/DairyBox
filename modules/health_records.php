<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$root      = '../';
$pageTitle = 'Health Records';
$db        = getDB();
$user      = currentUser();
$action    = $_GET['action'] ?? 'list';
$msg = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $bufId    = (int)($_POST['buffalo_id'] ?? 0);
    $date     = $_POST['record_date'] ?? date('Y-m-d');
    $ctype    = $_POST['condition_type'] ?? 'Routine Check';
    $diag     = trim($_POST['diagnosis'] ?? '');
    $symp     = trim($_POST['symptoms'] ?? '');
    $treat    = trim($_POST['treatment'] ?? '');
    $med      = trim($_POST['medicine_used'] ?? '');
    $dose     = trim($_POST['dosage'] ?? '');
    $vet      = trim($_POST['vet_name'] ?? '');
    $followup = $_POST['followup_date'] ?? null;
    $status   = $_POST['status'] ?? 'Active';
    $notes    = trim($_POST['notes'] ?? '');

    if (!$bufId) { $error = 'Buffalo is required.'; $action = 'add'; }
    else {
        if ($id > 0) {
            $db->prepare("UPDATE health_records SET buffalo_id=?,record_date=?,condition_type=?,diagnosis=?,symptoms=?,treatment=?,medicine_used=?,dosage=?,vet_name=?,followup_date=?,status=?,notes=? WHERE id=?")
               ->execute([$bufId,$date,$ctype,$diag,$symp,$treat,$med,$dose,$vet,$followup?:null,$status,$notes,$id]);
            // update buffalo health status
            $hMap = ['Illness'=>'Sick','Injury'=>'Sick','Routine Check'=>'Healthy','Disease Alert'=>'Sick','Other'=>'Healthy'];
            if ($status === 'Resolved') {
                $db->prepare("UPDATE buffaloes SET health_status='Recovered' WHERE id=?")->execute([$bufId]);
            } elseif (in_array($ctype,['Illness','Injury','Disease Alert'])) {
                $db->prepare("UPDATE buffaloes SET health_status='Under Treatment' WHERE id=?")->execute([$bufId]);
            }
            $msg = 'Health record updated.';
        } else {
            $db->prepare("INSERT INTO health_records (buffalo_id,record_date,condition_type,diagnosis,symptoms,treatment,medicine_used,dosage,vet_name,followup_date,status,notes,recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$bufId,$date,$ctype,$diag,$symp,$treat,$med,$dose,$vet,$followup?:null,$status,$notes,$user['id']]);
            if (in_array($ctype,['Illness','Injury','Disease Alert'])) {
                $db->prepare("UPDATE buffaloes SET health_status='Sick' WHERE id=?")->execute([$bufId]);
            }
            $msg = 'Health record added.';
        }
        $action = 'list';
    }
}

if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM health_records WHERE id=?")->execute([(int)$_GET['delete']]);
    $msg = 'Deleted.'; $action = 'list';
}

$editing = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $s = $db->prepare("SELECT * FROM health_records WHERE id=?");
    $s->execute([(int)$_GET['id']]);
    $editing = $s->fetch();
}

$bufFilter   = (int)($_GET['buffalo_id'] ?? 0);
$statusFilter= $_GET['sf'] ?? '';

$where = "WHERE 1"; $params = [];
if ($bufFilter) { $where .= " AND hr.buffalo_id=?"; $params[] = $bufFilter; }
if ($statusFilter) { $where .= " AND hr.status=?"; $params[] = $statusFilter; }

$stmt = $db->prepare("
    SELECT hr.*, b.tag_number, b.name
    FROM health_records hr JOIN buffaloes b ON b.id=hr.buffalo_id
    $where ORDER BY hr.record_date DESC
");
$stmt->execute($params);
$records = $stmt->fetchAll();

$bufList = $db->query("SELECT id,tag_number,name FROM buffaloes WHERE status='Active' ORDER BY tag_number")->fetchAll();

include '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><i class="fa fa-check me-2"></i><?= htmlspecialchars($msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card-section">
    <div class="section-title"><i class="fa fa-heartbeat me-2"></i><?= $editing ? 'Edit' : 'New' ?> Health Record</div>
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
            <div class="col-md-3">
                <label class="form-label fw-semibold">Date *</label>
                <input type="date" name="record_date" class="form-control" value="<?= $editing['record_date'] ?? date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Condition Type</label>
                <select name="condition_type" class="form-select">
                    <?php foreach (['Illness','Injury','Routine Check','Disease Alert','Other'] as $ct): ?>
                    <option value="<?= $ct ?>" <?= ($editing['condition_type']??'Routine Check')===$ct?'selected':'' ?>><?= $ct ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <?php foreach (['Active','Resolved','Monitoring'] as $st): ?>
                    <option value="<?= $st ?>" <?= ($editing['status']??'Active')===$st?'selected':'' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Diagnosis</label>
                <input type="text" name="diagnosis" class="form-control" value="<?= htmlspecialchars($editing['diagnosis'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Symptoms</label>
                <input type="text" name="symptoms" class="form-control" value="<?= htmlspecialchars($editing['symptoms'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Treatment</label>
                <textarea name="treatment" class="form-control" rows="2"><?= htmlspecialchars($editing['treatment'] ?? '') ?></textarea>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Medicine Used</label>
                <input type="text" name="medicine_used" class="form-control" value="<?= htmlspecialchars($editing['medicine_used'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Dosage</label>
                <input type="text" name="dosage" class="form-control" value="<?= htmlspecialchars($editing['dosage'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Veterinarian</label>
                <input type="text" name="vet_name" class="form-control" value="<?= htmlspecialchars($editing['vet_name'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Follow-up Date</label>
                <input type="date" name="followup_date" class="form-control" value="<?= $editing['followup_date'] ?? '' ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label fw-semibold">Notes</label>
                <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($editing['notes'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-success"><i class="fa fa-save me-1"></i><?= $editing ? 'Update' : 'Save Record' ?></button>
            <a href="health_records.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php else: ?>
<div class="card-section">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div class="section-title mb-0"><i class="fa fa-heartbeat me-2"></i>Health Records</div>
        <a href="?action=add" class="btn btn-danger btn-sm no-print"><i class="fa fa-plus me-1"></i>New Record</a>
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
                <?php foreach (['Active','Resolved','Monitoring'] as $st): ?>
                <option value="<?= $st ?>" <?= $statusFilter===$st?'selected':'' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-success w-100"><i class="fa fa-filter me-1"></i>Filter</button></div>
        <div class="col-md-2"><a href="health_records.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a></div>
    </form>
    <div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead><tr><th>#</th><th>Date</th><th>Buffalo</th><th>Type</th><th>Diagnosis</th><th>Medicine</th><th>Vet</th><th>Follow-up</th><th>Status</th><th class="no-print">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($records as $i => $r):
            $sCls = match($r['status']) {'Resolved'=>'badge-healthy','Active'=>'badge-sick','Monitoring'=>'badge-treated',default=>''};
        ?>
        <tr>
            <td><?= $i+1 ?></td>
            <td><?= $r['record_date'] ?></td>
            <td><span class="badge bg-success"><?= htmlspecialchars($r['tag_number']) ?></span> <?= htmlspecialchars($r['name']??'') ?></td>
            <td><span class="badge bg-secondary"><?= $r['condition_type'] ?></span></td>
            <td><?= htmlspecialchars($r['diagnosis'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['medicine_used'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['vet_name'] ?? '-') ?></td>
            <td><?= $r['followup_date'] ?? '-' ?></td>
            <td><span class="badge-custom <?= $sCls ?>"><?= $r['status'] ?></span></td>
            <td class="no-print">
                <a href="?action=edit&id=<?= $r['id'] ?>" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem"><i class="fa fa-edit"></i></a>
                <a href="?delete=<?= $r['id'] ?>" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem" onclick="return confirm('Delete?')"><i class="fa fa-trash"></i></a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($records)): ?><tr><td colspan="10" class="text-center text-muted py-3">No health records found.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
