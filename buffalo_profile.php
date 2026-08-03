<?php
/**
 * DairyBox – Public Buffalo Profile Page
 * ---------------------------------------
 * This page is opened when a QR code is scanned.
 * No login required — read-only view of buffalo info.
 * URL: http://yoursite/dairybox/buffalo_profile.php?id=1
 *      http://yoursite/dairybox/buffalo_profile.php?qr=QR-BUF-001
 */
require_once 'config/database.php';

$db  = getDB();
$bid = (int)($_GET['id'] ?? 0);
$qr  = trim($_GET['qr']  ?? '');

$buffalo = null;
if ($bid) {
    $s = $db->prepare("SELECT * FROM buffaloes WHERE id=? AND status='Active'");
    $s->execute([$bid]); $buffalo = $s->fetch();
} elseif ($qr) {
    $s = $db->prepare("SELECT * FROM buffaloes WHERE (qr_code=? OR tag_number=?) AND status='Active'");
    $s->execute([$qr, $qr]); $buffalo = $s->fetch();
}

if (!$buffalo) {
    // Buffalo not found page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Buffalo Not Found – DairyBox</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body class="login-page">
        <div style="text-align:center;color:#fff;padding:3rem">
            <div style="font-size:5rem">🔍</div>
            <h2>Buffalo Not Found</h2>
            <p>The QR code scanned does not match any active buffalo record.</p>
            <a href="index.php" class="btn btn-light">Go to Login</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$id = $buffalo['id'];

// ---- Load all records ----
// Milk – this month
$milkMonth = $db->prepare("
    SELECT COALESCE(SUM(quantity_liters),0) as total,
           COALESCE(AVG(quantity_liters),0) as avg_sess,
           MAX(record_date) as last_record,
           COUNT(*) as sessions
    FROM milk_production
    WHERE buffalo_id=? AND MONTH(record_date)=MONTH(CURDATE()) AND YEAR(record_date)=YEAR(CURDATE())
");
$milkMonth->execute([$id]);
$milkMonth = $milkMonth->fetch();

// Milk – last 7 days for chart
$milkChart = $db->prepare("
    SELECT record_date, SUM(quantity_liters) as total
    FROM milk_production
    WHERE buffalo_id=? AND record_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY record_date ORDER BY record_date ASC
");
$milkChart->execute([$id]);
$milkChart = $milkChart->fetchAll();

$chartLabels = [];
$chartData   = [];
foreach ($milkChart as $r) {
    $chartLabels[] = date('M d', strtotime($r['record_date']));
    $chartData[]   = (float)$r['total'];
}

// Health records – latest 5
$healthRecs = $db->prepare("SELECT * FROM health_records WHERE buffalo_id=? ORDER BY record_date DESC LIMIT 5");
$healthRecs->execute([$id]);
$healthRecs = $healthRecs->fetchAll();

// Vaccinations – latest 5
$vaccRecs = $db->prepare("SELECT * FROM vaccinations WHERE buffalo_id=? ORDER BY administered_date DESC LIMIT 5");
$vaccRecs->execute([$id]);
$vaccRecs = $vaccRecs->fetchAll();

// Next due vaccination
$nextVacc = $db->prepare("SELECT * FROM vaccinations WHERE buffalo_id=? AND next_due_date >= CURDATE() ORDER BY next_due_date ASC LIMIT 1");
$nextVacc->execute([$id]);
$nextVacc = $nextVacc->fetch();

// Overdue vaccination
$overdueVacc = $db->prepare("SELECT * FROM vaccinations WHERE buffalo_id=? AND status='Overdue' LIMIT 1");
$overdueVacc->execute([$id]);
$overdueVacc = $overdueVacc->fetch();

// Breeding – latest
$breedRec = $db->prepare("SELECT * FROM breeding_records WHERE buffalo_id=? ORDER BY breeding_date DESC LIMIT 1");
$breedRec->execute([$id]);
$breedRec = $breedRec->fetch();

// Calving history
$calvingRecs = $db->prepare("SELECT * FROM calving_records WHERE mother_id=? ORDER BY calving_date DESC LIMIT 3");
$calvingRecs->execute([$id]);
$calvingRecs = $calvingRecs->fetchAll();

// Health status color
$hColor = match($buffalo['health_status']) {
    'Healthy'         => ['bg' => '#d4edda', 'text' => '#155724', 'icon' => '✅'],
    'Sick'            => ['bg' => '#f8d7da', 'text' => '#721c24', 'icon' => '🤒'],
    'Under Treatment' => ['bg' => '#cce5ff', 'text' => '#004085', 'icon' => '💊'],
    'Recovered'       => ['bg' => '#d1ecf1', 'text' => '#0c5460', 'icon' => '🔄'],
    default           => ['bg' => '#f8f9fa', 'text' => '#333',    'icon' => '🐃'],
};

// Age calculation
$age = '';
if ($buffalo['date_of_birth']) {
    $dob   = new DateTime($buffalo['date_of_birth']);
    $now   = new DateTime();
    $diff  = $dob->diff($now);
    $age   = $diff->y > 0 ? $diff->y . ' yr' . ($diff->m > 0 ? ' ' . $diff->m . ' mo' : '') : $diff->m . ' months';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title><?= htmlspecialchars($buffalo['name'] ?? $buffalo['tag_number']) ?> – DairyBox</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --green-dark: #1a6b3c;
            --green:      #28a745;
            --green-light:#d4edda;
            --cream:      #fdf6ec;
        }
        * { box-sizing: border-box; }
        body {
            background: var(--cream);
            font-family: 'Segoe UI', sans-serif;
            margin: 0; padding: 0;
        }

        /* ---- Top Banner ---- */
        .profile-banner {
            background: linear-gradient(135deg, var(--green-dark), var(--green));
            color: #fff;
            padding: 1.2rem 1rem .8rem;
            text-align: center;
        }
        .profile-banner .tag-badge {
            display: inline-block;
            background: rgba(255,255,255,.2);
            border: 1px solid rgba(255,255,255,.4);
            border-radius: 20px;
            padding: .2rem .8rem;
            font-size: .8rem;
            letter-spacing: .5px;
            margin-bottom: .4rem;
        }
        .profile-banner h2 {
            margin: 0; font-size: 1.6rem; font-weight: 700;
        }
        .profile-banner .breed-text {
            opacity: .85; font-size: .85rem; margin-top: .2rem;
        }
        .health-pill {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .35rem 1rem;
            border-radius: 20px;
            font-weight: 700;
            font-size: .85rem;
            margin-top: .5rem;
        }

        /* ---- Content ---- */
        .page-body { padding: 1rem; max-width: 600px; margin: 0 auto; }

        /* ---- Stat row ---- */
        .stat-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: .5rem;
            margin-bottom: 1rem;
        }
        .stat-box {
            background: #fff;
            border-radius: 10px;
            padding: .7rem .4rem;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0,0,0,.07);
        }
        .stat-box .val { font-size: 1.1rem; font-weight: 700; color: var(--green-dark); }
        .stat-box .lbl { font-size: .65rem; color: #888; margin-top: .1rem; }

        /* ---- Info grid ---- */
        .info-card {
            background: #fff;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 2px 6px rgba(0,0,0,.07);
            margin-bottom: .8rem;
        }
        .info-card .card-title {
            font-size: .8rem;
            font-weight: 700;
            color: var(--green-dark);
            text-transform: uppercase;
            letter-spacing: .5px;
            border-bottom: 2px solid var(--green-light);
            padding-bottom: .4rem;
            margin-bottom: .7rem;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: .3rem 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: .86rem;
        }
        .info-row:last-child { border-bottom: none; }
        .info-row .lbl { color: #888; }
        .info-row .val { font-weight: 600; text-align: right; }

        /* ---- Table ---- */
        .mini-table { width: 100%; font-size: .8rem; border-collapse: collapse; }
        .mini-table th {
            background: var(--green-dark);
            color: #fff;
            padding: .4rem .6rem;
            text-align: left;
            font-weight: 600;
        }
        .mini-table td { padding: .4rem .6rem; border-bottom: 1px solid #f0f0f0; }
        .mini-table tr:last-child td { border-bottom: none; }
        .mini-table tr:nth-child(even) td { background: #fafafa; }

        /* ---- Alert banners ---- */
        .alert-banner {
            border-radius: 10px;
            padding: .7rem 1rem;
            margin-bottom: .8rem;
            font-size: .85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .alert-danger-soft  { background: #f8d7da; color: #721c24; }
        .alert-warning-soft { background: #fff3cd; color: #856404; }
        .alert-success-soft { background: #d4edda; color: #155724; }

        /* ---- Chart ---- */
        .chart-wrap { height: 130px; margin-top: .5rem; }

        /* ---- Tab nav ---- */
        .tab-nav {
            display: flex;
            border-bottom: 2px solid var(--green-light);
            margin-bottom: .8rem;
            gap: 0;
        }
        .tab-nav button {
            flex: 1;
            background: none;
            border: none;
            padding: .5rem .3rem;
            font-size: .78rem;
            font-weight: 600;
            color: #888;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: color .2s, border-color .2s;
        }
        .tab-nav button.active { color: var(--green-dark); border-bottom-color: var(--green-dark); }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        /* ---- Footer ---- */
        .page-footer {
            text-align: center;
            padding: 1.5rem 1rem;
            color: #aaa;
            font-size: .72rem;
        }
        .page-footer strong { color: var(--green-dark); }

        /* ---- Badge ---- */
        .status-badge {
            display: inline-block;
            padding: .2em .6em;
            border-radius: 12px;
            font-size: .75rem;
            font-weight: 600;
        }
        .sb-healthy  { background:#d4edda; color:#155724; }
        .sb-sick     { background:#f8d7da; color:#721c24; }
        .sb-treated  { background:#cce5ff; color:#004085; }
        .sb-done     { background:#d4edda; color:#155724; }
        .sb-overdue  { background:#f8d7da; color:#721c24; }
        .sb-scheduled{ background:#fff3cd; color:#856404; }
        .sb-confirmed{ background:#fff3cd; color:#856404; }
        .sb-delivered{ background:#d4edda; color:#155724; }
    </style>
</head>
<body>

<!-- ===== BANNER ===== -->
<div class="profile-banner">
    <div class="tag-badge">🐃 <?= htmlspecialchars($buffalo['tag_number']) ?></div>
    <h2><?= htmlspecialchars($buffalo['name'] ?? 'Unnamed Buffalo') ?></h2>
    <div class="breed-text"><?= htmlspecialchars($buffalo['breed'] ?? 'Unknown Breed') ?> &bull; <?= $buffalo['sex'] ?><?= $age ? ' &bull; ' . $age : '' ?></div>
    <div>
        <span class="health-pill" style="background:<?= $hColor['bg'] ?>;color:<?= $hColor['text'] ?>">
            <?= $hColor['icon'] ?> <?= $buffalo['health_status'] ?>
        </span>
    </div>
</div>

<div class="page-body">

    <!-- ===== ALERTS ===== -->
    <?php if ($buffalo['health_status'] === 'Sick' || $buffalo['health_status'] === 'Under Treatment'): ?>
    <div class="alert-banner alert-danger-soft">
        <i class="fa fa-exclamation-circle"></i>
        This animal is currently <?= strtolower($buffalo['health_status']) ?>. Contact the veterinarian.
    </div>
    <?php endif; ?>

    <?php if ($overdueVacc): ?>
    <div class="alert-banner alert-warning-soft">
        <i class="fa fa-syringe"></i>
        Overdue vaccination: <strong><?= htmlspecialchars($overdueVacc['vaccine_name']) ?></strong>
        (was due <?= $overdueVacc['next_due_date'] ?>)
    </div>
    <?php endif; ?>

    <?php if ($breedRec && $breedRec['pregnancy_status'] === 'Confirmed' && $breedRec['expected_calving']): ?>
    <?php $daysLeft = (int)((strtotime($breedRec['expected_calving']) - time()) / 86400); ?>
    <?php if ($daysLeft >= 0 && $daysLeft <= 30): ?>
    <div class="alert-banner alert-warning-soft">
        <i class="fa fa-baby"></i>
        Expected calving in <strong><?= $daysLeft ?> day(s)</strong> — <?= $breedRec['expected_calving'] ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- ===== MILK STATS ===== -->
    <div class="stat-row">
        <div class="stat-box">
            <div class="val"><?= number_format($milkMonth['total'] ?? 0, 1) ?>L</div>
            <div class="lbl">Month Total</div>
        </div>
        <div class="stat-box">
            <div class="val"><?= number_format($milkMonth['avg_sess'] ?? 0, 1) ?>L</div>
            <div class="lbl">Avg/Session</div>
        </div>
        <div class="stat-box">
            <div class="val"><?= $milkMonth['sessions'] ?? 0 ?></div>
            <div class="lbl">Sessions</div>
        </div>
        <div class="stat-box">
            <div class="val" style="font-size:.85rem"><?= $milkMonth['last_record'] ? date('M d', strtotime($milkMonth['last_record'])) : '—' ?></div>
            <div class="lbl">Last Record</div>
        </div>
    </div>

    <!-- ===== MILK CHART ===== -->
    <?php if (!empty($chartData)): ?>
    <div class="info-card">
        <div class="card-title"><i class="fa fa-tint me-1"></i> Milk Production — Last 7 Days</div>
        <div class="chart-wrap">
            <canvas id="milkChart"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== BUFFALO INFO ===== -->
    <div class="info-card">
        <div class="card-title"><i class="fa fa-id-card me-1"></i> Animal Information</div>
        <div class="info-row"><span class="lbl">Tag Number</span><span class="val"><?= htmlspecialchars($buffalo['tag_number']) ?></span></div>
        <div class="info-row"><span class="lbl">Name</span><span class="val"><?= htmlspecialchars($buffalo['name'] ?? '—') ?></span></div>
        <div class="info-row"><span class="lbl">Breed</span><span class="val"><?= htmlspecialchars($buffalo['breed'] ?? '—') ?></span></div>
        <div class="info-row"><span class="lbl">Sex</span><span class="val"><?= $buffalo['sex'] ?></span></div>
        <div class="info-row"><span class="lbl">Date of Birth</span><span class="val"><?= $buffalo['date_of_birth'] ?? '—' ?><?= $age ? " ($age)" : '' ?></span></div>
        <div class="info-row"><span class="lbl">Weight</span><span class="val"><?= $buffalo['weight_kg'] ? $buffalo['weight_kg'] . ' kg' : '—' ?></span></div>
        <div class="info-row"><span class="lbl">Color</span><span class="val"><?= htmlspecialchars($buffalo['color'] ?? '—') ?></span></div>
        <div class="info-row"><span class="lbl">Acquired</span><span class="val"><?= $buffalo['acquisition_date'] ?? '—' ?> (<?= $buffalo['acquisition_type'] ?? '—' ?>)</span></div>
        <div class="info-row"><span class="lbl">Health Status</span>
            <span class="val">
                <?php $sc = match($buffalo['health_status']) {'Healthy'=>'sb-healthy','Sick'=>'sb-sick',default=>'sb-treated'}; ?>
                <span class="status-badge <?= $sc ?>"><?= $buffalo['health_status'] ?></span>
            </span>
        </div>
        <?php if ($buffalo['notes']): ?>
        <div class="info-row"><span class="lbl">Notes</span><span class="val"><?= htmlspecialchars($buffalo['notes']) ?></span></div>
        <?php endif; ?>
    </div>

    <!-- ===== TABS: Health / Vaccination / Breeding ===== -->
    <div class="info-card">
        <div class="tab-nav">
            <button class="active" onclick="showTab('health', this)"><i class="fa fa-heartbeat me-1"></i>Health</button>
            <button onclick="showTab('vacc', this)"><i class="fa fa-syringe me-1"></i>Vaccines</button>
            <button onclick="showTab('breed', this)"><i class="fa fa-venus-mars me-1"></i>Breeding</button>
            <?php if (!empty($calvingRecs)): ?>
            <button onclick="showTab('calv', this)"><i class="fa fa-baby me-1"></i>Calving</button>
            <?php endif; ?>
        </div>

        <!-- Health Tab -->
        <div id="tab-health" class="tab-pane active">
            <?php if (empty($healthRecs)): ?>
                <p class="text-center text-muted py-2" style="font-size:.84rem">No health records on file.</p>
            <?php else: ?>
            <table class="mini-table">
                <thead><tr><th>Date</th><th>Type</th><th>Diagnosis</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($healthRecs as $h):
                    $sCls = match($h['status']) {'Resolved'=>'sb-done','Active'=>'sb-sick',default=>'sb-treated'};
                ?>
                <tr>
                    <td><?= $h['record_date'] ?></td>
                    <td><?= $h['condition_type'] ?></td>
                    <td><?= htmlspecialchars($h['diagnosis'] ?? '—') ?></td>
                    <td><span class="status-badge <?= $sCls ?>"><?= $h['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Vaccination Tab -->
        <div id="tab-vacc" class="tab-pane">
            <?php if ($nextVacc): ?>
            <div class="alert-banner alert-success-soft mb-2" style="font-size:.8rem">
                <i class="fa fa-calendar-check"></i>
                Next: <strong><?= htmlspecialchars($nextVacc['vaccine_name']) ?></strong> due <?= $nextVacc['next_due_date'] ?>
            </div>
            <?php endif; ?>
            <?php if (empty($vaccRecs)): ?>
                <p class="text-center text-muted py-2" style="font-size:.84rem">No vaccination records on file.</p>
            <?php else: ?>
            <table class="mini-table">
                <thead><tr><th>Vaccine</th><th>Given</th><th>Next Due</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($vaccRecs as $v):
                    $sCls = match($v['status']) {'Done'=>'sb-done','Overdue'=>'sb-overdue','Scheduled'=>'sb-scheduled',default=>''};
                ?>
                <tr>
                    <td><?= htmlspecialchars($v['vaccine_name']) ?></td>
                    <td><?= $v['administered_date'] ?></td>
                    <td><?= $v['next_due_date'] ?? '—' ?></td>
                    <td><span class="status-badge <?= $sCls ?>"><?= $v['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Breeding Tab -->
        <div id="tab-breed" class="tab-pane">
            <?php if (!$breedRec): ?>
                <p class="text-center text-muted py-2" style="font-size:.84rem">No breeding records on file.</p>
            <?php else:
                $pCls = match($breedRec['pregnancy_status']) {'Confirmed'=>'sb-confirmed','Delivered'=>'sb-delivered','Failed'=>'sb-sick',default=>'sb-treated'};
            ?>
            <table class="mini-table">
                <thead><tr><th>Field</th><th>Value</th></tr></thead>
                <tbody>
                    <tr><td>Breeding Date</td><td><?= $breedRec['breeding_date'] ?></td></tr>
                    <tr><td>Method</td><td><?= $breedRec['method'] ?></td></tr>
                    <tr><td>Sire</td><td><?= htmlspecialchars($breedRec['sire_name'] ?? '—') ?></td></tr>
                    <tr><td>Expected Calving</td><td><?= $breedRec['expected_calving'] ?? '—' ?></td></tr>
                    <tr><td>Pregnancy</td><td><span class="status-badge <?= $pCls ?>"><?= $breedRec['pregnancy_status'] ?></span></td></tr>
                    <?php if ($breedRec['pregnancy_check_date']): ?>
                    <tr><td>Last Check</td><td><?= $breedRec['pregnancy_check_date'] ?></td></tr>
                    <?php endif; ?>
                    <?php if ($breedRec['notes']): ?>
                    <tr><td>Notes</td><td><?= htmlspecialchars($breedRec['notes']) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Calving Tab -->
        <?php if (!empty($calvingRecs)): ?>
        <div id="tab-calv" class="tab-pane">
            <table class="mini-table">
                <thead><tr><th>Date</th><th>Calf Tag</th><th>Sex</th><th>Health</th></tr></thead>
                <tbody>
                <?php foreach ($calvingRecs as $c):
                    $cCls = match($c['calf_health']) {'Healthy'=>'sb-done','Stillborn'=>'sb-sick',default=>'sb-treated'};
                ?>
                <tr>
                    <td><?= $c['calving_date'] ?></td>
                    <td><?= htmlspecialchars($c['calf_tag'] ?? '—') ?></td>
                    <td><?= $c['calf_sex'] ?></td>
                    <td><span class="status-badge <?= $cCls ?>"><?= $c['calf_health'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===== FOOTER ===== -->
    <div class="page-footer">
        <strong>🐃 DairyBox</strong> Production & Herd Health System<br>
        Dairy Box Surallah, D.A Compound Surallah, So.Cot<br>
        Scanned: <?= date('F d, Y h:i A') ?>
    </div>
</div>

<!-- Chart.js -->
<script>
<?php if (!empty($chartData)): ?>
new Chart(document.getElementById('milkChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets:[{
            label: 'Liters',
            data: <?= json_encode($chartData) ?>,
            backgroundColor: 'rgba(40,167,69,.75)',
            borderRadius: 4,
            borderColor: '#1a6b3c',
            borderWidth: 1
        }]
    },
    options:{
        responsive: true,
        maintainAspectRatio: false,
        plugins:{ legend:{ display:false } },
        scales:{ y:{ beginAtZero:true, ticks:{ font:{ size:10 } } }, x:{ ticks:{ font:{ size:10 } } } }
    }
});
<?php endif; ?>

function showTab(name, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-nav button').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}
</script>
</body>
</html>
