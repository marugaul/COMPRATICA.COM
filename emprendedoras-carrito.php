<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
require_once __DIR__ . '/includes/config.php'; // maneja sesión con el path correcto
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/shipping_emprendedoras.php';

// Agrupar carrito por vendedor
$cartItems = $_SESSION['emp_cart'] ?? [];
$groups    = [];
foreach ($cartItems as $item) {
    $sid = (int)$item['seller_id'];
    if (!isset($groups[$sid])) {
        $groups[$sid] = ['seller_name' => $item['seller_name'], 'items' => [], 'subtotal' => 0];
    }
    $groups[$sid]['items'][]   = $item;
    $groups[$sid]['subtotal'] += $item['qty'] * $item['price'];
}
$grandTotal = array_sum(array_column($groups, 'subtotal'));
$cartCount  = array_sum(array_column($cartItems, 'qty'));

// Cargar opciones de envío para cada vendedor
$pdo = db();
$sellerShipping = [];
foreach (array_keys($groups) as $sid) {
    $sellerShipping[$sid] = getShippingConfig($pdo, $sid);
}

// Calcular costo de envío ya guardado en sesión
$shippingChoices = $_SESSION['emp_shipping'] ?? [];
$shippingTotal   = 0;
foreach ($shippingChoices as $sid => $choice) {
    $shippingTotal += (int)($choice['zone_price'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Carrito | CompraTica Emprendedoras</title>
    <style>#cartButton{display:none!important;}</style>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/compratica-header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-wrap { max-width: 900px; margin: 40px auto; padding: 0 20px; }
        h1 { font-size: 1.8rem; font-weight: 800; color: #333; margin-bottom: 8px; }
        .back-link { color: #667eea; text-decoration: none; font-size: 0.9rem; }
        .back-link:hover { text-decoration: underline; }

        .seller-group {
            background: white; border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin-bottom: 24px; overflow: hidden;
        }
        .seller-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; padding: 14px 20px; display: flex; align-items: center; gap: 10px;
        }
        .seller-avatar {
            width: 36px; height: 36px; background: rgba(255,255,255,0.3);
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; font-weight: 700; font-size: 1rem; flex-shrink: 0;
        }
        .seller-header h3 { margin: 0; font-size: 1rem; }

        .cart-item {
            display: grid; grid-template-columns: 80px 1fr auto auto;
            gap: 16px; align-items: center; padding: 16px 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        .cart-item:last-child { border-bottom: none; }
        .cart-item img { width: 80px; height: 80px; object-fit: cover; border-radius: 10px; background: #f5f5f5; }
        .item-name { font-weight: 700; color: #333; margin-bottom: 4px; }
        .item-price { color: #667eea; font-weight: 700; font-size: 1.05rem; }
        .qty-control { display: flex; align-items: center; gap: 8px; }
        .qty-btn {
            width: 30px; height: 30px; border: 2px solid #e0e0e0; background: white;
            border-radius: 8px; cursor: pointer; font-size: 1rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        .qty-btn:hover { border-color: #667eea; color: #667eea; }
        .qty-val { min-width: 28px; text-align: center; font-weight: 700; }
        .remove-btn { color: #ef4444; background: none; border: none; cursor: pointer; font-size: 1.1rem; padding: 4px 8px; }
        .remove-btn:hover { opacity: 0.7; }
        .group-subtotal { text-align: right; padding: 12px 20px; color: #555; font-size: 0.95rem; border-top: 1px solid #f0f0f0; }
        .group-subtotal strong { color: #667eea; font-size: 1.1rem; }

        /* ── Shipping selector ── */
        .shipping-section {
            padding: 16px 20px;
            border-top: 2px solid #f0f0f0;
            background: #fafbff;
        }
        .shipping-section h4 {
            margin: 0 0 12px; font-size: .9rem; color: #374151; font-weight: 700;
            display: flex; align-items: center; gap: 7px;
        }
        .ship-options { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
        .ship-option {
            display: flex; align-items: center; gap: 8px;
            border: 2px solid #e5e7eb; border-radius: 10px; padding: 9px 14px;
            cursor: pointer; font-size: .88rem; font-weight: 600; color: #374151;
            background: white; transition: all .18s; user-select: none;
        }
        .ship-option:hover { border-color: #667eea; background: #f5f3ff; }
        .ship-option.selected { border-color: #667eea; background: #f0f0ff; color: #4f46e5; }
        .ship-option input[type=radio] { accent-color: #667eea; width: 16px; height: 16px; }

        .express-detail {
            background: white; border: 2px solid #e5e7eb; border-radius: 12px;
            padding: 14px 16px; margin-top: 10px;
        }
        .express-detail label { display: block; font-size: .82rem; font-weight: 600; color: #555; margin-bottom: 4px; }
        .express-detail input, .express-detail select {
            width: 100%; padding: 9px 13px; border: 2px solid #e0e0e0; border-radius: 8px;
            font-size: .9rem; box-sizing: border-box; margin-bottom: 10px;
        }
        .express-detail input:focus, .express-detail select:focus { border-color: #667eea; outline: none; }
        .express-cost-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: #fef3c7; color: #92400e; border-radius: 8px;
            padding: 5px 12px; font-size: .85rem; font-weight: 700;
        }
        .locate-btn {
            background: #f0f4ff; color: #667eea; border: 2px solid #c7d2fe;
            border-radius: 8px; padding: 7px 14px; font-size: .82rem; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
            transition: background .2s; margin-bottom: 10px;
        }
        .locate-btn:hover { background: #e0e7ff; }
        .shipping-row {
            display: flex; justify-content: space-between; margin-bottom: 6px;
            color: #555; font-size: .92rem;
        }
        .shipping-row.envio-cost { color: #f59e0b; font-weight: 600; }

        .summary-box {
            background: white; border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 24px; margin-top: 10px;
        }
        .summary-box h3 { margin: 0 0 16px; font-size: 1.1rem; color: #333; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 10px; color: #555; }
        .summary-row.total { font-weight: 800; font-size: 1.2rem; color: #333; border-top: 2px solid #f0f0f0; padding-top: 12px; margin-top: 4px; }
        .btn-checkout {
            display: block; width: 100%; margin-top: 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; padding: 16px; border-radius: 12px; border: none;
            font-size: 1.1rem; font-weight: 700; cursor: pointer; text-align: center;
            text-decoration: none; transition: all 0.3s;
        }
        .btn-checkout:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102,126,234,0.4); }
        .btn-checkout:disabled { opacity: .5; cursor: not-allowed; transform: none; }
        .empty-state { text-align: center; padding: 80px 20px; }
        .empty-state i { font-size: 5rem; color: #ddd; margin-bottom: 20px; display: block; }
        .note { font-size: 0.82rem; color: #888; margin-top: 10px; text-align: center; }

        @media (max-width: 600px) {
            .cart-item { grid-template-columns: 60px 1fr; }
            .cart-item img { width: 60px; height: 60px; }
            .qty-control, .remove-btn { grid-column: 2; justify-self: start; }
            .ship-options { flex-direction: column; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="page-wrap">
    <p><a href="emprendedoras-catalogo.php" class="back-link"><i class="fas fa-arrow-left"></i> Seguir comprando</a></p>
    <h1><i class="fas fa-shopping-bag" style="color:#667eea;"></i> Mi Carrito del Mercadito</h1>

    <?php if (empty($groups)): ?>
    <div class="empty-state">
        <i class="fas fa-shopping-bag"></i>
        <h3 style="color:#555;">Tu carrito está vacío</h3>
        <p style="color:#999;">Explora el mercadito y agrega productos de las emprendedoras.</p>
        <a href="emprendedoras-catalogo.php" style="display:inline-block;margin-top:16px;background:#667eea;color:white;padding:12px 28px;border-radius:10px;text-decoration:none;font-weight:700;">
            <i class="fas fa-store"></i> Ir al Mercadito
        </a>
    </div>
    <?php else: ?>

    <div id="cart-groups">
    <?php foreach ($groups as $sid => $group):
        $sc   = $sellerShipping[$sid] ?? [];
        $hasMethods = !empty($sc['enable_pickup']) || !empty($sc['enable_free_shipping']) || !empty($sc['enable_express']);
        $chosen = $shippingChoices[$sid] ?? null;
    ?>
        <div class="seller-group" data-seller="<?= $sid ?>">
            <div class="seller-header">
                <div class="seller-avatar"><?= strtoupper(substr($group['seller_name'], 0, 1)) ?></div>
                <h3><i class="fas fa-store"></i> <?= htmlspecialchars($group['seller_name']) ?></h3>
            </div>
            <?php foreach ($group['items'] as $item): ?>
            <div class="cart-item" data-pid="<?= $item['product_id'] ?>">
                <?php if ($item['image']): ?>
                    <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                <?php else: ?>
                    <div style="width:80px;height:80px;background:#f5f5f5;border-radius:10px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-image" style="color:#ccc;font-size:1.8rem;"></i></div>
                <?php endif; ?>
                <div>
                    <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                    <div class="item-price">₡<?= number_format($item['price'], 0) ?> c/u</div>
                    <div class="qty-control">
                        <button class="qty-btn" onclick="changeQty(<?= $item['product_id'] ?>, -1)">−</button>
                        <span class="qty-val" id="qty-<?= $item['product_id'] ?>"><?= $item['qty'] ?></span>
                        <button class="qty-btn" onclick="changeQty(<?= $item['product_id'] ?>, 1)">+</button>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-weight:800;color:#333;" id="line-<?= $item['product_id'] ?>">₡<?= number_format($item['qty'] * $item['price'], 0) ?></div>
                </div>
                <div>
                    <button class="remove-btn" onclick="removeItem(<?= $item['product_id'] ?>)" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if ($hasMethods): ?>
            <!-- ── Selector de envío para este vendedor ── -->
            <div class="shipping-section">
                <h4><i class="fas fa-truck" style="color:#667eea;"></i> Método de entrega</h4>
                <div class="ship-options" id="ship-opts-<?= $sid ?>">
                    <?php if (!empty($sc['enable_pickup'])): ?>
                    <label class="ship-option <?= ($chosen['method']??'') === 'pickup' ? 'selected' : '' ?>"
                           onclick="selectShipping(<?= $sid ?>, 'pickup', this)">
                        <input type="radio" name="ship_<?= $sid ?>" value="pickup"
                               <?= ($chosen['method']??'') === 'pickup' ? 'checked' : '' ?>>
                        <i class="fas fa-store" style="color:#667eea;"></i> Retiro en local
                        <span style="font-weight:400;color:#9ca3af;font-size:.82rem;">(Gratis)</span>
                    </label>
                    <?php endif; ?>

                    <?php if (!empty($sc['enable_free_shipping'])): ?>
                    <?php
                        $minFree = (int)($sc['free_shipping_min'] ?? 0);
                        $freeOk  = $minFree === 0 || $group['subtotal'] >= $minFree;
                    ?>
                    <label class="ship-option <?= ($chosen['method']??'') === 'free' ? 'selected' : '' ?> <?= !$freeOk ? 'disabled-ship' : '' ?>"
                           <?= !$freeOk ? 'title="Compra mínima ₡'.number_format($minFree,0).' para envío gratis"' : '' ?>
                           onclick="<?= $freeOk ? "selectShipping($sid, 'free', this)" : '' ?>">
                        <input type="radio" name="ship_<?= $sid ?>" value="free"
                               <?= ($chosen['method']??'') === 'free' ? 'checked' : '' ?>
                               <?= !$freeOk ? 'disabled' : '' ?>>
                        <i class="fas fa-gift" style="color:#10b981;"></i> Envío gratis
                        <?php if (!$freeOk && $minFree > 0): ?>
                        <span style="font-weight:400;color:#9ca3af;font-size:.78rem;">(min ₡<?= number_format($minFree,0) ?>)</span>
                        <?php endif; ?>
                    </label>
                    <?php endif; ?>

                    <?php if (!empty($sc['enable_express'])): ?>
                    <label class="ship-option <?= ($chosen['method']??'') === 'express' ? 'selected' : '' ?>"
                           onclick="selectShipping(<?= $sid ?>, 'express', this)">
                        <input type="radio" name="ship_<?= $sid ?>" value="express"
                               <?= ($chosen['method']??'') === 'express' ? 'checked' : '' ?>>
                        <i class="fas fa-shipping-fast" style="color:#f59e0b;"></i> Envío express
                    </label>
                    <?php endif; ?>
                </div>

                <?php if (!empty($sc['enable_pickup']) && !empty($sc['pickup_instructions'])): ?>
                <div id="pickup-info-<?= $sid ?>" style="<?= ($chosen['method']??'') !== 'pickup' ? 'display:none;' : '' ?>
                     background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:10px 14px;font-size:.85rem;color:#166534;margin-top:8px;">
                    <i class="fas fa-map-marker-alt"></i>
                    <?= htmlspecialchars($sc['pickup_instructions']) ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($sc['enable_express']) && !empty($sc['express_zones'])): ?>
                <div id="express-detail-<?= $sid ?>" style="<?= ($chosen['method']??'') !== 'express' ? 'display:none;' : '' ?>">
                    <div class="express-detail">
                        <button type="button" class="locate-btn" onclick="locateMe(<?= $sid ?>)">
                            <i class="fas fa-location-arrow"></i> Usar mi ubicación actual
                        </button>
                        <label>Dirección de entrega</label>
                        <input type="text" id="addr-<?= $sid ?>"
                               placeholder="Ej: 100m norte del parque, San José"
                               value="<?= htmlspecialchars($chosen['address'] ?? '') ?>"
                               oninput="saveExpressChoice(<?= $sid ?>)">
                        <label>Zona de entrega</label>
                        <select id="zone-<?= $sid ?>" onchange="saveExpressChoice(<?= $sid ?>)">
                            <option value="">— Selecciona tu zona —</option>
                            <?php foreach ($sc['express_zones'] as $zone): ?>
                            <option value="<?= htmlspecialchars($zone['name']) ?>"
                                    data-price="<?= (int)$zone['price'] ?>"
                                    <?= ($chosen['zone_name']??'') === $zone['name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($zone['name']) ?> — ₡<?= number_format($zone['price'], 0) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="express-cost-<?= $sid ?>">
                        <?php if (!empty($chosen['zone_price'])): ?>
                            <span class="express-cost-badge">
                                <i class="fas fa-truck"></i> Envío: ₡<?= number_format($chosen['zone_price'], 0) ?>
                            </span>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; /* hasMethods */ ?>

            <div class="group-subtotal">
                Subtotal de <strong><?= htmlspecialchars($group['seller_name']) ?></strong>:
                <strong>₡<?= number_format($group['subtotal'], 0) ?></strong>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <div class="summary-box">
        <h3><i class="fas fa-receipt"></i> Resumen</h3>
        <?php foreach ($groups as $sid => $group): ?>
        <div class="summary-row">
            <span><?= htmlspecialchars($group['seller_name']) ?></span>
            <span id="subtotal-<?= $sid ?>">₡<?= number_format($group['subtotal'], 0) ?></span>
        </div>
        <?php
            $ch = $shippingChoices[$sid] ?? null;
            if ($ch):
        ?>
        <div class="summary-row" id="ship-summary-<?= $sid ?>" style="color:#6b7280;font-size:.9rem;">
            <span>
                <i class="fas fa-<?= $ch['method']==='pickup'?'store':($ch['method']==='free'?'gift':'shipping-fast') ?>"></i>
                <?= $ch['method']==='pickup' ? 'Retiro en local' : ($ch['method']==='free' ? 'Envío gratis' : 'Envío express'.($ch['zone_name'] ? ' ('.$ch['zone_name'].')' : '')) ?>
            </span>
            <span id="ship-cost-<?= $sid ?>" style="color:<?= $ch['zone_price']>0?'#f59e0b':'#10b981' ?>">
                <?= $ch['zone_price']>0 ? '₡'.number_format($ch['zone_price'],0) : 'Gratis' ?>
            </span>
        </div>
        <?php else: ?>
        <div class="summary-row" id="ship-summary-<?= $sid ?>" style="display:none;color:#6b7280;font-size:.9rem;">
            <span></span><span id="ship-cost-<?= $sid ?>"></span>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
        <div class="summary-row total">
            <span>Total</span>
            <span id="grand-total">₡<?= number_format($grandTotal + $shippingTotal, 0) ?></span>
        </div>
        <p class="note"><i class="fas fa-info-circle"></i> El pago se realiza por separado a cada emprendedora</p>
        <a href="emprendedoras-checkout.php" class="btn-checkout" id="btn-checkout">
            <i class="fas fa-lock"></i> Proceder al Pago
        </a>
    </div>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
// Redirigir "Mi Carrito" del menú al carrito de emprendedoras
document.querySelectorAll('#hamburger-menu a').forEach(function(a) {
    if (a.getAttribute('href') === 'cart' || a.getAttribute('href') === '/cart') {
        a.setAttribute('href', '/emprendedoras-carrito.php');
    }
});

function changeQty(pid, delta) {
    const el  = document.getElementById('qty-' + pid);
    const cur = parseInt(el.textContent);
    const nxt = Math.max(0, cur + delta);
    if (nxt === 0) { removeItem(pid); return; }
    el.textContent = nxt;
    fetch('/api/emp-cart.php?action=update', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({product_id: pid, qty: nxt})
    }).then(r => r.json()).then(d => { if (d.ok) location.reload(); });
}

function removeItem(pid) {
    fetch('/api/emp-cart.php?action=remove', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({product_id: pid})
    }).then(r => r.json()).then(d => { if (d.ok) location.reload(); });
}

// ── Shipping ─────────────────────────────────────────────────────────────────

// subtotals per seller (for grand total recalc)
const sellerSubtotals = {
    <?php foreach ($groups as $sid => $g): ?>
    <?= $sid ?>: <?= $g['subtotal'] ?>,
    <?php endforeach; ?>
};
const shippingCosts = {};  // sid → cost in CRC

// Init from existing session choices
<?php foreach ($shippingChoices as $sid => $ch): ?>
shippingCosts[<?= $sid ?>] = <?= (int)($ch['zone_price'] ?? 0) ?>;
<?php endforeach; ?>

function selectShipping(sid, method, labelEl) {
    // Update UI
    document.querySelectorAll(`#ship-opts-${sid} .ship-option`).forEach(el => el.classList.remove('selected'));
    if (labelEl) labelEl.classList.add('selected');

    // Show/hide sub-panels
    const pickupInfo   = document.getElementById(`pickup-info-${sid}`);
    const expressDetail= document.getElementById(`express-detail-${sid}`);
    if (pickupInfo)    pickupInfo.style.display    = (method === 'pickup')  ? 'block' : 'none';
    if (expressDetail) expressDetail.style.display = (method === 'express') ? 'block' : 'none';

    if (method !== 'express') {
        // Save immediately
        const cost = 0;
        shippingCosts[sid] = cost;
        saveToSession(sid, method, '', '', 0);
        updateSummary(sid, method, '', 0);
    } else {
        // For express: wait for zone selection before saving
        updateSummary(sid, 'express', '', 0);
    }
}

function saveExpressChoice(sid) {
    const addrEl = document.getElementById(`addr-${sid}`);
    const zoneEl = document.getElementById(`zone-${sid}`);
    if (!addrEl || !zoneEl) return;

    const address   = addrEl.value.trim();
    const opt       = zoneEl.options[zoneEl.selectedIndex];
    const zoneName  = opt ? opt.value : '';
    const zonePrice = opt ? (parseInt(opt.dataset.price) || 0) : 0;

    // Update express cost badge
    const costDiv = document.getElementById(`express-cost-${sid}`);
    if (costDiv) {
        costDiv.innerHTML = zoneName
            ? `<span class="express-cost-badge"><i class="fas fa-truck"></i> Envío: ₡${zonePrice.toLocaleString('es-CR')}</span>`
            : '';
    }

    shippingCosts[sid] = zonePrice;
    saveToSession(sid, 'express', address, zoneName, zonePrice);
    updateSummary(sid, 'express', zoneName, zonePrice);
}

function saveToSession(sid, method, address, zoneName, zonePrice) {
    fetch('/api/emp-shipping.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            seller_id: sid, method, address,
            zone_name: zoneName, zone_price: zonePrice
        })
    }).catch(() => {});
}

function updateSummary(sid, method, zoneName, zonePrice) {
    // Update shipping row in summary
    const row  = document.getElementById(`ship-summary-${sid}`);
    const cost = document.getElementById(`ship-cost-${sid}`);
    if (row && cost) {
        const labels = { pickup: 'Retiro en local', free: 'Envío gratis', express: 'Envío express' };
        const icons  = { pickup: 'store', free: 'gift', express: 'shipping-fast' };
        const label  = labels[method] + (method === 'express' && zoneName ? ` (${zoneName})` : '');
        row.style.display = '';
        row.querySelector('span').innerHTML = `<i class="fas fa-${icons[method]}"></i> ${label}`;
        cost.textContent  = zonePrice > 0 ? `₡${zonePrice.toLocaleString('es-CR')}` : 'Gratis';
        cost.style.color  = zonePrice > 0 ? '#f59e0b' : '#10b981';
    }
    // Recalc grand total
    let total = Object.values(sellerSubtotals).reduce((a, b) => a + b, 0);
    Object.values(shippingCosts).forEach(c => total += c);
    const gt = document.getElementById('grand-total');
    if (gt) gt.textContent = `₡${total.toLocaleString('es-CR')}`;
}

// Geolocalización → rellenar campo dirección con Nominatim (OpenStreetMap)
function locateMe(sid) {
    if (!navigator.geolocation) {
        alert('Tu navegador no soporta geolocalización.');
        return;
    }
    const addrEl = document.getElementById(`addr-${sid}`);
    if (addrEl) { addrEl.placeholder = 'Detectando ubicación…'; addrEl.disabled = true; }
    navigator.geolocation.getCurrentPosition(
        pos => {
            const { latitude: lat, longitude: lng } = pos.coords;
            fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&accept-language=es`)
                .then(r => r.json())
                .then(d => {
                    const addr = d.display_name || `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
                    if (addrEl) {
                        addrEl.value = addr;
                        addrEl.disabled = false;
                        addrEl.placeholder = 'Dirección de entrega';
                        saveExpressChoice(sid);
                    }
                })
                .catch(() => {
                    if (addrEl) {
                        addrEl.value = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
                        addrEl.disabled = false;
                        addrEl.placeholder = 'Dirección de entrega';
                        saveExpressChoice(sid);
                    }
                });
        },
        err => {
            if (addrEl) { addrEl.disabled = false; addrEl.placeholder = 'Dirección de entrega'; }
            alert('No se pudo obtener tu ubicación. Por favor escribe la dirección manualmente.');
        },
        { timeout: 10000 }
    );
}
</script>
</body>
</html>
