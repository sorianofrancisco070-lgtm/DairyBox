<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin('dairy_cooperative');

$root      = '../';
$pageTitle = 'Dairy Cooperative Dashboard';
$db        = getDB();
$user      = currentUser();

// POS / Sales stats
try {
    $todaySales    = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM coop_sales WHERE status='Completed' AND sale_date=CURDATE()")->fetchColumn();
    $monthSales    = $db->query("SELECT COALESCE(SUM(total_amount),0) FROM coop_sales WHERE status='Completed' AND MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE())")->fetchColumn();
    $todayTxns     = $db->query("SELECT COUNT(*) FROM coop_sales WHERE status='Completed' AND sale_date=CURDATE()")->fetchColumn();
    $lowStockProds = $db->query("SELECT COUNT(*) FROM coop_products WHERE is_active=1 AND stock_qty<=reorder_level")->fetchColumn();
    $totalProducts = $db->query("SELECT COUNT(*) FROM coop_products WHERE is_active=1")->fetchColumn();
    $monthTxns     = $db->query("SELECT COUNT(*) FROM coop_sales WHERE status='Completed' AND MONTH(sale_date)=MONTH(CURDATE()) AND YEAR(sale_date)=YEAR(CURDATE())")->fetchColumn();

    // Monthly sales chart data
    $monthlySales = $db->query("
        SELECT MONTH(sale_date) AS mo, SUM(total_amount) AS total
        FROM coop_sales
        WHERE status='Completed' AND YEAR(sale_date)=YEAR(CURDATE())
        GROUP BY mo ORDER BY mo
    ")->fetchAll();
    $moLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $moData   = array_fill(0, 12, 0);
    foreach ($monthlySales as $m) $moData[$m['mo'] - 1] = (float)$m['total'];

    // Top products this month
    $topProducts = $db->query("
        SELECT cp.name, cp.unit, SUM(si.quantity) AS qty_sold, SUM(si.line_total) AS revenue
        FROM coop_sale_items si
        JOIN coop_products cp ON cp.id = si.product_id
        JOIN coop_sales s ON s.id = si.sale_id
        WHERE s.status='Completed'
          AND MONTH(s.sale_date)=MONTH(CURDATE())
          AND YEAR(s.sale_date)=YEAR(CURDATE())
        GROUP BY cp.id ORDER BY revenue DESC LIMIT 5
    ")->fetchAll();

    // Recent transactions
    $recentSales = $db->query("
        SELECT receipt_number, customer_name, total_amount, payment_method, created_at
        FROM coop_sales
        WHERE status='Completed'
        ORDER BY created_at DESC LIMIT 8
    ")->fetchAll();

    $posReady = true;
} catch (Exception $e) {
    $posReady = false;
    $todaySales = $monthSales = $todayTxns = $lowStockProds = $totalProducts = $monthTxns = 0;
    $moData = array_fill(0, 12, 0);
    $topProducts = $recentSales = [];
}

include '../includes/header.php';
?>

<?php if (!$posReady): ?>
<div class="alert alert-warning">
    <i class="fa fa-database me-2"></i>
    POS tables not yet installed. Please run <code>database/coop_pos_migration.sql</code> to enable sales features.
</div>
<?php endif; ?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#d4edda"><i class="fa fa-cash-register text-success" style="font-size:1.4rem"></i></div>
            <div><p class="stat-label">Today's Sales</p><p class="stat-value text-success" style="font-size:1.3rem">₱<?= number_format($todaySales, 2) ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#cce5ff"><i class="fa fa-coins text-primary" style="font-size:1.4rem"></i></div>
            <div><p class="stat-label">Month Sales</p><p class="stat-value text-primary" style="font-size:1.3rem">₱<?= number_format($monthSales, 2) ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e2e3f3"><i class="fa fa-receipt text-secondary" style="font-size:1.4rem"></i></div>
            <div><p class="stat-label">Today's Transactions</p><p class="stat-value"><?= $todayTxns ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff3cd"><i class="fa fa-exclamation-triangle text-warning" style="font-size:1.4rem"></i></div>
            <div><p class="stat-label">Low Stock Products</p><p class="stat-value text-warning"><?= $lowStockProds ?></p></div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-bolt me-2"></i>Quick Actions</div>
    <div class="row g-2">
        <div class="col-6 col-md-3">
            <a href="../modules/pos.php" class="btn btn-success w-100 py-3 d-flex flex-column align-items-center gap-1" style="border-radius:10px">
                <i class="fa fa-cash-register fa-xl"></i>
                <span class="fw-semibold" style="font-size:.88rem">Point of Sale</span>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="../modules/products.php" class="btn btn-outline-success w-100 py-3 d-flex flex-column align-items-center gap-1" style="border-radius:10px">
                <i class="fa fa-box-open fa-xl"></i>
                <span class="fw-semibold" style="font-size:.88rem">Products</span>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="../modules/sales_reports.php" class="btn btn-outline-success w-100 py-3 d-flex flex-column align-items-center gap-1" style="border-radius:10px">
                <i class="fa fa-chart-bar fa-xl"></i>
                <span class="fw-semibold" style="font-size:.88rem">Sales Reports</span>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="../modules/receipts.php" class="btn btn-outline-success w-100 py-3 d-flex flex-column align-items-center gap-1" style="border-radius:10px">
                <i class="fa fa-receipt fa-xl"></i>
                <span class="fw-semibold" style="font-size:.88rem">Receipts</span>
            </a>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Monthly Sales Chart -->
    <div class="col-lg-8">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-chart-bar me-2"></i>Monthly Sales Revenue – <?= date('Y') ?></div>
            <canvas id="salesChart" height="100"></canvas>
        </div>
    </div>

    <!-- Top Products -->
    <div class="col-lg-4">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-star me-2"></i>Top Products – This Month</div>
            <?php if (empty($topProducts)): ?>
            <p class="text-muted text-center py-3">No sales this month yet.</p>
            <?php else: ?>
            <table class="table table-sm">
                <thead><tr><th>Product</th><th>Qty</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($topProducts as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td><?= number_format($p['qty_sold'], 1) ?> <small class="text-muted"><?= htmlspecialchars($p['unit']) ?></small></td>
                    <td class="text-success fw-semibold">₱<?= number_format($p['revenue'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <a href="../modules/sales_reports.php" class="btn btn-outline-success w-100 mt-2">
                <i class="fa fa-chart-bar me-1"></i>Full Sales Report
            </a>
        </div>
    </div>
</div>

<!-- Recent Transactions -->
<div class="card-section mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="section-title mb-0"><i class="fa fa-list me-2"></i>Recent Transactions</div>
        <a href="../modules/receipts.php" class="btn btn-sm btn-outline-success">View All</a>
    </div>
    <?php if (empty($recentSales)): ?>
    <p class="text-muted text-center py-3">No transactions yet. <a href="../modules/pos.php">Start a sale</a>.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead><tr><th>Receipt #</th><th>Customer</th><th>Payment</th><th>Total</th><th>Time</th></tr></thead>
            <tbody>
            <?php foreach ($recentSales as $s): ?>
            <tr>
                <td><code><?= htmlspecialchars($s['receipt_number']) ?></code></td>
                <td><?= htmlspecialchars($s['customer_name']) ?></td>
                <td><?= $s['payment_method'] ?></td>
                <td class="text-success fw-semibold">₱<?= number_format($s['total_amount'], 2) ?></td>
                <td class="text-muted" style="font-size:.82rem"><?= date('M d, h:i A', strtotime($s['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
new Chart(document.getElementById('salesChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($moLabels) ?>,
        datasets: [{
            label: 'Sales Revenue (₱)',
            data: <?= json_encode($moData) ?>,
            backgroundColor: 'rgba(40,167,69,.75)',
            borderColor: '#1a6b3c',
            borderWidth: 1,
            borderRadius: 5,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => '₱' + v.toLocaleString() } } }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
