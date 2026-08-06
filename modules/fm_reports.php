<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin('farm_manager');

$root      = '../';
$pageTitle = 'All Reports';
$db        = getDB();
$user      = currentUser();

$month = (int)($_GET['month'] ?? date('m'));
$year  = (int)($_GET['year']  ?? date('Y'));
$tab   = $_GET['tab'] ?? 'sales';
$monthName = date('F', mktime(0,0,0,$month,1));

// ── Year list ────────────────────────────────────────────
$years = [];
try {
    $y1 = $db->query("SELECT DISTINCT YEAR(record_date) FROM milk_production ORDER BY 1 DESC")->fetchAll(PDO::FETCH_COLUMN);
    $y2 = $db->query("SELECT DISTINCT YEAR(sale_date) FROM coop_sales ORDER BY 1 DESC")->fetchAll(PDO::FETCH_COLUMN);
    $years = array_unique(array_merge($y1, $y2, [date('Y')]));
    rsort($years);
} catch (Exception $e) { $years = [date('Y')]; }

// ── 1. SALES SUMMARY ────────────────────────────────────
$salesSum = ['total'=>0,'count'=>0,'cash'=>0,'gcash'=>0,'other'=>0];
$salesByDay = [];
try {
    $s = $db->prepare("SELECT payment_method, COUNT(*) as cnt, SUM(total_amount) as rev FROM coop_sales WHERE status='Completed' AND MONTH(sale_date)=? AND YEAR(sale_date)=? GROUP BY payment_method");
    $s->execute([$month,$year]);
    foreach ($s->fetchAll() as $r) {
        $salesSum['total'] += $r['rev'];
        $salesSum['count'] += $r['cnt'];
        $key = strtolower($r['payment_method']);
        if ($key === 'cash') $salesSum['cash'] += $r['rev'];
        elseif (in_array($key,['gcash','maya'])) $salesSum['gcash'] += $r['rev'];
        else $salesSum['other'] += $r['rev'];
    }
    $sd = $db->prepare("SELECT sale_date, COUNT(*) as txn, SUM(total_amount) as rev FROM coop_sales WHERE status='Completed' AND MONTH(sale_date)=? AND YEAR(sale_date)=? GROUP BY sale_date ORDER BY sale_date");
    $sd->execute([$month,$year]); $salesByDay = $sd->fetchAll();
} catch (Exception $e) {}

// ── 2. TOP PRODUCTS ──────────────────────────────────────
$topProducts = [];
try {
    $tp = $db->prepare("SELECT cp.name, cp.unit, SUM(si.quantity) as qty, SUM(si.line_total) as rev FROM coop_sale_items si JOIN coop_products cp ON cp.id=si.product_id JOIN coop_sales s ON s.id=si.sale_id WHERE s.status='Completed' AND MONTH(s.sale_date)=? AND YEAR(s.sale_date)=? GROUP BY cp.id, cp.name, cp.unit ORDER BY rev DESC LIMIT 10");
    $tp->execute([$month,$year]); $topProducts = $tp->fetchAll();
} catch (Exception $e) {}

// ── 3. INVENTORY STATUS ──────────────────────────────────
$inventory = [];
try {
    $inventory = $db->query("SELECT name, category, unit, stock_qty, reorder_level, selling_price, cost_price, (stock_qty * selling_price) as stock_value FROM coop_products WHERE is_active=1 ORDER BY category, name")->fetchAll();
} catch (Exception $e) {}
$totalStockValue = array_sum(array_column($inventory,'stock_value'));
$lowStockCount   = count(array_filter($inventory, fn($p) => $p['stock_qty'] <= $p['reorder_level']));

