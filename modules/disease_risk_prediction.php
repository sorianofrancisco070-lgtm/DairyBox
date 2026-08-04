<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$root      = '../';
$pageTitle = 'Disease Risk Prediction';
$db        = getDB();
$user      = currentUser();

// ════════════════════════════════════════════════════════
// DISEASE REFERENCE GUIDE
// ════════════════════════════════════════════════════════
$diseaseGuide = [
    'Mastitis' => [
        'icon'        => 'fa-tint',
        'color'       => '#dc3545',
        'desc'        => 'Inflammation of the mammary gland, often caused by bacterial infection (Staphylococcus, Streptococcus, E. coli).',
        'symptoms'    => 'Swollen udder, abnormal milk (clots, watery, bloody), reduced milk yield, fever, pain on palpation.',
        'prevention'  => 'Proper milking hygiene, teat dipping, dry-cow therapy, regular California Mastitis Test (CMT), housing cleanliness.',
    ],
    'Foot and Mouth Disease' => [
        'icon'        => 'fa-paw',
        'color'       => '#fd7e14',
        'desc'        => 'Highly contagious viral disease (Aphthovirus) affecting cloven-hoofed animals. Reportable disease.',
        'symptoms'    => 'Fever, blisters/ulcers on mouth, tongue, feet, and teats; excessive salivation; lameness; anorexia; milk drop.',
        'prevention'  => 'Regular FMD vaccination (every 6 months), strict biosecurity, quarantine of new animals, avoid exposure to infected herds.',
    ],
    'Hemorrhagic Septicemia' => [
        'icon'        => 'fa-skull',
        'color'       => '#6c757d',
        'desc'        => 'Acute bacterial disease caused by Pasteurella multocida, highly fatal in buffalo and cattle.',
        'symptoms'    => 'High fever (41°C+), edematous swelling of throat/neck, difficulty breathing, profuse salivation, sudden death.',
        'prevention'  => 'Annual HS vaccination, avoid stress and overcrowding, proper nutrition, good ventilation in housing.',
    ],
    'Brucellosis' => [
        'icon'        => 'fa-bacteria',
        'color'       => '#6f42c1',
        'desc'        => 'Bacterial disease (Brucella abortus/melitensis) causing reproductive failure. Zoonotic – transmissible to humans.',
        'symptoms'    => 'Abortion in late pregnancy, retained placenta, weak calves, reduced fertility, orchitis in males.',
        'prevention'  => 'Test-and-slaughter programs, vaccination of heifers, avoid introduction of untested animals, proper carcass disposal.',
    ],
    'Milk Fever' => [
        'icon'        => 'fa-thermometer',
        'color'       => '#17a2b8',
        'desc'        => 'Metabolic disorder (hypocalcemia) occurring around calving due to low blood calcium levels.',
        'symptoms'    => 'Muscle tremors, inability to stand, cold extremities, loss of appetite, bloat, constipation within 72 hours of calving.',
        'prevention'  => 'Restrict calcium pre-calving (anionic salts), Vitamin D3 injection at dry-off, calcium bolus at calving.',
    ],
    'Ketosis' => [
        'icon'        => 'fa-flask',
        'color'       => '#20c997',
        'desc'        => 'Metabolic disease caused by negative energy balance in early lactation, leading to excessive fat mobilization.',
        'symptoms'    => 'Reduced appetite, weight loss, sweet/fruity smell of breath (acetone), reduced milk yield, lethargy, nervous signs.',
        'prevention'  => 'Prevent over-conditioning at dry-off (target BCS 3.0–3.5), adequate energy intake in transition period, propylene glycol supplementation.',
    ],
    'Retained Placenta' => [
        'icon'        => 'fa-baby',
        'color'       => '#e83e8c',
        'desc'        => 'Failure to expel fetal membranes within 12–24 hours after calving, predisposing to uterine infection.',
        'symptoms'    => 'Visible hanging membranes after calving, foul-smelling discharge, fever, reduced appetite, secondary metritis.',
        'prevention'  => 'Selenium/Vitamin E supplementation pre-calving, proper nutrition in dry period, reduce stress at calving.',
    ],
    'Secondary Infections' => [
        'icon'        => 'fa-virus',
        'color'       => '#856404',
        'desc'        => 'Opportunistic infections that develop when an animal has a prolonged primary illness or weakened immune system.',
        'symptoms'    => 'Worsening of existing condition, new symptoms, failure to respond to initial treatment, persistent fever.',
        'prevention'  => 'Prompt treatment of primary illness, complete antibiotic courses, supportive care, improve hygiene and nutrition.',
    ],
];

