<?php
/**
 * DairyBox – Milk Yield Prediction
 * Uses output buffering to prevent blank page from redirect issues
 */
ob_start();

// Manual session setup (same as config/session.php but inline)
if (session_status() === PHP_SESSION_NONE) {
    $sp = '/tmp/dairybox_sessions';
    if (!is_dir($sp)) @mkdir($sp, 0777, true);
    ini_set('session.save_path', $sp);
    ini_set('session.gc_maxlifetime', 86400);
    session_start();
}

// Manual auth check
if (!isset($_SESSION['user'])) {
    ob_end_clean();
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base  = $proto . '://' . $_SERVER['HTTP_HOST'];
    header('Location: ' . $base . '/index.php?error=Please+login+first');
    exit;
}

require_once '../config/database.php';

$root      = '../';
$pageTitle = 'Milk Yield Prediction';
$db        = getDB();
$user      = $_SESSION['user'];

// ── Linear Regression ────────────────────────────────
function linReg(array $y): array {
    $n = count($y);
    if ($n < 2) return ['slope' => 0, 'intercept' => $n ? $y[0] : 0];
    $sx=$sy=$sxy=$sx2=0;
    for ($i=0;$i<$n;$i++) { $sx+=$i; $sy+=$y[$i]; $sxy+=$i*$y[$i]; $sx2+=$i*$i; }
    $d = $n*$sx2 - $sx*$sx;
    if (!$d) return ['slope'=>0,'intercept'=>$sy/$n];
    $m = ($n*$sxy-$sx*$sy)/$d;
    return ['slope'=>$m, 'intercept'=>($sy-$m*$sx)/$n];
}

