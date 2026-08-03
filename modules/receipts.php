<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin('dairy_cooperative');

$root      = '../';
$pageTitle = 'Receipts';
$db        = getDB();
$user      = currentUser();

// ── Handle Void ───────────────────────────────────────────
if (isset($_GET['void'])) {
    $voidId = (int)$_GET['void'];
    $sale   = $db->prepare("SELECT * FROM coop_sales WHERE id=?");
    $sale->execute([$voidId]);
    $saleRow = $sale->fetch();
    if ($saleRow && $saleRow['status'] === 'Completed') {
        $db->prepare("UPDATE coop_sales SET status='Voided' WHERE id=?")->execute([$voidId]);
        // Restore stock
        $items = $db->prepare("SELECT * FROM coop_sale_items WHERE sale_id=?");
        $items->execute([$voidId]);
        foreach ($items->fetchAll() as $item) {
            $db->prepare("UPDATE coop_products SET stock_qty = stock_qty + ? WHERE id=?")->execute([$item['quantity'], $item['product_id']]);
            $db->prepare("INSERT INTO coop_inventory (product_id,movement_type,quantity,reference_id,notes,recorded_by) VALUES (?,?,?,?,?,?)")
               ->execute([$item['product_id'],'Return',$item['quantity'],$voidId,'Void: '.$saleRow['receipt_number'],$user['id']]);
        }
        header('Location: receipts.php?msg=voided'); exit;
    }
}

$viewId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg    = $_GET['msg'] ?? '';

