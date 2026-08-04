<?php
ob_start(); // buffer all output to prevent headers-already-sent errors
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$root      = '../';
$pageTitle = 'Milk Yield Prediction';
$db        = getDB();
$user      = currentUser();

// ── Helper: linear regression ─────────────────────────
if (!function_exists('linReg')) {
    function linReg(array $y): array {
        $n = count($y);
        if ($n < 2) return ['slope' => 0, 'intercept' => $n ? $y[0] : 0];
        $sumX = $sumY = $sumXY = $sumX2 = 0;
        for ($i = 0; $i < $n; $i++) {
            $sumX  += $i;
            $sumY  += $y[$i];
            $sumXY += $i * $y[$i];
            $sumX2 += $i * $i;
        }
        $d = $n * $sumX2 - $sumX * $sumX;
        if ($d == 0) return ['slope' => 0, 'intercept' => $sumY / $n];
        return [
            'slope'     => ($n * $sumXY - $sumX * $sumY) / $d,
            'intercept' => ($sumY - (($n * $sumXY - $sumX * $sumY) / $d) * $sumX) / $n,
        ];
    }
}

// ── Fetch last 90 days herd production ────────────────
$dailyProd = [];
$dbError   = null;
try {
    $rows = $db->query("
        SELECT record_date, SUM(quantity_liters) AS total
        FROM milk_production
        WHERE record_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        GROUP BY record_date
        ORDER BY record_date ASC
    ")->fetchAll();
    foreach ($rows as $r) $dailyProd[$r['record_date']] = (float)$r['total'];
} catch (Exception $e) {
    $dbError = $e->getMessage();
}

// Build continuous 90-day array
$allDates = $allVals = [];
for ($i = 89; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $allDates[] = $d;
    $allVals[]  = $dailyProd[$d] ?? 0;
}

// 7-day moving average
$mov7 = [];
for ($i = 0; $i < count($allVals); $i++) {
    $w = array_filter(array_slice($allVals, max(0, $i - 6), 7), fn($v) => $v > 0);
    $mov7[] = count($w) ? round(array_sum($w) / count($w), 2) : 0;
}

// 14-day trend
$last14    = array_filter(array_slice($allVals, -14), fn($v) => $v > 0);
$last14    = array_values($last14);
$trend     = linReg(count($last14) >= 2 ? $last14 : $allVals);

// Baseline = avg of last 7 non-zero days
$last7     = array_filter(array_slice($allVals, -7), fn($v) => $v > 0);
$baseline  = count($last7) ? array_sum($last7) / count($last7) : 0;

// Seasonal factors
$seasonal  = [1=>0.97,2=>0.96,3=>0.98,4=>1.00,5=>1.02,6=>1.00,
              7=>0.98,8=>0.97,9=>0.99,10=>1.01,11=>1.02,12=>1.00];

// Next 7 days forecast
$next7 = [];
$base  = count($last14);
for ($i = 1; $i <= 7; $i++) {
    $dt   = date('Y-m-d', strtotime("+{$i} days"));
    $mo   = (int)date('m', strtotime("+{$i} days"));
    $pred = max(0, ($trend['slope'] * ($base + $i) + $trend['intercept']) * ($seasonal[$mo] ?? 1.0));
    if ($baseline > 0) $pred = max($baseline * 0.6, min($baseline * 1.4, $pred));
    $next7[] = ['date' => $dt, 'pred' => round($pred, 2)];
}

// Next 30 days forecast
$next30 = [];
for ($i = 1; $i <= 30; $i++) {
    $dt   = date('Y-m-d', strtotime("+{$i} days"));
    $mo   = (int)date('m', strtotime("+{$i} days"));
    $pred = max(0, ($trend['slope'] * ($base + $i) + $trend['intercept']) * ($seasonal[$mo] ?? 1.0));
    if ($baseline > 0) $pred = max($baseline * 0.55, min($baseline * 1.5, $pred));
    $next30[] = ['date' => $dt, 'pred' => round($pred, 2)];
}

// Confidence
$conf = 50;
if (count($last14) >= 4) {
    $mean = array_sum($last14) / count($last14);
    if ($mean > 0) {
        $var  = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $last14)) / count($last14);
        $cv   = sqrt($var) / $mean;
        $conf = (int)max(30, min(95, round((1 - $cv) * 100)));
    }
}

