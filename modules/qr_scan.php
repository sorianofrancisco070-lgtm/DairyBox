<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$root      = '../';
$pageTitle = 'QR Code Lookup';
$db        = getDB();
$user      = currentUser();

$buffalo   = null;
$milkSummary = [];
$healthRecs  = [];
$vaccRecs    = [];
$breedRecs   = [];

$bid = (int)($_GET['id'] ?? 0);
$qr  = trim($_GET['qr'] ?? '');

if ($bid) {
    $stmt = $db->prepare("SELECT * FROM buffaloes WHERE id=?");
    $stmt->execute([$bid]);
    $buffalo = $stmt->fetch();
} elseif ($qr) {
    $stmt = $db->prepare("SELECT * FROM buffaloes WHERE qr_code=? OR tag_number=?");
    $stmt->execute([$qr,$qr]);
    $buffalo = $stmt->fetch();
}

if ($buffalo) {
    $id = $buffalo['id'];

    $milkSummary = $db->prepare("
        SELECT SUM(quantity_liters) as month_total, AVG(quantity_liters) as avg_sess,
               MAX(record_date) as last_record, COUNT(*) as entries
        FROM milk_production WHERE buffalo_id=? AND MONTH(record_date)=MONTH(CURDATE())
    ");
    $milkSummary->execute([$id]);
    $milkSummary = $milkSummary->fetch();

    $healthRecs = $db->prepare("SELECT * FROM health_records WHERE buffalo_id=? ORDER BY record_date DESC LIMIT 5");
    $healthRecs->execute([$id]);
    $healthRecs = $healthRecs->fetchAll();

    $vaccRecs = $db->prepare("SELECT * FROM vaccinations WHERE buffalo_id=? ORDER BY administered_date DESC LIMIT 5");
    $vaccRecs->execute([$id]);
    $vaccRecs = $vaccRecs->fetchAll();

    $breedRecs = $db->prepare("SELECT * FROM breeding_records WHERE buffalo_id=? ORDER BY breeding_date DESC LIMIT 3");
    $breedRecs->execute([$id]);
    $breedRecs = $breedRecs->fetchAll();
}

include '../includes/header.php';
?>

<div class="row g-3">
    <!-- Search Panel -->
    <div class="col-md-4">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-qrcode me-2"></i>QR Code Lookup</div>

            <form method="GET" class="mb-3">
                <div class="mb-2">
                    <label class="form-label fw-semibold">Tag / QR Code</label>
                    <div class="input-group">
                        <input type="text" name="qr" class="form-control" value="<?= htmlspecialchars($qr) ?>" placeholder="Enter tag or scan QR">
                        <button class="btn btn-success" type="submit"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </form>

            <hr>
            <p class="small text-muted fw-semibold mb-2">Or select buffalo:</p>
            <?php
            $bufList = $db->query("SELECT id,tag_number,name FROM buffaloes WHERE status='Active' ORDER BY tag_number")->fetchAll();
            foreach ($bufList as $b):
                $active = $buffalo && $buffalo['id'] == $b['id'];
            ?>
            <a href="?id=<?= $b['id'] ?>" class="btn btn-sm w-100 mb-1 text-start <?= $active ? 'btn-success' : 'btn-outline-secondary' ?>">
                <i class="fa fa-paw me-1"></i> <?= htmlspecialchars($b['tag_number']) ?> – <?= htmlspecialchars($b['name'] ?? '') ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Buffalo Profile -->
    <div class="col-md-8">
        <?php if (!$buffalo): ?>
        <div class="card-section text-center py-5">
            <div style="font-size:4rem">🔍</div>
            <h5 class="text-muted mt-2">Scan a QR code or search by tag number to view buffalo profile</h5>
        </div>
        <?php else: ?>

        <!-- Profile Header -->
        <div class="card-section mb-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h4 class="fw-bold text-success mb-1">
                        <?= htmlspecialchars($buffalo['name'] ?? 'Unnamed') ?>
                        <span class="badge bg-secondary fs-6"><?= htmlspecialchars($buffalo['tag_number']) ?></span>
                    </h4>
                    <p class="mb-1"><strong>Breed:</strong> <?= htmlspecialchars($buffalo['breed'] ?? '-') ?></p>
                    <p class="mb-1"><strong>Sex:</strong> <?= $buffalo['sex'] ?> | <strong>DOB:</strong> <?= $buffalo['date_of_birth'] ?? '-' ?></p>
                    <p class="mb-1"><strong>Weight:</strong> <?= $buffalo['weight_kg'] ? $buffalo['weight_kg'].' kg' : '-' ?></p>
                    <p class="mb-0">
                        <strong>Health:</strong>
                        <?php $hCls = match($buffalo['health_status']) {'Healthy'=>'badge-healthy','Sick'=>'badge-sick','Under Treatment'=>'badge-treated',default=>''}; ?>
                        <span class="badge-custom <?= $hCls ?>"><?= $buffalo['health_status'] ?></span>
                        &nbsp; <strong>Status:</strong> <?= $buffalo['status'] ?>
                    </p>
                </div>
                <div class="col-md-3 text-center mt-3 mt-md-0">
                    <div class="qr-box">
                        <div id="qrcode"></div>
                        <small class="text-muted d-block mt-1" style="font-size:.68rem;word-break:break-all">Scan to view profile</small>
                        <button onclick="printQR()" class="btn btn-sm btn-outline-success mt-1 no-print"><i class="fa fa-print me-1"></i>Print QR</button>
                    </div>
                </div>
                <div class="col-md-3 mt-3 mt-md-0">
                    <div class="d-grid gap-1">
                        <a href="milk_production.php?action=add" class="btn btn-sm btn-success"><i class="fa fa-tint me-1"></i>Record Milk</a>
                        <a href="health_records.php?action=add&buffalo_id=<?= $buffalo['id'] ?>" class="btn btn-sm btn-outline-danger"><i class="fa fa-heartbeat me-1"></i>Health Log</a>
                        <a href="vaccinations.php?action=add&buffalo_id=<?= $buffalo['id'] ?>" class="btn btn-sm btn-outline-warning"><i class="fa fa-syringe me-1"></i>Vaccinate</a>
                        <a href="buffaloes.php?action=edit&id=<?= $buffalo['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fa fa-edit me-1"></i>Edit Profile</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Milk Summary -->
        <div class="card-section mb-3">
            <div class="section-title"><i class="fa fa-tint me-2"></i>Milk Production – This Month</div>
            <div class="row g-2 text-center">
                <div class="col-3"><div class="border rounded p-2"><div class="fw-bold text-success"><?= number_format($milkSummary['month_total']??0,1) ?> L</div><small class="text-muted">Month Total</small></div></div>
                <div class="col-3"><div class="border rounded p-2"><div class="fw-bold text-primary"><?= number_format($milkSummary['avg_sess']??0,1) ?> L</div><small class="text-muted">Avg/Session</small></div></div>
                <div class="col-3"><div class="border rounded p-2"><div class="fw-bold"><?= $milkSummary['entries'] ?? 0 ?></div><small class="text-muted">Sessions</small></div></div>
                <div class="col-3"><div class="border rounded p-2"><div class="fw-bold"><?= $milkSummary['last_record'] ?? '-' ?></div><small class="text-muted">Last Record</small></div></div>
            </div>
        </div>

        <!-- Health, Vaccines, Breeding in tabs -->
        <div class="card-section">
            <ul class="nav nav-tabs mb-3" id="profileTabs">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-health">Health</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-vacc">Vaccines</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-breed">Breeding</a></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-health">
                    <table class="table table-sm">
                        <thead><tr><th>Date</th><th>Type</th><th>Diagnosis</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($healthRecs as $h): ?>
                        <tr><td><?= $h['record_date'] ?></td><td><?= $h['condition_type'] ?></td><td><?= htmlspecialchars($h['diagnosis']??'-') ?></td><td><?= $h['status'] ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (empty($healthRecs)): ?><tr><td colspan="4" class="text-center text-muted">No health records.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="tab-pane fade" id="tab-vacc">
                    <table class="table table-sm">
                        <thead><tr><th>Vaccine</th><th>Date</th><th>Next Due</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($vaccRecs as $v): ?>
                        <tr><td><?= htmlspecialchars($v['vaccine_name']) ?></td><td><?= $v['administered_date'] ?></td><td><?= $v['next_due_date']??'-' ?></td><td><?= $v['status'] ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (empty($vaccRecs)): ?><tr><td colspan="4" class="text-center text-muted">No vaccination records.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="tab-pane fade" id="tab-breed">
                    <table class="table table-sm">
                        <thead><tr><th>Date</th><th>Method</th><th>Exp. Calving</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($breedRecs as $br): ?>
                        <tr><td><?= $br['breeding_date'] ?></td><td><?= $br['method'] ?></td><td><?= $br['expected_calving']??'-' ?></td><td><?= $br['pregnancy_status'] ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (empty($breedRecs)): ?><tr><td colspan="4" class="text-center text-muted">No breeding records.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($buffalo): ?>
<?php
// Build the full scannable URL for QR
$protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'];
$scriptDir = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'])));
$scriptDir = rtrim($scriptDir, '/');
$qrUrl     = $protocol . '://' . $host . $scriptDir . '/buffalo_profile.php?id=' . $buffalo['id'];
?>
<script>
const QR_URL = <?= json_encode($qrUrl) ?>;
const TAG    = <?= json_encode($buffalo['tag_number']) ?>;
const NAME   = <?= json_encode($buffalo['name'] ?? '') ?>;
const BREED  = <?= json_encode($buffalo['breed'] ?? '') ?>;

new QRCode(document.getElementById("qrcode"), {
    text: QR_URL,
    width: 140,
    height: 140,
    colorDark: "#1a6b3c",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.H
});

function printQR() {
    const qrHtml = document.getElementById('qrcode').innerHTML;
    const w = window.open('', '_blank');
    w.document.write(`
        <!DOCTYPE html>
        <html><head>
        <meta charset="UTF-8">
        <title>QR – ${TAG}</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 20px; }
            .qr-print-card {
                display: inline-block;
                border: 2px solid #1a6b3c;
                border-radius: 12px;
                padding: 16px 20px;
                margin: 10px;
            }
            .farm-name { font-size: 10px; color: #666; margin-bottom: 6px; }
            .tag  { font-size: 18px; font-weight: bold; color: #1a6b3c; margin: 6px 0 2px; }
            .name { font-size: 14px; color: #333; margin-bottom: 2px; }
            .breed{ font-size: 11px; color: #888; margin-bottom: 8px; }
            .url  { font-size: 9px; color: #aaa; margin-top: 8px; word-break: break-all; max-width: 160px; }
            img, canvas { display: block; margin: 0 auto; }
        </style>
        </head>
        <body onload="window.print()">
            <div class="qr-print-card">
                <div class="farm-name">🐃 DairyBox – Dairy Box Surallah</div>
                ${qrHtml}
                <div class="tag">${TAG}</div>
                <div class="name">${NAME}</div>
                <div class="breed">${BREED}</div>
                <div class="url">${QR_URL}</div>
            </div>
        </body></html>
    `);
    w.document.close();
}
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
