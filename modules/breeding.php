<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$root      = '../';
$pageTitle = 'Breeding & Calving';
$db        = getDB();
$user      = currentUser();
$tab       = $_GET['tab'] ?? 'breeding';
$action    = $_GET['action'] ?? 'list';
$msg = $error = '';

// ---- BREEDING SAVE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'breeding') {
    $id      = (int)($_POST['id'] ?? 0);
    $bufId   = (int)($_POST['buffalo_id'] ?? 0);
    $bdate   = $_POST['breeding_date'] ?? date('Y-m-d');
    $method  = $_POST['method'] ?? 'Natural';
    $sire    = trim($_POST['sire_name'] ?? '');
    $expCalv = $_POST['expected_calving'] ?? null;
    $pregSt  = $_POST['pregnancy_status'] ?? 'Not Confirmed';
    $checkDt = $_POST['pregnancy_check_date'] ?? null;
    $notes   = trim($_POST['notes'] ?? '');

    if (!$bufId) { $error = 'Buffalo is required.'; }
    else {
        if ($id > 0) {
            $db->prepare("UPDATE breeding_records SET buffalo_id=?,breeding_date=?,method=?,sire_name=?,expected_calving=?,pregnancy_status=?,pregnancy_check_date=?,notes=? WHERE id=?")
               ->execute([$bufId,$bdate,$method,$sire,$expCalv?:null,$pregSt,$checkDt?:null,$notes,$id]);
        } else {
            $db->prepare("INSERT INTO breeding_records (buffalo_id,breeding_date,method,sire_name,expected_calving,pregnancy_status,pregnancy_check_date,notes,recorded_by) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute([$bufId,$bdate,$method,$sire,$expCalv?:null,$pregSt,$checkDt?:null,$notes,$user['id']]);
            // Notification for calving
            if ($expCalv) {
                $b = $db->prepare("SELECT tag_number,name FROM buffaloes WHERE id=?"); $b->execute([$bufId]); $b=$b->fetch();
                $db->prepare("INSERT INTO notifications (type,title,message,buffalo_id,target_role,priority,due_date) VALUES (?,?,?,?,?,?,?)")
                   ->execute(['calving',"Expected Calving – {$b['tag_number']}","Expected calving on $expCalv for {$b['name']}",$bufId,'veterinarian','medium',$expCalv]);
            }
        }
        $msg = 'Breeding record saved.'; $action = 'list';
    }
}

// ---- CALVING SAVE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'calving') {
    $mId    = (int)($_POST['mother_id'] ?? 0);
    $brId   = (int)($_POST['breeding_id'] ?? 0) ?: null;
    $cdate  = $_POST['calving_date'] ?? date('Y-m-d');
    $ctag   = trim($_POST['calf_tag'] ?? '');
    $csex   = $_POST['calf_sex'] ?? 'Unknown';
    $cwt    = (float)($_POST['calf_weight_kg'] ?? 0);
    $dtype  = $_POST['delivery_type'] ?? 'Normal';
    $chealth= $_POST['calf_health'] ?? 'Healthy';
    $notes  = trim($_POST['notes'] ?? '');

    if (!$mId) { $error = 'Mother buffalo is required.'; }
    else {
        $db->prepare("INSERT INTO calving_records (mother_id,breeding_id,calving_date,calf_tag,calf_sex,calf_weight_kg,delivery_type,calf_health,notes,recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([$mId,$brId,$cdate,$ctag,$csex,$cwt?:null,$dtype,$chealth,$notes,$user['id']]);
        // Update breeding record
        if ($brId) $db->prepare("UPDATE breeding_records SET pregnancy_status='Delivered' WHERE id=?")->execute([$brId]);
        $msg = 'Calving record saved.'; $tab = 'calving'; $action = 'list';
    }
}

if (isset($_GET['delete_b'])) { $db->prepare("DELETE FROM breeding_records WHERE id=?")->execute([(int)$_GET['delete_b']]); $msg='Deleted.'; }
if (isset($_GET['delete_c'])) { $db->prepare("DELETE FROM calving_records WHERE id=?")->execute([(int)$_GET['delete_c']]); $msg='Deleted.'; $tab='calving'; }

$editBreeding = null;
if ($action === 'edit' && $tab === 'breeding' && isset($_GET['id'])) {
    $s = $db->prepare("SELECT * FROM breeding_records WHERE id=?"); $s->execute([(int)$_GET['id']]); $editBreeding = $s->fetch();
}

