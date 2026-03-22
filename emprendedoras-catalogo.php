<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/live_embed.php';
require_once __DIR__ . '/includes/avatar_builder.php';

$isLoggedIn = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
$userName   = $_SESSION['name'] ?? 'Usuario';
$cantidadProductos = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $it) $cantidadProductos += (int)($it['qty'] ?? 0);
}

$pdo = db();

// Migración silenciosa: columnas de "En Vivo" y personalización de puesto
try {
    $cols = array_column($pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('is_live',           $cols)) $pdo->exec("ALTER TABLE users ADD COLUMN is_live INTEGER DEFAULT 0");
    if (!in_array('live_title',        $cols)) $pdo->exec("ALTER TABLE users ADD COLUMN live_title TEXT");
    if (!in_array('live_link',         $cols)) $pdo->exec("ALTER TABLE users ADD COLUMN live_link TEXT");
    if (!in_array('live_started_at',   $cols)) $pdo->exec("ALTER TABLE users ADD COLUMN live_started_at TEXT");
    if (!in_array('live_type',         $cols)) $pdo->exec("ALTER TABLE users ADD COLUMN live_type TEXT DEFAULT 'link'");
    if (!in_array('store_color1',      $cols)) $pdo->exec("ALTER TABLE users ADD COLUMN store_color1 TEXT DEFAULT '#667eea'");
    if (!in_array('store_color2',      $cols)) $pdo->exec("ALTER TABLE users ADD COLUMN store_color2 TEXT DEFAULT '#764ba2'");
    if (!in_array('store_banner_style',$cols)) $pdo->exec("ALTER TABLE users ADD COLUMN store_banner_style TEXT DEFAULT 'stripes'");
    if (!in_array('store_logo',        $cols)) $pdo->exec("ALTER TABLE users ADD COLUMN store_logo TEXT");
    if (!in_array('seller_type',       $cols)) $pdo->exec("ALTER TABLE users ADD COLUMN seller_type TEXT DEFAULT 'emprendedora'");
    if (!in_array('store_avatar',      $cols)) $pdo->exec("ALTER TABLE users ADD COLUMN store_avatar TEXT");
} catch (Throwable $_e) {}

// Filtros de búsqueda
$searchQuery = trim($_GET['search'] ?? '');
$filterType  = in_array($_GET['filter'] ?? '', ['emprendedora','emprendedor']) ? $_GET['filter'] : 'all';

// Buscar vendedores con productos activos y suscripción vigente
$sellersSql  = "
    SELECT u.id AS seller_id, u.name AS seller_name,
           COALESCE(u.is_live, 0) AS is_live,
           u.live_title, u.live_link,
           COALESCE(u.live_type,'link') AS live_type,
           COALESCE(u.store_avatar,'') AS store_avatar,
           COALESCE(u.store_color1,'#667eea') AS store_color1,
           COALESCE(u.store_color2,'#764ba2') AS store_color2,
           COALESCE(u.store_banner_style,'stripes') AS store_banner_style,
           COALESCE(u.store_logo,'') AS store_logo,
           COALESCE(u.seller_type,'emprendedora') AS seller_type,
           COUNT(p.id) AS product_count,
           SUM(p.sales_count) AS total_sales
    FROM entrepreneur_products p
    JOIN users u ON u.id = p.user_id
    WHERE p.is_active = 1
      AND (
          NOT EXISTS (SELECT 1 FROM entrepreneur_subscriptions WHERE user_id = u.id)
          OR (
              SELECT status FROM entrepreneur_subscriptions
              WHERE user_id = u.id
              ORDER BY id DESC LIMIT 1
          ) = 'active'
      )
