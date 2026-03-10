<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);

$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

$isLoggedIn = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
$userName = $_SESSION['name'] ?? 'Usuario';

$cantidadProductos = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $it) {
        $cantidadProductos += (int)($it['qty'] ?? 0);
    }
}

$pdo = db();

// Obtener categorías
$categories = $pdo->query("
    SELECT * FROM entrepreneur_categories
    WHERE is_active = 1
    ORDER BY display_order
")->fetchAll(PDO::FETCH_ASSOC);

// Filtros
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Construir query
$sql = "
    SELECT p.*, c.name as category_name, u.name as seller_name
    FROM entrepreneur_products p
    LEFT JOIN entrepreneur_categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.is_active = 1
";

$params = [];

if ($categoryFilter > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $categoryFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

// Ordenamiento
switch ($sortBy) {
    case 'price_low':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'popular':
        $sql .= " ORDER BY p.views_count DESC, p.sales_count DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY p.created_at DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener productos destacados
$featuredProducts = $pdo->query("
    SELECT p.*, c.name as category_name, u.name as seller_name
    FROM entrepreneur_products p
    LEFT JOIN entrepreneur_categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.is_active = 1 AND p.featured = 1
    ORDER BY p.created_at DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emprendedoras | CompraTica</title>
    <meta name="description" content="Descubre productos únicos de emprendedoras costarricenses. Café, joyería, artesanías y más.">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/compratica-header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .hero-emprendedoras {
            background: linear-gradient(135deg, #fff5f5 0%, #ffe4e1 50%, #fff0e6 100%);
            color: #333;
            padding: 60px 20px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
            border-bottom: 4px solid #ff6b9d;
        }
        .hero-emprendedoras::before {
            content: "🏪";
            position: absolute;
            font-size: 12rem;
            opacity: 0.08;
            top: -20px;
            right: 10%;
            animation: float 6s ease-in-out infinite;
        }
        .hero-emprendedoras::after {
            content: "🌸";
            position: absolute;
            font-size: 8rem;
            opacity: 0.1;
            bottom: 20px;
            left: 5%;
            animation: float 8s ease-in-out infinite reverse;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-15px) rotate(5deg); }
        }
        .hero-emprendedoras h1 {
            font-size: 2.8rem;
            margin-bottom: 15px;
            font-weight: 800;
            line-height: 1.2;
            text-shadow: 2px 2px 4px rgba(255,255,255,0.5);
        }
        .hero-emprendedoras p {
            font-size: 1.2rem;
            opacity: 0.85;
            max-width: 700px;
            margin: 0 auto 25px;
            color: #555;
        }
        .hero-cta {
            display: inline-block;
            background: linear-gradient(135deg, #ff6b9d 0%, #ec4899 100%);
            color: white;
            padding: 15px 45px;
            border-radius: 50px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(255, 107, 157, 0.3);
            position: relative;
            z-index: 10;
        }
        .hero-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 107, 157, 0.5);
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .filters-section {
            background: white;
            padding: 30px;
            margin: -40px auto 40px;
            max-width: 1200px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            position: relative;
            z-index: 10;
        }
        .filters-row {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 15px;
            align-items: center;
        }
        .search-box {
            position: relative;
        }
        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
        }
        .search-box button {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
        }
        .filter-select {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            background: white;
        }
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin: 40px auto;
            max-width: 1200px;
        }
        .category-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid #f0f0f0;
            text-decoration: none;
            color: #333;
        }
        .category-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        }
        .category-card.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }
        .category-card i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #667eea;
        }
        .category-card.active i {
            color: white;
        }
        .section-title {
            font-size: 2.2rem;
            font-weight: 800;
            margin: 60px 0 30px;
            text-align: center;
            color: #333;
            position: relative;
            padding-bottom: 15px;
        }
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, #ff6b9d, #ec4899);
            border-radius: 2px;
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 50px 30px;
            margin: 60px auto 40px;
        }
        .product-card {
            background: white;
            border-radius: 15px 15px 20px 20px;
            overflow: visible;
            transition: all 0.3s;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
            margin-top: 40px;
        }
        /* Toldo estilo mercadito con rayas */
        .product-card::before {
            content: '';
            position: absolute;
            top: -35px;
            left: 0;
            right: 0;
            height: 45px;
            background: repeating-linear-gradient(
                90deg,
                var(--awning-color-1, #ef4444) 0px,
                var(--awning-color-1, #ef4444) 35px,
                var(--awning-color-2, #dc2626) 35px,
                var(--awning-color-2, #dc2626) 70px
            );
            border-radius: 12px 12px 0 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        /* Borde ondulado del toldo */
        .product-card::after {
            content: '';
            position: absolute;
            top: 10px;
            left: 0;
            right: 0;
            height: 18px;
            background: radial-gradient(circle at 50% 0%, transparent 13px, white 13px);
            background-size: 26px 18px;
            background-position: 0 0;
        }
        /* Colores diferentes para cada puesto */
        .product-card:nth-child(6n+1) { --awning-color-1: #ef4444; --awning-color-2: #dc2626; }
        .product-card:nth-child(6n+2) { --awning-color-1: #f97316; --awning-color-2: #ea580c; }
        .product-card:nth-child(6n+3) { --awning-color-1: #22c55e; --awning-color-2: #16a34a; }
        .product-card:nth-child(6n+4) { --awning-color-1: #ec4899; --awning-color-2: #db2777; }
        .product-card:nth-child(6n+5) { --awning-color-1: #8b5cf6; --awning-color-2: #7c3aed; }
        .product-card:nth-child(6n+6) { --awning-color-1: #06b6d4; --awning-color-2: #0891b2; }
        .product-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 20px 45px rgba(0,0,0,0.2);
        }
        .product-image {
            width: 100%;
            height: 220px;
            object-fit: cover;
            object-position: center;
            background: #f5f5f5;
            display: block;
            border-radius: 0;
        }
        /* Wrapper para que el toldo quede detrás de la imagen */
        .product-card::before,
        .product-card::after {
            z-index: 0;
        }
        .product-image,
        .product-info {
            position: relative;
            z-index: 1;
        }
        .product-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #667eea;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .product-info {
            padding: 20px;
        }
        .product-category {
            color: #667eea;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .product-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
            line-height: 1.3;
        }
        .product-seller {
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 12px;
        }
        .product-price {
            font-size: 1.5rem;
            font-weight: 800;
            color: #667eea;
            margin-bottom: 10px;
        }
        .product-meta {
            display: flex;
            justify-content: space-between;
            color: #999;
            font-size: 0.85rem;
        }
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }
        .empty-state i {
            font-size: 5rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
        }
        .categories-section {
            background: white;
            padding: 25px 0;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-radius: 15px;
        }
        .categories-section-title {
            text-align: center;
            margin-bottom: 20px;
            color: #ff6b9d;
            font-size: 1.3rem;
        }
        .btn-add-catalog {
            display: block; width: 100%; margin-top: 10px;
            padding: 10px; background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; border: none; border-radius: 10px;
            font-size: 0.9rem; font-weight: 700; cursor: pointer; transition: all 0.25s;
        }
        .btn-add-catalog:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(102,126,234,0.4); }
        .btn-add-catalog:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .emp-cart-fab {
            position: fixed; bottom: 28px; right: 28px; z-index: 999;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; width: 60px; height: 60px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 6px 20px rgba(102,126,234,0.5); text-decoration: none;
            font-size: 1.4rem; transition: transform 0.2s;
        }
        .emp-cart-fab:hover { transform: scale(1.1); }
        .emp-cart-fab .count {
            position: absolute; top: -4px; right: -4px; background: #ef4444;
            color: white; border-radius: 50%; width: 22px; height: 22px;
            font-size: 0.72rem; font-weight: 800; display: flex; align-items: center; justify-content: center;
        }
        .catalog-toast {
            position: fixed; bottom: 100px; right: 28px; z-index: 9998;
            background: #111; color: white; padding: 12px 18px; border-radius: 10px;
            font-weight: 600; font-size: 0.9rem; box-shadow: 0 6px 20px rgba(0,0,0,0.3);
            transform: translateY(60px); opacity: 0; transition: all 0.3s; pointer-events: none;
        }
        .catalog-toast.show { transform: translateY(0); opacity: 1; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="hero-emprendedoras">
        <h1>¡Bienvenida el <span style="color: #ff6b9d;">Mercadito</span> Compratica! ❤️</h1>
        <p>Descubre, apoya y compra directo a emprendedoras costarricenses.</p>
        <?php if ($isLoggedIn): ?>
            <a href="emprendedoras-dashboard.php" class="hero-cta">
                <i class="fas fa-store"></i> Mi Tienda
            </a>
        <?php else: ?>
            <a href="emprendedoras-planes.php" class="hero-cta">
                <i class="fas fa-rocket"></i> Vende tus Productos
            </a>
        <?php endif; ?>
    </div>

    <div class="container">
        <form method="GET" class="filters-section">
            <div class="filters-row">
                <div class="search-box">
                    <input type="text" name="search" placeholder="Buscar productos..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <button type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>

                <select name="sort" class="filter-select" onchange="this.form.submit()">
                    <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Más recientes</option>
                    <option value="popular" <?php echo $sortBy === 'popular' ? 'selected' : ''; ?>>Más populares</option>
                    <option value="price_low" <?php echo $sortBy === 'price_low' ? 'selected' : ''; ?>>Precio: Menor a Mayor</option>
                    <option value="price_high" <?php echo $sortBy === 'price_high' ? 'selected' : ''; ?>>Precio: Mayor a Menor</option>
                </select>

                <?php if ($categoryFilter > 0 || !empty($searchQuery)): ?>
                    <a href="emprendedoras-catalogo.php" style="background: #ef4444; color: white; padding: 12px 20px; border-radius: 10px; text-decoration: none; white-space: nowrap;">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <div class="categories-section">
            <h3 class="categories-section-title">
                <i class="fas fa-store"></i> Pasillos del Mercadito
            </h3>
            <div class="categories-grid">
                <a href="emprendedoras-catalogo.php" class="category-card <?php echo $categoryFilter === 0 ? 'active' : ''; ?>">
                    <i class="fas fa-th"></i>
                    <div><strong>Todas</strong></div>
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="?category=<?php echo $cat['id']; ?>" class="category-card <?php echo $categoryFilter === $cat['id'] ? 'active' : ''; ?>">
                        <i class="<?php echo htmlspecialchars($cat['icon'] ?? 'fas fa-box'); ?>"></i>
                        <div><strong><?php echo htmlspecialchars($cat['name']); ?></strong></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!empty($featuredProducts) && $categoryFilter === 0 && empty($searchQuery)): ?>
            <h2 class="section-title" id="puestos">🏆 Puestos Destacados</h2>
            <div class="products-grid">
                <?php foreach ($featuredProducts as $product): ?>
                    <a href="emprendedoras-producto.php?id=<?php echo $product['id']; ?>" class="product-card">
                        <?php if ($product['image_1']): ?>
                            <img src="<?php echo htmlspecialchars($product['image_1']); ?>" class="product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <div class="product-image" style="display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-image" style="font-size: 4rem; color: #ccc;"></i>
                            </div>
                        <?php endif; ?>
                        <div class="product-badge">⭐ Destacado</div>
                        <div class="product-info">
                            <div class="product-category"><?php echo htmlspecialchars($product['category_name'] ?? 'Sin categoría'); ?></div>
                            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                            <div class="product-seller">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($product['seller_name']); ?>
                            </div>
                            <div class="product-price">₡<?php echo number_format($product['price'], 0); ?></div>
                            <div class="product-meta">
                                <span><i class="fas fa-eye"></i> <?php echo $product['views_count']; ?></span>
                                <span><i class="fas fa-shopping-cart"></i> <?php echo $product['sales_count']; ?></span>
                            </div>
                            <?php if (($product['stock'] ?? 0) > 0): ?>
                            <button class="btn-add-catalog" onclick="event.preventDefault();addToCatalogCart(<?php echo $product['id']; ?>,this)">
                                <i class="fas fa-shopping-bag"></i> Agregar
                            </button>
                            <?php else: ?>
                            <div style="text-align:center;color:#ef4444;font-size:0.82rem;margin-top:8px;font-weight:600;">Sin stock</div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h2 class="section-title">
            <?php
            if ($categoryFilter > 0) {
                $catName = '';
                foreach ($categories as $c) {
                    if ($c['id'] == $categoryFilter) {
                        $catName = $c['name'];
                        break;
                    }
                }
                echo htmlspecialchars($catName);
            } else {
                echo 'Todos los Productos';
            }
            ?>
        </h2>

        <?php if (empty($products)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>No se encontraron productos</h3>
                <p style="color: #999;">Intenta con otra búsqueda o categoría</p>
                <a href="emprendedoras-catalogo.php" style="color: #667eea; margin-top: 15px; display: inline-block;">
                    <i class="fas fa-arrow-left"></i> Ver todos los productos
                </a>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <a href="emprendedoras-producto.php?id=<?php echo $product['id']; ?>" class="product-card">
                        <?php if ($product['image_1']): ?>
                            <img src="<?php echo htmlspecialchars($product['image_1']); ?>" class="product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <div class="product-image" style="display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-image" style="font-size: 4rem; color: #ccc;"></i>
                            </div>
                        <?php endif; ?>
                        <?php if ($product['featured']): ?>
                            <div class="product-badge">⭐ Destacado</div>
                        <?php endif; ?>
                        <div class="product-info">
                            <div class="product-category"><?php echo htmlspecialchars($product['category_name'] ?? 'Sin categoría'); ?></div>
                            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                            <div class="product-seller">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($product['seller_name']); ?>
                            </div>
                            <div class="product-price">₡<?php echo number_format($product['price'], 0); ?></div>
                            <div class="product-meta">
                                <span><i class="fas fa-eye"></i> <?php echo $product['views_count']; ?></span>
                                <span><i class="fas fa-shopping-cart"></i> <?php echo $product['sales_count']; ?></span>
                            </div>
                            <?php if (($product['stock'] ?? 0) > 0): ?>
                            <button class="btn-add-catalog" onclick="event.preventDefault();addToCatalogCart(<?php echo $product['id']; ?>,this)">
                                <i class="fas fa-shopping-bag"></i> Agregar
                            </button>
                            <?php else: ?>
                            <div style="text-align:center;color:#ef4444;font-size:0.82rem;margin-top:8px;font-weight:600;">Sin stock</div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 60px 20px; margin-top: 80px; text-align: center;">
        <h2 style="font-size: 2.5rem; margin-bottom: 20px;">¿Eres emprendedora?</h2>
        <p style="font-size: 1.2rem; margin-bottom: 30px; opacity: 0.95;">
            Únete a nuestra comunidad y comienza a vender tus productos hoy
        </p>
        <a href="emprendedoras-planes.php" style="background: white; color: #667eea; padding: 15px 40px; border-radius: 50px; font-weight: 700; text-decoration: none; display: inline-block;">
            <i class="fas fa-rocket"></i> Ver Planes y Precios
        </a>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>

<!-- Botón flotante del carrito -->
<a href="emprendedoras-carrito.php" class="emp-cart-fab" id="emp-fab" style="display:none;">
    <i class="fas fa-shopping-bag"></i>
    <span class="count emp-cart-count" id="fab-count">0</span>
</a>
<div class="catalog-toast" id="catalog-toast"></div>

<script>
function showCatalogToast(msg, ok) {
    const t = document.getElementById('catalog-toast');
    t.innerHTML = (ok ? '🛍️ ' : '⚠️ ') + msg;
    t.style.background = ok ? '#111' : '#ef4444';
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

function addToCatalogCart(pid, btn) {
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    fetch('/api/emp-cart.php?action=add', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({product_id: pid, qty: 1})
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            showCatalogToast(d.message || '¡Agregado!', true);
            btn.innerHTML = '<i class="fas fa-check"></i> ¡Listo!';
            updateFab(d.cart_count);
            setTimeout(() => { btn.disabled = false; btn.innerHTML = orig; }, 2000);
        } else {
            showCatalogToast(d.error || 'Error al agregar', false);
            btn.disabled = false; btn.innerHTML = orig;
        }
    })
    .catch(() => { showCatalogToast('Error de conexión', false); btn.disabled = false; btn.innerHTML = orig; });
}

function updateFab(count) {
    const fab = document.getElementById('emp-fab');
    const cnt = document.getElementById('fab-count');
    cnt.textContent = count;
    fab.style.display = count > 0 ? 'flex' : 'none';
}

// Cargar conteo inicial
fetch('/api/emp-cart.php?action=get', {credentials:'same-origin'})
    .then(r => r.json())
    .then(d => { if (d.ok) updateFab(d.count); })
    .catch(() => {});
</script>
</body>
</html>
