<?php
// Show errors so blank page becomes a visible error
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$root      = '../';
$pageTitle = 'Milk Yield Prediction';
$db        = getDB();
$user      = currentUser();

// ════════════════════════════════════════════════════════
// HELPER: Linear Regression slope & intercept
// ════════════════════════════════════════════════════════
if (!function_exists('linearRegression')) {
function linearRegression(array $y): array {
    $n = count($y);
    if ($n < 2) return ['slope' => 0, 'intercept' => $n === 1 ? $y[0] : 0];
    $x     = range(0, $n - 1);
    $sumX  = array_sum($x);
    $sumY  = array_sum($y);
    $sumXY = 0;
    $sumX2 = 0;
    for ($i = 0; $i < $n; $i++) {
        $sumXY += $x[$i] * $y[$i];
        $sumX2 += $x[$i] * $x[$i];
    }
    $denom = ($n * $sumX2 - $sumX * $sumX);
    if ($denom == 0) return ['slope' => 0, 'intercept' => $n > 0 ? $sumY / $n : 0];
    $slope     = ($n * $sumXY - $sumX * $sumY) / $denom;
    $intercept = ($sumY - $slope * $sumX) / $n;
    return ['slope' => $slope, 'intercept' => $intercept];
}
}

// ════════════════════════════════════════════════════════
// 1. FETCH LAST 90 DAYS HERD PRODUCTION (by date)
// ════════════════════════════════════════════════════════
$fatalError      = null;
$dailyProduction = [];
try {
    $stmt = $db->query("
        SELECT record_date, SUM(quantity_liters) AS total
        FROM milk_production
        WHERE record_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        GROUP BY record_date
        ORDER BY record_date ASC
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dailyProduction[$row['record_date']] = (float)$row['total'];
    }
} catch (Exception $e) {
    $fatalError = $e->getMessage();
}

// Fill any missing days with 0 for continuity
$allDates = [];
for ($i = 89; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $allDates[$d] = $dailyProduction[$d] ?? 0;
}
$dateKeys  = array_keys($allDates);
$dateVals  = array_values($allDates);

// ════════════════════════════════════════════════════════
// 2. 7-DAY MOVING AVERAGE
// ════════════════════════════════════════════════════════
$movingAvg7 = [];
for ($i = 0; $i < count($dateVals); $i++) {
    $start = max(0, $i - 6);
    $window = array_slice($dateVals, $start, $i - $start + 1);
    $nonZero = array_filter($window, fn($v) => $v > 0);
    $movingAvg7[$i] = count($nonZero) > 0 ? round(array_sum($nonZero) / count($nonZero), 2) : 0;
}

// ════════════════════════════════════════════════════════
// 3. 14-DAY TREND (linear regression on last 14 recorded days)
// ════════════════════════════════════════════════════════
$last14Vals = array_slice($dateVals, -14);
$last14Nonzero = array_values(array_filter($last14Vals, fn($v) => $v > 0));
$trend14 = linearRegression(count($last14Nonzero) >= 2 ? $last14Nonzero : $last14Vals);

// Current baseline: average of last 7 non-zero days
$last7Vals   = array_slice($dateVals, -7);
$last7Nonzero = array_values(array_filter($last7Vals, fn($v) => $v > 0));
$baseline    = count($last7Nonzero) > 0 ? array_sum($last7Nonzero) / count($last7Nonzero) : 0;

// ════════════════════════════════════════════════════════
// 4. SEASONAL ADJUSTMENT (simple month-based factor)
// ════════════════════════════════════════════════════════
$seasonalFactors = [
    1 => 0.97, 2 => 0.96, 3 => 0.98, 4 => 1.00,
    5 => 1.02, 6 => 1.00, 7 => 0.98, 8 => 0.97,
    9 => 0.99, 10 => 1.01, 11 => 1.02, 12 => 1.00,
];

