<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/check_low_stock.php';
requireLogin('dairy_cooperative');

$root      = '../';
$pageTitle = 'Product Management';
$db        = getDB();
$user      = currentUser();
$action    = $_GET['action'] ?? 'list';
$msg = $error = '';

// ── Handle DELETE ──────────────────────────────────────────
if (isset($_GET['delete'])) {
    $del = (int)$_GET['delete'];
    // Check if product has sales
    $used = $db->prepare("SELECT COUNT(*) FROM coop_sale_items WHERE product_id=?");
    $used->execute([$del]);
    if ($used->fetchColumn() > 0) {
        $error  = 'Cannot delete – product has existing sales records. Deactivate it instead.';
        $action = 'list';
    } else {
        $db->prepare("DELETE FROM coop_products WHERE id=?")->execute([$del]);
        $msg    = 'Product deleted.';
        $action = 'list';
    }
}

// ── Handle TOGGLE ACTIVE ──────────────────────────────────
if (isset($_GET['toggle'])) {
    $db->prepare("UPDATE coop_products SET is_active = 1 - is_active WHERE id=?")
       ->execute([(int)$_GET['toggle']]);
    header('Location: products.php'); exit;
}

// ── Handle SAVE (add / edit) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $code     = strtoupper(trim($_POST['product_code'] ?? ''));
    $name     = trim($_POST['name'] ?? '');
    $cat      = trim($_POST['category'] ?? '');
    if ($cat === '') $cat = 'Uncategorized';
    $desc     = trim($_POST['description'] ?? '');
    $unit     = trim($_POST['unit']    ?? 'liter');
    $sell     = (float)($_POST['selling_price'] ?? 0);
    $cost     = (float)($_POST['cost_price']    ?? 0);
    $stock    = (float)($_POST['stock_qty']     ?? 0);
    $reorder  = (float)($_POST['reorder_level'] ?? 10);
    $active   = isset($_POST['is_active']) ? 1 : 0;

    if (!$code || !$name || $sell < 0) {
        $error  = 'Product code, name, and a valid selling price are required.';
        $action = ($id > 0) ? 'edit' : 'add';
    } else {
        if ($id > 0) {
            $db->prepare("UPDATE coop_products SET product_code=?,name=?,category=?,description=?,unit=?,selling_price=?,cost_price=?,stock_qty=?,reorder_level=?,is_active=? WHERE id=?")
               ->execute([$code,$name,$cat,$desc,$unit,$sell,$cost,$stock,$reorder,$active,$id]);
            // Stock adjustment record if stock changed
            $old = $db->prepare("SELECT stock_qty FROM coop_products WHERE id=?");
            $old->execute([$id]);
            $msg = 'Product updated.';
        } else {
            $db->prepare("INSERT INTO coop_products (product_code,name,category,description,unit,selling_price,cost_price,stock_qty,reorder_level,is_active,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$code,$name,$cat,$desc,$unit,$sell,$cost,$stock,$reorder,$active,$user['id']]);
            $newId = $db->lastInsertId();
            if ($stock > 0) {
                $db->prepare("INSERT INTO coop_inventory (product_id,movement_type,quantity,notes,recorded_by) VALUES (?,?,?,?,?)")
                   ->execute([$newId,'Stock In',$stock,'Initial stock on product creation',$user['id']]);
            }
            $msg = 'Product added.';
        }
        // Check low stock after save
        checkLowStockNotifications($db);
        $action = 'list';
    }
}

// ── Edit record ───────────────────────────────────────────
$editing = null;
if (($action === 'edit') && isset($_GET['id'])) {
    $s = $db->prepare("SELECT * FROM coop_products WHERE id=?");
    $s->execute([(int)$_GET['id']]);
    $editing = $s->fetch();
    if (!$editing) { $action = 'list'; }
}

