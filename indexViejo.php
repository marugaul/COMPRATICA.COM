<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

// ============= LOG DE DEBUG =============
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$logFile = $logDir . '/index_debug.log';

function logDebug($msg, $data = null) {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($data) $line .= ' | ' . json_encode($data);
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

logDebug("INDEX_START", ['uri' => $_SERVER['REQUEST_URI']]);

$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0700, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}

logDebug("SESSION_PATH_SET", ['path' => $__sessPath]);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

// Detectar HTTPS
$__isHttps = false;
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') $__isHttps = true;
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') $__isHttps = true;
if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') $__isHttps = true;
if (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false) $__isHttps = true;

// Dominio de cookie
$host = $_SERVER['HTTP_HOST'] ?? parse_url(defined('BASE_URL') ? BASE_URL : '', PHP_URL_HOST) ?? '';
$cookieDomain = '';
if ($host && strpos($host, 'localhost') === false && !filter_var($host, FILTER_VALIDATE_IP)) {
    $clean = preg_replace('/^www\./i', '', $host);
    if (filter_var($clean, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        $cookieDomain = $clean;
    }
}

logDebug("BEFORE_SESSION_START", [
    'session_status' => session_status(),
    'cookies' => $_COOKIE,
    'cookie_domain' => $cookieDomain,
    'host' => $host
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PHPSESSID');
    
    if (PHP_VERSION_ID < 70300) {
        ini_set('session.cookie_lifetime', '0');
        ini_set('session.cookie_path', '/');
        ini_set('session.cookie_domain', $cookieDomain);
        ini_set('session.cookie_secure', $__isHttps ? '1' : '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_set_cookie_params(0, '/', $cookieDomain, $__isHttps, true);
    } else {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => $cookieDomain,
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

logDebug("AFTER_SESSION_START", [
    'sid' => session_id(),
    'session_data' => $_SESSION,
    'cookie_domain' => $cookieDomain
]);

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cantidadProductos = 0;
foreach ($_SESSION['cart'] as $it) { $cantidadProductos += (int)($it['qty'] ?? 0); }

// Verificar si el usuario está logueado
$isLoggedIn = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
$userName = $_SESSION['name'] ?? 'Usuario';

logDebug("USER_CHECK", [
    'isLoggedIn' => $isLoggedIn,
    'uid' => $_SESSION['uid'] ?? null,
    'userName' => $userName
]);

if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
ini_set('default_charset', 'UTF-8');

// CSRF por cookie
$token = $_COOKIE['vg_csrf'] ?? bin2hex(random_bytes(32));
$isHttps = $__isHttps;
if (PHP_VERSION_ID < 70300) {
    setcookie('vg_csrf', $token, time()+7200, '/', $cookieDomain, $isHttps, false);
} else {
    setcookie('vg_csrf', $token, [
        'expires'  => time()+7200,
        'path'     => '/',
        'domain'   => $cookieDomain,
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

logDebug("RENDERING_PAGE", ['sales_count' => count($sales)]);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo APP_NAME; ?> — Marketplace</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    :root {
      /* Colores elegantes */
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

    /* ===================================== */
    /* HEADER CON MENÚ HAMBURGUESA */
    /* ===================================== */
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
      display: flex;
      align-items: center;
      gap: 0.75rem;
      letter-spacing: -0.02em;
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

    /* ===================================== */
    /* MENÚ HAMBURGUESA */
    /* ===================================== */
    #menu-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 999;
      display: none;
      opacity: 0;
      transition: opacity 0.3s;
    }

    #menu-overlay.show {
      display: block;
      opacity: 1;
    }

    #hamburger-menu {
      position: fixed;
      top: 0;
      right: -320px;
      width: 320px;
      height: 100vh;
      background: var(--white);
      box-shadow: -4px 0 20px rgba(0, 0, 0, 0.1);
      z-index: 1000;
      transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      overflow-y: auto;
    }

    #hamburger-menu.show {
      right: 0;
    }

    .menu-header {
      padding: 1.5rem;
      border-bottom: 1px solid var(--gray-300);
      background: linear-gradient(to right, #f8f9fa, #ffffff);
    }

    .menu-user {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 0.75rem;
    }

    .menu-user-avatar {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: var(--primary);
      color: var(--white);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1.25rem;
    }

    .menu-user-info h3 {
      font-size: 1.0625rem;
      font-weight: 600;
      color: var(--dark);
      margin-bottom: 0.125rem;
    }

    .menu-user-info p {
      font-size: 0.875rem;
      color: var(--gray-500);
    }

    .menu-close {
      position: absolute;
      top: 1.25rem;
      right: 1.25rem;
      width: 32px;
      height: 32px;
      border: none;
      background: transparent;
      color: var(--gray-500);
      font-size: 1.5rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
      transition: var(--transition);
    }

    .menu-close:hover {
      background: var(--gray-100);
      color: var(--dark);
    }

    .menu-body {
      padding: 1rem 0;
    }

    .menu-item {
      display: flex;
      align-items: center;
      gap: 0.875rem;
      padding: 0.875rem 1.5rem;
      color: var(--gray-700);
      text-decoration: none;
      transition: var(--transition);
      font-size: 0.9375rem;
      font-weight: 500;
    }

    .menu-item:hover {
      background: var(--gray-100);
      color: var(--primary);
    }

    .menu-item i {
      width: 20px;
      text-align: center;
      font-size: 1.0625rem;
      color: var(--gray-500);
    }

    .menu-item:hover i {
      color: var(--accent);
    }

    .menu-divider {
      height: 1px;
      background: var(--gray-300);
      margin: 0.5rem 0;
    }

    .menu-item.primary {
      color: var(--accent);
      font-weight: 600;
    }

    .menu-item.primary i {
      color: var(--accent);
    }

    .menu-item.danger {
      color: var(--danger);
    }

    .menu-item.danger i {
      color: var(--danger);
    }

    /* ===================================== */
    /* POPOVER DEL CARRITO */
    /* ===================================== */
    #cart-popover {
      position: absolute;
      top: calc(100% + 12px);
      right: 0;
      width: 380px;
      max-width: calc(100vw - 2rem);
      background: var(--white);
      border: 1px solid var(--gray-300);
      border-radius: var(--radius);
      box-shadow: var(--shadow-lg);
      display: none;
      flex-direction: column;
      max-height: 500px;
      z-index: 101;
    }

    #cart-popover.show {
      display: flex;
    }

    .cart-popover-header {
      padding: 1rem 1.25rem;
      border-bottom: 1px solid var(--gray-300);
      font-size: 1.0625rem;
      font-weight: 600;
      color: var(--dark);
      background: linear-gradient(to right, #f8f9fa, #ffffff);
    }

    .cart-popover-body {
      flex: 1;
      overflow-y: auto;
      padding: 1rem;
    }

    #cart-empty {
      text-align: center;
      padding: 2.5rem 1.5rem;
      color: var(--gray-500);
      font-size: 0.9375rem;
    }

    .cart-popover-item {
      display: flex;
      gap: 0.875rem;
      padding: 0.875rem;
      border-radius: var(--radius);
      background: var(--gray-100);
      margin-bottom: 0.625rem;
      position: relative;
      transition: var(--transition);
    }

    .cart-popover-item:hover {
      background: var(--gray-300);
    }

    .cart-popover-item-img {
      width: 56px;
      height: 56px;
      object-fit: cover;
      border-radius: 6px;
      border: 1px solid var(--gray-300);
      flex-shrink: 0;
    }

    .cart-popover-item-info {
      flex: 1;
      min-width: 0;
      padding-right: 28px;
    }

    .cart-popover-item-name {
      font-weight: 600;
      font-size: 0.9375rem;
      color: var(--dark);
      margin-bottom: 0.25rem;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .cart-popover-item-price {
      font-size: 0.8125rem;
      color: var(--gray-500);
    }

    .cart-popover-item-total {
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--success);
      margin-top: 0.25rem;
    }

    .cart-popover-item-remove {
      position: absolute;
      top: 0.625rem;
      right: 0.625rem;
      width: 24px;
      height: 24px;
      border: none;
      background: transparent;
      color: var(--danger);
      border-radius: 4px;
      cursor: pointer;
      font-size: 1.125rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: var(--transition);
    }

    .cart-popover-item-remove:hover {
      background: rgba(192, 57, 43, 0.1);
    }

    .cart-popover-footer {
      padding: 1rem 1.25rem;
      border-top: 1px solid var(--gray-300);
    }

    .cart-popover-total {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.875rem;
      font-size: 1.0625rem;
      font-weight: 700;
      color: var(--dark);
    }

    .cart-popover-actions {
      display: flex;
      gap: 0.625rem;
    }

    .cart-popover-btn {
      flex: 1;
      padding: 0.75rem;
      border: none;
      border-radius: var(--radius);
      font-weight: 600;
      font-size: 0.875rem;
      cursor: pointer;
      transition: var(--transition);
      text-decoration: none;
      text-align: center;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .cart-popover-btn.secondary {
      background: var(--gray-100);
      color: var(--gray-700);
      border: 1px solid var(--gray-300);
    }

    .cart-popover-btn.secondary:hover {
      background: var(--gray-300);
    }

    .cart-popover-btn.primary {
      background: var(--success);
      color: var(--white);
    }

    .cart-popover-btn.primary:hover {
      background: #229954;
    }

    /* ===================================== */
    /* CONTENIDO PRINCIPAL */
    /* ===================================== */
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem 1.5rem;
    }

    h1 {
      font-size: 2rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 0.5rem;
      letter-spacing: -0.02em;
    }

    .subtitle {
      font-size: 1rem;
      color: var(--gray-500);
      margin-bottom: 2rem;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1.5rem;
    }

    @media (max-width: 640px) {
      .grid {
        grid-template-columns: 1fr;
      }
    }

    .card {
      border: 1px solid var(--gray-300);
      border-radius: var(--radius);
      overflow: hidden;
      background: var(--white);
      box-shadow: var(--shadow-sm);
      transition: var(--transition);
    }

    .card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-md);
    }

    .imgbox {
      position: relative;
      width: 100%;
      height: 200px;
      overflow: hidden;
      background: var(--gray-100);
    }

    .sale-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .badges-row {
      display: flex;
      gap: 0.5rem;
      padding: 0.875rem;
      flex-wrap: wrap;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      padding: 0.375rem 0.75rem;
      border-radius: 6px;
      font-size: 0.8125rem;
      font-weight: 600;
    }

    .chip {
      display: inline-flex;
      padding: 0.375rem 0.75rem;
      border-radius: 6px;
      font-size: 0.8125rem;
      font-weight: 600;
    }

    .chip-red {
      background: #fee2e2;
      color: #991b1b;
    }

    .chip-orange {
      background: #fed7aa;
      color: #9a3412;
    }

    .chip-blue {
      background: #dbeafe;
      color: #1e40af;
    }

    .card h3 {
      font-size: 1.125rem;
      font-weight: 600;
      margin: 0 0.875rem 0.5rem 0.875rem;
      color: var(--dark);
      line-height: 1.4;
    }

    .card p {
      margin: 0 0.875rem 0.875rem 0.875rem;
      font-size: 0.875rem;
      color: var(--gray-500);
      line-height: 1.5;
    }

    .actions {
      padding: 0.875rem;
      display: flex;
      gap: 0.625rem;
    }

    .btn-primary {
      flex: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.75rem 1.25rem;
      background: var(--success);
      color: var(--white);
      border: none;
      border-radius: var(--radius);
      font-weight: 600;
      font-size: 0.9375rem;
      text-decoration: none;
      cursor: pointer;
      transition: var(--transition);
    }

    .btn-primary:hover {
      background: #229954;
    }

    .btn-disabled {
      flex: 1;
      padding: 0.75rem 1.25rem;
      background: var(--gray-300);
      color: var(--gray-500);
      border-radius: var(--radius);
      font-weight: 500;
      font-size: 0.9375rem;
      text-align: center;
      cursor: not-allowed;
      opacity: 0.7;
    }

    /* ===================================== */
    /* FOOTER */
    /* ===================================== */
    .site-footer {
      background: var(--primary);
      color: var(--white);
      padding: 2rem 1.5rem;
      margin-top: 4rem;
    }

    .site-footer .inner {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .footer-links {
      display: flex;
      gap: 1.5rem;
      flex-wrap: wrap;
    }

    .footer-links a {
      color: rgba(255, 255, 255, 0.8);
      text-decoration: none;
      font-size: 0.875rem;
      transition: var(--transition);
    }

    .footer-links a:hover {
      color: var(--white);
    }

    /* ===================================== */
    /* RESPONSIVE */
    /* ===================================== */
    @media (max-width: 768px) {
      .header {
        padding: 0.875rem 1rem;
      }

      .logo {
        font-size: 1.25rem;
      }

      h1 {
        font-size: 1.625rem;
      }

      #cart-popover {
        right: 1rem;
        left: 1rem;
        width: auto;
      }

      .site-footer .inner {
        flex-direction: column;
        text-align: center;
      }
    }
  </style>
</head>
<body>

<header class="header">
  <div class="logo">
    <i class="fas fa-shopping-bag"></i>
    <?php echo APP_NAME; ?>
  </div>
  
  <div class="header-nav">
    <button id="cartButton" class="btn-icon" title="Carrito" aria-label="Ver carrito">
      <i class="fas fa-shopping-cart"></i>
      <span id="cartBadge" class="cart-badge" style="display:none">0</span>
    </button>
    
    <button id="menuButton" class="btn-icon" title="Menú" aria-label="Abrir menú">
      <i class="fas fa-bars"></i>
    </button>
  </div>
  
  <!-- Popover del carrito -->
  <div id="cart-popover">
    <div class="cart-popover-header">
      <i class="fas fa-shopping-cart"></i> Tu Carrito
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
        <a href="checkout.php" id="checkoutBtn" class="cart-popover-btn primary">
          Pagar
        </a>
      </div>
    </div>
  </div>
</header>

<!-- Overlay del menú -->
<div id="menu-overlay"></div>

<!-- Menú hamburguesa -->
<aside id="hamburger-menu">
  <button class="menu-close" id="menu-close" aria-label="Cerrar menú">
    <i class="fas fa-times"></i>
  </button>
  
  <div class="menu-header">
    <?php if ($isLoggedIn): ?>
      <div class="menu-user">
        <div class="menu-user-avatar">
          <?php echo strtoupper(substr($userName, 0, 1)); ?>
        </div>
        <div class="menu-user-info">
          <h3><?php echo htmlspecialchars($userName); ?></h3>
          <p>Bienvenido de nuevo</p>
        </div>
      </div>
    <?php else: ?>
      <div class="menu-user">
        <div class="menu-user-avatar">
          <i class="fas fa-user"></i>
        </div>
        <div class="menu-user-info">
          <h3>Hola, Invitado</h3>
          <p>Inicia sesión para más opciones</p>
        </div>
      </div>
    <?php endif; ?>
  </div>
  
  <div class="menu-body">
    <?php if ($isLoggedIn): ?>
      <a href="my_orders.php" class="menu-item">
        <i class="fas fa-box"></i>
        <span>Mis Órdenes</span>
      </a>
      <a href="cart.php" class="menu-item">
        <i class="fas fa-shopping-cart"></i>
        <span>Mi Carrito</span>
      </a>
      <div class="menu-divider"></div>
    <?php else: ?>
      <a href="login.php" class="menu-item primary">
        <i class="fas fa-sign-in-alt"></i>
        <span>Iniciar Sesión</span>
      </a>
      <div class="menu-divider"></div>
    <?php endif; ?>
    
    <a href="affiliate/register.php" class="menu-item">
      <i class="fas fa-bullhorn"></i>
      <span>Publicar mi venta</span>
    </a>
    
    <a href="affiliate/login.php" class="menu-item">
      <i class="fas fa-user-tie"></i>
      <span>Portal Afiliados</span>
    </a>
    
    <a href="admin/login.php" class="menu-item">
      <i class="fas fa-user-shield"></i>
      <span>Administrador</span>
    </a>
    
    <?php if ($isLoggedIn): ?>
      <div class="menu-divider"></div>
      <a href="logout.php" class="menu-item danger">
        <i class="fas fa-sign-out-alt"></i>
        <span>Cerrar Sesión</span>
      </a>
    <?php endif; ?>
  </div>
</aside>

<div class="container">
  <h1>Espacios de venta</h1>
  <p class="subtitle">Descubre ventas de garaje activas y próximas cerca de ti</p>

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
        $color = '#27ae60';
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
            <a class="btn-primary" href="store.php?sale_id=<?php echo (int)$s['id']; ?>">Entrar</a>
          <?php elseif ($state === 'Próxima'): ?>
            <span class="btn-disabled">Aún no inicia</span>
          <?php else: ?>
            <span class="btn-disabled">Finalizada</span>
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
      <a href="affiliate/login.php">Afiliados</a>
      <a href="admin/login.php">Administrador</a>
    </div>
  </div>
</footer>

<script>
// ============= MENÚ HAMBURGUESA =============
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

if (menuButton) {
  menuButton.addEventListener('click', openMenu);
}

if (menuClose) {
  menuClose.addEventListener('click', closeMenu);
}

if (menuOverlay) {
  menuOverlay.addEventListener('click', closeMenu);
}

// Cerrar con ESC
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && hamburgerMenu.classList.contains('show')) {
    closeMenu();
  }
});

