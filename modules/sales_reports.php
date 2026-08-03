<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin('dairy_cooperative');

$root      = '../';
$pageTitle = 'Sales Reports';
$db        = getDB();
$user      = currentUser();

// ── Date filters ─────────────────────────────────────────
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');
$groupBy  = $_GET['group_by']  ?? 'day';

// ── Summary Stats ─────────────────────────────────────────
$stmt = $db->prepare("
    SELECT
        COUNT(*)                   AS total_transactions,
        COALESCE(SUM(total_amount),0)   AS total_revenue,
        COALESCE(SUM(discount_amount),0) AS total_discounts,
        COALESCE(SUM(tax_amount),0)     AS total_tax,
        COALESCE(AVG(total_amount),0)   AS avg_sale
    FROM coop_sales
    WHERE status='Completed' AND sale_date BETWEEN ? AND ?
");
$stmt->execute([$dateFrom, $dateTo]);
$summary = $stmt->fetch();

// ── Sales by Period ───────────────────────────────────────
$groupExpr = match($groupBy) {
    'month' => "DATE_FORMAT(sale_date,'%Y-%m')",
    'week'  => "YEARWEEK(sale_date,1)",
    default => "DATE_FORMAT(sale_date,'%Y-%m-%d')",
};
$stmtPeriod = $db->prepare("
    SELECT $groupExpr AS period,
           COUNT(*) AS transactions,
           SUM(total_amount) AS revenue
    FROM coop_sales
    WHERE status='Completed' AND sale_date BETWEEN ? AND ?
    GROUP BY period ORDER BY period
");
$stmtPeriod->execute([$dateFrom, $dateTo]);
$periodData = $stmtPeriod->fetchAll();

// ── Top Products ──────────────────────────────────────────
$stmtTop = $db->prepare("
    SELECT cp.id, cp.name, cp.unit,
           SUM(si.quantity) AS total_qty,
           SUM(si.line_total) AS total_revenue
    FROM coop_sale_items si
    JOIN coop_products cp ON cp.id = si.product_id
    JOIN coop_sales s ON s.id = si.sale_id
    WHERE s.status='Completed' AND s.sale_date BETWEEN ? AND ?
    GROUP BY cp.id, cp.name, cp.unit ORDER BY total_revenue DESC LIMIT 10
");
$stmtTop->execute([$dateFrom, $dateTo]);
$topProducts = $stmtTop->fetchAll();

// ── Payment Method Breakdown ──────────────────────────────
$stmtPay = $db->prepare("
    SELECT payment_method, COUNT(*) AS cnt, SUM(total_amount) AS revenue
    FROM coop_sales WHERE status='Completed' AND sale_date BETWEEN ? AND ?
    GROUP BY payment_method ORDER BY revenue DESC
");
$stmtPay->execute([$dateFrom, $dateTo]);
$payBreakdown = $stmtPay->fetchAll();

// ── Recent Sales ──────────────────────────────────────────
$stmtSales = $db->prepare("
    SELECT s.*, u.full_name AS cashier
    FROM coop_sales s
    LEFT JOIN users u ON u.id = s.created_by
    WHERE s.sale_date BETWEEN ? AND ?
    ORDER BY s.created_at DESC LIMIT 50
");
$stmtSales->execute([$dateFrom, $dateTo]);
$recentSales = $stmtSales->fetchAll();

// ── Chart data ────────────────────────────────────────────
$chartLabels  = array_column($periodData, 'period');
$chartRevenue = array_map(fn($r) => (float)$r['revenue'], $periodData);

// ── CSV Export ────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "sales_report_{$dateFrom}_to_{$dateTo}.csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ["DairyBox Sales Report – {$dateFrom} to {$dateTo}"]);
    fputcsv($out, ["Generated: ".date('M d, Y h:i A')." by ".$user['full_name']]);
    fputcsv($out, []);
    // Summary
    fputcsv($out, ['=== SUMMARY ===']);
    fputcsv($out, ['Transactions','Total Revenue','Average Sale','Total Discounts','Total Tax']);
    fputcsv($out, [
        $summary['total_transactions'],
        number_format($summary['total_revenue'],2),
        number_format($summary['avg_sale'],2),
        number_format($summary['total_discounts'],2),
        number_format($summary['total_tax'],2),
    ]);
    fputcsv($out, []);
    // Top products
    fputcsv($out, ['=== TOP SELLING PRODUCTS ===']);
    fputcsv($out, ['#','Product','Unit','Qty Sold','Revenue']);
    foreach ($topProducts as $i => $tp) {
        fputcsv($out, [$i+1, $tp['name'], $tp['unit'],
            number_format($tp['total_qty'],2), number_format($tp['total_revenue'],2)]);
    }
    fputcsv($out, []);
    // Payment breakdown
    fputcsv($out, ['=== PAYMENT METHODS ===']);
    fputcsv($out, ['Method','Transactions','Revenue']);
    foreach ($payBreakdown as $pay) {
        fputcsv($out, [$pay['payment_method'], $pay['cnt'], number_format($pay['revenue'],2)]);
    }
    fputcsv($out, []);
    // Transactions
    fputcsv($out, ['=== TRANSACTIONS ===']);
    fputcsv($out, ['Receipt #','Date','Customer','Payment','Subtotal','Discount','Tax','Total','Status']);
    foreach ($recentSales as $s) {
        fputcsv($out, [
            $s['receipt_number'], $s['sale_date'], $s['customer_name'],
            $s['payment_method'], number_format($s['subtotal'],2),
            number_format($s['discount_amount'],2), number_format($s['tax_amount'],2),
            number_format($s['total_amount'],2), $s['status'],
        ]);
    }
    fclose($out);
    exit;
}

include '../includes/header.php';
?>

<!-- Date Filter Bar -->
<div class="card-section no-print mb-3">
    <form class="row g-2 align-items-end" method="GET">
        <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $dateFrom ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $dateTo ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Group By</label>
            <select name="group_by" class="form-select form-select-sm">
                <option value="day"   <?= $groupBy==='day'   ?'selected':'' ?>>Daily</option>
                <option value="week"  <?= $groupBy==='week'  ?'selected':'' ?>>Weekly</option>
                <option value="month" <?= $groupBy==='month' ?'selected':'' ?>>Monthly</option>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-success btn-sm w-100 mt-1"><i class="fa fa-filter me-1"></i>Apply</button></div>
        <div class="col-md-2">
            <a href="?date_from=<?= date('Y-m-01') ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-secondary w-100 mt-1">This Month</a>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#d4edda"><i class="fa fa-receipt text-success" style="font-size:1.4rem"></i></div>
            <div><p class="stat-label">Transactions</p><p class="stat-value text-success"><?= number_format($summary['total_transactions']) ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#cce5ff"><i class="fa fa-coins text-primary" style="font-size:1.4rem"></i></div>
            <div><p class="stat-label">Total Revenue</p><p class="stat-value text-primary" style="font-size:1.2rem">₱<?= number_format($summary['total_revenue'], 2) ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff3cd"><i class="fa fa-chart-line text-warning" style="font-size:1.4rem"></i></div>
            <div><p class="stat-label">Avg Sale</p><p class="stat-value text-warning" style="font-size:1.2rem">₱<?= number_format($summary['avg_sale'], 2) ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f8d7da"><i class="fa fa-tags text-danger" style="font-size:1.4rem"></i></div>
            <div><p class="stat-label">Total Discounts</p><p class="stat-value text-danger" style="font-size:1.2rem">₱<?= number_format($summary['total_discounts'], 2) ?></p></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <!-- Revenue Chart -->
    <div class="col-lg-8">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-chart-bar me-2"></i>Revenue Trend
                <small class="text-muted fw-normal"><?= date('M d', strtotime($dateFrom)) ?> – <?= date('M d, Y', strtotime($dateTo)) ?></small>
            </div>
            <?php if (empty($periodData)): ?>
            <p class="text-muted text-center py-4">No sales data for selected period.</p>
            <?php else: ?>
            <canvas id="revenueChart" height="100"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Methods -->
    <div class="col-lg-4">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-credit-card me-2"></i>Payment Methods</div>
            <?php if (empty($payBreakdown)): ?>
            <p class="text-muted text-center py-4">No data.</p>
            <?php else: ?>
            <canvas id="payChart" height="180"></canvas>
            <table class="table table-sm mt-2">
                <tbody>
                <?php foreach ($payBreakdown as $pay): ?>
                <tr>
                    <td><?= $pay['payment_method'] ?></td>
                    <td class="text-end"><?= $pay['cnt'] ?> sales</td>
                    <td class="text-end fw-semibold">₱<?= number_format($pay['revenue'],2) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <!-- Top Products -->
    <div class="col-lg-5">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-star me-2"></i>Top Selling Products</div>
            <?php if (empty($topProducts)): ?>
            <p class="text-muted text-center py-4">No sales in this period.</p>
            <?php else: ?>
            <table class="table table-sm">
                <thead><tr><th>#</th><th>Product</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($topProducts as $i => $tp): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($tp['name']) ?></td>
                    <td><?= number_format($tp['total_qty'],2) ?> <?= htmlspecialchars($tp['unit']) ?></td>
                    <td class="text-success fw-semibold">₱<?= number_format($tp['total_revenue'],2) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="col-lg-7">
        <div class="card-section">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="section-title mb-0"><i class="fa fa-list me-2"></i>Recent Transactions</div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-success no-print" onclick="window.print()"><i class="fa fa-print me-1"></i>Print</button>
                    <button class="btn btn-sm btn-outline-primary no-print" onclick="savePDF()"><i class="fa fa-file-pdf me-1"></i>Save PDF</button>
                    <a href="?date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&group_by=<?= $groupBy ?>&export=csv"
                       class="btn btn-sm btn-outline-secondary no-print"><i class="fa fa-file-csv me-1"></i>Export CSV</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead><tr><th>Receipt #</th><th>Date</th><th>Customer</th><th>Payment</th><th>Total</th><th>Status</th><th class="no-print">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentSales as $sale): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($sale['receipt_number']) ?></code></td>
                        <td><?= date('M d', strtotime($sale['sale_date'])) ?></td>
                        <td><?= htmlspecialchars($sale['customer_name']) ?></td>
                        <td><?= $sale['payment_method'] ?></td>
                        <td class="fw-semibold text-success">₱<?= number_format($sale['total_amount'],2) ?></td>
                        <td>
                            <?php $sc = match($sale['status']) { 'Completed'=>'badge-healthy', 'Voided'=>'badge-sick', default=>'badge-pregnant' }; ?>
                            <span class="badge-custom <?= $sc ?>"><?= $sale['status'] ?></span>
                        </td>
                        <td class="no-print">
                            <a href="receipts.php?id=<?= $sale['id'] ?>" class="btn btn-xs btn-outline-primary" title="View Receipt"><i class="fa fa-receipt"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentSales)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">No sales in this period.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($periodData)): ?>
<script>
new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Revenue (₱)',
            data: <?= json_encode($chartRevenue) ?>,
            backgroundColor: 'rgba(40,167,69,.75)',
            borderColor: '#1a6b3c',
            borderWidth: 1,
            borderRadius: 5,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => '₱'+v.toLocaleString() } } } }
});
</script>
<?php endif; ?>

<?php if (!empty($payBreakdown)): ?>
<script>
new Chart(document.getElementById('payChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($payBreakdown,'payment_method')) ?>,
        datasets: [{
            data: <?= json_encode(array_map(fn($p)=>(float)$p['revenue'],$payBreakdown)) ?>,
            backgroundColor: ['#28a745','#17a2b8','#ffc107','#6f42c1','#dc3545'],
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
});
</script>
<?php endif; ?>

<script>
function savePDF() {
    const orig = document.title;
    document.title = 'Sales_Report_<?= $dateFrom ?>_to_<?= $dateTo ?>';
    window.print();
    document.title = orig;
}
</script>

<?php include '../includes/footer.php'; ?>
