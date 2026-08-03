<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin('dairy_cooperative');

$root      = '../';
$pageTitle = 'Point of Sale';
$db        = getDB();
$user      = currentUser();

// ── AJAX: Process Sale ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_sale'])) {
    header('Content-Type: application/json');
    try {
        $items       = json_decode($_POST['items'] ?? '[]', true);
        $customer    = trim($_POST['customer_name'] ?? 'Walk-in Customer');
        $phone       = trim($_POST['customer_phone'] ?? '');
        $discount    = (float)($_POST['discount'] ?? 0);
        $tax_rate    = (float)($_POST['tax_rate'] ?? 0);
        $payment     = $_POST['payment_method'] ?? 'Cash';
        $tendered    = (float)($_POST['amount_tendered'] ?? 0);
        $notes       = trim($_POST['notes'] ?? '');

        if (empty($items)) { echo json_encode(['success'=>false,'message'=>'Cart is empty.']); exit; }

        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += (float)$item['qty'] * (float)$item['price'];
        }
        $tax_amount  = round($subtotal * $tax_rate / 100, 2);
        $total       = round($subtotal - $discount + $tax_amount, 2);
        $change      = max(0, $tendered - $total);
        $receiptNo   = 'RCP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

        $db->beginTransaction();

        // Insert sale header
        $db->prepare("INSERT INTO coop_sales (receipt_number,sale_date,customer_name,customer_phone,subtotal,discount_amount,tax_amount,total_amount,amount_tendered,change_amount,payment_method,notes,created_by)
                      VALUES (?,CURDATE(),?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$receiptNo, $customer ?: 'Walk-in Customer', $phone, $subtotal, $discount, $tax_amount, $total, $tendered, $change, $payment, $notes, $user['id']]);
        $saleId = $db->lastInsertId();

        // Insert line items + deduct stock
        foreach ($items as $item) {
            $pid   = (int)$item['product_id'];
            $qty   = (float)$item['qty'];
            $price = (float)$item['price'];
            $disc  = (float)($item['item_discount'] ?? 0);
            $line  = round($qty * $price - $disc, 2);

            $db->prepare("INSERT INTO coop_sale_items (sale_id,product_id,quantity,unit_price,discount,line_total) VALUES (?,?,?,?,?,?)")
               ->execute([$saleId, $pid, $qty, $price, $disc, $line]);

            // Deduct stock
            $db->prepare("UPDATE coop_products SET stock_qty = GREATEST(0, stock_qty - ?) WHERE id=?")->execute([$qty, $pid]);

            // Log inventory movement
            $db->prepare("INSERT INTO coop_inventory (product_id,movement_type,quantity,reference_id,notes,recorded_by) VALUES (?,?,?,?,?,?)")
               ->execute([$pid, 'Sale', $qty, $saleId, 'POS Sale '.$receiptNo, $user['id']]);
        }

        $db->commit();
        echo json_encode(['success'=>true,'sale_id'=>$saleId,'receipt_number'=>$receiptNo,'total'=>$total,'change'=>$change]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: Get products ────────────────────────────────────
if (isset($_GET['get_products'])) {
    header('Content-Type: application/json');
    $prods = $db->query("SELECT id,product_code,name,category,unit,selling_price,stock_qty FROM coop_products WHERE is_active=1 AND stock_qty>0 ORDER BY category,name")->fetchAll();
    echo json_encode($prods);
    exit;
}

$products = $db->query("SELECT id,product_code,name,category,unit,selling_price,stock_qty FROM coop_products WHERE is_active=1 ORDER BY category,name")->fetchAll();

include '../includes/header.php';
?>

<style>
.pos-grid { display: grid; grid-template-columns: 1fr 380px; gap: 1rem; min-height: calc(100vh - 120px); }
@media (max-width: 900px) { .pos-grid { grid-template-columns: 1fr; } }
.product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .7rem; }
.prod-card {
    background: #fff;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: .8rem .7rem;
    cursor: pointer;
    transition: border-color .15s, transform .15s;
    text-align: center;
}
.prod-card:hover { border-color: #28a745; transform: translateY(-2px); }
.prod-card.out-of-stock { opacity: .45; cursor: not-allowed; }
.prod-card .prod-name { font-size: .82rem; font-weight: 600; color: #1a6b3c; margin: .3rem 0 .1rem; }
.prod-card .prod-price { font-size: 1rem; font-weight: 700; color: #28a745; }
.prod-card .prod-stock { font-size: .7rem; color: #6c757d; }
.prod-card .prod-icon { font-size: 1.8rem; }
.cart-section { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.08); padding: 1rem; display: flex; flex-direction: column; }
.cart-items { flex: 1; overflow-y: auto; max-height: 340px; }
.cart-item { display: flex; align-items: center; gap: .5rem; padding: .4rem .2rem; border-bottom: 1px solid #f0f0f0; font-size: .85rem; }
.cart-item:last-child { border-bottom: none; }
.cart-item .item-name { flex: 1; font-weight: 600; }
.cart-item .item-qty input { width: 52px; text-align: center; }
.qty-btn { width: 26px; height: 26px; border-radius: 6px; border: 1px solid #dee2e6; background: #f8f9fa; cursor: pointer; font-weight: 700; line-height: 1; }
.qty-btn:hover { background: #28a745; color: #fff; border-color: #28a745; }
.pos-totals { border-top: 2px solid #e9ecef; padding-top: .8rem; margin-top: .5rem; font-size: .88rem; }
.pos-totals .total-line { display: flex; justify-content: space-between; padding: .18rem 0; }
.pos-totals .grand-total { font-size: 1.2rem; font-weight: 700; color: #1a6b3c; }
.cat-filter .btn { font-size: .78rem; padding: .25rem .6rem; }
#searchProduct { font-size: .85rem; }
</style>

<div class="pos-grid">
    <!-- LEFT: Product Browser -->
    <div>
        <div class="card-section mb-3">
            <div class="section-title"><i class="fa fa-cash-register me-2"></i>Point of Sale</div>
            <div class="d-flex gap-2 mb-2">
                <input type="text" id="searchProduct" class="form-control form-control-sm" placeholder="🔍 Search products…">
            </div>
            <div class="cat-filter d-flex flex-wrap gap-1 mb-3" id="catFilter">
                <button class="btn btn-success btn-sm active" data-cat="">All</button>
                <?php
                $cats = array_unique(array_column($products, 'category'));
                foreach ($cats as $c): ?>
                <button class="btn btn-outline-success btn-sm" data-cat="<?= htmlspecialchars($c) ?>"><?= $c ?></button>
                <?php endforeach; ?>
            </div>
            <div class="product-grid" id="productGrid">
                <?php foreach ($products as $p):
                    $icons = ['Milk'=>'🥛','Cheese'=>'🧀','Butter'=>'🧈','Yogurt'=>'🍶','Ice Cream'=>'🍦','By-Product'=>'📦','Other'=>'🛒'];
                    $icon  = $icons[$p['category']] ?? '📦';
                    $oos   = $p['stock_qty'] <= 0;
                ?>
                <div class="prod-card <?= $oos ? 'out-of-stock' : '' ?>"
                     data-id="<?= $p['id'] ?>"
                     data-name="<?= htmlspecialchars($p['name']) ?>"
                     data-price="<?= $p['selling_price'] ?>"
                     data-unit="<?= htmlspecialchars($p['unit']) ?>"
                     data-stock="<?= $p['stock_qty'] ?>"
                     data-cat="<?= htmlspecialchars($p['category']) ?>"
                     onclick="addToCart(this)">
                    <div class="prod-icon"><?= $icon ?></div>
                    <div class="prod-name"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="prod-price">₱<?= number_format($p['selling_price'], 2) ?></div>
                    <div class="prod-stock"><?= $oos ? '❌ Out of stock' : '📦 '.$p['stock_qty'].' '.htmlspecialchars($p['unit']) ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                <p class="text-muted">No products available. <a href="products.php?action=add">Add products first.</a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: Cart & Checkout -->
    <div class="cart-section">
        <div class="fw-bold mb-2" style="color:#1a6b3c"><i class="fa fa-shopping-cart me-2"></i>Cart
            <span class="badge bg-success ms-1" id="cartCount">0</span>
        </div>

        <div class="cart-items" id="cartItems">
            <p class="text-muted text-center py-3" id="emptyCart"><i class="fa fa-cart-plus fa-2x mb-2 d-block opacity-50"></i>Tap a product to add it</p>
        </div>

        <div class="pos-totals">
            <div class="total-line"><span>Subtotal</span><span id="subtotalVal">₱0.00</span></div>
            <div class="total-line">
                <span>Discount (₱)</span>
                <input type="number" id="discountInput" min="0" step="0.01" value="0"
                       class="form-control form-control-sm" style="width:90px;text-align:right" oninput="recalc()">
            </div>
            <div class="total-line">
                <span>Tax (%)</span>
                <input type="number" id="taxInput" min="0" max="100" step="0.5" value="0"
                       class="form-control form-control-sm" style="width:70px;text-align:right" oninput="recalc()">
            </div>
            <div class="total-line grand-total"><span>TOTAL</span><span id="totalVal">₱0.00</span></div>
        </div>

        <div class="mt-3">
            <label class="form-label fw-semibold" style="font-size:.82rem">Customer Name</label>
            <input type="text" id="customerName" class="form-control form-control-sm mb-2" value="Walk-in Customer">

            <label class="form-label fw-semibold" style="font-size:.82rem">Payment Method</label>
            <select id="paymentMethod" class="form-select form-select-sm mb-2">
                <option value="Cash">💵 Cash</option>
                <option value="GCash">📱 GCash</option>
                <option value="Maya">📱 Maya</option>
                <option value="Bank Transfer">🏦 Bank Transfer</option>
                <option value="Credit">💳 Credit</option>
            </select>

            <div id="cashSection">
                <label class="form-label fw-semibold" style="font-size:.82rem">Amount Tendered (₱)</label>
                <input type="number" id="amountTendered" min="0" step="0.01" value="0"
                       class="form-control form-control-sm mb-1" oninput="recalc()">
                <div class="d-flex justify-content-between" style="font-size:.82rem">
                    <span>Change:</span>
                    <strong class="text-success" id="changeVal">₱0.00</strong>
                </div>
            </div>
        </div>

        <div class="mt-3 d-flex flex-column gap-2">
            <button class="btn btn-success w-100 fw-bold" onclick="processSale()" id="checkoutBtn" disabled>
                <i class="fa fa-check-circle me-1"></i>Process Sale
            </button>
            <button class="btn btn-outline-danger btn-sm w-100" onclick="clearCart()">
                <i class="fa fa-trash me-1"></i>Clear Cart
            </button>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title text-success"><i class="fa fa-receipt me-2"></i>Sale Complete!</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="receiptBody"></div>
      <div class="modal-footer border-0 pt-0">
        <button class="btn btn-outline-secondary btn-sm" onclick="printReceipt()"><i class="fa fa-print me-1"></i>Print Receipt</button>
        <button class="btn btn-primary btn-sm" onclick="saveReceiptPDF()"><i class="fa fa-file-pdf me-1"></i>Save PDF</button>
        <button class="btn btn-success btn-sm" data-bs-dismiss="modal" onclick="clearCart()">New Sale</button>
      </div>
    </div>
  </div>
</div>

<script>
let cart = [];

// ── Category & Search filter ─────────────────────────────
document.getElementById('catFilter').addEventListener('click', e => {
    const btn = e.target.closest('[data-cat]');
    if (!btn) return;
    document.querySelectorAll('#catFilter button').forEach(b => b.classList.remove('active','btn-success'));
    btn.classList.add('active','btn-success');
    btn.classList.remove('btn-outline-success');
    filterProducts();
});

document.getElementById('searchProduct').addEventListener('input', filterProducts);

function filterProducts() {
    const cat   = document.querySelector('#catFilter .active')?.dataset.cat ?? '';
    const query = document.getElementById('searchProduct').value.toLowerCase();
    document.querySelectorAll('.prod-card').forEach(card => {
        const matchCat  = !cat   || card.dataset.cat === cat;
        const matchName = !query || card.dataset.name.toLowerCase().includes(query);
        card.style.display = (matchCat && matchName) ? '' : 'none';
    });
}

// ── Cart logic ───────────────────────────────────────────
function addToCart(card) {
    if (card.classList.contains('out-of-stock')) return;
    const pid   = parseInt(card.dataset.id);
    const stock = parseFloat(card.dataset.stock);
    const exist = cart.find(i => i.product_id === pid);
    if (exist) {
        if (exist.qty >= stock) { alert('Not enough stock!'); return; }
        exist.qty++;
    } else {
        cart.push({ product_id: pid, name: card.dataset.name, price: parseFloat(card.dataset.price), unit: card.dataset.unit, qty: 1, max_stock: stock, item_discount: 0 });
    }
    renderCart();
}

function renderCart() {
    const container = document.getElementById('cartItems');
    const empty     = document.getElementById('emptyCart');
    document.getElementById('cartCount').textContent = cart.reduce((s,i)=>s+i.qty,0);
    document.getElementById('checkoutBtn').disabled = cart.length === 0;

    if (cart.length === 0) { container.innerHTML = ''; container.appendChild(empty); recalc(); return; }
    empty.style.display = 'none';

    container.innerHTML = cart.map((item, idx) => `
        <div class="cart-item">
            <div class="item-name">${item.name}<br><small class="text-muted">₱${item.price.toFixed(2)} / ${item.unit}</small></div>
            <div class="d-flex align-items-center gap-1 item-qty">
                <button class="qty-btn" onclick="changeQty(${idx},-1)">−</button>
                <input type="number" min="1" max="${item.max_stock}" value="${item.qty}"
                       class="form-control form-control-sm p-0"
                       style="width:50px;text-align:center"
                       onchange="setQty(${idx},this.value)">
                <button class="qty-btn" onclick="changeQty(${idx},1)">+</button>
            </div>
            <div class="text-end" style="min-width:72px">
                <strong>₱${(item.qty * item.price).toFixed(2)}</strong><br>
                <button class="btn btn-xs btn-outline-danger mt-1" onclick="removeItem(${idx})"><i class="fa fa-times"></i></button>
            </div>
        </div>
    `).join('');
    recalc();
}

function changeQty(idx, delta) {
    cart[idx].qty = Math.max(1, Math.min(cart[idx].qty + delta, cart[idx].max_stock));
    renderCart();
}
function setQty(idx, val) {
    cart[idx].qty = Math.max(1, Math.min(parseInt(val)||1, cart[idx].max_stock));
    renderCart();
}
function removeItem(idx) { cart.splice(idx,1); renderCart(); }
function clearCart()     { cart = []; renderCart(); }

function recalc() {
    const subtotal  = cart.reduce((s,i) => s + i.qty * i.price, 0);
    const discount  = parseFloat(document.getElementById('discountInput').value) || 0;
    const taxRate   = parseFloat(document.getElementById('taxInput').value) || 0;
    const tax       = subtotal * taxRate / 100;
    const total     = Math.max(0, subtotal - discount + tax);
    const tendered  = parseFloat(document.getElementById('amountTendered').value) || 0;
    const change    = Math.max(0, tendered - total);

    document.getElementById('subtotalVal').textContent = '₱' + subtotal.toFixed(2);
    document.getElementById('totalVal').textContent    = '₱' + total.toFixed(2);
    document.getElementById('changeVal').textContent   = '₱' + change.toFixed(2);
}

// ── Payment method toggle ────────────────────────────────
document.getElementById('paymentMethod').addEventListener('change', function() {
    document.getElementById('cashSection').style.display = this.value === 'Cash' ? '' : 'none';
});

// ── Process Sale ─────────────────────────────────────────
function processSale() {
    if (cart.length === 0) return;
    const total    = parseFloat(document.getElementById('totalVal').textContent.replace('₱',''));
    const tendered = parseFloat(document.getElementById('amountTendered').value) || 0;
    const method   = document.getElementById('paymentMethod').value;

    if (method === 'Cash' && tendered < total) { alert('Amount tendered is less than total!'); return; }

    document.getElementById('checkoutBtn').disabled = true;
    document.getElementById('checkoutBtn').innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Processing…';

    const fd = new FormData();
    fd.append('ajax_sale',       '1');
    fd.append('items',           JSON.stringify(cart));
    fd.append('customer_name',   document.getElementById('customerName').value);
    fd.append('discount',        document.getElementById('discountInput').value);
    fd.append('tax_rate',        document.getElementById('taxInput').value);
    fd.append('payment_method',  method);
    fd.append('amount_tendered', tendered);

    fetch('pos.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showReceiptModal(data);
        } else {
            alert('Error: ' + data.message);
            document.getElementById('checkoutBtn').disabled = false;
            document.getElementById('checkoutBtn').innerHTML = '<i class="fa fa-check-circle me-1"></i>Process Sale';
        }
    })
    .catch(() => {
        alert('Network error. Please try again.');
        document.getElementById('checkoutBtn').disabled = false;
        document.getElementById('checkoutBtn').innerHTML = '<i class="fa fa-check-circle me-1"></i>Process Sale';
    });
}

// ── Receipt Modal ────────────────────────────────────────
let lastReceiptData = {};
function showReceiptModal(data) {
    lastReceiptData = data;
    const subtotal = cart.reduce((s,i) => s + i.qty * i.price, 0);
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const taxRate  = parseFloat(document.getElementById('taxInput').value) || 0;
    const customer = document.getElementById('customerName').value;
    const method   = document.getElementById('paymentMethod').value;
    const items    = [...cart];

    const rows = items.map(i => `
        <tr>
            <td>${i.name}</td>
            <td class="text-center">${i.qty}</td>
            <td class="text-end">₱${i.price.toFixed(2)}</td>
            <td class="text-end">₱${(i.qty*i.price).toFixed(2)}</td>
        </tr>`).join('');

    document.getElementById('receiptBody').innerHTML = `
        <div style="font-family:monospace;font-size:.88rem">
            <div class="text-center mb-2">
                <strong style="font-size:1.1rem">🐃 DairyBox Cooperative</strong><br>
                <small>${new Date().toLocaleString()}</small><br>
                <strong>Receipt #: ${data.receipt_number}</strong>
            </div>
            <hr>
            <p class="mb-1"><strong>Customer:</strong> ${customer}</p>
            <p class="mb-1"><strong>Payment:</strong> ${method}</p>
            <hr>
            <table class="table table-sm mb-0">
                <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead>
                <tbody>${rows}</tbody>
            </table>
            <hr>
            <div class="d-flex justify-content-between"><span>Subtotal:</span><strong>₱${subtotal.toFixed(2)}</strong></div>
            ${discount > 0 ? `<div class="d-flex justify-content-between text-danger"><span>Discount:</span><strong>-₱${discount.toFixed(2)}</strong></div>` : ''}
            ${taxRate > 0 ? `<div class="d-flex justify-content-between"><span>Tax (${taxRate}%):</span><strong>₱${(subtotal*taxRate/100).toFixed(2)}</strong></div>` : ''}
            <div class="d-flex justify-content-between fw-bold" style="font-size:1.1rem;border-top:2px solid #dee2e6;padding-top:.3rem;margin-top:.3rem">
                <span>TOTAL:</span><span class="text-success">₱${data.total.toFixed(2)}</span>
            </div>
            ${method==='Cash' ? `<div class="d-flex justify-content-between"><span>Tendered:</span><span>₱${parseFloat(document.getElementById('amountTendered').value).toFixed(2)}</span></div>
            <div class="d-flex justify-content-between fw-bold text-primary"><span>Change:</span><span>₱${data.change.toFixed(2)}</span></div>` : ''}
            <hr>
            <p class="text-center text-muted mb-0" style="font-size:.78rem">Thank you for your purchase!<br>DairyBox – Fresh from the farm.</p>
        </div>`;
    new bootstrap.Modal(document.getElementById('receiptModal')).show();
}

function printReceipt() {
    const body = document.getElementById('receiptBody').innerHTML;
    const w = window.open('','','width=400,height=600');
    w.document.write(`<html><head><title>Receipt</title>
        <link rel="stylesheet" href="<?= $root ?>assets/css/bootstrap.min.css">
        <style>body{padding:1rem;font-family:monospace} table th{background:#1a6b3c;color:#fff}</style>
        </head><body>${body}<script>window.onload=()=>window.print()<\/script></body></html>`);
    w.document.close();
}

function saveReceiptPDF() {
    const body = document.getElementById('receiptBody').innerHTML;
    const rcptNo = lastReceiptData.receipt_number || 'Receipt';
    const w = window.open('','','width=400,height=600');
    w.document.write(`<html><head><title>Receipt_${rcptNo}</title>
        <link rel="stylesheet" href="<?= $root ?>assets/css/bootstrap.min.css">
        <style>body{padding:1rem;font-family:monospace} table th{background:#1a6b3c;color:#fff}
        @media print { @page { size: A5 portrait; margin: 10mm; } }</style>
        </head><body>${body}<script>window.onload=()=>window.print()<\/script></body></html>`);
    w.document.close();
}
</script>

<?php include '../includes/footer.php'; ?>
