<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$root      = '../';
$pageTitle = 'Inventory Management';
$db        = getDB();
$user      = currentUser();
$action    = $_GET['action'] ?? 'list';
$msg = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $name     = trim($_POST['item_name'] ?? '');
    $cat      = $_POST['category'] ?? 'Medicine';
    $unit     = trim($_POST['unit'] ?? '');
    $qty      = (float)($_POST['quantity'] ?? 0);
    $reorder  = (float)($_POST['reorder_level'] ?? 10);
    $expiry   = $_POST['expiry_date'] ?? null;
    $supplier = trim($_POST['supplier'] ?? '');
    $cost     = (float)($_POST['unit_cost'] ?? 0);
    $notes    = trim($_POST['notes'] ?? '');

    if (!$name) { $error = 'Item name required.'; $action = 'add'; }
    else {
        if ($id > 0) {
            $db->prepare("UPDATE inventory SET item_name=?,category=?,unit=?,quantity=?,reorder_level=?,expiry_date=?,supplier=?,unit_cost=?,notes=?,updated_by=? WHERE id=?")
               ->execute([$name,$cat,$unit,$qty,$reorder,$expiry?:null,$supplier,$cost?:null,$notes,$user['id'],$id]);
            $msg = 'Item updated.';
        } else {
            $db->prepare("INSERT INTO inventory (item_name,category,unit,quantity,reorder_level,expiry_date,supplier,unit_cost,notes,updated_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
               ->execute([$name,$cat,$unit,$qty,$reorder,$expiry?:null,$supplier,$cost?:null,$notes,$user['id']]);
            $msg = 'Item added.';
        }
        $action = 'list';
    }
}

if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM inventory WHERE id=?")->execute([(int)$_GET['delete']]);
    $msg = 'Deleted.'; $action = 'list';
}

$editing = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $s = $db->prepare("SELECT * FROM inventory WHERE id=?"); $s->execute([(int)$_GET['id']]); $editing = $s->fetch();
}

$catFilter = $_GET['cat'] ?? '';
$where = "WHERE 1"; $params = [];
if ($catFilter) { $where .= " AND category=?"; $params[] = $catFilter; }

$stmt = $db->prepare("SELECT * FROM inventory $where ORDER BY category,item_name");
$stmt->execute($params);
$items = $stmt->fetchAll();

$lowCount   = $db->query("SELECT COUNT(*) FROM inventory WHERE quantity <= reorder_level")->fetchColumn();
$expirySoon = $db->query("SELECT COUNT(*) FROM inventory WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(),INTERVAL 30 DAY)")->fetchColumn();

include '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($lowCount > 0): ?><div class="alert alert-warning"><i class="fa fa-exclamation-triangle me-2"></i><strong><?= $lowCount ?> item(s)</strong> at or below reorder level!</div><?php endif; ?>
<?php if ($expirySoon > 0): ?><div class="alert alert-danger"><i class="fa fa-calendar-times me-2"></i><strong><?= $expirySoon ?> item(s)</strong> expiring within 30 days!</div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card-section">
    <div class="section-title"><i class="fa fa-boxes me-2"></i><?= $editing ? 'Edit' : 'Add' ?> Inventory Item</div>
    <form method="POST">
        <input type="hidden" name="id" value="<?= $editing['id'] ?? 0 ?>">
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label fw-semibold">Item Name *</label>
                <input type="text" name="item_name" class="form-control" value="<?= htmlspecialchars($editing['item_name'] ?? '') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Category</label>
                <select name="category" class="form-select">
                    <?php foreach (['Medicine','Vaccine','Supply','Equipment','Feed','Other'] as $cat): ?>
                    <option value="<?= $cat ?>" <?= ($editing['category']??'Medicine')===$cat?'selected':'' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Unit</label>
                <input type="text" name="unit" class="form-control" value="<?= htmlspecialchars($editing['unit'] ?? '') ?>" placeholder="bottle, vial...">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Quantity</label>
                <input type="number" step="0.01" name="quantity" class="form-control" value="<?= $editing['quantity'] ?? 0 ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Reorder Level</label>
                <input type="number" step="0.01" name="reorder_level" class="form-control" value="<?= $editing['reorder_level'] ?? 10 ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Expiry Date</label>
                <input type="date" name="expiry_date" class="form-control" value="<?= $editing['expiry_date'] ?? '' ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Unit Cost (₱)</label>
                <input type="number" step="0.01" name="unit_cost" class="form-control" value="<?= $editing['unit_cost'] ?? '' ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Supplier</label>
                <input type="text" name="supplier" class="form-control" value="<?= htmlspecialchars($editing['supplier'] ?? '') ?>">
            </div>
            <div class="col-md-8">
                <label class="form-label fw-semibold">Notes</label>
                <input type="text" name="notes" class="form-control" value="<?= htmlspecialchars($editing['notes'] ?? '') ?>">
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-success"><i class="fa fa-save me-1"></i>Save</button>
            <a href="inventory.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php else: ?>
