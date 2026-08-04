<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$root      = '../';
$pageTitle = 'Herd Health Risk Index';
$db        = getDB();
$user      = currentUser();

// ════════════════════════════════════════════════════════
// DATA COLLECTION
// ════════════════════════════════════════════════════════

$totalActive  = (int)$db->query("SELECT COUNT(*) FROM buffaloes WHERE status='Active'")->fetchColumn();
if ($totalActive === 0) $totalActive = 1; // prevent division by zero

// 1. Sick / Under Treatment
$sickCount    = (int)$db->query("SELECT COUNT(*) FROM buffaloes WHERE health_status IN ('Sick','Under Treatment') AND status='Active'")->fetchColumn();

// 2. Overdue vaccinations
$overdueVacc  = (int)$db->query("SELECT COUNT(DISTINCT buffalo_id) FROM vaccinations WHERE status='Overdue'")->fetchColumn();

// 3. Active unresolved health conditions > 7 days
$prolongedSick= (int)$db->query("
    SELECT COUNT(DISTINCT buffalo_id) FROM health_records
    WHERE status='Active'
    AND condition_type IN ('Illness','Injury','Disease Alert')
    AND record_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
")->fetchColumn();

// 4. Never vaccinated
$neverVacc    = (int)$db->query("
    SELECT COUNT(*) FROM buffaloes b
    WHERE b.status='Active'
    AND NOT EXISTS (SELECT 1 FROM vaccinations v WHERE v.buffalo_id=b.id)
")->fetchColumn();

// 5. Production drop (>20% below 7-day avg) – indicates subclinical illness
$prodDropCount = 0;
$bufList = $db->query("SELECT id FROM buffaloes WHERE status='Active' AND sex='Female'")->fetchAll(PDO::FETCH_COLUMN);
foreach ($bufList as $bid) {
    $s = $db->prepare("SELECT AVG(quantity_liters) FROM milk_production WHERE buffalo_id=? AND record_date BETWEEN DATE_SUB(CURDATE(),INTERVAL 7 DAY) AND DATE_SUB(CURDATE(),INTERVAL 1 DAY)");
    $s->execute([$bid]); $avg7 = (float)$s->fetchColumn();

    $s2 = $db->prepare("SELECT COALESCE(SUM(quantity_liters),0) FROM milk_production WHERE buffalo_id=? AND record_date=CURDATE()");
    $s2->execute([$bid]); $today = (float)$s2->fetchColumn();

    if ($avg7 > 0 && $today > 0 && (($avg7 - $today) / $avg7) >= 0.20) $prodDropCount++;
}

// 6. Upcoming calvings in next 7 days (high-risk period)
$urgentCalving = (int)$db->query("
    SELECT COUNT(*) FROM breeding_records
    WHERE pregnancy_status='Confirmed'
    AND expected_calving BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
")->fetchColumn();

// 7. Vaccination coverage rate
$vaccCoverageStmt = $db->query("SELECT COUNT(DISTINCT buffalo_id) FROM vaccinations WHERE status='Done' AND administered_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)");
$vaccCovered  = (int)$vaccCoverageStmt->fetchColumn();
$vaccCoverage = round(($vaccCovered / $totalActive) * 100);

// 8. Herd mortality this year
$deathCount   = (int)$db->query("SELECT COUNT(*) FROM buffaloes WHERE status='Dead' AND YEAR(updated_at)=YEAR(CURDATE())")->fetchColumn();

// ════════════════════════════════════════════════════════
// RISK INDEX CALCULATION
// ════════════════════════════════════════════════════════
/*
  Scoring system (lower = better):
  Each risk factor adds points to the index (0–100 scale).
  Final score: 0 = No Risk | 100 = Critical Risk
*/

$riskPoints = 0;
$riskFactors = [];

// Factor 1: Disease prevalence (40 pts max)
$diseasePct = ($sickCount / $totalActive) * 100;
$f1Points   = min(40, round($diseasePct * 1.5));
$riskPoints += $f1Points;
$riskFactors[] = [
    'name'    => 'Disease Prevalence',
    'icon'    => 'fa-heartbeat',
    'value'   => "{$sickCount} sick / {$totalActive} total ({$diseasePct}%)",
    'points'  => $f1Points,
    'max'     => 40,
    'color'   => $f1Points > 20 ? '#dc3545' : ($f1Points > 10 ? '#ffc107' : '#28a745'),
    'tip'     => 'Percentage of herd currently sick or under treatment',
];

// Factor 2: Vaccination gaps (20 pts max)
$unvaccPct  = ($overdueVacc / $totalActive) * 100;
$f2Points   = min(20, round($unvaccPct));
$riskPoints += $f2Points;
$riskFactors[] = [
    'name'   => 'Vaccination Gaps',
    'icon'   => 'fa-syringe',
    'value'  => "{$overdueVacc} with overdue vaccines",
    'points' => $f2Points,
    'max'    => 20,
    'color'  => $f2Points > 10 ? '#dc3545' : ($f2Points > 5 ? '#ffc107' : '#28a745'),
    'tip'    => 'Animals with overdue or missing vaccinations',
];

// Factor 3: Prolonged illness (15 pts max)
$f3Points   = min(15, $prolongedSick * 5);
$riskPoints += $f3Points;
$riskFactors[] = [
    'name'   => 'Prolonged Illness',
    'icon'   => 'fa-bed',
    'value'  => "{$prolongedSick} unresolved >7 days",
    'points' => $f3Points,
    'max'    => 15,
    'color'  => $f3Points > 8 ? '#dc3545' : ($f3Points > 4 ? '#ffc107' : '#28a745'),
    'tip'    => 'Animals with health conditions unresolved for more than 7 days',
];

// Factor 4: Production drops (15 pts max)
$f4Points   = min(15, $prodDropCount * 3);
$riskPoints += $f4Points;
$riskFactors[] = [
    'name'   => 'Production Drop Alerts',
    'icon'   => 'fa-chart-line',
    'value'  => "{$prodDropCount} animals with ≥20% drop",
    'points' => $f4Points,
    'max'    => 15,
    'color'  => $f4Points > 8 ? '#dc3545' : ($f4Points > 4 ? '#ffc107' : '#28a745'),
    'tip'    => 'Animals showing significant production decline (possible subclinical illness)',
];

// Factor 5: Mortality (10 pts max)
$f5Points   = min(10, $deathCount * 5);
$riskPoints += $f5Points;
$riskFactors[] = [
    'name'   => 'Herd Mortality',
    'icon'   => 'fa-skull',
    'value'  => "{$deathCount} death(s) this year",
    'points' => $f5Points,
    'max'    => 10,
    'color'  => $f5Points > 5 ? '#dc3545' : ($f5Points > 0 ? '#ffc107' : '#28a745'),
    'tip'    => 'Number of animals lost this year',
];

$riskIndex = min(100, $riskPoints);

// ── Risk Level Classification ──
if ($riskIndex <= 20) {
    $riskLevel = 'Low';
    $riskColor = '#28a745';
    $riskBg    = '#d4edda';
    $riskEmoji = '✅';
    $riskMsg   = 'Herd is in good health. Continue current management practices and maintain regular monitoring.';
} elseif ($riskIndex <= 45) {
    $riskLevel = 'Moderate';
    $riskColor = '#ffc107';
    $riskBg    = '#fff3cd';
    $riskEmoji = '⚠️';
    $riskMsg   = 'Some health concerns detected. Investigate flagged risk factors and take preventive action to avoid escalation.';
} elseif ($riskIndex <= 70) {
    $riskLevel = 'High';
    $riskColor = '#fd7e14';
    $riskBg    = '#fce8d5';
    $riskEmoji = '🔶';
    $riskMsg   = 'Significant health risks present. Immediate veterinary review recommended. Prioritize vaccination, treatment, and isolation of sick animals.';
} else {
    $riskLevel = 'Critical';
    $riskColor = '#dc3545';
    $riskBg    = '#f8d7da';
    $riskEmoji = '🚨';
    $riskMsg   = 'Critical herd health status. Urgent veterinary intervention required. Review biosecurity protocols immediately.';
}

// ── Per-animal risk scores ────────────────────────────────
$animalRisks = $db->query("
    SELECT b.id, b.tag_number, b.name, b.health_status,
           (SELECT COUNT(*) FROM vaccinations v WHERE v.buffalo_id=b.id AND v.status='Overdue') as overdue_vacc,
           (SELECT COUNT(*) FROM health_records hr WHERE hr.buffalo_id=b.id AND hr.status='Active' AND hr.condition_type IN ('Illness','Injury','Disease Alert')) as active_conditions,
           (SELECT MAX(mp.record_date) FROM milk_production mp WHERE mp.buffalo_id=b.id) as last_milk_record
    FROM buffaloes b WHERE b.status='Active'
    ORDER BY b.health_status DESC, overdue_vacc DESC
")->fetchAll();

// Historical context: last 6 months health events per month
$monthlyEvents = $db->query("
    SELECT MONTH(record_date) as mo, MONTHNAME(record_date) as mon_name,
           COUNT(*) as events,
           SUM(CASE WHEN condition_type IN ('Illness','Disease Alert') THEN 1 ELSE 0 END) as illnesses
    FROM health_records
    WHERE record_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY mo, mon_name ORDER BY mo ASC
")->fetchAll();
$chartLabels = array_column($monthlyEvents,'mon_name');
$chartEvents = array_map('intval', array_column($monthlyEvents,'events'));
$chartIll    = array_map('intval', array_column($monthlyEvents,'illnesses'));

include '../includes/header.php';
?>

<!-- ═══════════════ RISK INDEX SCORE CARD ═══════════════ -->
<div class="row g-3 mb-4 align-items-stretch">

    <!-- Main Score -->
    <div class="col-md-4">
        <div class="card-section text-center h-100 d-flex flex-column justify-content-center"
             style="background:<?= $riskBg ?>;border:2px solid <?= $riskColor ?>">
            <div style="font-size:3rem"><?= $riskEmoji ?></div>
            <h3 class="fw-bold mt-1" style="color:<?= $riskColor ?>"><?= $riskLevel ?> Risk</h3>
            <!-- Gauge -->
            <div class="position-relative mx-auto my-2" style="width:140px;height:70px;overflow:hidden">
                <div style="width:140px;height:140px;border-radius:50%;background:conic-gradient(
                    <?= $riskColor ?> 0% <?= $riskIndex/2 ?>%,
                    #e9ecef <?= $riskIndex/2 ?>% 50%,
                    transparent 50%);position:absolute;top:0;left:0"></div>
                <div style="width:100px;height:100px;background:#fff;border-radius:50%;position:absolute;top:20px;left:20px"></div>
                <div style="position:absolute;bottom:0;width:100%;text-align:center;font-size:1.5rem;font-weight:800;color:<?= $riskColor ?>"><?= $riskIndex ?></div>
            </div>
            <div style="font-size:.8rem;color:#555">Risk Index Score (0–100)</div>
            <p class="mt-2 mb-0 small text-muted px-2"><?= $riskMsg ?></p>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="col-md-4">
        <div class="card-section h-100">
            <div class="section-title"><i class="fa fa-chart-bar me-2"></i>Herd Health Metrics</div>
            <div class="row g-2 text-center">
                <div class="col-6">
                    <div class="border rounded p-2">
                        <div class="fw-bold text-danger" style="font-size:1.4rem"><?= $sickCount ?></div>
                        <div style="font-size:.72rem;color:#888">Sick/In Treatment</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="border rounded p-2">
                        <div class="fw-bold text-warning" style="font-size:1.4rem"><?= $overdueVacc ?></div>
                        <div style="font-size:.72rem;color:#888">Overdue Vaccines</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="border rounded p-2">
                        <div class="fw-bold text-success" style="font-size:1.4rem"><?= $vaccCoverage ?>%</div>
                        <div style="font-size:.72rem;color:#888">Vaccination Coverage</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="border rounded p-2">
                        <div class="fw-bold text-info" style="font-size:1.4rem"><?= $totalActive ?></div>
                        <div style="font-size:.72rem;color:#888">Active Animals</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="border rounded p-2">
                        <div class="fw-bold" style="font-size:1.4rem;color:<?= $prodDropCount > 0 ? '#dc3545' : '#28a745' ?>"><?= $prodDropCount ?></div>
                        <div style="font-size:.72rem;color:#888">Prod. Drop Alerts</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="border rounded p-2">
                        <div class="fw-bold" style="font-size:1.4rem;color:<?= $urgentCalving > 0 ? '#ffc107' : '#28a745' ?>"><?= $urgentCalving ?></div>
                        <div style="font-size:.72rem;color:#888">Calving in 7 Days</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Risk Breakdown -->
    <div class="col-md-4">
        <div class="card-section h-100">
            <div class="section-title"><i class="fa fa-tachometer-alt me-2"></i>Risk Factor Breakdown</div>
            <?php foreach ($riskFactors as $f): ?>
            <div class="mb-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span style="font-size:.8rem"><i class="fa <?= $f['icon'] ?> me-1" style="color:<?= $f['color'] ?>"></i><?= $f['name'] ?></span>
                    <span style="font-size:.78rem;font-weight:600;color:<?= $f['color'] ?>"><?= $f['points'] ?>/<?= $f['max'] ?></span>
                </div>
                <div class="progress" style="height:8px">
                    <div class="progress-bar" style="width:<?= $f['max']>0?round($f['points']/$f['max']*100):0 ?>%;background:<?= $f['color'] ?>"></div>
                </div>
                <div style="font-size:.7rem;color:#888"><?= $f['value'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ═══════════════ HEALTH TREND CHART ═══════════════ -->
<?php if (!empty($monthlyEvents)): ?>
<div class="card-section mb-3">
    <div class="section-title"><i class="fa fa-chart-area me-2"></i>Health Events Trend – Last 6 Months</div>
    <canvas id="trendChart" height="80"></canvas>
</div>
<?php endif; ?>

<!-- ═══════════════ MANAGEMENT RECOMMENDATIONS ═══════════════ -->
<div class="card-section mb-3">
    <div class="section-title"><i class="fa fa-clipboard-list me-2"></i>Management Recommendations</div>

    <?php if ($riskIndex > 70): ?>
    <div class="alert alert-danger"><i class="fa fa-ambulance me-2"></i><strong>URGENT:</strong> Critical health risk detected. Contact your veterinarian immediately. Isolate all sick animals and review biosecurity measures.</div>
    <?php elseif ($riskIndex > 45): ?>
    <div class="alert alert-warning"><i class="fa fa-exclamation-triangle me-2"></i><strong>HIGH RISK:</strong> Multiple health concerns require attention. Schedule veterinary visit within 48 hours.</div>
    <?php endif; ?>

    <div class="row g-3">
        <?php if ($sickCount > 0): ?>
        <div class="col-md-6">
            <div class="p-3 rounded border-start border-danger border-3 bg-light">
                <strong class="text-danger"><i class="fa fa-heartbeat me-1"></i>Treat Sick Animals</strong>
                <p class="small mb-1 mt-1"><?= $sickCount ?> animal(s) currently sick or under treatment. Ensure daily monitoring, complete treatment courses, and prevent contact with healthy animals.</p>
                <a href="health_records.php" class="btn btn-sm btn-outline-danger">View Health Records</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($overdueVacc > 0): ?>
        <div class="col-md-6">
            <div class="p-3 rounded border-start border-warning border-3 bg-light">
                <strong class="text-warning"><i class="fa fa-syringe me-1"></i>Schedule Overdue Vaccines</strong>
                <p class="small mb-1 mt-1"><?= $overdueVacc ?> animal(s) have overdue vaccinations. Coordinate with your veterinarian to administer vaccines immediately.</p>
                <a href="vaccinations.php?sf=Overdue" class="btn btn-sm btn-outline-warning">View Overdue</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($prodDropCount > 0): ?>
        <div class="col-md-6">
            <div class="p-3 rounded border-start border-info border-3 bg-light">
                <strong class="text-info"><i class="fa fa-tint me-1"></i>Investigate Production Drops</strong>
                <p class="small mb-1 mt-1"><?= $prodDropCount ?> animal(s) showing significant milk decline. This may indicate subclinical disease, stress, or nutritional deficiency.</p>
                <a href="early_detection.php" class="btn btn-sm btn-outline-info">View Alerts</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($urgentCalving > 0): ?>
        <div class="col-md-6">
            <div class="p-3 rounded border-start border-success border-3 bg-light">
                <strong class="text-success"><i class="fa fa-baby me-1"></i>Prepare for Imminent Calvings</strong>
                <p class="small mb-1 mt-1"><?= $urgentCalving ?> animal(s) expected to calve within 7 days. Ensure calving pen is ready, colostrum protocol is in place, and veterinarian is on call.</p>
                <a href="breeding.php?tab=calving" class="btn btn-sm btn-outline-success">View Calvings</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($vaccCoverage < 80): ?>
        <div class="col-md-6">
            <div class="p-3 rounded border-start border-secondary border-3 bg-light">
                <strong><i class="fa fa-shield-alt me-1"></i>Improve Vaccination Coverage</strong>
                <p class="small mb-1 mt-1">Only <?= $vaccCoverage ?>% of the herd has been vaccinated in the past 12 months. Target ≥80% coverage for effective herd immunity.</p>
                <a href="vaccinations.php?action=add" class="btn btn-sm btn-outline-secondary">Record Vaccination</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($riskIndex <= 20): ?>
        <div class="col-12">
            <div class="alert alert-success mb-0">
                <i class="fa fa-check-circle me-2"></i><strong>Excellent Herd Health!</strong>
                All major health indicators are within acceptable ranges. Continue your current management practices and maintain regular health monitoring schedules.
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════════ PER-ANIMAL RISK TABLE ═══════════════ -->
<div class="card-section">
    <div class="section-title"><i class="fa fa-list me-2"></i>Individual Animal Risk Summary</div>

    <!-- Desktop -->
    <div class="d-none d-md-block table-responsive">
    <table class="table table-sm table-hover mb-0">
        <thead>
            <tr><th>Tag</th><th>Name</th><th>Health Status</th><th>Overdue Vaccines</th><th>Active Conditions</th><th>Last Milk Record</th><th>Risk Level</th></tr>
        </thead>
        <tbody>
        <?php foreach ($animalRisks as $a):
            // Per-animal risk score
            $aRisk = 0;
            if (in_array($a['health_status'], ['Sick','Under Treatment'])) $aRisk += 40;
            $aRisk += min(30, (int)$a['overdue_vacc'] * 15);
            $aRisk += min(20, (int)$a['active_conditions'] * 10);
            if (!$a['last_milk_record'] || strtotime($a['last_milk_record']) < strtotime('-7 days')) $aRisk += 10;
            $aRisk = min(100, $aRisk);

            $aLevel = $aRisk <= 20 ? ['Low','success'] : ($aRisk <= 50 ? ['Moderate','warning'] : ($aRisk <= 75 ? ['High','danger'] : ['Critical','danger']));
            $hCls   = match($a['health_status']) {'Healthy'=>'badge-healthy','Sick'=>'badge-sick','Under Treatment'=>'badge-treated',default=>''};
        ?>
        <tr>
            <td><span class="badge bg-success"><?= htmlspecialchars($a['tag_number']) ?></span></td>
            <td><?= htmlspecialchars($a['name']??'-') ?></td>
            <td><span class="badge-custom <?= $hCls ?>"><?= $a['health_status'] ?></span></td>
            <td><?= $a['overdue_vacc'] > 0 ? '<span class="text-danger fw-bold">'.$a['overdue_vacc'].'</span>' : '<span class="text-success">0</span>' ?></td>
            <td><?= $a['active_conditions'] > 0 ? '<span class="text-danger fw-bold">'.$a['active_conditions'].'</span>' : '<span class="text-success">0</span>' ?></td>
            <td><?= $a['last_milk_record'] ? $a['last_milk_record'] : '<span class="text-muted">No record</span>' ?></td>
            <td>
                <span class="badge bg-<?= $aLevel[1] ?>"><?= $aLevel[0] ?></span>
                <small class="text-muted ms-1"><?= $aRisk ?>/100</small>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- Mobile Cards -->
    <div class="d-md-none">
        <?php foreach ($animalRisks as $a):
            $aRisk = 0;
            if (in_array($a['health_status'], ['Sick','Under Treatment'])) $aRisk += 40;
            $aRisk += min(30, (int)$a['overdue_vacc'] * 15);
            $aRisk += min(20, (int)$a['active_conditions'] * 10);
            $aRisk = min(100, $aRisk);
            $aLevel = $aRisk <= 20 ? ['Low','success'] : ($aRisk <= 50 ? ['Moderate','warning'] : ['High','danger']);
        ?>
        <div class="d-flex align-items-center gap-2 p-2 mb-2 rounded border">
            <div class="flex-grow-1">
                <span class="badge bg-success"><?= htmlspecialchars($a['tag_number']) ?></span>
                <strong class="ms-1"><?= htmlspecialchars($a['name']??'') ?></strong>
                <span class="badge bg-<?= $aLevel[1] ?> ms-1"><?= $aLevel[0] ?></span>
                <div style="font-size:.75rem;color:#888;margin-top:.2rem">
                    <?= $a['health_status'] ?>
                    <?= $a['overdue_vacc']>0 ? ' | <span class="text-danger">'.$a['overdue_vacc'].' overdue vacc</span>' : '' ?>
                    <?= $a['active_conditions']>0 ? ' | <span class="text-danger">'.$a['active_conditions'].' conditions</span>' : '' ?>
                </div>
            </div>
            <div class="text-end">
                <strong style="font-size:1.1rem;color:<?= $aLevel[1]==='success'?'#28a745':($aLevel[1]==='warning'?'#856404':'#dc3545') ?>"><?= $aRisk ?></strong>
                <div style="font-size:.65rem;color:#888">/ 100</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!empty($monthlyEvents)): ?>
<script>
new Chart(document.getElementById('trendChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [
            {
                label: 'Total Health Events',
                data: <?= json_encode($chartEvents) ?>,
                backgroundColor: 'rgba(23,162,184,.6)',
                borderRadius: 4,
                order: 2,
            },
            {
                label: 'Illnesses / Disease Alerts',
                data: <?= json_encode($chartIll) ?>,
                backgroundColor: 'rgba(220,53,69,.7)',
                borderRadius: 4,
                order: 1,
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