// ════════════════════════════════════════════════════════
// 5. PREDICT NEXT 7 DAYS
// ════════════════════════════════════════════════════════
$next7Days = [];
$baseOffset = count($last14Nonzero); // position in regression
for ($i = 1; $i <= 7; $i++) {
    $dateStr     = date('Y-m-d', strtotime("+{$i} days"));
    $month       = (int)date('m', strtotime("+{$i} days"));
    $seasonal    = $seasonalFactors[$month] ?? 1.0;
    $predicted   = max(0, ($trend14['slope'] * ($baseOffset + $i) + $trend14['intercept']) * $seasonal);
    // Clamp with baseline +-40% to avoid wild extrapolation
    if ($baseline > 0) {
        $predicted = max($baseline * 0.60, min($baseline * 1.40, $predicted));
    }
    $next7Days[] = ['date' => $dateStr, 'predicted' => round($predicted, 2)];
}

// ════════════════════════════════════════════════════════
// 6. PREDICT NEXT 30 DAYS
// ════════════════════════════════════════════════════════
$next30Days = [];
for ($i = 1; $i <= 30; $i++) {
    $dateStr   = date('Y-m-d', strtotime("+{$i} days"));
    $month     = (int)date('m', strtotime("+{$i} days"));
    $seasonal  = $seasonalFactors[$month] ?? 1.0;
    $predicted = max(0, ($trend14['slope'] * ($baseOffset + $i) + $trend14['intercept']) * $seasonal);
    if ($baseline > 0) {
        $predicted = max($baseline * 0.55, min($baseline * 1.50, $predicted));
    }
    $next30Days[] = ['date' => $dateStr, 'predicted' => round($predicted, 2)];
}

// ════════════════════════════════════════════════════════
// 7. PREDICTION CONFIDENCE (based on variance of last 14 days)
// ════════════════════════════════════════════════════════
$confidence = 50; // default
if (count($last14Nonzero) >= 4) {
    $mean = array_sum($last14Nonzero) / count($last14Nonzero);
    if ($mean > 0) {
        $variance = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $last14Nonzero)) / count($last14Nonzero);
        $cv = sqrt($variance) / $mean; // coefficient of variation
        $confidence = (int)max(30, min(95, round((1 - $cv) * 100)));
    }
}

// ════════════════════════════════════════════════════════
// 8. TODAY PREDICTED VS ACTUAL
// ════════════════════════════════════════════════════════
$todayActual = $dailyProduction[date('Y-m-d')] ?? null;
$todayPredicted = $baseline > 0 ? round($baseline * ($seasonalFactors[(int)date('m')] ?? 1.0), 2) : 0;

// ════════════════════════════════════════════════════════
// 9. ACCURACY CHECK: last week predicted vs actual
// ════════════════════════════════════════════════════════
// We estimate last week prediction = 7-day moving average from 2 weeks ago applied forward
$accuracyData = [];
for ($i = 7; $i >= 1; $i--) {
    $checkDate = date('Y-m-d', strtotime("-{$i} days"));
    $actual    = $dailyProduction[$checkDate] ?? null;

    // Predicted: moving avg of the 7 days before checkDate
    $predictedSum = 0;
    $predictedCnt = 0;
    for ($j = 8; $j <= 14; $j++) {
        $prevDate  = date('Y-m-d', strtotime("-{$i} days - {$j} days"));
        $prevDate2 = date('Y-m-d', strtotime("-" . ($i + $j) . " days"));
        if (isset($dailyProduction[$prevDate2]) && $dailyProduction[$prevDate2] > 0) {
            $predictedSum += $dailyProduction[$prevDate2];
            $predictedCnt++;
        }
    }
    $estPred = $predictedCnt > 0 ? round($predictedSum / $predictedCnt, 2) : null;
    $accuracyData[] = [
        'date'      => $checkDate,
        'actual'    => $actual,
        'predicted' => $estPred,
        'diff'      => ($actual !== null && $estPred !== null) ? round($actual - $estPred, 2) : null,
        'pct_err'   => ($actual !== null && $estPred !== null && $estPred > 0)
                        ? round(abs($actual - $estPred) / $estPred * 100, 1) : null,
    ];
}

