<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$root      = '../';
$pageTitle = 'Production Analytics';
$db        = getDB();
$user      = currentUser();

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('m'));

// --- Monthly totals for year ---
$monthly = $db->prepare("
    SELECT MONTH(record_date) as mo, SUM(quantity_liters) as total
    FROM milk_production WHERE YEAR(record_date)=?
    GROUP BY mo ORDER BY mo
");
$monthly->execute([$year]);
$moData = array_fill(1,12,0);
foreach ($monthly->fetchAll() as $r) $moData[$r['mo']] = (float)$r['total'];
$moLabels = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

// --- Daily totals for selected month ---
$daily = $db->prepare("
    SELECT DAY(record_date) as d, SUM(quantity_liters) as total
    FROM milk_production WHERE YEAR(record_date)=? AND MONTH(record_date)=?
    GROUP BY d ORDER BY d
");
$daily->execute([$year,$month]);
$dData = []; $dLabels = [];
foreach ($daily->fetchAll() as $r) { $dLabels[] = $r['d']; $dData[] = (float)$r['total']; }

// --- Per buffalo for selected month ---
$perBuf = $db->prepare("
    SELECT b.id, b.tag_number, b.name, SUM(mp.quantity_liters) as total,
           COUNT(DISTINCT mp.record_date) as days_recorded,
           AVG(mp.quantity_liters) as avg_per_session
    FROM milk_production mp JOIN buffaloes b ON b.id=mp.buffalo_id
    WHERE YEAR(mp.record_date)=? AND MONTH(mp.record_date)=?
    GROUP BY b.id, b.tag_number, b.name ORDER BY total DESC
");
$perBuf->execute([$year,$month]);
$perBufData = $perBuf->fetchAll();

$pbLabels = array_map(fn($r)=>$r['tag_number'], $perBufData);
$pbData   = array_map(fn($r)=>(float)$r['total'], $perBufData);

// --- Session breakdown ---
$sessions = $db->prepare("
    SELECT session, SUM(quantity_liters) as total
    FROM milk_production WHERE YEAR(record_date)=? AND MONTH(record_date)=?
    GROUP BY session
");
$sessions->execute([$year,$month]);
$sessData = $sessions->fetchAll();
$sLabels  = array_column($sessData,'session');
$sData    = array_map(fn($r)=>(float)$r['total'], $sessData);

// --- Summary stats ---
$monthTotal = array_sum($pbData);
$avgDaily   = !empty($dData) ? array_sum($dData)/count($dData) : 0;
$activeBuf  = count($pbData);

// Year list for filter
$years = $db->query("SELECT DISTINCT YEAR(record_date) as y FROM milk_production ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($years)) $years = [date('Y')];

include '../includes/header.php';
?>

<form class="row g-2 mb-4 no-print" method="GET">
    <div class="col-md-2">
        <select name="year" class="form-select form-select-sm">
            <?php foreach ($years as $y): ?>
            <option value="<?= $y ?>" <?= $y==$year?'selected':'' ?>><?= $y ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="month" class="form-select form-select-sm">
            <?php for ($m=1;$m<=12;$m++): ?>
            <option value="<?= $m ?>" <?= $m==$month?'selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="col-auto"><button class="btn btn-sm btn-outline-success"><i class="fa fa-filter me-1"></i>Apply</button></div>
</form>

<!-- Summary Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#cce5ff"><i class="fa fa-tint text-primary" style="font-size:1.5rem"></i></div>
        <div><p class="stat-label">Month Total (L)</p><p class="stat-value text-primary"><?= number_format($monthTotal,1) ?></p></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#d4edda"><i class="fa fa-calendar-day text-success" style="font-size:1.5rem"></i></div>
        <div><p class="stat-label">Avg/Day (L)</p><p class="stat-value text-success"><?= number_format($avgDaily,1) ?></p></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#fff3cd"><span style="font-size:1.5rem">🐃</span></div>
        <div><p class="stat-label">Active Producers</p><p class="stat-value text-warning"><?= $activeBuf ?></p></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:#e2e3f3"><i class="fa fa-chart-line text-secondary" style="font-size:1.5rem"></i></div>
        <div><p class="stat-label">Year Total (L)</p><p class="stat-value"><?= number_format(array_sum($moData),1) ?></p></div></div>
    </div>
</div>

<div class="row g-3">
    <!-- Monthly Trend -->
    <div class="col-lg-8">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-chart-bar me-2"></i>Monthly Production Trend – <?= $year ?></div>
            <canvas id="monthChart" height="90"></canvas>
        </div>
    </div>
    <!-- Session Breakdown -->
    <div class="col-lg-4">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-chart-pie me-2"></i>Session Breakdown – <?= date('F',mktime(0,0,0,$month,1)) ?></div>
            <canvas id="sessChart"></canvas>
        </div>
    </div>
    <!-- Daily Production -->
    <div class="col-lg-8">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-chart-line me-2"></i>Daily Production – <?= date('F',mktime(0,0,0,$month,1)).' '.$year ?></div>
            <canvas id="dailyChart" height="80"></canvas>
        </div>
    </div>
    <!-- Per Buffalo Bar -->
    <div class="col-lg-4">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-paw me-2"></i>Per Buffalo – <?= date('F',mktime(0,0,0,$month,1)) ?></div>
            <canvas id="perBufChart"></canvas>
        </div>
    </div>
    <!-- Per Buffalo Table -->
    <div class="col-12">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-table me-2"></i>Individual Buffalo Performance</div>
            <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead><tr><th>#</th><th>Tag</th><th>Name</th><th>Month Total (L)</th><th>Days Recorded</th><th>Avg/Session (L)</th><th>Contribution</th></tr></thead>
                <tbody>
                <?php foreach ($perBufData as $i => $p): 
                    $pct = $monthTotal > 0 ? round($p['total']/$monthTotal*100,1) : 0;
                ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><span class="badge bg-success"><?= htmlspecialchars($p['tag_number']) ?></span></td>
                    <td><?= htmlspecialchars($p['name']??'-') ?></td>
                    <td><strong><?= number_format($p['total'],2) ?></strong></td>
                    <td><?= $p['days_recorded'] ?></td>
                    <td><?= number_format($p['avg_per_session'],2) ?></td>
                    <td>
                        <div class="progress" style="height:12px">
                            <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                        </div>
                        <small><?= $pct ?>%</small>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($perBufData)): ?><tr><td colspan="7" class="text-center text-muted py-3">No data for this period.</td></tr><?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

<script>
const monthLabels = <?= json_encode(array_values(array_slice($moLabels,1))) ?>;
const monthData   = <?= json_encode(array_values(array_slice($moData,1))) ?>;

new Chart(document.getElementById('monthChart'), {
    type: 'bar',
    data: { labels: monthLabels, datasets:[{ label:'Milk (L)', data: monthData,
        backgroundColor:'rgba(40,167,69,.7)', borderColor:'#1a6b3c', borderWidth:1, borderRadius:4 }]},
    options:{ responsive:true, plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true} } }
});

new Chart(document.getElementById('dailyChart'), {
    type: 'line',
    data: { labels: <?= json_encode($dLabels) ?>, datasets:[{ label:'Daily Milk (L)', data: <?= json_encode($dData) ?>,
        borderColor:'#28a745', backgroundColor:'rgba(40,167,69,.1)', fill:true, tension:.4, pointRadius:4 }]},
    options:{ responsive:true, plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true} } }
});

new Chart(document.getElementById('sessChart'), {
    type: 'doughnut',
    data: { labels: <?= json_encode($sLabels) ?>, datasets:[{ data: <?= json_encode($sData) ?>,
        backgroundColor:['#28a745','#17a2b8','#ffc107'] }]},
    options:{ responsive:true, plugins:{ legend:{position:'bottom'} } }
});

new Chart(document.getElementById('perBufChart'), {
    type: 'bar',
    data: { labels: <?= json_encode($pbLabels) ?>, datasets:[{ label:'Total (L)', data: <?= json_encode($pbData) ?>,
        backgroundColor:'rgba(23,162,184,.7)' }]},
    options:{ responsive:true, indexAxis:'y', plugins:{legend:{display:false}}, scales:{ x:{beginAtZero:true} } }
});
</script>

<?php include '../includes/footer.php'; ?>
