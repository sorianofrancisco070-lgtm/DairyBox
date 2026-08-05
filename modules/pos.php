<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/check_low_stock.php';
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
        // Check and notify if any product is now low stock after sale
        checkLowStockNotifications($db);
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

// Load payment settings for cashless display
$paymentSettings = [];
try {
    $ps = $db->query("SELECT * FROM coop_payment_settings WHERE is_active=1 ORDER BY method")->fetchAll();
    foreach ($ps as $p) $paymentSettings[$p['method']] = $p;
} catch (Exception $e) { /* table may not exist yet */ }

include '../includes/header.php';
?>

<style>
/* ── POS Layout ── */
.pos-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 1rem;
    min-height: calc(100vh - 130px);
}

/* ── Product grid ── */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: .65rem;
}
.prod-card {
    background: #fff;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: .75rem .6rem;
    cursor: pointer;
    transition: border-color .15s, transform .15s, box-shadow .15s;
    text-align: center;
    position: relative;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}
.prod-card:hover  { border-color: #28a745; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(40,167,69,.15); }
.prod-card:active { transform: scale(.97); }
.prod-card.out-of-stock { opacity: .42; cursor: not-allowed; pointer-events: none; }
.prod-card .prod-name  { font-size: .8rem; font-weight: 600; color: #1a6b3c; margin: .3rem 0 .1rem; line-height: 1.2; }
.prod-card .prod-price { font-size: .98rem; font-weight: 700; color: #28a745; }
.prod-card .prod-stock { font-size: .68rem; color: #6c757d; }
.prod-card .prod-icon  { font-size: 1.7rem; }

/* Cart badge on product card */
.prod-card .cart-badge {
    position: absolute;
    top: -7px; right: -7px;
    background: #dc3545;
    color: #fff;
    border-radius: 50%;
    width: 22px; height: 22px;
    font-size: .7rem;
    font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    display: none;
    box-shadow: 0 1px 4px rgba(0,0,0,.2);
}
.prod-card.in-cart .cart-badge { display: flex; }
.prod-card.in-cart { border-color: #28a745; background: #f8fff9; }

/* ── Cart panel (desktop) ── */
.cart-section {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
    padding: 1rem;
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 130px);
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
}
.cart-items {
    min-height: 80px;
    width: 100%;
}
.cart-item {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .5rem .2rem;
    border-bottom: 1px solid #f0f0f0;
    font-size: .85rem;
    animation: slideInCart .15s ease;
    background: #fff;
}
@keyframes slideInCart { from { opacity:0; transform:translateX(-8px); } to { opacity:1; transform:none; } }
.cart-item:last-child { border-bottom: none; }
.cart-item .item-name { flex: 1; font-weight: 600; min-width: 0; }
.qty-btn {
    width: 28px; height: 28px;
    border-radius: 7px;
    border: 1.5px solid #dee2e6;
    background: #f8f9fa;
    cursor: pointer;
    font-weight: 700;
    font-size: .9rem;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    transition: background .12s;
}
.qty-btn:hover  { background: #28a745; color: #fff; border-color: #28a745; }
.qty-btn:active { transform: scale(.92); }

/* ── Totals ── */
.pos-totals { border-top: 2px solid #e9ecef; padding-top: .7rem; margin-top: .4rem; font-size: .87rem; }
.pos-totals .total-line { display: flex; justify-content: space-between; align-items: center; padding: .18rem 0; }
.pos-totals .grand-total { font-size: 1.2rem; font-weight: 700; color: #1a6b3c; }

/* ── Filters ── */
.cat-filter { overflow-x: auto; -webkit-overflow-scrolling: touch; flex-wrap: nowrap !important; padding-bottom: 4px; }
.cat-filter .btn { font-size: .76rem; padding: .22rem .55rem; white-space: nowrap; flex-shrink: 0; }
#searchProduct { font-size: .86rem; }

/* ═══════════════════════════════════════════════
   MOBILE: Cart becomes slide-up drawer
   ═══════════════════════════════════════════════ */
@media (max-width: 900px) {
    .pos-grid { grid-template-columns: 1fr; }

    /* Floating cart button */
    .cart-fab {
        position: fixed;
        bottom: calc(60px + 1rem);
        right: 1rem;
        z-index: 1050;
        background: #28a745;
        color: #fff;
        border: none;
        border-radius: 50px;
        padding: .65rem 1.2rem;
        font-size: .92rem;
        font-weight: 700;
        box-shadow: 0 4px 16px rgba(40,167,69,.4);
        display: flex;
        align-items: center;
        gap: .5rem;
        cursor: pointer;
        transition: transform .15s, box-shadow .15s;
    }
    .cart-fab:active { transform: scale(.96); }
    .cart-fab .fab-count {
        background: #dc3545;
        border-radius: 50%;
        width: 22px; height: 22px;
        font-size: .72rem;
        display: flex; align-items: center; justify-content: center;
    }

    /* Cart drawer */
    .cart-section {
        position: fixed;
        bottom: 0; left: 0; right: 0;
        z-index: 1055;
        border-radius: 18px 18px 0 0;
        max-height: 92vh;
        transform: translateY(100%);
        transition: transform .3s cubic-bezier(.4,0,.2,1);
        box-shadow: 0 -4px 24px rgba(0,0,0,.18);
        padding: 0 1rem 1.5rem;
        overflow-y: auto;           /* whole drawer scrolls */
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
    }
    .cart-section.open {
        transform: translateY(0);
    }
    .cart-drawer-handle {
        text-align: center;
        padding: .6rem 0 .4rem;
        cursor: pointer;
        position: sticky;           /* handle stays at top when scrolling */
        top: 0;
        background: #fff;
        z-index: 2;
    }
    .cart-drawer-handle::before {
        content: '';
        display: inline-block;
        width: 40px; height: 4px;
        background: #dee2e6;
        border-radius: 4px;
    }
    .cart-items {
        max-height: none;           /* no inner height limit */
        overflow-y: visible;
    }
    .cart-section-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.45);
        z-index: 1054;
        display: none;
    }
    .cart-section-overlay.show { display: block; }

    /* product grid on mobile */
    .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: .5rem;
    }
    .prod-card { padding: .6rem .4rem; }
    .prod-card .prod-icon { font-size: 1.5rem; }
    .prod-card .prod-name { font-size: .75rem; }
}

@media (min-width: 901px) {
    .cart-fab { display: none; }
    .cart-drawer-handle { display: none; }
    .cart-section-overlay { display: none !important; }
}
</style>

<div class="pos-grid">
    <!-- LEFT: Product Browser -->
    <div>
        <div class="card-section mb-3">
            <div class="section-title"><i class="fa fa-cash-register me-2"></i>Point of Sale</div>
            <div class="d-flex gap-2 mb-2">
                <input type="text" id="searchProduct" class="form-control form-control-sm" placeholder="🔍 Search products…">
            </div>
            <div class="cat-filter d-flex gap-1 mb-3" id="catFilter">
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
                    <div class="cart-badge" id="badge-<?= $p['id'] ?>">0</div>
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
    <div class="cart-section" id="cartSection">
        <!-- Mobile drawer handle -->
        <div class="cart-drawer-handle" onclick="closeCart()"></div>

        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-bold" style="color:#1a6b3c">
                <i class="fa fa-shopping-cart me-2"></i>Cart
                <span class="badge bg-success ms-1" id="cartCount">0</span>
            </div>
            <button class="btn btn-xs btn-outline-danger" onclick="clearCart()" id="clearBtn" style="display:none">
                <i class="fa fa-trash me-1"></i>Clear
            </button>
        </div>

        <div class="cart-items" id="cartItems">
            <div id="emptyCart" class="text-center py-4 text-muted">
                <i class="fa fa-cart-plus fa-2x mb-2 d-block" style="opacity:.35"></i>
                <span style="font-size:.85rem">Tap a product to add it to cart</span>
            </div>
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

        <div class="mt-2">
            <label class="form-label fw-semibold" style="font-size:.82rem">Customer Name</label>
            <input type="text" id="customerName" class="form-control form-control-sm mb-2" value="Walk-in Customer">

            <label class="form-label fw-semibold" style="font-size:.82rem">Payment Method</label>
            <select id="paymentMethod" class="form-select form-select-sm mb-2" onchange="onPaymentChange()">
                <option value="Cash">💵 Cash</option>
                <?php foreach ($paymentSettings as $m => $ps): ?>
                <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($ps['display_name']) ?></option>
                <?php endforeach; ?>
                <?php
                // Fallback options if no settings configured
                if (empty($paymentSettings)):
                ?>
                <option value="GCash">📱 GCash</option>
                <option value="Maya">📱 Maya</option>
                <option value="Bank Transfer">🏦 Bank Transfer</option>
                <option value="Credit">💳 Credit</option>
                <?php endif; ?>
            </select>

            <!-- Cash section -->
            <div id="cashSection">
                <label class="form-label fw-semibold" style="font-size:.82rem">Amount Tendered (₱)</label>
                <input type="number" id="amountTendered" min="0" step="0.01" value="0"
                       class="form-control form-control-sm mb-1" oninput="recalc()">
                <div class="d-flex justify-content-between" style="font-size:.82rem">
                    <span>Change:</span>
                    <strong class="text-success" id="changeVal">₱0.00</strong>
                </div>
            </div>

            <!-- Cashless payment details panel -->
            <div id="cashlessSection" style="display:none">
                <div id="cashlessPanel" class="mt-1 p-3 rounded text-center"
                     style="background:#f0fdf4;border:2px dashed #28a745">
                    <!-- filled by JS -->
                </div>
            </div>
        </div>

        <div class="mt-2 d-flex flex-column gap-2">
            <button class="btn btn-success w-100 fw-bold" onclick="processSale()" id="checkoutBtn" disabled>
                <i class="fa fa-check-circle me-1"></i>Process Sale
            </button>
            <button class="btn btn-outline-danger btn-sm w-100" onclick="clearCart()">
                <i class="fa fa-trash me-1"></i>Clear Cart
            </button>
        </div>
    </div>
</div>

<!-- Mobile Cart Overlay -->
<div class="cart-section-overlay" id="cartOverlay" onclick="closeCart()"></div>

<!-- Mobile Floating Cart Button -->
<button class="cart-fab d-md-none" onclick="openCart()" id="cartFab" style="display:none!important">
    <i class="fa fa-shopping-cart"></i>
    <span id="fabTotal">₱0.00</span>
    <span class="fab-count" id="fabCount">0</span>
</button>

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
const isMobile = () => window.innerWidth <= 900;

// Payment settings from PHP
const paymentSettings = <?= json_encode($paymentSettings) ?>;
const rootPath        = '<?= $root ?>';

// ── Payment method change ────────────────────────────────
function onPaymentChange() {
    const method   = document.getElementById('paymentMethod').value;
    const cashDiv  = document.getElementById('cashSection');
    const cashless = document.getElementById('cashlessSection');
    const panel    = document.getElementById('cashlessPanel');

    if (method === 'Cash') {
        cashDiv.style.display  = '';
        cashless.style.display = 'none';
        return;
    }

    cashDiv.style.display  = 'none';
    cashless.style.display = '';

    const ps = paymentSettings[method];
    if (!ps) {
        panel.innerHTML = `<p class="text-muted small mb-0"><i class="fa fa-info-circle me-1"></i>No payment details set up. <a href="payment_settings.php">Configure here →</a></p>`;
        return;
    }

    // Build QR + details display
    let html = `<div style="font-size:.88rem">`;

    // QR image
    if (ps.qr_image) {
        html += `
        <div class="mb-2">
            <img src="${rootPath}${ps.qr_image}?v=${Date.now()}"
                 alt="${ps.display_name} QR"
                 style="width:160px;height:160px;object-fit:contain;border:2px solid #d4edda;border-radius:10px;padding:6px;background:#fff">
            <div style="font-size:.72rem;color:#6c757d;margin-top:.2rem">Scan to pay</div>
        </div>`;
    }

    html += `<div class="fw-bold text-success" style="font-size:1rem">${escHtml(ps.display_name)}</div>`;

    if (ps.account_name) {
        html += `<div class="mt-1"><i class="fa fa-user me-1 text-muted"></i><strong>${escHtml(ps.account_name)}</strong></div>`;
    }
    if (ps.account_number) {
        html += `<div style="font-size:1.1rem;font-weight:700;color:#1a6b3c;letter-spacing:.5px">${escHtml(ps.account_number)}</div>`;
    }
    if (ps.instructions) {
        html += `<div class="mt-2 p-2 rounded" style="background:rgba(40,167,69,.08);font-size:.8rem;color:#555">${escHtml(ps.instructions)}</div>`;
    }

    html += `</div>`;
    panel.innerHTML = html;
}
function openCart() {
    document.getElementById('cartSection').classList.add('open');
    document.getElementById('cartOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeCart() {
    document.getElementById('cartSection').classList.remove('open');
    document.getElementById('cartOverlay').classList.remove('show');
    document.body.style.overflow = '';
}

// ── Category & Search filter ─────────────────────────────
document.getElementById('catFilter').addEventListener('click', e => {
    const btn = e.target.closest('[data-cat]');
    if (!btn) return;
    document.querySelectorAll('#catFilter button').forEach(b => {
        b.classList.remove('active','btn-success');
        b.classList.add('btn-outline-success');
    });
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

// ── Update product card badges ────────────────────────────
function updateCardBadges() {
    // Reset all
    document.querySelectorAll('.prod-card').forEach(c => {
        c.classList.remove('in-cart');
        const b = c.querySelector('.cart-badge');
        if (b) b.style.display = 'none';
    });
    // Set active
    cart.forEach(item => {
        const card  = document.querySelector(`.prod-card[data-id="${item.product_id}"]`);
        const badge = document.getElementById('badge-' + item.product_id);
        if (card)  card.classList.add('in-cart');
        if (badge) { badge.textContent = item.qty; badge.style.display = 'flex'; }
    });
}

// ── Cart logic ───────────────────────────────────────────
function addToCart(card) {
    if (card.classList.contains('out-of-stock')) return;
    const pid   = parseInt(card.dataset.id);
    const stock = parseFloat(card.dataset.stock);
    const exist = cart.find(i => i.product_id === pid);
    if (exist) {
        if (exist.qty >= stock) {
            // Flash red to indicate max stock
            card.style.borderColor = '#dc3545';
            setTimeout(() => card.style.borderColor = '', 600);
            return;
        }
        exist.qty++;
    } else {
        cart.push({
            product_id:    pid,
            name:          card.dataset.name,
            price:         parseFloat(card.dataset.price),
            unit:          card.dataset.unit,
            qty:           1,
            max_stock:     stock,
            item_discount: 0
        });
    }
    renderCart();

    // Brief visual feedback on card
    card.style.transform = 'scale(1.06)';
    setTimeout(() => card.style.transform = '', 180);
}

function renderCart() {
    const container = document.getElementById('cartItems');
    const totalQty  = cart.reduce((s, i) => s + i.qty, 0);

    document.getElementById('cartCount').textContent = totalQty;
    document.getElementById('checkoutBtn').disabled  = cart.length === 0;
    document.getElementById('clearBtn').style.display = cart.length ? '' : 'none';

    // FAB button
    const fab = document.getElementById('cartFab');
    if (cart.length > 0) {
        fab.style.removeProperty('display');
        document.getElementById('fabCount').textContent = totalQty;
    } else {
        fab.style.setProperty('display','none','important');
    }

    // Build inner HTML — always includes the empty state (hidden when not needed)
    if (cart.length === 0) {
        container.innerHTML = `
            <div id="emptyCart" class="text-center py-4 text-muted">
                <i class="fa fa-cart-plus fa-2x mb-2 d-block" style="opacity:.35"></i>
                <span style="font-size:.85rem">Tap a product to add it to cart</span>
            </div>`;
    } else {
        container.innerHTML = cart.map((item, idx) => `
            <div class="cart-item" id="cart-row-${idx}">
                <div class="item-name">
                    <span style="font-weight:700;font-size:.88rem">${escHtml(item.name)}</span><br>
                    <small class="text-muted">₱${item.price.toFixed(2)} / ${escHtml(item.unit)}</small>
                </div>
                <div class="d-flex align-items-center gap-1" style="flex-shrink:0">
                    <button class="qty-btn" onclick="changeQty(${idx},-1)">−</button>
                    <input type="number" min="1" max="${item.max_stock}" value="${item.qty}"
                           class="form-control form-control-sm p-0"
                           style="width:46px;text-align:center;font-weight:700;font-size:.9rem"
                           onchange="setQty(${idx},this.value)">
                    <button class="qty-btn" onclick="changeQty(${idx},1)">+</button>
                </div>
                <div class="text-end" style="min-width:72px;flex-shrink:0">
                    <strong class="text-success" style="font-size:.9rem">₱${(item.qty * item.price).toFixed(2)}</strong><br>
                    <button class="btn btn-xs btn-outline-danger mt-1"
                            onclick="removeItem(${idx})"
                            style="padding:.15rem .4rem;font-size:.7rem">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>
        `).join('');
    }

    updateCardBadges();
    recalc();
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function changeQty(idx, delta) {
    cart[idx].qty = Math.max(1, Math.min(cart[idx].qty + delta, cart[idx].max_stock));
    renderCart();
}
function setQty(idx, val) {
    cart[idx].qty = Math.max(1, Math.min(parseInt(val)||1, cart[idx].max_stock));
    renderCart();
}
function removeItem(idx) { cart.splice(idx, 1); renderCart(); }
function clearCart()     { cart = []; closeCart(); renderCart(); }

function recalc() {
    const subtotal = cart.reduce((s, i) => s + i.qty * i.price, 0);
    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const taxRate  = parseFloat(document.getElementById('taxInput').value) || 0;
    const tax      = subtotal * taxRate / 100;
    const total    = Math.max(0, subtotal - discount + tax);
    const tendered = parseFloat(document.getElementById('amountTendered').value) || 0;
    const change   = Math.max(0, tendered - total);

    document.getElementById('subtotalVal').textContent = '₱' + subtotal.toFixed(2);
    document.getElementById('totalVal').textContent    = '₱' + total.toFixed(2);
    document.getElementById('changeVal').textContent   = '₱' + change.toFixed(2);

    // Update FAB total
    const fab = document.getElementById('fabTotal');
    if (fab) fab.textContent = '₱' + total.toFixed(2);
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

    if (method === 'Cash' && tendered < total) {
        alert('Amount tendered is less than the total!');
        return;
    }

    const btn = document.getElementById('checkoutBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>Processing…';

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
                closeCart();
                showReceiptModal(data);
            } else {
                alert('Error: ' + data.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-check-circle me-1"></i>Process Sale';
            }
        })
        .catch(() => {
            alert('Network error. Please try again.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-check-circle me-1"></i>Process Sale';
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
            <td>${escHtml(i.name)}</td>
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
            <p class="mb-1"><strong>Customer:</strong> ${escHtml(customer)}</p>
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
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        <style>body{padding:1rem;font-family:monospace} table th{background:#1a6b3c;color:#fff}</style>
        </head><body>${body}<script>window.onload=()=>window.print()<\/script></body></html>`);
    w.document.close();
}

function saveReceiptPDF() {
    const body   = document.getElementById('receiptBody').innerHTML;
    const rcptNo = lastReceiptData.receipt_number || 'Receipt';
    const w = window.open('','','width=400,height=600');
    w.document.write(`<html><head><title>Receipt_${rcptNo}</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        <style>body{padding:1rem;font-family:monospace} table th{background:#1a6b3c;color:#fff}
        @media print { @page { size: A5 portrait; margin:10mm; } }</style>
        </head><body>${body}<script>window.onload=()=>window.print()<\/script></body></html>`);
    w.document.close();
}

// After receipt modal dismissed, clear cart
document.getElementById('receiptModal').addEventListener('hidden.bs.modal', () => clearCart());
</script>

<?php include '../includes/footer.php'; ?>
