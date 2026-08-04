<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/check_low_stock.php';
requireLogin('dairy_cooperative');

$root      = '../';
$pageTitle = 'Cooperative Inventory';
$db        = getDB();
$user      = currentUser();
$msg = $error = '';

// ── Handle Stock Adjustment ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $type      = $_POST['movement_type'] ?? 'Stock In';
    $qty       = (float)($_POST['quantity'] ?? 0);
    $notes     = trim($_POST['notes'] ?? '');

    if (!$productId || $qty <= 0) {
        $error = 'Please select a product and enter a valid quantity.';
    } else {
        $allowed = ['Stock In','Stock Out','Adjustment','Return'];
        if (!in_array($type, $allowed)) $type = 'Stock In';

        $db->prepare("INSERT INTO coop_inventory (product_id,movement_type,quantity,notes,recorded_by) VALUES (?,?,?,?,?)")
           ->execute([$productId, $type, $qty, $notes, $user['id']]);

        // Update stock
        if (in_array($type, ['Stock In','Return'])) {
            $db->prepare("UPDATE coop_products SET stock_qty = stock_qty + ? WHERE id=?")->execute([$qty, $productId]);
        } elseif (in_array($type, ['Stock Out','Adjustment'])) {
            $db->prepare("UPDATE coop_products SET stock_qty = GREATEST(0, stock_qty - ?) WHERE id=?")->execute([$qty, $productId]);
        }
        $msg = 'Stock movement recorded.';

        // Check and notify if any product is now low stock
        checkLowStockNotifications($db);
    }
}

// ── Stats ─────────────────────────────────────────────────
$totalProducts = $db->query("SELECT COUNT(*) FROM coop_products WHERE is_active=1")->fetchColumn();
$lowStock      = $db->query("SELECT COUNT(*) FROM coop_products WHERE is_active=1 AND stock_qty<=reorder_level")->fetchColumn();
$totalStockVal = $db->query("SELECT COALESCE(SUM(stock_qty * cost_price),0) FROM coop_products WHERE is_active=1")->fetchColumn();

// ── Product list for stock status ────────────────────────
$products = $db->query("SELECT * FROM coop_products WHERE is_active=1 ORDER BY category,name")->fetchAll();