// ── 4. MILK PRODUCTION REPORT ───────────────────────────
$milkData = [];
$milkTotal = 0;
try {
    $mp = $db->prepare("SELECT b.tag_number, b.name, b.breed, SUM(mp.quantity_liters) as total, AVG(mp.quantity_liters) as avg_sess, COUNT(DISTINCT mp.record_date) as days FROM milk_production mp JOIN buffaloes b ON b.id=mp.buffalo_id WHERE MONTH(mp.record_date)=? AND YEAR(mp.record_date)=? GROUP BY b.id, b.tag_number, b.name, b.breed ORDER BY total DESC");
    $mp->execute([$month,$year]); $milkData = $mp->fetchAll();
    $milkTotal = array_sum(array_column($milkData,'total'));
} catch (Exception $e) {}

// ── 5. HEALTH SUMMARY ───────────────────────────────────
$healthData = [];
try {
    $hd = $db->prepare("SELECT condition_type, status, COUNT(*) as cnt FROM health_records WHERE MONTH(record_date)=? AND YEAR(record_date)=? GROUP BY condition_type, status ORDER BY cnt DESC");
    $hd->execute([$month,$year]); $healthData = $hd->fetchAll();
} catch (Exception $e) {}

// ── 6. VACCINATION SUMMARY ──────────────────────────────
$vaccData = [];
try {
    $vd = $db->prepare("SELECT vaccine_name, status, COUNT(*) as cnt FROM vaccinations WHERE MONTH(administered_date)=? AND YEAR(administered_date)=? GROUP BY vaccine_name, status ORDER BY cnt DESC");
    $vd->execute([$month,$year]); $vaccData = $vd->fetchAll();
} catch (Exception $e) {}

// ── 7. RECENT RECEIPTS ──────────────────────────────────
$recentReceipts = [];
try {
    $rr = $db->prepare("SELECT s.receipt_number, s.sale_date, s.customer_name, s.total_amount, s.payment_method, s.status FROM coop_sales s WHERE MONTH(s.sale_date)=? AND YEAR(s.sale_date)=? ORDER BY s.created_at DESC LIMIT 20");
    $rr->execute([$month,$year]); $recentReceipts = $rr->fetchAll();
} catch (Exception $e) {}

include '../includes/header.php';
?>