$breeding = $db->query("
    SELECT br.*, b.tag_number, b.name FROM breeding_records br
    JOIN buffaloes b ON b.id=br.buffalo_id ORDER BY br.breeding_date DESC
")->fetchAll();

$calving = $db->query("
    SELECT cr.*, b.tag_number, b.name as mother_name FROM calving_records cr
    JOIN buffaloes b ON b.id=cr.mother_id ORDER BY cr.calving_date DESC
")->fetchAll();

$femBufList = $db->query("SELECT id,tag_number,name FROM buffaloes WHERE status='Active' AND sex='Female' ORDER BY tag_number")->fetchAll();
$allBufList  = $db->query("SELECT id,tag_number,name FROM buffaloes WHERE status='Active' ORDER BY tag_number")->fetchAll();
$breedingList= $db->query("SELECT id,buffalo_id,breeding_date FROM breeding_records WHERE pregnancy_status='Confirmed' ORDER BY breeding_date DESC")->fetchAll();

include '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><i class="fa fa-check me-2"></i><?= htmlspecialchars($msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3 no-print">
    <li class="nav-item"><a class="nav-link <?= $tab==='breeding'?'active':'' ?>" href="?tab=breeding"><i class="fa fa-venus-mars me-1"></i>Breeding Records</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='calving'?'active':'' ?>" href="?tab=calving"><i class="fa fa-baby me-1"></i>Calving Records</a></li>
</ul>

<?php if ($tab === 'breeding'): ?>
<!-- ===== BREEDING ===== -->
<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card-section">
    <div class="section-title"><i class="fa fa-venus-mars me-2"></i><?= $editBreeding ? 'Edit' : 'New' ?> Breeding Record</div>
    <form method="POST">
        <input type="hidden" name="form_type" value="breeding">
        <input type="hidden" name="id" value="<?= $editBreeding['id'] ?? 0 ?>">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Female Buffalo *</label>
                <select name="buffalo_id" class="form-select" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($femBufList as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($editBreeding['buffalo_id']??0)==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['tag_number'].' – '.($b['name']??'')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Breeding Date *</label>
                <input type="date" name="breeding_date" class="form-control" value="<?= $editBreeding['breeding_date'] ?? date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Method</label>
                <select name="method" class="form-select">
                    <?php foreach (['Natural','Artificial Insemination'] as $m): ?>
                    <option value="<?= $m ?>" <?= ($editBreeding['method']??'Natural')===$m?'selected':'' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Sire Name</label>
                <input type="text" name="sire_name" class="form-control" value="<?= htmlspecialchars($editBreeding['sire_name'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Expected Calving</label>
                <input type="date" name="expected_calving" class="form-control" value="<?= $editBreeding['expected_calving'] ?? '' ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Pregnancy Status</label>
                <select name="pregnancy_status" class="form-select">
                    <?php foreach (['Not Confirmed','Confirmed','Failed','Delivered'] as $ps): ?>
                    <option value="<?= $ps ?>" <?= ($editBreeding['pregnancy_status']??'Not Confirmed')===$ps?'selected':'' ?>><?= $ps ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Pregnancy Check Date</label>
                <input type="date" name="pregnancy_check_date" class="form-control" value="<?= $editBreeding['pregnancy_check_date'] ?? '' ?>">
            </div>
            <div class="col-md-9">
                <label class="form-label fw-semibold">Notes</label>
                <input type="text" name="notes" class="form-control" value="<?= htmlspecialchars($editBreeding['notes'] ?? '') ?>">
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-success"><i class="fa fa-save me-1"></i>Save</button>
            <a href="?tab=breeding" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php else: ?>
<div class="card-section">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="section-title mb-0"><i class="fa fa-venus-mars me-2"></i>Breeding Records</div>
        <a href="?tab=breeding&action=add" class="btn btn-success btn-sm no-print"><i class="fa fa-plus me-1"></i>Add Breeding</a>
    </div>
    <div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead><tr><th>#</th><th>Buffalo</th><th>Date</th><th>Method</th><th>Sire</th><th>Expected Calving</th><th>Pregnancy</th><th>Check Date</th><th class="no-print">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($breeding as $i => $r):
            $pCls = match($r['pregnancy_status']) {'Confirmed'=>'badge-pregnant','Failed'=>'badge-sick','Delivered'=>'badge-healthy',default=>'badge-treated'};
        ?>
        <tr>
            <td><?= $i+1 ?></td>
            <td><span class="badge bg-success"><?= htmlspecialchars($r['tag_number']) ?></span> <?= htmlspecialchars($r['name']??'') ?></td>
            <td><?= $r['breeding_date'] ?></td>
            <td><?= $r['method'] ?></td>
            <td><?= htmlspecialchars($r['sire_name'] ?? '-') ?></td>
            <td><?= $r['expected_calving'] ?? '-' ?></td>
            <td><span class="badge-custom <?= $pCls ?>"><?= $r['pregnancy_status'] ?></span></td>
            <td><?= $r['pregnancy_check_date'] ?? '-' ?></td>
            <td class="no-print">
                <a href="?tab=breeding&action=edit&id=<?= $r['id'] ?>" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem"><i class="fa fa-edit"></i></a>
                <a href="?tab=breeding&delete_b=<?= $r['id'] ?>" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem" onclick="return confirm('Delete?')"><i class="fa fa-trash"></i></a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($breeding)): ?><tr><td colspan="9" class="text-center text-muted py-3">No breeding records.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ===== CALVING ===== -->
<?php if ($action === 'add'): ?>
<div class="card-section">
    <div class="section-title"><i class="fa fa-baby me-2"></i>New Calving Record</div>
    <form method="POST">
        <input type="hidden" name="form_type" value="calving">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Mother Buffalo *</label>
                <select name="mother_id" class="form-select" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($femBufList as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['tag_number'].' – '.($b['name']??'')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Breeding Record (Optional)</label>
                <select name="breeding_id" class="form-select">
                    <option value="">-- None --</option>
                    <?php foreach ($breedingList as $br): ?>
                    <option value="<?= $br['id'] ?>">ID <?= $br['id'] ?> – Buffalo #<?= $br['buffalo_id'] ?> (<?= $br['breeding_date'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Calving Date *</label>
                <input type="date" name="calving_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Calf Tag</label>
                <input type="text" name="calf_tag" class="form-control" placeholder="e.g. CALF-001">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Calf Sex</label>
                <select name="calf_sex" class="form-select">
                    <?php foreach (['Unknown','Female','Male'] as $cs): ?><option value="<?= $cs ?>"><?= $cs ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Calf Weight (kg)</label>
                <input type="number" step="0.1" name="calf_weight_kg" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Delivery Type</label>
                <select name="delivery_type" class="form-select">
                    <?php foreach (['Normal','Assisted','Cesarean'] as $dt): ?><option value="<?= $dt ?>"><?= $dt ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Calf Health</label>
                <select name="calf_health" class="form-select">
                    <?php foreach (['Healthy','Weak','Stillborn'] as $ch): ?><option value="<?= $ch ?>"><?= $ch ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-9">
                <label class="form-label fw-semibold">Notes</label>
                <input type="text" name="notes" class="form-control">
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-success"><i class="fa fa-save me-1"></i>Save Calving</button>
            <a href="?tab=calving" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php else: ?>
<div class="card-section">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="section-title mb-0"><i class="fa fa-baby me-2"></i>Calving Records</div>
        <a href="?tab=calving&action=add" class="btn btn-success btn-sm no-print"><i class="fa fa-plus me-1"></i>Add Calving</a>
    </div>
    <div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead><tr><th>#</th><th>Mother</th><th>Calving Date</th><th>Calf Tag</th><th>Sex</th><th>Weight</th><th>Delivery</th><th>Calf Health</th><th class="no-print">Delete</th></tr></thead>
        <tbody>
        <?php foreach ($calving as $i => $r):
            $hCls = match($r['calf_health']) {'Healthy'=>'badge-healthy','Stillborn'=>'badge-sick',default=>'badge-treated'};
        ?>
        <tr>
            <td><?= $i+1 ?></td>
            <td><span class="badge bg-success"><?= htmlspecialchars($r['tag_number']) ?></span> <?= htmlspecialchars($r['mother_name']??'') ?></td>
            <td><?= $r['calving_date'] ?></td>
            <td><?= htmlspecialchars($r['calf_tag'] ?? '-') ?></td>
            <td><?= $r['calf_sex'] ?></td>
            <td><?= $r['calf_weight_kg'] ? $r['calf_weight_kg'].' kg' : '-' ?></td>
            <td><?= $r['delivery_type'] ?></td>
            <td><span class="badge-custom <?= $hCls ?>"><?= $r['calf_health'] ?></span></td>
            <td class="no-print"><a href="?tab=calving&delete_c=<?= $r['id'] ?>" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem" onclick="return confirm('Delete?')"><i class="fa fa-trash"></i></a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($calving)): ?><tr><td colspan="9" class="text-center text-muted py-3">No calving records.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