// ════════════════════════════════════════════════════════
// 10. PER-BUFFALO PREDICTIONS (last 30 days + next week)
// ════════════════════════════════════════════════════════
$bufPredictions = [];
try {
    $bStmt = $db->query("SELECT id, tag_number, name, health_status FROM buffaloes WHERE status='Active' AND sex='Female' ORDER BY tag_number ASC");
    $females = $bStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($females as $buf) {
        $bid = $buf['id'];

        // Last 30 days daily totals
        $ps = $db->prepare("
            SELECT record_date, SUM(quantity_liters) AS total
            FROM milk_production
            WHERE buffalo_id = ? AND record_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY record_date
            ORDER BY record_date ASC
        ");
        $ps->execute([$bid]);
        $bRows = $ps->fetchAll(PDO::FETCH_ASSOC);

        $bVals   = array_map(fn($r) => (float)$r['total'], $bRows);
        $bNonZero = array_values(array_filter($bVals, fn($v) => $v > 0));

        if (empty($bNonZero)) {
            $bufPredictions[] = array_merge($buf, [
                'avg30'        => 0,
                'predicted_wk' => 0,
                'trend'        => 'No data',
                'lactation'    => 'Unknown',
                'days_data'    => 0,
            ]);
            continue;
        }

        $bAvg      = round(array_sum($bNonZero) / count($bNonZero), 2);
        $bTrend    = linearRegression($bNonZero);
        $bPredNext = max(0, $bTrend['slope'] * (count($bNonZero) + 3) + $bTrend['intercept']);
        // Clamp
        $bPredNext = max($bAvg * 0.60, min($bAvg * 1.40, $bPredNext));
        $bPredNext = round($bPredNext, 2);

        // Trend direction
        if ($bTrend['slope'] > 0.05) $trendDir = 'Rising';
        elseif ($bTrend['slope'] < -0.05) $trendDir = 'Declining';
        else $trendDir = 'Stable';

        // Lactation stage estimate from first milk record
        $firstMilk = $db->prepare("SELECT MIN(record_date) FROM milk_production WHERE buffalo_id=?");
        $firstMilk->execute([$bid]);
        $firstDate = $firstMilk->fetchColumn();
        $lacDays   = $firstDate ? (int)(new DateTime())->diff(new DateTime($firstDate))->days : null;
        if ($lacDays === null) $lacStage = 'Unknown';
        elseif ($lacDays <= 90)  $lacStage = 'Early Lactation';
        elseif ($lacDays <= 200) $lacStage = 'Mid Lactation';
        elseif ($lacDays <= 305) $lacStage = 'Late Lactation';
        else                     $lacStage = 'Extended / Dry';

        $bufPredictions[] = array_merge($buf, [
            'avg30'        => $bAvg,
            'predicted_wk' => $bPredNext,
            'trend'        => $trendDir,
            'lactation'    => $lacStage,
            'days_data'    => count($bNonZero),
        ]);
    }
} catch (Exception $e) {
    $bufPredictions = [];
}

// ════════════════════════════════════════════════════════
// DATA FOR CHARTS
// ════════════════════════════════════════════════════════
// Last 30 days actuals for chart
$chart30Labels = array_slice($dateKeys, -30);
$chart30Vals   = array_slice($dateVals, -30);
$chartMov7     = array_slice($movingAvg7, -30);

// Forecast labels & data
$forecastLabels = array_column($next7Days, 'date');
$forecastVals   = array_column($next7Days, 'predicted');

include '../includes/header.php';
?>

<?php if ($fatalError): ?>
<div class="alert alert-danger">
    <i class="fa fa-exclamation-circle me-2"></i>
    <strong>Error loading prediction data:</strong> <?= htmlspecialchars($fatalError) ?>
</div>
<?php endif; ?>

<?php if (empty($dailyProduction) && !$fatalError): ?>
<div class="card-section text-center py-5">
    <div style="font-size:3rem">📊</div>
    <h5 class="text-muted mt-2">No Production Data Available</h5>
    <p class="text-muted small">Start recording milk production to enable yield predictions.</p>
    <a href="milk_production.php?action=add" class="btn btn-success"><i class="fa fa-plus me-1"></i>Record Milk Production</a>
</div>
<?php include '../includes/footer.php'; ?>
<?php
exit;
endif;
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#cce5ff"><i class="fa fa-tint text-primary" style="font-size:1.5rem"></i></div>
            <div>
                <p class="stat-label">Today Predicted (L)</p>
                <p class="stat-value text-primary"><?php echo number_format($todayPredicted, 1); ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#d4edda"><i class="fa fa-check-circle text-success" style="font-size:1.5rem"></i></div>
            <div>
                <p class="stat-label">Today Actual (L)</p>
                <p class="stat-value text-success"><?php echo $todayActual !== null ? number_format($todayActual, 1) : 'Not recorded'; ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff3cd"><i class="fa fa-chart-line text-warning" style="font-size:1.5rem"></i></div>
            <div>
                <p class="stat-label">7-Day Baseline (L)</p>
                <p class="stat-value text-warning"><?php echo number_format($baseline, 1); ?></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:#e2d9f3"><i class="fa fa-bullseye text-secondary" style="font-size:1.5rem"></i></div>
            <div>
                <p class="stat-label">Prediction Confidence</p>
                <p class="stat-value"><?php echo $confidence; ?>%</p>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════ TODAY COMPARISON & TREND SUMMARY ═══════════════ -->
<div class="row g-3 mb-4">
    <div class="col-md-5">
        <div class="card-section h-100">
            <div class="section-title"><i class="fa fa-balance-scale me-2"></i>Today: Predicted vs Actual</div>
            <?php if ($todayActual !== null): ?>
                <?php
                $diff   = $todayActual - $todayPredicted;
                $diffPct = $todayPredicted > 0 ? round(abs($diff) / $todayPredicted * 100, 1) : 0;
                $diffColor = $diff >= 0 ? '#28a745' : '#dc3545';
                $diffIcon  = $diff >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                ?>
                <div class="row g-2 text-center mb-3">
                    <div class="col-6">
                        <div class="border rounded p-2">
                            <div class="fw-bold text-primary" style="font-size:1.6rem"><?php echo number_format($todayPredicted, 1); ?>L</div>
                            <div style="font-size:.75rem;color:#888">Predicted</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="border rounded p-2">
                            <div class="fw-bold text-success" style="font-size:1.6rem"><?php echo number_format($todayActual, 1); ?>L</div>
                            <div style="font-size:.75rem;color:#888">Actual</div>
                        </div>
                    </div>
                </div>
                <div class="text-center">
                    <span style="font-size:1.1rem;font-weight:600;color:<?php echo $diffColor; ?>">
                        <i class="fa <?php echo $diffIcon; ?> me-1"></i>
                        <?php echo abs($diff) > 0 ? number_format(abs($diff), 1) . 'L (' . $diffPct . '%)' : 'On target'; ?>
                    </span>
                    <div style="font-size:.75rem;color:#888"><?php echo $diff >= 0 ? 'Above prediction' : 'Below prediction'; ?></div>
                </div>
            <?php else: ?>
                <div class="text-center py-3">
                    <div style="font-size:2rem" class="text-warning">📊</div>
                    <div class="fw-bold" style="font-size:1.4rem;color:#17a2b8"><?php echo number_format($todayPredicted, 1); ?>L</div>
                    <div class="text-muted small">Predicted for today</div>
                    <div class="alert alert-info mt-2 mb-0 small">No milk records entered yet for today.</div>
                </div>
            <?php endif; ?>

            <hr class="my-2">
            <div class="d-flex justify-content-between small">
                <span class="text-muted">Trend (14-day slope):</span>
                <span class="fw-bold <?php echo $trend14['slope'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <i class="fa fa-<?php echo $trend14['slope'] >= 0 ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                    <?php echo number_format($trend14['slope'], 3); ?> L/day
                </span>
            </div>
            <div class="d-flex justify-content-between small mt-1">
                <span class="text-muted">Confidence:</span>
                <span>
                    <div class="progress d-inline-flex" style="height:8px;width:80px;vertical-align:middle">
                        <div class="progress-bar <?php echo $confidence >= 70 ? 'bg-success' : ($confidence >= 50 ? 'bg-warning' : 'bg-danger'); ?>" style="width:<?php echo $confidence; ?>%"></div>
                    </div>
                    <strong class="ms-1"><?php echo $confidence; ?>%</strong>
                </span>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card-section h-100">
            <div class="section-title"><i class="fa fa-chart-line me-2"></i>Next 7-Day Forecast</div>
            <canvas id="forecastChart" height="130"></canvas>
        </div>
    </div>
</div>

<!-- ═══════════════ HISTORICAL CHART ═══════════════ -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-chart-area me-2"></i>Last 30 Days Production with 7-Day Moving Average</div>
    <canvas id="historyChart" height="80"></canvas>
</div>

<!-- ═══════════════ NEXT 30-DAY FORECAST TABLE ═══════════════ -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-calendar-alt me-2"></i>30-Day Production Forecast</div>
    <p class="text-muted small mb-3">
        Forecast uses weighted 14-day trend + seasonal adjustment. Confidence: <strong><?php echo $confidence; ?>%</strong>.
        <?php if ($baseline == 0): ?>
        <span class="text-warning"><i class="fa fa-exclamation-triangle me-1"></i>Insufficient historical data for reliable predictions.</span>
        <?php endif; ?>
    </p>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Day</th>
                    <th>Predicted Yield (L)</th>
                    <th>vs Baseline</th>
                    <th>Confidence Range</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($next30Days as $i => $day):
                $diff30  = $baseline > 0 ? round($day['predicted'] - $baseline, 2) : 0;
                $diffCls = $diff30 >= 0 ? 'text-success' : 'text-danger';
                $low     = round($day['predicted'] * (1 - (100 - $confidence) / 200), 1);
                $high    = round($day['predicted'] * (1 + (100 - $confidence) / 200), 1);
            ?>
            <tr <?php echo $i < 7 ? 'class="table-info"' : ''; ?>>
                <td><?php echo $i + 1; ?></td>
                <td><?php echo date('D, M j', strtotime($day['date'])); ?></td>
                <td><small class="text-muted"><?php echo date('Y-m-d', strtotime($day['date'])); ?></small></td>
                <td><strong><?php echo number_format($day['predicted'], 2); ?>L</strong></td>
                <td>
                    <?php if ($baseline > 0): ?>
                    <span class="<?php echo $diffCls; ?> small fw-bold">
                        <?php echo $diff30 >= 0 ? '+' : ''; echo number_format($diff30, 2); ?>L
                    </span>
                    <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                </td>
                <td><small class="text-muted"><?php echo number_format($low, 1); ?>–<?php echo number_format($high, 1); ?>L</small></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($next30Days)): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">No forecast available.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="mt-2 small text-info"><i class="fa fa-info-circle me-1"></i>Rows highlighted in blue = next 7 days.</div>
