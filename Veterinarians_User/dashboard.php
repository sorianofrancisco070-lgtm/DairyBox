<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/session.php';
require_once '../config/database.php';
requireLogin('veterinarian');

$root      = '../';
$pageTitle = 'Veterinarian Dashboard';
$db        = getDB();
$user      = currentUser();

$sickCount    = $db->query("SELECT COUNT(*) FROM buffaloes WHERE health_status IN ('Sick','Under Treatment') AND status='Active'")->fetchColumn();
$overdueVacc  = $db->query("SELECT COUNT(*) FROM vaccinations WHERE status='Overdue'")->fetchColumn();
$upcomingVacc = $db->query("SELECT COUNT(*) FROM vaccinations WHERE status='Scheduled' AND next_due_date <= DATE_ADD(CURDATE(),INTERVAL 14 DAY)")->fetchColumn();
$pregnantCount= $db->query("SELECT COUNT(*) FROM breeding_records WHERE pregnancy_status='Confirmed'")->fetchColumn();

// Sick / under treatment animals
$sickList = $db->query("
    SELECT b.*, hr.diagnosis, hr.treatment, hr.record_date
    FROM buffaloes b
    LEFT JOIN (SELECT buffalo_id, diagnosis, treatment, record_date FROM health_records ORDER BY record_date DESC LIMIT 50) hr ON hr.buffalo_id=b.id
    WHERE b.health_status IN ('Sick','Under Treatment') AND b.status='Active'
    LIMIT 10
")->fetchAll();

// Upcoming vaccinations
$upcomingV = $db->query("
    SELECT v.*, b.tag_number, b.name
    FROM vaccinations v JOIN buffaloes b ON b.id=v.buffalo_id
    WHERE v.next_due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY v.next_due_date ASC LIMIT 8
")->fetchAll();

// Health status pie
$healthStat = $db->query("
    SELECT health_status, COUNT(*) as cnt
    FROM buffaloes WHERE status='Active'
    GROUP BY health_status
")->fetchAll();
$hLabels = array_column($healthStat,'health_status');
$hData   = array_map('intval', array_column($healthStat,'cnt'));

include '../includes/header.php';
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f8d7da"><i class="fa fa-heartbeat text-danger" style="font-size:1.5rem"></i></div>
            <div><p class="stat-label">Sick / In Treatment</p><p class="stat-value text-danger"><?= $sickCount ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff3cd"><i class="fa fa-syringe text-warning" style="font-size:1.5rem"></i></div>
            <div><p class="stat-label">Overdue Vaccines</p><p class="stat-value text-warning"><?= $overdueVacc ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#cce5ff"><i class="fa fa-calendar-check text-info" style="font-size:1.5rem"></i></div>
            <div><p class="stat-label">Vaccines Due (14d)</p><p class="stat-value text-info"><?= $upcomingVacc ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#d1ecf1"><i class="fa fa-venus text-info" style="font-size:1.5rem"></i></div>
            <div><p class="stat-label">Pregnant</p><p class="stat-value text-info"><?= $pregnantCount ?></p></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Sick Animals -->
    <div class="col-lg-8">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-stethoscope me-2"></i>Animals Requiring Attention
                <a href="../modules/health_records.php?action=add" class="btn btn-sm btn-danger float-end no-print">+ Log Health Event</a>
            </div>
            <?php if (empty($sickList)): ?>
                <p class="text-success"><i class="fa fa-check me-1"></i>All animals are healthy!</p>
            <?php else: ?>
            <table class="table table-sm table-hover">
                <thead><tr><th>Tag</th><th>Name</th><th>Status</th><th>Diagnosis</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($sickList as $s): ?>
                <tr>
                    <td><span class="badge bg-success"><?= htmlspecialchars($s['tag_number']) ?></span></td>
                    <td><?= htmlspecialchars($s['name'] ?? '-') ?></td>
                    <td><span class="badge-custom badge-sick"><?= $s['health_status'] ?></span></td>
                    <td><?= htmlspecialchars($s['diagnosis'] ?? 'Pending') ?></td>
                    <td><a href="../modules/health_records.php?buffalo_id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger">View</a></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <!-- Upcoming Vaccinations -->
        <div class="card-section mt-3">
            <div class="section-title"><i class="fa fa-syringe me-2"></i>Upcoming Vaccinations (30 Days)</div>
            <table class="table table-sm table-hover">
                <thead><tr><th>Tag</th><th>Name</th><th>Vaccine</th><th>Due Date</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($upcomingV as $v): 
                    $overdue = strtotime($v['next_due_date']) < time();
                ?>
                <tr class="<?= $overdue ? 'table-danger' : '' ?>">
                    <td><span class="badge bg-success"><?= htmlspecialchars($v['tag_number']) ?></span></td>
                    <td><?= htmlspecialchars($v['name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($v['vaccine_name']) ?></td>
                    <td><?= $v['next_due_date'] ?></td>
                    <td><span class="badge-custom <?= $overdue ? 'badge-sick' : 'badge-treated' ?>"><?= $v['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Health Pie -->
    <div class="col-lg-4">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-chart-pie me-2"></i>Herd Health Status</div>
            <canvas id="healthPie"></canvas>
        </div>
        <div class="card-section mt-3">
            <div class="section-title"><i class="fa fa-bolt me-2"></i>Quick Actions</div>
            <div class="d-grid gap-2">
                <a href="../modules/health_records.php?action=add" class="btn btn-danger"><i class="fa fa-plus me-2"></i>New Health Record</a>
                <a href="../modules/vaccinations.php?action=add" class="btn btn-outline-warning"><i class="fa fa-syringe me-2"></i>Record Vaccination</a>
                <a href="../modules/early_detection.php" class="btn btn-outline-danger"><i class="fa fa-exclamation-triangle me-2"></i>Early Detection</a>
            </div>
        </div>
    </div>
</div>

<script>
new Chart(document.getElementById('healthPie'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($hLabels) ?>,
        datasets: [{ data: <?= json_encode($hData) ?>, backgroundColor: ['#28a745','#dc3545','#ffc107','#17a2b8'] }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php include '../includes/footer.php'; ?>