// ============= CARRUSEL DE IMÁGENES =============
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

// ============= CARRITO =============
const API = '/api/cart.php';

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
  const checkoutBtn = document.getElementById('checkoutBtn');
  
  if (!data || !data.ok || !data.groups || data.groups.length === 0) {
    cartBadge.textContent = '0';
    cartBadge.style.display = 'none';
    cartEmpty.style.display = 'block';
    cartItemsContainer.innerHTML = '';
    cartTotal.textContent = '₡0';
    if (checkoutBtn) {
      checkoutBtn.href = 'cart.php';
      checkoutBtn.textContent = 'Pagar';
    }
    return;
  }
  
  const groupedItems = groupCartItems(data.groups);
  
  let totalCount = 0;
  let totalAmount = 0;
  let mainCurrency = 'CRC';
  
  groupedItems.forEach(item => {
    totalCount += item.qty;
    totalAmount += item.line_total;
    mainCurrency = item.currency || 'CRC';
  });
  
  cartBadge.textContent = totalCount;
  cartBadge.style.display = totalCount > 0 ? 'inline-block' : 'none';
  cartEmpty.style.display = totalCount === 0 ? 'block' : 'none';
  
  if (checkoutBtn) {
    if (data.groups.length === 1) {
      checkoutBtn.href = `checkout.php?sale_id=${data.groups[0].sale_id}`;
      checkoutBtn.innerHTML = '<i class="fas fa-credit-card"></i> Pagar';
    } else {
      checkoutBtn.href = 'cart.php';
      checkoutBtn.innerHTML = '<i class="fas fa-shopping-cart"></i> Ver carrito';
    }
  }
  
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

// Toggle popover carrito
const cartBtn = document.getElementById('cartButton');
const cartPopover = document.getElementById('cart-popover');

if (cartBtn && cartPopover) {
  cartBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    const isOpen = cartPopover.classList.contains('show');
    
    if (!isOpen) {
      loadCart();
    }
    
    cartPopover.classList.toggle('show');
  });
  
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
      loadCart();
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