</div>

<!-- ═══════════════ PER-BUFFALO PREDICTIONS ═══════════════ -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-paw me-2"></i>Per-Buffalo Prediction (Next Week)</div>

    <?php if (empty($bufPredictions)): ?>
    <div class="alert alert-info mb-0"><i class="fa fa-info-circle me-2"></i>No active female buffaloes found.</div>
    <?php else: ?>

    <!-- Desktop Table -->
    <div class="d-none d-md-block table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tag</th>
                    <th>Name</th>
                    <th>Health</th>
                    <th>30-Day Avg (L/day)</th>
                    <th>Predicted Next Week (L/day)</th>
                    <th>Trend</th>
                    <th>Lactation Stage</th>
                    <th>Data Days</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bufPredictions as $bp):
                $hCls = match($bp['health_status']) {
                    'Healthy' => 'badge-healthy', 'Sick' => 'badge-sick',
                    'Under Treatment' => 'badge-treated', default => ''
                };
                $trendCls  = $bp['trend'] === 'Rising' ? 'text-success' : ($bp['trend'] === 'Declining' ? 'text-danger' : 'text-secondary');
                $trendIcon = $bp['trend'] === 'Rising' ? 'fa-arrow-up' : ($bp['trend'] === 'Declining' ? 'fa-arrow-down' : 'fa-minus');
            ?>
            <tr>
                <td><span class="badge bg-success"><?php echo htmlspecialchars($bp['tag_number']); ?></span></td>
                <td><?php echo htmlspecialchars($bp['name'] ?? '-'); ?></td>
                <td><span class="badge-custom <?php echo $hCls; ?>"><?php echo $bp['health_status']; ?></span></td>
                <td><?php echo $bp['avg30'] > 0 ? number_format($bp['avg30'], 2) : '<span class="text-muted">No data</span>'; ?></td>
                <td>
                    <?php if ($bp['predicted_wk'] > 0): ?>
                    <strong class="text-primary"><?php echo number_format($bp['predicted_wk'], 2); ?>L</strong>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td>
                    <span class="<?php echo $trendCls; ?> small fw-bold">
                        <i class="fa <?php echo $trendIcon; ?> me-1"></i><?php echo $bp['trend']; ?>
                    </span>
                </td>
                <td><span class="badge bg-secondary"><?php echo $bp['lactation']; ?></span></td>
                <td><small class="text-muted"><?php echo $bp['days_data']; ?></small></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Cards -->
    <div class="d-md-none">
        <?php foreach ($bufPredictions as $bp):
            $hCls      = match($bp['health_status']) {'Healthy'=>'badge-healthy','Sick'=>'badge-sick','Under Treatment'=>'badge-treated',default=>''};
            $trendCls  = $bp['trend']==='Rising'?'text-success':($bp['trend']==='Declining'?'text-danger':'text-secondary');
            $trendIcon = $bp['trend']==='Rising'?'fa-arrow-up':($bp['trend']==='Declining'?'fa-arrow-down':'fa-minus');
        ?>
        <div class="border rounded p-3 mb-2">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="badge bg-success me-1"><?php echo htmlspecialchars($bp['tag_number']); ?></span>
                    <strong><?php echo htmlspecialchars($bp['name'] ?? ''); ?></strong>
                    <span class="badge-custom <?php echo $hCls; ?> ms-1"><?php echo $bp['health_status']; ?></span>
                </div>
                <div class="text-end">
                    <?php if ($bp['predicted_wk'] > 0): ?>
                    <strong class="text-primary"><?php echo number_format($bp['predicted_wk'], 2); ?>L</strong>
                    <div style="font-size:.7rem;color:#888">predicted/day</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mt-1 small">
                <span class="text-muted">30-day avg: </span><strong><?php echo $bp['avg30'] > 0 ? number_format($bp['avg30'], 2).'L' : 'No data'; ?></strong>
                <span class="ms-2 <?php echo $trendCls; ?>"><i class="fa <?php echo $trendIcon; ?> me-1"></i><?php echo $bp['trend']; ?></span>
            </div>
            <div class="mt-1 small">
                <span class="badge bg-secondary"><?php echo $bp['lactation']; ?></span>
                <span class="text-muted ms-2"><?php echo $bp['days_data']; ?> days data</span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<!-- ═══════════════ FACTORS AFFECTING PREDICTION ═══════════════ -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-info-circle me-2"></i>Factors Affecting Prediction</div>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="p-3 rounded border-start border-danger border-3 bg-light h-100">
                <strong class="text-danger"><i class="fa fa-heartbeat me-2"></i>Health Status</strong>
                <p class="small mb-0 mt-1">Sick or injured animals typically show 15–40% reduction in milk yield. Animals under treatment may have partial recovery. Health status is the strongest predictor of production anomalies.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 rounded border-start border-info border-3 bg-light h-100">
                <strong class="text-info"><i class="fa fa-baby me-2"></i>Lactation Stage</strong>
                <p class="small mb-0 mt-1">Buffalo yield follows a lactation curve: peaks at 4–8 weeks post-calving, then gradually declines. Early lactation animals may show rapid increases while late lactation animals will naturally decrease.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 rounded border-start border-warning border-3 bg-light h-100">
                <strong class="text-warning"><i class="fa fa-sun me-2"></i>Seasonal Factors</strong>
                <p class="small mb-0 mt-1">Milk yield is slightly higher in cooler months (Oct–Dec, May) and lower during peak heat (Jan–Feb, Aug). Seasonal adjustment factor applied ranges from 0.96 to 1.02 to account for climate impact.</p>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════ PREDICTION ACCURACY ═══════════════ -->