// ── List query ────────────────────────────────────────────
$catFilter    = $_GET['cat']    ?? '';
$statusFilter = $_GET['status'] ?? '';
$where = "WHERE 1"; $params = [];
if ($catFilter)    { $where .= " AND category LIKE ?"; $params[] = "%$catFilter%"; }
if ($statusFilter !== '') { $where .= " AND is_active=?"; $params[] = (int)$statusFilter; }
$stmt = $db->prepare("SELECT * FROM coop_products $where ORDER BY category,name");
$stmt->execute($params);
$products = $stmt->fetchAll();

$totalProducts = $db->query("SELECT COUNT(*) FROM coop_products WHERE is_active=1")->fetchColumn();
$lowStock      = $db->query("SELECT COUNT(*) FROM coop_products WHERE is_active=1 AND stock_qty<=reorder_level")->fetchColumn();

// Auto-fix: ensure category column is VARCHAR (not ENUM) so free-text values are accepted
try {
    $colType = $db->query("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='coop_products' AND COLUMN_NAME='category'")->fetchColumn();
    if (strtolower($colType) === 'enum') {
        $db->exec("ALTER TABLE coop_products MODIFY COLUMN category VARCHAR(80) DEFAULT 'Uncategorized'");
        $db->exec("UPDATE coop_products SET category='Uncategorized' WHERE category IS NULL OR category=''");
    }
} catch (Exception $e) { /* silently skip if no permission */ }

