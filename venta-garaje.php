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
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
} else {
    // Fallback a /tmp si no se puede escribir en sessions
    ini_set('session.save_path', '/tmp');
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
  ORDER BY s.start_at ASC
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
  <title>Venta de Garaje — <?php echo APP_NAME; ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Soporte de emojis -->
  <link href="https://fonts.googleapis.com/css2?family=Noto+Color+Emoji&display=swap" rel="stylesheet">
  
  <style>
    :root {
      /* Colores Costa Rica */
      --cr-azul: #002b7f;
      --cr-azul-claro: #0041b8;
      --cr-rojo: #ce1126;
      --cr-rojo-claro: #e63946;
      --cr-blanco: #ffffff;
      --cr-gris: #f8f9fa;
      
      /* Colores de diseño */
      --dark: #1a1a1a;
      --gray-700: #4a5568;
      --gray-500: #718096;
      --gray-300: #e2e8f0;
      --gray-100: #f7fafc;
      --success: #27ae60;

      /* Gradientes con colores de CR */
      --gradient-cr: linear-gradient(135deg, var(--cr-azul) 0%, var(--cr-rojo) 100%);
      --gradient-azul: linear-gradient(135deg, #002b7f 0%, #0041b8 100%);
      --gradient-rojo: linear-gradient(135deg, #ce1126 0%, #e63946 100%);

      --shadow-sm: 0 1px 3px 0 rgba(0, 43, 127, 0.1);
      --shadow-md: 0 4px 6px -1px rgba(0, 43, 127, 0.15), 0 2px 4px -1px rgba(0, 43, 127, 0.1);
      --shadow-lg: 0 10px 15px -3px rgba(0, 43, 127, 0.2), 0 4px 6px -2px rgba(0, 43, 127, 0.1);
      --shadow-xl: 0 20px 25px -5px rgba(0, 43, 127, 0.25), 0 10px 10px -5px rgba(0, 43, 127, 0.1);
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      --radius: 16px;
      --radius-sm: 8px;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: var(--cr-gris);
      color: var(--dark);
      line-height: 1.6;
      overflow-x: hidden;
    }

    /* Clase para emojis */
    .emoji {
      font-family: "Noto Color Emoji", "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", sans-serif;
      font-style: normal;
      font-weight: normal;
      line-height: 1;
    }

    /* HEADER */
    .header {
      background: var(--cr-blanco);
      border-bottom: 3px solid var(--cr-azul);
      padding: 1.25rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 4px 12px rgba(0, 43, 127, 0.1);
    }

    .logo {
      font-size: 1.75rem;
      font-weight: 800;
      color: var(--cr-azul);
      display: flex;
      align-items: center;
      gap: 0.75rem;
      letter-spacing: -0.03em;
      text-decoration: none;
    }

    .logo .flag {
      font-size: 2.5rem;
      filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
      line-height: 1;
    }

    .logo .text {
      display: flex;
      flex-direction: column;
      line-height: 1.2;
    }

    .logo .text .main {
      color: var(--cr-azul);
      font-size: 1.5rem;
    }

    .logo .text .sub {
      color: var(--cr-rojo);
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 0.05em;
      text-transform: uppercase;
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
      width: 44px;
      height: 44px;
      border-radius: 12px;
      border: 2px solid var(--cr-azul);
      background: var(--cr-blanco);
      color: var(--cr-azul);
      cursor: pointer;
      transition: var(--transition);
      position: relative;
      font-size: 1.125rem;
    }

    .btn-icon:hover {
      background: var(--cr-azul);
      color: var(--cr-blanco);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 43, 127, 0.3);
    }

    .cart-badge {
      position: absolute;
      top: -6px;
      right: -6px;
      background: var(--cr-rojo);
      color: var(--cr-blanco);
      border-radius: 999px;
      padding: 2px 7px;
      font-size: 0.7rem;
      font-weight: 700;
      min-width: 20px;
      text-align: center;
      box-shadow: 0 2px 8px rgba(206, 17, 38, 0.4);
    }

    /* MENÚ HAMBURGUESA */
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
      background: var(--cr-blanco);
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
      background: var(--cr-azul);
      color: var(--cr-blanco);
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
      color: var(--cr-azul);
    }

    .menu-item i {
      width: 20px;
      text-align: center;
      font-size: 1.0625rem;
      color: var(--gray-500);
    }

    .menu-item:hover i {
      color: var(--cr-azul);
    }

    .menu-divider {
      height: 1px;
      background: var(--gray-300);
      margin: 0.5rem 0;
    }

    .menu-item.primary {
      color: var(--cr-azul);
      font-weight: 600;
    }

    .menu-item.primary i {
      color: var(--cr-azul);
    }

    .menu-item.danger {
      color: var(--cr-rojo);
    }

    .menu-item.danger i {
      color: var(--cr-rojo);
    }

    /* POPOVER DEL CARRITO */
    #cart-popover {
      position: absolute;
      top: calc(100% + 12px);
      right: 0;
      width: 380px;
      max-width: calc(100vw - 2rem);
      background: var(--cr-blanco);
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
      color: var(--cr-rojo);
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
      background: rgba(206, 17, 38, 0.1);
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
      color: var(--cr-blanco);
    }

    .cart-popover-btn.primary:hover {
      background: #229954;
    }

    /* CONTENIDO PRINCIPAL */
    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 3rem 2rem;
    }

    .page-header {
      text-align: center;
      margin-bottom: 4rem;
    }

    h1 {
      font-family: 'Playfair Display', serif;
      font-size: 3rem;
      font-weight: 900;
      color: var(--cr-azul);
      margin-bottom: 1rem;
      line-height: 1.2;
    }

    .subtitle {
      font-size: 1.2rem;
      color: var(--gray-700);
      max-width: 700px;
      margin: 0 auto;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 2rem;
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
      background: var(--cr-blanco);
      box-shadow: var(--shadow-sm);
      transition: var(--transition);
    }

    .card:hover {
      transform: translateY(-8px);
      box-shadow: var(--shadow-xl);
      border-color: var(--cr-azul);
    }

    .imgbox {
      position: relative;
      width: 100%;
      height: 220px;
      overflow: hidden;
      background: var(--gray-100);
    }

    .sale-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.3s ease;
    }

    .card:hover .sale-img {
      transform: scale(1.05);
    }

    .badges-row {
      display: flex;
      gap: 0.5rem;
      padding: 1rem;
      flex-wrap: wrap;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      padding: 0.5rem 1rem;
      border-radius: var(--radius-sm);
      font-size: 0.875rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .chip {
      display: inline-flex;
      padding: 0.5rem 1rem;
      border-radius: var(--radius-sm);
      font-size: 0.875rem;
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
      font-size: 1.25rem;
      font-weight: 700;
      margin: 0 1rem 0.75rem 1rem;
      color: var(--dark);
      line-height: 1.4;
    }

    .card p {
      margin: 0 1rem 0.75rem 1rem;
      font-size: 0.9375rem;
      color: var(--gray-500);
      line-height: 1.8;
    }

    .card p i {
      color: var(--cr-azul);
      margin-right: 0.35rem;
      font-size: 0.875rem;
    }

    /* Sale Dates */
    .sale-dates {
      margin: 0 1rem 1rem 1rem;
      padding: 1rem;
      background: var(--gray-100);
      border-radius: var(--radius-sm);
      font-size: 0.875rem;
    }

    .date-row {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 0.5rem;
      color: var(--gray-700);
    }

    .date-row:last-of-type {
      margin-bottom: 0;
    }

    .date-row i {
      color: var(--cr-azul);
      font-size: 0.875rem;
      width: 16px;
      text-align: center;
    }

    .date-label {
      font-weight: 600;
      min-width: 50px;
    }

    .date-value {
      font-family: 'Courier New', monospace;
      font-weight: 500;
      color: var(--dark);
    }

    /* Countdown Box */
    .countdown-box {
      margin-top: 0.75rem;
      padding: 0.75rem;
      border-radius: var(--radius-sm);
      text-align: center;
      font-weight: 600;
    }

    .countdown-box.live {
      background: linear-gradient(135deg, rgba(39, 174, 96, 0.1), rgba(39, 174, 96, 0.05));
      border: 2px solid rgba(39, 174, 96, 0.3);
      color: #27ae60;
    }

    .countdown-box.upcoming {
      background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(37, 99, 235, 0.05));
      border: 2px solid rgba(37, 99, 235, 0.3);
      color: #2563eb;
    }

    .countdown-box i {
      margin-right: 0.35rem;
      animation: pulse-icon 2s ease-in-out infinite;
    }

    @keyframes pulse-icon {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }

    .countdown-timer {
      display: block;
      font-size: 1.1rem;
      font-weight: 700;
      margin-top: 0.35rem;
      font-family: 'Courier New', monospace;
      letter-spacing: 0.5px;
    }

    .actions {
      padding: 1rem;
      display: flex;
      gap: 0.75rem;
    }

    .btn-primary {
      flex: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.875rem 1.5rem;
      background: linear-gradient(135deg, var(--success), #229954);
      color: var(--cr-blanco);
      border: none;
      border-radius: var(--radius-sm);
      font-weight: 700;
      font-size: 1rem;
      text-decoration: none;
      cursor: pointer;
      transition: var(--transition);
      box-shadow: var(--shadow-sm);
    }

    .btn-primary:hover {
      background: linear-gradient(135deg, #229954, var(--success));
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .btn-disabled {
      flex: 1;
      padding: 0.875rem 1.5rem;
      background: var(--gray-300);
      color: var(--gray-500);
      border-radius: var(--radius-sm);
      font-weight: 600;
      font-size: 1rem;
      text-align: center;
      cursor: not-allowed;
      opacity: 0.7;
    }

    /* FOOTER */
    .site-footer {
      background: var(--cr-azul);
      color: var(--cr-blanco);
      padding: 4rem 2rem 2rem;
      margin-top: 5rem;
      border-top: 5px solid var(--cr-rojo);
    }

    .footer-content {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 3rem;
      margin-bottom: 3rem;
    }

    .footer-section h3 {
      font-size: 1.25rem;
      margin-bottom: 1.5rem;
      font-weight: 700;
      color: var(--cr-blanco);
    }

    .footer-section-flag {
      font-size: 1.5rem;
      margin-right: 0.5rem;
      line-height: 1;
    }

    .footer-section p,
    .footer-section a {
      color: rgba(255, 255, 255, 0.8);
      text-decoration: none;
      display: block;
      margin-bottom: 0.75rem;
      transition: var(--transition);
    }

    .footer-section a:hover {
      color: var(--cr-blanco);
      padding-left: 5px;
      text-decoration: underline;
    }

    .footer-bottom {
      border-top: 2px solid rgba(255, 255, 255, 0.2);
      padding-top: 2rem;
      text-align: center;
      color: rgba(255, 255, 255, 0.9);
      font-size: 1rem;
    }

    .footer-heart {
      color: var(--cr-rojo);
      font-size: 1.2rem;
      margin: 0 0.3rem;
      line-height: 1;
    }

    .footer-flag {
      font-size: 1.5rem;
      display: inline-block;
      animation: pulse 2s ease-in-out infinite;
      line-height: 1;
      margin-left: 0.5rem;
    }

    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.1); }
    }

    /* RESPONSIVE */
    @media (max-width: 768px) {
      .header {
        padding: 0.875rem 1rem;
      }

      .logo {
        font-size: 1.25rem;
      }

      .logo .text .main {
        font-size: 1.2rem;
      }

      .logo .text .sub {
        font-size: 0.65rem;
      }

      h1 {
        font-size: 2rem;
      }

      .container {
        padding: 2rem 1.25rem;
      }

      #cart-popover {
        right: 1rem;
        left: 1rem;
        width: auto;
      }

      .site-footer {
        padding: 3rem 1.5rem 1.5rem;
      }

      .footer-content {
        grid-template-columns: 1fr;
        gap: 2rem;
        text-align: center;
      }
    }
  </style>
