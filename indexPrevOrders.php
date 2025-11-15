<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0700, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

$__isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PHPSESSID');
    
    if (PHP_VERSION_ID < 70300) {
        ini_set('session.cookie_lifetime', '0');
        ini_set('session.cookie_path', '/');
        ini_set('session.cookie_domain', '');
        ini_set('session.cookie_secure', $__isHttps ? '1' : '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_set_cookie_params(0, '/', '', $__isHttps, true);
    } else {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $__isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    
    ini_set('session.use_strict_mode', '0');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '86400');
    session_start();
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cantidadProductos = 0;
foreach ($_SESSION['cart'] as $it) { $cantidadProductos += (int)($it['qty'] ?? 0); }

if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
ini_set('default_charset', 'UTF-8');

$token = $_COOKIE['vg_csrf'] ?? bin2hex(random_bytes(32));
$isHttps = $__isHttps;
if (PHP_VERSION_ID < 70300) {
    setcookie('vg_csrf', $token, time()+7200, '/', '', $isHttps, false);
} else {
    setcookie('vg_csrf', $token, [
      'expires'  => time()+7200,
      'path'     => '/',
      'domain'   => '',
      'secure'   => $isHttps,
      'httponly' => false,
      'samesite' => 'Lax'
    ]);
}

$pdo = db();
$sales = $pdo->query("
  SELECT s.*, a.name AS affiliate_name
  FROM sales s
  JOIN affiliates a ON a.id = s.affiliate_id
  WHERE s.is_active = 1
  ORDER BY datetime(s.start_at) ASC
")->fetchAll(PDO::FETCH_ASSOC);

function same_date($tsA, $tsB) {
  return date('Y-m-d', $tsA) === date('Y-m-d', $tsB);
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo APP_NAME; ?> — Marketplace</title>
  <link rel="stylesheet" href="assets/style.css?v=20251025">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial;margin:0;background:#f9fafb;color:#111}
    .header{display:flex;align-items:center;justify-content:space-between;padding:16px 24px;background:#fff;border-bottom:1px solid #e5e7eb;box-shadow:0 1px 3px rgba(0,0,0,0.05);flex-wrap:wrap;gap:12px;position:relative}
    .logo{font-size:1.2rem;font-weight:700;color:#111}
    nav{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
    .btn{display:inline-flex;align-items:center;gap:8px;border:1px solid #d1d5db;border-radius:10px;padding:10px 16px;text-decoration:none;color:#374151;background:#fff;cursor:pointer;font-size:0.95rem;font-weight:500;transition:all 0.2s}
    .btn:hover{background:#f9fafb;border-color:#9ca3af}
    .btn.primary{background:#0ea5e9;border-color:#0ea5e9;color:#fff}
    .btn.primary:hover{background:#0284c7}
    .container{max-width:1200px;margin:0 auto;padding:24px}
    h1{font-size:2rem;margin:0 0 8px 0}
    .small{font-size:0.95rem;color:#6b7280;margin:0 0 24px 0}
    .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:24px}
    @media (max-width:640px){.grid{grid-template-columns:1fr}}
    .card{border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.08);transition:transform 0.2s,box-shadow 0.2s}
    .card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.12)}
    .imgbox{position:relative;width:100%;height:200px;overflow:hidden;background:#f3f4f6}
    .sale-img{width:100%;height:100%;object-fit:cover}
    .badges-row{display:flex;gap:8px;padding:12px;flex-wrap:wrap}
    .badge{display:inline-flex;align-items:center;padding:4px 10px;border-radius:6px;font-size:0.85rem;font-weight:600}
    .chip{display:inline-flex;padding:4px 10px;border-radius:6px;font-size:0.85rem;font-weight:600}
    .chip-red{background:#fee2e2;color:#991b1b}
    .chip-orange{background:#fed7aa;color:#9a3412}
    .chip-blue{background:#dbeafe;color:#1e40af}
    .card h3{font-size:1.2rem;margin:0 12px 8px 12px;color:#111}
    .card p{margin:0 12px 12px 12px;font-size:0.9rem;color:#6b7280;line-height:1.5}
    .actions{padding:12px;display:flex;gap:8px}
    .site-footer{background:#111;color:#fff;padding:24px;margin-top:48px}
    .site-footer .inner{max-width:1200px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px}
    .footer-links{display:flex;gap:16px}
    .footer-links a{color:#9ca3af;text-decoration:none;font-size:0.9rem}
    .footer-links a:hover{color:#fff}
    .cart-link{position:relative}
    .cart-badge{position:absolute;top:-8px;right:-8px;background:#dc2626;color:#fff;border-radius:999px;padding:3px 8px;font-size:0.75rem;font-weight:700;box-shadow:0 2px 4px rgba(0,0,0,0.2)}
    
    /* ============= POPOVER DEL CARRITO ============= */
    #cart-popover{position:absolute;top:calc(100% + 12px);right:24px;width:400px;max-width:90vw;
      background:#fff;border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,0.15);
      display:none;flex-direction:column;max-height:600px;z-index:1000}
    #cart-popover.show{display:flex}
    .cart-popover-header{padding:16px 20px;border-bottom:2px solid #e5e7eb;font-size:1.1rem;font-weight:700;color:#111}
    .cart-popover-body{flex:1;overflow-y:auto;padding:12px}
    #cart-empty{text-align:center;padding:40px 20px;color:#6b7280}
    .cart-popover-item{display:flex;gap:12px;padding:12px;border-radius:12px;background:#f9fafb;
      margin-bottom:8px;position:relative;transition:all 0.2s}
    .cart-popover-item:hover{background:#f3f4f6}
    .cart-popover-item-img{width:60px;height:60px;object-fit:cover;border-radius:8px;background:#e5e7eb;flex-shrink:0}
    .cart-popover-item-info{flex:1;display:flex;flex-direction:column;gap:4px;padding-right:30px}
    .cart-popover-item-name{font-weight:600;font-size:0.95rem;color:#111;line-height:1.3}
    .cart-popover-item-price{font-size:0.85rem;color:#6b7280}
    .cart-popover-item-total{font-size:0.9rem;font-weight:700;color:#059669}
    .cart-popover-item-remove{position:absolute;top:8px;right:8px;width:24px;height:24px;border:none;
      background:#fee2e2;color:#991b1b;border-radius:50%;cursor:pointer;font-size:1.2rem;font-weight:700;
      display:flex;align-items:center;justify-content:center;transition:all 0.2s;line-height:1}
    .cart-popover-item-remove:hover{background:#fca5a5;transform:scale(1.1)}
    .cart-popover-footer{padding:16px 20px;border-top:2px solid #e5e7eb;background:#f9fafb;border-radius:0 0 16px 16px}
    .cart-popover-total{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;font-size:1.1rem;font-weight:700}
    .cart-popover-actions{display:flex;gap:8px}
    .cart-popover-btn{flex:1;padding:12px;border:none;border-radius:10px;font-weight:600;cursor:pointer;
      transition:all 0.2s;text-decoration:none;text-align:center;display:flex;align-items:center;justify-content:center;gap:6px}
    .cart-popover-btn.secondary{background:#e5e7eb;color:#374151}
    .cart-popover-btn.secondary:hover{background:#d1d5db}
    .cart-popover-btn.primary{background:#0ea5e9;color:#fff}
    .cart-popover-btn.primary:hover{background:#0284c7}
  </style>
</head>
<body>

<header class="header">
  <div class="logo">🛍️ <?php echo APP_NAME; ?></div>
  <nav>
    <a class="btn" href="affiliate/login.php">Afiliados</a>
    <a class="btn primary" href="affiliate/register.php">Publicar mi venta</a>
    <button id="cartButton" class="btn cart-link">
      🛒 Carrito 
      <span id="cartBadge" class="cart-badge" style="display:none">0</span>
    </button>
    <a class="btn" href="admin/login.php">Backoffice</a>
  </nav>
  
  <!-- Popover del carrito -->
  <div id="cart-popover">
    <div class="cart-popover-header">
      🛒 Tu Carrito
    </div>
    
    <div class="cart-popover-body">
      <div id="cart-empty" style="display:none">
        <p>Tu carrito está vacío</p>
      </div>
      <div id="cart-items"></div>
    </div>
    
    <div class="cart-popover-footer">
      <div class="cart-popover-total">
        <span>Total:</span>
        <span id="cart-total">₡0</span>
      </div>
      <div class="cart-popover-actions">
        <a href="cart.php" class="cart-popover-btn secondary">
          Ver carrito
        </a>
        <a href="cart.php" class="cart-popover-btn primary">
          Pagar
        </a>
      </div>
    </div>
  </div>
</header>

<div class="container">
  <h1>Espacios de venta</h1>
  <p class="small">Descubre ventas de garaje activas y próximas cerca de ti.</p>

  <div class="grid">
    <?php
    $nowTs = time();
    foreach ($sales as $s):
      $st  = strtotime($s['start_at']);
      $en  = strtotime($s['end_at']);

      $state = 'Próxima';
      $color = '#2563eb';
      if ($nowTs >= $st && $nowTs <= $en) {
        $state = 'En vivo';
        $color = '#059669';
      } elseif ($nowTs > $en) {
        $state = 'Finalizada';
        $color = '#6b7280';
      }

      $secondary = null; $secClass = '';
      if ($state === 'En vivo' && same_date($en, $nowTs)) {
        $secondary = 'Último día'; $secClass = 'chip chip-red';
      } elseif (same_date($st, $nowTs)) {
        $secondary = 'Hoy';        $secClass = 'chip chip-orange';
      } elseif ($st >= strtotime('-2 days', $nowTs)) {
        $secondary = 'Nuevo';      $secClass = 'chip chip-blue';
      }

      $img = $s['cover_image'] ? 'uploads/affiliates/' . htmlspecialchars($s['cover_image']) : 'assets/placeholder.jpg';
      $img2 = !empty($s['cover_image2']) ? 'uploads/affiliates/' . htmlspecialchars($s['cover_image2']) : null;
      $imgs = $img2 ? [$img, $img2] : [$img];
    ?>
      <div class="card">
        <div class="imgbox">
          <img class="sale-img" data-images='<?php echo json_encode($imgs, JSON_UNESCAPED_SLASHES); ?>' src="<?php echo $imgs[0]; ?>" alt="Portada de <?php echo htmlspecialchars($s['title']); ?>">
        </div>

        <div class="badges-row">
          <span class="badge" style="background:<?php echo $color; ?>;color:#fff">
            <?php echo $state; ?>
          </span>
          <?php if ($secondary): ?>
            <span class="<?php echo $secClass; ?>"><?php echo $secondary; ?></span>
          <?php endif; ?>
        </div>

        <h3><?php echo htmlspecialchars($s['title']); ?></h3>
        <p>
          <?php echo htmlspecialchars($s['affiliate_name']); ?><br>
          <?php echo date('d/m/Y H:i', $st); ?> — <?php echo date('d/m/Y H:i', $en); ?>
        </p>

        <div class="actions">
          <?php if ($state === 'En vivo'): ?>
            <a class="btn primary" href="store.php?sale_id=<?php echo (int)$s['id']; ?>" style="flex:1;justify-content:center">Entrar</a>
          <?php elseif ($state === 'Próxima'): ?>
            <span class="btn" style="flex:1;justify-content:center;opacity:0.6;cursor:not-allowed">Aún no inicia</span>
          <?php else: ?>
            <span class="btn" style="flex:1;justify-content:center;opacity:0.7;cursor:not-allowed">Finalizada</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if (empty($sales)): ?>
      <div class="card">
        <p style="padding:20px">Aún no hay espacios activos.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<footer class="site-footer">
  <div class="inner">
    <div>© <?php echo date('Y'); ?> <?php echo APP_NAME; ?> — Todos los derechos reservados.</div>
    <div class="footer-links">
      <a href="mailto:<?php echo defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'info@compratica.com'; ?>">Contacto</a>
      <a href="affiliate/login.php">Ser afiliado</a>
      <a href="admin/login.php">Administrador</a>
    </div>
  </div>
</footer>

<script>
(function(){
  var nodes = document.querySelectorAll('.sale-img[data-images]');
  nodes.forEach(function(img){
    try {
      var arr = JSON.parse(img.getAttribute('data-images')||'[]');
      if (!Array.isArray(arr) || arr.length < 2) return;
      var i = 0;
      setInterval(function(){
        i = (i + 1) % arr.length;
        img.src = arr[i];
      }, 3500);
    } catch(e){}
  });
})();
</script>

<script>
const API = '/api/cart.php';

// ============= FUNCIONES DEL CARRITO =============
function groupCartItems(groups) {
  const productMap = new Map();
  
  groups.forEach(group => {
    group.items.forEach(item => {
      const key = `${item.product_id}_${item.unit_price}`;
      
      if (productMap.has(key)) {
        const existing = productMap.get(key);
        existing.qty += item.qty;
        existing.line_total += item.line_total;
      } else {
        productMap.set(key, {
          ...item,
          sale_id: group.sale_id,
          sale_title: group.sale_title,
          currency: group.currency
        });
      }
    });
  });
  
  return Array.from(productMap.values());
}

function fmtPrice(n, currency = 'CRC') {
  currency = currency.toUpperCase();
  if (currency === 'USD') {
    return '$' + n.toFixed(2);
  }
  return '₡' + Math.round(n).toLocaleString('es-CR');
}

function renderCart(data) {
  const cartItemsContainer = document.getElementById('cart-items');
  const cartTotal = document.getElementById('cart-total');
  const cartEmpty = document.getElementById('cart-empty');
  const cartBadge = document.getElementById('cartBadge');
  
  if (!data || !data.ok || !data.groups || data.groups.length === 0) {
    cartBadge.textContent = '0';
    cartBadge.style.display = 'none';
    cartEmpty.style.display = 'block';
    cartItemsContainer.innerHTML = '';
    cartTotal.textContent = '₡0';
    return;
  }
  
  // Agrupar productos
  const groupedItems = groupCartItems(data.groups);
  
  // Calcular totales
  let totalCount = 0;
  let totalAmount = 0;
  let mainCurrency = 'CRC';
  
  groupedItems.forEach(item => {
    totalCount += item.qty;
    totalAmount += item.line_total;
    mainCurrency = item.currency || 'CRC';
  });
  
  // Actualizar badge
  cartBadge.textContent = totalCount;
  cartBadge.style.display = totalCount > 0 ? 'inline-block' : 'none';
  
  // Mostrar/ocultar mensaje vacío
  cartEmpty.style.display = totalCount === 0 ? 'block' : 'none';
  
  // Renderizar items
  cartItemsContainer.innerHTML = groupedItems.map(item => `
    <div class="cart-popover-item" data-pid="${item.product_id}" data-sale-id="${item.sale_id}">
      <img 
        src="${item.product_image_url || '/assets/placeholder.jpg'}" 
        alt="${item.product_name}"
        class="cart-popover-item-img"
      >
      <div class="cart-popover-item-info">
        <div class="cart-popover-item-name">${item.product_name}</div>
        <div class="cart-popover-item-price">
          ${fmtPrice(item.unit_price, item.currency)} × ${item.qty}
        </div>
        <div class="cart-popover-item-total">
          ${fmtPrice(item.line_total, item.currency)}
        </div>
      </div>
      <button 
        class="cart-popover-item-remove" 
        data-pid="${item.product_id}"
        data-sale-id="${item.sale_id}"
        title="Eliminar"
      >
        ×
      </button>
    </div>
  `).join('');
  
  // Actualizar total
  cartTotal.textContent = fmtPrice(totalAmount, mainCurrency);
}

async function loadCart() {
  try {
    const response = await fetch(API + '?action=get', {
      credentials: 'include',
      cache: 'no-store'
    });
    const data = await response.json();
    renderCart(data);
  } catch (error) {
    console.error('Error al cargar carrito:', error);
  }
}

// Toggle popover
const cartBtn = document.getElementById('cartButton');
const cartPopover = document.getElementById('cart-popover');

if (cartBtn && cartPopover) {
  cartBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    const isOpen = cartPopover.classList.contains('show');
    
    if (!isOpen) {
      loadCart(); // Recargar antes de abrir
    }
    
    cartPopover.classList.toggle('show');
  });
  
  // Cerrar al hacer clic fuera
  document.addEventListener('click', (e) => {
    if (!cartPopover.contains(e.target) && !cartBtn.contains(e.target)) {
      cartPopover.classList.remove('show');
    }
  });
}

// Eliminar item del carrito
document.addEventListener('click', async (e) => {
  const removeBtn = e.target.closest('.cart-popover-item-remove');
  if (!removeBtn) return;
  
  const pid = parseInt(removeBtn.dataset.pid);
  const saleId = parseInt(removeBtn.dataset.saleId);
  
  try {
    const token = (document.cookie.match(/(?:^|;\s*)vg_csrf=([^;]+)/) || [])[1] || '';
    const response = await fetch(API + '?action=remove', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': token
      },
      body: JSON.stringify({ product_id: pid, sale_id: saleId }),
      credentials: 'include'
    });
    
    const data = await response.json();
    if (data.ok) {
      loadCart(); // Recargar carrito
    }
  } catch (error) {
    console.error('Error:', error);
  }
});

// Cargar carrito al inicio
loadCart();
</script>

</body>
</html>