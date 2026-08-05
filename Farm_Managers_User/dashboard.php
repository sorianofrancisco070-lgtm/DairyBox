<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin('farm_manager');

$root      = '../';
$pageTitle = 'Farm Manager Dashboard';
$db        = getDB();
$user      = currentUser();

// ── Overall Stats ─────────────────────────────────────
$totalBuffaloes = $db->query("SELECT COUNT(*) FROM buffaloes WHERE status='Active'")->fetchColumn();
$totalUsers     = $db->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
$totalMilkRecs  = $db->query("SELECT COUNT(*) FROM milk_production WHERE MONTH(record_date)=MONTH(CURDATE()) AND YEAR(record_date)=YEAR(CURDATE())")->fetchColumn();
$totalHealthRecs= $db->query("SELECT COUNT(*) FROM health_records WHERE MONTH(record_date)=MONTH(CURDATE()) AND YEAR(record_date)=YEAR(CURDATE())")->fetchColumn();
$totalVaccRecs  = $db->query("SELECT COUNT(*) FROM vaccinations WHERE MONTH(administered_date)=MONTH(CURDATE()) AND YEAR(administered_date)=YEAR(CURDATE())")->fetchColumn();
$totalBreedRecs = $db->query("SELECT COUNT(*) FROM breeding_records WHERE MONTH(breeding_date)=MONTH(CURDATE()) AND YEAR(breeding_date)=YEAR(CURDATE())")->fetchColumn();

