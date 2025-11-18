<?php
declare(strict_types=1);
/**
 * checkout_con_uber.php - Finalizar Compra CON OPCIONES DE ENVÍO
 * 
 * IMPORTANTE: Este archivo reemplaza a checkout.php
 * Agrega 3 opciones de envío:
 * 1. Recoger en tienda (gratis)
 * 2. Envío gratis (si el afiliado lo ofrece)
 * 3. Envío por Uber (cotización en tiempo real)
 */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

// CONFIGURACIÓN DE SESIONES
$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0700, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}
$__isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
             (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
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
            'lifetime'=>0,'path'=>'/','domain'=>'',
            'secure'=>$__isHttps,'httponly'=>true,'samesite'=>'Lax',
        ]);
    }
    ini_set('session.use_strict_mode','0');
    ini_set('session.use_only_cookies','1');
    ini_set('session.gc_maxlifetime','86400');
    session_start();
}

// Normalizar uid → user_id
if ((!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) && !empty($_SESSION['uid'])) {
    $_SESSION['user_id'] = (int)$_SESSION['uid'];
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/uber/UberDirectAPI.php'; // ⭐ Nueva clase

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Verificar login
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    $_SESSION['error'] = "Debes iniciar sesión para continuar";
    header('Location: login.php');
    exit;
}

$pdo = db();

// Obtener usuario
$st = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE id=?");
$st->execute([$user_id]);
$user = $st->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    $_SESSION['error'] = "Usuario no encontrado";
    header('Location: login.php');
    exit;
}

// sale_id
$sale_id = 0;
if (isset($_GET['sale_id']) && (int)$_GET['sale_id'] > 0) {
    $sale_id = (int)$_GET['sale_id'];
} elseif (isset($_POST['sale_id']) && (int)$_POST['sale_id'] > 0) {
    $sale_id = (int)$_POST['sale_id'];
} elseif (!empty($_SESSION['cart']['groups'][0]['sale_id'])) {
    $sale_id = (int)$_SESSION['cart']['groups'][0]['sale_id'];
}

if ($sale_id <= 0) {
    $_SESSION['error'] = "No se especificó el espacio de venta (sale_id).";
    header('Location: cart.php');
    exit;
}