// Distinct categories for filter datalist (from existing data)
$existingCats = $db->query("SELECT DISTINCT category FROM coop_products WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

include '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($lowStock > 0): ?><div class="alert alert-warning"><i class="fa fa-exclamation-triangle me-2"></i><strong><?= $lowStock ?> product(s)</strong> at or below reorder level.</div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<!-- ── ADD / EDIT FORM ── -->
<div class="card-section">
    <div class="section-title"><i class="fa fa-box-open me-2"></i><?= $editing ? 'Edit Product' : 'Add New Product' ?></div>
    <form method="POST">
        <input type="hidden" name="id" value="<?= $editing['id'] ?? 0 ?>">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Product Code *</label>
                <input type="text" name="product_code" class="form-control"
                       value="<?= htmlspecialchars($editing['product_code'] ?? '') ?>" required
                       placeholder="PRD-001">
            </div>
            <div class="col-md-5">
                <label class="form-label fw-semibold">Product Name *</label>
                <input type="text" name="name" class="form-control"
                       value="<?= htmlspecialchars($editing['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Category</label>
                <input type="text" name="category" class="form-control"
                       value="<?= htmlspecialchars($editing['category'] ?? '') ?>"
                       placeholder="e.g. Milk, Cheese, Butter…" maxlength="80">
            </div>
            <div class="col-md-12">
                <label class="form-label fw-semibold">Description</label>
                <input type="text" name="description" class="form-control"
                       value="<?= htmlspecialchars($editing['description'] ?? '') ?>"
                       placeholder="Short product description">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Unit</label>
                <input type="text" name="unit" class="form-control"
                       value="<?= htmlspecialchars($editing['unit'] ?? 'liter') ?>"
                       placeholder="liter, bottle, pack…">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Selling Price (₱) *</label>
                <input type="number" step="0.01" min="0" name="selling_price" class="form-control"
                       value="<?= $editing['selling_price'] ?? '' ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Cost Price (₱)</label>
                <input type="number" step="0.01" min="0" name="cost_price" class="form-control"
                       value="<?= $editing['cost_price'] ?? '' ?>">
            </div>
            <?php if (!$editing): ?>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Opening Stock</label>
                <input type="number" step="0.01" min="0" name="stock_qty" class="form-control" value="0">
            </div>
            <?php else: ?>
            <input type="hidden" name="stock_qty" value="<?= $editing['stock_qty'] ?>">
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Reorder Level</label>
                <input type="number" step="0.01" min="0" name="reorder_level" class="form-control"
                       value="<?= $editing['reorder_level'] ?? 10 ?>">
            </div>
            <div class="col-md-12">
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                           <?= ($editing['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="isActive">Active (visible in POS)</label>
                </div>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-success"><i class="fa fa-save me-1"></i>Save Product</button>
            <a href="products.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php else: ?>
<!-- ── PRODUCT LIST ── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#d4edda"><i class="fa fa-box-open text-success" style="font-size:1.4rem"></i></div>
            <div><p class="stat-label">Active Products</p><p class="stat-value text-success"><?= $totalProducts ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff3cd"><i class="fa fa-exclamation-triangle text-warning" style="font-size:1.4rem"></i></div>
            <div><p class="stat-label">Low Stock Items</p><p class="stat-value text-warning"><?= $lowStock ?></p></div>
        </div>
    </div>
</div>

<div class="card-section">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div class="section-title mb-0"><i class="fa fa-box-open me-2"></i>Products</div>
        <a href="?action=add" class="btn btn-success btn-sm no-print"><i class="fa fa-plus me-1"></i>Add Product</a>
    </div>

    <form class="row g-2 mb-3 no-print" method="GET">
        <div class="col-md-3">
            <input type="text" name="cat" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($catFilter) ?>" placeholder="Filter by category…"
                   list="catSuggestions">
            <datalist id="catSuggestions">
                <?php foreach ($existingCats as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>">
                <?php endforeach; ?>
            </datalist>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
                <option value="">All Status</option>
                <option value="1" <?= $statusFilter === '1' ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= $statusFilter === '0' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-success w-100"><i class="fa fa-filter me-1"></i>Filter</button></div>
        <div class="col-md-2"><a href="products.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a></div>
    </form>

    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>#</th><th>Code</th><th>Name</th><th>Category</th>
                    <th>Unit</th><th>Selling (₱)</th><th>Cost (₱)</th>
                    <th>Stock</th><th>Reorder</th><th>Status</th>
                    <th class="no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $i => $p):
                $isLow = $p['stock_qty'] <= $p['reorder_level'];
                $rowCls = !$p['is_active'] ? 'table-secondary' : ($isLow ? 'table-warning' : '');
            ?>
            <tr class="<?= $rowCls ?>">
                <td><?= $i + 1 ?></td>
                <td><code><?= htmlspecialchars($p['product_code']) ?></code></td>
                <td><strong><?= htmlspecialchars($p['name']) ?></strong>
                    <?php if ($p['description']): ?><br><small class="text-muted"><?= htmlspecialchars($p['description']) ?></small><?php endif; ?>
                </td>
                <td><?= htmlspecialchars($p['category'] ?: '—') ?></td>
                <td><?= htmlspecialchars($p['unit']) ?></td>
                <td class="text-success fw-semibold">₱<?= number_format($p['selling_price'], 2) ?></td>
                <td>₱<?= number_format($p['cost_price'], 2) ?></td>
                <td>
                    <?php if ($isLow && $p['is_active']): ?>
                        <span class="badge-custom badge-sick"><?= number_format($p['stock_qty'], 2) ?></span>
                    <?php else: ?>
                        <strong><?= number_format($p['stock_qty'], 2) ?></strong>
                    <?php endif; ?>
                </td>
                <td><?= number_format($p['reorder_level'], 2) ?></td>
                <td>
                    <?php if ($p['is_active']): ?>
                        <span class="badge-custom badge-healthy">Active</span>
                    <?php else: ?>
                        <span class="badge-custom" style="background:#e9ecef;color:#6c757d">Inactive</span>
                    <?php endif; ?>
                </td>
                <td class="no-print">
                    <a href="?action=edit&id=<?= $p['id'] ?>" class="btn btn-xs btn-outline-primary" title="Edit"><i class="fa fa-edit"></i></a>
                    <a href="?toggle=<?= $p['id'] ?>" class="btn btn-xs <?= $p['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                       title="<?= $p['is_active'] ? 'Deactivate' : 'Activate' ?>">
                        <i class="fa fa-<?= $p['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                    </a>
                    <a href="?delete=<?= $p['id'] ?>" class="btn btn-xs btn-outline-danger" title="Delete"
                       onclick="return confirm('Delete this product?')"><i class="fa fa-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
            <tr><td colspan="11" class="text-center text-muted py-3">No products found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
