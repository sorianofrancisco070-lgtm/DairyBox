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

// ══════════════════════════════════════════════════════════
// SINGLE RECEIPT VIEW
// ══════════════════════════════════════════════════════════
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

<style>
/* ── Screen: center the receipt card ── */
.receipt-wrapper {
    display: flex;
    justify-content: center;
    padding: 1rem;
}
.receipt-card {
    width: 100%;
    max-width: 420px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,.12);
    padding: 1.8rem 1.6rem;
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: .9rem;
}
.receipt-header { text-align: center; margin-bottom: 1rem; }
.receipt-header img { width: 60px; height: 60px; object-fit: contain; border-radius: 8px; margin-bottom: .4rem; }
.receipt-header h5 { margin: 0; font-size: 1.1rem; font-weight: 700; color: #1a6b3c; }
.receipt-header small { color: #888; font-size: .75rem; }
.receipt-divider { border: none; border-top: 1px dashed #bbb; margin: .7rem 0; }
.receipt-divider.double { border-top: 2px solid #333; }
.receipt-meta { display: flex; justify-content: space-between; flex-wrap: wrap; gap: .3rem; font-size: .82rem; margin-bottom: .5rem; }
.receipt-meta .lbl { color: #888; }
.receipt-meta .val { font-weight: 600; }
.receipt-table { width: 100%; border-collapse: collapse; font-size: .83rem; }
.receipt-table th { border-bottom: 1px solid #333; padding: .3rem .2rem; font-weight: 700; font-size: .75rem; text-transform: uppercase; letter-spacing: .4px; }
.receipt-table td { padding: .35rem .2rem; vertical-align: top; }
.receipt-table .item-name { font-weight: 600; }
.receipt-table .item-unit { color: #888; font-size: .72rem; }
.receipt-table .text-right { text-align: right; }
.receipt-table .text-center { text-align: center; }
.receipt-totals { width: 100%; font-size: .87rem; }
.receipt-totals tr td { padding: .22rem 0; }
.receipt-totals tr td:last-child { text-align: right; font-weight: 600; }
.receipt-grand-total td { font-size: 1.1rem; font-weight: 700; color: #1a6b3c; border-top: 2px solid #333; padding-top: .5rem !important; }
.receipt-footer { text-align: center; margin-top: .8rem; font-size: .75rem; color: #888; }
.voided-stamp {
    text-align: center;
    border: 3px solid #dc3545;
    color: #dc3545;
    font-size: 1.3rem;
    font-weight: 900;
    letter-spacing: 4px;
    padding: .4rem;
    border-radius: 6px;
    margin: .5rem 0;
    transform: rotate(-3deg);
    opacity: .8;
}
.receipt-actions { display: flex; gap: .5rem; margin-top: 1.2rem; flex-wrap: wrap; }
.receipt-actions .btn { flex: 1; min-width: 100px; }

/* ── Print Styles ── */
@media print {
    /* Hide everything except the receipt */
    body * { visibility: hidden !important; }
    .receipt-card, .receipt-card * { visibility: visible !important; }

    body { margin: 0; padding: 0; background: #fff !important; }

    .receipt-card {
        position: fixed !important;
        top: 0 !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        width: 80mm !important;          /* thermal 80mm width */
        max-width: 80mm !important;
        padding: 4mm 5mm !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        font-size: 10pt !important;
    }

    .receipt-header img { width: 40px !important; height: 40px !important; }
    .receipt-header h5  { font-size: 11pt !important; }

    .receipt-table { font-size: 9pt !important; }
    .receipt-table th { font-size: 8pt !important; }
    .receipt-totals { font-size: 9pt !important; }
    .receipt-grand-total td { font-size: 11pt !important; }
    .receipt-footer { font-size: 8pt !important; }

    .receipt-actions { display: none !important; }
    .receipt-divider { border-top: 1px dashed #000 !important; }

    @page {
        size: 80mm auto;
        margin: 3mm 0;
    }
}
</style>

<div class="receipt-wrapper no-print-pad">
    <div>
        <!-- Action Buttons (above card on screen) -->
        <div class="receipt-actions mb-3 no-print d-flex flex-wrap gap-2">
            <button class="btn btn-success btn-sm" onclick="window.print()">
                <i class="fa fa-print me-1"></i>Print Receipt
            </button>
            <button class="btn btn-primary btn-sm" onclick="savePDF()">
                <i class="fa fa-file-pdf me-1"></i>Save as PDF
            </button>
            <a href="receipts.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa fa-arrow-left me-1"></i>Back to List
            </a>
            <?php if ($sale['status'] === 'Completed'): ?>
            <a href="?void=<?= $sale['id'] ?>" class="btn btn-outline-danger btn-sm"
               onclick="return confirm('Void this receipt? Stock will be restored.')">
                <i class="fa fa-ban me-1"></i>Void
            </a>
            <?php endif; ?>
        </div>

        <!-- RECEIPT CARD -->
        <div class="receipt-card" id="receiptCard">

            <!-- Header -->
            <div class="receipt-header">
                <img src="<?= $root ?>assets/img/logo.jpg" alt="Logo" onerror="this.style.display='none'">
                <h5>DairyBox Cooperative</h5>
                <small>Dairy Box Surallah, South Cotabato</small>
            </div>

            <hr class="receipt-divider double">

            <!-- VOIDED STAMP -->
            <?php if ($sale['status'] === 'Voided'): ?>
            <div class="voided-stamp">VOIDED</div>
            <?php endif; ?>

            <!-- Receipt Meta -->
            <div class="receipt-meta">
                <div><span class="lbl">Receipt #:</span><br><span class="val"><?= htmlspecialchars($sale['receipt_number']) ?></span></div>
                <div class="text-end"><span class="lbl">Date:</span><br><span class="val"><?= date('M d, Y', strtotime($sale['sale_date'])) ?></span></div>
            </div>
            <div class="receipt-meta">
                <div><span class="lbl">Customer:</span><br><span class="val"><?= htmlspecialchars($sale['customer_name']) ?></span></div>
                <div class="text-end"><span class="lbl">Time:</span><br><span class="val"><?= date('h:i A', strtotime($sale['created_at'])) ?></span></div>
            </div>
            <?php if ($sale['customer_phone']): ?>
            <div class="receipt-meta">
                <div><span class="lbl">Phone:</span> <span class="val"><?= htmlspecialchars($sale['customer_phone']) ?></span></div>
            </div>
            <?php endif; ?>
            <div class="receipt-meta">
                <div><span class="lbl">Cashier:</span> <span class="val"><?= htmlspecialchars($sale['cashier'] ?? 'N/A') ?></span></div>
                <div class="text-end"><span class="lbl">Payment:</span> <span class="val"><?= $sale['payment_method'] ?></span></div>
            </div>

            <hr class="receipt-divider">

            <!-- Items Table -->
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th style="width:45%">Item</th>
                        <th class="text-center" style="width:15%">Qty</th>
                        <th class="text-right" style="width:20%">Price</th>
                        <th class="text-right" style="width:20%">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lineItems as $li): ?>
                <tr>
                    <td>
                        <div class="item-name"><?= htmlspecialchars($li['product_name']) ?></div>
                        <div class="item-unit"><?= htmlspecialchars($li['unit']) ?></div>
                    </td>
                    <td class="text-center"><?= number_format($li['quantity'], 0) ?></td>
                    <td class="text-right">₱<?= number_format($li['unit_price'], 2) ?></td>
                    <td class="text-right">₱<?= number_format($li['line_total'], 2) ?></td>
                </tr>
                <?php if ($li['discount'] > 0): ?>
                <tr>
                    <td colspan="3" class="text-right" style="color:#dc3545;font-style:italic;font-size:.78rem">Disc:</td>
                    <td class="text-right" style="color:#dc3545">-₱<?= number_format($li['discount'],2) ?></td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>

            <hr class="receipt-divider">

            <!-- Totals -->
            <table class="receipt-totals">
                <tr><td>Subtotal</td><td>₱<?= number_format($sale['subtotal'],2) ?></td></tr>
                <?php if ($sale['discount_amount'] > 0): ?>
                <tr style="color:#dc3545"><td>Discount</td><td>-₱<?= number_format($sale['discount_amount'],2) ?></td></tr>
                <?php endif; ?>
                <?php if ($sale['tax_amount'] > 0): ?>
                <tr><td>Tax</td><td>₱<?= number_format($sale['tax_amount'],2) ?></td></tr>
                <?php endif; ?>
                <tr class="receipt-grand-total"><td>TOTAL</td><td>₱<?= number_format($sale['total_amount'],2) ?></td></tr>
                <?php if ($sale['payment_method'] === 'Cash'): ?>
                <tr><td>Tendered</td><td>₱<?= number_format($sale['amount_tendered'],2) ?></td></tr>
                <tr style="color:#0066cc;font-weight:700"><td>Change</td><td>₱<?= number_format($sale['change_amount'],2) ?></td></tr>
                <?php endif; ?>
            </table>

            <?php if ($sale['notes']): ?>
            <hr class="receipt-divider">
            <p style="font-size:.78rem;color:#888;margin:0;font-style:italic">Note: <?= htmlspecialchars($sale['notes']) ?></p>
            <?php endif; ?>

            <hr class="receipt-divider">

            <!-- Footer -->
            <div class="receipt-footer">
                <p style="margin:0;font-size:.8rem;font-weight:600">Thank you for your purchase!</p>
                <p style="margin:.2rem 0 0;font-size:.72rem">DairyBox – Fresh from the farm 🐃</p>
                <p style="margin:.2rem 0 0;font-size:.68rem;color:#aaa"><?= date('M d, Y h:i A', strtotime($sale['created_at'])) ?></p>
            </div>
        </div>
        <!-- END RECEIPT CARD -->
    </div>
</div>

<script>
function savePDF() {
    const orig = document.title;
    document.title = 'Receipt_<?= htmlspecialchars($sale['receipt_number']) ?>';
    window.print();
    document.title = orig;
}
</script>

<?php
    include '../includes/footer.php';
    exit;
}
?>

<?php
// ══════════════════════════════════════════════════════════
// RECEIPT LIST
// ══════════════════════════════════════════════════════════
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
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ["DairyBox Receipts: {$dateF} to {$dateT}"]);
    fputcsv($out, ["Generated: ".date('M d, Y h:i A')." by ".$user['full_name']]);
    fputcsv($out, []);
    fputcsv($out, ['Receipt #','Date','Customer','Phone','Items','Subtotal','Discount','Tax','Total','Payment','Status','Cashier']);
    foreach ($sales as $s) {
        fputcsv($out, [
            $s['receipt_number'], $s['sale_date'], $s['customer_name'],
            $s['customer_phone']??'', $s['item_count'],
            number_format($s['subtotal'],2), number_format($s['discount_amount'],2),
            number_format($s['tax_amount'],2), number_format($s['total_amount'],2),
            $s['payment_method'], $s['status'], $s['cashier']??'',
        ]);
    }
    fclose($out); exit;
}

include '../includes/header.php';
?>

<style>
@media print {
    .sidebar,.topbar,.no-print,.mobile-bottom-nav,.sidebar-backdrop { display:none!important; }
    .main-content { margin-left:0!important; padding-top:0!important; }
    .content-body { padding:0!important; }
    body { background:#fff!important; }
    .card-section { box-shadow:none; border:1px solid #dee2e6; }
    .table th { background:#1a6b3c!important; -webkit-print-color-adjust:exact; color:#fff!important; }
}
</style>

<?php if ($msg === 'voided'): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="fa fa-check me-1"></i>Receipt voided and inventory stock restored.
    <button class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card-section mb-3 no-print">
    <form class="row g-2 align-items-end" method="GET">
        <div class="col-md-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Search</label>
            <input type="text" name="search" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($search) ?>" placeholder="Receipt # or customer…">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $dateF ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $dateT ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="Completed" <?= $status==='Completed'?'selected':'' ?>>Completed</option>
                <option value="Voided"    <?= $status==='Voided'   ?'selected':'' ?>>Voided</option>
            </select>
        </div>
        <div class="col-md-1"><button class="btn btn-success btn-sm w-100"><i class="fa fa-search"></i></button></div>
        <div class="col-md-1"><a href="receipts.php" class="btn btn-outline-secondary btn-sm w-100">Reset</a></div>
    </form>
</div>

<div class="card-section">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="section-title mb-0">
            <i class="fa fa-receipt me-2"></i>Receipts
            <small class="text-muted fw-normal">(<?= count($sales) ?>)</small>
        </div>
        <div class="d-flex gap-2 flex-wrap no-print">
            <button class="btn btn-sm btn-outline-success" onclick="window.print()"><i class="fa fa-print me-1"></i>Print</button>
            <a href="?search=<?= urlencode($search) ?>&date_from=<?= $dateF ?>&date_to=<?= $dateT ?>&status=<?= $status ?>&export=csv"
               class="btn btn-sm btn-outline-secondary"><i class="fa fa-file-csv me-1"></i>CSV</a>
            <a href="pos.php" class="btn btn-success btn-sm"><i class="fa fa-plus me-1"></i>New Sale</a>
        </div>
    </div>

    <!-- Print header (only shows when printing) -->
    <div class="d-none" id="printHeader" style="display:none!important">
        <div class="text-center mb-3">
            <h5 class="fw-bold text-success">🐃 DairyBox Cooperative — Receipts</h5>
            <p class="text-muted small mb-0"><?= date('M d, Y', strtotime($dateF)) ?> to <?= date('M d, Y', strtotime($dateT)) ?> | Printed <?= date('M d, Y h:i A') ?></p>
        </div>
    </div>

    <div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead>
            <tr>
                <th>Receipt #</th>
                <th>Date</th>
                <th>Customer</th>
                <th class="text-center">Items</th>
                <th class="text-end">Subtotal</th>
                <th class="text-end">Discount</th>
                <th class="text-end">Total</th>
                <th>Payment</th>
                <th>Status</th>
                <th class="no-print">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($sales as $s):
            $rowCls = $s['status'] === 'Voided' ? 'table-secondary' : '';
            $sCls   = match($s['status']) {'Completed'=>'badge-healthy','Voided'=>'badge-sick',default=>'badge-treated'};
        ?>
        <tr class="<?= $rowCls ?>">
            <td><code style="font-size:.8rem"><?= htmlspecialchars($s['receipt_number']) ?></code></td>
            <td><?= date('M d, Y', strtotime($s['sale_date'])) ?></td>
            <td><?= htmlspecialchars($s['customer_name']) ?></td>
            <td class="text-center"><?= $s['item_count'] ?></td>
            <td class="text-end">₱<?= number_format($s['subtotal'],2) ?></td>
            <td class="text-end"><?= $s['discount_amount']>0 ? '-₱'.number_format($s['discount_amount'],2) : '—' ?></td>
            <td class="text-end fw-semibold text-success">₱<?= number_format($s['total_amount'],2) ?></td>
            <td><?= $s['payment_method'] ?></td>
            <td><span class="badge-custom <?= $sCls ?>"><?= $s['status'] ?></span></td>
            <td class="no-print">
                <a href="?id=<?= $s['id'] ?>" class="btn btn-xs btn-outline-primary" title="View & Print">
                    <i class="fa fa-eye"></i>
                </a>
                <?php if ($s['status'] === 'Completed'): ?>
                <a href="?void=<?= $s['id'] ?>" class="btn btn-xs btn-outline-danger" title="Void"
                   onclick="return confirm('Void this receipt?')">
                    <i class="fa fa-ban"></i>
                </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($sales)): ?>
        <tr><td colspan="10" class="text-center text-muted py-4">No receipts found.</td></tr>
        <?php endif; ?>
        </tbody>
        <?php if (!empty($sales)):
            $grandTotal = array_sum(array_column($sales,'total_amount'));
            $totalDisc  = array_sum(array_column($sales,'discount_amount'));
        ?>
        <tfoot>
            <tr class="table-success fw-bold">
                <td colspan="4">Total (<?= count($sales) ?> receipts)</td>
                <td></td>
                <td class="text-end"><?= $totalDisc>0?'-₱'.number_format($totalDisc,2):'—' ?></td>
                <td class="text-end">₱<?= number_format($grandTotal,2) ?></td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