// Cargar info del espacio y afiliado
$st = $pdo->prepare("
    SELECT s.*, a.name AS affiliate_name, a.email AS affiliate_email, a.phone AS affiliate_phone
    FROM sales s
    JOIN affiliates a ON a.id = s.affiliate_id
    WHERE s.id = ?
");
$st->execute([$sale_id]);
$sale = $st->fetch(PDO::FETCH_ASSOC);
if (!$sale) {
    $_SESSION['error'] = "Espacio de venta no encontrado";
    header('Location: cart.php');
    exit;
}
$affiliate_id = (int)$sale['affiliate_id'];

// ⭐ Obtener ubicación de pickup del espacio
$st = $pdo->prepare("SELECT * FROM sale_pickup_locations WHERE sale_id = ? AND is_active = 1 LIMIT 1");
$st->execute([$sale_id]);
$pickup_location = $st->fetch(PDO::FETCH_ASSOC);
$has_pickup_location = !empty($pickup_location);

// cart_id del usuario
$cart_id = 0;
$st = $pdo->prepare("SELECT id FROM carts WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$st->execute([$user_id]);
$cart_id = (int)$st->fetchColumn();
if ($cart_id <= 0) {
    $guest_sid = (string)($_SESSION['guest_sid'] ?? ($_COOKIE['vg_guest'] ?? ''));
    if ($guest_sid !== '') {
        $st = $pdo->prepare("SELECT id FROM carts WHERE guest_sid = ? ORDER BY id DESC LIMIT 1");
        $st->execute([$guest_sid]);
        $cart_id = (int)$st->fetchColumn();
    }
}

if ($cart_id <= 0) {
    $_SESSION['error'] = "Carrito no encontrado";
    header('Location: cart.php');
    exit;
}

// Cargar items del carrito
$sqlItems = "
    SELECT
        ci.product_id,
        ci.qty AS quantity,
        ci.unit_price,
        COALESCE(ci.tax_rate, 0) AS tax_rate,
        p.name,
        p.price AS product_price,
        p.image,
        p.sale_id,
        p.currency
    FROM cart_items ci
    JOIN products p ON p.id = ci.product_id
    WHERE ci.cart_id = ?
      AND (p.sale_id = ? OR ci.sale_id = ?)
    ORDER BY p.name
";
$st = $pdo->prepare($sqlItems);
$st->execute([$cart_id, $sale_id, $sale_id]);
$items = $st->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    $_SESSION['error'] = "No hay productos en el carrito para este espacio";
    header('Location: cart.php');
    exit;
}

// Funciones auxiliares
function fmt_price_local($n, $cur='CRC'){
    $cur = strtoupper((string)$cur);
    if ($cur === 'USD') return '$'.number_format((float)$n, 2);
    return '₡'.number_format((float)$n, 2);
}

function ensure_img_url($img){
    if (!$img) return '/assets/placeholder.jpg';
    if (preg_match('~^https?://~i', $img)) return $img;
    if ($img[0] === '/') return $img;
    return '/uploads/' . ltrim($img, '/');
}

// Calcular totales
$subtotal = 0.0;
$tax_total = 0.0;
$grand_total = 0.0;
$currency = 'CRC';

foreach ($items as &$it) {
    $qty  = (float)($it['quantity'] ?? 1);
    $unit = isset($it['unit_price']) && $it['unit_price'] !== null
          ? (float)$it['unit_price']
          : (float)($it['product_price'] ?? 0);
    $line = $qty * $unit;
    $raw  = (float)($it['tax_rate'] ?? 0);
    $tr   = 0.0;
    if ($raw > 1.0 && $raw <= 100.0) $tr = $raw/100.0;
    elseif ($raw >= 0.0 && $raw <= 1.0) $tr = $raw;
    $line_tax = $line * $tr;
    $it['line_subtotal'] = $line;
    $it['line_tax']      = $line_tax;
    $it['line_total']    = $line + $line_tax;
    $it['image_url']     = ensure_img_url($it['image'] ?? '');
    $subtotal   += $line;
    $tax_total  += $line_tax;
    if (!empty($it['currency'])) $currency = strtoupper($it['currency']);
}
unset($it);

$grand_total = $subtotal + $tax_total;

// Tipo de cambio
$exchange_rate = 510.0;
try {
    $row = $pdo->query("SELECT exchange_rate FROM settings ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($row && (float)$row['exchange_rate']>0) $exchange_rate=(float)$row['exchange_rate'];
} catch (Throwable $e) {}

$grand_total_usd = $currency === 'USD' ? $grand_total : ($grand_total / $exchange_rate);

// Métodos de pago del afiliado
$st = $pdo->prepare("
    SELECT paypal_email, sinpe_phone, active_paypal, active_sinpe
    FROM affiliate_payment_methods
    WHERE affiliate_id = ?
    LIMIT 1
");
$st->execute([$affiliate_id]);
$pm = $st->fetch(PDO::FETCH_ASSOC) ?: [];
$has_paypal = (!empty($pm['active_paypal']) && !empty($pm['paypal_email']));
$has_sinpe  = (!empty($pm['active_sinpe'])  && !empty($pm['sinpe_phone']));

$flash_error = $_SESSION['error'] ?? '';
if ($flash_error) unset($_SESSION['error']);

if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
ini_set('default_charset', 'UTF-8');

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
  <title>Checkout - <?= htmlspecialchars($APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary: #2c3e50;
      --primary-light: #34495e;
      --accent: #3498db;
      --success: #27ae60;
      --danger: #c0392b;
      --warning: #f39c12;
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
    .menu-link i {
      font-size: 1.125rem;
      width: 24px;
      text-align: center;
    }
    /* CONTAINER */
    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 2rem;
    }
    .page-header {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: var(--white);
      padding: 2rem;
      border-radius: var(--radius);
      margin-bottom: 2rem;
      box-shadow: var(--shadow-md);
    }
    .page-title {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }
    .page-subtitle {
      opacity: 0.9;
      font-size: 1rem;
    }
    /* ALERT */
    .alert {
      padding: 1rem 1.25rem;
      border-radius: var(--radius);
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      background: #fee2e2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }
    .alert-warning {
      background: #fff3cd;
      color: #856404;
      border: 1px solid #ffeeba;
    }
    /* CHECKOUT GRID */
    .checkout-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 2rem;
    }
    .section {
      background: var(--white);
      border: 1px solid var(--gray-300);
      border-radius: var(--radius);
      padding: 1.5rem;
      box-shadow: var(--shadow-sm);
    }
    .section-title {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 1.25rem;
      padding-bottom: 0.75rem;
      border-bottom: 2px solid var(--gray-300);
    }
    /* FORM */
    .form-group {
      margin-bottom: 1.25rem;
    }
    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 600;
      color: var(--gray-700);
      font-size: 0.95rem;
    }
    .form-group input,
    .form-group textarea {
      width: 100%;
      padding: 0.75rem;
      border: 1.5px solid var(--gray-300);
      border-radius: var(--radius);
      font-size: 0.95rem;
      transition: var(--transition);
      font-family: inherit;
    }
    .form-group input:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    }
    .form-group input:read-only {
      background: var(--gray-100);
      color: var(--gray-500);
    }
    /* ⭐ SHIPPING OPTIONS */
    .shipping-options {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .shipping-option {
      display: flex;
      align-items: flex-start;
      gap: 1rem;
      padding: 1.25rem;
      border: 2px solid var(--gray-300);
      border-radius: var(--radius);
      cursor: pointer;
      transition: var(--transition);
      background: var(--white);
    }
    .shipping-option:hover {
      border-color: var(--accent);
      background: #f0f9ff;
    }
    .shipping-option.selected {
      border-color: var(--accent);
      background: #e0f2fe;
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    }
    .shipping-option.disabled {
      opacity: 0.5;
      cursor: not-allowed;
      background: var(--gray-100);
    }
    .shipping-option input[type="radio"] {
      width: 20px;
      height: 20px;
      cursor: pointer;
      margin-top: 2px;
    }
    .shipping-icon {
      font-size: 2rem;
      min-width: 40px;
      text-align: center;
    }
    .shipping-info {
      flex: 1;
    }
    .shipping-info h4 {
      font-weight: 700;
      color: var(--gray-900);
      margin-bottom: 0.25rem;
    }
    .shipping-info p {
      font-size: 0.9rem;
      color: var(--gray-500);
      margin: 0;
    }
    .shipping-cost {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--success);
    }
    .shipping-cost.calculating {
      color: var(--warning);
      font-size: 0.9rem;
    }
    /* PAYMENT OPTIONS */
    .payment-methods {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }
    .payment-option {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1.25rem;
      border: 2px solid var(--gray-300);
      border-radius: var(--radius);
      cursor: pointer;
      transition: var(--transition);
      background: var(--white);
    }
    .payment-option:hover {
      border-color: var(--accent);
      background: #f0f9ff;
    }
    .payment-option.selected {
      border-color: var(--accent);
      background: #e0f2fe;
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    }
    .payment-option input[type="radio"] {
      width: 20px;
      height: 20px;
      cursor: pointer;
    }
    .payment-icon {
      font-size: 2rem;
    }
    .payment-info h4 {
      font-weight: 700;
      color: var(--gray-900);
      margin-bottom: 0.25rem;
    }
    .payment-info p {
      font-size: 0.9rem;
      color: var(--gray-500);
      margin: 0;
    }
    /* CART ITEMS */
    .cart-item {
      display: flex;
      gap: 1rem;
      padding: 1rem;
      border: 1px solid var(--gray-300);
      border-radius: var(--radius);
      margin-bottom: 1rem;
      background: var(--gray-100);
    }
    .cart-item img {
      width: 100px;
      height: 100px;
      object-fit: cover;
      border-radius: var(--radius);
      border: 1px solid var(--gray-300);
    }
    .cart-item-info {
      flex: 1;
    }
    .cart-item-name {
      font-weight: 700;
      color: var(--gray-900);
      margin-bottom: 0.5rem;
      font-size: 1.05rem;
    }
    .cart-item-details {
      font-size: 0.9rem;
      color: var(--gray-600);
      line-height: 1.6;
    }
    /* TOTALS */
    .totals {
      background: var(--gray-100);
      border: 1px solid var(--gray-300);
      border-radius: var(--radius);
      padding: 1.25rem;
      margin-top: 1rem;
    }
    .totals-row {
      display: flex;
      justify-content: space-between;
      padding: 0.5rem 0;
      font-size: 0.95rem;
    }
    .totals-row.shipping {
      color: var(--accent);
      font-weight: 600;
    }
    .totals-row.grand {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--primary);
      padding-top: 1rem;
      margin-top: 0.75rem;
      border-top: 2px solid var(--gray-300);
    }
    /* BUTTONS */
    .btn-primary {
      width: 100%;
      padding: 1rem;
      border: none;
      border-radius: var(--radius);
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: var(--white);
      font-size: 1.125rem;
      font-weight: 700;
      cursor: pointer;
      transition: var(--transition);
      margin-top: 1.5rem;
    }
    .btn-primary:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: var(--shadow-lg);
    }
    .btn-primary:disabled {
      background: var(--gray-300);
      cursor: not-allowed;
      color: var(--gray-500);
    }
    .btn-secondary {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--accent);
      text-decoration: none;
      font-weight: 600;
      padding: 0.75rem 1rem;
      border-radius: var(--radius);
      transition: var(--transition);
    }
    .btn-secondary:hover {
      background: var(--gray-100);
    }
    /* ⭐ DELIVERY ADDRESS SECTION */
    #delivery-address-section {
      display: none;
      margin-top: 1.5rem;
      padding: 1.5rem;
      background: #f0f9ff;
      border: 2px dashed var(--accent);
      border-radius: var(--radius);
    }
    #delivery-address-section.show {
      display: block;
    }
    @media (max-width: 1024px) {
      .checkout-grid {
        grid-template-columns: 1fr;
      }
    }
    @media (max-width: 768px) {
      .container {
        padding: 1rem;
      }
      .page-title {
        font-size: 1.5rem;
      }
      .section {
        padding: 1rem;
      }
    }
  </style>
