<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$root      = '../';
$pageTitle = 'Buffalo Records';
$db        = getDB();
$user      = currentUser();

$action    = $_GET['action'] ?? 'list';
$msg       = '';
$error     = '';

// ---- SAVE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = (int)($_POST['id'] ?? 0);
    $tag = trim($_POST['tag_number'] ?? '');
    $name= trim($_POST['name'] ?? '');
    $breed=trim($_POST['breed'] ?? '');
    $sex = $_POST['sex'] ?? 'Female';
    $dob = $_POST['date_of_birth'] ?? null;
    $wt  = (float)($_POST['weight_kg'] ?? 0);
    $color=trim($_POST['color'] ?? '');
    $acqDate = $_POST['acquisition_date'] ?? null;
    $acqType = $_POST['acquisition_type'] ?? 'Born on Farm';
    $status  = $_POST['status'] ?? 'Active';
    $hstatus = $_POST['health_status'] ?? 'Healthy';
    $notes   = trim($_POST['notes'] ?? '');
    $qr      = 'QR-' . strtoupper($tag);

    if (!$tag) { $error = 'Tag number is required.'; $action = 'add'; }
    else {
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE buffaloes SET tag_number=?,qr_code=?,name=?,breed=?,sex=?,date_of_birth=?,weight_kg=?,color=?,acquisition_date=?,acquisition_type=?,status=?,health_status=?,notes=? WHERE id=?");
            $stmt->execute([$tag,$qr,$name,$breed,$sex,$dob?:null,$wt?:null,$color,$acqDate?:null,$acqType,$status,$hstatus,$notes,$id]);
            $msg = 'Buffalo record updated.';
        } else {
            $stmt = $db->prepare("INSERT INTO buffaloes (tag_number,qr_code,name,breed,sex,date_of_birth,weight_kg,color,acquisition_date,acquisition_type,status,health_status,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$tag,$qr,$name,$breed,$sex,$dob?:null,$wt?:null,$color,$acqDate?:null,$acqType,$status,$hstatus,$notes,$user['id']]);
            $msg = 'Buffalo registered successfully.';
        }
        $db->prepare("INSERT INTO activity_log (user_id,action,module,details) VALUES (?,?,?,?)")
           ->execute([$user['id'], $id?'Update Buffalo':'Add Buffalo', 'Buffaloes', "Tag: $tag"]);
        $action = 'list';
    }
}

// ---- DELETE ----
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $db->prepare("DELETE FROM buffaloes WHERE id=?")->execute([$did]);
    $msg = 'Buffalo record deleted.';
    $action = 'list';
}

// ---- EDIT LOAD ----
$editing = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $editing = $db->prepare("SELECT * FROM buffaloes WHERE id=?");
    $editing->execute([(int)$_GET['id']]);
    $editing = $editing->fetch();
}

