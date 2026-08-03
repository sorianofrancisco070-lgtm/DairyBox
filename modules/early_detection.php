<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$root      = '../';
$pageTitle = 'Early Detection & Alerts';
$db        = getDB();
$user      = currentUser();

// ---- Production Drop Detection (>20% below 7-day avg) ----
$prodAlerts = [];
$buffaloes  = $db->query("SELECT id, tag_number, name FROM buffaloes WHERE status='Active' AND sex='Female'")->fetchAll();

foreach ($buffaloes as $buf) {
    $id = $buf['id'];
    // Avg last 7 days (excluding today)
    $s = $db->prepare("SELECT AVG(quantity_liters) FROM milk_production WHERE buffalo_id=? AND record_date BETWEEN DATE_SUB(CURDATE(),INTERVAL 7 DAY) AND DATE_SUB(CURDATE(),INTERVAL 1 DAY)");
    $s->execute([$id]); $avg7 = (float)$s->fetchColumn();

    // Today's total
    $s = $db->prepare("SELECT COALESCE(SUM(quantity_liters),0) FROM milk_production WHERE buffalo_id=? AND record_date=CURDATE()");
    $s->execute([$id]); $today = (float)$s->fetchColumn();

    if ($avg7 > 0 && $today > 0) {
        $drop = ($avg7 - $today) / $avg7 * 100;
        if ($drop >= 20) {
            $prodAlerts[] = ['buffalo'=>$buf, 'avg7'=>$avg7, 'today'=>$today, 'drop'=>round($drop,1), 'severity'=> $drop>=40 ? 'High' : 'Medium'];
        }
    }
}

