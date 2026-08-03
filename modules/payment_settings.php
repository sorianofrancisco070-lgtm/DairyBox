<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin('dairy_cooperative');

$root      = '../';
$pageTitle = 'Payment Settings';
$db        = getDB();
$user      = currentUser();
$msg = $error = '';

// Upload directory
$uploadDir = __DIR__ . '/../assets/uploads/qr/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

// ── SAVE SETTINGS ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method      = trim($_POST['method'] ?? '');
    $displayName = trim($_POST['display_name'] ?? '');
    $acctName    = trim($_POST['account_name'] ?? '');
    $acctNumber  = trim($_POST['account_number'] ?? '');
    $instructions= trim($_POST['instructions'] ?? '');
    $isActive    = isset($_POST['is_active']) ? 1 : 0;

    if (!$method) { $error = 'Method key is required.'; }
    else {
        $qrImage = null;

        // Handle QR upload
        if (!empty($_FILES['qr_image']['name'])) {
            $file     = $_FILES['qr_image'];
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed  = ['jpg','jpeg','png','gif','webp'];
            if (!in_array($ext, $allowed)) {
                $error = 'QR image must be JPG, PNG, GIF or WebP.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $error = 'QR image must be under 2MB.';
            } else {
                $filename = 'qr_' . preg_replace('/[^a-z0-9]/', '_', strtolower($method)) . '.' . $ext;
                $destPath = $uploadDir . $filename;
                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $qrImage = 'assets/uploads/qr/' . $filename;
                } else {
                    $error = 'Failed to upload QR image.';
                }
            }
        }

        if (!$error) {
            // Check if exists
            $existing = $db->prepare("SELECT id, qr_image FROM coop_payment_settings WHERE method=?");
            $existing->execute([$method]);
            $existing = $existing->fetch();

            if ($existing) {
                // Keep old QR if no new one uploaded
                if (!$qrImage) $qrImage = $existing['qr_image'];
                $db->prepare("UPDATE coop_payment_settings SET display_name=?,account_name=?,account_number=?,instructions=?,qr_image=?,is_active=?,updated_by=? WHERE method=?")
                   ->execute([$displayName,$acctName,$acctNumber,$instructions,$qrImage,$isActive,$user['id'],$method]);
                $msg = "Payment settings for '$displayName' updated.";
            } else {
                $db->prepare("INSERT INTO coop_payment_settings (method,display_name,account_name,account_number,instructions,qr_image,is_active,updated_by) VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([$method,$displayName,$acctName,$acctNumber,$instructions,$qrImage,$isActive,$user['id']]);
                $msg = "Payment method '$displayName' added.";
            }
        }
    }
}