// ---- LIST ----
$search = trim($_GET['search'] ?? '');
$status_f = $_GET['status_f'] ?? '';
$where = "WHERE 1";
$params = [];
if ($search) { $where .= " AND (tag_number LIKE ? OR name LIKE ? OR breed LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($status_f) { $where .= " AND status=?"; $params[] = $status_f; }
$stmt = $db->prepare("SELECT * FROM buffaloes $where ORDER BY tag_number ASC");
$stmt->execute($params);
$buffaloes = $stmt->fetchAll();

include '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><i class="fa fa-check-circle me-2"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- ===== FORM ===== -->
<div class="card-section">
    <div class="section-title"><i class="fa fa-paw me-2"></i><?= $editing ? 'Edit Buffalo' : 'Register New Buffalo' ?></div>
    <form method="POST">
        <input type="hidden" name="id" value="<?= $editing['id'] ?? 0 ?>">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Tag Number *</label>
                <input type="text" name="tag_number" class="form-control" value="<?= htmlspecialchars($editing['tag_number'] ?? '') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Name</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($editing['name'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Breed</label>
                <input type="text" name="breed" class="form-control" value="<?= htmlspecialchars($editing['breed'] ?? '') ?>" placeholder="e.g. Murrah, Nili-Ravi">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Sex</label>
                <select name="sex" class="form-select">
                    <option value="Female" <?= ($editing['sex']??'Female')==='Female'?'selected':'' ?>>Female</option>
                    <option value="Male"   <?= ($editing['sex']??'')==='Male'?'selected':'' ?>>Male</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Date of Birth</label>
                <input type="date" name="date_of_birth" class="form-control" value="<?= $editing['date_of_birth'] ?? '' ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Weight (kg)</label>
                <input type="number" step="0.01" name="weight_kg" class="form-control" value="<?= $editing['weight_kg'] ?? '' ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Color</label>
                <input type="text" name="color" class="form-control" value="<?= htmlspecialchars($editing['color'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Acquisition Type</label>
                <select name="acquisition_type" class="form-select">
                    <?php foreach (['Born on Farm','Purchased','Donated'] as $at): ?>
                    <option value="<?= $at ?>" <?= ($editing['acquisition_type']??'')===$at?'selected':'' ?>><?= $at ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Acquisition Date</label>
                <input type="date" name="acquisition_date" class="form-control" value="<?= $editing['acquisition_date'] ?? '' ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <?php foreach (['Active','Sold','Dead','Transferred'] as $st): ?>
                    <option value="<?= $st ?>" <?= ($editing['status']??'Active')===$st?'selected':'' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Health Status</label>
                <select name="health_status" class="form-select">
                    <?php foreach (['Healthy','Sick','Under Treatment','Recovered'] as $hs): ?>
                    <option value="<?= $hs ?>" <?= ($editing['health_status']??'Healthy')===$hs?'selected':'' ?>><?= $hs ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-12">
                <label class="form-label fw-semibold">Notes</label>
                <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($editing['notes'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-success"><i class="fa fa-save me-1"></i><?= $editing ? 'Update' : 'Register Buffalo' ?></button>
            <a href="buffaloes.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php else: ?>
<!-- ===== LIST ===== -->
<div class="card-section">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div class="section-title mb-0"><i class="fa fa-paw me-2"></i>Buffalo Records</div>
        <a href="?action=add" class="btn btn-success btn-sm no-print"><i class="fa fa-plus me-1"></i>Register Buffalo</a>
    </div>
    <form class="row g-2 mb-3 no-print" method="GET">
        <div class="col-md-5">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by tag, name, breed..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3">
            <select name="status_f" class="form-select form-select-sm">
                <option value="">All Status</option>
                <?php foreach (['Active','Sold','Dead','Transferred'] as $st): ?>
                <option value="<?= $st ?>" <?= $status_f===$st?'selected':'' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-outline-success w-100"><i class="fa fa-search me-1"></i>Search</button>
        </div>
        <div class="col-md-2">
            <a href="buffaloes.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
        </div>
    </form>
    <div class="table-responsive">
    <table class="table table-hover table-sm">
        <thead>
            <tr><th>#</th><th>Tag</th><th>Name</th><th>Breed</th><th>Sex</th><th>DOB</th><th>Weight</th><th>Health</th><th>Status</th><th class="no-print">Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($buffaloes as $i => $b): 
            $hCls = match($b['health_status']) {
                'Healthy'        => 'badge-healthy',
                'Sick'           => 'badge-sick',
                'Under Treatment'=> 'badge-treated',
                default          => 'badge-custom bg-secondary text-white'
            };
        ?>
        <tr>
            <td><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($b['tag_number']) ?></strong></td>
            <td><?= htmlspecialchars($b['name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($b['breed'] ?? '-') ?></td>
            <td><?= $b['sex'] ?></td>
            <td><?= $b['date_of_birth'] ?? '-' ?></td>
            <td><?= $b['weight_kg'] ? $b['weight_kg'].' kg' : '-' ?></td>
            <td><span class="badge-custom <?= $hCls ?>"><?= $b['health_status'] ?></span></td>
            <td><?= $b['status'] ?></td>
            <td class="no-print">
                <div class="d-flex gap-1">
                    <a href="?action=edit&id=<?= $b['id'] ?>" class="btn btn-xs btn-outline-primary" title="Edit" style="padding:.2rem .5rem;font-size:.75rem"><i class="fa fa-edit"></i></a>
                    <a href="qr_scan.php?id=<?= $b['id'] ?>" class="btn btn-xs btn-outline-success" title="QR" style="padding:.2rem .5rem;font-size:.75rem"><i class="fa fa-qrcode"></i></a>
                    <a href="health_records.php?buffalo_id=<?= $b['id'] ?>" class="btn btn-xs btn-outline-danger" title="Health" style="padding:.2rem .5rem;font-size:.75rem"><i class="fa fa-heartbeat"></i></a>
                    <a href="?delete=<?= $b['id'] ?>" class="btn btn-xs btn-outline-danger" title="Delete" style="padding:.2rem .5rem;font-size:.75rem" onclick="return confirm('Delete this record?')"><i class="fa fa-trash"></i></a>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($buffaloes)): ?>
        <tr><td colspan="10" class="text-center text-muted py-4">No buffalo records found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    <small class="text-muted">Total: <?= count($buffaloes) ?> record(s)</small>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