// ---- Overdue Vaccinations ----
$overdueVacc = $db->query("
    SELECT v.*, b.tag_number, b.name FROM vaccinations v
    JOIN buffaloes b ON b.id=v.buffalo_id
    WHERE v.status='Overdue' ORDER BY v.next_due_date ASC
")->fetchAll();

// ---- Unresolved Health Issues > 7 days ----
$longSick = $db->query("
    SELECT hr.*, b.tag_number, b.name, DATEDIFF(CURDATE(), hr.record_date) as days_sick
    FROM health_records hr JOIN buffaloes b ON b.id=hr.buffalo_id
    WHERE hr.status='Active' AND hr.condition_type IN ('Illness','Injury','Disease Alert')
    AND hr.record_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY days_sick DESC
")->fetchAll();

// ---- Expected Calvings in next 14 days ----
$upcomingCalv = $db->query("
    SELECT br.*, b.tag_number, b.name, DATEDIFF(br.expected_calving, CURDATE()) as days_left
    FROM breeding_records br JOIN buffaloes b ON b.id=br.buffalo_id
    WHERE br.pregnancy_status='Confirmed' AND br.expected_calving BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
    ORDER BY br.expected_calving ASC
")->fetchAll();

// ---- Low Inventory ----
$lowInventory = $db->query("
    SELECT * FROM inventory WHERE quantity <= reorder_level ORDER BY quantity ASC
")->fetchAll();

include '../includes/header.php';
?>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#f8d7da"><i class="fa fa-exclamation-triangle text-danger" style="font-size:1.5rem"></i></div><div><p class="stat-label">Production Drops</p><p class="stat-value text-danger"><?= count($prodAlerts) ?></p></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#fff3cd"><i class="fa fa-syringe text-warning" style="font-size:1.5rem"></i></div><div><p class="stat-label">Overdue Vaccines</p><p class="stat-value text-warning"><?= count($overdueVacc) ?></p></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#fce8b2"><i class="fa fa-baby text-warning" style="font-size:1.5rem"></i></div><div><p class="stat-label">Calving in 14 Days</p><p class="stat-value text-warning"><?= count($upcomingCalv) ?></p></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-icon" style="background:#cce5ff"><i class="fa fa-boxes text-info" style="font-size:1.5rem"></i></div><div><p class="stat-label">Low Stock Items</p><p class="stat-value text-info"><?= count($lowInventory) ?></p></div></div></div>
</div>

<!-- Production Drops -->
<div class="card-section mb-3">
    <div class="section-title"><i class="fa fa-chart-line me-2 text-danger"></i>⚠ Production Drop Alerts (≥20% below 7-day average)</div>
    <?php if (empty($prodAlerts)): ?>
        <p class="text-success mb-0"><i class="fa fa-check-circle me-1"></i>No production drops detected today.</p>
    <?php else: ?>
    <table class="table table-sm table-hover">
        <thead><tr><th>Buffalo</th><th>7-Day Avg (L)</th><th>Today (L)</th><th>Drop %</th><th>Severity</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($prodAlerts as $a): ?>
        <tr class="<?= $a['severity']==='High'?'table-danger':'table-warning' ?>">
            <td><span class="badge bg-success"><?= htmlspecialchars($a['buffalo']['tag_number']) ?></span> <?= htmlspecialchars($a['buffalo']['name']??'') ?></td>
            <td><?= number_format($a['avg7'],2) ?></td>
            <td><?= number_format($a['today'],2) ?></td>
            <td><strong><?= $a['drop'] ?>%</strong></td>
            <td><span class="badge-custom <?= $a['severity']==='High'?'badge-sick':'badge-pregnant' ?>"><?= $a['severity'] ?></span></td>
            <td><a href="health_records.php?action=add&buffalo_id=<?= $a['buffalo']['id'] ?>" class="btn btn-sm btn-outline-danger">Log Health</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Unresolved Health > 7 days -->
<div class="card-section mb-3">
    <div class="section-title"><i class="fa fa-heartbeat me-2 text-danger"></i>Prolonged Health Issues (>7 days unresolved)</div>
    <?php if (empty($longSick)): ?>
        <p class="text-success mb-0"><i class="fa fa-check-circle me-1"></i>No prolonged health issues.</p>
    <?php else: ?>
    <table class="table table-sm">
        <thead><tr><th>Buffalo</th><th>Type</th><th>Diagnosis</th><th>Days Sick</th><th>Vet</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($longSick as $r): ?>
        <tr class="table-danger">
            <td><span class="badge bg-success"><?= htmlspecialchars($r['tag_number']) ?></span> <?= htmlspecialchars($r['name']??'') ?></td>
            <td><?= $r['condition_type'] ?></td>
            <td><?= htmlspecialchars($r['diagnosis']??'-') ?></td>
            <td><strong><?= $r['days_sick'] ?> days</strong></td>
            <td><?= htmlspecialchars($r['vet_name']??'-') ?></td>
            <td><a href="health_records.php?action=edit&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger">Update</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Overdue Vaccinations -->
<div class="card-section mb-3">
    <div class="section-title"><i class="fa fa-syringe me-2 text-warning"></i>Overdue Vaccinations</div>
    <?php if (empty($overdueVacc)): ?>
        <p class="text-success mb-0"><i class="fa fa-check-circle me-1"></i>All vaccinations are up to date.</p>
    <?php else: ?>
    <table class="table table-sm">
        <thead><tr><th>Buffalo</th><th>Vaccine</th><th>Due Date</th><th>Days Overdue</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($overdueVacc as $v): 
            $daysOverdue = (int)((time()-strtotime($v['next_due_date']))/86400);
        ?>
        <tr class="table-warning">
            <td><span class="badge bg-success"><?= htmlspecialchars($v['tag_number']) ?></span> <?= htmlspecialchars($v['name']??'') ?></td>
            <td><?= htmlspecialchars($v['vaccine_name']) ?></td>
            <td><?= $v['next_due_date'] ?></td>
            <td><strong><?= $daysOverdue ?> days</strong></td>
            <td><a href="vaccinations.php?action=add&buffalo_id=<?= $v['buffalo_id'] ?>" class="btn btn-sm btn-outline-warning">Administer</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Upcoming Calvings -->
<?php if (!empty($upcomingCalv)): ?>
<div class="card-section mb-3">
    <div class="section-title"><i class="fa fa-baby me-2 text-warning"></i>Expected Calvings – Next 14 Days</div>
    <table class="table table-sm">
        <thead><tr><th>Buffalo</th><th>Expected Date</th><th>Days Left</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($upcomingCalv as $c): ?>
        <tr class="<?= $c['days_left']<=3?'table-danger':'table-warning' ?>">
            <td><span class="badge bg-success"><?= htmlspecialchars($c['tag_number']) ?></span> <?= htmlspecialchars($c['name']??'') ?></td>
            <td><?= $c['expected_calving'] ?></td>
            <td><strong><?= $c['days_left'] ?> days</strong></td>
            <td><a href="breeding.php?tab=calving&action=add" class="btn btn-sm btn-outline-success">Record Calving</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Low Inventory -->
<?php if (!empty($lowInventory)): ?>
<div class="card-section">
    <div class="section-title"><i class="fa fa-box me-2 text-info"></i>Low Stock Alerts</div>
    <table class="table table-sm">
        <thead><tr><th>Item</th><th>Category</th><th>In Stock</th><th>Reorder Level</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($lowInventory as $inv): ?>
        <tr class="table-info">
            <td><?= htmlspecialchars($inv['item_name']) ?></td>
            <td><?= $inv['category'] ?></td>
            <td><strong><?= $inv['quantity'] ?> <?= $inv['unit'] ?></strong></td>
            <td><?= $inv['reorder_level'] ?> <?= $inv['unit'] ?></td>
            <td><a href="inventory.php?action=edit&id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-info">Update Stock</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
