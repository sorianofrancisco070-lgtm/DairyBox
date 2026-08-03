<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin('farm_caretaker');

$root      = '../';
$pageTitle = 'Farm Caretaker Dashboard';
$db        = getDB();
$user      = currentUser();

$todayProd   = $db->query("SELECT COALESCE(SUM(quantity_liters),0) FROM milk_production WHERE record_date=CURDATE()")->fetchColumn();
$totalBuf    = $db->query("SELECT COUNT(*) FROM buffaloes WHERE status='Active'")->fetchColumn();
$pendingVacc = $db->query("SELECT COUNT(*) FROM vaccinations WHERE status IN ('Scheduled','Overdue')")->fetchColumn();
$todayRecords= $db->query("SELECT COUNT(*) FROM milk_production WHERE record_date=CURDATE()")->fetchColumn();

// Today's tasks / reminders
$tasks = $db->query("
    SELECT * FROM notifications
    WHERE (target_role='farm_caretaker' OR target_role IS NULL) AND is_read=0
    ORDER BY priority DESC LIMIT 5
")->fetchAll();

// Recent milk entries by this user
$recentMilk = $db->query("
    SELECT mp.*, b.tag_number, b.name FROM milk_production mp
    JOIN buffaloes b ON b.id=mp.buffalo_id
    WHERE mp.recorded_by={$user['id']}
    ORDER BY mp.created_at DESC LIMIT 8
")->fetchAll();

include '../includes/header.php';
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#d4edda"><span style="font-size:1.6rem">🐃</span></div>
            <div><p class="stat-label">Active Buffaloes</p><p class="stat-value text-success"><?= $totalBuf ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#cce5ff"><i class="fa fa-tint text-primary" style="font-size:1.5rem"></i></div>
            <div><p class="stat-label">Today's Milk (L)</p><p class="stat-value text-primary"><?= number_format($todayProd,1) ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff3cd"><i class="fa fa-clipboard-list text-warning" style="font-size:1.5rem"></i></div>
            <div><p class="stat-label">Today's Entries</p><p class="stat-value text-warning"><?= $todayRecords ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f8d7da"><i class="fa fa-syringe text-danger" style="font-size:1.5rem"></i></div>
            <div><p class="stat-label">Pending Vaccines</p><p class="stat-value text-danger"><?= $pendingVacc ?></p></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Quick Actions -->
    <div class="col-md-5">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-bolt me-2"></i>Quick Actions</div>
            <div class="d-grid gap-2">
                <a href="../modules/milk_production.php?action=add" class="btn btn-success btn-lg"><i class="fa fa-tint me-2"></i>Record Milk</a>
                <a href="../modules/health_records.php?action=add" class="btn btn-outline-danger"><i class="fa fa-heartbeat me-2"></i>Log Health Event</a>
                <a href="../modules/vaccinations.php?action=add" class="btn btn-outline-warning"><i class="fa fa-syringe me-2"></i>Record Vaccination</a>
                <a href="../modules/breeding.php?action=add" class="btn btn-outline-info"><i class="fa fa-venus-mars me-2"></i>Record Breeding</a>
                <a href="../modules/qr_scan.php" class="btn btn-outline-secondary"><i class="fa fa-qrcode me-2"></i>Scan QR Code</a>
            </div>
        </div>
        <!-- Reminders -->
        <div class="card-section mt-3">
            <div class="section-title"><i class="fa fa-bell me-2"></i>My Reminders</div>
            <?php foreach ($tasks as $t): 
                $cls = match($t['priority']) { 'urgent'=>'urgent','high'=>'warning', default=>'' };
            ?>
            <div class="notif-item <?= $cls ?>">
                <strong><?= htmlspecialchars($t['title']) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($t['message']) ?></small>
            </div>
            <?php endforeach; ?>
            <?php if (empty($tasks)): ?><p class="text-muted small">No pending reminders.</p><?php endif; ?>
        </div>
    </div>
    <!-- Recent Milk Entries -->
    <div class="col-md-7">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-tint me-2"></i>My Recent Milk Entries</div>
            <table class="table table-sm table-hover">
                <thead><tr><th>Date</th><th>Buffalo</th><th>Session</th><th>Liters</th></tr></thead>
                <tbody>
                <?php foreach ($recentMilk as $r): ?>
                <tr>
                    <td><?= $r['record_date'] ?></td>
                    <td><span class="badge bg-success"><?= htmlspecialchars($r['tag_number']) ?></span> <?= htmlspecialchars($r['name'] ?? '') ?></td>
                    <td><?= $r['session'] ?></td>
                    <td><strong><?= number_format($r['quantity_liters'],2) ?> L</strong></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentMilk)): ?>
                <tr><td colspan="4" class="text-center text-muted">No entries yet today.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
