<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$isLoggedIn = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
$userName   = $_SESSION['name'] ?? 'Usuario';
$cantidadProductos = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $it) $cantidadProductos += (int)($it['qty'] ?? 0);
}

$sid = (int)($_GET['id'] ?? 0);
if ($sid <= 0) { header('Location: emprendedoras-catalogo.php'); exit; }

$pdo = db();

// Datos del vendedor
$stSeller = $pdo->prepare("
    SELECT u.id, u.name, u.email,
           COALESCE(u.is_live,0)    AS is_live,
           u.live_title, u.live_link,
           COUNT(p.id)              AS product_count,
           SUM(p.sales_count)       AS total_sales
    FROM users u
    LEFT JOIN entrepreneur_products p ON p.user_id = u.id AND p.is_active = 1
    WHERE u.id = ?
    GROUP BY u.id
");
$stSeller->execute([$sid]);
$seller = $stSeller->fetch(PDO::FETCH_ASSOC);
if (!$seller) { header('Location: emprendedoras-catalogo.php'); exit; }

// Categorías del vendedor
$categories = $pdo->prepare("
    SELECT DISTINCT c.id, c.name
    FROM entrepreneur_products p
    JOIN entrepreneur_categories c ON c.id = p.category_id
    WHERE p.user_id = ? AND p.is_active = 1
    ORDER BY c.name
");
$categories->execute([$sid]);
$categories = $categories->fetchAll(PDO::FETCH_ASSOC);

// Filtros
$catFilter = (int)($_GET['cat'] ?? 0);
$search    = trim($_GET['q'] ?? '');

// Productos del vendedor
$sql = "SELECT p.id, p.name, p.price, p.stock, p.image_1, p.featured,
               p.views_count, p.sales_count, p.description,
               c.name AS category_name
        FROM entrepreneur_products p
        LEFT JOIN entrepreneur_categories c ON c.id = p.category_id
        WHERE p.is_active = 1 AND p.user_id = ?";
$params = [$sid];
if ($catFilter > 0) { $sql .= " AND p.category_id = ?"; $params[] = $catFilter; }
if ($search !== '') {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
$sql .= " ORDER BY p.featured DESC, p.sales_count DESC, p.id DESC";
$stProds = $pdo->prepare($sql);
$stProds->execute($params);
$products = $stProds->fetchAll(PDO::FETCH_ASSOC);

// Carrito
$empCartCount = 0;
foreach ($_SESSION['emp_cart'] ?? [] as $it) $empCartCount += (int)$it['qty'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($seller['name']) ?> | Mercadito Emprendedoras</title>
    <style>#cartButton{display:none!important;}</style>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/compratica-header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Toldo de la tienda */
        .store-awning {
            height: 110px;
            background: repeating-linear-gradient(
                90deg,
                #667eea 0px, #667eea 40px,
                #764ba2 40px, #764ba2 80px
            );
            position: relative;
        }
        .store-awning::after {
            content: '';
            position: absolute;
            bottom: -16px; left: 0; right: 0;
            height: 22px;
            background: radial-gradient(circle at 50% 0%, transparent 12px, #f9f9f9 12px);
            background-size: 30px 22px;
        }

        .store-header {
            max-width: 960px; margin: 0 auto; padding: 48px 20px 24px;
            display: flex; align-items: flex-start; gap: 24px;
        }
        .store-avatar {
            width: 80px; height: 80px; border-radius: 50%; flex-shrink: 0;
            background: linear-gradient(135deg,#667eea,#764ba2);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.2rem; font-weight: 800; color: white;
            box-shadow: 0 6px 20px rgba(102,126,234,.35);
            border: 4px solid white;
            margin-top: -60px;
        }
        .store-meta h1 { font-size: 1.8rem; font-weight: 800; color: #222; margin: 0 0 6px; }
        .store-stats { display: flex; gap: 20px; font-size: 0.88rem; color: #777; flex-wrap: wrap; }
        .store-stats span { display: flex; align-items: center; gap: 5px; }

        .live-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: #ef4444; color: white;
            padding: 6px 14px; border-radius: 20px; font-size: 0.82rem;
            font-weight: 700; text-decoration: none; margin-left: 8px;
        }
        .live-dot { width: 9px; height: 9px; background: white; border-radius: 50%; animation: pls 1.2s infinite; }
        @keyframes pls { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }

        /* Filtros */
        .store-filters {
            max-width: 960px; margin: 0 auto 28px; padding: 0 20px;
            display: flex; gap: 12px; flex-wrap: wrap; align-items: center;
        }
        .store-filters form { display: flex; gap: 10px; flex: 1; flex-wrap: wrap; }
        .store-filters input {
            flex: 1; min-width: 180px; padding: 10px 16px;
            border: 2px solid #e0e0e0; border-radius: 10px; font-size: 0.9rem;
        }
        .store-filters select {
            padding: 10px 14px; border: 2px solid #e0e0e0; border-radius: 10px;
            font-size: 0.9rem; background: white;
        }
        .store-filters button {
            background: linear-gradient(135deg,#667eea,#764ba2); color: white;
            border: none; padding: 10px 20px; border-radius: 10px;
            font-weight: 700; cursor: pointer;
        }

        /* Grid productos */
        .store-products {
            max-width: 960px; margin: 0 auto; padding: 0 20px 80px;
            display: grid; grid-template-columns: repeat(auto-fill,minmax(240px,1fr)); gap: 24px;
        }
        .store-product-card {
            background: white; border-radius: 14px;
            box-shadow: 0 4px 16px rgba(0,0,0,.08);
            overflow: hidden; text-decoration: none; color: inherit;
            transition: all .25s; display: block;
        }
        .store-product-card:hover { transform: translateY(-6px); box-shadow: 0 12px 32px rgba(0,0,0,.15); }
        .store-product-card img {
            width: 100%; height: 200px; object-fit: cover;
        }
        .store-product-noimg {
            width: 100%; height: 200px; background: #f5f5f5;
            display: flex; align-items: center; justify-content: center;
        }
        .spc-body { padding: 14px 16px; }
        .spc-cat  { font-size: 0.75rem; color: #667eea; font-weight: 700; margin-bottom: 4px; }
        .spc-name { font-size: 0.98rem; font-weight: 700; color: #222; margin-bottom: 6px; line-height: 1.3; }
        .spc-price { font-size: 1.25rem; font-weight: 800; color: #667eea; margin-bottom: 10px; }
        .spc-add {
            display: block; width: 100%; padding: 9px;
            background: linear-gradient(135deg,#667eea,#764ba2);
            color: white; border: none; border-radius: 8px;
            font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: all .2s;
        }
        .spc-add:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(102,126,234,.4); }
        .spc-add:disabled { opacity: .6; cursor: not-allowed; transform: none; }
        .spc-nostock { text-align: center; color: #ef4444; font-size: 0.8rem; font-weight: 700; padding: 8px 0 0; }

        /* Empty */
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 4rem; color: #ddd; display: block; margin-bottom: 16px; }

        /* FAB */
        .emp-cart-fab {
            position: fixed; bottom: 28px; right: 28px; z-index: 999;
            background: linear-gradient(135deg,#667eea,#764ba2);
            color: white; width: 62px; height: 62px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 6px 20px rgba(102,126,234,.5);
            text-decoration: none; font-size: 1.4rem; transition: transform .2s;
        }
        .emp-cart-fab:hover { transform: scale(1.1); }
        .fab-count {
            position: absolute; top: -4px; right: -4px;
            background: #ef4444; color: white; border-radius: 50%;
            width: 22px; height: 22px; font-size: 0.72rem; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
        }
        .catalog-toast {
            position: fixed; bottom: 104px; right: 28px; z-index: 9999;
            background: #111; color: white; padding: 12px 18px; border-radius: 10px;
            font-weight: 600; font-size: 0.9rem; box-shadow: 0 6px 20px rgba(0,0,0,.3);
            transform: translateY(60px); opacity: 0; transition: all .3s; pointer-events: none;
        }
        .catalog-toast.show { transform: translateY(0); opacity: 1; }

        @media (max-width: 540px) {
            .store-header { flex-direction: column; }
            .store-avatar { margin-top: -50px; }
            .store-products { grid-template-columns: repeat(2,1fr); gap: 14px; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<!-- Toldo de la tienda -->
<div class="store-awning"></div>

<div class="store-header">
    <div class="store-avatar"><?= strtoupper(mb_substr($seller['name'], 0, 1)) ?></div>
    <div class="store-meta">
        <h1>
            <?= htmlspecialchars($seller['name']) ?>
            <?php if ($seller['is_live']): ?>
                <a href="<?= htmlspecialchars($seller['live_link'] ?? '#') ?>" target="_blank" class="live-badge">
                    <span class="live-dot"></span>
                    <?= htmlspecialchars($seller['live_title'] ?: 'EN VIVO') ?>
                </a>
            <?php endif; ?>
        </h1>
        <div class="store-stats">
            <span><i class="fas fa-box" style="color:#667eea;"></i> <?= (int)$seller['product_count'] ?> productos</span>
            <span><i class="fas fa-shopping-cart" style="color:#667eea;"></i> <?= number_format((int)$seller['total_sales']) ?> ventas</span>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="store-filters">
    <form method="GET">
        <input type="hidden" name="id" value="<?= $sid ?>">
        <input type="text" name="q" placeholder="Buscar en esta tienda…" value="<?= htmlspecialchars($search) ?>">
        <?php if (!empty($categories)): ?>
        <select name="cat" onchange="this.form.submit()">
            <option value="0">Todas las categorías</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $catFilter == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button type="submit"><i class="fas fa-search"></i></button>
    </form>
    <a href="emprendedoras-catalogo.php" style="color:#667eea;font-size:0.9rem;white-space:nowrap;">
        <i class="fas fa-arrow-left"></i> Volver al Mercadito
    </a>
</div>

<!-- Productos -->
<?php if (empty($products)): ?>
<div class="empty-state">
    <i class="fas fa-box-open"></i>
    <h3 style="color:#555;">No se encontraron productos</h3>
    <a href="?id=<?= $sid ?>" style="color:#667eea;">Ver todos</a>
</div>
<?php else: ?>
<div class="store-products">
    <?php foreach ($products as $prod): ?>
    <a href="emprendedoras-producto.php?id=<?= $prod['id'] ?>" class="store-product-card">
        <?php if ($prod['image_1']): ?>
            <img src="<?= htmlspecialchars($prod['image_1']) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" loading="lazy">
        <?php else: ?>
            <div class="store-product-noimg"><i class="fas fa-image" style="font-size:3rem;color:#ccc;"></i></div>
        <?php endif; ?>
        <div class="spc-body">
            <div class="spc-cat"><?= htmlspecialchars($prod['category_name'] ?? '') ?></div>
            <div class="spc-name"><?= htmlspecialchars($prod['name']) ?></div>
            <div class="spc-price">₡<?= number_format($prod['price'], 0) ?></div>
            <?php if (($prod['stock'] ?? 0) > 0): ?>
                <button class="spc-add" onclick="event.preventDefault();addToCart(<?= $prod['id'] ?>,this)">
                    <i class="fas fa-cart-plus"></i> Agregar al carrito
                </button>
            <?php else: ?>
                <div class="spc-nostock">Sin stock</div>
            <?php endif; ?>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

<a href="emprendedoras-carrito.php" class="emp-cart-fab" id="emp-fab"
   style="display:<?= $empCartCount > 0 ? 'flex' : 'none' ?>">
    <i class="fas fa-shopping-bag"></i>
    <span class="fab-count" id="fab-count"><?= $empCartCount ?></span>
</a>
<div class="catalog-toast" id="catalog-toast"></div>

<script>
document.querySelectorAll('#hamburger-menu a').forEach(function(a) {
    if (a.getAttribute('href') === 'cart' || a.getAttribute('href') === '/cart') {
        a.setAttribute('href', '/emprendedoras-carrito.php');
    }
});

function showToast(msg, ok) {
    const t = document.getElementById('catalog-toast');
    t.innerHTML = (ok ? '🛍️ ' : '⚠️ ') + msg;
    t.style.background = ok ? '#111' : '#ef4444';
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}
function updateFab(count) {
    const fab = document.getElementById('emp-fab');
    document.getElementById('fab-count').textContent = count;
    fab.style.display = count > 0 ? 'flex' : 'none';
}
function addToCart(pid, btn) {
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando…';
    fetch('/api/emp-cart.php?action=add', {
        method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({product_id: pid, qty: 1})
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            showToast(d.message || '¡Agregado!', true);
            btn.innerHTML = '<i class="fas fa-check"></i> ¡Listo!';
            updateFab(d.cart_count);
            setTimeout(() => { btn.disabled = false; btn.innerHTML = orig; }, 2000);
        } else {
            showToast(d.error || 'Error', false);
            btn.disabled = false; btn.innerHTML = orig;
        }
    })
    .catch(() => { showToast('Error de conexión', false); btn.disabled = false; btn.innerHTML = orig; });
}
fetch('/api/emp-cart.php?action=get', {credentials:'same-origin'})
    .then(r => r.json())
    .then(d => { if (d.ok) updateFab(d.count); })
    .catch(() => {});
</script>
</body>
</html>