</head>
<body>
<!-- HEADER -->
<header class="header">
  <a href="index.php" class="logo">
    <i class="fas fa-store"></i>
    <?= htmlspecialchars($APP_NAME) ?>
  </a>
  <div class="header-nav">
    <a href="cart.php" class="btn-icon" title="Carrito">
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
      <a href="index.php" class="menu-link">
        <i class="fas fa-home"></i> Inicio
      </a>
      <a href="cart.php" class="menu-link">
        <i class="fas fa-shopping-cart"></i> Carrito
      </a>
      <a href="my_orders.php" class="menu-link">
        <i class="fas fa-box"></i> Mis Órdenes
      </a>
    </div>
    <div class="menu-section">
      <div class="menu-section-title">Cuenta</div>
      <?php if ($isLoggedIn): ?>
        <a href="logout.php" class="menu-link">
          <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
        </a>
      <?php else: ?>
        <a href="login.php" class="menu-link">
          <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- CONTENIDO -->
<div class="container">
  <div class="page-header">
    <h1 class="page-title">
      <i class="fas fa-shopping-bag"></i> Finalizar Compra
    </h1>
    <p class="page-subtitle">
      <?= htmlspecialchars($sale['title'] ?? '') ?> — <?= htmlspecialchars($sale['affiliate_name'] ?? '') ?>
    </p>
  </div>

  <?php if ($flash_error): ?>
    <div class="alert">
      <i class="fas fa-exclamation-circle"></i>
      <strong><?= htmlspecialchars($flash_error) ?></strong>
    </div>
  <?php endif; ?>

  <?php if (!$has_pickup_location): ?>
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle"></i>
      <strong>Nota:</strong> El vendedor aún no ha configurado la ubicación de recogida, por lo que el envío por Uber no está disponible temporalmente.
    </div>
  <?php endif; ?>

  <form id="checkout-form" action="process_checkout.php" method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="sale_id" value="<?= (int)$sale_id ?>">
    <input type="hidden" name="cart_id" value="<?= (int)$cart_id ?>">
    <input type="hidden" name="shipping_cost" id="shipping_cost_hidden" value="0">
    
    <div class="checkout-grid">
      <!-- COLUMNA IZQUIERDA: Información del cliente, envío y pago -->
      <div>
        <!-- Información del Cliente -->
        <div class="section">
          <h2 class="section-title">
            <i class="fas fa-user"></i> Información del Cliente
          </h2>
          <div class="form-group">
            <label>Nombre completo</label>
            <input type="text" value="<?= htmlspecialchars($user['name'] ?? '') ?>" readonly>
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly>
          </div>
          <div class="form-group">
            <label>Teléfono <span style="color:var(--danger)">*</span></label>
            <input type="tel" name="customer_phone" id="customer_phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
          </div>
        </div>

        <!-- ⭐ OPCIONES DE ENVÍO -->
        <div class="section" style="margin-top: 1.5rem;">
          <h2 class="section-title">
            <i class="fas fa-truck"></i> Método de Envío
          </h2>
          
          <div class="shipping-options">
            <!-- Opción 1: Recoger en tienda -->
            <label class="shipping-option" data-cost="0">
              <input type="radio" name="shipping_method" value="pickup" required>
              <span class="shipping-icon">🏪</span>
              <div class="shipping-info">
                <h4>Recoger en tienda</h4>
                <p>Coordina con el vendedor para recoger tu pedido</p>
              </div>
              <span class="shipping-cost">GRATIS</span>
            </label>

            <!-- Opción 2: Envío gratis (si el vendedor lo ofrece) -->
            <!-- Por ahora comentado, se puede activar si el afiliado configura envío gratis
            <label class="shipping-option" data-cost="0">
              <input type="radio" name="shipping_method" value="free_shipping">
              <span class="shipping-icon">🎁</span>
              <div class="shipping-info">
                <h4>Envío gratis</h4>
                <p>El vendedor cubre los costos de envío</p>
              </div>
              <span class="shipping-cost">GRATIS</span>
            </label>
            -->

            <!-- Opción 3: Envío por Uber -->
            <label class="shipping-option <?= $has_pickup_location ? '' : 'disabled' ?>" data-cost="0" id="uber-option">
              <input type="radio" name="shipping_method" value="uber" <?= $has_pickup_location ? '' : 'disabled' ?>>
              <span class="shipping-icon">🚗</span>
              <div class="shipping-info">
                <h4>Envío por Uber</h4>
                <p>Entrega rápida con conductor de Uber</p>
                <?php if (!$has_pickup_location): ?>
                  <p style="color: var(--danger); font-weight: 600;">⚠️ No disponible - vendedor debe configurar ubicación</p>
                <?php else: ?>
                  <p style="color: var(--gray-400); font-size: 0.85rem;">
                    Se calculará el costo al ingresar tu dirección
                  </p>
                <?php endif; ?>
              </div>
              <span class="shipping-cost calculating" id="uber-cost">-</span>
            </label>
          </div>

          <!-- ⭐ SECCIÓN PARA DIRECCIÓN DE ENTREGA (Solo visible si selecciona Uber) -->
          <div id="delivery-address-section">
            <h4 style="margin-bottom: 1rem; color: var(--primary);">
              <i class="fas fa-map-marker-alt"></i> Dirección de Entrega
            </h4>
            <div class="form-group">
              <label>Dirección completa <span style="color:var(--danger)">*</span></label>
              <div style="display: flex; gap: 0.5rem;">
                <input type="text" name="delivery_address" id="delivery_address"
                       style="flex: 1;"
                       placeholder="Ej: Avenida Central, Calle 5, San José">
                <button type="button" id="geolocate-btn" class="btn-secondary"
                        style="white-space: nowrap; padding: 0.75rem 1rem; background: var(--accent); color: white; border: none; cursor: pointer; border-radius: var(--radius);"
                        title="Usar mi ubicación actual">
                  <i class="fas fa-crosshairs"></i> Mi Ubicación
                </button>
              </div>
              <input type="hidden" name="delivery_lat" id="delivery_lat">
              <input type="hidden" name="delivery_lng" id="delivery_lng">
            </div>
            <div class="form-group">
              <label>Apartamento, piso, oficina (opcional)</label>
              <input type="text" name="delivery_address_line2" 
                     placeholder="Ej: Edificio A, Piso 3">
            </div>
            <div class="form-group">
              <label>Instrucciones de entrega (opcional)</label>
              <textarea name="delivery_instructions" rows="2"
                        placeholder="Ej: Dejar en recepción, tocar timbre verde"></textarea>
            </div>
            <button type="button" id="calculate-uber-btn" class="btn-primary" style="margin-top: 1rem;">
              <i class="fas fa-calculator"></i> Calcular Costo de Envío
            </button>
            <div id="uber-quote-result" style="margin-top: 1rem; display: none;">
              <!-- Resultado de la cotización -->
            </div>
          </div>

          <div class="form-group" style="margin-top: 1.5rem;">
            <label>Notas adicionales (opcional)</label>
            <textarea name="notes" rows="3" placeholder="Instrucciones especiales, comentarios, etc."></textarea>
          </div>
        </div>

        <!-- Método de Pago -->
        <div class="section" style="margin-top: 1.5rem;">
          <h2 class="section-title">
            <i class="fas fa-credit-card"></i> Método de Pago
          </h2>
          <?php if (!$has_paypal && !$has_sinpe): ?>
            <div class="alert">
              <i class="fas fa-exclamation-triangle"></i>
              El vendedor no tiene métodos de pago configurados.
            </div>
          <?php else: ?>
            <div class="payment-methods">
              <?php if ($has_paypal): ?>
                <label class="payment-option">
                  <input type="radio" name="payment_method" value="paypal" required>
                  <span class="payment-icon">💳</span>
                  <div class="payment-info">
                    <h4>PayPal</h4>
                    <p>Pago seguro con tarjeta o cuenta PayPal</p>
                    <?php if (strtoupper($currency) !== 'USD'): ?>
                      <p style="color: var(--gray-400); font-size: 0.85rem;">
                        Aprox. $<?= number_format($grand_total_usd, 2) ?> USD
                      </p>
                    <?php endif; ?>
                  </div>
                </label>
              <?php endif; ?>
              <?php if ($has_sinpe): ?>
                <label class="payment-option">
                  <input type="radio" name="payment_method" value="sinpe" required>
                  <span class="payment-icon">📱</span>
                  <div class="payment-info">
                    <h4>SINPE Móvil</h4>
                    <p>Transferencia bancaria instantánea</p>
                    <p style="color: var(--gray-400); font-size: 0.85rem;">
                      Deberás subir tu comprobante de pago
                    </p>
                  </div>
                </label>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- COLUMNA DERECHA: Resumen del pedido -->
      <div>
        <div class="section">
          <h2 class="section-title">
            <i class="fas fa-box-open"></i> Resumen del Pedido
          </h2>
          <?php foreach ($items as $it): ?>
            <div class="cart-item">
              <img src="<?= htmlspecialchars($it['image_url']) ?>" alt="<?= htmlspecialchars($it['name']) ?>">
              <div class="cart-item-info">
                <div class="cart-item-name"><?= htmlspecialchars($it['name']) ?></div>
                <div class="cart-item-details">
                  <div><strong>Cantidad:</strong> <?= number_format($it['quantity'], 0) ?></div>
                  <div><strong>Precio unitario:</strong> <?= fmt_price_local($it['unit_price'] ?? $it['product_price'], $currency) ?></div>
                  <div><strong>Subtotal:</strong> <?= fmt_price_local($it['line_subtotal'], $currency) ?></div>
                  <?php if ($it['line_tax'] > 0): ?>
                    <div><strong>IVA:</strong> <?= fmt_price_local($it['line_tax'], $currency) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>

          <div class="totals">
            <div class="totals-row">
              <span>Subtotal:</span>
              <span><?= fmt_price_local($subtotal, $currency) ?></span>
            </div>
            <div class="totals-row">
              <span>IVA:</span>
              <span><?= fmt_price_local($tax_total, $currency) ?></span>
            </div>
            <div class="totals-row shipping" id="shipping-total-row" style="display: none;">
              <span>Envío:</span>
              <span id="shipping-total-display">₡0.00</span>
            </div>
            <div class="totals-row grand">
              <span>Total a pagar:</span>
              <span id="grand-total-display"><?= fmt_price_local($grand_total, $currency) ?></span>
            </div>
          </div>

          <button type="submit" class="btn-primary" <?= (!$has_paypal && !$has_sinpe) ? 'disabled' : '' ?>>
            <i class="fas fa-lock"></i> Confirmar y Pagar
          </button>

          <div style="text-align: center; margin-top: 1rem;">
            <a href="cart.php" class="btn-secondary">
              <i class="fas fa-arrow-left"></i> Volver al carrito
            </a>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