</head>
<body>

<header class="header">
  <a href="index.php" class="logo">
    <span class="flag emoji">🇨🇷</span>
    <div class="text">
      <span class="main">CompraTica</span>
      <span class="sub">100% COSTARRICENSE</span>
    </div>
  </a>
  
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
    
    <a href="index.php" class="menu-item">
      <i class="fas fa-home"></i>
      <span>Inicio</span>
    </a>
    
    <a href="servicios.php" class="menu-item">
      <i class="fas fa-briefcase"></i>
      <span>Servicios</span>
    </a>
    
    <a href="emprendedores.php" class="menu-item">
      <i class="fas fa-rocket"></i>
      <span>Emprendedores</span>
    </a>
    
    <a href="emprendedoras.php" class="menu-item">
      <i class="fas fa-crown"></i>
      <span>Emprendedoras</span>
    </a>
    
    <div class="menu-divider"></div>
    
    <a href="/affiliate/register.php" class="menu-item">
      <i class="fas fa-bullhorn"></i>
      <span>Publicar mi venta</span>
    </a>
    
    <a href="/affiliate/login.php" class="menu-item">
      <i class="fas fa-user-tie"></i>
      <span>Portal Afiliados</span>
    </a>
    
    <a href="/admin/login.php" class="menu-item">
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
  <div class="page-header">
    <h1>Espacios de Venta de Garaje</h1>
    <p class="subtitle">Descubrí ventas de garaje activas y próximas cerca de ti. Tesoros únicos a precios increíbles</p>
  </div>

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
          <strong><?php echo htmlspecialchars($s['affiliate_name']); ?></strong>
        </p>
        
        <div class="sale-dates" 
             data-start="<?php echo $st; ?>" 
             data-end="<?php echo $en; ?>"
             data-state="<?php echo $state; ?>">
          <div class="date-row">
            <i class="fas fa-play-circle"></i>
            <span class="date-label">Inicio:</span>
            <span class="date-value"><?php echo date('d/m/Y H:i:s', $st); ?></span>
          </div>
          <div class="date-row">
            <i class="fas fa-stop-circle"></i>
            <span class="date-label">Fin:</span>
            <span class="date-value"><?php echo date('d/m/Y H:i:s', $en); ?></span>
          </div>
          
          <?php if ($state === 'En vivo'): ?>
            <div class="countdown-box live">
              <i class="fas fa-hourglass-half"></i>
              <strong>Termina en:</strong>
              <span class="countdown-timer" data-target="<?php echo $en; ?>">Calculando...</span>
            </div>
          <?php elseif ($state === 'Próxima'): ?>
            <div class="countdown-box upcoming">
              <i class="fas fa-clock"></i>
              <strong>Inicia en:</strong>
              <span class="countdown-timer" data-target="<?php echo $st; ?>">Calculando...</span>
            </div>
          <?php endif; ?>
        </div>

        <div class="actions">
          <?php if ($state === 'En vivo'): ?>
            <a class="btn-primary" href="store.php?sale_id=<?php echo (int)$s['id']; ?>">
              <i class="fas fa-shopping-bag"></i> Entrar a la Venta
            </a>
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
        <div style="padding: 3rem 2rem; text-align: center;">
          <i class="fas fa-box-open" style="font-size: 3rem; color: var(--gray-300); margin-bottom: 1rem;"></i>
          <h3 style="margin: 0 0 0.5rem 0;">No hay espacios activos</h3>
          <p style="margin: 0; color: var(--gray-500);">Volvé pronto para descubrir nuevas ventas de garaje.</p>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<footer class="site-footer">
  <div class="footer-content">
    <div class="footer-section">
      <h3><span class="footer-section-flag emoji">🇨🇷</span> CompraTica</h3>
      <p>El marketplace costarricense que une a emprendedores ticos con compradores nacionales. Hecho en Costa Rica, con orgullo y dedicación.</p>
    </div>
    <div class="footer-section">
      <h3>Enlaces Rápidos</h3>
      <a href="index.php">Inicio</a>
      <a href="servicios.php">Servicios</a>
      <a href="emprendedores.php">Emprendedores</a>
      <a href="emprendedoras.php">Emprendedoras</a>
    </div>
    <div class="footer-section">
      <h3>Para Emprendedores</h3>
      <a href="/affiliate/register.php">Publicar mi venta</a>
      <a href="/affiliate/login.php">Portal de Afiliados</a>
      <a href="/admin/login.php">Administración</a>
    </div>
    <div class="footer-section">
      <h3>Contacto</h3>
      <a href="mailto:<?php echo defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'info@compratica.com'; ?>">
        <i class="fas fa-envelope"></i> Enviar Email
      </a>
      <a href="tel:+50622222222">
        <i class="fas fa-phone"></i> +506 2222-2222
      </a>
    </div>
  </div>
  <div class="footer-bottom">
    <p>
      © <?php echo date('Y'); ?> CompraTica — Hecho con <span class="footer-heart emoji">❤️</span> en Costa Rica
      <span class="footer-flag emoji">🇨🇷</span>
    </p>
    <p style="margin-top: 0.5rem; font-size: 0.9rem; opacity: 0.8;">
      Apoyando el talento costarricense desde el corazón de Centroamérica
    </p>
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