// ════════════════════════════════════════════════════════
// FETCH BUFFALO DATA
// ════════════════════════════════════════════════════════
$animalRisks = [];
$herdSummary = ['low' => 0, 'moderate' => 0, 'high' => 0, 'critical' => 0];

try {
    // Get all active buffaloes with basic info
    $bufStmt = $db->query("
        SELECT b.id, b.tag_number, b.name, b.health_status, b.sex,
               b.date_of_birth,
               (SELECT MAX(hr.record_date) FROM health_records hr WHERE hr.buffalo_id = b.id) AS last_health_check,
               (SELECT COUNT(*) FROM vaccinations v WHERE v.buffalo_id = b.id AND v.status = 'Overdue' AND v.vaccine_name LIKE '%FMD%') AS fmd_overdue,
               (SELECT COUNT(*) FROM vaccinations v WHERE v.buffalo_id = b.id AND v.status = 'Overdue' AND (v.vaccine_name LIKE '%Hemorrhagic%' OR v.vaccine_name LIKE '%HS%' OR v.vaccine_name LIKE '%Septicemia%')) AS hs_overdue,
               (SELECT COUNT(*) FROM vaccinations v WHERE v.buffalo_id = b.id) AS total_vacc_records,
               (SELECT br.expected_calving FROM breeding_records br WHERE br.buffalo_id = b.id AND br.pregnancy_status = 'Confirmed' ORDER BY br.expected_calving ASC LIMIT 1) AS expected_calving
        FROM buffaloes b
        WHERE b.status = 'Active'
        ORDER BY b.health_status DESC, b.tag_number ASC
    ");
    $bufList = $bufStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bufList = [];
}

// Per-buffalo production drop check
$prodDropCache = [];
try {
    $femaleBufs = $db->query("SELECT id FROM buffaloes WHERE status='Active' AND sex='Female'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($femaleBufs as $bid) {
        $s = $db->prepare("SELECT COALESCE(AVG(quantity_liters),0) FROM milk_production WHERE buffalo_id=? AND record_date BETWEEN DATE_SUB(CURDATE(),INTERVAL 7 DAY) AND DATE_SUB(CURDATE(),INTERVAL 1 DAY)");
        $s->execute([$bid]);
        $avg7 = (float)$s->fetchColumn();

        $s2 = $db->prepare("SELECT COALESCE(SUM(quantity_liters),0) FROM milk_production WHERE buffalo_id=? AND record_date = CURDATE()");
        $s2->execute([$bid]);
        $todayTotal = (float)$s2->fetchColumn();

        $hasDrop = false;
        $dropPct  = 0;
        if ($avg7 > 0 && $todayTotal > 0 && (($avg7 - $todayTotal) / $avg7) >= 0.20) {
            $hasDrop = true;
            $dropPct = round((($avg7 - $todayTotal) / $avg7) * 100, 1);
        }
        $prodDropCache[$bid] = ['has_drop' => $hasDrop, 'avg7' => $avg7, 'today' => $todayTotal, 'drop_pct' => $dropPct];
    }
} catch (Exception $e) {
    // silently continue without production data
}

// ════════════════════════════════════════════════════════
// RULE-BASED RISK SCORING PER ANIMAL
// ════════════════════════════════════════════════════════
foreach ($bufList as &$buf) {
    $score    = 0;
    $diseases = [];
    $factors  = [];

    $id     = $buf['id'];
    $age    = 0;
    if (!empty($buf['date_of_birth'])) {
        $dob  = new DateTime($buf['date_of_birth']);
        $now  = new DateTime();
        $age  = (int)$dob->diff($now)->y;
    }

    // ── Rule 1: Health status ──────────────────────────────
    if ($buf['health_status'] === 'Sick') {
        $score += 35;
        $factors[] = ['label' => 'Currently sick', 'pts' => 35, 'color' => '#dc3545'];
        $diseases[] = 'Mastitis';
        $diseases[] = 'Foot and Mouth Disease';
        $diseases[] = 'Secondary Infections';
    } elseif ($buf['health_status'] === 'Under Treatment') {
        $score += 20;
        $factors[] = ['label' => 'Under treatment', 'pts' => 20, 'color' => '#fd7e14'];
        $diseases[] = 'Secondary Infections';
    }

    // ── Rule 2: Milk production drop >20% ─────────────────
    if (isset($prodDropCache[$id]) && $prodDropCache[$id]['has_drop']) {
        $score += 15;
        $drop = $prodDropCache[$id]['drop_pct'];
        $factors[] = ['label' => "Milk drop {$drop}% (7-day avg)", 'pts' => 15, 'color' => '#fd7e14'];
        $diseases[] = 'Mastitis';
        $diseases[] = 'Foot and Mouth Disease';
    }

    // ── Rule 3: Overdue FMD vaccine ───────────────────────
    if ((int)$buf['fmd_overdue'] > 0) {
        $score += 12;
        $factors[] = ['label' => 'FMD vaccine overdue', 'pts' => 12, 'color' => '#fd7e14'];
        $diseases[] = 'Foot and Mouth Disease';
    }

    // ── Rule 4: Overdue HS vaccine ────────────────────────
    if ((int)$buf['hs_overdue'] > 0) {
        $score += 10;
        $factors[] = ['label' => 'Hemorrhagic Septicemia vaccine overdue', 'pts' => 10, 'color' => '#ffc107'];
        $diseases[] = 'Hemorrhagic Septicemia';
    }

    // ── Rule 5: No vaccination records at all ─────────────
    if ((int)$buf['total_vacc_records'] === 0) {
        $score += 18;
        $factors[] = ['label' => 'Never vaccinated', 'pts' => 18, 'color' => '#dc3545'];
        $diseases[] = 'Hemorrhagic Septicemia';
        $diseases[] = 'Brucellosis';
        $diseases[] = 'Foot and Mouth Disease';
    }

    // ── Rule 6: Days since last health check >30 ──────────
    $daysSinceCheck = 999;
    if (!empty($buf['last_health_check'])) {
        $daysSinceCheck = (int)(new DateTime())->diff(new DateTime($buf['last_health_check']))->days;
    }
    if ($daysSinceCheck > 30) {
        $score += 8;
        $factors[] = ['label' => "No health check in {$daysSinceCheck} days", 'pts' => 8, 'color' => '#ffc107'];
    }

    // ── Rule 7: Near calving (<14 days) ───────────────────
    $daysToCalving = null;
    if (!empty($buf['expected_calving'])) {
        $calDate       = new DateTime($buf['expected_calving']);
        $today         = new DateTime();
        $daysToCalving = (int)$today->diff($calDate)->days * ($calDate >= $today ? 1 : -1);
        if ($daysToCalving >= 0 && $daysToCalving < 14) {
            $score += 12;
            $factors[] = ['label' => "Calving in {$daysToCalving} day(s) – periparturient risk", 'pts' => 12, 'color' => '#17a2b8'];
            $diseases[] = 'Milk Fever';
            $diseases[] = 'Ketosis';
            $diseases[] = 'Retained Placenta';
        }
    }

    // ── Rule 8: Age >8 years ──────────────────────────────
    if ($age > 8) {
        $score += 10;
        $factors[] = ['label' => "Aged {$age} years (higher susceptibility)", 'pts' => 10, 'color' => '#6c757d'];
    }

    $score = min(100, $score);

    // Risk level
    if ($score <= 20) {
        $riskLevel = 'Low';
        $riskColor = 'success';
        $riskHex   = '#28a745';
    } elseif ($score <= 45) {
        $riskLevel = 'Moderate';
        $riskColor = 'warning';
        $riskHex   = '#856404';
    } elseif ($score <= 70) {
        $riskLevel = 'High';
        $riskColor = 'danger';
        $riskHex   = '#dc3545';
    } else {
        $riskLevel = 'Critical';
        $riskColor = 'danger';
        $riskHex   = '#9b0000';
    }

    $herdSummary[strtolower($riskLevel)]++;

    $buf['risk_score']   = $score;
    $buf['risk_level']   = $riskLevel;
    $buf['risk_color']   = $riskColor;
    $buf['risk_hex']     = $riskHex;
    $buf['risk_factors'] = $factors;
    $buf['diseases']     = array_values(array_unique($diseases));
    $buf['age']          = $age;
    $buf['days_since_check'] = $daysSinceCheck;
    $buf['days_to_calving']  = $daysToCalving;
    $buf['prod_info']    = $prodDropCache[$id] ?? null;
}
unset($buf);

// Sort by risk score descending
usort($animalRisks, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);
$animalRisks = $bufList; // bufList was modified in-place

// Overall herd risk level
$totalAnimals = count($animalRisks);
$critHighCount = $herdSummary['critical'] + $herdSummary['high'];
if ($critHighCount >= ceil($totalAnimals * 0.3)) {
    $herdRiskLevel = 'Critical';
    $herdRiskColor = '#dc3545';
    $herdRiskBg    = '#f8d7da';
    $herdRiskEmoji = '🚨';
} elseif ($critHighCount > 0 || $herdSummary['moderate'] >= ceil($totalAnimals * 0.3)) {
    $herdRiskLevel = 'High';
    $herdRiskColor = '#fd7e14';
    $herdRiskBg    = '#fce8d5';
    $herdRiskEmoji = '🔶';
} elseif ($herdSummary['moderate'] > 0) {
    $herdRiskLevel = 'Moderate';
    $herdRiskColor = '#856404';
    $herdRiskBg    = '#fff3cd';
    $herdRiskEmoji = '⚠️';
} else {
    $herdRiskLevel = 'Low';
    $herdRiskColor = '#28a745';
    $herdRiskBg    = '#d4edda';
    $herdRiskEmoji = '✅';
}

include '../includes/header.php';
?>

<!-- ═══════════════ HERD SUMMARY STATS ═══════════════ -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#d4edda"><i class="fa fa-check-circle text-success" style="font-size:1.5rem"></i></div>
            <div><p class="stat-label">Low Risk</p><p class="stat-value text-success"><?php echo $herdSummary['low']; ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff3cd"><i class="fa fa-exclamation-circle text-warning" style="font-size:1.5rem"></i></div>
            <div><p class="stat-label">Moderate Risk</p><p class="stat-value text-warning"><?php echo $herdSummary['moderate']; ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f8d7da"><i class="fa fa-exclamation-triangle text-danger" style="font-size:1.5rem"></i></div>
            <div><p class="stat-label">High Risk</p><p class="stat-value text-danger"><?php echo $herdSummary['high']; ?></p></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#f5c6cb"><i class="fa fa-skull-crossbones text-danger" style="font-size:1.5rem"></i></div>
            <div><p class="stat-label">Critical Risk</p><p class="stat-value text-danger"><?php echo $herdSummary['critical']; ?></p></div>
        </div>
    </div>
</div>

<!-- ═══════════════ HERD RISK BANNER ═══════════════ -->
<div class="card-section mb-4" style="background:<?php echo $herdRiskBg; ?>;border:2px solid <?php echo $herdRiskColor; ?>">
    <div class="d-flex align-items-center gap-3">
        <div style="font-size:2.5rem"><?php echo $herdRiskEmoji; ?></div>
        <div>
            <h4 class="fw-bold mb-1" style="color:<?php echo $herdRiskColor; ?>">Overall Herd Disease Risk: <?php echo $herdRiskLevel; ?></h4>
            <p class="mb-0 small">
                <?php echo $totalAnimals; ?> active animals assessed.
                <?php if ($herdSummary['critical'] > 0): ?>
                    <strong class="text-danger"><?php echo $herdSummary['critical']; ?> critical</strong>,
                <?php endif; ?>
                <?php if ($herdSummary['high'] > 0): ?>
                    <strong class="text-danger"><?php echo $herdSummary['high']; ?> high</strong>,
                <?php endif; ?>
                <?php if ($herdSummary['moderate'] > 0): ?>
                    <strong class="text-warning"><?php echo $herdSummary['moderate']; ?> moderate</strong>,
                <?php endif; ?>
                <strong class="text-success"><?php echo $herdSummary['low']; ?> low</strong> risk.
                <?php if ($totalAnimals === 0): ?>No active animals found.<?php endif; ?>
            </p>
        </div>
        <div class="ms-auto d-none d-md-block text-end">
            <div style="font-size:2rem;font-weight:800;color:<?php echo $herdRiskColor; ?>">
                <?php
                $avgScore = $totalAnimals > 0 ? round(array_sum(array_column($animalRisks, 'risk_score')) / $totalAnimals) : 0;
                echo $avgScore;
                ?>/100
            </div>
            <small class="text-muted">Avg. Risk Score</small>
        </div>
    </div>
</div>

<!-- ═══════════════ PER-ANIMAL RISK TABLE (DESKTOP) ═══════════════ -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-table me-2"></i>Individual Animal Disease Risk Assessment</div>

    <?php if (empty($animalRisks)): ?>
    <div class="alert alert-info mb-0"><i class="fa fa-info-circle me-2"></i>No active animals found in the system.</div>
    <?php else: ?>

    <!-- Desktop Table -->
    <div class="d-none d-md-block table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tag</th>
                    <th>Name</th>
                    <th>Health Status</th>
                    <th>Risk Score</th>
                    <th>Risk Level</th>
                    <th>Predicted Diseases</th>
                    <th>Key Factors</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($animalRisks as $a):
                $hCls = match($a['health_status']) {
                    'Healthy'        => 'badge-healthy',
                    'Sick'           => 'badge-sick',
                    'Under Treatment'=> 'badge-treated',
                    default          => ''
                };
            ?>
            <tr>
                <td><span class="badge bg-success"><?php echo htmlspecialchars($a['tag_number']); ?></span></td>
                <td><?php echo htmlspecialchars($a['name'] ?? '-'); ?></td>
                <td><span class="badge-custom <?php echo $hCls; ?>"><?php echo $a['health_status']; ?></span></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="progress flex-grow-1" style="height:10px;min-width:60px">
                            <div class="progress-bar bg-<?php echo $a['risk_color']; ?>" style="width:<?php echo $a['risk_score']; ?>%"></div>
                        </div>
                        <span class="fw-bold" style="color:<?php echo $a['risk_hex']; ?>"><?php echo $a['risk_score']; ?></span>
                    </div>
                </td>
                <td><span class="badge bg-<?php echo $a['risk_color']; ?>"><?php echo $a['risk_level']; ?></span></td>
                <td>
                    <?php if (empty($a['diseases'])): ?>
                        <span class="text-success small"><i class="fa fa-check-circle me-1"></i>None predicted</span>
                    <?php else: ?>
                        <?php foreach ($a['diseases'] as $d): ?>
                        <span class="badge mb-1" style="background:<?php echo $diseaseGuide[$d]['color'] ?? '#6c757d'; ?>;font-size:.7rem">
                            <?php echo $d; ?>
                        </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (empty($a['risk_factors'])): ?>
                        <span class="text-success small">No risk factors</span>
                    <?php else: ?>
                        <?php foreach (array_slice($a['risk_factors'], 0, 2) as $f): ?>
                        <div style="font-size:.72rem;color:<?php echo $f['color']; ?>">
                            <i class="fa fa-dot-circle me-1"></i><?php echo htmlspecialchars($f['label']); ?>
                        </div>
                        <?php endforeach; ?>
                        <?php if (count($a['risk_factors']) > 2): ?>
                        <div style="font-size:.68rem;color:#888">+<?php echo count($a['risk_factors']) - 2; ?> more</div>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?php echo $root; ?>buffalo_profile.php?id=<?php echo $a['id']; ?>" class="btn btn-sm btn-outline-success" title="View Profile">
                        <i class="fa fa-eye"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Cards -->
    <div class="d-md-none">
        <?php foreach ($animalRisks as $a):
            $hCls = match($a['health_status']) {
                'Healthy'        => 'badge-healthy',
                'Sick'           => 'badge-sick',
                'Under Treatment'=> 'badge-treated',
                default          => ''
            };
        ?>
        <div class="border rounded p-3 mb-3" style="border-left:4px solid <?php echo $a['risk_hex']; ?> !important">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <span class="badge bg-success me-1"><?php echo htmlspecialchars($a['tag_number']); ?></span>
                    <strong><?php echo htmlspecialchars($a['name'] ?? ''); ?></strong>
                    <span class="badge-custom <?php echo $hCls; ?> ms-1"><?php echo $a['health_status']; ?></span>
                </div>
                <div class="text-end">
                    <span class="badge bg-<?php echo $a['risk_color']; ?>"><?php echo $a['risk_level']; ?></span>
                    <div style="font-size:.75rem;color:<?php echo $a['risk_hex']; ?>;font-weight:700"><?php echo $a['risk_score']; ?>/100</div>
                </div>
            </div>
            <?php if (!empty($a['diseases'])): ?>
            <div class="mb-1">
                <small class="text-muted">Predicted Diseases: </small>
                <?php foreach ($a['diseases'] as $d): ?>
                <span class="badge mb-1" style="background:<?php echo $diseaseGuide[$d]['color'] ?? '#6c757d'; ?>;font-size:.68rem"><?php echo $d; ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($a['risk_factors'])): ?>
            <div>
                <?php foreach ($a['risk_factors'] as $f): ?>
                <div style="font-size:.72rem;color:<?php echo $f['color']; ?>"><i class="fa fa-dot-circle me-1"></i><?php echo htmlspecialchars($f['label']); ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="mt-2">
                <a href="<?php echo $root; ?>buffalo_profile.php?id=<?php echo $a['id']; ?>" class="btn btn-sm btn-outline-success">
                    <i class="fa fa-eye me-1"></i>View Profile
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<!-- ═══════════════ DISEASE REFERENCE GUIDE ═══════════════ -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-book-medical me-2"></i>Disease Reference Guide</div>
    <p class="text-muted small mb-3">Common diseases affecting dairy buffaloes – symptoms and prevention guidelines.</p>
    <div class="row g-3">
        <?php foreach ($diseaseGuide as $diseaseName => $info): ?>
        <div class="col-md-6 col-lg-4">
            <div class="border rounded p-3 h-100" style="border-left:4px solid <?php echo $info['color']; ?> !important">
                <h6 class="fw-bold mb-2" style="color:<?php echo $info['color']; ?>">
                    <i class="fa <?php echo $info['icon']; ?> me-2"></i><?php echo $diseaseName; ?>
                </h6>
                <p class="small mb-2 text-muted"><?php echo $info['desc']; ?></p>
                <div class="mb-1">
                    <span class="fw-semibold small"><i class="fa fa-stethoscope me-1 text-danger"></i>Symptoms:</span>
                    <p class="small mb-1 text-muted"><?php echo $info['symptoms']; ?></p>
                </div>
                <div>
                    <span class="fw-semibold small"><i class="fa fa-shield-alt me-1 text-success"></i>Prevention:</span>
                    <p class="small mb-0 text-muted"><?php echo $info['prevention']; ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ═══════════════ QUICK ACTIONS ═══════════════ -->
<div class="card-section mb-3">
    <div class="section-title"><i class="fa fa-bolt me-2"></i>Quick Actions</div>
    <div class="row g-3">
        <div class="col-6 col-md-3">
            <a href="<?php echo $root; ?>modules/vaccinations.php?sf=Overdue" class="btn btn-outline-warning w-100">
                <i class="fa fa-syringe me-2"></i>Overdue Vaccines
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="<?php echo $root; ?>modules/health_records.php" class="btn btn-outline-danger w-100">
                <i class="fa fa-heartbeat me-2"></i>Health Records
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="<?php echo $root; ?>modules/herd_health_risk.php" class="btn btn-outline-info w-100">
                <i class="fa fa-chart-bar me-2"></i>Herd Health Risk
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="<?php echo $root; ?>modules/early_detection.php" class="btn btn-outline-secondary w-100">
                <i class="fa fa-search me-2"></i>Early Detection
            </a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
