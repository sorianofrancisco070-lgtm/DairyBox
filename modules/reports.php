<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$root      = '../';
$pageTitle = 'Reports';
$db        = getDB();
$user      = currentUser();

$type  = $_GET['type']  ?? 'production';
$month = (int)($_GET['month'] ?? date('m'));
$year  = (int)($_GET['year']  ?? date('Y'));

$monthName = date('F', mktime(0,0,0,$month,1));

// ---- Production Report ----
$prodData = $db->prepare("
    SELECT b.id, b.tag_number, b.name, b.breed,
           SUM(mp.quantity_liters) as total,
           AVG(mp.quantity_liters) as avg_sess,
           MAX(mp.quantity_liters) as max_sess,
           COUNT(DISTINCT mp.record_date) as days_rec
    FROM milk_production mp JOIN buffaloes b ON b.id=mp.buffalo_id
    WHERE MONTH(mp.record_date)=? AND YEAR(mp.record_date)=?
    GROUP BY b.id, b.tag_number, b.name, b.breed ORDER BY total DESC
");
$prodData->execute([$month,$year]);
$prodData = $prodData->fetchAll();
$prodTotal = array_sum(array_column($prodData,'total'));

// ---- Health Summary ----
$healthData = $db->prepare("
    SELECT hr.condition_type, hr.status, COUNT(*) as cnt
    FROM health_records hr
    WHERE MONTH(hr.record_date)=? AND YEAR(hr.record_date)=?
    GROUP BY hr.condition_type, hr.status
");
$healthData->execute([$month,$year]);
$healthData = $healthData->fetchAll();

// ---- Vaccination Summary ----
$vaccData = $db->prepare("
    SELECT vaccine_name, COUNT(*) as cnt, status
    FROM vaccinations
    WHERE MONTH(administered_date)=? AND YEAR(administered_date)=?
    GROUP BY vaccine_name, status
");
$vaccData->execute([$month,$year]);
$vaccData = $vaccData->fetchAll();

// ---- Breeding Summary ----
$breedData = $db->prepare("
    SELECT pregnancy_status, method, COUNT(*) as cnt
    FROM breeding_records
    WHERE MONTH(breeding_date)=? AND YEAR(breeding_date)=?
    GROUP BY pregnancy_status, method
");
$breedData->execute([$month,$year]);
$breedData = $breedData->fetchAll();

// ---- Herd Status ----
$herdStatus = $db->query("
    SELECT health_status, COUNT(*) as cnt FROM buffaloes WHERE status='Active' GROUP BY health_status
")->fetchAll();

$years = $db->query("SELECT DISTINCT YEAR(record_date) as y FROM milk_production ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($years)) $years = [date('Y')];

// ── CSV Export ────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "farm_report_{$monthName}_{$year}.csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    // Milk Production
    fputcsv($out, ["DairyBox Farm Report – {$monthName} {$year}"]);
    fputcsv($out, []);
    fputcsv($out, ['=== MILK PRODUCTION ===']);
    fputcsv($out, ['#','Tag','Name','Breed','Total (L)','Avg/Session (L)','Max/Session (L)','Days Recorded']);
    foreach ($prodData as $i => $p) {
        fputcsv($out, [$i+1, $p['tag_number'], $p['name']??'-', $p['breed']??'-',
            number_format($p['total'],2), number_format($p['avg_sess'],2),
            number_format($p['max_sess'],2), $p['days_rec']]);
    }
    fputcsv($out, ['','','','','TOTAL: '.number_format($prodTotal,2).' L']);
    fputcsv($out, []);
    // Health
    fputcsv($out, ['=== HEALTH RECORDS ===']);
    fputcsv($out, ['Condition Type','Status','Count']);
    foreach ($healthData as $h) fputcsv($out, [$h['condition_type'], $h['status'], $h['cnt']]);
    fputcsv($out, []);
    // Vaccinations
    fputcsv($out, ['=== VACCINATION SUMMARY ===']);
    fputcsv($out, ['Vaccine','Status','Count']);
    foreach ($vaccData as $v) fputcsv($out, [$v['vaccine_name'], $v['status'], $v['cnt']]);
    fputcsv($out, []);
    // Breeding
    fputcsv($out, ['=== BREEDING SUMMARY ===']);
    fputcsv($out, ['Method','Pregnancy Status','Count']);
    foreach ($breedData as $br) fputcsv($out, [$br['method'], $br['pregnancy_status'], $br['cnt']]);
    fputcsv($out, []);
    fputcsv($out, ["Generated: ".date('M d, Y h:i A')." by ".$user['full_name']]);
    fclose($out);
    exit;
}

include '../includes/header.php';
?>

<div class="d-flex flex-wrap gap-3 mb-4 no-print align-items-end">
    <form class="d-flex gap-2 align-items-end flex-wrap" method="GET">
        <div>
            <label class="form-label mb-1 small fw-semibold">Month</label>
            <select name="month" class="form-select form-select-sm">
                <?php for ($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $m==$month?'selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option><?php endfor; ?>
            </select>
        </div>
        <div>
            <label class="form-label mb-1 small fw-semibold">Year</label>
            <select name="year" class="form-select form-select-sm">
                <?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= $y==$year?'selected':'' ?>><?= $y ?></option><?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-sm btn-outline-success"><i class="fa fa-filter me-1"></i>Apply</button>
    </form>
    <button onclick="window.print()" class="btn btn-sm btn-success"><i class="fa fa-print me-1"></i>Print Report</button>
    <button onclick="savePDF()" class="btn btn-sm btn-outline-primary"><i class="fa fa-file-pdf me-1"></i>Save as PDF</button>
    <a href="?type=<?= $type ?>&month=<?= $month ?>&year=<?= $year ?>&export=csv" class="btn btn-sm btn-outline-secondary"><i class="fa fa-file-csv me-1"></i>Export CSV</a>
</div>

<!-- Report Header (visible on print) -->
<div class="card-section mb-4" id="report-header">
    <div class="text-center mb-3">
        <h4 class="fw-bold text-success">🐃 Dairy Box Production & Herd Health System</h4>
        <h5>Monthly Farm Report – <?= $monthName ?> <?= $year ?></h5>
        <p class="text-muted small mb-0">Dairy Box Surallah, D.A Compound Surallah, So.Cot, 9512 | Generated: <?= date('M d, Y h:i A') ?></p>
    </div>

    <!-- Herd Status -->
    <div class="section-title"><i class="fa fa-paw me-2"></i>1. Herd Status</div>
    <div class="row g-2 mb-3">
        <?php foreach ($herdStatus as $h): ?>
        <div class="col-auto">
            <div class="border rounded px-3 py-2 text-center">
                <div class="fw-bold fs-4"><?= $h['cnt'] ?></div>
                <small class="text-muted"><?= $h['health_status'] ?></small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Milk Production -->
    <div class="section-title"><i class="fa fa-tint me-2"></i>2. Milk Production – <?= $monthName ?> <?= $year ?></div>
    <?php if (empty($prodData)): ?>
        <p class="text-muted">No production data for this period.</p>
    <?php else: ?>
    <table class="table table-sm table-bordered mb-3">
        <thead><tr><th>#</th><th>Tag</th><th>Name</th><th>Breed</th><th>Total (L)</th><th>Avg/Session (L)</th><th>Max/Session (L)</th><th>Days Recorded</th></tr></thead>
        <tbody>
        <?php foreach ($prodData as $i => $p): ?>
        <tr>
            <td><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($p['tag_number']) ?></strong></td>
            <td><?= htmlspecialchars($p['name']??'-') ?></td>
            <td><?= htmlspecialchars($p['breed']??'-') ?></td>
            <td><strong><?= number_format($p['total'],2) ?></strong></td>
            <td><?= number_format($p['avg_sess'],2) ?></td>
            <td><?= number_format($p['max_sess'],2) ?></td>
            <td><?= $p['days_rec'] ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr class="table-success"><td colspan="4"><strong>TOTAL</strong></td><td><strong><?= number_format($prodTotal,2) ?> L</strong></td><td colspan="3"></td></tr></tfoot>
    </table>
    <?php endif; ?>

    <!-- Health Summary -->
    <div class="section-title"><i class="fa fa-heartbeat me-2"></i>3. Health Records – <?= $monthName ?> <?= $year ?></div>
    <?php if (empty($healthData)): ?>
        <p class="text-muted">No health records for this period.</p>
    <?php else: ?>
    <table class="table table-sm table-bordered mb-3">
        <thead><tr><th>Condition Type</th><th>Status</th><th>Count</th></tr></thead>
        <tbody>
        <?php foreach ($healthData as $h): ?>
        <tr><td><?= $h['condition_type'] ?></td><td><?= $h['status'] ?></td><td><?= $h['cnt'] ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Vaccination Summary -->
    <div class="section-title"><i class="fa fa-syringe me-2"></i>4. Vaccination Summary – <?= $monthName ?> <?= $year ?></div>
    <?php if (empty($vaccData)): ?>
        <p class="text-muted">No vaccination records for this period.</p>
    <?php else: ?>
    <table class="table table-sm table-bordered mb-3">
        <thead><tr><th>Vaccine</th><th>Status</th><th>Count</th></tr></thead>
        <tbody>
        <?php foreach ($vaccData as $v): ?>
        <tr><td><?= htmlspecialchars($v['vaccine_name']) ?></td><td><?= $v['status'] ?></td><td><?= $v['cnt'] ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Breeding Summary -->
    <div class="section-title"><i class="fa fa-venus-mars me-2"></i>5. Breeding Summary – <?= $monthName ?> <?= $year ?></div>
    <?php if (empty($breedData)): ?>
        <p class="text-muted">No breeding records for this period.</p>
    <?php else: ?>
    <table class="table table-sm table-bordered mb-3">
        <thead><tr><th>Method</th><th>Pregnancy Status</th><th>Count</th></tr></thead>
        <tbody>
        <?php foreach ($breedData as $br): ?>
        <tr><td><?= $br['method'] ?></td><td><?= $br['pregnancy_status'] ?></td><td><?= $br['cnt'] ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div class="mt-4 pt-3 border-top text-center text-muted small">
        <p class="mb-0">This report was generated by the Dairy Box Production & Herd Health System | <?= date('M d, Y') ?></p>
        <p class="mb-0">Prepared by: <?= htmlspecialchars($user['full_name']) ?> | <?= $pageTitle ?></p>
    </div>
</div>

<script>
function savePDF() {
    // Set page title to filename-friendly string, then print (browser saves as PDF)
    const orig = document.title;
    document.title = 'Farm_Report_<?= $monthName ?>_<?= $year ?>';
    // Hide no-print elements temporarily already handled by @media print CSS
    window.print();
    document.title = orig;
}
</script>

<?php include '../includes/footer.php'; ?>