";
$sellerParams = [];
if ($searchQuery !== '') {
    $sellersSql  .= " AND (p.name LIKE ? OR u.name LIKE ?)";
    $sellerParams[] = "%$searchQuery%";
    $sellerParams[] = "%$searchQuery%";
}
if ($filterType !== 'all') {
    $sellersSql  .= " AND COALESCE(u.seller_type,'emprendedora') = ?";
    $sellerParams[] = $filterType;
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
        /* ── HERO — anular bandera de main.css ── */
        .hero::before,
        .hero::after { content: none !important; display: none !important; }

        /* ── HERO ── */
        .hero {
            background: #ffffff;
            padding: 48px 20px 80px;
            text-align: center;
            border-bottom: 1px solid #f0f0f0;
            position: relative;
        }
        .hero h1 {
            font-size: 2.4rem; font-weight: 800; color: #1a1a2e; margin-bottom: 10px;
        }
        .hero h1 span { color: #667eea; }
        .hero p  { font-size: 1.05rem; color: #6b7280; max-width: 540px; margin: 0 auto 24px; }
        .hero-cta {
            display: inline-flex; align-items: center; gap: 8px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; padding: 13px 32px; border-radius: 50px;
            font-weight: 700; text-decoration: none; font-size: .95rem;
            box-shadow: 0 4px 16px rgba(102,126,234,.35);
            transition: all .3s;
        }
        .hero-cta:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(102,126,234,.45); }

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
            grid-template-columns: repeat(auto-fill, minmax(340px, 440px));
            gap: 56px 36px;
            justify-content: center;
        }

        /* ── PUESTO (stall) ── */
        .puesto-wrapper {
            position: relative;
            margin-top: 70px;
            width: 100%;
        }
        .puesto {
            background: linear-gradient(to bottom, #f5f1e8 0%, #ede8dc 100%);
            border-radius: 0 0 18px 18px;
            box-shadow:
                0 8px 32px rgba(0,0,0,.15),
                0 2px 8px rgba(0,0,0,.1),
                inset 0 1px 0 rgba(255,255,255,.5);
            overflow: hidden;
            border: 8px solid #d4a574;
            border-top: none;
            position: relative;
        }
        /* Textura de madera en los bordes */
        .puesto::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background:
                repeating-linear-gradient(90deg,
                    transparent, transparent 2px,
                    rgba(180, 140, 100, 0.03) 2px, rgba(180, 140, 100, 0.03) 4px);
            pointer-events: none;
            z-index: 1;
        }

        /* Toldo más grande y prominente */
        .puesto-awning {
            position: absolute;
            top: -70px; left: -8px; right: -8px;
            height: 78px;
            border-radius: 16px 16px 0 0;
            overflow: hidden;
            box-shadow:
                0 6px 20px rgba(0,0,0,.3),
                0 3px 10px rgba(0,0,0,.2),
                inset 0 -3px 8px rgba(0,0,0,.15);
            border: 3px solid rgba(0,0,0,.1);
            border-bottom: none;
        }
        .puesto-awning-stripes {
            width: 100%; height: 100%;
        }
        /* Borda ondulada del toldo más pronunciada */
        .puesto-awning::after {
            content: '';
            position: absolute;
            bottom: -12px; left: 0; right: 0;
            height: 16px;
            background: radial-gradient(circle at 50% 0%, transparent 11px, #f5f1e8 11px);
            background-size: 24px 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,.15);
        }

        /* Cabecera del puesto */
        .puesto-header {
            padding: 16px 20px 14px;
            display: flex; align-items: center; gap: 12px;
            border-bottom: 2px solid rgba(180, 140, 100, 0.2);
            background: linear-gradient(to bottom, rgba(255,255,255,0.5) 0%, transparent 100%);
            position: relative;
            z-index: 2;
        }
        .puesto-avatar {
            width: 48px; height: 48px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; font-weight: 800; color: white;
            flex-shrink: 0;
            box-shadow: 0 3px 8px rgba(0,0,0,.15);
            border: 3px solid rgba(255,255,255,.4);
        }
        .puesto-info { flex: 1; min-width: 0; }
        .puesto-name {
            font-weight: 800; font-size: 1.1rem; color: #2c2416;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            text-shadow: 0 1px 2px rgba(255,255,255,.8);
        }
        .puesto-meta {
            font-size: 0.85rem; color: #6b5d4f; margin-top: 2px;
            font-weight: 600;
        }

        /* Live badge */
        .live-badge {
            display: inline-flex; align-items: center; gap: 4px;
            background: #ef4444; color: white;
            padding: 3px 7px; border-radius: 5px; font-size: 0.68rem; font-weight: 800;
            text-decoration: none; white-space: nowrap; letter-spacing: .5px;
            border: none; cursor: pointer; flex-shrink: 0;
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
            gap: 2px;
            background: linear-gradient(to bottom, #d4a574 0%, #c09560 100%);
            padding: 2px;
            position: relative;
            z-index: 2;
        }
        .puesto-product-cell {
            background: white;
            position: relative;
            aspect-ratio: 1;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
            display: block;
            box-shadow: inset 0 0 0 1px rgba(212, 165, 116, 0.15);
        }
        .puesto-product-cell img {
            width: 100%; height: 100%;
            object-fit: contain;
            background: linear-gradient(135deg, #fafaf8 0%, #f5f3f0 100%);
            transition: transform .35s;
        }
        .puesto-product-cell:hover img { transform: scale(1.08); }
        .puesto-product-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(to top, rgba(44, 36, 22, 0.85) 0%, transparent 60%);
            opacity: 0; transition: opacity .25s;
            display: flex; flex-direction: column; justify-content: flex-end;
            padding: 10px;
        }
        .puesto-product-cell:hover .puesto-product-overlay { opacity: 1; }
        .ppo-name  { color: white; font-size: 0.8rem; font-weight: 700; line-height: 1.2; }
        .ppo-price { color: #fbbf24; font-size: 0.85rem; font-weight: 800; margin-top: 2px; }
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
            padding: 16px 20px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px;
            background: linear-gradient(to top, rgba(180, 140, 100, 0.08) 0%, transparent 100%);
            border-top: 2px solid rgba(180, 140, 100, 0.15);
            position: relative;
            z-index: 2;
        }
        .puesto-count {
            font-size: 0.85rem;
            color: #6b5d4f;
            font-weight: 600;
        }
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

        @media (max-width: 768px) {
            .puestos-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 420px));
                gap: 40px 16px;
            }
            .hero h1 { font-size: 2.2rem; }
            .live-section-header h2 { font-size: 1.4rem; }
            .live-section-header { margin-bottom: 24px; }
            .puesto-wrapper { margin-top: 36px; }
            .puesto-awning { height: 44px; top: -44px; }
            /* Limitar altura del iframe en vivo en móvil */
            .live-iframe-wrap { max-height: 200px; overflow: hidden; }
        }

        @media (max-width: 480px) {
            .hero h1 { font-size: 1.9rem; }
            .puesto-products { grid-template-columns: repeat(2, 1fr); }
            .puestos-grid {
                grid-template-columns: minmax(0, 440px);
                gap: 36px 0;
            }
            .live-section-header h2 { font-size: 1.2rem; gap: 6px; }
            .live-iframe-wrap { max-height: 180px; }
            .puesto-header { padding: 10px 12px; }
            .puesto-footer { padding: 10px 12px; }
            .puesto-wrapper { margin-top: 32px; }
        }

        /* ── Panel de live incrustado ── */
        .live-panel {
            display: none;
            background: #000;
            position: relative;
        }
        .live-panel.open { display: block; }
        .live-panel iframe {
            width: 100%; height: 0;
            padding-bottom: 56.25%; /* 16:9 */
            display: block; border: none;
            position: relative;
        }
        /* iframe dentro de un wrapper aspect-ratio */
        .live-iframe-wrap {
            position: relative; width: 100%; padding-bottom: 56.25%; background: #000;
        }
        .live-iframe-wrap iframe {
            position: absolute; inset: 0; width: 100%; height: 100%; border: none;
        }
        .live-panel-footer {
            display: flex; align-items: center; justify-content: space-between;
            padding: 8px 12px; background: #111; gap: 8px;
        }
        .live-panel-platform {
            color: #ccc; font-size: 0.78rem; display: flex; align-items: center; gap: 6px;
        }
        .live-panel-links { display: flex; gap: 8px; }
        .live-panel-links a {
            font-size: 0.75rem; font-weight: 700; padding: 4px 10px;
            border-radius: 6px; text-decoration: none; white-space: nowrap;
        }
        .live-btn-ext {
            background: #333; color: #fff;
        }
        .live-btn-close {
            background: #ef4444; color: #fff;
        }
        /* Badge-botón para abrir el live */
        .live-badge { cursor: pointer; }
        /* Plataformas sin embed */
        .live-nonembed-bar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 14px; gap: 8px;
            border-top: 1px solid #f0f0f0;
        }
        .live-nonembed-bar span { font-size: 0.8rem; color: #555; display: flex; align-items: center; gap: 6px; }
        .live-nonembed-bar a {
            font-size: 0.8rem; font-weight: 700; padding: 5px 12px;
            border-radius: 8px; text-decoration: none; color: white; white-space: nowrap;
        }

        /* ── Avatar chibi animado ── */
        @keyframes avatarFloat {
            0%,100% { transform: translateY(0px) rotate(0deg); }
            30%     { transform: translateY(-5px) rotate(-1.5deg); }
            70%     { transform: translateY(-3px) rotate(1.2deg); }
        }
        @keyframes avatarWave {
            0%,100% { transform: rotate(0deg); transform-origin: bottom right; }
            20%     { transform: rotate(-18deg); }
            50%     { transform: rotate(14deg); }
            80%     { transform: rotate(-10deg); }
        }
        .puesto-avatar-chibi {
            flex-shrink: 0;
            animation: avatarFloat 2.8s ease-in-out infinite;
            display: block;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.18));
            margin-top: -18px;   /* Asoma sobre el borde del card */
            cursor: default;
        }
        .puesto-avatar-chibi:hover {
            animation: avatarFloat 0.6s ease-in-out infinite;
            filter: drop-shadow(0 6px 10px rgba(0,0,0,0.25));
        }
        .seller-type-pill {
            display: inline-flex; align-items: center; gap: 3px;
            font-size: .68rem; font-weight: 700; padding: 2px 8px;
            border-radius: 20px; margin-left: 4px; vertical-align: middle;
            letter-spacing: .2px;
        }
        .seller-type-pill.mujer  { background: #fce7f3; color: #be185d; }
        .seller-type-pill.hombre { background: #dbeafe; color: #1d4ed8; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="hero">
    <h1>🏪 Mercadito <span>Emprendedor</span></h1>
    <p>Camina por cada puesto, conoce a quienes venden y compra directo a ellos.</p>
    <?php if ($isLoggedIn): ?>
        <a href="emprendedoras-dashboard.php" class="hero-cta"><i class="fas fa-store"></i> Mi Tienda</a>
    <?php else: ?>
        <a href="emprendedoras-planes.php" class="hero-cta"><i class="fas fa-rocket"></i> Vende tus Productos</a>
    <?php endif; ?>
</div>

<div class="market-wrap">
    <!-- Búsqueda + filtros -->
    <form method="GET" class="search-wrap">
        <input type="text" name="search" placeholder="Buscar productos o vendedores…"
               value="<?= htmlspecialchars($searchQuery) ?>">
        <?php if ($filterType !== 'all'): ?>
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filterType) ?>">
        <?php endif; ?>
        <button type="submit"><i class="fas fa-search"></i></button>
    </form>

    <!-- Tabs de filtro por tipo -->
    <div style="display:flex;justify-content:center;gap:10px;margin-bottom:32px;flex-wrap:wrap;">
        <?php
        $baseUrl = 'emprendedoras-catalogo.php' . ($searchQuery ? '?search='.urlencode($searchQuery).'&filter=' : '?filter=');
        $tabs = [
            'all'           => ['🏪 Todos',          '#1f2937'],
            'emprendedora'  => ['👩 Emprendedoras',   '#db2777'],
            'emprendedor'   => ['👨 Emprendedores',   '#1d4ed8'],
        ];
        foreach ($tabs as $key => [$label, $color]):
            $active = $filterType === $key;
        ?>
        <a href="<?= $baseUrl . $key ?>"
           style="display:inline-flex;align-items:center;gap:6px;padding:10px 22px;
                  border-radius:999px;font-weight:700;font-size:.92rem;text-decoration:none;
                  transition:all .2s;
                  <?= $active
                      ? "background:{$color};color:white;box-shadow:0 4px 14px {$color}55;"
                      : "background:white;color:{$color};border:2px solid {$color}40;" ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

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
    $sid         = (int)$seller['seller_id'];
    $name        = htmlspecialchars($seller['seller_name']);
    $initial     = strtoupper(mb_substr($seller['seller_name'], 0, 1));
    $isLive      = (int)$seller['is_live'];
    $liveTitle   = htmlspecialchars($seller['live_title'] ?? '');
    $liveLink    = htmlspecialchars($seller['live_link'] ?? '#');
    $liveType    = $seller['live_type'] ?? 'link';
    $isCamLive   = ($liveType === 'camera');
    $pCount      = (int)$seller['product_count'];
    $products    = $productsBySeller[$sid] ?? [];
    $sellerType  = $seller['seller_type'] ?? 'emprendedora';

    // Avatar chibi: decodificar JSON o usar defaults
    $avatarRaw = $seller['store_avatar'] ?? '';
    $avatarCfg = $avatarRaw ? (json_decode($avatarRaw, true) ?: []) : [];
    if (empty($avatarCfg)) {
        $avType    = ($sellerType === 'emprendedor') ? 'man' : 'woman';
        $avatarCfg = avatarDefaults($avType);
    }
    // Sincronizar tipo con seller_type si no fue personalizado
    if (empty($seller['store_avatar'])) {
        $avatarCfg['type'] = ($sellerType === 'emprendedor') ? 'man' : 'woman';
    }

    // Colores: usa los personalizados si el vendedor los configuró, si no usa la paleta por índice
    $c1     = !empty($seller['store_color1']) ? $seller['store_color1'] : $palette[$idx % count($palette)][0];
    $c2     = !empty($seller['store_color2']) ? $seller['store_color2'] : $palette[$idx % count($palette)][1];
    $style  = $seller['store_banner_style'] ?? 'stripes';
    $logo   = $seller['store_logo'] ?? '';

    // Badge de tipo (emprendedor/a)
    $typeBadge = $sellerType === 'emprendedor'
        ? '<span style="font-size:.68rem;background:#dbeafe;color:#1d4ed8;padding:2px 8px;border-radius:20px;font-weight:700;margin-left:6px;">👨 Emprendedor</span>'
        : '<span style="font-size:.68rem;background:#fce7f3;color:#be185d;padding:2px 8px;border-radius:20px;font-weight:700;margin-left:6px;">👩 Emprendedora</span>';

    // Construir SVG del toldo según el estilo elegido
    if ($style === 'gradient') {
        $awningInner = "<defs>
            <linearGradient id='awn{$sid}' x1='0' y1='0' x2='1' y2='0'>
                <stop offset='0%' stop-color='{$c1}'/>
                <stop offset='100%' stop-color='{$c2}'/>
            </linearGradient>
        </defs>
        <rect width='100%' height='100%' fill='url(#awn{$sid})'/>
        <rect width='100%' height='100%' fill='rgba(255,255,255,0.07)'
              style='background:repeating-linear-gradient(90deg,transparent,transparent 2px,rgba(255,255,255,.05) 2px,rgba(255,255,255,.05) 4px)'/>
        <rect y='0' width='100%' height='4' fill='rgba(255,255,255,.25)'/>";
    } elseif ($style === 'solid') {
        $awningInner = "<rect width='100%' height='100%' fill='{$c1}'/>
        <rect width='100%' height='6' fill='rgba(255,255,255,.15)'/>
        <rect y='16' width='100%' height='3' fill='rgba(255,255,255,.1)'/>
        <rect y='30' width='100%' height='3' fill='rgba(255,255,255,.1)'/>";
    } elseif ($style === 'wave') {
        $awningInner = "<rect width='100%' height='100%' fill='{$c1}'/>
        <defs>
            <pattern id='awn{$sid}' x='0' y='0' width='40' height='60' patternUnits='userSpaceOnUse'>
                <rect width='40' height='60' fill='{$c1}'/>
                <path d='M0 20 Q10 10 20 20 Q30 30 40 20' stroke='{$c2}' stroke-width='6' fill='none'/>
                <path d='M0 40 Q10 30 20 40 Q30 50 40 40' stroke='{$c2}' stroke-width='6' fill='none'/>
            </pattern>
        </defs>
        <rect width='100%' height='100%' fill='url(#awn{$sid})'/>";
    } else {
        // stripes (default)
        $awningInner = "<defs>
            <pattern id='awn{$sid}' x='0' y='0' width='50' height='60' patternUnits='userSpaceOnUse'>
                <rect width='25' height='60' fill='{$c1}'/>
                <rect x='25' width='25' height='60' fill='{$c2}'/>
            </pattern>
        </defs>
        <rect width='100%' height='100%' fill='url(#awn{$sid})'/>
        <rect width='100%' height='100%' fill='rgba(0,0,0,0.04)'/>";
    }
    ?>
    <div class="puesto-wrapper">
        <!-- Toldo (fuera del overflow:hidden) -->
        <div class="puesto-awning">
            <svg class="puesto-awning-stripes" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" preserveAspectRatio="none">
                <?= $awningInner ?>
            </svg>
        </div>
        <div class="puesto">

        <!-- Cabecera -->
        <div class="puesto-header" style="overflow:visible;">
            <!-- Avatar profesional DiceBear -->
            <div class="puesto-avatar-chibi" title="<?= $name ?>">
                <?= avatarImg($avatarCfg, 68, '', $name) ?>
            </div>
            <div class="puesto-info">
                <div class="puesto-name">
                    <?= $name ?>
                </div>
                <div class="puesto-meta">
                    <?= $pCount ?> producto<?= $pCount !== 1 ? 's' : '' ?>
                    <?php if ($logo): ?>
                        &nbsp;·&nbsp;<img src="<?= htmlspecialchars($logo) ?>" alt="logo" style="height:16px;vertical-align:middle;border-radius:3px;">
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($isLive): ?>
                <?php if ($isCamLive): ?>
                    <a href="emprendedoras-tienda.php?id=<?= $sid ?>" class="live-badge"
                       style="background:linear-gradient(135deg,#ef4444,#b91c1c);">
                        EN VIVO
                    </a>
                <?php else: ?>
                    <?php $lv = parseLiveUrl($liveLink); ?>
                    <?php if ($lv['embedUrl']): ?>
                        <button class="live-badge" onclick="toggleLive(<?= $sid ?>)" type="button">
                            EN VIVO
                        </button>
                    <?php else: ?>
                        <a href="<?= $liveLink ?>" target="_blank" class="live-badge">
                            EN VIVO
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Panel live incrustado (YouTube / Facebook) — solo para links, no cámara -->
        <?php if ($isLive && !$isCamLive): ?>
            <?php $lv = parseLiveUrl($liveLink); ?>
            <?php if ($lv['embedUrl']): ?>
            <div class="live-panel" id="live-<?= $sid ?>">
                <div class="live-iframe-wrap">
                    <iframe src="" data-src="<?= htmlspecialchars($lv['embedUrl']) ?>"
                            allow="autoplay; fullscreen; picture-in-picture"
                            allowfullscreen loading="lazy"></iframe>
                </div>
                <div class="live-panel-footer">
                    <span class="live-panel-platform">
                        <i class="<?= $lv['icon'] ?>" style="color:<?= $lv['color'] ?>"></i>
                        <?= $lv['platform'] ?>
                    </span>
                    <div class="live-panel-links">
                        <a href="<?= htmlspecialchars($liveLink) ?>" target="_blank" class="live-btn-ext">
                            <i class="fas fa-external-link-alt"></i> Abrir en <?= $lv['platform'] ?>
                        </a>
                        <button class="live-btn-close" onclick="toggleLive(<?= $sid ?>)" type="button">
                            <i class="fas fa-times"></i> Cerrar
                        </button>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="live-nonembed-bar">
                <span><i class="<?= $lv['icon'] ?>" style="color:<?= $lv['color'] ?>"></i>
                    Live en <?= $lv['platform'] ?> — solo disponible en la app</span>
                <a href="<?= htmlspecialchars($liveLink) ?>" target="_blank"
                   style="background:<?= $lv['color'] ?>">
                    <i class="fas fa-external-link-alt"></i> Ver Live
                </a>
            </div>
            <?php endif; ?>
        <?php endif; ?>

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
            <span class="puesto-count"><i class="fas fa-shopping-bag" style="color:<?= $c1 ?>;"></i> <?= number_format((int)$seller['total_sales']) ?> ventas</span>
            <a href="emprendedoras-tienda.php?id=<?= $sid ?>" class="btn-entrar"
               style="background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);">
                <i class="fas fa-store"></i> Entrar al puesto
            </a>
        </div>
        </div><!-- /.puesto -->
    </div><!-- /.puesto-wrapper -->
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
<script>
function toggleLive(sid) {
    const panel = document.getElementById('live-' + sid);
    if (!panel) return;
    const isOpen = panel.classList.toggle('open');
    // Cargar iframe src solo al abrir (lazy)
    if (isOpen) {
        const iframe = panel.querySelector('iframe');
        if (iframe && !iframe.src) {
            iframe.src = iframe.dataset.src;
        }
    } else {
        // Pausar quitando src
        const iframe = panel.querySelector('iframe');
        if (iframe) iframe.src = '';
    }
}
</script>
</body>
</html>
