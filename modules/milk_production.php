<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$root      = '../';
$pageTitle = 'Milk Production';
$db        = getDB();
$user      = currentUser();
$action    = $_GET['action'] ?? 'list';
$msg = $error = '';

// ---- SAVE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = (int)($_POST['id'] ?? 0);
    $bufId   = (int)($_POST['buffalo_id'] ?? 0);
    $date    = $_POST['record_date'] ?? date('Y-m-d');
    $session = $_POST['session'] ?? 'Morning';
    $qty     = (float)($_POST['quantity_liters'] ?? 0);
    $notes   = trim($_POST['quality_notes'] ?? '');

    if (!$bufId || $qty <= 0) {
        $error = 'Buffalo and quantity are required.'; $action = 'add';
    } else {
        if ($id > 0) {
            $db->prepare("UPDATE milk_production SET buffalo_id=?,record_date=?,session=?,quantity_liters=?,quality_notes=? WHERE id=?")
               ->execute([$bufId,$date,$session,$qty,$notes,$id]);
            $msg = 'Record updated.';
        } else {
            $db->prepare("INSERT INTO milk_production (buffalo_id,record_date,session,quantity_liters,quality_notes,recorded_by) VALUES (?,?,?,?,?,?)")
               ->execute([$bufId,$date,$session,$qty,$notes,$user['id']]);
            $msg = 'Milk production recorded.';
        }
        $action = 'list';
    }
}

if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM milk_production WHERE id=?")->execute([(int)$_GET['delete']]);
    $msg = 'Record deleted.'; $action = 'list';
}

$editing = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $s = $db->prepare("SELECT * FROM milk_production WHERE id=?");
    $s->execute([(int)$_GET['id']]);
    $editing = $s->fetch();
}

// ---- List Filters ----
$filterDate  = $_GET['filter_date'] ?? date('Y-m-d');
$filterBuf   = (int)($_GET['filter_buf'] ?? 0);

$where = "WHERE mp.record_date=?"; $params = [$filterDate];
if ($filterBuf) { $where .= " AND mp.buffalo_id=?"; $params[] = $filterBuf; }

