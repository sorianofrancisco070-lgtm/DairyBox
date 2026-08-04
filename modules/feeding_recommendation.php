<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$root      = '../';
$pageTitle = 'Feeding Recommendation';
$db        = getDB();
$user      = currentUser();

// ── Feeding programs database (based on dairy buffalo standards) ──────────────
$feedingPrograms = [
    'high_producing' => [
        'label'       => 'High-Producing Lactating Buffalo',
        'condition'   => 'Avg milk ≥ 8 L/session',
        'color'       => '#d4edda',
        'icon'        => '🥛',
        'dmi'         => '3.0–3.5% of body weight',
        'roughage'    => '12–15 kg/day (napier grass, rice straw, corn silage)',
        'concentrate' => '4–5 kg/day (formulated dairy ration 16–18% CP)',
        'minerals'    => 'Free-choice mineral salt block; Ca:P ratio 2:1',
        'water'       => '60–80 liters/day',
        'supplements' => 'Bypass fat (100–150g/day) for energy; Vitamin A & D supplementation',
        'notes'       => 'Ensure energy-dense ration to support peak milk output. Split concentrate feeding into 2–3 meals to avoid acidosis. Monitor body condition score (BCS) monthly; target BCS 3.0–3.5.',
        'frequency'   => 'Feed twice daily (morning & evening milking)',
    ],
    'mid_producing' => [
        'label'       => 'Mid-Producing Lactating Buffalo',
        'condition'   => 'Avg milk 5–7.9 L/session',
        'color'       => '#cce5ff',
        'icon'        => '🐃',
        'dmi'         => '2.5–3.0% of body weight',
        'roughage'    => '10–12 kg/day (napier grass, legume hay, sweet potato vine)',
        'concentrate' => '2–3 kg/day (commercial dairy feed 14–16% CP)',
        'minerals'    => 'Mineral-vitamin premix in concentrate; salt lick',
        'water'       => '50–60 liters/day',
        'supplements' => 'Molasses-urea block for energy and protein supplementation',
        'notes'       => 'Balance roughage and concentrate to sustain production. Introduce quality legume forage to improve protein intake. Monitor milk yield weekly; adjust ration if yield drops.',
        'frequency'   => 'Feed twice daily',
    ],
    'low_producing' => [
        'label'       => 'Low-Producing / Early Lactation Buffalo',
        'condition'   => 'Avg milk < 5 L/session',
        'color'       => '#fff3cd',
        'icon'        => '⚠️',
        'dmi'         => '2.0–2.5% of body weight',
        'roughage'    => '8–10 kg/day (napier grass, rice straw)',
        'concentrate' => '1.5–2 kg/day (dairy starter feed 14% CP)',
        'minerals'    => 'Mineral supplement; dicalcium phosphate 50g/day',
        'water'       => '40–50 liters/day',
        'supplements' => 'Check for nutritional deficiencies; consider B-vitamin injection. Evaluate health status – low yield may indicate subclinical disease.',
        'notes'       => 'Prioritize health check before adjusting nutrition. Increase feed quality rather than quantity. Consider transition feeding if recently calved. Review body condition score – if BCS < 2.5, increase energy supplementation.',
        'frequency'   => 'Feed twice daily; monitor consumption closely',
    ],
    'dry_pregnant' => [
        'label'       => 'Dry / Pregnant Buffalo',
        'condition'   => 'Not in lactation; pregnancy confirmed',
        'color'       => '#e2d9f3',
        'icon'        => '🤰',
        'dmi'         => '1.8–2.2% of body weight',
        'roughage'    => '8–10 kg/day (good quality hay, napier grass)',
        'concentrate' => '1–1.5 kg/day (transition feed or dry cow ration)',
        'minerals'    => 'Ca:P 2:1 ratio strictly; avoid excess calcium pre-calving. Anionic salts last 3 weeks before calving.',
        'water'       => '35–45 liters/day',
        'supplements' => 'Vitamin E (500–1000 IU/day) + Selenium (1–3 mg/day) 3 weeks pre-calving to prevent retained placenta and milk fever.',
        'notes'       => 'Maintain BCS 3.0–3.5 at dry-off. Avoid overfeeding – prevents fat cow syndrome. Introduce transition diet 3 weeks before expected calving date. Monitor for signs of hypocalcemia post-calving.',
        'frequency'   => 'Once or twice daily; avoid abrupt feed changes',
    ],
    'calf' => [
        'label'       => 'Calves (0–6 months)',
        'condition'   => 'Young stock / replacement heifers',
        'color'       => '#d1ecf1',
        'icon'        => '🐄',
        'dmi'         => 'Start at 10% body weight in milk; reduce gradually',
        'roughage'    => 'Introduce starter hay at 2 weeks; good quality legume forage by 4 weeks',
        'concentrate' => 'Calf starter (18–20% CP) from week 2; 0.5–1 kg/day by week 6',
        'minerals'    => 'Milk replacer or whole milk for first 2–3 months; calf mineral mix',
        'water'       => 'Fresh clean water available from day 1 (2–4 liters/day initially)',
        'supplements' => 'Iron dextran injection at birth if needed. Vitamin A, D, E in colostrum is critical. Deworm at 3 months.',
        'notes'       => 'Ensure colostrum feeding within 2 hours of birth (3–4 liters). Wean gradually at 3–4 months when eating ≥1 kg concentrate/day. Weaning weight target: 90–100 kg. Prevent scours – clean feeding equipment daily.',
        'frequency'   => '2–3 feedings/day; reduce to twice daily after 3 months',
    ],
    'bull' => [
        'label'       => 'Breeding Bulls',
        'condition'   => 'Active breeding males',
        'color'       => '#f8d7da',
        'icon'        => '🐂',
        'dmi'         => '2.0–2.5% of body weight',
        'roughage'    => '8–10 kg/day (quality hay and napier grass)',
        'concentrate' => '2–3 kg/day (bull ration 12–14% CP); increase during breeding season',
        'minerals'    => 'Zinc (50–100 mg/day) for semen quality; general mineral-vitamin mix',
        'water'       => '40–50 liters/day',
        'supplements' => 'Vitamin E + Selenium for reproductive health. Avoid overfeeding – obese bulls have reduced libido.',
        'notes'       => 'Maintain BCS 2.5–3.5. Exercise regularly to maintain muscle tone and libido. Monitor semen quality annually. Increase concentrate by 0.5 kg/day 30 days before breeding season.',
        'frequency'   => 'Twice daily',
    ],
];