// Variables globales
let shippingCost = 0;
const baseTotal = <?= isset($grand_total) && is_numeric($grand_total) ? $grand_total : 0 ?>;
const currency = '<?= isset($currency) ? addslashes($currency) : 'CRC' ?>';

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

// SHIPPING OPTIONS
document.querySelectorAll('.shipping-option').forEach(function(opt) {
  opt.addEventListener('click', function() {
    if (this.classList.contains('disabled')) return;

    document.querySelectorAll('.shipping-option').forEach(function(o) {
      o.classList.remove('selected');
    });
    this.classList.add('selected');
    var r = this.querySelector('input[type="radio"]');
    if (r) r.checked = true;

    // Mostrar/ocultar sección de dirección de entrega
    var deliverySection = document.getElementById('delivery-address-section');
    if (deliverySection) {
      if (r && r.value === 'uber') {
        deliverySection.classList.add('show');
      } else {
        deliverySection.classList.remove('show');
        shippingCost = 0;
        updateTotals();
      }
    }
  });
});

// También escuchar cambios en los radio buttons directamente
document.querySelectorAll('input[name="shipping_method"]').forEach(function(radio) {
  radio.addEventListener('change', function() {
    var deliverySection = document.getElementById('delivery-address-section');
    if (deliverySection) {
      if (this.value === 'uber') {
        deliverySection.classList.add('show');
      } else {
        deliverySection.classList.remove('show');
      }
    }
  });
});

