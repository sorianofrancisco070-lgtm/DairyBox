<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin('farm_manager');

$root      = '../';
$pageTitle = 'Farm Manager Dashboard';
$db        = getDB();
$user      = currentUser();

// ---- Stats ----
$totalBuffaloes = $db->query("SELECT COUNT(*) FROM buffaloes WHERE status='Active'")->fetchColumn();
$healthyCount   = $db->query("SELECT COUNT(*) FROM buffaloes WHERE health_status='Healthy' AND status='Active'")->fetchColumn();
$sickCount      = $db->query("SELECT COUNT(*) FROM buffaloes WHERE health_status IN ('Sick','Under Treatment') AND status='Active'")->fetchColumn();

$todayProd      = $db->query("SELECT COALESCE(SUM(quantity_liters),0) FROM milk_production WHERE record_date=CURDATE()")->fetchColumn();
$monthProd      = $db->query("SELECT COALESCE(SUM(quantity_liters),0) FROM milk_production WHERE MONTH(record_date)=MONTH(CURDATE()) AND YEAR(record_date)=YEAR(CURDATE())")->fetchColumn();

$overdueVacc    = $db->query("SELECT COUNT(*) FROM vaccinations WHERE status='Overdue'")->fetchColumn();
$confirmedPreg  = $db->query("SELECT COUNT(*) FROM breeding_records WHERE pregnancy_status='Confirmed'")->fetchColumn();
$upcomingCalv   = $db->query("SELECT COUNT(*) FROM breeding_records WHERE expected_calving BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)")->fetchColumn();

// ---- Chart Data: Last 7 days production ----
$prodChart = $db->query("
    SELECT record_date, SUM(quantity_liters) as total
    FROM milk_production
    WHERE record_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY record_date ORDER BY record_date ASC
")->fetchAll();

$chartLabels = [];
$chartData   = [];
foreach ($prodChart as $row) {
    $chartLabels[] = date('M d', strtotime($row['record_date']));
    $chartData[]   = (float)$row['total'];
}

// ---- Top producers ----
$topProducers = $db->query("
    SELECT b.tag_number, b.name, SUM(mp.quantity_liters) as total
    FROM milk_production mp JOIN buffaloes b ON b.id=mp.buffalo_id
    WHERE mp.record_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY b.id ORDER BY total DESC LIMIT 5
")->fetchAll();

// ---- Recent notifications ----
$recentNotif = $db->query("
    SELECT * FROM notifications
    WHERE (target_role='farm_manager' OR target_role IS NULL) AND is_read=0
    ORDER BY priority DESC, created_at DESC LIMIT 6
")->fetchAll();

include '../includes/header.php';
?>

<!-- Stat Cards Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#d4edda"><span style="font-size:1.6rem">🐃</span></div>
            <div>
                <p class="stat-label">Active Buffaloes</p>
                <p class="stat-value text-success"><?= $totalBuffaloes ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#cce5ff"><i class="fa fa-tint text-primary" style="font-size:1.5rem"></i></div>
            <div>
                <p class="stat-label">Today's Milk (L)</p>
                <p class="stat-value text-primary"><?= number_format($todayProd,1) ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff3cd"><i class="fa fa-heartbeat text-warning" style="font-size:1.5rem"></i></div>
            <div>
                <p class="stat-label">Sick / In Treatment</p>
                <p class="stat-value text-warning"><?= $sickCount ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f8d7da"><i class="fa fa-syringe text-danger" style="font-size:1.5rem"></i></div>
            <div>
                <p class="stat-label">Overdue Vaccines</p>
                <p class="stat-value text-danger"><?= $overdueVacc ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e2e3f3"><i class="fa fa-chart-bar text-secondary" style="font-size:1.5rem"></i></div>
            <div>
                <p class="stat-label">This Month (L)</p>
                <p class="stat-value text-secondary"><?= number_format($monthProd,1) ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#d1ecf1"><i class="fa fa-venus text-info" style="font-size:1.5rem"></i></div>
            <div>
                <p class="stat-label">Pregnant</p>
                <p class="stat-value text-info"><?= $confirmedPreg ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fce8b2"><i class="fa fa-baby text-warning" style="font-size:1.5rem"></i></div>
            <div>
                <p class="stat-label">Calving in 30d</p>
                <p class="stat-value text-warning"><?= $upcomingCalv ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#d4edda"><i class="fa fa-check-circle text-success" style="font-size:1.5rem"></i></div>
            <div>
                <p class="stat-label">Healthy</p>
                <p class="stat-value text-success"><?= $healthyCount ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Production Chart -->
    <div class="col-lg-8">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-chart-line me-2"></i>Milk Production – Last 7 Days</div>
            <canvas id="prodChart" height="100"></canvas>
        </div>
    </div>
    <!-- Notifications -->
    <div class="col-lg-4">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-bell me-2"></i>Alerts & Reminders
                <a href="../modules/notifications.php" class="float-end small text-success">View All</a>
            </div>
            <?php if (empty($recentNotif)): ?>
                <p class="text-muted small">No new notifications.</p>
            <?php else: ?>
                <?php foreach ($recentNotif as $n): 
                    $cls = match($n['priority']) { 'urgent' => 'urgent', 'high' => 'warning', default => '' };
                ?>
                <div class="notif-item <?= $cls ?>">
                    <strong><?= htmlspecialchars($n['title']) ?></strong><br>
                    <span class="text-muted" style="font-size:.78rem"><?= htmlspecialchars($n['message']) ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Producers -->
    <div class="col-lg-6">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-trophy me-2"></i>Top Producers – Last 30 Days</div>
            <table class="table table-sm table-hover">
                <thead><tr><th>Tag</th><th>Name</th><th>Total (L)</th></tr></thead>
                <tbody>
                <?php foreach ($topProducers as $i => $p): ?>
                    <tr>
                        <td><span class="badge bg-success"><?= htmlspecialchars($p['tag_number']) ?></span></td>
                        <td><?= htmlspecialchars($p['name'] ?? '-') ?></td>
                        <td><strong><?= number_format($p['total'],1) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-lg-6">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-bolt me-2"></i>Quick Actions</div>
            <div class="d-grid gap-2">
                <a href="../modules/milk_production.php?action=add" class="btn btn-success"><i class="fa fa-plus me-2"></i>Record Milk Production</a>
                <a href="../modules/buffaloes.php?action=add" class="btn btn-outline-success"><i class="fa fa-paw me-2"></i>Register New Buffalo</a>
                <a href="../modules/vaccinations.php?action=add" class="btn btn-outline-warning"><i class="fa fa-syringe me-2"></i>Schedule Vaccination</a>
                <a href="../modules/reports.php" class="btn btn-outline-secondary"><i class="fa fa-file-pdf me-2"></i>Generate Report</a>
            </div>
        </div>
    </div>
</div>

<script>
const ctx = document.getElementById('prodChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Total Milk (Liters)',
            data: <?= json_encode($chartData) ?>,
            backgroundColor: 'rgba(40,167,69,.7)',
            borderColor: '#1a6b3c',
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, grid: { color: '#f0f0f0' } } }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
