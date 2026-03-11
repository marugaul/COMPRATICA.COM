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

$pdo = db();

// Migración silenciosa: columnas de "En Vivo"
try {
    $cols = array_column($pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('is_live',        $cols)) $pdo->exec("ALTER TABLE users ADD COLUMN is_live INTEGER DEFAULT 0");
    if (!in_array('live_title',     $cols)) $pdo->exec("ALTER TABLE users ADD COLUMN live_title TEXT");
    if (!in_array('live_link',      $cols)) $pdo->exec("ALTER TABLE users ADD COLUMN live_link TEXT");
    if (!in_array('live_started_at',$cols)) $pdo->exec("ALTER TABLE users ADD COLUMN live_started_at TEXT");
} catch (Throwable $_e) {}

// Buscar vendedores con productos activos
$searchQuery = trim($_GET['search'] ?? '');
$sellersSql  = "
    SELECT u.id AS seller_id, u.name AS seller_name,
           COALESCE(u.is_live, 0) AS is_live,
           u.live_title, u.live_link,
           COUNT(p.id) AS product_count,
           SUM(p.sales_count) AS total_sales
    FROM entrepreneur_products p
    JOIN users u ON u.id = p.user_id
    WHERE p.is_active = 1
";
$sellerParams = [];
if ($searchQuery !== '') {
    $sellersSql  .= " AND (p.name LIKE ? OR u.name LIKE ?)";
    $sellerParams[] = "%$searchQuery%";
    $sellerParams[] = "%$searchQuery%";
}
$sellersSql .= " GROUP BY u.id ORDER BY COALESCE(u.is_live,0) DESC, total_sales DESC, product_count DESC";

$stSellers = $pdo->prepare($sellersSql);
$stSellers->execute($sellerParams);
$sellers = $stSellers->fetchAll(PDO::FETCH_ASSOC);

// Buscar productos por vendedor (max 6 por puesto)
$productsBySeller = [];
foreach ($sellers as $seller) {
    $sid  = (int)$seller['seller_id'];
    $sqlP = "SELECT p.id, p.name, p.price, p.stock, p.image_1, p.featured,
                    p.views_count, p.sales_count, c.name AS category_name
             FROM entrepreneur_products p
             LEFT JOIN entrepreneur_categories c ON c.id = p.category_id
             WHERE p.is_active = 1 AND p.user_id = ?
             ORDER BY p.featured DESC, p.sales_count DESC
             LIMIT 6";
    $stP  = $pdo->prepare($sqlP);
    $stP->execute([$sid]);
    $productsBySeller[$sid] = $stP->fetchAll(PDO::FETCH_ASSOC);
}

// Carrito
$empCartCount = 0;
foreach ($_SESSION['emp_cart'] ?? [] as $it) $empCartCount += (int)$it['qty'];