// Today
$todayActual    = $dailyProd[date('Y-m-d')] ?? null;
$todayPredicted = $baseline > 0 ? round($baseline * ($seasonal[(int)date('m')] ?? 1.0), 2) : 0;

// Accuracy last 7 days
$accuracy = [];
for ($i = 7; $i >= 1; $i--) {
    $d      = date('Y-m-d', strtotime("-{$i} days"));
    $actual = $dailyProd[$d] ?? null;
    $psum   = $pcnt = 0;
    for ($j = 8; $j <= 14; $j++) {
        $pd = date('Y-m-d', strtotime("-" . ($i + $j) . " days"));
        if (isset($dailyProd[$pd]) && $dailyProd[$pd] > 0) { $psum += $dailyProd[$pd]; $pcnt++; }
    }
    $ep  = $pcnt ? round($psum / $pcnt, 2) : null;
    $accuracy[] = [
        'date'    => $d,
        'actual'  => $actual,
        'pred'    => $ep,
        'diff'    => ($actual !== null && $ep !== null) ? round($actual - $ep, 2) : null,
        'pct'     => ($actual !== null && $ep !== null && $ep > 0)
                        ? round(abs($actual - $ep) / $ep * 100, 1) : null,
    ];
}

// Per-buffalo predictions
$bufPreds = [];
try {
    $females = $db->query("
        SELECT id, tag_number, name, health_status
        FROM buffaloes WHERE status='Active' AND sex='Female'
        ORDER BY tag_number
    ")->fetchAll();

    foreach ($females as $buf) {
        $bid  = $buf['id'];
        $rows = $db->prepare("
            SELECT record_date, SUM(quantity_liters) AS total
            FROM milk_production
            WHERE buffalo_id=? AND record_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY record_date ORDER BY record_date ASC
        ");
        $rows->execute([$bid]);
        $bv  = array_map(fn($r) => (float)$r['total'], $rows->fetchAll());
        $bnz = array_values(array_filter($bv, fn($v) => $v > 0));

        if (empty($bnz)) {
            $bufPreds[] = $buf + ['avg30'=>0,'pred_wk'=>0,'trend'=>'No data','lac'=>'Unknown','days'=>0];
            continue;
        }
        $bavg  = round(array_sum($bnz) / count($bnz), 2);
        $bt    = linReg($bnz);
        $bpred = max(0, $bt['slope'] * (count($bnz) + 3) + $bt['intercept']);
        $bpred = round(max($bavg * 0.6, min($bavg * 1.4, $bpred)), 2);

        $tdir = $bt['slope'] > 0.05 ? 'Rising' : ($bt['slope'] < -0.05 ? 'Declining' : 'Stable');

        // Lactation stage
        $fm = $db->prepare("SELECT MIN(record_date) FROM milk_production WHERE buffalo_id=?");
        $fm->execute([$bid]);
        $fd  = $fm->fetchColumn();
        $ld  = $fd ? (int)(new DateTime())->diff(new DateTime($fd))->days : null;
        $lac = $ld === null ? 'Unknown'
             : ($ld <= 90 ? 'Early Lactation'
             : ($ld <= 200 ? 'Mid Lactation'
             : ($ld <= 305 ? 'Late Lactation' : 'Extended / Dry')));

        $bufPreds[] = $buf + ['avg30'=>$bavg,'pred_wk'=>$bpred,'trend'=>$tdir,'lac'=>$lac,'days'=>count($bnz)];
    }
} catch (Exception $e) {
    $dbError = $dbError ?: $e->getMessage();
}

// Chart data
$c30lab = array_slice($allDates, -30);
$c30val = array_slice($allVals, -30);
$c30mov = array_slice($mov7, -30);
$f7lab  = array_column($next7, 'date');
$f7val  = array_column($next7, 'pred');

include '../includes/header.php';
?>

<?php if ($dbError): ?>
<div class="alert alert-danger">
    <i class="fa fa-exclamation-circle me-2"></i><strong>Error:</strong> <?= htmlspecialchars($dbError) ?>
</div>
<?php endif; ?>

<?php if (empty($dailyProd) && !$dbError): ?>
<div class="card-section text-center py-5">
    <div style="font-size:3.5rem">📊</div>
    <h5 class="text-muted mt-3">No Milk Production Data Available</h5>
    <p class="text-muted small">Record daily milk production to enable yield predictions and forecasting.</p>
    <a href="milk_production.php?action=add" class="btn btn-success mt-2">
        <i class="fa fa-plus me-1"></i>Record Milk Production
    </a>
</div>
<?php include '../includes/footer.php'; ?>
<?php ob_end_flush(); exit; endif; ?>

<!-- ── SUMMARY STATS ── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#cce5ff"><i class="fa fa-tint text-primary" style="font-size:1.5rem"></i></div>
            <div><p class="stat-label">Today Predicted (L)</p><p class="stat-value text-primary"><?= number_format($todayPredicted,1) ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#d4edda"><i class="fa fa-check-circle text-success" style="font-size:1.5rem"></i></div>
            <div><p class="stat-label">Today Actual (L)</p>
            <p class="stat-value text-success"><?= $todayActual !== null ? number_format($todayActual,1) : '—' ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff3cd"><i class="fa fa-chart-line text-warning" style="font-size:1.5rem"></i></div>
            <div><p class="stat-label">7-Day Baseline (L)</p><p class="stat-value text-warning"><?= number_format($baseline,1) ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e2d9f3"><i class="fa fa-bullseye text-secondary" style="font-size:1.5rem"></i></div>
            <div><p class="stat-label">Confidence</p><p class="stat-value"><?= $conf ?>%</p></div>
        </div>
    </div>
</div>

<!-- ── TODAY COMPARISON + FORECAST CHART ── -->
<div class="row g-3 mb-4">
    <div class="col-md-5">
        <div class="card-section h-100">
            <div class="section-title"><i class="fa fa-balance-scale me-2"></i>Today: Predicted vs Actual</div>
            <div class="row g-2 text-center mb-3">
                <div class="col-6">
                    <div class="border rounded p-3">
                        <div class="fw-bold text-primary" style="font-size:1.7rem"><?= number_format($todayPredicted,1) ?>L</div>
                        <div style="font-size:.75rem;color:#888">Predicted</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="border rounded p-3">
                        <div class="fw-bold text-success" style="font-size:1.7rem">
                            <?= $todayActual !== null ? number_format($todayActual,1).'L' : '—' ?>
                        </div>
                        <div style="font-size:.75rem;color:#888">Actual</div>
                    </div>
                </div>
            </div>
            <?php if ($todayActual !== null && $todayPredicted > 0): ?>
            <?php $diff = round($todayActual - $todayPredicted, 1); ?>
            <div class="text-center mb-2">
                <span style="font-size:1rem;font-weight:600;color:<?= $diff>=0?'#28a745':'#dc3545' ?>">
                    <i class="fa fa-arrow-<?= $diff>=0?'up':'down' ?> me-1"></i>
                    <?= abs($diff) ?>L <?= $diff>=0?'above':'below' ?> prediction
                </span>
            </div>
            <?php endif; ?>
            <hr class="my-2">
            <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">14-day slope:</span>
                <span class="fw-bold <?= $trend['slope']>=0?'text-success':'text-danger' ?>">
                    <?= $trend['slope']>=0?'+':'' ?><?= number_format($trend['slope'],3) ?> L/day
                </span>
            </div>
            <div class="d-flex justify-content-between small align-items-center">
                <span class="text-muted">Confidence:</span>
                <span>
                    <div class="progress d-inline-flex" style="height:8px;width:70px;vertical-align:middle">
                        <div class="progress-bar <?= $conf>=70?'bg-success':($conf>=50?'bg-warning':'bg-danger') ?>" style="width:<?= $conf ?>%"></div>
                    </div>
                    <strong class="ms-1"><?= $conf ?>%</strong>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card-section h-100">
            <div class="section-title"><i class="fa fa-chart-line me-2"></i>Next 7-Day Forecast</div>
            <canvas id="forecastChart" height="130"></canvas>
        </div>
    </div>
</div>

<!-- ── HISTORY CHART ── -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-chart-area me-2"></i>Last 30 Days + 7-Day Moving Average</div>
    <canvas id="historyChart" height="80"></canvas>
</div>

<!-- ── 30-DAY FORECAST TABLE ── -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-calendar-alt me-2"></i>30-Day Production Forecast</div>
    <p class="text-muted small mb-3">
        Based on 14-day trend + seasonal adjustment. Confidence: <strong><?= $conf ?>%</strong>
    </p>
    <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
        <thead><tr><th>#</th><th>Date</th><th>Day</th><th>Predicted (L)</th><th>vs Baseline</th><th>Range (±)</th></tr></thead>
        <tbody>
        <?php foreach ($next30 as $i => $d):
            $diff = $baseline > 0 ? round($d['pred'] - $baseline, 2) : 0;
            $lo   = round($d['pred'] * (1 - (100-$conf)/200), 1);
            $hi   = round($d['pred'] * (1 + (100-$conf)/200), 1);
        ?>
        <tr <?= $i<7?'class="table-info"':'' ?>>
            <td><?= $i+1 ?></td>
            <td><?= date('D, M j', strtotime($d['date'])) ?></td>
            <td><small class="text-muted"><?= $d['date'] ?></small></td>
            <td><strong><?= number_format($d['pred'],2) ?>L</strong></td>
            <td>
                <?php if ($baseline>0): ?>
                <span class="small fw-bold <?= $diff>=0?'text-success':'text-danger' ?>">
                    <?= $diff>=0?'+':'' ?><?= number_format($diff,2) ?>L
                </span>
                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
            <td><small class="text-muted"><?= $lo ?>–<?= $hi ?>L</small></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <small class="text-info mt-1 d-block"><i class="fa fa-info-circle me-1"></i>Blue rows = next 7 days</small>
</div>

<!-- ── PER-BUFFALO PREDICTIONS ── -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-paw me-2"></i>Per-Buffalo Prediction – Next Week</div>
    <?php if (empty($bufPreds)): ?>
    <div class="alert alert-info mb-0">No active female buffaloes found.</div>
    <?php else: ?>
    <!-- Desktop -->
    <div class="d-none d-md-block table-responsive">
    <table class="table table-sm table-hover mb-0">
        <thead><tr><th>Tag</th><th>Name</th><th>Health</th><th>30-Day Avg</th><th>Predicted/Day</th><th>Trend</th><th>Lactation Stage</th></tr></thead>
        <tbody>
        <?php foreach ($bufPreds as $bp):
            $hCls = match($bp['health_status']) {'Healthy'=>'badge-healthy','Sick'=>'badge-sick','Under Treatment'=>'badge-treated',default=>''};
            $tCls = $bp['trend']==='Rising'?'text-success':($bp['trend']==='Declining'?'text-danger':'text-secondary');
            $tIco = $bp['trend']==='Rising'?'fa-arrow-up':($bp['trend']==='Declining'?'fa-arrow-down':'fa-minus');
        ?>
        <tr>
            <td><span class="badge bg-success"><?= htmlspecialchars($bp['tag_number']) ?></span></td>
            <td><?= htmlspecialchars($bp['name']??'-') ?></td>
            <td><span class="badge-custom <?= $hCls ?>"><?= $bp['health_status'] ?></span></td>
            <td><?= $bp['avg30']>0 ? number_format($bp['avg30'],2).'L' : '<span class="text-muted">—</span>' ?></td>
            <td><?= $bp['pred_wk']>0 ? '<strong class="text-primary">'.number_format($bp['pred_wk'],2).'L</strong>' : '<span class="text-muted">—</span>' ?></td>
            <td><span class="<?= $tCls ?> small fw-bold"><i class="fa <?= $tIco ?> me-1"></i><?= $bp['trend'] ?></span></td>
            <td><span class="badge bg-secondary"><?= $bp['lac'] ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <!-- Mobile -->
    <div class="d-md-none">
    <?php foreach ($bufPreds as $bp):
        $hCls = match($bp['health_status']) {'Healthy'=>'badge-healthy','Sick'=>'badge-sick','Under Treatment'=>'badge-treated',default=>''};
        $tCls = $bp['trend']==='Rising'?'text-success':($bp['trend']==='Declining'?'text-danger':'text-secondary');
    ?>
    <div class="border rounded p-2 mb-2">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <span class="badge bg-success me-1"><?= htmlspecialchars($bp['tag_number']) ?></span>
                <strong><?= htmlspecialchars($bp['name']??'') ?></strong>
                <span class="badge-custom <?= $hCls ?> ms-1"><?= $bp['health_status'] ?></span>
            </div>
            <div class="text-end">
                <?php if ($bp['pred_wk']>0): ?>
                <strong class="text-primary"><?= number_format($bp['pred_wk'],2) ?>L</strong>
                <div style="font-size:.68rem;color:#888">predicted/day</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="mt-1 small">
            <span class="text-muted">Avg: </span><?= $bp['avg30']>0?number_format($bp['avg30'],2).'L':'—' ?>
            <span class="ms-2 <?= $tCls ?>"><i class="fa <?= $bp['trend']==='Rising'?'fa-arrow-up':($bp['trend']==='Declining'?'fa-arrow-down':'fa-minus') ?> me-1"></i><?= $bp['trend'] ?></span>
            <span class="ms-2 badge bg-secondary"><?= $bp['lac'] ?></span>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ── FACTORS ── -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-info-circle me-2"></i>Factors Affecting Prediction</div>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="p-3 rounded border-start border-3 border-danger bg-light h-100">
                <strong class="text-danger"><i class="fa fa-heartbeat me-1"></i>Health Status</strong>
                <p class="small mb-0 mt-1">Sick animals show 15–40% milk reduction. Health is the strongest production predictor.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 rounded border-start border-3 border-info bg-light h-100">
                <strong class="text-info"><i class="fa fa-baby me-1"></i>Lactation Stage</strong>
                <p class="small mb-0 mt-1">Production peaks 4–8 weeks post-calving then gradually declines following a natural lactation curve.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 rounded border-start border-3 border-warning bg-light h-100">
                <strong class="text-warning"><i class="fa fa-sun me-1"></i>Seasonal Factors</strong>
                <p class="small mb-0 mt-1">Yield is slightly higher Oct–Dec and May (cooler months). Adjustment factor: 0.96–1.02.</p>
            </div>
        </div>
    </div>
</div>

<!-- ── ACCURACY ── -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-bullseye me-2"></i>Prediction Accuracy – Last 7 Days</div>
    <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
        <thead><tr><th>Date</th><th>Est. Prediction (L)</th><th>Actual (L)</th><th>Difference</th><th>Error %</th></tr></thead>
        <tbody>
        <?php foreach ($accuracy as $a): ?>
        <tr>
            <td><?= date('D, M j', strtotime($a['date'])) ?></td>
            <td><?= $a['pred']!==null?number_format($a['pred'],2):'<span class="text-muted">—</span>' ?></td>
            <td><?= $a['actual']!==null?number_format($a['actual'],2):'<span class="text-muted">Not recorded</span>' ?></td>
            <td>
                <?php if ($a['diff']!==null): ?>
                <span class="small fw-bold <?= $a['diff']>=0?'text-success':'text-danger' ?>">
                    <?= $a['diff']>=0?'+':'' ?><?= number_format($a['diff'],2) ?>L
                </span>
                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
            <td>
                <?php if ($a['pct']!==null): ?>
                <span class="badge <?= $a['pct']<=10?'bg-success':($a['pct']<=20?'bg-warning text-dark':'bg-danger') ?>"><?= $a['pct'] ?>%</span>
                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<script>
new Chart(document.getElementById('historyChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($c30lab) ?>,
        datasets: [
            { label:'Daily Production (L)', data: <?= json_encode($c30val) ?>, backgroundColor:'rgba(40,167,69,.4)', borderColor:'#28a745', borderWidth:1, borderRadius:3, order:2 },
            { label:'7-Day Moving Avg', data: <?= json_encode($c30mov) ?>, type:'line', borderColor:'#fd7e14', backgroundColor:'transparent', borderWidth:2.5, pointRadius:2, tension:.4, order:1 }
        ]
    },
    options: { responsive:true, plugins:{legend:{position:'top'},tooltip:{mode:'index',intersect:false}}, scales:{y:{beginAtZero:true,title:{display:true,text:'Liters'}}} }
});

new Chart(document.getElementById('forecastChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: <?= json_encode($f7lab) ?>,
        datasets: [{ label:'Predicted (L)', data: <?= json_encode($f7val) ?>, borderColor:'#17a2b8', backgroundColor:'rgba(23,162,184,.15)', fill:true, tension:.4, pointRadius:5, pointBackgroundColor:'#17a2b8', borderWidth:2.5 }]
    },
    options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:false,title:{display:true,text:'Liters'}}} }
});
</script>

<?php include '../includes/footer.php'; ?>
<?php ob_end_flush(); ?>