<div class="card-section">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div class="section-title mb-0"><i class="fa fa-boxes me-2"></i>Inventory</div>
        <a href="?action=add" class="btn btn-success btn-sm no-print"><i class="fa fa-plus me-1"></i>Add Item</a>
    </div>
    <form class="row g-2 mb-3 no-print" method="GET">
        <div class="col-md-3">
            <select name="cat" class="form-select form-select-sm">
                <option value="">All Categories</option>
                <?php foreach (['Medicine','Vaccine','Supply','Equipment','Feed','Other'] as $cat): ?>
                <option value="<?= $cat ?>" <?= $catFilter===$cat?'selected':'' ?>><?= $cat ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-success w-100"><i class="fa fa-filter me-1"></i>Filter</button></div>
        <div class="col-md-2"><a href="inventory.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a></div>
    </form>
    <div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead><tr><th>#</th><th>Item</th><th>Category</th><th>Qty</th><th>Unit</th><th>Reorder</th><th>Expiry</th><th>Unit Cost</th><th>Status</th><th class="no-print">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($items as $i => $inv):
            $lowStock  = $inv['quantity'] <= $inv['reorder_level'];
            $expiringSoon = $inv['expiry_date'] && strtotime($inv['expiry_date']) <= strtotime('+30 days');
            $rowCls    = $lowStock ? 'table-warning' : ($expiringSoon ? 'table-danger' : '');
        ?>
        <tr class="<?= $rowCls ?>">
            <td><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($inv['item_name']) ?></strong></td>
            <td><?= $inv['category'] ?></td>
            <td><strong><?= $inv['quantity'] ?></strong></td>
            <td><?= htmlspecialchars($inv['unit']) ?></td>
            <td><?= $inv['reorder_level'] ?></td>
            <td><?= $inv['expiry_date'] ?? '-' ?></td>
            <td><?= $inv['unit_cost'] ? '₱'.number_format($inv['unit_cost'],2) : '-' ?></td>
            <td>
                <?php if ($lowStock): ?><span class="badge-custom badge-pregnant">Low Stock</span><?php endif; ?>
                <?php if ($expiringSoon): ?><span class="badge-custom badge-sick">Expiring</span><?php endif; ?>
                <?php if (!$lowStock && !$expiringSoon): ?><span class="badge-custom badge-healthy">OK</span><?php endif; ?>
            </td>
            <td class="no-print">
                <a href="?action=edit&id=<?= $inv['id'] ?>" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem"><i class="fa fa-edit"></i></a>
                <a href="?delete=<?= $inv['id'] ?>" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem" onclick="return confirm('Delete?')"><i class="fa fa-trash"></i></a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($items)): ?><tr><td colspan="10" class="text-center text-muted py-3">No items found.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