// ── Pull buffalo data from DB ────────────────────────────
$buffaloes = $db->query("
    SELECT b.*,
           AVG(mp.quantity_liters) as avg_session,
           COUNT(mp.id) as total_records,
           br.pregnancy_status,
           br.expected_calving
    FROM buffaloes b
    LEFT JOIN milk_production mp ON mp.buffalo_id=b.id
        AND MONTH(mp.record_date)=MONTH(CURDATE())
        AND YEAR(mp.record_date)=YEAR(CURDATE())
    LEFT JOIN (
        SELECT buffalo_id, pregnancy_status, expected_calving
        FROM breeding_records
        WHERE id IN (
            SELECT MAX(id) FROM breeding_records GROUP BY buffalo_id
        )
    ) br ON br.buffalo_id=b.id
    WHERE b.status='Active'
    GROUP BY b.id, br.pregnancy_status, br.expected_calving
    ORDER BY b.tag_number
")->fetchAll();

// ── Assign feeding program per buffalo ──────────────────
function assignFeedingProgram(array $buffalo): string {
    $sex     = $buffalo['sex'] ?? 'Female';
    $avgSess = (float)($buffalo['avg_session'] ?? 0);
    $pregSt  = $buffalo['pregnancy_status'] ?? '';
    $health  = $buffalo['health_status'] ?? 'Healthy';
    $records = (int)($buffalo['total_records'] ?? 0);

    if ($sex === 'Male') return 'bull';

    // Age-based (calves)
    if ($buffalo['date_of_birth']) {
        $ageMonths = (int)((time() - strtotime($buffalo['date_of_birth'])) / (86400 * 30));
        if ($ageMonths < 7) return 'calf';
    }

    // Dry / pregnant (not producing)
    if (($pregSt === 'Confirmed' || $pregSt === 'Delivered') && $records === 0) {
        return 'dry_pregnant';
    }

    // Production-based
    if ($records === 0 || $avgSess === 0) {
        // No production data – assume dry
        return $pregSt === 'Confirmed' ? 'dry_pregnant' : 'low_producing';
    }
    if ($avgSess >= 8) return 'high_producing';
    if ($avgSess >= 5) return 'mid_producing';
    return 'low_producing';
}

// ── Summary counts ───────────────────────────────────────
$programCounts = array_fill_keys(array_keys($feedingPrograms), 0);
foreach ($buffaloes as $b) {
    $prog = assignFeedingProgram($b);
    $programCounts[$prog]++;
}

// ── Selected buffalo detail ──────────────────────────────
$selectedId  = (int)($_GET['buffalo_id'] ?? 0);
$selectedBuf = null;
if ($selectedId) {
    foreach ($buffaloes as $b) {
        if ($b['id'] === $selectedId) { $selectedBuf = $b; break; }
    }
}

include '../includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h5 class="fw-bold text-success mb-0"><i class="fa fa-seedling me-2"></i>Feeding Recommendation</h5>
        <small class="text-muted">Science-based feeding programs tailored to each buffalo's production stage and needs</small>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-2 mb-4">
    <?php foreach ($feedingPrograms as $key => $prog): ?>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card-section text-center py-2" style="background:<?= $prog['color'] ?>;cursor:pointer"
             onclick="document.getElementById('prog-<?= $key ?>').scrollIntoView({behavior:'smooth'})">
            <div style="font-size:1.6rem"><?= $prog['icon'] ?></div>
            <div class="fw-bold" style="font-size:1.4rem"><?= $programCounts[$key] ?></div>
            <div style="font-size:.68rem;color:#555;line-height:1.2"><?= $prog['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Individual Buffalo Quick Look -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-search me-2"></i>Look Up Individual Buffalo</div>
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <select class="form-select form-select-sm" onchange="location='?buffalo_id='+this.value">
                <option value="">-- Select a Buffalo --</option>
                <?php foreach ($buffaloes as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $selectedId===$b['id']?'selected':'' ?>>
                    <?= htmlspecialchars($b['tag_number']) ?> – <?= htmlspecialchars($b['name']??'') ?>
                    (<?= $b['sex'] ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($selectedBuf): ?>
        <div class="col-md-8">
            <?php
            $spKey  = assignFeedingProgram($selectedBuf);
            $spProg = $feedingPrograms[$spKey];
            ?>
            <div class="p-3 rounded" style="background:<?= $spProg['color'] ?>;border:1px solid rgba(0,0,0,.08)">
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span style="font-size:1.5rem"><?= $spProg['icon'] ?></span>
                    <div>
                        <strong><?= htmlspecialchars($selectedBuf['tag_number']) ?>
                            <?= $selectedBuf['name'] ? '– '.htmlspecialchars($selectedBuf['name']) : '' ?>
                        </strong><br>
                        <span class="badge bg-success"><?= $spProg['label'] ?></span>
                    </div>
                    <div class="ms-auto text-end">
                        <small class="text-muted">Avg/session:</small><br>
                        <strong class="text-success"><?= $selectedBuf['avg_session'] ? number_format($selectedBuf['avg_session'],1).' L' : 'No data' ?></strong>
                    </div>
                </div>
                <div class="row g-2" style="font-size:.82rem">
                    <div class="col-6"><strong>🌾 Roughage:</strong><br><?= $spProg['roughage'] ?></div>
                    <div class="col-6"><strong>🌽 Concentrate:</strong><br><?= $spProg['concentrate'] ?></div>
                    <div class="col-6"><strong>💧 Water:</strong><br><?= $spProg['water'] ?></div>
                    <div class="col-6"><strong>⏰ Frequency:</strong><br><?= $spProg['frequency'] ?></div>
                    <div class="col-12"><strong>📝 Notes:</strong><br><?= $spProg['notes'] ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- All Buffalo by Program -->
<?php foreach ($feedingPrograms as $key => $prog):
    $inProg = array_filter($buffaloes, fn($b) => assignFeedingProgram($b) === $key);
    if (empty($inProg)) continue;
?>
<div class="card-section mb-3" id="prog-<?= $key ?>">
    <div class="section-title" style="border-bottom-color:<?= $prog['color'] ?>">
        <span style="font-size:1.2rem"><?= $prog['icon'] ?></span>
        <span class="ms-2"><?= $prog['label'] ?></span>
        <span class="badge ms-2" style="background:<?= $prog['color'] ?>;color:#333"><?= count($inProg) ?> animal(s)</span>
    </div>

    <!-- Feeding Program Details -->
    <div class="p-3 rounded mb-3" style="background:<?= $prog['color'] ?>;font-size:.85rem">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="fw-bold mb-1">🌾 Roughage / Forage</div>
                <div><?= $prog['roughage'] ?></div>
            </div>
            <div class="col-md-4">
                <div class="fw-bold mb-1">🌽 Concentrate Feed</div>
                <div><?= $prog['concentrate'] ?></div>
            </div>
            <div class="col-md-4">
                <div class="fw-bold mb-1">🪨 Minerals & Supplements</div>
                <div><?= $prog['minerals'] ?></div>
            </div>
            <div class="col-md-4">
                <div class="fw-bold mb-1">💧 Water Requirement</div>
                <div><?= $prog['water'] ?></div>
            </div>
            <div class="col-md-4">
                <div class="fw-bold mb-1">💊 Additional Supplements</div>
                <div><?= $prog['supplements'] ?></div>
            </div>
            <div class="col-md-4">
                <div class="fw-bold mb-1">⏰ Feeding Frequency</div>
                <div><?= $prog['frequency'] ?></div>
            </div>
            <div class="col-12">
                <div class="fw-bold mb-1">📋 Management Notes</div>
                <div><?= $prog['notes'] ?></div>
            </div>
        </div>
    </div>

    <!-- Buffaloes in this program -->
    <!-- Desktop Table -->
    <div class="d-none d-md-block table-responsive">
    <table class="table table-sm table-hover mb-0">
        <thead>
            <tr><th>Tag</th><th>Name</th><th>Breed</th><th>Sex</th><th>Weight</th><th>Avg/Session</th><th>Health</th><th>Action</th></tr>
        </thead>
        <tbody>
        <?php foreach ($inProg as $b):
            $hCls = match($b['health_status']) {'Healthy'=>'badge-healthy','Sick'=>'badge-sick','Under Treatment'=>'badge-treated',default=>''};
        ?>
        <tr>
            <td><span class="badge bg-success"><?= htmlspecialchars($b['tag_number']) ?></span></td>
            <td><?= htmlspecialchars($b['name']??'-') ?></td>
            <td><?= htmlspecialchars($b['breed']??'-') ?></td>
            <td><?= $b['sex'] ?></td>
            <td><?= $b['weight_kg'] ? $b['weight_kg'].' kg' : '-' ?></td>
            <td>
                <?php if ($b['avg_session']): ?>
                <strong class="text-success"><?= number_format($b['avg_session'],1) ?> L</strong>
                <?php else: ?><span class="text-muted">No data</span><?php endif; ?>
            </td>
            <td><span class="badge-custom <?= $hCls ?>"><?= $b['health_status'] ?></span></td>
            <td>
                <a href="?buffalo_id=<?= $b['id'] ?>" class="btn btn-xs btn-outline-success" style="padding:.2rem .5rem;font-size:.72rem">
                    <i class="fa fa-seedling me-1"></i>Detail
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- Mobile Cards -->
    <div class="d-md-none">
        <?php foreach ($inProg as $b):
            $hCls = match($b['health_status']) {'Healthy'=>'badge-healthy','Sick'=>'badge-sick','Under Treatment'=>'badge-treated',default=>''};
        ?>
        <div class="d-flex align-items-center gap-2 p-2 mb-2 rounded border" style="background:#fafafa">
            <div class="flex-grow-1">
                <span class="badge bg-success"><?= htmlspecialchars($b['tag_number']) ?></span>
                <strong class="ms-1"><?= htmlspecialchars($b['name']??'') ?></strong>
                <span class="badge-custom <?= $hCls ?> ms-1"><?= $b['health_status'] ?></span><br>
                <small class="text-muted"><?= htmlspecialchars($b['breed']??'-') ?> | <?= $b['sex'] ?>
                    <?= $b['avg_session'] ? ' | '.number_format($b['avg_session'],1).' L/session' : '' ?>
                </small>
            </div>
            <a href="?buffalo_id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-success">
                <i class="fa fa-seedling"></i>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<!-- General Feeding Guidelines -->
<div class="card-section mt-2">
    <div class="section-title"><i class="fa fa-book-open me-2 text-success"></i>General Feeding Guidelines for Dairy Buffalo</div>
    <div class="row g-3" style="font-size:.85rem">
        <div class="col-md-6">
            <div class="p-3 rounded" style="background:#f8fff9;border:1px solid #d4edda">
                <div class="fw-bold text-success mb-2">✅ Best Practices</div>
                <ul class="mb-0 ps-3">
                    <li>Feed at consistent times daily to reduce stress</li>
                    <li>Ensure clean, fresh water is always available</li>
                    <li>Introduce new feeds gradually over 7–10 days</li>
                    <li>Monitor body condition score (BCS) monthly</li>
                    <li>Weigh animals regularly to adjust ration accurately</li>
                    <li>Store feeds properly to prevent mold and spoilage</li>
                    <li>Provide shade and good ventilation during feeding</li>
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <div class="p-3 rounded" style="background:#fff5f5;border:1px solid #f8d7da">
                <div class="fw-bold text-danger mb-2">⚠️ Common Feeding Mistakes to Avoid</div>
                <ul class="mb-0 ps-3">
                    <li>Sudden changes in feed type or amount</li>
                    <li>Overfeeding concentrate — causes acidosis and bloat</li>
                    <li>Feeding moldy or spoiled roughage</li>
                    <li>Insufficient roughage in lactating animals</li>
                    <li>Neglecting mineral supplementation</li>
                    <li>Feeding the same ration regardless of production stage</li>
                    <li>Ignoring water quality — check pH and cleanliness</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