<div class="card-section mb-4">
    <div class="section-title"><i class="fa fa-bullseye me-2"></i>Prediction Accuracy – Last 7 Days</div>
    <p class="text-muted small mb-3">Compares estimated predictions (based on prior moving average) against actual recorded production.</p>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr><th>Date</th><th>Estimated Prediction (L)</th><th>Actual (L)</th><th>Difference (L)</th><th>Error %</th></tr>
            </thead>
            <tbody>
            <?php foreach ($accuracyData as $ad): ?>
            <tr>
                <td><?php echo date('D, M j', strtotime($ad['date'])); ?></td>
                <td><?php echo $ad['predicted'] !== null ? number_format($ad['predicted'], 2) : '<span class="text-muted">—</span>'; ?></td>
                <td><?php echo $ad['actual'] !== null ? number_format($ad['actual'], 2) : '<span class="text-muted">Not recorded</span>'; ?></td>
                <td>
                    <?php if ($ad['diff'] !== null): ?>
                    <span class="<?php echo $ad['diff'] >= 0 ? 'text-success' : 'text-danger'; ?> fw-bold small">
                        <?php echo $ad['diff'] >= 0 ? '+' : ''; echo number_format($ad['diff'], 2); ?>L
                    </span>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td>
                    <?php if ($ad['pct_err'] !== null): ?>
                    <span class="badge <?php echo $ad['pct_err'] <= 10 ? 'bg-success' : ($ad['pct_err'] <= 20 ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                        <?php echo $ad['pct_err']; ?>%
                    </span>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════════════ CHART.JS SCRIPTS ═══════════════ -->