// Paleta de toldos
$awningPalette = [
    ['#ef4444','#dc2626'],
    ['#f97316','#ea580c'],
    ['#22c55e','#16a34a'],
    ['#ec4899','#db2777'],
    ['#8b5cf6','#7c3aed'],
    ['#06b6d4','#0891b2'],
    ['#f59e0b','#d97706'],
    ['#84cc16','#65a30d'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mercadito Emprendedoras | CompraTica</title>
    <style>#cartButton{display:none!important;}</style>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/compratica-header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ── HERO ── */
        .hero {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe4e1 50%, #fff0e6 100%);
            padding: 56px 20px 90px;
            text-align: center;
            border-bottom: 4px solid #ff6b9d;
            position: relative;
            overflow: hidden;
        }
        .hero h1 { font-size: 2.6rem; font-weight: 800; color: #333; margin-bottom: 12px; }
        .hero p  { font-size: 1.15rem; color: #666; max-width: 640px; margin: 0 auto 24px; }
        .hero-cta {
            display: inline-block;
            background: linear-gradient(135deg, #ff6b9d, #ec4899);
            color: white; padding: 14px 40px; border-radius: 50px;
            font-weight: 700; text-decoration: none;
            box-shadow: 0 4px 16px rgba(255,107,157,.35);
            transition: all .3s;
        }
        .hero-cta:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(255,107,157,.55); }

        /* ── SEARCH ── */
        .search-wrap {
            max-width: 600px; margin: -32px auto 48px;
            background: white; border-radius: 50px;
            box-shadow: 0 8px 32px rgba(0,0,0,.13);
            display: flex; overflow: hidden; position: relative; z-index: 10;
        }
        .search-wrap input {
            flex: 1; border: none; padding: 16px 24px; font-size: 1rem; outline: none;
        }
        .search-wrap button {
            background: linear-gradient(135deg,#667eea,#764ba2);
            color: white; border: none; padding: 0 28px; cursor: pointer;
            font-size: 1.1rem;
        }

        /* ── MERCADO / PUESTOS ── */
        .market-wrap { max-width: 1280px; margin: 0 auto; padding: 0 20px 80px; }

        .live-section-header {
            text-align: center; margin-bottom: 36px;
        }
        .live-section-header h2 {
            font-size: 2rem; font-weight: 800; color: #333;
            display: inline-flex; align-items: center; gap: 10px;
        }

        .puestos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 56px 36px;
        }

        /* ── PUESTO (stall) ── */
        .puesto {
            background: white;
            border-radius: 0 0 18px 18px;
            box-shadow: 0 6px 28px rgba(0,0,0,.1);
            position: relative;
            margin-top: 52px;
            overflow: hidden;
        }

        /* Toldo */
        .puesto-awning {
            position: absolute;
            top: -52px; left: 0; right: 0;
            height: 60px;
            border-radius: 14px 14px 0 0;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,.2);
        }
        .puesto-awning-stripes {
            width: 100%; height: 100%;
        }
        /* Borda ondulada del toldo */
        .puesto-awning::after {
            content: '';
            position: absolute;
            bottom: -10px; left: 0; right: 0;
            height: 14px;
            background: radial-gradient(circle at 50% 0%, transparent 10px, white 10px);
            background-size: 22px 14px;
        }

        /* Cabecera del puesto */
        .puesto-header {
            padding: 14px 18px 12px;
            display: flex; align-items: center; gap: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        .puesto-avatar {
            width: 44px; height: 44px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; font-weight: 800; color: white;
            flex-shrink: 0;
        }
        .puesto-info { flex: 1; min-width: 0; }
        .puesto-name { font-weight: 800; font-size: 1.05rem; color: #222; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .puesto-meta { font-size: 0.8rem; color: #888; margin-top: 2px; }

        /* Live badge */
        .live-badge {
            display: inline-flex; align-items: center; gap: 5px;
            background: #ef4444; color: white;
            padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700;
            text-decoration: none; white-space: nowrap;
        }
        .live-dot {
            width: 8px; height: 8px; background: white; border-radius: 50%;
            animation: pulse-dot 1.2s infinite;
        }
        @keyframes pulse-dot {
            0%,100% { opacity: 1; transform: scale(1); }
            50%      { opacity: .4; transform: scale(.7); }
        }

        /* Grilla de productos dentro del puesto */
        .puesto-products {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1px;
            background: #f0f0f0;
        }
        .puesto-product-cell {
            background: white; position: relative; aspect-ratio: 1;
            overflow: hidden; cursor: pointer; text-decoration: none; display: block;
        }
        .puesto-product-cell img {
            width: 100%; height: 100%; object-fit: cover;
            transition: transform .35s;
        }
        .puesto-product-cell:hover img { transform: scale(1.08); }
        .puesto-product-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,.72) 0%, transparent 55%);
            opacity: 0; transition: opacity .25s;
            display: flex; flex-direction: column; justify-content: flex-end;
            padding: 10px;
        }
        .puesto-product-cell:hover .puesto-product-overlay { opacity: 1; }
        .ppo-name  { color: white; font-size: 0.78rem; font-weight: 700; line-height: 1.2; }
        .ppo-price { color: #fbbf24; font-size: 0.82rem; font-weight: 800; margin-top: 2px; }
        .ppo-add   {
            margin-top: 6px; background: white; color: #667eea;
            border: none; border-radius: 6px; padding: 4px 8px;
            font-size: 0.73rem; font-weight: 700; cursor: pointer; width: 100%;
        }
        .ppo-add:hover { background: #667eea; color: white; }
        .ppo-nostock { color: #ef4444; font-size: 0.72rem; font-weight: 700; margin-top: 4px; }

        /* Placeholder cuando no hay imagen */
        .puesto-product-noimg {
            width: 100%; height: 100%; background: #f8f8f8;
            display: flex; align-items: center; justify-content: center;
        }
        .puesto-product-noimg i { font-size: 2rem; color: #ccc; }

        /* Footer del puesto */
        .puesto-footer {
            padding: 14px 18px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px;
        }
        .puesto-count { font-size: 0.82rem; color: #888; }
        .btn-entrar {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; text-decoration: none;
            padding: 9px 18px; border-radius: 10px;
            font-size: 0.85rem; font-weight: 700;
            transition: all .25s;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-entrar:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(102,126,234,.4); }

        /* Estado vacío */
        .empty-state { text-align: center; padding: 80px 20px; }
        .empty-state i { font-size: 5rem; color: #ddd; margin-bottom: 20px; display: block; }

        /* FAB carrito */
        .emp-cart-fab {
            position: fixed; bottom: 28px; right: 28px; z-index: 999;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; width: 62px; height: 62px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 6px 20px rgba(102,126,234,.5);
            text-decoration: none; font-size: 1.4rem; transition: transform .2s;
        }
        .emp-cart-fab:hover { transform: scale(1.1); }
        .emp-cart-fab .fab-count {
            position: absolute; top: -4px; right: -4px;
            background: #ef4444; color: white; border-radius: 50%;
            width: 22px; height: 22px; font-size: 0.72rem; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
        }

        /* Toast */
        .catalog-toast {
            position: fixed; bottom: 104px; right: 28px; z-index: 9999;
            background: #111; color: white; padding: 12px 18px; border-radius: 10px;
            font-weight: 600; font-size: 0.9rem; box-shadow: 0 6px 20px rgba(0,0,0,.3);
            transform: translateY(60px); opacity: 0; transition: all .3s; pointer-events: none;
        }
        .catalog-toast.show { transform: translateY(0); opacity: 1; }

        /* Divisor live/normal */
        .section-divider {
            text-align: center; margin: 48px 0 32px;
            position: relative;
        }
        .section-divider::before {
            content: ''; position: absolute;
            top: 50%; left: 0; right: 0;
            height: 2px; background: #f0f0f0;
        }
        .section-divider span {
            position: relative; background: #f9f9f9;
            padding: 6px 20px; border-radius: 20px;
            font-size: 0.95rem; font-weight: 700; color: #888;
        }

        @media (max-width: 480px) {
            .puestos-grid { grid-template-columns: 1fr; }
            .hero h1 { font-size: 1.9rem; }
            .puesto-products { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="hero">
    <h1>🏪 Mercadito <span style="color:#ff6b9d;">Emprendedoras</span></h1>
    <p>Camina por cada puesto, conoce a las emprendedoras y compra directo a ellas.</p>
    <?php if ($isLoggedIn): ?>
        <a href="emprendedoras-dashboard.php" class="hero-cta"><i class="fas fa-store"></i> Mi Tienda</a>
    <?php else: ?>
        <a href="emprendedoras-planes.php" class="hero-cta"><i class="fas fa-rocket"></i> Vende tus Productos</a>
    <?php endif; ?>
</div>

<div class="market-wrap">
    <!-- Búsqueda -->
    <form method="GET" class="search-wrap">
        <input type="text" name="search" placeholder="Buscar productos o emprendedoras…"
               value="<?= htmlspecialchars($searchQuery) ?>">
        <button type="submit"><i class="fas fa-search"></i></button>
    </form>

    <?php
    $liveSellers   = array_filter($sellers, fn($s) => $s['is_live']);
    $normalSellers = array_filter($sellers, fn($s) => !$s['is_live']);
    ?>

    <?php if (empty($sellers)): ?>
    <div class="empty-state">
        <i class="fas fa-store-slash"></i>
        <h3 style="color:#555;">No se encontraron puestos</h3>
        <p style="color:#999;">Prueba otra búsqueda o vuelve pronto.</p>
        <a href="emprendedoras-catalogo.php" style="color:#667eea;display:inline-block;margin-top:16px;">
            <i class="fas fa-arrow-left"></i> Ver todos
        </a>
    </div>
    <?php else: ?>

    <?php if (!empty($liveSellers)): ?>
    <div class="live-section-header">
        <h2><span class="live-badge" style="font-size:1rem;"><span class="live-dot"></span>EN VIVO</span> Puestos en vivo ahora</h2>
    </div>
    <div class="puestos-grid" style="margin-bottom:0;">
        <?php foreach ($liveSellers as $i => $seller): renderPuesto($seller, $i, $productsBySeller, $awningPalette); endforeach; ?>
    </div>
    <div class="section-divider"><span>Todos los puestos</span></div>
    <?php endif; ?>

    <div class="puestos-grid">
        <?php foreach ($normalSellers as $i => $seller): renderPuesto($seller, $i, $productsBySeller, $awningPalette); endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<!-- CTA bottom -->
<div style="background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:60px 20px;text-align:center;">
    <h2 style="font-size:2.2rem;margin-bottom:16px;">¿Eres emprendedora?</h2>
    <p style="font-size:1.15rem;margin-bottom:28px;opacity:.9;">Abre tu puesto hoy y llega a miles de compradores costarricenses.</p>
    <a href="emprendedoras-planes.php" style="background:white;color:#667eea;padding:14px 38px;border-radius:50px;font-weight:700;text-decoration:none;display:inline-block;">
        <i class="fas fa-rocket"></i> Ver Planes
    </a>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<!-- FAB carrito emprendedoras -->
<a href="emprendedoras-carrito.php" class="emp-cart-fab" id="emp-fab"
   style="display:<?= $empCartCount > 0 ? 'flex' : 'none' ?>">
    <i class="fas fa-shopping-bag"></i>
    <span class="fab-count" id="fab-count"><?= $empCartCount ?></span>
</a>
<div class="catalog-toast" id="catalog-toast"></div>

<?php
// ─── Helper: renderizar un puesto ───────────────────────────────────────────
function renderPuesto(array $seller, int $idx, array $productsBySeller, array $palette): void {
    $sid      = (int)$seller['seller_id'];
    $name     = htmlspecialchars($seller['seller_name']);
    $initial  = strtoupper(mb_substr($seller['seller_name'], 0, 1));
    $isLive   = (int)$seller['is_live'];
    $liveTitle= htmlspecialchars($seller['live_title'] ?? '');
    $liveLink = htmlspecialchars($seller['live_link'] ?? '#');
    $pCount   = (int)$seller['product_count'];
    $c        = $palette[$idx % count($palette)];
    $products = $productsBySeller[$sid] ?? [];
    ?>
    <div class="puesto">
        <!-- Toldo -->
        <div class="puesto-awning">
            <svg class="puesto-awning-stripes" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" preserveAspectRatio="none">
                <defs>
                    <pattern id="awn<?= $sid ?>" x="0" y="0" width="60" height="60" patternUnits="userSpaceOnUse" patternTransform="rotate(0)">
                        <rect width="30" height="60" fill="<?= $c[0] ?>"/>
                        <rect x="30" width="30" height="60" fill="<?= $c[1] ?>"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#awn<?= $sid ?>)"/>
            </svg>
        </div>

        <!-- Cabecera -->
        <div class="puesto-header">
            <div class="puesto-avatar" style="background:<?= $c[0] ?>"><?= $initial ?></div>
            <div class="puesto-info">
                <div class="puesto-name"><?= $name ?></div>
                <div class="puesto-meta"><?= $pCount ?> producto<?= $pCount !== 1 ? 's' : '' ?></div>
            </div>
            <?php if ($isLive): ?>
                <a href="<?= $liveLink ?>" target="_blank" class="live-badge">
                    <span class="live-dot"></span>
                    <?= $liveTitle ?: 'EN VIVO' ?>
                </a>
            <?php endif; ?>
        </div>

        <!-- Productos (grilla 3×) -->
        <div class="puesto-products">
            <?php foreach ($products as $prod): ?>
            <a href="emprendedoras-producto.php?id=<?= $prod['id'] ?>"
               class="puesto-product-cell"
               data-pid="<?= $prod['id'] ?>">
                <?php if ($prod['image_1']): ?>
                    <img src="<?= htmlspecialchars($prod['image_1']) ?>" alt="<?= htmlspecialchars($prod['name']) ?>" loading="lazy">
                <?php else: ?>
                    <div class="puesto-product-noimg"><i class="fas fa-image"></i></div>
                <?php endif; ?>
                <div class="puesto-product-overlay">
                    <div class="ppo-name"><?= htmlspecialchars($prod['name']) ?></div>
                    <div class="ppo-price">₡<?= number_format($prod['price'], 0) ?></div>
                    <?php if (($prod['stock'] ?? 0) > 0): ?>
                        <button class="ppo-add" onclick="event.preventDefault();addToCart(<?= $prod['id'] ?>,this)">
                            <i class="fas fa-cart-plus"></i> Agregar
                        </button>
                    <?php else: ?>
                        <div class="ppo-nostock">Sin stock</div>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
                <div style="grid-column:1/-1;padding:32px;text-align:center;color:#bbb;">
                    <i class="fas fa-box-open" style="font-size:2rem;"></i>
                    <p style="margin-top:8px;font-size:0.85rem;">Próximamente</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="puesto-footer">
            <span class="puesto-count"><i class="fas fa-eye" style="color:#667eea;"></i> <?= number_format((int)$seller['total_sales']) ?> ventas</span>
            <a href="emprendedoras-tienda.php?id=<?= $sid ?>" class="btn-entrar">
                <i class="fas fa-store"></i> Entrar al puesto
            </a>
        </div>
    </div>
    <?php
}
?>

<script>
// Redirigir "Mi Carrito" del menú hamburguesa al carrito de emprendedoras
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
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
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
            showToast(d.error || 'Error al agregar', false);
            btn.disabled = false; btn.innerHTML = orig;
        }
    })
    .catch(() => { showToast('Error de conexión', false); btn.disabled = false; btn.innerHTML = orig; });
}

// Cargar conteo inicial del carrito
fetch('/api/emp-cart.php?action=get', {credentials: 'same-origin'})
    .then(r => r.json())
    .then(d => { if (d.ok) updateFab(d.count); })
    .catch(() => {});
</script>
</body>
</html>
