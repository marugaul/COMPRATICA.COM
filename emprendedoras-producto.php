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

$isLoggedIn        = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
$userName          = $_SESSION['name'] ?? 'Usuario';
$cantidadProductos = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $it) {
        $cantidadProductos += (int)($it['qty'] ?? 0);
    }
}

$pdo       = db();
$productId = intval($_GET['id'] ?? 0);

if (!$productId) {
    header('Location: emprendedoras-catalogo.php');
    exit;
}

// Cargar producto con info del vendedor
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, u.name AS seller_name, u.email AS seller_email
    FROM entrepreneur_products p
    LEFT JOIN entrepreneur_categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.id = ? AND p.is_active = 1
");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: emprendedoras-catalogo.php');
    exit;
}

// Incrementar vistas (silencioso)
try {
    $pdo->prepare("UPDATE entrepreneur_products SET views_count = views_count + 1 WHERE id = ?")->execute([$productId]);
} catch (Throwable $e) { /* ignorar */ }

// Productos relacionados (misma categoría, excluir este)
$related = $pdo->prepare("
    SELECT p.*, u.name AS seller_name
    FROM entrepreneur_products p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.is_active = 1 AND p.category_id = ? AND p.id != ?
    ORDER BY p.created_at DESC LIMIT 4
");
$related->execute([$product['category_id'], $productId]);
$relatedProducts = $related->fetchAll(PDO::FETCH_ASSOC);

// Imágenes disponibles
$images = array_filter([
    $product['image_1'],
    $product['image_2'],
    $product['image_3'],
    $product['image_4'],
    $product['image_5'],
]);
$mainImage = reset($images) ?: '';

$sinpePhone  = $product['sinpe_phone']  ?? '';
$paypalEmail = $product['paypal_email'] ?? '';

// Número SINPE para WhatsApp (solo dígitos)
$sinpeWA = preg_replace('/\D/', '', $sinpePhone);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> | CompraTica Emprendedoras</title>
    <style>#cartButton{display:none!important;}</style>
    <meta name="description" content="<?= htmlspecialchars(substr($product['description'] ?? '', 0, 160)) ?>">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/compratica-header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-wrap { max-width: 1100px; margin: 40px auto; padding: 0 20px; }

        /* Breadcrumb */
        .breadcrumb { color: #999; font-size: 0.9rem; margin-bottom: 25px; }
        .breadcrumb a { color: #667eea; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { margin: 0 6px; }

        /* Layout principal */
        .product-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 50px; align-items: start; }
        @media (max-width: 768px) { .product-layout { grid-template-columns: 1fr; gap: 30px; } }

        /* Galería */
        .gallery { position: sticky; top: 20px; }
        .main-image-wrap {
            border-radius: 16px; overflow: hidden;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            background: #f5f5f5; margin-bottom: 12px;
            aspect-ratio: 1; display: flex; align-items: center; justify-content: center;
        }
        .main-image-wrap img { width: 100%; height: 100%; object-fit: cover; cursor: zoom-in; }
        .thumbs { display: flex; gap: 8px; flex-wrap: wrap; }
        .thumb {
            width: 70px; height: 70px; border-radius: 8px; overflow: hidden;
            cursor: pointer; border: 2px solid transparent; transition: border-color 0.2s;
            background: #f5f5f5;
        }
        .thumb.active { border-color: #667eea; }
        .thumb img { width: 100%; height: 100%; object-fit: cover; }

        /* Info producto */
        .product-info-panel { display: flex; flex-direction: column; gap: 18px; }
        .badge-category {
            display: inline-block; background: #ede9fe; color: #6d28d9;
            padding: 4px 14px; border-radius: 20px; font-size: 0.82rem; font-weight: 700;
        }
        .product-title { font-size: 2rem; font-weight: 800; color: #1a1a1a; line-height: 1.2; margin: 0; }
        .seller-row { display: flex; align-items: center; gap: 10px; color: #555; font-size: 0.95rem; }
        .seller-avatar {
            width: 34px; height: 34px; background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; border-radius: 50%; display: flex; align-items: center;
            justify-content: center; font-weight: 700; font-size: 0.9rem; flex-shrink: 0;
        }
        .price-block { display: flex; align-items: baseline; gap: 12px; }
        .price-main { font-size: 2.6rem; font-weight: 900; color: #667eea; }

        /* Stock */
        .stock-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 20px; font-size: 0.88rem; font-weight: 600;
        }
        .stock-ok  { background: #d1fae5; color: #065f46; }
        .stock-low { background: #fef3c7; color: #92400e; }
        .stock-out { background: #fee2e2; color: #991b1b; }

        /* Descripción */
        .description-box { background: #fafafa; border-radius: 12px; padding: 18px; color: #444; line-height: 1.7; }

        /* Carrito */
        .btn-add-cart {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            width: 100%; padding: 16px; border-radius: 12px; border: none;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; font-size: 1.05rem; font-weight: 700; cursor: pointer;
            transition: all 0.3s; text-decoration: none;
        }
        .btn-add-cart:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102,126,234,0.4); }
        .btn-add-cart:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .cart-toast {
            position: fixed; bottom: 28px; right: 28px; z-index: 9998;
            background: #111; color: white; padding: 14px 22px; border-radius: 12px;
            font-weight: 600; box-shadow: 0 8px 24px rgba(0,0,0,0.3);
            transform: translateY(80px); opacity: 0; transition: all 0.35s;
        }
        .cart-toast.show { transform: translateY(0); opacity: 1; }

        /* Entrega */
        .delivery-row { display: flex; gap: 12px; flex-wrap: wrap; }
        .delivery-chip {
            display: flex; align-items: center; gap: 6px;
            background: #f0f4ff; color: #4f46e5;
            padding: 7px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;
        }

        /* Relacionados */
        .related-section { margin-top: 70px; }
        .related-title { font-size: 1.5rem; font-weight: 800; color: #333; margin-bottom: 25px; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
        .related-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
        .related-card {
            background: white; border-radius: 12px; overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08); text-decoration: none; color: inherit;
            transition: transform 0.25s, box-shadow 0.25s; display: block;
        }
        .related-card:hover { transform: translateY(-6px); box-shadow: 0 12px 30px rgba(0,0,0,0.15); }
        .related-card img { width: 100%; height: 160px; object-fit: cover; }
        .related-card-body { padding: 14px; }
        .related-card-name { font-weight: 700; color: #333; margin-bottom: 4px; font-size: 0.95rem; }
        .related-card-price { color: #667eea; font-weight: 800; font-size: 1.1rem; }

        /* Lightbox */
        #lightbox {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.9);
            z-index: 9999; align-items: center; justify-content: center;
        }
        #lightbox.open { display: flex; }
        #lightbox img { max-width: 92vw; max-height: 90vh; border-radius: 8px; object-fit: contain; }
        #lightbox-close {
            position: absolute; top: 20px; right: 24px; color: white;
            font-size: 2rem; cursor: pointer; background: none; border: none;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="page-wrap">

    <div class="breadcrumb">
        <a href="index">Inicio</a><span>›</span>
        <a href="emprendedoras-catalogo.php">Emprendedoras</a><span>›</span>
        <?php if ($product['category_name']): ?>
            <a href="emprendedoras-catalogo.php?category=<?= $product['category_id'] ?>"><?= htmlspecialchars($product['category_name']) ?></a><span>›</span>
        <?php endif; ?>
        <strong><?= htmlspecialchars($product['name']) ?></strong>
    </div>

    <div class="product-layout">

        <!-- ========= GALERÍA ========= -->
        <div class="gallery">
            <div class="main-image-wrap">
                <?php if ($mainImage): ?>
                    <img id="main-img" src="<?= htmlspecialchars($mainImage) ?>"
                         alt="<?= htmlspecialchars($product['name']) ?>"
                         onclick="openLightbox(this.src)">
                <?php else: ?>
                    <i class="fas fa-image" style="font-size:5rem;color:#ccc;"></i>
                <?php endif; ?>
            </div>

            <?php if (count($images) > 1): ?>
            <div class="thumbs">
                <?php $i = 0; foreach ($images as $img): ?>
                    <div class="thumb <?= $i === 0 ? 'active' : '' ?>"
                         onclick="changeImage(this, '<?= htmlspecialchars($img) ?>')">
                        <img src="<?= htmlspecialchars($img) ?>" alt="Imagen <?= $i + 1 ?>">
                    </div>
                <?php $i++; endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ========= INFO ========= -->
        <div class="product-info-panel">

            <div>
                <span class="badge-category">
                    <i class="fas fa-tag"></i> <?= htmlspecialchars($product['category_name'] ?? 'Sin categoría') ?>
                </span>
            </div>

            <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>

            <div class="seller-row">
                <div class="seller-avatar"><?= strtoupper(substr($product['seller_name'] ?? 'E', 0, 1)) ?></div>
                <div>
                    <strong><?= htmlspecialchars($product['seller_name'] ?? 'Emprendedora') ?></strong>
                    <div style="font-size:0.82rem;color:#999;">Vendedora verificada ✓</div>
                </div>
            </div>

            <div class="price-block">
                <div class="price-main">₡<?= number_format((float)$product['price'], 0) ?></div>
            </div>

            <?php
            $stock = (int)($product['stock'] ?? 0);
            if ($stock > 5):
            ?>
                <span class="stock-badge stock-ok"><i class="fas fa-check-circle"></i> En stock (<?= $stock ?> disponibles)</span>
            <?php elseif ($stock > 0): ?>
                <span class="stock-badge stock-low"><i class="fas fa-exclamation-triangle"></i> ¡Últimas <?= $stock ?> unidades!</span>
            <?php else: ?>
                <span class="stock-badge stock-out"><i class="fas fa-times-circle"></i> Sin stock</span>
            <?php endif; ?>

            <?php if (!empty($product['description'])): ?>
            <div class="description-box">
                <?= nl2br(htmlspecialchars($product['description'])) ?>
            </div>
            <?php endif; ?>

            <!-- Entrega -->
            <?php if ($product['shipping_available'] || $product['pickup_available']): ?>
            <div>
                <div style="font-size:0.9rem;color:#666;margin-bottom:8px;font-weight:600;">
                    <i class="fas fa-truck"></i> Opciones de entrega
                </div>
                <div class="delivery-row">
                    <?php if ($product['shipping_available']): ?>
                        <span class="delivery-chip"><i class="fas fa-shipping-fast"></i> Envío a domicilio</span>
                    <?php endif; ?>
                    <?php if ($product['pickup_available']): ?>
                        <span class="delivery-chip"><i class="fas fa-store"></i> Retiro en persona
                            <?= !empty($product['pickup_location']) ? '· ' . htmlspecialchars($product['pickup_location']) : '' ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Agregar al carrito -->
            <?php if ($stock > 0): ?>
            <div style="display:flex;gap:10px;align-items:center;">
                <div style="display:flex;align-items:center;border:2px solid #e0e0e0;border-radius:10px;overflow:hidden;">
                    <button onclick="this.nextElementSibling.value=Math.max(1,+this.nextElementSibling.value-1)" style="width:36px;height:42px;border:none;background:white;font-size:1.2rem;cursor:pointer;font-weight:700;">−</button>
                    <input type="number" id="qty-input" value="1" min="1" max="<?= $stock ?>" style="width:46px;height:42px;text-align:center;border:none;border-left:2px solid #e0e0e0;border-right:2px solid #e0e0e0;font-weight:700;font-size:1rem;">
                    <button onclick="this.previousElementSibling.value=Math.min(<?= $stock ?>,+this.previousElementSibling.value+1)" style="width:36px;height:42px;border:none;background:white;font-size:1.2rem;cursor:pointer;font-weight:700;">+</button>
                </div>
                <button class="btn-add-cart" id="btn-cart" onclick="addToCart(<?= $productId ?>)">
                    <i class="fas fa-shopping-bag"></i> Agregar al carrito
                </button>
            </div>

            <!-- Banner Ver Carrito (aparece después de agregar) -->
            <div id="cart-banner" style="display:none;background:linear-gradient(135deg,#667eea,#764ba2);color:white;border-radius:12px;padding:14px 20px;display:none;align-items:center;justify-content:space-between;gap:12px;">
                <span><i class="fas fa-check-circle"></i> <strong id="cart-banner-msg">¡Producto agregado!</strong></span>
                <a href="emprendedoras-carrito.php" style="background:white;color:#667eea;padding:8px 18px;border-radius:8px;font-weight:700;text-decoration:none;white-space:nowrap;font-size:0.9rem;">
                    <i class="fas fa-shopping-bag"></i> Ver carrito
                </a>
            </div>
            <?php else: ?>
            <div style="text-align:center;padding:14px;background:#fee2e2;border-radius:10px;color:#991b1b;font-weight:700;">
                <i class="fas fa-times-circle"></i> Producto sin stock
            </div>
            <?php endif; ?>

            <!-- WhatsApp contacto -->
            <?php if ($sinpePhone): ?>
            <a href="https://wa.me/506<?= $sinpeWA ?>?text=<?= urlencode('Hola, me interesa el producto: ' . $product['name'] . ' que vi en CompraTica. ¿Está disponible?') ?>"
               target="_blank" rel="noopener"
               style="display:flex;align-items:center;gap:10px;background:#25D366;color:white;padding:13px 20px;border-radius:12px;text-decoration:none;font-weight:700;">
                <i class="fab fa-whatsapp" style="font-size:1.3rem;"></i>
                <div>
                    <div>Consultar disponibilidad</div>
                    <div style="font-size:0.8rem;font-weight:400;opacity:0.9;">Contactar a <?= htmlspecialchars($product['seller_name']) ?></div>
                </div>
            </a>
            <?php endif; ?>

            <!-- Stats -->
            <div style="display:flex;gap:20px;color:#999;font-size:0.85rem;padding-top:4px;">
                <span><i class="fas fa-eye"></i> <?= (int)($product['views_count'] ?? 0) ?> vistas</span>
                <span><i class="fas fa-shopping-bag"></i> <?= (int)($product['sales_count'] ?? 0) ?> ventas</span>
            </div>

        </div>
    </div>

    <!-- Productos relacionados -->
    <?php if (!empty($relatedProducts)): ?>
    <div class="related-section">
        <h2 class="related-title"><i class="fas fa-store" style="color:#667eea;"></i> Más de este pasillo</h2>
        <div class="related-grid">
            <?php foreach ($relatedProducts as $rel): ?>
                <a href="emprendedoras-producto.php?id=<?= $rel['id'] ?>" class="related-card">
                    <?php if ($rel['image_1']): ?>
                        <img src="<?= htmlspecialchars($rel['image_1']) ?>" alt="<?= htmlspecialchars($rel['name']) ?>">
                    <?php else: ?>
                        <div style="height:160px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;">
                            <i class="fas fa-image" style="font-size:3rem;color:#ccc;"></i>
                        </div>
                    <?php endif; ?>
                    <div class="related-card-body">
                        <div class="related-card-name"><?= htmlspecialchars($rel['name']) ?></div>
                        <div style="font-size:0.8rem;color:#999;margin-bottom:4px;"><?= htmlspecialchars($rel['seller_name'] ?? '') ?></div>
                        <div class="related-card-price">₡<?= number_format((float)$rel['price'], 0) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Lightbox -->
<div id="lightbox">
    <button id="lightbox-close" onclick="closeLightbox()"><i class="fas fa-times"></i></button>
    <img id="lightbox-img" src="" alt="Imagen ampliada">
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<div class="cart-toast" id="cart-toast"></div>

<script>
function changeImage(thumb, src) {
    document.getElementById('main-img').src = src;
    document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
}
function openLightbox(src) {
    document.getElementById('lightbox-img').src = src;
    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
    document.body.style.overflow = '';
}
document.getElementById('lightbox').addEventListener('click', function(e) {
    if (e.target === this) closeLightbox();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });

function showToast(msg, ok) {
    const t = document.getElementById('cart-toast');
    t.innerHTML = (ok ? '🛍️ ' : '⚠️ ') + msg;
    t.style.background = ok ? '#111' : '#ef4444';
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3500);
}

function addToCart(pid) {
    const qty = parseInt(document.getElementById('qty-input').value) || 1;
    const btn = document.getElementById('btn-cart');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
    fetch('/api/emp-cart.php?action=add', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({product_id: pid, qty: qty})
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            // Mostrar banner con enlace al carrito
            const banner = document.getElementById('cart-banner');
            document.getElementById('cart-banner-msg').textContent = d.message || '¡Producto agregado!';
            banner.style.display = 'flex';
            btn.innerHTML = '<i class="fas fa-check"></i> ¡Agregado!';
            document.querySelectorAll('.emp-cart-count').forEach(el => el.textContent = d.cart_count);
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-shopping-bag"></i> Agregar al carrito';
            }, 3000);
        } else {
            showToast(d.error || 'Error al agregar', false);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-shopping-bag"></i> Agregar al carrito';
        }
    })
    .catch(() => {
        showToast('Error de conexión', false);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-shopping-bag"></i> Agregar al carrito';
    });
}
</script>
</body>
</html>