<script>
const chart30Labels   = <?php echo json_encode($chart30Labels); ?>;
const chart30Vals     = <?php echo json_encode($chart30Vals); ?>;
const chartMov7       = <?php echo json_encode($chartMov7); ?>;
const forecastLabels  = <?php echo json_encode($forecastLabels); ?>;
const forecastVals    = <?php echo json_encode($forecastVals); ?>;

// History chart
new Chart(document.getElementById('historyChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: chart30Labels,
        datasets: [
            {
                label: 'Daily Production (L)',
                data: chart30Vals,
                backgroundColor: 'rgba(40,167,69,0.4)',
                borderColor: '#28a745',
                borderWidth: 1,
                borderRadius: 3,
                order: 2,
            },
            {
                label: '7-Day Moving Avg',
                data: chartMov7,
                type: 'line',
                borderColor: '#fd7e14',
                backgroundColor: 'transparent',
                borderWidth: 2.5,
                pointRadius: 2,
                tension: 0.4,
                order: 1,
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top' },
            tooltip: { mode: 'index', intersect: false }
        },
        scales: { y: { beginAtZero: true, title: { display: true, text: 'Liters' } } }
    }
});

// Forecast chart
new Chart(document.getElementById('forecastChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: forecastLabels,
        datasets: [{
            label: 'Predicted Yield (L)',
            data: forecastVals,
            borderColor: '#17a2b8',
            backgroundColor: 'rgba(23,162,184,0.15)',
            fill: true,
            tension: 0.4,
            pointRadius: 5,
            pointBackgroundColor: '#17a2b8',
            borderWidth: 2.5,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: false, title: { display: true, text: 'Liters' } }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
