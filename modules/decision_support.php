<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$root      = '../';
$pageTitle = 'Decision Support';
$db        = getDB();
$user      = currentUser();

// ---- Gather data for recommendations ----

// Low producers (< 5 L/day avg this month)
$lowProd = $db->query("
    SELECT b.id, b.tag_number, b.name, b.breed, b.date_of_birth,
           AVG(mp.quantity_liters) as avg_session,
           SUM(mp.quantity_liters) as month_total
    FROM buffaloes b
    LEFT JOIN milk_production mp ON mp.buffalo_id=b.id
        AND MONTH(mp.record_date)=MONTH(CURDATE())
        AND YEAR(mp.record_date)=YEAR(CURDATE())
    WHERE b.status='Active' AND b.sex='Female'
    GROUP BY b.id
    HAVING avg_session < 5 OR avg_session IS NULL
    ORDER BY avg_session ASC
")->fetchAll();

// High producers (> 8 L/day avg)
$highProd = $db->query("
    SELECT b.tag_number, b.name, AVG(mp.quantity_liters) as avg_session
    FROM buffaloes b JOIN milk_production mp ON mp.buffalo_id=b.id
    WHERE b.status='Active' AND MONTH(mp.record_date)=MONTH(CURDATE())
    GROUP BY b.id HAVING avg_session >= 8 ORDER BY avg_session DESC
")->fetchAll();

// Females not bred in 12 months
$notBred = $db->query("
    SELECT b.id, b.tag_number, b.name, b.date_of_birth,
           MAX(br.breeding_date) as last_bred
    FROM buffaloes b
    LEFT JOIN breeding_records br ON br.buffalo_id=b.id
    WHERE b.status='Active' AND b.sex='Female'
    GROUP BY b.id
    HAVING last_bred IS NULL OR last_bred < DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
")->fetchAll();

// Trend analysis: is farm production increasing or decreasing?
$trend = $db->query("
    SELECT MONTH(record_date) as mo, SUM(quantity_liters) as total
    FROM milk_production WHERE YEAR(record_date)=YEAR(CURDATE())
    GROUP BY mo ORDER BY mo DESC LIMIT 3
")->fetchAll();
$trendDir = 'stable';
if (count($trend) >= 2) {
    if ($trend[0]['total'] > $trend[1]['total'] * 1.05) $trendDir = 'increasing';
    elseif ($trend[0]['total'] < $trend[1]['total'] * 0.95) $trendDir = 'decreasing';
}

// Upcoming events summary
$pendingVacc  = $db->query("SELECT COUNT(*) FROM vaccinations WHERE status IN ('Overdue','Scheduled') AND (next_due_date IS NULL OR next_due_date <= DATE_ADD(CURDATE(),INTERVAL 30 DAY))")->fetchColumn();
$confirmedPreg= $db->query("SELECT COUNT(*) FROM breeding_records WHERE pregnancy_status='Confirmed'")->fetchColumn();
$sickCount    = $db->query("SELECT COUNT(*) FROM buffaloes WHERE health_status IN ('Sick','Under Treatment') AND status='Active'")->fetchColumn();

include '../includes/header.php';
?>

<!-- Score Card -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card-section text-center" style="background:<?= $trendDir==='increasing'?'#d4edda':($trendDir==='decreasing'?'#f8d7da':'#fff3cd') ?>">
            <div style="font-size:2.5rem"><?= $trendDir==='increasing'?'📈':($trendDir==='decreasing'?'📉':'➡️') ?></div>
            <h5 class="fw-bold mt-2">Production is <?= strtoupper($trendDir) ?></h5>
            <p class="text-muted small mb-0">Based on last 3 months</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-section text-center">
            <div style="font-size:2.5rem">🐃</div>
            <h5 class="fw-bold mt-2">Farm Health Score</h5>
            <?php
            $score = 100;
            if ($sickCount > 0)    $score -= min($sickCount * 10, 30);
            if ($pendingVacc > 2)  $score -= 10;
            if (count($lowProd) > 0) $score -= min(count($lowProd)*5, 20);
            $score = max($score, 0);
            $scoreCls = $score >= 80 ? 'success' : ($score >= 60 ? 'warning' : 'danger');
            ?>
            <div class="progress mb-2" style="height:18px">
                <div class="progress-bar bg-<?= $scoreCls ?>" style="width:<?= $score ?>%"><?= $score ?>%</div>
            </div>
            <p class="text-muted small mb-0">Overall farm performance</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-tasks me-2"></i>Action Summary</div>
            <ul class="list-unstyled mb-0 small">
                <li class="mb-1"><span class="badge bg-danger me-1"><?= $sickCount ?></span>Animals needing health attention</li>
                <li class="mb-1"><span class="badge bg-warning text-dark me-1"><?= $pendingVacc ?></span>Vaccinations due/overdue</li>
                <li class="mb-1"><span class="badge bg-info me-1"><?= $confirmedPreg ?></span>Confirmed pregnancies to monitor</li>
                <li><span class="badge bg-secondary me-1"><?= count($notBred) ?></span>Females not recently bred</li>
            </ul>
        </div>
    </div>
</div>

<!-- Recommendations -->
<div class="card-section mb-3">
    <div class="section-title"><i class="fa fa-brain me-2"></i>Data-Driven Recommendations</div>

    <?php if ($trendDir === 'decreasing'): ?>
    <div class="alert alert-danger"><i class="fa fa-chart-line me-2"></i><strong>Production Declining:</strong> Herd milk production has been declining over the past months. Consider reviewing feeding regimes, checking for subclinical illnesses, and evaluating breeding cycles.</div>
    <?php elseif ($trendDir === 'increasing'): ?>
    <div class="alert alert-success"><i class="fa fa-thumbs-up me-2"></i><strong>Production Growing:</strong> Excellent! Production is trending upward. Continue current management practices and ensure vaccination schedules are maintained.</div>
    <?php else: ?>
    <div class="alert alert-info"><i class="fa fa-info-circle me-2"></i><strong>Production Stable:</strong> Production is relatively stable. Focus on identifying opportunities to improve feed efficiency and herd health to increase yields.</div>
    <?php endif; ?>

    <?php if ($sickCount > 0): ?>
    <div class="alert alert-warning"><i class="fa fa-heartbeat me-2"></i><strong>Health Alert:</strong> <?= $sickCount ?> buffalo(es) are sick or under treatment. Ensure follow-up appointments are scheduled and treatment protocols are being followed. Isolate sick animals to prevent disease spread.</div>
    <?php endif; ?>

    <?php if ($pendingVacc > 0): ?>
    <div class="alert alert-warning"><i class="fa fa-syringe me-2"></i><strong>Vaccination Due:</strong> <?= $pendingVacc ?> vaccination(s) are overdue or scheduled within 30 days. Delayed vaccination increases vulnerability to preventable diseases. Coordinate with your veterinarian immediately.</div>
    <?php endif; ?>

    <?php if (!empty($notBred)): ?>
    <div class="alert alert-info"><i class="fa fa-venus-mars me-2"></i><strong>Breeding Opportunity:</strong> <?= count($notBred) ?> female buffalo(es) have not been bred in the past 12 months. Review their reproductive health and consider scheduling breeding to maintain herd productivity.</div>
    <?php endif; ?>

    <?php if (empty($lowProd) && $trendDir !== 'decreasing' && $sickCount === 0): ?>
    <div class="alert alert-success"><i class="fa fa-star me-2"></i><strong>Great Status:</strong> Farm performance is looking good! All indicators are within healthy ranges.</div>
    <?php endif; ?>
</div>

<!-- Low Producers -->
<?php if (!empty($lowProd)): ?>
<div class="card-section mb-3">
    <div class="section-title"><i class="fa fa-arrow-down me-2 text-warning"></i>Low-Producing Buffaloes (< 5 L/session avg)</div>
    <p class="text-muted small">These animals may benefit from improved nutrition, health checks, or breeding assessment.</p>
    <table class="table table-sm table-hover">
        <thead><tr><th>Tag</th><th>Name</th><th>Breed</th><th>Avg/Session (L)</th><th>Month Total (L)</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($lowProd as $p): ?>
        <tr class="table-warning">
            <td><span class="badge bg-success"><?= htmlspecialchars($p['tag_number']) ?></span></td>
            <td><?= htmlspecialchars($p['name']??'-') ?></td>
            <td><?= htmlspecialchars($p['breed']??'-') ?></td>
            <td><?= $p['avg_session'] ? number_format($p['avg_session'],2) : 'No data' ?></td>
            <td><?= $p['month_total'] ? number_format($p['month_total'],2) : '0' ?></td>
            <td>
                <a href="health_records.php?action=add&buffalo_id=<?= $p['id'] ?>" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem">Health Check</a>
                <a href="breeding.php?tab=breeding&action=add" class="btn btn-xs btn-outline-info" style="padding:.2rem .5rem;font-size:.75rem">Check Breeding</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- High Producers -->
<?php if (!empty($highProd)): ?>
<div class="card-section mb-3">
    <div class="section-title"><i class="fa fa-trophy me-2 text-success"></i>Top Performers (≥ 8 L/session avg)</div>
    <p class="text-muted small">Maintain optimal care for these animals as they contribute most to overall production.</p>
    <table class="table table-sm">
        <thead><tr><th>Tag</th><th>Name</th><th>Avg/Session (L)</th><th>Recommendation</th></tr></thead>
        <tbody>
        <?php foreach ($highProd as $p): ?>
        <tr class="table-success">
            <td><span class="badge bg-success"><?= htmlspecialchars($p['tag_number']) ?></span></td>
            <td><?= htmlspecialchars($p['name']??'-') ?></td>
            <td><strong><?= number_format($p['avg_session'],2) ?></strong></td>
            <td><span class="text-success">✓ Maintain current feeding & health management</span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Not Bred -->
<?php if (!empty($notBred)): ?>
<div class="card-section">
    <div class="section-title"><i class="fa fa-venus-mars me-2 text-info"></i>Females Not Recently Bred</div>
    <table class="table table-sm">
        <thead><tr><th>Tag</th><th>Name</th><th>DOB</th><th>Last Bred</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($notBred as $b): ?>
        <tr>
            <td><span class="badge bg-success"><?= htmlspecialchars($b['tag_number']) ?></span></td>
            <td><?= htmlspecialchars($b['name']??'-') ?></td>
            <td><?= $b['date_of_birth']??'-' ?></td>
            <td><?= $b['last_bred'] ?? 'Never recorded' ?></td>
            <td><a href="breeding.php?tab=breeding&action=add" class="btn btn-sm btn-outline-info">Schedule Breeding</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