$records = $db->prepare("
    SELECT mp.*, b.tag_number, b.name
    FROM milk_production mp JOIN buffaloes b ON b.id=mp.buffalo_id
    $where ORDER BY mp.session ASC
");
$records->execute($params);
$records = $records->fetchAll();

$dayTotal = array_sum(array_column($records,'quantity_liters'));

// Summary per buffalo today
$summaryStmt = $db->prepare("
    SELECT b.id, b.tag_number, b.name, SUM(mp.quantity_liters) as total, COUNT(*) as sessions
    FROM milk_production mp JOIN buffaloes b ON b.id=mp.buffalo_id
    $where GROUP BY b.id, b.tag_number, b.name ORDER BY total DESC
");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetchAll();

$bufList = $db->query("SELECT id,tag_number,name FROM buffaloes WHERE status='Active' AND sex='Female' ORDER BY tag_number")->fetchAll();

include '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><i class="fa fa-check me-2"></i><?= htmlspecialchars($msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card-section">
    <div class="section-title"><i class="fa fa-tint me-2"></i><?= $editing ? 'Edit' : 'Record' ?> Milk Production</div>
    <form method="POST">
        <input type="hidden" name="id" value="<?= $editing['id'] ?? 0 ?>">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Buffalo *</label>
                <select name="buffalo_id" class="form-select" required>
                    <option value="">-- Select Buffalo --</option>
                    <?php foreach ($bufList as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= ($editing['buffalo_id']??0)==$b['id']?'selected':'' ?>>
                        <?= htmlspecialchars($b['tag_number']) ?> – <?= htmlspecialchars($b['name'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Date *</label>
                <input type="date" name="record_date" class="form-control" value="<?= $editing['record_date'] ?? date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Session</label>
                <select name="session" class="form-select">
                    <?php foreach (['Morning','Afternoon','Evening'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($editing['session']??'Morning')===$s?'selected':'' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Quantity (Liters) *</label>
                <input type="number" step="0.01" min="0" name="quantity_liters" class="form-control" value="<?= $editing['quantity_liters'] ?? '' ?>" required>
            </div>
            <div class="col-md-12">
                <label class="form-label fw-semibold">Quality Notes</label>
                <input type="text" name="quality_notes" class="form-control" value="<?= htmlspecialchars($editing['quality_notes'] ?? '') ?>" placeholder="Optional notes on quality, abnormalities...">
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-success"><i class="fa fa-save me-1"></i><?= $editing ? 'Update' : 'Save Record' ?></button>
            <a href="milk_production.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php else: ?>
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#cce5ff"><i class="fa fa-tint text-primary" style="font-size:1.5rem"></i></div>
            <div><p class="stat-label">Total for <?= $filterDate ?></p><p class="stat-value text-primary"><?= number_format($dayTotal,2) ?> L</p></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#d4edda"><span style="font-size:1.5rem">🐃</span></div>
            <div><p class="stat-label">Buffaloes Milked</p><p class="stat-value text-success"><?= count($summary) ?></p></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff3cd"><i class="fa fa-list text-warning" style="font-size:1.5rem"></i></div>
            <div><p class="stat-label">Entries</p><p class="stat-value text-warning"><?= count($records) ?></p></div>
        </div>
    </div>
</div>

<div class="card-section">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div class="section-title mb-0"><i class="fa fa-tint me-2"></i>Milk Production Records</div>
        <a href="?action=add" class="btn btn-success btn-sm no-print"><i class="fa fa-plus me-1"></i>Record Milk</a>
    </div>
    <form class="row g-2 mb-3 no-print" method="GET">
        <div class="col-md-3">
            <input type="date" name="filter_date" class="form-control form-control-sm" value="<?= $filterDate ?>">
        </div>
        <div class="col-md-4">
            <select name="filter_buf" class="form-select form-select-sm">
                <option value="">All Buffaloes</option>
                <?php foreach ($bufList as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $filterBuf==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['tag_number']) ?> – <?= htmlspecialchars($b['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-success w-100"><i class="fa fa-filter me-1"></i>Filter</button></div>
        <div class="col-md-2"><a href="milk_production.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a></div>
    </form>

    <div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead><tr><th>#</th><th>Buffalo</th><th>Session</th><th>Liters</th><th>Notes</th><th class="no-print">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($records as $i => $r): ?>
        <tr>
            <td><?= $i+1 ?></td>
            <td><span class="badge bg-success"><?= htmlspecialchars($r['tag_number']) ?></span> <?= htmlspecialchars($r['name'] ?? '') ?></td>
            <td><?= $r['session'] ?></td>
            <td><strong><?= number_format($r['quantity_liters'],2) ?> L</strong></td>
            <td><?= htmlspecialchars($r['quality_notes'] ?? '-') ?></td>
            <td class="no-print">
                <a href="?action=edit&id=<?= $r['id'] ?>" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem"><i class="fa fa-edit"></i></a>
                <a href="?delete=<?= $r['id'] ?>" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem" onclick="return confirm('Delete?')"><i class="fa fa-trash"></i></a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($records)): ?>
        <tr><td colspan="6" class="text-center text-muted py-3">No records for this date.</td></tr>
        <?php endif; ?>
        </tbody>
        <?php if (!empty($records)): ?>
        <tfoot><tr class="table-success"><td colspan="3"><strong>Total</strong></td><td><strong><?= number_format($dayTotal,2) ?> L</strong></td><td colspan="2"></td></tr></tfoot>
        <?php endif; ?>
    </table>
    </div>
</div>

<?php if (!empty($summary)): ?>
<div class="card-section mt-3">
    <div class="section-title"><i class="fa fa-chart-bar me-2"></i>Summary by Buffalo – <?= $filterDate ?></div>
    <table class="table table-sm table-hover">
        <thead><tr><th>Tag</th><th>Name</th><th>Sessions</th><th>Total (L)</th><th>% of Day</th></tr></thead>
        <tbody>
        <?php foreach ($summary as $s): ?>
        <tr>
            <td><span class="badge bg-success"><?= htmlspecialchars($s['tag_number']) ?></span></td>
            <td><?= htmlspecialchars($s['name'] ?? '-') ?></td>
            <td><?= $s['sessions'] ?></td>
            <td><strong><?= number_format($s['total'],2) ?> L</strong></td>
            <td>
                <?php $pct = $dayTotal > 0 ? round($s['total']/$dayTotal*100,1) : 0; ?>
                <div class="progress" style="height:10px">
                    <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                </div>
                <small><?= $pct ?>%</small>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