// ── Single receipt view ───────────────────────────────────
if ($viewId > 0) {
    $stmt = $db->prepare("SELECT s.*, u.full_name AS cashier FROM coop_sales s LEFT JOIN users u ON u.id=s.created_by WHERE s.id=?");
    $stmt->execute([$viewId]);
    $sale = $stmt->fetch();

    if (!$sale) { header('Location: receipts.php'); exit; }

    $itemsStmt = $db->prepare("SELECT si.*, cp.name AS product_name, cp.unit FROM coop_sale_items si JOIN coop_products cp ON cp.id=si.product_id WHERE si.sale_id=?");
    $itemsStmt->execute([$viewId]);
    $lineItems = $itemsStmt->fetchAll();

    include '../includes/header.php';
    ?>
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card-section" id="receiptPrint">
                <!-- Print Header -->
                <div class="text-center mb-3">
                    <div style="font-size:2rem">🐃</div>
                    <h5 class="mb-0 fw-bold" style="color:#1a6b3c">DairyBox Cooperative</h5>
                    <small class="text-muted">Fresh from the Farm | dairybox.ph</small>
                </div>

                <div class="d-flex justify-content-between mb-2 flex-wrap gap-1">
                    <div>
                        <p class="mb-1"><strong>Receipt No.:</strong> <code><?= htmlspecialchars($sale['receipt_number']) ?></code></p>
                        <p class="mb-1"><strong>Date:</strong> <?= date('F d, Y', strtotime($sale['sale_date'])) ?></p>
                        <p class="mb-1"><strong>Time:</strong> <?= date('h:i A', strtotime($sale['created_at'])) ?></p>
                    </div>
                    <div class="text-end">
                        <p class="mb-1"><strong>Customer:</strong> <?= htmlspecialchars($sale['customer_name']) ?></p>
                        <?php if ($sale['customer_phone']): ?><p class="mb-1"><strong>Phone:</strong> <?= htmlspecialchars($sale['customer_phone']) ?></p><?php endif; ?>
                        <p class="mb-1"><strong>Cashier:</strong> <?= htmlspecialchars($sale['cashier'] ?? 'N/A') ?></p>
                    </div>
                </div>

                <?php if ($sale['status'] === 'Voided'): ?>
                <div class="alert alert-danger text-center fw-bold">⚠️ THIS RECEIPT HAS BEEN VOIDED</div>
                <?php endif; ?>

                <hr>
                <table class="table table-sm mb-2">
                    <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($lineItems as $li): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($li['product_name']) ?></strong>
                            <small class="text-muted d-block"><?= htmlspecialchars($li['unit']) ?></small>
                        </td>
                        <td class="text-center"><?= number_format($li['quantity'], 2) ?></td>
                        <td class="text-end">₱<?= number_format($li['unit_price'], 2) ?></td>
                        <td class="text-end fw-semibold">₱<?= number_format($li['line_total'], 2) ?></td>
                    </tr>
                    <?php if ($li['discount'] > 0): ?>
                    <tr class="table-light"><td colspan="3" class="text-end text-danger fst-italic ps-4">Item Discount:</td><td class="text-end text-danger">-₱<?= number_format($li['discount'],2) ?></td></tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <hr>
                <div class="px-2">
                    <div class="d-flex justify-content-between mb-1"><span>Subtotal:</span><strong>₱<?= number_format($sale['subtotal'],2) ?></strong></div>
                    <?php if ($sale['discount_amount'] > 0): ?>
                    <div class="d-flex justify-content-between mb-1 text-danger"><span>Discount:</span><strong>-₱<?= number_format($sale['discount_amount'],2) ?></strong></div>
                    <?php endif; ?>
                    <?php if ($sale['tax_amount'] > 0): ?>
                    <div class="d-flex justify-content-between mb-1"><span>Tax:</span><strong>₱<?= number_format($sale['tax_amount'],2) ?></strong></div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between mb-1 fw-bold" style="font-size:1.15rem;border-top:2px solid #dee2e6;padding-top:.5rem;margin-top:.3rem">
                        <span>TOTAL:</span><span class="text-success">₱<?= number_format($sale['total_amount'],2) ?></span>
                    </div>
                    <?php if ($sale['payment_method'] === 'Cash'): ?>
                    <div class="d-flex justify-content-between mb-1"><span>Amount Tendered:</span><span>₱<?= number_format($sale['amount_tendered'],2) ?></span></div>
                    <div class="d-flex justify-content-between mb-1 text-primary fw-semibold"><span>Change:</span><span>₱<?= number_format($sale['change_amount'],2) ?></span></div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between mb-1"><span>Payment Method:</span><span><strong><?= $sale['payment_method'] ?></strong></span></div>
                    <?php if ($sale['notes']): ?>
                    <p class="mt-2 fst-italic text-muted" style="font-size:.82rem">Notes: <?= htmlspecialchars($sale['notes']) ?></p>
                    <?php endif; ?>
                </div>

                <hr>
                <div class="text-center text-muted" style="font-size:.78rem">
                    <p class="mb-0">Thank you for your purchase!</p>
                    <p class="mb-0">DairyBox – Fresh from the farm 🐃</p>
                </div>
            </div>

            <div class="d-flex gap-2 mb-3 no-print">
                <button class="btn btn-outline-success btn-sm" onclick="window.print()"><i class="fa fa-print me-1"></i>Print</button>
                <button class="btn btn-primary btn-sm" onclick="saveReceiptPDF()"><i class="fa fa-file-pdf me-1"></i>Save PDF</button>
                <a href="receipts.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i>Back</a>
                <?php if ($sale['status'] === 'Completed'): ?>
                <a href="?void=<?= $sale['id'] ?>" class="btn btn-outline-danger btn-sm"
                   onclick="return confirm('Void this receipt? This will restore inventory stock.')">
                   <i class="fa fa-ban me-1"></i>Void Receipt
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function saveReceiptPDF() {
        const orig = document.title;
        document.title = 'Receipt_<?= htmlspecialchars($sale['receipt_number']) ?>';
        window.print();
        document.title = orig;
    }
    </script>

    <?php include '../includes/footer.php'; ?>
    <?php exit; ?>
<?php } ?>

<?php
// ── Receipt List ──────────────────────────────────────────
$search  = trim($_GET['search'] ?? '');
$status  = $_GET['status'] ?? '';
$dateF   = $_GET['date_from'] ?? date('Y-m-01');
$dateT   = $_GET['date_to']   ?? date('Y-m-d');

