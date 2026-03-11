<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
require_once __DIR__ . '/includes/config.php'; // maneja sesión con el path correcto

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Carrito | CompraTica Emprendedoras</title>
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
        .empty-state { text-align: center; padding: 80px 20px; }
        .empty-state i { font-size: 5rem; color: #ddd; margin-bottom: 20px; display: block; }
        .note { font-size: 0.82rem; color: #888; margin-top: 10px; text-align: center; }

        @media (max-width: 600px) {
            .cart-item { grid-template-columns: 60px 1fr; }
            .cart-item img { width: 60px; height: 60px; }
            .qty-control, .remove-btn { grid-column: 2; justify-self: start; }
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
    <?php foreach ($groups as $sid => $group): ?>
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
        <?php endforeach; ?>
        <div class="summary-row total">
            <span>Total</span>
            <span id="grand-total">₡<?= number_format($grandTotal, 0) ?></span>
        </div>
        <p class="note"><i class="fas fa-info-circle"></i> El pago se realiza por separado a cada emprendedora</p>
        <a href="emprendedoras-checkout.php" class="btn-checkout">
            <i class="fas fa-lock"></i> Proceder al Pago
        </a>
    </div>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
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
</script>
</body>
</html>