// ============= COUNTDOWN EN TIEMPO REAL =============
function updateCountdowns() {
  const timers = document.querySelectorAll('.countdown-timer');
  const now = Math.floor(Date.now() / 1000); // Timestamp actual en segundos
  
  timers.forEach(timer => {
    const targetTimestamp = parseInt(timer.dataset.target);
    const diff = targetTimestamp - now;
    
    if (diff <= 0) {
      timer.textContent = 'Tiempo agotado';
      timer.style.color = '#6b7280';
      return;
    }
    
    // Calcular días, horas, minutos y segundos
    const days = Math.floor(diff / 86400);
    const hours = Math.floor((diff % 86400) / 3600);
    const minutes = Math.floor((diff % 3600) / 60);
    const seconds = diff % 60;
    
    // Formatear con ceros a la izquierda
    const format = (num) => String(num).padStart(2, '0');
    
    // Construir texto del countdown
    let countdownText = '';
    
    if (days > 0) {
      countdownText = `${days}d ${format(hours)}:${format(minutes)}:${format(seconds)}`;
    } else if (hours > 0) {
      countdownText = `${format(hours)}:${format(minutes)}:${format(seconds)}`;
    } else {
      countdownText = `${format(minutes)}:${format(seconds)}`;
    }
    
    timer.textContent = countdownText;
  });
}

// Actualizar cada segundo
updateCountdowns();
setInterval(updateCountdowns, 1000);
</script>

</body>
</html>