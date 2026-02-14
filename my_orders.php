<?php
declare(strict_types=1);

/**
 * my_orders.php - Mis Órdenes
 * Diseño elegante coherente con index.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Verificar login
$uid = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
if ($uid === 0) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit;
}

$pdo = null;
$allOrders = [];
$errorMessage = '';

try {
    $pdo = db();
    if (!$pdo instanceof PDO) {
        throw new Exception("Error de conexión a la base de datos");
    }
} catch (Exception $e) {
    $errorMessage = 'Error de conexión a la base de datos.';
}

$userEmail = trim((string)($_SESSION['email'] ?? ''));

// Consultar órdenes
if ($pdo && !$errorMessage && $userEmail !== '') {
    try {
        $sql = "
          SELECT 
            o.*,
            p.name as product_name,
            p.image as product_image,
            a.name as seller_name,
            a.email as seller_email
          FROM orders o
          LEFT JOIN products p ON p.id = o.product_id
          LEFT JOIN affiliates a ON a.id = o.affiliate_id
          WHERE o.buyer_email = ?
          ORDER BY o.created_at DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userEmail]);
        $allOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $errorMessage = 'Error al cargar las órdenes.';
    }
}

// Agrupar órdenes por número de pedido
$orderGroups = [];
if (empty($errorMessage) && is_array($allOrders)) {
    foreach ($allOrders as $order) {
        if (!is_array($order) || !isset($order['order_number'])) continue;
        $orderNum = $order['order_number'];
        if (!isset($orderGroups[$orderNum])) {
            $orderGroups[$orderNum] = [
                'order_number' => $orderNum,
                'created_at' => $order['created_at'] ?? '',
                'status' => $order['status'] ?? 'Desconocido',
                'payment_method' => $order['payment_method'] ?? '',
                'shipping_method' => $order['shipping_method'] ?? '',
                'shipping_cost' => (float)($order['shipping_cost'] ?? 0),
                'currency' => $order['currency'] ?? 'CRC',
                'grand_total' => (float)($order['grand_total'] ?? 0),
                'buyer_name' => $order['buyer_name'] ?? '',
                'buyer_email' => $order['buyer_email'] ?? '',
                'items' => [],
                'item_count' => 0,
                'subtotal' => 0,
                'tax_total' => 0
            ];
        }

        $qty = (float)($order['qty'] ?? 0);
        $subtotal = (float)($order['subtotal'] ?? 0);
        $tax = (float)($order['tax'] ?? 0);
        $unit_price = 0;
        if ($qty > 0) {
            $unit_price = isset($order['unit_price']) ? (float)$order['unit_price'] : ($subtotal > 0 ? $subtotal / $qty : 0);
        }

        $orderGroups[$orderNum]['items'][] = [
            'product_name' => $order['product_name'] ?? 'Producto desconocido',
            'product_image' => $order['product_image'] ?? '',
            'qty' => $qty,
            'unit_price' => $unit_price,
        ];

        $orderGroups[$orderNum]['item_count'] += $qty;
        $orderGroups[$orderNum]['subtotal'] += $subtotal;
        $orderGroups[$orderNum]['tax_total'] += $tax;
    }
}

function fmt_crc($n){ return '₡'.number_format((float)$n,0,',','.'); }
function fmt_usd($n){ return '$'.number_format((float)$n,2,'.',','); }
function fmt_any($n,$c){ return (strtoupper((string)$c)==='USD') ? fmt_usd($n) : fmt_crc($n); }

function getStatusBadge($status) {
    $badges = [
        'Pendiente' => ['bg' => '#fef3c7', 'color' => '#92400e', 'icon' => '⏳'],
        'En Revisión' => ['bg' => '#dbeafe', 'color' => '#1e40af', 'icon' => '🔍'],
        'Confirmado' => ['bg' => '#d1fae5', 'color' => '#065f46', 'icon' => '✅'],
        'Enviado' => ['bg' => '#e0e7ff', 'color' => '#3730a3', 'icon' => '📦'],
        'Completado' => ['bg' => '#d1fae5', 'color' => '#065f46', 'icon' => '🎉'],
        'Cancelado' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'icon' => '❌'],
    ];
    $badge = $badges[$status] ?? ['bg' => '#f3f4f6', 'color' => '#374151', 'icon' => '•'];
    return sprintf(
        '<span style="background:%s;color:%s;padding:6px 14px;border-radius:20px;font-size:0.85rem;font-weight:600;display:inline-flex;align-items:center;gap:6px;">%s %s</span>',
        $badge['bg'],
        $badge['color'],
        $badge['icon'],
        htmlspecialchars($status)
    );
}

$APP_NAME = defined('APP_NAME') ? APP_NAME : 'Compratica';
$isLoggedIn = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
$userName = $_SESSION['name'] ?? 'Usuario';

// Carrito
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cantidadProductos = 0;
foreach ($_SESSION['cart'] as $it) { 
    $cantidadProductos += (int)($it['qty'] ?? 0); 
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mis Órdenes - <?= htmlspecialchars($APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    :root {
      --primary: #2c3e50;
      --primary-light: #34495e;
      --accent: #3498db;
      --success: #27ae60;
      --danger: #c0392b;
      --dark: #1a1a1a;
      --gray-900: #2d3748;
      --gray-700: #4a5568;
      --gray-500: #718096;
      --gray-300: #cbd5e0;
      --gray-100: #f7fafc;
      --white: #ffffff;
      --bg-primary: #f8f9fa;
      --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
      --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
      --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
      --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      --radius: 8px;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: var(--bg-primary);
      color: var(--dark);
      line-height: 1.6;
    }

    /* HEADER */
    .header {
      background: var(--white);
      border-bottom: 1px solid var(--gray-300);
      padding: 1rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: var(--shadow-sm);
    }

    .logo {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--primary);
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .header-nav {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .btn-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 42px;
      height: 42px;
      border-radius: var(--radius);
      border: 1.5px solid var(--gray-300);
      background: var(--white);
      color: var(--gray-700);
      cursor: pointer;
      transition: var(--transition);
      position: relative;
      font-size: 1.125rem;
      text-decoration: none;
    }

    .btn-icon:hover {
      background: var(--gray-100);
      border-color: var(--gray-500);
    }

    .cart-badge {
      position: absolute;
      top: -6px;
      right: -6px;
      background: var(--danger);
      color: var(--white);
      border-radius: 999px;
      padding: 2px 6px;
      font-size: 0.7rem;
      font-weight: 700;
      min-width: 18px;
      text-align: center;
    }

    /* MENÚ HAMBURGUESA */
    #menu-overlay {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 999;
      display: none;
      opacity: 0;
      transition: opacity 0.3s;
    }
    #menu-overlay.show { display: block; opacity: 1; }

    #hamburger-menu {
      position: fixed;
      top: 0; right: -320px;
      width: 320px;
      height: 100vh;
      background: var(--white);
      box-shadow: var(--shadow-lg);
      z-index: 1000;
      transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      display: flex;
      flex-direction: column;
    }
    #hamburger-menu.show { right: 0; }

    .menu-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1.5rem;
      border-bottom: 1px solid var(--gray-300);
    }

    .menu-title {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--primary);
    }

    .menu-close {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      border: none;
      background: var(--gray-100);
      color: var(--gray-700);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
      transition: var(--transition);
    }
    .menu-close:hover { background: var(--gray-300); }

    .menu-body {
      flex: 1;
      overflow-y: auto;
      padding: 1rem;
    }

    .menu-section {
      margin-bottom: 1.5rem;
    }

    .menu-section-title {
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      color: var(--gray-500);
      margin-bottom: 0.75rem;
      padding: 0 0.5rem;
    }

    .menu-link {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 0.875rem 1rem;
      color: var(--gray-700);
      text-decoration: none;
      border-radius: var(--radius);
      transition: var(--transition);
      font-weight: 500;
    }
    .menu-link:hover {
      background: var(--gray-100);
      color: var(--primary);
    }
    .menu-link.active {
      background: var(--primary);
      color: var(--white);
    }

    .menu-link i {
      font-size: 1.125rem;
      width: 24px;
      text-align: center;
    }

    /* CONTAINER */
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem;
    }

    .page-header {
      margin-bottom: 2rem;
    }

    .page-title {
      font-size: 2rem;
      font-weight: 700;
      color: var(--primary);
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 0.5rem;
    }

    .page-subtitle {
      color: var(--gray-500);
      font-size: 1rem;
    }

    /* ALERTS */
    .alert {
      padding: 1rem 1.25rem;
      border-radius: var(--radius);
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .alert-error {
      background: #fee2e2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }

    /* ORDERS */
    .order-card {
      background: var(--white);
      border: 1px solid var(--gray-300);
      border-radius: var(--radius);
      overflow: hidden;
      margin-bottom: 1.5rem;
      box-shadow: var(--shadow-sm);
      transition: var(--transition);
    }
    .order-card:hover {
      box-shadow: var(--shadow-md);
    }

    .order-header {
      background: var(--gray-100);
      padding: 1.25rem 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .order-info h3 {
      font-size: 1.125rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 0.25rem;
    }

    .order-date {
      font-size: 0.9rem;
      color: var(--gray-500);
    }

    .order-body {
      padding: 1.5rem;
    }

    .order-item {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem 0;
      border-bottom: 1px solid var(--gray-300);
    }
    .order-item:last-child { border-bottom: none; }

    .order-item-image {
      width: 80px;
      height: 80px;
      object-fit: cover;
      border-radius: var(--radius);
      border: 1px solid var(--gray-300);
    }

    .order-item-info {
      flex: 1;
    }

    .order-item-name {
      font-weight: 600;
      color: var(--gray-900);
      margin-bottom: 0.25rem;
    }

    .order-item-qty {
      color: var(--gray-500);
      font-size: 0.9rem;
    }

    .order-item-price {
      font-weight: 700;
      color: var(--primary);
      font-size: 1.125rem;
    }

    .order-totals {
      margin-top: 1.5rem;
      padding-top: 1.5rem;
      border-top: 2px solid var(--gray-300);
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 0.5rem;
    }

    .order-total-row {
      display: flex;
      justify-content: space-between;
      min-width: 300px;
      font-size: 0.95rem;
    }

    .order-total-row.grand {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--primary);
      padding-top: 0.75rem;
      border-top: 1px solid var(--gray-300);
    }

    .empty-state {
      background: var(--white);
      padding: 3rem;
      border-radius: var(--radius);
      text-align: center;
      box-shadow: var(--shadow-sm);
    }

    .empty-state i {
      font-size: 4rem;
      color: var(--gray-300);
      margin-bottom: 1rem;
    }

    .empty-state h3 {
      font-size: 1.5rem;
      color: var(--gray-700);
      margin-bottom: 0.5rem;
    }

    .empty-state p {
      color: var(--gray-500);
      margin-bottom: 1.5rem;
    }

    .btn-primary {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.75rem 1.5rem;
      background: var(--primary);
      color: var(--white);
      text-decoration: none;
      border-radius: var(--radius);
      font-weight: 600;
      transition: var(--transition);
    }
    .btn-primary:hover {
      background: var(--primary-light);
    }

    @media (max-width: 768px) {
      .container { padding: 1rem; }
      .page-title { font-size: 1.5rem; }
      .order-header { padding: 1rem; }
      .order-body { padding: 1rem; }
      .order-item { flex-direction: column; align-items: flex-start; }
      .order-item-image { width: 100%; height: 200px; }
      .order-total-row { min-width: 100%; }
    }
  </style>
</head>
<body>

<!-- HEADER -->
<header class="header">
  <a href="index" class="logo">
    <i class="fas fa-store"></i>
    <?= htmlspecialchars($APP_NAME) ?>
  </a>

  <div class="header-nav">
    <a href="cart" class="btn-icon" title="Carrito">
      <i class="fas fa-shopping-cart"></i>
      <?php if ($cantidadProductos > 0): ?>
        <span class="cart-badge"><?= $cantidadProductos ?></span>
      <?php endif; ?>
    </a>
    
    <button id="menuButton" class="btn-icon" title="Menú">
      <i class="fas fa-bars"></i>
    </button>
  </div>
</header>

<!-- MENÚ HAMBURGUESA -->
<div id="menu-overlay"></div>
<div id="hamburger-menu">
  <div class="menu-header">
    <div class="menu-title">Menú</div>
    <button id="menu-close" class="menu-close">×</button>
  </div>
  
  <div class="menu-body">
    <?php if ($isLoggedIn): ?>
      <div class="menu-section">
        <div class="menu-section-title">Usuario</div>
        <div style="padding:0 1rem;margin-bottom:1rem;color:var(--gray-700)">
          <i class="fas fa-user-circle"></i> <?= htmlspecialchars($userName) ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="menu-section">
      <div class="menu-section-title">Navegación</div>
      <a href="index" class="menu-link">
        <i class="fas fa-home"></i> Inicio
      </a>
      <a href="cart" class="menu-link">
        <i class="fas fa-shopping-cart"></i> Carrito
      </a>
      <a href="my_orders" class="menu-link active">
        <i class="fas fa-box"></i> Mis Órdenes
      </a>
    </div>

    <div class="menu-section">
      <div class="menu-section-title">Cuenta</div>
      <?php if ($isLoggedIn): ?>
        <a href="logout" class="menu-link">
          <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
        </a>
      <?php else: ?>
        <a href="login" class="menu-link">
          <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
        </a>
      <?php endif; ?>
    </div>

    <div class="menu-section">
      <div class="menu-section-title">Enlaces</div>
      <a href="affiliate/login.php" class="menu-link">
        <i class="fas fa-handshake"></i> Afiliados
      </a>
      <a href="admin/login.php" class="menu-link">
        <i class="fas fa-shield-alt"></i> Administrador
      </a>
    </div>
  </div>
</div>

<!-- CONTENIDO -->
<div class="container">
  <div class="page-header">
    <h1 class="page-title">
      <i class="fas fa-box"></i> Mis Órdenes
    </h1>
    <p class="page-subtitle">Historial completo de tus compras</p>
  </div>

  <?php if (!empty($errorMessage)): ?>
    <div class="alert alert-error">
      <i class="fas fa-exclamation-circle"></i>
      <strong>Error:</strong> <?= htmlspecialchars($errorMessage) ?>
    </div>
  <?php endif; ?>

  <?php if (empty($orderGroups) && empty($errorMessage)): ?>
    <div class="empty-state">
      <i class="fas fa-box-open"></i>
      <h3>No tienes órdenes aún</h3>
      <p>Comienza a comprar en nuestros espacios activos</p>
      <a href="index" class="btn-primary">
        <i class="fas fa-shopping-bag"></i> Ir a comprar
      </a>
    </div>
  <?php else: ?>
    <?php foreach ($orderGroups as $order): ?>
      <div class="order-card">
        <div class="order-header">
          <div class="order-info">
            <h3><?= htmlspecialchars($order['order_number']) ?></h3>
            <div class="order-date">
              <i class="far fa-calendar"></i>
              <?= $order['created_at'] ? date('d/m/Y H:i', strtotime($order['created_at'])) : '' ?>
            </div>
          </div>
          <div><?= getStatusBadge($order['status']) ?></div>
        </div>
        
        <div class="order-body">
          <?php foreach ($order['items'] as $item): ?>
            <div class="order-item">
              <img 
                src="<?= !empty($item['product_image']) ? 'uploads/' . htmlspecialchars($item['product_image']) : 'assets/no-image.png' ?>" 
                alt="<?= htmlspecialchars($item['product_name']) ?>"
                class="order-item-image"
              >
              <div class="order-item-info">
                <div class="order-item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                <div class="order-item-qty">Cantidad: <?= number_format($item['qty'], 0) ?></div>
              </div>
              <div class="order-item-price">
                <?= fmt_any($item['qty'] * $item['unit_price'], $order['currency']) ?>
              </div>
            </div>
          <?php endforeach; ?>

          <div class="order-totals">
            <div class="order-total-row">
              <span>Subtotal:</span>
              <span><?= fmt_any($order['subtotal'], $order['currency']) ?></span>
            </div>
            <div class="order-total-row">
              <span>Impuestos:</span>
              <span><?= fmt_any($order['tax_total'], $order['currency']) ?></span>
            </div>
            <?php if ($order['shipping_cost'] > 0): ?>
              <div class="order-total-row">
                <span>Envío:</span>
                <span><?= fmt_any($order['shipping_cost'], $order['currency']) ?></span>
              </div>
            <?php endif; ?>
            <div class="order-total-row grand">
              <span>Total:</span>
              <span><?= fmt_any($order['grand_total'], $order['currency']) ?></span>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
// MENÚ HAMBURGUESA
const menuButton = document.getElementById('menuButton');
const menuOverlay = document.getElementById('menu-overlay');
const hamburgerMenu = document.getElementById('hamburger-menu');
const menuClose = document.getElementById('menu-close');

function openMenu() {
  menuOverlay.classList.add('show');
  hamburgerMenu.classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeMenu() {
  menuOverlay.classList.remove('show');
  hamburgerMenu.classList.remove('show');
  document.body.style.overflow = '';
}

if (menuButton) menuButton.addEventListener('click', openMenu);
if (menuClose) menuClose.addEventListener('click', closeMenu);
if (menuOverlay) menuOverlay.addEventListener('click', closeMenu);

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && hamburgerMenu.classList.contains('show')) {
    closeMenu();
  }
});
</script>

</body>
</html>