// ── All Users List ────────────────────────────────────
$allUsers = $db->query("
    SELECT u.*,
        (SELECT COUNT(*) FROM milk_production mp WHERE mp.recorded_by=u.id AND MONTH(mp.record_date)=MONTH(CURDATE())) as milk_entries,
        (SELECT COUNT(*) FROM health_records hr WHERE hr.recorded_by=u.id AND MONTH(hr.record_date)=MONTH(CURDATE())) as health_entries,
        (SELECT COUNT(*) FROM vaccinations v WHERE v.recorded_by=u.id AND MONTH(v.administered_date)=MONTH(CURDATE())) as vacc_entries,
        (SELECT COUNT(*) FROM breeding_records br WHERE br.recorded_by=u.id AND MONTH(br.breeding_date)=MONTH(CURDATE())) as breed_entries,
        (SELECT MAX(al.created_at) FROM activity_log al WHERE al.user_id=u.id) as last_active
    FROM users u
    WHERE u.is_active=1
    ORDER BY FIELD(u.role,'farm_manager','farm_caretaker','veterinarian','dairy_cooperative'), u.full_name
")->fetchAll();

// ── Recent Activity Log ───────────────────────────────
$recentActivity = $db->query("
    SELECT al.*, u.full_name, u.role FROM activity_log al
    JOIN users u ON u.id=al.user_id
    ORDER BY al.created_at DESC LIMIT 20
")->fetchAll();

// ── Buffalo Records Summary ───────────────────────────
$buffaloSummary = $db->query("
    SELECT b.id, b.tag_number, b.name, b.health_status, b.sex,
           COALESCE(SUM(mp.quantity_liters),0) as month_milk,
           (SELECT COUNT(*) FROM health_records hr WHERE hr.buffalo_id=b.id AND MONTH(hr.record_date)=MONTH(CURDATE())) as health_count,
           (SELECT COUNT(*) FROM vaccinations v WHERE v.buffalo_id=b.id) as vacc_count
    FROM buffaloes b
    LEFT JOIN milk_production mp ON mp.buffalo_id=b.id
        AND MONTH(mp.record_date)=MONTH(CURDATE())
        AND YEAR(mp.record_date)=YEAR(CURDATE())
    WHERE b.status='Active'
    GROUP BY b.id, b.tag_number, b.name, b.health_status, b.sex
    ORDER BY b.tag_number
")->fetchAll();

// ── Milk Production Records (this month) ─────────────
$milkRecords = $db->query("
    SELECT mp.*, b.tag_number, b.name as buffalo_name, u.full_name as recorded_by_name
    FROM milk_production mp
    JOIN buffaloes b ON b.id=mp.buffalo_id
    LEFT JOIN users u ON u.id=mp.recorded_by
    WHERE MONTH(mp.record_date)=MONTH(CURDATE()) AND YEAR(mp.record_date)=YEAR(CURDATE())
    ORDER BY mp.record_date DESC, mp.created_at DESC LIMIT 20
")->fetchAll();

// ── Health Records (this month) ───────────────────────
$healthRecords = $db->query("
    SELECT hr.*, b.tag_number, b.name as buffalo_name, u.full_name as recorded_by_name
    FROM health_records hr
    JOIN buffaloes b ON b.id=hr.buffalo_id
    LEFT JOIN users u ON u.id=hr.recorded_by
    WHERE MONTH(hr.record_date)=MONTH(CURDATE()) AND YEAR(hr.record_date)=YEAR(CURDATE())
    ORDER BY hr.record_date DESC LIMIT 15
")->fetchAll();

// ── Vaccination Records (this month) ─────────────────
$vaccRecords = $db->query("
    SELECT v.*, b.tag_number, b.name as buffalo_name, u.full_name as recorded_by_name
    FROM vaccinations v
    JOIN buffaloes b ON b.id=v.buffalo_id
    LEFT JOIN users u ON u.id=v.recorded_by
    WHERE MONTH(v.administered_date)=MONTH(CURDATE()) AND YEAR(v.administered_date)=YEAR(CURDATE())
    ORDER BY v.administered_date DESC LIMIT 15
")->fetchAll();

// ── Breeding Records (this month) ────────────────────
$breedRecords = $db->query("
    SELECT br.*, b.tag_number, b.name as buffalo_name, u.full_name as recorded_by_name
    FROM breeding_records br
    JOIN buffaloes b ON b.id=br.buffalo_id
    LEFT JOIN users u ON u.id=br.recorded_by
    WHERE MONTH(br.breeding_date)=MONTH(CURDATE()) AND YEAR(br.breeding_date)=YEAR(CURDATE())
    ORDER BY br.breeding_date DESC LIMIT 10
")->fetchAll();

$monthName = date('F Y');
$roleLabels = ['farm_manager'=>'Farm Manager','farm_caretaker'=>'Farm Caretaker','dairy_cooperative'=>'Dairy Cooperative','veterinarian'=>'Veterinarian'];

include '../includes/header.php';
?>

<!-- ── PAGE HEADER ── -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h5 class="fw-bold text-success mb-0"><i class="fa fa-tachometer-alt me-2"></i>Farm Manager Dashboard</h5>
        <small class="text-muted">All records overview — <?= $monthName ?></small>
    </div>
    <a href="../modules/users.php" class="btn btn-success btn-sm">
        <i class="fa fa-users me-1"></i>Manage Users
    </a>
</div>

<!-- ── SUMMARY STATS ── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:#d4edda"><i class="fa fa-users text-success" style="font-size:1.3rem"></i></div>
            <div><p class="stat-label">Users</p><p class="stat-value text-success"><?= $totalUsers ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:#cce5ff"><span style="font-size:1.4rem">🐃</span></div>
            <div><p class="stat-label">Buffaloes</p><p class="stat-value text-primary"><?= $totalBuffaloes ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:#d4edda"><i class="fa fa-tint text-success" style="font-size:1.3rem"></i></div>
            <div><p class="stat-label">Milk Records</p><p class="stat-value text-success"><?= $totalMilkRecs ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f8d7da"><i class="fa fa-heartbeat text-danger" style="font-size:1.3rem"></i></div>
            <div><p class="stat-label">Health Records</p><p class="stat-value text-danger"><?= $totalHealthRecs ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff3cd"><i class="fa fa-syringe text-warning" style="font-size:1.3rem"></i></div>
            <div><p class="stat-label">Vaccinations</p><p class="stat-value text-warning"><?= $totalVaccRecs ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e2d9f3"><i class="fa fa-venus-mars" style="color:#6f42c1;font-size:1.3rem"></i></div>
            <div><p class="stat-label">Breeding Records</p><p class="stat-value" style="color:#6f42c1"><?= $totalBreedRecs ?></p></div>
        </div>
    </div>
</div>

<!-- ── USER RECORDS ── -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-users me-2"></i>All Users — Records This Month</div>
    <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
        <thead>
            <tr><th>Name</th><th>Role</th><th>Milk Entries</th><th>Health Entries</th><th>Vacc. Entries</th><th>Breed Entries</th><th>Last Active</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php foreach ($allUsers as $u):
            $roleCls = match($u['role']) {
                'farm_manager'      => 'bg-success',
                'farm_caretaker'    => 'bg-primary',
                'veterinarian'      => 'bg-danger',
                'dairy_cooperative' => 'bg-warning text-dark',
                default             => 'bg-secondary'
            };
        ?>
        <tr>
            <td>
                <strong><?= htmlspecialchars($u['full_name']) ?></strong><br>
                <small class="text-muted"><?= htmlspecialchars($u['username']) ?></small>
            </td>
            <td><span class="badge <?= $roleCls ?>"><?= $roleLabels[$u['role']] ?? $u['role'] ?></span></td>
            <td><span class="badge bg-success"><?= $u['milk_entries'] ?></span></td>
            <td><span class="badge bg-danger"><?= $u['health_entries'] ?></span></td>
            <td><span class="badge bg-warning text-dark"><?= $u['vacc_entries'] ?></span></td>
            <td><span class="badge" style="background:#6f42c1"><?= $u['breed_entries'] ?></span></td>
            <td>
                <?php if ($u['last_active']): ?>
                <small class="text-muted"><?= date('M d, H:i', strtotime($u['last_active'])) ?></small>
                <?php else: ?><small class="text-muted">Never</small><?php endif; ?>
            </td>
            <td><span class="badge <?= $u['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- ── BUFFALO RECORDS ── -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-paw me-2"></i>Buffalo Records Overview</div>
    <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
        <thead><tr><th>Tag</th><th>Name</th><th>Sex</th><th>Health</th><th>Month Milk (L)</th><th>Health Records</th><th>Vaccinations</th></tr></thead>
        <tbody>
        <?php foreach ($buffaloSummary as $b):
            $hCls = match($b['health_status']) {'Healthy'=>'badge-healthy','Sick'=>'badge-sick','Under Treatment'=>'badge-treated',default=>''};
        ?>
        <tr>
            <td><span class="badge bg-success"><?= htmlspecialchars($b['tag_number']) ?></span></td>
            <td><?= htmlspecialchars($b['name']??'-') ?></td>
            <td><?= $b['sex'] ?></td>
            <td><span class="badge-custom <?= $hCls ?>"><?= $b['health_status'] ?></span></td>
            <td><?= $b['month_milk'] > 0 ? '<strong>'.number_format($b['month_milk'],1).'</strong>' : '<span class="text-muted">—</span>' ?></td>
            <td><span class="badge bg-danger"><?= $b['health_count'] ?></span></td>
            <td><span class="badge bg-warning text-dark"><?= $b['vacc_count'] ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($buffaloSummary)): ?><tr><td colspan="7" class="text-center text-muted py-3">No buffaloes found.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- ── TABS: Milk / Health / Vaccination / Breeding ── -->
<div class="card-section mb-4">
    <ul class="nav nav-tabs mb-3" id="recordsTabs">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-milk"><i class="fa fa-tint me-1"></i>Milk Production</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-health"><i class="fa fa-heartbeat me-1"></i>Health Records</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-vacc"><i class="fa fa-syringe me-1"></i>Vaccinations</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-breed"><i class="fa fa-venus-mars me-1"></i>Breeding</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-activity"><i class="fa fa-history me-1"></i>Activity Log</a></li>
    </ul>
    <div class="tab-content">

        <!-- Milk Tab -->
        <div class="tab-pane fade show active" id="tab-milk">
            <p class="text-muted small mb-2">Latest milk production entries this month — <?= $monthName ?></p>
            <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>Date</th><th>Buffalo</th><th>Session</th><th>Liters</th><th>Recorded By</th></tr></thead>
                <tbody>
                <?php foreach ($milkRecords as $r): ?>
                <tr>
                    <td><?= $r['record_date'] ?></td>
                    <td><span class="badge bg-success"><?= htmlspecialchars($r['tag_number']) ?></span> <?= htmlspecialchars($r['buffalo_name']??'') ?></td>
                    <td><?= $r['session'] ?></td>
                    <td><strong><?= number_format($r['quantity_liters'],2) ?> L</strong></td>
                    <td><small class="text-muted"><?= htmlspecialchars($r['recorded_by_name']??'—') ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($milkRecords)): ?><tr><td colspan="5" class="text-center text-muted py-3">No records this month.</td></tr><?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Health Tab -->
        <div class="tab-pane fade" id="tab-health">
            <p class="text-muted small mb-2">Health events recorded this month — <?= $monthName ?></p>
            <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>Date</th><th>Buffalo</th><th>Type</th><th>Diagnosis</th><th>Status</th><th>Recorded By</th></tr></thead>
                <tbody>
                <?php foreach ($healthRecords as $r):
                    $sCls = match($r['status']) {'Resolved'=>'badge-healthy','Active'=>'badge-sick',default=>'badge-treated'};
                ?>
                <tr>
                    <td><?= $r['record_date'] ?></td>
                    <td><span class="badge bg-success"><?= htmlspecialchars($r['tag_number']) ?></span> <?= htmlspecialchars($r['buffalo_name']??'') ?></td>
                    <td><span class="badge bg-secondary"><?= $r['condition_type'] ?></span></td>
                    <td><?= htmlspecialchars($r['diagnosis']??'—') ?></td>
                    <td><span class="badge-custom <?= $sCls ?>"><?= $r['status'] ?></span></td>
                    <td><small class="text-muted"><?= htmlspecialchars($r['recorded_by_name']??'—') ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($healthRecords)): ?><tr><td colspan="6" class="text-center text-muted py-3">No records this month.</td></tr><?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Vaccination Tab -->
        <div class="tab-pane fade" id="tab-vacc">
            <p class="text-muted small mb-2">Vaccinations administered this month — <?= $monthName ?></p>
            <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>Date</th><th>Buffalo</th><th>Vaccine</th><th>Next Due</th><th>Status</th><th>Recorded By</th></tr></thead>
                <tbody>
                <?php foreach ($vaccRecords as $r):
                    $sCls = match($r['status']) {'Done'=>'badge-healthy','Overdue'=>'badge-sick','Scheduled'=>'badge-treated',default=>''};
                ?>
                <tr>
                    <td><?= $r['administered_date'] ?></td>
                    <td><span class="badge bg-success"><?= htmlspecialchars($r['tag_number']) ?></span> <?= htmlspecialchars($r['buffalo_name']??'') ?></td>
                    <td><?= htmlspecialchars($r['vaccine_name']) ?></td>
                    <td><?= $r['next_due_date']??'—' ?></td>
                    <td><span class="badge-custom <?= $sCls ?>"><?= $r['status'] ?></span></td>
                    <td><small class="text-muted"><?= htmlspecialchars($r['recorded_by_name']??'—') ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($vaccRecords)): ?><tr><td colspan="6" class="text-center text-muted py-3">No records this month.</td></tr><?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Breeding Tab -->
        <div class="tab-pane fade" id="tab-breed">
            <p class="text-muted small mb-2">Breeding records this month — <?= $monthName ?></p>
            <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>Date</th><th>Buffalo</th><th>Method</th><th>Expected Calving</th><th>Pregnancy</th><th>Recorded By</th></tr></thead>
                <tbody>
                <?php foreach ($breedRecords as $r):
                    $pCls = match($r['pregnancy_status']) {'Confirmed'=>'badge-pregnant','Delivered'=>'badge-healthy','Failed'=>'badge-sick',default=>'badge-treated'};
                ?>
                <tr>
                    <td><?= $r['breeding_date'] ?></td>
                    <td><span class="badge bg-success"><?= htmlspecialchars($r['tag_number']) ?></span> <?= htmlspecialchars($r['buffalo_name']??'') ?></td>
                    <td><?= $r['method'] ?></td>
                    <td><?= $r['expected_calving']??'—' ?></td>
                    <td><span class="badge-custom <?= $pCls ?>"><?= $r['pregnancy_status'] ?></span></td>
                    <td><small class="text-muted"><?= htmlspecialchars($r['recorded_by_name']??'—') ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($breedRecords)): ?><tr><td colspan="6" class="text-center text-muted py-3">No records this month.</td></tr><?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Activity Log Tab -->
        <div class="tab-pane fade" id="tab-activity">
            <p class="text-muted small mb-2">Recent system activity across all users</p>
            <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>Time</th><th>User</th><th>Role</th><th>Action</th><th>Module</th></tr></thead>
                <tbody>
                <?php foreach ($recentActivity as $a): ?>
                <tr>
                    <td><small class="text-muted"><?= date('M d, H:i', strtotime($a['created_at'])) ?></small></td>
                    <td><?= htmlspecialchars($a['full_name']) ?></td>
                    <td><span class="badge bg-secondary" style="font-size:.65rem"><?= $roleLabels[$a['role']]??$a['role'] ?></span></td>
                    <td><?= htmlspecialchars($a['action']??'—') ?></td>
                    <td><small class="text-muted"><?= htmlspecialchars($a['module']??'—') ?></small></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentActivity)): ?><tr><td colspan="5" class="text-center text-muted py-3">No activity recorded yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