$where = "WHERE s.sale_date BETWEEN ? AND ?"; $params = [$dateF, $dateT];
if ($search) { $where .= " AND (s.receipt_number LIKE ? OR s.customer_name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status) { $where .= " AND s.status=?"; $params[] = $status; }

$stmt = $db->prepare("
    SELECT s.*, u.full_name AS cashier,
           (SELECT COUNT(*) FROM coop_sale_items si WHERE si.sale_id=s.id) AS item_count
    FROM coop_sales s LEFT JOIN users u ON u.id=s.created_by
    $where ORDER BY s.created_at DESC LIMIT 100
");
$stmt->execute($params);
$sales = $stmt->fetchAll();

// ── CSV Export ────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "receipts_{$dateF}_to_{$dateT}.csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ["DairyBox – Receipts Export: {$dateF} to {$dateT}"]);
    fputcsv($out, ["Generated: ".date('M d, Y h:i A')." by ".$user['full_name']]);
    fputcsv($out, []);
    fputcsv($out, ['Receipt #','Date','Customer','Phone','Items','Subtotal','Discount','Tax','Total','Payment Method','Status','Cashier']);
    foreach ($sales as $s) {
        fputcsv($out, [
            $s['receipt_number'], $s['sale_date'], $s['customer_name'],
            $s['customer_phone'] ?? '', $s['item_count'],
            number_format($s['subtotal'],2), number_format($s['discount_amount'],2),
            number_format($s['tax_amount'],2), number_format($s['total_amount'],2),
            $s['payment_method'], $s['status'], $s['cashier'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

include '../includes/header.php';
?>

<?php if ($msg === 'voided'): ?><div class="alert alert-success alert-dismissible fade show">Receipt voided and stock restored.<button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- Filters -->
<div class="card-section no-print mb-3">
    <form class="row g-2 align-items-end" method="GET">
        <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Search</label>
            <input type="text" name="search" class="form-control form-control-sm" value="<?= htmlspecialchars($search) ?>" placeholder="Receipt # or customer…">
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $dateF ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $dateT ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="Completed" <?= $status==='Completed'?'selected':'' ?>>Completed</option>
                <option value="Voided"    <?= $status==='Voided'   ?'selected':'' ?>>Voided</option>
                <option value="Refunded"  <?= $status==='Refunded' ?'selected':'' ?>>Refunded</option>
            </select>
        </div>
        <div class="col-md-1"><button class="btn btn-success btn-sm w-100 mt-1"><i class="fa fa-search"></i></button></div>
        <div class="col-md-2"><a href="receipts.php" class="btn btn-outline-secondary btn-sm w-100 mt-1">Reset</a></div>
    </form>
</div>

<div class="card-section">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="section-title mb-0"><i class="fa fa-receipt me-2"></i>Receipts
            <small class="text-muted fw-normal">(<?= count($sales) ?> records)</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-success no-print" onclick="window.print()"><i class="fa fa-print me-1"></i>Print</button>
            <button class="btn btn-sm btn-outline-primary no-print" onclick="saveListPDF()"><i class="fa fa-file-pdf me-1"></i>Save PDF</button>
            <a href="?search=<?= urlencode($search) ?>&date_from=<?= $dateF ?>&date_to=<?= $dateT ?>&status=<?= $status ?>&export=csv"
               class="btn btn-sm btn-outline-secondary no-print"><i class="fa fa-file-csv me-1"></i>Export CSV</a>
            <a href="pos.php" class="btn btn-success btn-sm no-print"><i class="fa fa-plus me-1"></i>New Sale</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>Receipt #</th><th>Date</th><th>Customer</th>
                    <th>Items</th><th>Subtotal</th><th>Discount</th>
                    <th>Total</th><th>Payment</th><th>Status</th>
                    <th class="no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sales as $s): ?>
            <tr class="<?= $s['status']==='Voided' ? 'table-secondary text-decoration-line-through' : '' ?>">
                <td><code><?= htmlspecialchars($s['receipt_number']) ?></code></td>
                <td><?= date('M d, Y', strtotime($s['sale_date'])) ?></td>
                <td><?= htmlspecialchars($s['customer_name']) ?></td>
                <td class="text-center"><?= $s['item_count'] ?></td>
                <td>₱<?= number_format($s['subtotal'], 2) ?></td>
                <td><?= $s['discount_amount'] > 0 ? '₱'.number_format($s['discount_amount'],2) : '—' ?></td>
                <td class="fw-semibold text-success">₱<?= number_format($s['total_amount'], 2) ?></td>
                <td><?= $s['payment_method'] ?></td>
                <td>
                    <?php $sc = match($s['status']) { 'Completed'=>'badge-healthy', 'Voided'=>'badge-sick', default=>'badge-pregnant' }; ?>
                    <span class="badge-custom <?= $sc ?>"><?= $s['status'] ?></span>
                </td>
                <td class="no-print">
                    <a href="?id=<?= $s['id'] ?>" class="btn btn-xs btn-outline-primary" title="View Receipt"><i class="fa fa-eye"></i></a>
                    <?php if ($s['status'] === 'Completed'): ?>
                    <a href="?void=<?= $s['id'] ?>" class="btn btn-xs btn-outline-danger" title="Void"
                       onclick="return confirm('Void this receipt?')"><i class="fa fa-ban"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($sales)): ?>
            <tr><td colspan="10" class="text-center text-muted py-3">No receipts found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function saveListPDF() {
    const orig = document.title;
    document.title = 'Receipts_<?= $dateF ?>_to_<?= $dateT ?>';
    window.print();
    document.title = orig;
}
</script>

<?php include '../includes/footer.php'; ?>
