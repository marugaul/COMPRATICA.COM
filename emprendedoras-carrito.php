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
        /* ── VARIABLES (mismo sistema que checkout) ── */
        :root {
            --primary: #1e293b;
            --primary-light: #334155;
            --accent: #3b82f6;
            --accent-green: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --purple: #8b5cf6;
            --gray-50:  #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-700: #334155;
            --gray-900: #0f172a;
            --white: #ffffff;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.10);
            --radius: 10px;
            --transition: all 0.22s cubic-bezier(0.4,0,0.2,1);
        }

        body { background: var(--gray-50); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

        /* ── PAGE ── */
        .page-wrap { max-width: 960px; margin: 0 auto; padding: 28px 20px 80px; }

        .back-link {
            display: inline-flex; align-items: center; gap: 6px;
            color: var(--gray-500); text-decoration: none; font-size: 0.875rem;
            padding: 6px 0; transition: var(--transition); margin-bottom: 20px;
        }
        .back-link:hover { color: var(--accent); }

        .cart-title {
            font-size: 1.55rem; font-weight: 800; color: var(--gray-900);
            display: flex; align-items: center; gap: 10px; margin-bottom: 4px;
        }
        .cart-title i { color: var(--accent); }
        .cart-subtitle { color: var(--gray-500); font-size: 0.9rem; margin-bottom: 28px; }

        /* ── LAYOUT ── */
        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 780px) {
            .cart-layout { grid-template-columns: 1fr; }
        }

        /* ── SELLER GROUP ── */
        .seller-group {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 14px;
            box-shadow: var(--shadow-md);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .seller-header {
            background: var(--primary);
            color: var(--white);
            padding: 14px 20px;
            display: flex; align-items: center; gap: 12px;
            position: relative;
        }
        .seller-header::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0;
            width: 4px; background: var(--accent);
        }
        .seller-avatar {
            width: 36px; height: 36px;
            background: rgba(255,255,255,0.15);
            border: 1.5px solid rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.95rem; flex-shrink: 0;
        }
        .seller-header h3 { margin: 0; font-size: 0.95rem; font-weight: 700; }

        /* ── CART ITEMS ── */
        .cart-item {
            display: grid;
            grid-template-columns: 72px 1fr auto auto;
            gap: 16px; align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid var(--gray-100);
            transition: background .15s;
        }
        .cart-item:last-child { border-bottom: none; }
        .cart-item:hover { background: var(--gray-50); }
        .cart-item img {
            width: 72px; height: 72px; object-fit: cover;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
        }
        .item-img-placeholder {
            width: 72px; height: 72px; background: var(--gray-100);
            border-radius: var(--radius); display: flex; align-items: center;
            justify-content: center; color: var(--gray-400); font-size: 1.5rem;
            border: 1px solid var(--gray-200);
        }
        .item-name  { font-weight: 700; color: var(--gray-900); font-size: 0.93rem; margin-bottom: 4px; }
        .item-price { color: var(--gray-500); font-size: 0.82rem; }
        .item-line-total { font-weight: 700; color: var(--gray-700); font-size: 0.95rem; white-space: nowrap; }

        .qty-control { display: flex; align-items: center; gap: 6px; margin-top: 8px; }
        .qty-btn {
            width: 28px; height: 28px;
            border: 1.5px solid var(--gray-200); background: var(--white);
            border-radius: 6px; cursor: pointer; font-size: 1rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            transition: var(--transition); color: var(--gray-700);
        }
        .qty-btn:hover { border-color: var(--accent); color: var(--accent); background: #eff6ff; }
        .qty-val { min-width: 26px; text-align: center; font-weight: 700; font-size: 0.9rem; color: var(--gray-900); }
        .remove-btn {
            color: var(--gray-400); background: none; border: none; cursor: pointer;
            font-size: 1rem; padding: 6px; border-radius: 6px; transition: var(--transition);
        }
        .remove-btn:hover { color: var(--danger); background: #fef2f2; }

        .group-subtotal {
            text-align: right; padding: 10px 20px;
            color: var(--gray-500); font-size: 0.875rem;
            border-top: 1px solid var(--gray-100);
            background: var(--gray-50);
        }
        .group-subtotal strong { color: var(--gray-900); font-size: 0.95rem; }

        /* ── SHIPPING SECTION ── */
        .shipping-section {
            padding: 16px 20px;
            border-top: 1px solid var(--gray-200);
        }
        .shipping-section h4 {
            margin: 0 0 12px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; color: var(--gray-400);
            display: flex; align-items: center; gap: 6px;
        }
        .ship-options { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        .ship-option {
            display: flex; align-items: center; gap: 7px;
            border: 1.5px solid var(--gray-200); border-radius: var(--radius);
            padding: 9px 14px; cursor: pointer;
            font-size: 0.85rem; font-weight: 600; color: var(--gray-700);
            background: var(--white); transition: var(--transition); user-select: none;
        }
        .ship-option:hover { border-color: var(--accent); background: #eff6ff; color: var(--accent); }
        .ship-option.selected { border-color: var(--accent); background: #eff6ff; color: var(--accent); }
        .ship-option input[type=radio] { accent-color: var(--accent); width: 15px; height: 15px; }
        .ship-option .ship-badge {
            font-size: 0.72rem; font-weight: 400; color: var(--gray-400);
        }

        .express-detail {
            background: var(--gray-50); border: 1.5px solid var(--gray-200);
            border-radius: var(--radius); padding: 14px 16px; margin-top: 10px;
        }
        .express-detail label {
            display: block; font-size: 0.8rem; font-weight: 600;
            color: var(--gray-700); margin-bottom: 4px;
        }
        .express-detail input, .express-detail select {
            width: 100%; padding: 9px 13px;
            border: 1.5px solid var(--gray-200); border-radius: 8px;
            font-size: 0.88rem; box-sizing: border-box;
            background: var(--white); color: var(--gray-900);
            margin-bottom: 10px; transition: var(--transition);
        }
        .express-detail input:focus, .express-detail select:focus {
            border-color: var(--accent); outline: none;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        .express-cost-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: #fef3c7; color: #92400e; border-radius: 8px;
            padding: 5px 12px; font-size: 0.82rem; font-weight: 700;
        }
        .locate-btn {
            background: var(--gray-100); color: var(--gray-700);
            border: 1.5px solid var(--gray-200); border-radius: 8px;
            padding: 7px 14px; font-size: 0.8rem; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
            transition: var(--transition); margin-bottom: 10px;
        }
        .locate-btn:hover { background: #eff6ff; border-color: var(--accent); color: var(--accent); }

        .pickup-info {
            background: #f0fdf4; border: 1px solid #86efac;
            border-radius: 8px; padding: 10px 14px;
            font-size: 0.83rem; color: #166534; margin-top: 8px;
        }

        /* ── SUMMARY CARD (sticky) ── */
        .summary-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 14px;
            box-shadow: var(--shadow-md);
            padding: 22px;
            position: sticky;
            top: 80px;
        }
        .summary-title {
            font-size: 0.78rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.08em; color: var(--gray-400); margin-bottom: 16px;
            display: flex; align-items: center; gap: 6px;
        }
        .summary-row {
            display: flex; justify-content: space-between;
            padding: 7px 0; color: var(--gray-500); font-size: 0.875rem;
            border-bottom: 1px solid var(--gray-100);
        }
        .summary-row:last-of-type { border-bottom: none; }
        .summary-row.ship-row { font-size: 0.82rem; color: var(--gray-400); }
        .summary-total {
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 0 0;
            margin-top: 10px;
            border-top: 2px solid var(--gray-200);
        }
        .summary-total-label { font-weight: 700; font-size: 0.93rem; color: var(--gray-700); }
        .summary-total-amount { font-weight: 800; font-size: 1.35rem; color: var(--gray-900); }

        .btn-checkout {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; margin-top: 18px;
            background: var(--primary); color: var(--white);
            padding: 15px; border-radius: var(--radius); border: none;
            font-size: 1rem; font-weight: 700; cursor: pointer;
            text-align: center; text-decoration: none;
            transition: var(--transition);
        }
        .btn-checkout:hover { background: var(--primary-light); box-shadow: var(--shadow-lg); transform: translateY(-1px); }
        .btn-checkout:disabled { opacity: .45; cursor: not-allowed; transform: none; box-shadow: none; }
        .checkout-note {
            font-size: 0.75rem; color: var(--gray-400); margin-top: 10px;
            text-align: center; display: flex; align-items: center; justify-content: center; gap: 5px;
        }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center; padding: 80px 20px;
            background: var(--white); border-radius: 14px;
            border: 1px solid var(--gray-200); box-shadow: var(--shadow-md);
        }
        .empty-state .empty-icon {
            width: 80px; height: 80px; background: var(--gray-100);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px; font-size: 2rem; color: var(--gray-400);
        }
        .empty-state h3 { color: var(--gray-700); margin-bottom: 8px; }
        .empty-state p { color: var(--gray-400); margin-bottom: 24px; font-size: 0.9rem; }
        .btn-go-shop {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--primary); color: var(--white);
            padding: 12px 28px; border-radius: var(--radius);
            text-decoration: none; font-weight: 700; font-size: 0.93rem;
            transition: var(--transition);
        }
        .btn-go-shop:hover { background: var(--primary-light); }

        @media (max-width: 600px) {
            .cart-item { grid-template-columns: 62px 1fr; gap: 12px; }
            .cart-item img, .item-img-placeholder { width: 62px; height: 62px; }
            .qty-control, .remove-btn { grid-column: 2; }
            .ship-options { flex-direction: column; }
            .summary-card { position: static; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="page-wrap">
    <a href="emprendedoras-catalogo.php" class="back-link"><i class="fas fa-arrow-left"></i> Seguir comprando</a>
    <h1 class="cart-title"><i class="fas fa-shopping-bag"></i> Mi Carrito</h1>
    <p class="cart-subtitle">Revisá tus productos antes de proceder al pago.</p>

    <?php if (empty($groups)): ?>
    <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-shopping-bag"></i></div>
        <h3>Tu carrito está vacío</h3>
        <p>Explorá el mercadito y agregá productos de vendedores ticos.</p>
        <a href="emprendedoras-catalogo.php" class="btn-go-shop"><i class="fas fa-store"></i> Ir al Mercadito</a>
    </div>
    <?php else: ?>

    <div class="cart-layout">
    <div id="cart-groups">
    <?php foreach ($groups as $sid => $group):
        $sc   = $sellerShipping[$sid] ?? [];
        $hasMethods = !empty($sc['enable_pickup']) || !empty($sc['enable_free_shipping']) || !empty($sc['enable_express']) || !empty($sc['enable_mooving']);
        $chosen = $shippingChoices[$sid] ?? null;
    ?>
        <div class="seller-group" data-seller="<?= $sid ?>">
            <div class="seller-header">
                <div class="seller-avatar"><?= strtoupper(substr($group['seller_name'], 0, 1)) ?></div>
                <h3><?= htmlspecialchars($group['seller_name']) ?></h3>
            </div>
            <?php foreach ($group['items'] as $item): ?>
            <div class="cart-item" data-pid="<?= $item['product_id'] ?>">
                <?php if ($item['image']): ?>
                    <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                <?php else: ?>
                    <div class="item-img-placeholder"><i class="fas fa-image"></i></div>
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
                <div class="item-line-total" id="line-<?= $item['product_id'] ?>">₡<?= number_format($item['qty'] * $item['price'], 0) ?></div>
                <button class="remove-btn" onclick="removeItem(<?= $item['product_id'] ?>)" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
            </div>
            <?php endforeach; ?>

            <?php if ($hasMethods): ?>
            <!-- ── Selector de envío para este vendedor ── -->
            <div class="shipping-section">
                <h4><i class="fas fa-truck"></i> Método de entrega</h4>
                <div class="ship-options" id="ship-opts-<?= $sid ?>">
                    <?php if (!empty($sc['enable_pickup'])): ?>
                    <label class="ship-option <?= ($chosen['method']??'') === 'pickup' ? 'selected' : '' ?>"
                           onclick="selectShipping(<?= $sid ?>, 'pickup', this)">
                        <input type="radio" name="ship_<?= $sid ?>" value="pickup"
                               <?= ($chosen['method']??'') === 'pickup' ? 'checked' : '' ?>>
                        <i class="fas fa-store"></i> Retiro en local
                        <span class="ship-badge">(Gratis)</span>
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

                    <?php if (!empty($sc['enable_mooving'])): ?>
                    <label class="ship-option <?= ($chosen['method']??'') === 'mooving' ? 'selected' : '' ?>"
                           onclick="selectShipping(<?= $sid ?>, 'mooving', this)">
                        <input type="radio" name="ship_<?= $sid ?>" value="mooving"
                               <?= ($chosen['method']??'') === 'mooving' ? 'checked' : '' ?>>
                        <i class="fas fa-motorcycle" style="color:#8b5cf6;"></i> Envío Mooving
                        <span style="font-weight:400;color:#8b5cf6;font-size:.78rem;">⭐ Nuevo</span>
                    </label>
                    <?php endif; ?>
                </div>

                <?php if (!empty($sc['enable_pickup']) && !empty($sc['pickup_instructions'])): ?>
                <div id="pickup-info-<?= $sid ?>" class="pickup-info" style="<?= ($chosen['method']??'') !== 'pickup' ? 'display:none;' : '' ?>">
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

                <?php if (!empty($sc['enable_mooving'])): ?>
                <div id="mooving-detail-<?= $sid ?>" style="<?= ($chosen['method']??'') !== 'mooving' ? 'display:none;' : '' ?>">
                    <div class="express-detail">
                        <button type="button" class="locate-btn" onclick="locateMe(<?= $sid ?>, true)">
                            <i class="fas fa-location-arrow"></i> Usar mi ubicación actual
                        </button>
                        <label>Dirección de entrega</label>
                        <input type="text" id="addr-mooving-<?= $sid ?>"
                               placeholder="Ej: 100m norte del parque, San José"
                               value="<?= htmlspecialchars($chosen['address'] ?? '') ?>"
                               oninput="requestMovingQuote(<?= $sid ?>)">
                        <div id="mooving-quote-<?= $sid ?>" style="margin-top:10px;">
                            <p style="color:#8b5cf6;font-size:.88rem;"><i class="fas fa-info-circle"></i> Ingresa tu dirección para calcular el costo de envío con Mooving</p>
                        </div>
                        <div id="mooving-cost-<?= $sid ?>">
                        <?php if (!empty($chosen['zone_price'])): ?>
                            <span class="express-cost-badge" style="background:#f3e8ff;color:#6b21a8;">
                                <i class="fas fa-motorcycle"></i> Envío Mooving: ₡<?= number_format($chosen['zone_price'], 0) ?>
                            </span>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; /* hasMethods */ ?>

            <div class="group-subtotal">
                Subtotal: <strong>₡<?= number_format($group['subtotal'], 0) ?></strong>
            </div>
        </div>
    <?php endforeach; ?>
    </div><!-- /.cart-groups -->

    <!-- SUMMARY STICKY CARD -->
    <div>
        <div class="summary-card">
            <div class="summary-title"><i class="fas fa-receipt"></i> Resumen del pedido</div>
            <?php foreach ($groups as $sid => $group): ?>
            <div class="summary-row">
                <span><?= htmlspecialchars($group['seller_name']) ?></span>
                <span id="subtotal-<?= $sid ?>">₡<?= number_format($group['subtotal'], 0) ?></span>
            </div>
            <?php
                $ch = $shippingChoices[$sid] ?? null;
                if ($ch):
            ?>
            <div class="summary-row ship-row" id="ship-summary-<?= $sid ?>">
                <span>
                    <i class="fas fa-<?= $ch['method']==='pickup'?'store':($ch['method']==='free'?'gift':'shipping-fast') ?>"></i>
                    <?= $ch['method']==='pickup' ? 'Retiro en local' : ($ch['method']==='free' ? 'Envío gratis' : 'Envío express'.($ch['zone_name'] ? ' ('.$ch['zone_name'].')' : '')) ?>
                </span>
                <span id="ship-cost-<?= $sid ?>" style="color:<?= $ch['zone_price']>0?'var(--warning)':'var(--accent-green)' ?>">
                    <?= $ch['zone_price']>0 ? '₡'.number_format($ch['zone_price'],0) : 'Gratis' ?>
                </span>
            </div>
            <?php else: ?>
            <div class="summary-row ship-row" id="ship-summary-<?= $sid ?>" style="display:none;">
                <span></span><span id="ship-cost-<?= $sid ?>"></span>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
            <div class="summary-total">
                <span class="summary-total-label">Total</span>
                <span class="summary-total-amount" id="grand-total">₡<?= number_format($grandTotal + $shippingTotal, 0) ?></span>
            </div>
            <a href="emprendedoras-checkout.php" class="btn-checkout" id="btn-checkout">
                <i class="fas fa-lock"></i> Proceder al Pago
            </a>
            <p class="checkout-note"><i class="fas fa-shield-alt"></i> Pago directo a cada vendedor/a</p>
        </div>
    </div>

    </div><!-- /.cart-layout -->
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
    const pickupInfo    = document.getElementById(`pickup-info-${sid}`);
    const expressDetail = document.getElementById(`express-detail-${sid}`);
    const moovingDetail = document.getElementById(`mooving-detail-${sid}`);
    if (pickupInfo)     pickupInfo.style.display    = (method === 'pickup')  ? 'block' : 'none';
    if (expressDetail)  expressDetail.style.display = (method === 'express') ? 'block' : 'none';
    if (moovingDetail)  moovingDetail.style.display = (method === 'mooving') ? 'block' : 'none';

    if (method !== 'express' && method !== 'mooving') {
        // Save immediately
        const cost = 0;
        shippingCosts[sid] = cost;
        saveToSession(sid, method, '', '', 0);
        updateSummary(sid, method, '', 0);
    } else if (method === 'express') {
        // For express: wait for zone selection before saving
        updateSummary(sid, 'express', '', 0);
    } else if (method === 'mooving') {
        // For mooving: show initial state
        updateSummary(sid, 'mooving', '', 0);
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
        const labels = { pickup: 'Retiro en local', free: 'Envío gratis', express: 'Envío express', mooving: 'Envío Mooving' };
        const icons  = { pickup: 'store', free: 'gift', express: 'shipping-fast', mooving: 'motorcycle' };
        const label  = labels[method] + ((method === 'express' || method === 'mooving') && zoneName ? ` (${zoneName})` : '');
        row.style.display = '';
        row.querySelector('span').innerHTML = `<i class="fas fa-${icons[method]}"></i> ${label}`;
        cost.textContent  = zonePrice > 0 ? `₡${zonePrice.toLocaleString('es-CR')}` : 'Gratis';
        cost.style.color  = zonePrice > 0 ? (method === 'mooving' ? '#8b5cf6' : '#f59e0b') : '#10b981';
    }
    // Recalc grand total
    let total = Object.values(sellerSubtotals).reduce((a, b) => a + b, 0);
    Object.values(shippingCosts).forEach(c => total += c);
    const gt = document.getElementById('grand-total');
    if (gt) gt.textContent = `₡${total.toLocaleString('es-CR')}`;
}

// Geolocalización → rellenar campo dirección con Nominatim (OpenStreetMap)
function locateMe(sid, isMooving = false) {
    if (!navigator.geolocation) {
        alert('Tu navegador no soporta geolocalización.');
        return;
    }
    const addrEl = document.getElementById(isMooving ? `addr-mooving-${sid}` : `addr-${sid}`);
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
                        addrEl.dataset.lat = lat;
                        addrEl.dataset.lng = lng;
                        if (isMooving) {
                            requestMovingQuote(sid);
                        } else {
                            saveExpressChoice(sid);
                        }
                    }
                })
                .catch(() => {
                    if (addrEl) {
                        addrEl.value = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
                        addrEl.disabled = false;
                        addrEl.placeholder = 'Dirección de entrega';
                        addrEl.dataset.lat = lat;
                        addrEl.dataset.lng = lng;
                        if (isMooving) {
                            requestMovingQuote(sid);
                        } else {
                            saveExpressChoice(sid);
                        }
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

// ── Mooving Integration ──────────────────────────────────────────────────────
let moovingQuoteTimers = {}; // sid → timeout id

function requestMovingQuote(sid) {
    // Debounce: esperar 1 segundo después de que el usuario deje de escribir
    if (moovingQuoteTimers[sid]) {
        clearTimeout(moovingQuoteTimers[sid]);
    }

    moovingQuoteTimers[sid] = setTimeout(() => {
        const addrEl = document.getElementById(`addr-mooving-${sid}`);
        const quoteDiv = document.getElementById(`mooving-quote-${sid}`);
        const costDiv = document.getElementById(`mooving-cost-${sid}`);

        if (!addrEl || !quoteDiv) return;

        const address = addrEl.value.trim();
        if (!address) {
            quoteDiv.innerHTML = '<p style="color:#8b5cf6;font-size:.88rem;"><i class="fas fa-info-circle"></i> Ingresa tu dirección para calcular el costo de envío</p>';
            return;
        }

        // Mostrar loading
        quoteDiv.innerHTML = '<p style="color:#8b5cf6;font-size:.88rem;"><i class="fas fa-spinner fa-spin"></i> Calculando costo de envío...</p>';

        // Obtener coordenadas si están disponibles, sino usar default
        const lat = parseFloat(addrEl.dataset.lat || 0);
        const lng = parseFloat(addrEl.dataset.lng || 0);

        const subtotal = sellerSubtotals[sid] || 0;

        const requestData = {
            seller_id: sid,
            destination_address: address,
            destination_lat: lat || 9.9281, // Default San José si no hay coordenadas
            destination_lng: lng || -84.0907,
            package_value: subtotal
        };

        fetch('/mooving/ajax_mooving_quote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok && data.price > 0) {
                const price = Math.round(data.price);
                const estimatedTime = data.estimated_time || 60;

                quoteDiv.innerHTML = `
                    <div style="background:#f3e8ff;border:2px solid #8b5cf6;border-radius:10px;padding:12px;margin-top:8px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                            <span style="font-weight:700;color:#6b21a8;"><i class="fas fa-motorcycle"></i> Costo de envío</span>
                            <span style="font-weight:800;font-size:1.1rem;color:#6b21a8;">₡${price.toLocaleString('es-CR')}</span>
                        </div>
                        <div style="font-size:.82rem;color:#7c3aed;">
                            <i class="fas fa-clock"></i> Tiempo estimado: ~${estimatedTime} minutos
                        </div>
                        ${data.is_estimate ? '<div style="font-size:.75rem;color:#9333ea;margin-top:4px;"><i class="fas fa-info-circle"></i> Precio estimado</div>' : ''}
                    </div>
                `;

                if (costDiv) {
                    costDiv.innerHTML = `
                        <span class="express-cost-badge" style="background:#f3e8ff;color:#6b21a8;">
                            <i class="fas fa-motorcycle"></i> Envío Mooving: ₡${price.toLocaleString('es-CR')}
                        </span>
                    `;
                }

                // Guardar en sesión
                shippingCosts[sid] = price;
                saveToSession(sid, 'mooving', address, 'Mooving', price);
                updateSummary(sid, 'mooving', 'Mooving', price);

            } else {
                quoteDiv.innerHTML = `<p style="color:#dc2626;font-size:.88rem;"><i class="fas fa-exclamation-triangle"></i> ${data.error || 'No se pudo calcular el costo'}</p>`;
            }
        })
        .catch(err => {
            quoteDiv.innerHTML = '<p style="color:#dc2626;font-size:.88rem;"><i class="fas fa-exclamation-triangle"></i> Error al obtener cotización. Intenta de nuevo.</p>';
            console.error('Mooving quote error:', err);
        });

    }, 1000); // Esperar 1 segundo después de que deje de escribir
}
</script>
</body>
</html>