<style>
@media print {
    .sidebar,.topbar,.no-print,.mobile-bottom-nav,.sidebar-backdrop{display:none!important}
    .main-content{margin-left:0!important;padding-top:0!important}
    html,body,*{overflow:visible!important}
    ::-webkit-scrollbar{display:none!important}
    .card-section{box-shadow:none!important;border:1px solid #dee2e6!important;page-break-inside:avoid}
    .table{font-size:8pt!important;width:100%!important}
    .table th{background:#1a6b3c!important;color:#fff!important;-webkit-print-color-adjust:exact!important}
    @page{size:A4 portrait;margin:10mm}
}
</style>

<!-- Page Header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
    <div>
        <h5 class="fw-bold text-success mb-0"><i class="fa fa-file-alt me-2"></i>All Reports</h5>
        <small class="text-muted">Dairy Cooperative & Farm Reports — <?= $monthName.' '.$year ?></small>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-end">
        <form class="d-flex gap-1 align-items-end" method="GET">
            <input type="hidden" name="tab" value="<?= $tab ?>">
            <select name="month" class="form-select form-select-sm" style="width:110px">
                <?php for($m=1;$m<=12;$m++) { echo '<option value="'.$m.'"'.($m==$month?' selected':'').'>'.date('F',mktime(0,0,0,$m,1)).'</option>'; } ?>
            </select>
            <select name="year" class="form-select form-select-sm" style="width:80px">
                <?php foreach($years as $y) { echo '<option value="'.$y.'"'.($y==$year?' selected':'').'>'.$y.'</option>'; } ?>
            </select>
            <button class="btn btn-success btn-sm"><i class="fa fa-filter me-1"></i>Apply</button>
        </form>
        <button onclick="window.print()" class="btn btn-outline-success btn-sm"><i class="fa fa-print me-1"></i>Print</button>
    </div>
</div>

<!-- Print header -->
<div style="display:none" id="printHdr">
    <div class="text-center mb-3 border-bottom pb-2">
        <h4 class="fw-bold text-success mb-0">🐃 DairyBox – All Reports</h4>
        <p class="mb-0 text-muted"><?= $monthName.' '.$year ?> | Generated <?= date('M d, Y h:i A') ?> by <?= htmlspecialchars($user['full_name']) ?></p>
    </div>
</div>

<!-- Summary Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#d4edda"><i class="fa fa-peso-sign text-success" style="font-size:1.4rem"></i></div>
        <div><p class="stat-label">Sales Revenue</p><p class="stat-value text-success">₱<?= number_format($salesSum['total'],2) ?></p></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#cce5ff"><i class="fa fa-receipt text-primary" style="font-size:1.4rem"></i></div>
        <div><p class="stat-label">Transactions</p><p class="stat-value text-primary"><?= $salesSum['count'] ?></p></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#fff3cd"><i class="fa fa-tint text-warning" style="font-size:1.4rem"></i></div>
        <div><p class="stat-label">Milk Produced (L)</p><p class="stat-value text-warning"><?= number_format($milkTotal,1) ?></p></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#e2d9f3"><i class="fa fa-boxes" style="color:#6f42c1;font-size:1.4rem"></i></div>
        <div><p class="stat-label">Stock Value</p><p class="stat-value" style="color:#6f42c1">₱<?= number_format($totalStockValue,2) ?></p></div></div>
    </div>
</div>

<!-- Tab Navigation -->
<ul class="nav nav-tabs mb-3 no-print" id="reportTabs">
    <?php $tabs=['sales'=>'fa-chart-bar Sales','products'=>'fa-box-open Products','inventory'=>'fa-warehouse Inventory','receipts'=>'fa-receipt Receipts','milk'=>'fa-tint Milk Production','health'=>'fa-heartbeat Health','vaccinations'=>'fa-syringe Vaccinations']; ?>
    <?php foreach($tabs as $k=>$v): [$ico,$lbl]=explode(' ',$v,2); ?>
    <li class="nav-item">
        <a class="nav-link <?=$tab===$k?'active':''?>" href="?tab=<?=$k?>&month=<?=$month?>&year=<?=$year?>">
            <i class="fa <?=$ico?> me-1"></i><?=$lbl?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- ── SALES TAB ── -->
<div class="tab-content" id="reportContent">

<?php if($tab==='sales'): ?>
<div class="card-section mb-3">
    <div class="section-title"><i class="fa fa-chart-bar me-2"></i>Sales Summary — <?= $monthName.' '.$year ?></div>
    <div class="row g-3 mb-3 text-center">
        <div class="col-4"><div class="border rounded p-2"><div class="fw-bold text-success" style="font-size:1.3rem">₱<?= number_format($salesSum['cash'],2) ?></div><small class="text-muted">Cash</small></div></div>
        <div class="col-4"><div class="border rounded p-2"><div class="fw-bold text-info" style="font-size:1.3rem">₱<?= number_format($salesSum['gcash'],2) ?></div><small class="text-muted">GCash/Maya</small></div></div>
        <div class="col-4"><div class="border rounded p-2"><div class="fw-bold text-secondary" style="font-size:1.3rem">₱<?= number_format($salesSum['other'],2) ?></div><small class="text-muted">Other</small></div></div>
    </div>
    <?php if(!empty($salesByDay)): ?>
    <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>Date</th><th class="text-center">Transactions</th><th class="text-end">Revenue</th></tr></thead>
        <tbody>
        <?php foreach($salesByDay as $d): ?>
        <tr><td><?= date('D, M d',strtotime($d['sale_date'])) ?></td><td class="text-center"><?= $d['txn'] ?></td><td class="text-end fw-bold text-success">₱<?= number_format($d['rev'],2) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr class="table-success"><td><strong>TOTAL</strong></td><td class="text-center"><strong><?= $salesSum['count'] ?></strong></td><td class="text-end"><strong>₱<?= number_format($salesSum['total'],2) ?></strong></td></tr></tfoot>
    </table></div>
    <?php else: ?><p class="text-muted">No sales data for this period.</p><?php endif; ?>
</div>

<?php elseif($tab==='products'): ?>
<div class="card-section mb-3">
    <div class="section-title"><i class="fa fa-box-open me-2"></i>Top Products — <?= $monthName.' '.$year ?></div>
    <?php if(!empty($topProducts)): ?>
    <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>#</th><th>Product</th><th>Unit</th><th class="text-center">Qty Sold</th><th class="text-end">Revenue</th></tr></thead>
        <tbody>
        <?php foreach($topProducts as $i=>$p): ?>
        <tr><td><?=$i+1?></td><td><?= htmlspecialchars($p['name']) ?></td><td><?= htmlspecialchars($p['unit']) ?></td>
        <td class="text-center"><?= number_format($p['qty'],1) ?></td>
        <td class="text-end fw-bold text-success">₱<?= number_format($p['rev'],2) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php else: ?><p class="text-muted">No product sales data for this period.</p><?php endif; ?>
</div>

<?php elseif($tab==='inventory'): ?>
<div class="card-section mb-3">
    <div class="section-title"><i class="fa fa-warehouse me-2"></i>Current Inventory Status
        <?php if($lowStockCount>0): ?><span class="badge bg-danger ms-2"><?=$lowStockCount?> Low Stock</span><?php endif; ?>
    </div>
    <?php if(!empty($inventory)): ?>
    <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>Product</th><th>Category</th><th>Unit</th><th class="text-center">Stock</th><th class="text-center">Reorder</th><th class="text-end">Price</th><th class="text-end">Stock Value</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach($inventory as $p):
            $low = $p['stock_qty'] <= $p['reorder_level'];
        ?>
        <tr class="<?=$low?'table-warning':''?>">
            <td><?= htmlspecialchars($p['name']) ?></td><td><?= $p['category'] ?></td><td><?= htmlspecialchars($p['unit']) ?></td>
            <td class="text-center <?=$low?'fw-bold text-danger':''?>"><?= number_format($p['stock_qty'],1) ?></td>
            <td class="text-center"><?= number_format($p['reorder_level'],1) ?></td>
            <td class="text-end">₱<?= number_format($p['selling_price'],2) ?></td>
            <td class="text-end">₱<?= number_format($p['stock_value'],2) ?></td>
            <td><?= $low?'<span class="badge bg-warning text-dark">Low</span>':'<span class="badge bg-success">OK</span>' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr class="table-success"><td colspan="6"><strong>Total Stock Value</strong></td><td class="text-end"><strong>₱<?= number_format($totalStockValue,2) ?></strong></td><td></td></tr></tfoot>
    </table></div>
    <?php else: ?><p class="text-muted">No inventory data found.</p><?php endif; ?>
</div>

<?php elseif($tab==='receipts'): ?>
<div class="card-section mb-3">
    <div class="section-title"><i class="fa fa-receipt me-2"></i>Receipts — <?= $monthName.' '.$year ?></div>
    <?php if(!empty($recentReceipts)): ?>
    <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>Receipt #</th><th>Date</th><th>Customer</th><th class="text-end">Total</th><th>Payment</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach($recentReceipts as $r):
            $sCls=match($r['status']){'Completed'=>'badge-healthy','Voided'=>'badge-sick',default=>'badge-treated'};
        ?>
        <tr class="<?=$r['status']==='Voided'?'table-secondary':''?>">
            <td><code style="font-size:.78rem"><?= htmlspecialchars($r['receipt_number']) ?></code></td>
            <td><?= date('M d, Y',strtotime($r['sale_date'])) ?></td>
            <td><?= htmlspecialchars($r['customer_name']) ?></td>
            <td class="text-end fw-semibold text-success">₱<?= number_format($r['total_amount'],2) ?></td>
            <td><?= $r['payment_method'] ?></td>
            <td><span class="badge-custom <?=$sCls?>"><?= $r['status'] ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php else: ?><p class="text-muted">No receipts for this period.</p><?php endif; ?>
</div>

<?php elseif($tab==='milk'): ?>
<div class="card-section mb-3">
    <div class="section-title"><i class="fa fa-tint me-2"></i>Milk Production Report — <?= $monthName.' '.$year ?></div>
    <?php if(!empty($milkData)): ?>
    <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>Tag</th><th>Name</th><th>Breed</th><th class="text-center">Days</th><th class="text-end">Avg/Session (L)</th><th class="text-end">Total (L)</th><th>Share</th></tr></thead>
        <tbody>
        <?php foreach($milkData as $i=>$m):
            $pct = $milkTotal>0 ? round($m['total']/$milkTotal*100,1) : 0;
        ?>
        <tr>
            <td><span class="badge bg-success"><?= htmlspecialchars($m['tag_number']) ?></span></td>
            <td><?= htmlspecialchars($m['name']??'-') ?></td><td><?= htmlspecialchars($m['breed']??'-') ?></td>
            <td class="text-center"><?= $m['days'] ?></td>
            <td class="text-end"><?= number_format($m['avg_sess'],2) ?></td>
            <td class="text-end fw-bold"><?= number_format($m['total'],2) ?></td>
            <td><div class="progress" style="height:10px;min-width:60px"><div class="progress-bar bg-success" style="width:<?=$pct?>%"></div></div><small><?=$pct?>%</small></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr class="table-success"><td colspan="5"><strong>TOTAL</strong></td><td class="text-end"><strong><?= number_format($milkTotal,2) ?> L</strong></td><td></td></tr></tfoot>
    </table></div>
    <?php else: ?><p class="text-muted">No milk production data for this period.</p><?php endif; ?>
</div>

<?php elseif($tab==='health'): ?>
<div class="card-section mb-3">
    <div class="section-title"><i class="fa fa-heartbeat me-2"></i>Health Records Summary — <?= $monthName.' '.$year ?></div>
    <?php if(!empty($healthData)): ?>
    <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>Condition Type</th><th>Status</th><th class="text-center">Count</th></tr></thead>
        <tbody>
        <?php foreach($healthData as $h): $sCls=match($h['status']){'Resolved'=>'badge-healthy','Active'=>'badge-sick',default=>'badge-treated'}; ?>
        <tr><td><?= $h['condition_type'] ?></td><td><span class="badge-custom <?=$sCls?>"><?= $h['status'] ?></span></td><td class="text-center"><strong><?= $h['cnt'] ?></strong></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php else: ?><p class="text-muted">No health records for this period.</p><?php endif; ?>
</div>

<?php elseif($tab==='vaccinations'): ?>
<div class="card-section mb-3">
    <div class="section-title"><i class="fa fa-syringe me-2"></i>Vaccination Summary — <?= $monthName.' '.$year ?></div>
    <?php if(!empty($vaccData)): ?>
    <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>Vaccine</th><th>Status</th><th class="text-center">Count</th></tr></thead>
        <tbody>
        <?php foreach($vaccData as $v): $sCls=match($v['status']){'Done'=>'badge-healthy','Overdue'=>'badge-sick','Scheduled'=>'badge-treated',default=>''}; ?>
        <tr><td><?= htmlspecialchars($v['vaccine_name']) ?></td><td><span class="badge-custom <?=$sCls?>"><?= $v['status'] ?></span></td><td class="text-center"><strong><?= $v['cnt'] ?></strong></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php else: ?><p class="text-muted">No vaccination data for this period.</p><?php endif; ?>
</div>
<?php endif; ?>

</div><!-- end tab-content -->

<div class="text-center text-muted small no-print mt-2">
    <i class="fa fa-info-circle me-1"></i>Showing data for <strong><?= $monthName.' '.$year ?></strong>
</div>

<?php include '../includes/footer.php'; ?>