// ── Movement log ─────────────────────────────────────────
$prodFilter = isset($_GET['product']) ? (int)$_GET['product'] : 0;
$typeFilter = $_GET['type'] ?? '';
$where = "WHERE 1"; $params = [];
if ($prodFilter) { $where .= " AND ci.product_id=?"; $params[] = $prodFilter; }
if ($typeFilter) { $where .= " AND ci.movement_type=?"; $params[] = $typeFilter; }
$logStmt = $db->prepare("
    SELECT ci.*, cp.name AS product_name, cp.unit, u.full_name AS recorded_by_name
    FROM coop_inventory ci
    JOIN coop_products cp ON cp.id = ci.product_id
    LEFT JOIN users u ON u.id = ci.recorded_by
    $where
    ORDER BY ci.created_at DESC
    LIMIT 200
");
$logStmt->execute($params);
$movements = $logStmt->fetchAll();

// Active products for select
$allProducts = $db->query("SELECT id,name,unit,stock_qty FROM coop_products WHERE is_active=1 ORDER BY name")->fetchAll();

include '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($msg) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($lowStock > 0): ?><div class="alert alert-warning"><i class="fa fa-exclamation-triangle me-2"></i><strong><?= $lowStock ?> product(s)</strong> at or below reorder level.</div><?php endif; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#d4edda"><i class="fa fa-warehouse text-success" style="font-size:1.4rem"></i></div>
            <div><p class="stat-label">Active Products</p><p class="stat-value text-success"><?= $totalProducts ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff3cd"><i class="fa fa-exclamation-triangle text-warning" style="font-size:1.4rem"></i></div>
            <div><p class="stat-label">Low Stock</p><p class="stat-value text-warning"><?= $lowStock ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#cce5ff"><i class="fa fa-peso-sign text-primary" style="font-size:1.4rem"></i></div>
            <div><p class="stat-label">Stock Value (₱)</p><p class="stat-value text-primary"><?= number_format($totalStockVal, 2) ?></p></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Stock Status Table -->
    <div class="col-lg-7">
        <div class="card-section">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="section-title mb-0"><i class="fa fa-boxes me-2"></i>Current Stock Levels</div>
                <a href="products.php?action=add" class="btn btn-success btn-sm no-print"><i class="fa fa-plus me-1"></i>Add Product</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead><tr><th>Product</th><th>Category</th><th>Stock</th><th>Reorder</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($products as $p):
                        $isLow = $p['stock_qty'] <= $p['reorder_level'];
                    ?>
                    <tr class="<?= $isLow ? 'table-warning' : '' ?>">
                        <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                        <td><?= $p['category'] ?></td>
                        <td>
                            <strong><?= number_format($p['stock_qty'], 2) ?></strong>
                            <small class="text-muted"> <?= htmlspecialchars($p['unit']) ?></small>
                        </td>
                        <td><?= number_format($p['reorder_level'], 2) ?></td>
                        <td>
                            <?php if ($isLow): ?>
                                <span class="badge-custom badge-sick">Low Stock</span>
                            <?php else: ?>
                                <span class="badge-custom badge-healthy">OK</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($products)): ?><tr><td colspan="5" class="text-center text-muted py-3">No products found. <a href="products.php?action=add">Add one</a></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Stock Adjustment Form -->
    <div class="col-lg-5">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-exchange-alt me-2"></i>Record Stock Movement</div>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Product *</label>
                    <select name="product_id" class="form-select" required>
                        <option value="">— Select Product —</option>
                        <?php foreach ($allProducts as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= number_format($p['stock_qty'],2) ?> <?= htmlspecialchars($p['unit']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Movement Type *</label>
                    <select name="movement_type" class="form-select" required>
                        <option value="Stock In">📦 Stock In</option>
                        <option value="Stock Out">📤 Stock Out</option>
                        <option value="Adjustment">🔧 Adjustment (reduce)</option>
                        <option value="Return">↩️ Return</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Quantity *</label>
                    <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" required placeholder="e.g. 50">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Notes</label>
                    <input type="text" name="notes" class="form-control" placeholder="Delivery, spoilage, correction…">
                </div>
                <button type="submit" class="btn btn-success w-100"><i class="fa fa-save me-1"></i>Record Movement</button>
            </form>
        </div>
    </div>
</div>

<!-- Movement Log -->
<div class="card-section mt-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div class="section-title mb-0"><i class="fa fa-history me-2"></i>Stock Movement Log</div>
    </div>
    <form class="row g-2 mb-3 no-print" method="GET">
        <div class="col-md-4">
            <select name="product" class="form-select form-select-sm">
                <option value="">All Products</option>
                <?php foreach ($allProducts as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $prodFilter === $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="type" class="form-select form-select-sm">
                <option value="">All Types</option>
                <?php foreach (['Stock In','Stock Out','Adjustment','Sale','Return'] as $t): ?>
                <option value="<?= $t ?>" <?= $typeFilter === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-success w-100"><i class="fa fa-filter me-1"></i>Filter</button></div>
        <div class="col-md-2"><a href="coop_inventory.php" class="btn btn-sm btn-outline-secondary w-100">Reset</a></div>
    </form>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead><tr><th>Date</th><th>Product</th><th>Type</th><th>Qty</th><th>Unit</th><th>Notes</th><th>By</th></tr></thead>
            <tbody>
            <?php foreach ($movements as $m):
                $typeBadge = match($m['movement_type']) {
                    'Stock In'   => 'badge-healthy',
                    'Stock Out'  => 'badge-sick',
                    'Sale'       => 'badge-treated',
                    'Return'     => 'badge-pregnant',
                    default      => 'badge-custom',
                };
            ?>
            <tr>
                <td><?= date('M d, Y H:i', strtotime($m['created_at'])) ?></td>
                <td><?= htmlspecialchars($m['product_name']) ?></td>
                <td><span class="badge-custom <?= $typeBadge ?>"><?= $m['movement_type'] ?></span></td>
                <td><strong><?= number_format($m['quantity'], 2) ?></strong></td>
                <td><?= htmlspecialchars($m['unit']) ?></td>
                <td><?= htmlspecialchars($m['notes'] ?? '') ?></td>
                <td><?= htmlspecialchars($m['recorded_by_name'] ?? 'System') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($movements)): ?><tr><td colspan="7" class="text-center text-muted py-3">No movements recorded yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