// ── DELETE QR ─────────────────────────────────────────────
if (isset($_GET['remove_qr'])) {
    $mid = trim($_GET['remove_qr']);
    $row = $db->prepare("SELECT qr_image FROM coop_payment_settings WHERE method=?");
    $row->execute([$mid]); $row = $row->fetch();
    if ($row && $row['qr_image']) {
        $fp = __DIR__ . '/../' . $row['qr_image'];
        if (file_exists($fp)) unlink($fp);
        $db->prepare("UPDATE coop_payment_settings SET qr_image=NULL WHERE method=?")->execute([$mid]);
        $msg = 'QR image removed.';
    }
    header('Location: payment_settings.php?msg=' . urlencode($msg)); exit;
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

// ── LOAD ALL ──────────────────────────────────────────────
$settings = $db->query("SELECT * FROM coop_payment_settings ORDER BY method")->fetchAll();

// Currently editing
$editing = null;
if (isset($_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM coop_payment_settings WHERE method=?");
    $s->execute([trim($_GET['edit'])]);
    $editing = $s->fetch();
}

include '../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="fa fa-check me-2"></i><?= $msg ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row g-3">
    <!-- Payment Methods List -->
    <div class="col-lg-5">
        <div class="card-section">
            <div class="section-title"><i class="fa fa-credit-card me-2"></i>Payment Methods
                <a href="?edit=new" class="btn btn-sm btn-success float-end">+ Add New</a>
            </div>

            <?php if (empty($settings)): ?>
            <div class="alert alert-warning">
                No payment methods configured yet. Run the migration SQL first then add methods here.
            </div>
            <?php endif; ?>

            <?php foreach ($settings as $s): ?>
            <div class="d-flex align-items-center gap-3 p-3 mb-2 rounded border <?= !$s['is_active'] ? 'opacity-50' : '' ?>"
                 style="background:<?= $s['is_active'] ? '#f8fff9' : '#f8f9fa' ?>">

                <!-- QR thumbnail -->
                <div style="width:56px;height:56px;flex-shrink:0;border-radius:8px;overflow:hidden;background:#eee;display:flex;align-items:center;justify-content:center">
                    <?php if ($s['qr_image'] && file_exists(__DIR__.'/../'.$s['qr_image'])): ?>
                    <img src="<?= $root . $s['qr_image'] ?>?v=<?= time() ?>" alt="QR"
                         style="width:56px;height:56px;object-fit:cover">
                    <?php else: ?>
                    <i class="fa fa-qrcode text-muted" style="font-size:1.5rem"></i>
                    <?php endif; ?>
                </div>

                <div class="flex-grow-1 min-width-0">
                    <div class="fw-bold" style="font-size:.9rem"><?= htmlspecialchars($s['display_name']) ?></div>
                    <?php if ($s['account_name']): ?>
                    <div style="font-size:.78rem;color:#555"><?= htmlspecialchars($s['account_name']) ?></div>
                    <?php endif; ?>
                    <?php if ($s['account_number']): ?>
                    <div style="font-size:.8rem;color:#1a6b3c;font-weight:600"><?= htmlspecialchars($s['account_number']) ?></div>
                    <?php endif; ?>
                    <div class="mt-1">
                        <?php if ($s['is_active']): ?>
                        <span class="badge bg-success" style="font-size:.65rem">Active</span>
                        <?php else: ?>
                        <span class="badge bg-secondary" style="font-size:.65rem">Inactive</span>
                        <?php endif; ?>
                        <?php if ($s['qr_image']): ?>
                        <span class="badge bg-info" style="font-size:.65rem">QR ✓</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex flex-column gap-1">
                    <a href="?edit=<?= urlencode($s['method']) ?>" class="btn btn-xs btn-outline-primary" style="font-size:.72rem;padding:.2rem .5rem">
                        <i class="fa fa-edit"></i> Edit
                    </a>
                    <?php if ($s['qr_image']): ?>
                    <a href="?remove_qr=<?= urlencode($s['method']) ?>"
                       class="btn btn-xs btn-outline-danger"
                       style="font-size:.72rem;padding:.2rem .5rem"
                       onclick="return confirm('Remove QR image?')">
                        <i class="fa fa-trash"></i> QR
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Edit / Add Form -->
    <div class="col-lg-7">
        <div class="card-section">
            <div class="section-title">
                <i class="fa fa-cog me-2"></i>
                <?= $editing ? 'Edit: ' . htmlspecialchars($editing['display_name'] ?? '') : 'Add / Edit Payment Method' ?>
            </div>

            <?php if (!$editing && !isset($_GET['edit'])): ?>
            <p class="text-muted small">Select a payment method on the left to edit it, or click <strong>+ Add New</strong>.</p>
            <?php else: ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Method Key * <small class="text-muted">(e.g. GCash, Maya)</small></label>
                        <input type="text" name="method" class="form-control"
                               value="<?= htmlspecialchars($editing['method'] ?? '') ?>"
                               <?= $editing && $editing['method'] ? 'readonly' : '' ?>
                               required placeholder="GCash">
                        <?php if ($editing && $editing['method']): ?>
                        <small class="text-muted">Method key cannot be changed.</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Display Name *</label>
                        <input type="text" name="display_name" class="form-control"
                               value="<?= htmlspecialchars($editing['display_name'] ?? '') ?>"
                               required placeholder="GCash">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Account Name</label>
                        <input type="text" name="account_name" class="form-control"
                               value="<?= htmlspecialchars($editing['account_name'] ?? '') ?>"
                               placeholder="e.g. Juan Dela Cruz">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Account Number / Phone</label>
                        <input type="text" name="account_number" class="form-control"
                               value="<?= htmlspecialchars($editing['account_number'] ?? '') ?>"
                               placeholder="e.g. 09XX XXX XXXX">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Payment Instructions</label>
                        <textarea name="instructions" class="form-control" rows="2"
                                  placeholder="e.g. Send exact amount and show screenshot to cashier."><?= htmlspecialchars($editing['instructions'] ?? '') ?></textarea>
                    </div>

                    <!-- QR Upload -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            <i class="fa fa-qrcode me-1 text-success"></i>QR Code Image
                            <small class="text-muted">(JPG/PNG, max 2MB)</small>
                        </label>

                        <?php if (!empty($editing['qr_image']) && file_exists(__DIR__.'/../'.$editing['qr_image'])): ?>
                        <div class="mb-2 d-flex align-items-center gap-3">
                            <img src="<?= $root . $editing['qr_image'] ?>?v=<?= time() ?>"
                                 alt="Current QR"
                                 style="width:120px;height:120px;object-fit:contain;border:2px solid #d4edda;border-radius:8px;padding:4px;background:#fff">
                            <div>
                                <div class="badge bg-success mb-1">Current QR</div><br>
                                <small class="text-muted">Upload a new image to replace it.</small>
                            </div>
                        </div>
                        <?php endif; ?>

                        <input type="file" name="qr_image" class="form-control"
                               accept="image/jpeg,image/png,image/gif,image/webp"
                               onchange="previewQR(this)">
                        <div id="qrPreview" class="mt-2" style="display:none">
                            <img id="qrPreviewImg"
                                 style="width:120px;height:120px;object-fit:contain;border:2px solid #cce5ff;border-radius:8px;padding:4px;background:#fff"
                                 alt="Preview">
                            <small class="d-block text-muted mt-1">Preview</small>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                                   <?= ($editing['is_active'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="isActive">Active (show in POS)</label>
                        </div>
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-save me-1"></i>Save Settings
                    </button>
                    <a href="payment_settings.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
            <?php endif; ?>
        </div>

        <!-- How it works -->
        <div class="card-section">
            <div class="section-title"><i class="fa fa-info-circle me-2"></i>How it works</div>
            <ul class="mb-0 small text-muted">
                <li>Add your <strong>account name</strong>, <strong>number</strong>, and <strong>instructions</strong> for each payment method.</li>
                <li>Upload a <strong>QR code image</strong> (from GCash, Maya, Maribank, etc.) for each method.</li>
                <li>In the POS, when the cashier selects a cashless payment method, the <strong>QR code and payment details</strong> will be displayed for the customer to scan.</li>
                <li>Only <strong>active</strong> methods appear in the POS checkout.</li>
            </ul>
        </div>
    </div>
</div>

<script>
function previewQR(input) {
    const preview = document.getElementById('qrPreview');
    const img     = document.getElementById('qrPreviewImg');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { img.src = e.target.result; preview.style.display = ''; };
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
    }
}
</script>

<?php include '../includes/footer.php'; ?>