// ── Fetch 90 days production ──────────────────────────
$dailyProd = [];
$dbError   = null;
try {
    $rows = $db->query("
        SELECT record_date, SUM(quantity_liters) AS total
        FROM milk_production
        WHERE record_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        GROUP BY record_date ORDER BY record_date ASC
    ")->fetchAll();
    foreach ($rows as $r) $dailyProd[$r['record_date']] = (float)$r['total'];
} catch (Exception $e) { $dbError = $e->getMessage(); }

// Build 90-day arrays
$allDates = $allVals = [];
for ($i=89;$i>=0;$i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $allDates[] = $d;
    $allVals[]  = $dailyProd[$d] ?? 0;
}

// 7-day moving average
$mov7 = [];
for ($i=0;$i<count($allVals);$i++) {
    $w = array_filter(array_slice($allVals,max(0,$i-6),7),fn($v)=>$v>0);
    $mov7[] = count($w) ? round(array_sum($w)/count($w),2) : 0;
}

// Baseline & trend
$last7  = array_values(array_filter(array_slice($allVals,-7),fn($v)=>$v>0));
$last14 = array_values(array_filter(array_slice($allVals,-14),fn($v)=>$v>0));
$base   = count($last7)  ? array_sum($last7)/count($last7)  : 0;
$trend  = linReg(count($last14)>=2 ? $last14 : (count($allVals)?$allVals:[0]));

// Seasonal
$sea = [1=>.97,2=>.96,3=>.98,4=>1.0,5=>1.02,6=>1.0,7=>.98,8=>.97,9=>.99,10=>1.01,11=>1.02,12=>1.0];

// Next 7 & 30 days
$n7=$n30=[];
$off=count($last14);
for ($i=1;$i<=30;$i++) {
    $dt  = date('Y-m-d', strtotime("+{$i} days"));
    $mo  = (int)date('m', strtotime("+{$i} days"));
    $p   = max(0, ($trend['slope']*($off+$i)+$trend['intercept'])*($sea[$mo]??1.0));
    if ($base>0) $p = max($base*.6, min($base*1.4, $p));
    $p = round($p,2);
    if ($i<=7) $n7[] = ['date'=>$dt,'pred'=>$p];
    $n30[] = ['date'=>$dt,'pred'=>$p];
}

// Confidence
$conf=50;
if (count($last14)>=4) {
    $m2=array_sum($last14)/count($last14);
    if ($m2>0) {
        $cv=sqrt(array_sum(array_map(fn($v)=>($v-$m2)**2,$last14))/count($last14))/$m2;
        $conf=(int)max(30,min(95,round((1-$cv)*100)));
    }
}

// Today
$todayAct = $dailyProd[date('Y-m-d')] ?? null;
$todayPrd = $base>0 ? round($base*($sea[(int)date('m')]??1.0),2) : 0;

// Accuracy last 7 days
$acc=[];
for ($i=7;$i>=1;$i--) {
    $d  = date('Y-m-d', strtotime("-{$i} days"));
    $ac = $dailyProd[$d] ?? null;
    $ps = $pc = 0;
    for ($j=8;$j<=14;$j++) {
        $pd = date('Y-m-d', strtotime("-".($i+$j)." days"));
        if (isset($dailyProd[$pd])&&$dailyProd[$pd]>0) { $ps+=$dailyProd[$pd]; $pc++; }
    }
    $ep = $pc ? round($ps/$pc,2) : null;
    $acc[] = ['date'=>$d,'actual'=>$ac,'pred'=>$ep,
        'diff'=>($ac!==null&&$ep!==null)?round($ac-$ep,2):null,
        'pct'=>($ac!==null&&$ep!==null&&$ep>0)?round(abs($ac-$ep)/$ep*100,1):null];
}

// Per-buffalo
$bufPreds=[];
try {
    $fs = $db->query("SELECT id,tag_number,name,health_status FROM buffaloes WHERE status='Active' AND sex='Female' ORDER BY tag_number")->fetchAll();
    foreach ($fs as $buf) {
        $bid=$buf['id'];
        $st=$db->prepare("SELECT record_date, SUM(quantity_liters) AS total FROM milk_production WHERE buffalo_id=? AND record_date>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY record_date ORDER BY record_date ASC");
        $st->execute([$bid]);
        $bv=array_map(fn($r)=>(float)$r['total'],$st->fetchAll());
        $bnz=array_values(array_filter($bv,fn($v)=>$v>0));
        if (empty($bnz)) { $bufPreds[]=$buf+['avg'=>0,'pred'=>0,'trend'=>'No data','lac'=>'Unknown']; continue; }
        $ba=round(array_sum($bnz)/count($bnz),2);
        $bt=linReg($bnz);
        $bp=max(0,$bt['slope']*(count($bnz)+3)+$bt['intercept']);
        $bp=round(max($ba*.6,min($ba*1.4,$bp)),2);
        $td=$bt['slope']>.05?'Rising':($bt['slope']<-.05?'Declining':'Stable');
        $fm=$db->prepare("SELECT MIN(record_date) FROM milk_production WHERE buffalo_id=?");
        $fm->execute([$bid]); $fd=$fm->fetchColumn();
        $ld=$fd?(int)(new DateTime())->diff(new DateTime($fd))->days:null;
        $lac=$ld===null?'Unknown':($ld<=90?'Early':($ld<=200?'Mid':($ld<=305?'Late':'Extended')));
        $bufPreds[]=$buf+['avg'=>$ba,'pred'=>$bp,'trend'=>$td,'lac'=>$lac];
    }
} catch(Exception $e){ $dbError=$dbError?:$e->getMessage(); }

// Chart data
$c30l=array_slice($allDates,-30); $c30v=array_slice($allVals,-30); $c30m=array_slice($mov7,-30);
$f7l=array_column($n7,'date'); $f7v=array_column($n7,'pred');

// ── Role labels for header ───────────────────────────
$roleLabels=['farm_manager'=>'Farm Manager','farm_caretaker'=>'Farm Caretaker',
             'dairy_cooperative'=>'Dairy Cooperative','veterinarian'=>'Veterinarian'];
$roleLabel=$roleLabels[$user['role']]??'User';

// Nav file
$navFile = $root.'includes/nav_'.$user['role'].'.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<meta name="theme-color" content="#1a6b3c">
<title>Milk Yield Prediction | DairyBox</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= $root ?>assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="app-body">
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="<?= $root ?>assets/img/logo.jpg" alt="Logo" style="width:48px;height:48px;object-fit:contain;border-radius:8px;margin-bottom:.3rem;display:block;margin-left:auto;margin-right:auto" onerror="this.style.display='none'">
        <h5>DairyBox</h5><p>Production & Herd Health</p>
    </div>
    <nav><?php if (file_exists($navFile)) include $navFile; ?></nav>
    <div class="sidebar-footer">
        <i class="fa fa-user-circle me-1"></i>
        <strong><?= htmlspecialchars($user['full_name']) ?></strong><br>
        <span class="badge bg-success mt-1"><?= $roleLabel ?></span><br>
        <a href="<?= $root ?>auth/logout.php" onclick="return confirm('Log out?')"
           class="btn btn-sm btn-outline-danger mt-2 w-100" style="font-size:.78rem">
            <i class="fa fa-sign-out-alt me-1"></i>Logout
        </a>
    </div>
</aside>
<div class="sidebar-backdrop" id="cartOverlay" style="display:none"></div>
<header class="topbar">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-sm" style="border:none;background:none;font-size:1.15rem;color:#1a6b3c" onclick="toggleSidebar()"><i class="fa fa-bars"></i></button>
        <span class="page-title">Milk Yield Prediction</span>
    </div>
    <div class="topbar-right">
        <span class="user-badge d-none d-md-inline"><?= htmlspecialchars($user['full_name']) ?></span>
        <a href="<?= $root ?>auth/logout.php" class="btn btn-sm btn-outline-danger d-none d-md-inline-flex" style="font-size:.8rem">
            <i class="fa fa-sign-out-alt"></i>
        </a>
    </div>
</header>
<main class="main-content"><div class="content-body">

<?php if ($dbError): ?>
<div class="alert alert-danger"><i class="fa fa-exclamation-circle me-2"></i><strong>Error:</strong> <?= htmlspecialchars($dbError) ?></div>
<?php endif; ?>

<?php if (empty($dailyProd) && !$dbError): ?>
<div class="card-section text-center py-5">
    <div style="font-size:3.5rem">📊</div>
    <h5 class="text-muted mt-3">No Milk Production Data Available</h5>
    <p class="text-muted">Record daily milk production to enable yield predictions.</p>
    <a href="milk_production.php?action=add" class="btn btn-success"><i class="fa fa-plus me-1"></i>Record Milk Production</a>
</div>
<?php else: ?>

<!-- SUMMARY STATS -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#cce5ff"><i class="fa fa-tint text-primary" style="font-size:1.4rem"></i></div>
            <div><p class="stat-label">Today Predicted</p><p class="stat-value text-primary"><?= number_format($todayPrd,1) ?>L</p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#d4edda"><i class="fa fa-check-circle text-success" style="font-size:1.4rem"></i></div>
            <div><p class="stat-label">Today Actual</p><p class="stat-value text-success"><?= $todayAct!==null?number_format($todayAct,1).'L':'—' ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff3cd"><i class="fa fa-chart-line text-warning" style="font-size:1.4rem"></i></div>
            <div><p class="stat-label">7-Day Baseline</p><p class="stat-value text-warning"><?= number_format($base,1) ?>L</p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e2d9f3"><i class="fa fa-bullseye" style="font-size:1.4rem;color:#6f42c1"></i></div>
            <div><p class="stat-label">Confidence</p><p class="stat-value" style="color:#6f42c1"><?= $conf ?>%</p></div>
        </div>
    </div>
</div>

<!-- CHARTS ROW -->
<div class="row g-3 mb-4">
    <div class="col-md-5">
        <div class="card-section h-100">
            <div class="section-title"><i class="fa fa-balance-scale me-2"></i>Today: Predicted vs Actual</div>
            <div class="row g-2 text-center mb-3">
                <div class="col-6">
                    <div class="border rounded p-3">
                        <div class="fw-bold text-primary" style="font-size:1.6rem"><?= number_format($todayPrd,1) ?>L</div>
                        <div style="font-size:.75rem;color:#888">Predicted</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="border rounded p-3">
                        <div class="fw-bold text-success" style="font-size:1.6rem"><?= $todayAct!==null?number_format($todayAct,1).'L':'—' ?></div>
                        <div style="font-size:.75rem;color:#888">Actual</div>
                    </div>
                </div>
            </div>
            <?php if ($todayAct!==null && $todayPrd>0): $df=round($todayAct-$todayPrd,1); ?>
            <div class="text-center mb-2">
                <span style="font-size:.95rem;font-weight:600;color:<?= $df>=0?'#28a745':'#dc3545' ?>">
                    <i class="fa fa-arrow-<?= $df>=0?'up':'down' ?> me-1"></i>
                    <?= abs($df) ?>L <?= $df>=0?'above':'below' ?> prediction
                </span>
            </div>
            <?php endif; ?>
            <hr class="my-2">
            <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">14-day slope:</span>
                <strong class="<?= $trend['slope']>=0?'text-success':'text-danger' ?>">
                    <?= $trend['slope']>=0?'+':'' ?><?= number_format($trend['slope'],3) ?> L/day
                </strong>
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
            <canvas id="fChart" height="130"></canvas>
        </div>
    </div>
</div>

<!-- HISTORY CHART -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-chart-area me-2"></i>Last 30 Days + 7-Day Moving Average</div>
    <canvas id="hChart" height="80"></canvas>
</div>

<!-- 30-DAY FORECAST TABLE -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-calendar-alt me-2"></i>30-Day Production Forecast</div>
    <p class="text-muted small mb-2">Confidence: <strong><?= $conf ?>%</strong></p>
    <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
        <thead><tr><th>#</th><th>Date</th><th>Day</th><th>Predicted (L)</th><th>vs Baseline</th><th>Range</th></tr></thead>
        <tbody>
        <?php foreach ($n30 as $i=>$d):
            $df2=$base>0?round($d['pred']-$base,2):0;
            $lo=round($d['pred']*(1-(100-$conf)/200),1);
            $hi=round($d['pred']*(1+(100-$conf)/200),1);
        ?>
        <tr <?= $i<7?'class="table-info"':'' ?>>
            <td><?= $i+1 ?></td>
            <td><?= date('D, M j',strtotime($d['date'])) ?></td>
            <td><small class="text-muted"><?= $d['date'] ?></small></td>
            <td><strong><?= number_format($d['pred'],2) ?>L</strong></td>
            <td><?php if($base>0): ?><span class="small fw-bold <?= $df2>=0?'text-success':'text-danger' ?>"><?= $df2>=0?'+':'' ?><?= number_format($df2,2) ?>L</span><?php else: ?>—<?php endif; ?></td>
            <td><small class="text-muted"><?= $lo ?>–<?= $hi ?>L</small></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <small class="text-info mt-1 d-block"><i class="fa fa-info-circle me-1"></i>Blue = next 7 days</small>
</div>

<!-- PER-BUFFALO -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-paw me-2"></i>Per-Buffalo Prediction – Next Week</div>
    <?php if (empty($bufPreds)): ?>
    <div class="alert alert-info mb-0">No active female buffaloes found.</div>
    <?php else: ?>
    <div class="d-none d-md-block table-responsive">
    <table class="table table-sm table-hover mb-0">
        <thead><tr><th>Tag</th><th>Name</th><th>Health</th><th>30-Day Avg</th><th>Predicted/Day</th><th>Trend</th><th>Lactation</th></tr></thead>
        <tbody>
        <?php foreach ($bufPreds as $bp):
            $hc=match($bp['health_status']){'Healthy'=>'badge-healthy','Sick'=>'badge-sick','Under Treatment'=>'badge-treated',default=>''};
            $tc=$bp['trend']==='Rising'?'text-success':($bp['trend']==='Declining'?'text-danger':'text-secondary');
            $ti=$bp['trend']==='Rising'?'fa-arrow-up':($bp['trend']==='Declining'?'fa-arrow-down':'fa-minus');
        ?>
        <tr>
            <td><span class="badge bg-success"><?= htmlspecialchars($bp['tag_number']) ?></span></td>
            <td><?= htmlspecialchars($bp['name']??'-') ?></td>
            <td><span class="badge-custom <?= $hc ?>"><?= $bp['health_status'] ?></span></td>
            <td><?= $bp['avg']>0?number_format($bp['avg'],2).'L':'<span class="text-muted">—</span>' ?></td>
            <td><?= $bp['pred']>0?'<strong class="text-primary">'.number_format($bp['pred'],2).'L</strong>':'<span class="text-muted">—</span>' ?></td>
            <td><span class="<?= $tc ?> small fw-bold"><i class="fa <?= $ti ?> me-1"></i><?= $bp['trend'] ?></span></td>
            <td><span class="badge bg-secondary"><?= $bp['lac'] ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div class="d-md-none">
    <?php foreach ($bufPreds as $bp):
        $hc=match($bp['health_status']){'Healthy'=>'badge-healthy','Sick'=>'badge-sick','Under Treatment'=>'badge-treated',default=>''};
        $tc=$bp['trend']==='Rising'?'text-success':($bp['trend']==='Declining'?'text-danger':'text-secondary');
    ?>
    <div class="border rounded p-2 mb-2">
        <div class="d-flex justify-content-between">
            <div><span class="badge bg-success me-1"><?= htmlspecialchars($bp['tag_number']) ?></span><strong><?= htmlspecialchars($bp['name']??'') ?></strong><span class="badge-custom <?= $hc ?> ms-1"><?= $bp['health_status'] ?></span></div>
            <?php if ($bp['pred']>0): ?><strong class="text-primary"><?= number_format($bp['pred'],2) ?>L</strong><?php endif; ?>
        </div>
        <div class="mt-1 small">Avg: <?= $bp['avg']>0?number_format($bp['avg'],2).'L':'—' ?> <span class="ms-2 <?= $tc ?>"><?= $bp['trend'] ?></span> <span class="badge bg-secondary ms-1"><?= $bp['lac'] ?></span></div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ACCURACY -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-bullseye me-2"></i>Prediction Accuracy – Last 7 Days</div>
    <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
        <thead><tr><th>Date</th><th>Est. Prediction</th><th>Actual</th><th>Difference</th><th>Error %</th></tr></thead>
        <tbody>
        <?php foreach ($acc as $a): ?>
        <tr>
            <td><?= date('D, M j',strtotime($a['date'])) ?></td>
            <td><?= $a['pred']!==null?number_format($a['pred'],2).'L':'<span class="text-muted">—</span>' ?></td>
            <td><?= $a['actual']!==null?number_format($a['actual'],2).'L':'<span class="text-muted">Not recorded</span>' ?></td>
            <td><?php if ($a['diff']!==null): ?><span class="small fw-bold <?= $a['diff']>=0?'text-success':'text-danger' ?>"><?= $a['diff']>=0?'+':'' ?><?= number_format($a['diff'],2) ?>L</span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
            <td><?php if ($a['pct']!==null): ?><span class="badge <?= $a['pct']<=10?'bg-success':($a['pct']<=20?'bg-warning text-dark':'bg-danger') ?>"><?= $a['pct'] ?>%</span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<?php endif; ?>

</div></main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar(){const s=document.getElementById('sidebar'),b=document.getElementById('sidebarBackdrop');if(s.classList.contains('open')){s.classList.remove('open');b.classList.remove('show');document.body.style.overflow='';}else{s.classList.add('open');b.classList.add('show');document.body.style.overflow='hidden';}}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarBackdrop').classList.remove('show');document.body.style.overflow='';}
document.querySelectorAll('.sidebar nav a').forEach(a=>{if(window.location.href.includes(a.href))a.classList.add('active');});

<?php if (!empty($dailyProd)): ?>
new Chart(document.getElementById('hChart'),{type:'bar',data:{labels:<?= json_encode($c30l) ?>,datasets:[{label:'Daily (L)',data:<?= json_encode($c30v) ?>,backgroundColor:'rgba(40,167,69,.4)',borderColor:'#28a745',borderWidth:1,borderRadius:3,order:2},{label:'7-Day Avg',data:<?= json_encode($c30m) ?>,type:'line',borderColor:'#fd7e14',backgroundColor:'transparent',borderWidth:2.5,pointRadius:2,tension:.4,order:1}]},options:{responsive:true,plugins:{legend:{position:'top'}},scales:{y:{beginAtZero:true}}}});
new Chart(document.getElementById('fChart'),{type:'line',data:{labels:<?= json_encode($f7l) ?>,datasets:[{label:'Predicted (L)',data:<?= json_encode($f7v) ?>,borderColor:'#17a2b8',backgroundColor:'rgba(23,162,184,.15)',fill:true,tension:.4,pointRadius:5,pointBackgroundColor:'#17a2b8',borderWidth:2.5}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:false}}}});
<?php endif; ?>
</script>
</body>
</html>
<?php ob_end_flush(); ?>