// PAYMENT OPTIONS
document.querySelectorAll('.payment-option').forEach(opt => {
  opt.addEventListener('click', function() {
    document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
    this.classList.add('selected');
    const r = this.querySelector('input[type="radio"]');
    if (r) r.checked = true;
  });
});

// ⭐ GEOLOCALIZACIÓN EN TIEMPO REAL
document.getElementById('geolocate-btn')?.addEventListener('click', async function() {
  if (!navigator.geolocation) {
    alert('Tu navegador no soporta geolocalización');
    return;
  }

  const btn = this;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ubicando...';

  navigator.geolocation.getCurrentPosition(
    async (position) => {
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;

      // Guardar coordenadas
      document.getElementById('delivery_lat').value = lat;
      document.getElementById('delivery_lng').value = lng;

      // Hacer reverse geocoding para obtener la dirección
      try {
        // Usar servicio de geocoding inverso
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lng}&format=json&accept-language=es`);
        const data = await response.json();

        if (data && data.display_name) {
          document.getElementById('delivery_address').value = data.display_name;
          alert('✅ Ubicación detectada correctamente');
        } else {
          alert('📍 Coordenadas obtenidas. Por favor completa tu dirección manualmente.');
        }
      } catch (error) {
        console.error('Error en reverse geocoding:', error);
        alert('📍 Coordenadas obtenidas: ' + lat.toFixed(6) + ', ' + lng.toFixed(6) + '\nPor favor completa tu dirección manualmente.');
      }

      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-crosshairs"></i> Mi Ubicación';
    },
    (error) => {
      console.error('Error de geolocalización:', error);
      let errorMsg = 'No se pudo obtener tu ubicación. ';

      switch(error.code) {
        case error.PERMISSION_DENIED:
          errorMsg += 'Debes permitir el acceso a tu ubicación.';
          break;
        case error.POSITION_UNAVAILABLE:
          errorMsg += 'Ubicación no disponible.';
          break;
        case error.TIMEOUT:
          errorMsg += 'Tiempo de espera agotado.';
          break;
        default:
          errorMsg += 'Error desconocido.';
      }

      alert(errorMsg);
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-crosshairs"></i> Mi Ubicación';
    },
    {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 0
    }
  );
});

// ⭐ CALCULAR COSTO DE UBER
document.getElementById('calculate-uber-btn')?.addEventListener('click', async function() {
  const address = document.getElementById('delivery_address').value.trim();
  
  if (!address) {
    alert('Por favor ingresa tu dirección de entrega');
    return;
  }
  
  this.disabled = true;
  this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calculando...';
  
  try {
    const lat = document.getElementById('delivery_lat').value;
    const lng = document.getElementById('delivery_lng').value;

    const response = await fetch('uber/ajax_uber_quote.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        sale_id: <?= $sale_id ?>,
        delivery_address: address,
        delivery_city: 'San José',
        delivery_lat: lat ? parseFloat(lat) : null,
        delivery_lng: lng ? parseFloat(lng) : null,
        csrf_token: '<?= $csrf_token ?>'
      })
    });
    
    const data = await response.json();

    if (data.success) {
      // Determinar si es sandbox
      const isSandbox = data.is_sandbox || false;
      const sandboxBadge = isSandbox ? 
        '<span style="background: #fff3cd; color: #856404; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; margin-left: 0.5rem;"><i class="fas fa-flask"></i> MODO DEMO</span>' : 
        '';
      
      // Mostrar resultado
      const resultDiv = document.getElementById('uber-quote-result');
      resultDiv.innerHTML = `
        <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 1rem; border-radius: 8px;">
          <h4 style="color: #155724; margin: 0 0 0.5rem 0;">
            <i class="fas fa-check-circle"></i> Cotización Exitosa${sandboxBadge}
          </h4>
          ${isSandbox ? '<p style="margin: 0.5rem 0; font-size: 0.85rem; color: #856404; background: #fff3cd; padding: 0.5rem; border-radius: 4px;"><i class="fas fa-info-circle"></i> <strong>Modo Demo:</strong> Esta cotización es simulada. Cuando configures tus credenciales de Uber, se usarán precios reales.</p>' : ''}
          <p style="margin: 0.5rem 0;"><strong>Costo base Uber:</strong> ${data.uber_base_cost_formatted}</p>
          <p style="margin: 0.5rem 0;"><strong>Comisión plataforma:</strong> ${data.commission_formatted}</p>
          <p style="margin: 0.5rem 0; font-size: 1.2rem; color: #155724;">
            <strong>Total envío:</strong> ${data.total_cost_formatted}
          </p>
          <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; color: #666;">
            <i class="fas fa-clock"></i> Tiempo estimado: ${data.estimated_time || 'N/A'}
          </p>
        </div>
      `;
      resultDiv.style.display = 'block';
      
      // Actualizar costo en la UI
      document.getElementById('uber-cost').textContent = data.total_cost_formatted;
      document.getElementById('uber-cost').classList.remove('calculating');
      
      // Actualizar totales
      shippingCost = data.total_cost;
      updateTotals();
      
    } else {
      alert('Error: ' + (data.message || 'No se pudo obtener cotización'));
    }
    
  } catch (error) {
    console.error('Error:', error);
    alert('Error al calcular el costo de envío');
  } finally {
    this.disabled = false;
    this.innerHTML = '<i class="fas fa-calculator"></i> Calcular Costo de Envío';
  }
});

// Función para actualizar totales
function updateTotals() {
  const shippingRow = document.getElementById('shipping-total-row');
  const shippingDisplay = document.getElementById('shipping-total-display');
  const grandTotalDisplay = document.getElementById('grand-total-display');
  const hiddenInput = document.getElementById('shipping_cost_hidden');
  
  if (shippingCost > 0) {
    shippingRow.style.display = 'flex';
    const formatted = currency === 'USD' 
      ? '$' + shippingCost.toFixed(2) 
      : '₡' + shippingCost.toLocaleString('es-CR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    shippingDisplay.textContent = formatted;
  } else {
    shippingRow.style.display = 'none';
  }
  
  const newTotal = baseTotal + shippingCost;
  const totalFormatted = currency === 'USD'
    ? '$' + newTotal.toFixed(2)
    : '₡' + newTotal.toLocaleString('es-CR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  grandTotalDisplay.textContent = totalFormatted;
  
  hiddenInput.value = shippingCost;
}

// VALIDACIÓN DEL FORMULARIO
const form = document.getElementById('checkout-form');
form.addEventListener('submit', function(e){
  // Validar método de pago
  const pm = document.querySelector('input[name="payment_method"]:checked');
  if (!pm) { 
    e.preventDefault(); 
    alert('Por favor selecciona un método de pago'); 
    return false;
  }
  
  // Validar teléfono
  const phone = document.getElementById('customer_phone').value.trim();
  if (!phone) { 
    e.preventDefault(); 
    alert('Por favor ingresa tu número de teléfono'); 
    return false;
  }
  
  // Validar método de envío
  const sm = document.querySelector('input[name="shipping_method"]:checked');
  if (!sm) {
    e.preventDefault();
    alert('Por favor selecciona un método de envío');
    return false;
  }
  
  // Si seleccionó Uber, validar que tenga dirección y cotización
  if (sm.value === 'uber') {
    const address = document.getElementById('delivery_address').value.trim();
    if (!address) {
      e.preventDefault();
      alert('Por favor ingresa tu dirección de entrega');
      return false;
    }
    
    if (shippingCost <= 0) {
      e.preventDefault();
      alert('Por favor calcula el costo de envío antes de continuar');
      return false;
    }
  }
});
</script>
</body>
</html>