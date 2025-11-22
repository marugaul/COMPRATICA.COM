<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

// ============= LOG DE DEBUG =============
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$logFile = $logDir . '/emprendedoras_debug.log';

function logDebug($msg, $data = null) {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($data) $line .= ' | ' . json_encode($data);
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

logDebug("EMPRENDEDORAS_START", ['uri' => $_SERVER['REQUEST_URI']]);

// ============= CONFIGURACIÓN DE SESIONES =============
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

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMESITE');
}
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

logDebug("RENDERING_PAGE");
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Emprendedoras — <?php echo APP_NAME; ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Soporte de emojis -->
  <link href="https://fonts.googleapis.com/css2?family=Noto+Color+Emoji&display=swap" rel="stylesheet">
  
  <style>
    :root {
      /* Paleta Femenina y Cálida */
      --primary-rose: #ff6b9d;
      --primary-rose-light: #ffb4d1;
      --primary-rose-dark: #e5507d;
      
      --secondary-peach: #ffc4a3;
      --secondary-lavender: #d4a5f9;
      --secondary-mint: #a8e6cf;
      
      --accent-gold: #ffd700;
      --accent-coral: #ff8a80;
      
      /* Neutrales Suaves */
      --white: #ffffff;
      --cream: #fef9f3;
      --beige-light: #f9f5f0;
      --beige: #f0ebe5;
      --gray-soft: #e8e3dd;
      --gray-medium: #b8afa5;
      --text-dark: #4a4238;
      --text-medium: #6b6157;
      
      /* CR Colors */
      --cr-azul: #002b7f;
      --cr-rojo: #ce1126;
      
      /* Sombras Suaves */
      --shadow-sm: 0 2px 8px rgba(255, 107, 157, 0.1);
      --shadow-md: 0 4px 16px rgba(255, 107, 157, 0.15);
      --shadow-lg: 0 8px 24px rgba(255, 107, 157, 0.2);
      
      --radius-sm: 12px;
      --radius-md: 20px;
      --radius-lg: 28px;
      --radius-xl: 40px;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: linear-gradient(135deg, var(--cream) 0%, var(--beige-light) 100%);
      color: var(--text-dark);
      min-height: 100vh;
      line-height: 1.6;
    }

    a {
      text-decoration: none;
      color: inherit;
      transition: all 0.3s ease;
    }

    /* Clase para emojis */
    .emoji {
      font-family: "Noto Color Emoji", "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", sans-serif;
      font-style: normal;
      font-weight: normal;
      line-height: 1;
    }

    /* Header Renovado */
    .header {
      background: var(--white);
      padding: 1.25rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      text-decoration: none;
    }

    .logo .flag {
      font-size: 2rem;
      filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
      line-height: 1;
    }

    .logo .text .main {
      color: var(--cr-azul);
      font-weight: 800;
      font-size: 1.5rem;
      letter-spacing: -0.02em;
    }

    .logo .text .sub {
      color: var(--primary-rose);
      font-size: 0.7rem;
      font-weight: 600;
      letter-spacing: 0.15em;
      text-transform: uppercase;
    }

    .header-nav {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .btn-icon {
      position: relative;
      width: 2.75rem;
      height: 2.75rem;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary-rose-light), var(--primary-rose));
      color: var(--white);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s ease;
      border: none;
      box-shadow: var(--shadow-sm);
      font-size: 1.1rem;
    }

    .btn-icon:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .cart-badge {
      position: absolute;
      top: -4px;
      right: -4px;
      min-width: 20px;
      height: 20px;
      border-radius: 10px;
      background: var(--accent-coral);
      color: var(--white);
      font-size: 0.7rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 8px rgba(255, 138, 128, 0.4);
    }

    /* Main Content */
    .main-wrapper {
      max-width: 1280px;
      margin: 0 auto;
      padding: 2.5rem 2rem;
    }

    /* Breadcrumb Simple */
    .breadcrumb {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 2rem;
      font-size: 0.9rem;
      color: var(--text-medium);
    }

    .breadcrumb a {
      color: var(--primary-rose);
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }

    .breadcrumb a:hover {
      color: var(--primary-rose-dark);
    }

    .breadcrumb i {
      font-size: 0.75rem;
    }

    /* Hero Section Simplificada */
    .hero-section {
      background: linear-gradient(135deg, 
        rgba(255, 107, 157, 0.08) 0%, 
        rgba(255, 196, 163, 0.08) 100%);
      border-radius: var(--radius-xl);
      padding: 3rem 2.5rem;
      margin-bottom: 3rem;
      position: relative;
      overflow: hidden;
    }

    .hero-section::before {
      content: "";
      position: absolute;
      top: -50%;
      right: -10%;
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, rgba(255, 107, 157, 0.15), transparent 70%);
      border-radius: 50%;
    }

    .hero-content {
      position: relative;
      z-index: 2;
      max-width: 700px;
    }

    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: var(--white);
      padding: 0.5rem 1.25rem;
      border-radius: 50px;
      font-size: 0.85rem;
      font-weight: 500;
      color: var(--primary-rose);
      margin-bottom: 1.5rem;
      box-shadow: var(--shadow-sm);
    }

    .hero-badge i {
      font-size: 1rem;
    }

    .hero-title {
      font-family: "Playfair Display", Georgia, serif;
      font-size: clamp(2rem, 4vw, 3.25rem);
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 1rem;
      line-height: 1.15;
    }

    .hero-title .highlight {
      background: linear-gradient(135deg, var(--primary-rose), var(--secondary-peach));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .hero-description {
      font-size: 1.1rem;
      color: var(--text-medium);
      margin-bottom: 2rem;
      line-height: 1.7;
    }

    /* Tags Horizontales */
    .hero-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
    }

    .tag {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.6rem 1.25rem;
      background: var(--white);
      border-radius: 50px;
      font-size: 0.85rem;
      font-weight: 500;
      color: var(--text-medium);
      box-shadow: var(--shadow-sm);
      transition: all 0.3s ease;
    }

    .tag:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .tag i {
      color: var(--primary-rose);
      font-size: 0.9rem;
    }

    /* Info Box Lateral */
    .info-box {
      background: var(--white);
      border-radius: var(--radius-lg);
      padding: 2rem;
      box-shadow: var(--shadow-md);
      margin-bottom: 3rem;
    }

    .info-box-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
    }

    .info-box-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--text-dark);
    }

    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.4rem 1rem;
      background: linear-gradient(135deg, var(--secondary-peach), var(--primary-rose-light));
      border-radius: 50px;
      font-size: 0.8rem;
      font-weight: 500;
      color: var(--white);
    }

    .status-pill i {
      animation: spin 2s linear infinite;
    }

    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    .info-text {
      color: var(--text-medium);
      margin-bottom: 1.5rem;
      line-height: 1.7;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .stat-item {
      text-align: center;
      padding: 1rem;
      background: var(--beige-light);
      border-radius: var(--radius-sm);
    }

    .stat-number {
      font-size: 1.75rem;
      font-weight: 700;
      color: var(--primary-rose);
      display: block;
    }

    .stat-label {
      font-size: 0.8rem;
      color: var(--text-medium);
      margin-top: 0.25rem;
    }

    .info-footer {
      padding-top: 1.5rem;
      border-top: 2px dashed var(--gray-soft);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
    }

    .info-footer-text {
      font-size: 0.85rem;
      color: var(--text-medium);
    }

    .notify-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.65rem 1.5rem;
      background: linear-gradient(135deg, var(--primary-rose), var(--primary-rose-dark));
      color: var(--white);
      border-radius: 50px;
      font-size: 0.85rem;
      font-weight: 500;
      border: none;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: var(--shadow-sm);
    }

    .notify-btn:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    /* Sección de Categorías */
    .section-header {
      text-align: center;
      margin-bottom: 2.5rem;
    }

    .section-title {
      font-family: "Playfair Display", Georgia, serif;
      font-size: 2rem;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 0.75rem;
    }

    .section-subtitle {
      font-size: 1rem;
      color: var(--text-medium);
      max-width: 600px;
      margin: 0 auto;
    }

    /* Cards Grid Mejorado */
    .categories-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1.75rem;
      margin-bottom: 3rem;
    }

    .category-card {
      background: var(--white);
      border-radius: var(--radius-lg);
      padding: 2rem;
      box-shadow: var(--shadow-sm);
      transition: all 0.3s ease;
      cursor: pointer;
      position: relative;
      overflow: hidden;
    }

    .category-card::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--primary-rose), var(--secondary-peach));
      transform: scaleX(0);
      transition: transform 0.3s ease;
    }

    .category-card:hover::before {
      transform: scaleX(1);
    }

    .category-card:hover {
      transform: translateY(-8px);
      box-shadow: var(--shadow-lg);
    }

    .category-icon {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary-rose-light), var(--primary-rose));
      color: var(--white);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-bottom: 1.25rem;
      box-shadow: var(--shadow-sm);
    }

    .category-title {
      font-size: 1.2rem;
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 0.75rem;
    }

    .category-description {
      font-size: 0.95rem;
      color: var(--text-medium);
      line-height: 1.6;
      margin-bottom: 1.25rem;
    }

    .category-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.85rem;
      color: var(--text-medium);
      padding-top: 1rem;
      border-top: 1px solid var(--gray-soft);
    }

    .category-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      font-weight: 500;
    }

    .category-badge .dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--primary-rose);
    }

    .category-link {
      color: var(--primary-rose);
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
    }

    .category-link:hover {
      color: var(--primary-rose-dark);
    }

    /* Notice Box */
    .notice-box {
      background: linear-gradient(135deg, 
        rgba(255, 196, 163, 0.15), 
        rgba(212, 165, 249, 0.15));
      border: 2px dashed var(--secondary-peach);
      border-radius: var(--radius-lg);
      padding: 1.75rem 2rem;
      display: flex;
      align-items: flex-start;
      gap: 1rem;
    }

    .notice-icon {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: var(--white);
      color: var(--primary-rose);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      box-shadow: var(--shadow-sm);
    }

    .notice-text {
      color: var(--text-medium);
      line-height: 1.7;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .header {
        padding: 1rem 1.25rem;
      }

      .main-wrapper {
        padding: 1.75rem 1.25rem;
      }

      .hero-section {
        padding: 2rem 1.5rem;
      }

      .hero-title {
        font-size: 2rem;
      }

      .categories-grid {
        grid-template-columns: 1fr;
        gap: 1.25rem;
      }

      .stats-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
      }

      .info-footer {
        flex-direction: column;
        align-items: flex-start;
      }
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
      background: var(--cr-blanco, #ffffff);
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
      border-bottom: 1px solid var(--gray-300, #e2e8f0);
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
      background: var(--cr-azul, #002b7f);
      color: var(--cr-blanco, #ffffff);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1.25rem;
    }

    .menu-user-info h3 {
      font-size: 1.0625rem;
      font-weight: 600;
      color: var(--dark, #1a1a1a);
      margin-bottom: 0.125rem;
    }

    .menu-user-info p {
      font-size: 0.875rem;
      color: var(--gray-500, #718096);
    }

    .menu-close {
      position: absolute;
      top: 1.25rem;
      right: 1.25rem;
      width: 32px;
      height: 32px;
      border: none;
      background: transparent;
      color: var(--gray-500, #718096);
      font-size: 1.5rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
      transition: var(--transition, all 0.3s);
    }

    .menu-close:hover {
      background: var(--gray-100, #f7fafc);
      color: var(--dark, #1a1a1a);
    }

    .menu-body {
      padding: 1rem 0;
    }

    .menu-item {
      display: flex;
      align-items: center;
      gap: 0.875rem;
      padding: 0.875rem 1.5rem;
      color: var(--gray-700, #4a5568);
      text-decoration: none;
      transition: var(--transition, all 0.3s);
      font-size: 0.9375rem;
      font-weight: 500;
    }

    .menu-item:hover {
      background: var(--gray-100, #f7fafc);
      color: var(--cr-azul, #002b7f);
    }

    .menu-item i {
      width: 20px;
      text-align: center;
      font-size: 1.0625rem;
      color: var(--gray-500, #718096);
    }

    .menu-item:hover i {
      color: var(--cr-azul, #002b7f);
    }

    .menu-divider {
      height: 1px;
      background: var(--gray-300, #e2e8f0);
      margin: 0.5rem 0;
    }

    .menu-item.primary {
      color: var(--cr-azul, #002b7f);
      font-weight: 600;
    }

    .menu-item.primary i {
      color: var(--cr-azul, #002b7f);
    }

    .menu-item.danger {
      color: var(--cr-rojo, #ce1126);
    }

    .menu-item.danger i {
      color: var(--cr-rojo, #ce1126);
    }

    /* POPOVER DEL CARRITO */
    #cart-popover {
      position: absolute;
      top: calc(100% + 12px);
      right: 0;
      width: 380px;
      max-width: calc(100vw - 2rem);
      background: var(--cr-blanco, #ffffff);
      border: 1px solid var(--gray-300, #e2e8f0);
      border-radius: var(--radius, 16px);
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
      border-bottom: 1px solid var(--gray-300, #e2e8f0);
      font-size: 1.0625rem;
      font-weight: 600;
      color: var(--dark, #1a1a1a);
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
      color: var(--gray-500, #718096);
      font-size: 0.9375rem;
    }

    .cart-popover-item {
      display: flex;
      gap: 0.875rem;
      padding: 0.875rem;
      border-radius: var(--radius, 16px);
      background: var(--gray-100, #f7fafc);
      margin-bottom: 0.625rem;
      position: relative;
      transition: var(--transition, all 0.3s);
    }

    .cart-popover-item:hover {
      background: var(--gray-300, #e2e8f0);
    }

    .cart-popover-item-img {
      width: 56px;
      height: 56px;
      object-fit: cover;
      border-radius: 6px;
      border: 1px solid var(--gray-300, #e2e8f0);
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
      color: var(--dark, #1a1a1a);
      margin-bottom: 0.25rem;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .cart-popover-item-price {
      font-size: 0.8125rem;
      color: var(--gray-500, #718096);
    }

    .cart-popover-item-total {
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--success, #27ae60);
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
      color: var(--cr-rojo, #ce1126);
      border-radius: 4px;
      cursor: pointer;
      font-size: 1.125rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: var(--transition, all 0.3s);
    }

    .cart-popover-item-remove:hover {
      background: rgba(206, 17, 38, 0.1);
    }

    .cart-popover-footer {
      padding: 1rem 1.25rem;
      border-top: 1px solid var(--gray-300, #e2e8f0);
    }

    .cart-popover-total {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.875rem;
      font-size: 1.0625rem;
      font-weight: 700;
      color: var(--dark, #1a1a1a);
    }

    .cart-popover-actions {
      display: flex;
      gap: 0.625rem;
    }

    .cart-popover-btn {
      flex: 1;
      padding: 0.75rem;
      border: none;
      border-radius: var(--radius, 16px);
      font-weight: 600;
      font-size: 0.875rem;
      cursor: pointer;
      transition: var(--transition, all 0.3s);
      text-decoration: none;
      text-align: center;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .cart-popover-btn.secondary {
      background: var(--gray-100, #f7fafc);
      color: var(--gray-700, #4a5568);
      border: 1px solid var(--gray-300, #e2e8f0);
    }

    .cart-popover-btn.secondary:hover {
      background: var(--gray-300, #e2e8f0);
    }

    .cart-popover-btn.primary {
      background: var(--success, #27ae60);
      color: var(--cr-blanco, #ffffff);
    }

    .cart-popover-btn.primary:hover {
      background: #229954;
    }

  </style>
</head>
<body>

<header class="header">
  <a href="index.php" class="logo">
    <span class="flag emoji">🇨🇷</span>
    <div class="text">
      <span class="main">CompraTica</span>
      <span class="sub">Emprendedoras Ticas</span>
    </div>
  </a>
  <nav class="header-nav">
    <button id="cartButton" class="btn-icon" title="Carrito" aria-label="Ver carrito">
      <i class="fas fa-shopping-cart"></i>
      <span id="cartBadge" class="cart-badge" style="display:none">0</span>
    </button>
    
    <button id="menuButton" class="btn-icon" title="Menú" aria-label="Abrir menú">
      <i class="fas fa-bars"></i>
    </button>
  </nav>
  
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

    <a href="venta-garaje.php" class="menu-item">
      <i class="fas fa-tags"></i>
      <span>Venta de Garaje</span>
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


<div class="main-wrapper">
  <!-- Breadcrumb -->
  <div class="breadcrumb">
    <a href="index.php">
      <i class="fas fa-home"></i>
      Inicio
    </a>
    <i class="fas fa-chevron-right"></i>
    <span>Emprendedoras</span>
  </div>

  <!-- Hero Section -->
  <section class="hero-section">
    <div class="hero-content">
      <div class="hero-badge">
        <i class="fas fa-crown"></i>
        <span>Hecho por mujeres ticas</span>
      </div>
      
      <h1 class="hero-title">
        Emprendedoras <span class="highlight">que inspiran</span>
      </h1>
      
      <p class="hero-description">
        Apoyá a mujeres ticas que están construyendo sus propios negocios con creatividad,
        talento y mucha actitud. Muy pronto vas a poder encontrar sus productos en un solo lugar.
      </p>

      <div class="hero-tags">
        <span class="tag">
          <i class="fas fa-gem"></i>
          Diseño y estilo
        </span>
        <span class="tag">
          <i class="fas fa-spa"></i>
          Belleza y cuidado personal
        </span>
        <span class="tag">
          <i class="fas fa-cookie-bite"></i>
          Delicias artesanales
        </span>
        <span class="tag">
          <i class="fas fa-hand-holding-heart"></i>
          100% Local
        </span>
      </div>
    </div>
  </section>

  <!-- Info Box -->
  <div class="info-box">
    <div class="info-box-header">
      <h2 class="info-box-title">¿Qué viene pronto?</h2>
      <span class="status-pill">
        <i class="fas fa-circle-notch"></i>
        En construcción
      </span>
    </div>

    <p class="info-text">
      Estamos afinando los detalles del catálogo para que puedas filtrar por tipo de producto, ubicación,
      precio y más. Todo pensado para que apoyar a una emprendedora tica sea tan fácil como un par de clics.
    </p>

    <div class="stats-grid">
      <div class="stat-item">
        <span class="stat-number">24+</span>
        <span class="stat-label">Marcas registradas</span>
      </div>
      <div class="stat-item">
        <span class="stat-number">8</span>
        <span class="stat-label">Categorías</span>
      </div>
      <div class="stat-item">
        <span class="stat-number">100%</span>
        <span class="stat-label">Hecho en CR</span>
      </div>
    </div>

    <div class="info-footer">
      <span class="info-footer-text">Te avisamos cuando esté listo</span>
      <button class="notify-btn">
        <i class="fas fa-bell"></i>
        Quiero saber más
      </button>
    </div>
  </div>

  <!-- Categorías -->
  <div class="section-header">
    <h2 class="section-title">Categorías destacadas</h2>
    <p class="section-subtitle">
      Un adelanto de los productos que pronto vas a encontrar en CompraTica
    </p>
  </div>

  <div class="categories-grid">
    <article class="category-card">
      <div class="category-icon">
        <i class="fas fa-gem"></i>
      </div>
      <h3 class="category-title">Joyería & Accesorios</h3>
      <p class="category-description">
        Piezas únicas con diseño local. Producción en series limitadas con mucho amor y dedicación.
      </p>
      <div class="category-footer">
        <span class="category-badge">
          <span class="dot"></span>
          Series limitadas
        </span>
        <span class="category-link">
          Próximamente
          <i class="fas fa-arrow-right"></i>
        </span>
      </div>
    </article>

    <article class="category-card">
      <div class="category-icon">
        <i class="fas fa-spa"></i>
      </div>
      <h3 class="category-title">Belleza & Bienestar</h3>
      <p class="category-description">
        Productos de cuidado personal pensados para el día a día. Innovación con ingredientes locales.
      </p>
      <div class="category-footer">
        <span class="category-badge">
          <span class="dot"></span>
          Calidad premium
        </span>
        <span class="category-link">
          Explorar
          <i class="fas fa-arrow-right"></i>
        </span>
      </div>
    </article>

    <article class="category-card">
      <div class="category-icon">
        <i class="fas fa-palette"></i>
      </div>
      <h3 class="category-title">Arte & Decoración</h3>
      <p class="category-description">
        Ilustraciones, decoración y piezas únicas que llevan un pedacito de Costa Rica a tu hogar.
      </p>
      <div class="category-footer">
        <span class="category-badge">
          <span class="dot"></span>
          Hecho a mano
        </span>
        <span class="category-link">
          Ver más
          <i class="fas fa-arrow-right"></i>
        </span>
      </div>
    </article>

    <article class="category-card">
      <div class="category-icon">
        <i class="fas fa-cookie-bite"></i>
      </div>
      <h3 class="category-title">Sabores Locales</h3>
      <p class="category-description">
        Repostería, snacks y productos gourmet hechos con recetas tradicionales y mucho sabor tico.
      </p>
      <div class="category-footer">
        <span class="category-badge">
          <span class="dot"></span>
          Pequeños lotes
        </span>
        <span class="category-link">
          Descubrir
          <i class="fas fa-arrow-right"></i>
        </span>
      </div>
    </article>

    <article class="category-card">
      <div class="category-icon">
        <i class="fas fa-seedling"></i>
      </div>
      <h3 class="category-title">Eco & Sostenible</h3>
      <p class="category-description">
        Emprendimientos conscientes con el planeta. Materiales responsables y procesos sostenibles.
      </p>
      <div class="category-footer">
        <span class="category-badge">
          <span class="dot"></span>
          Enfoque verde
        </span>
        <span class="category-link">
          Conocer más
          <i class="fas fa-arrow-right"></i>
        </span>
      </div>
    </article>

    <article class="category-card">
      <div class="category-icon">
        <i class="fas fa-lightbulb"></i>
      </div>
      <h3 class="category-title">Ideas Creativas</h3>
      <p class="category-description">
        Productos fuera de lo común que muestran el lado más innovador del emprendimiento tico.
      </p>
      <div class="category-footer">
        <span class="category-badge">
          <span class="dot"></span>
          Innovación
        </span>
        <span class="category-link">
          Explorar
          <i class="fas fa-arrow-right"></i>
        </span>
      </div>
    </article>
  </div>

  <!-- Notice -->
  <div class="notice-box">
    <div class="notice-icon">
      <i class="fas fa-info-circle"></i>
    </div>
    <p class="notice-text">
      <strong>Esta es una vista previa del diseño.</strong> En el lanzamiento oficial vas a poder 
      filtrar, ordenar y guardar tus emprendimientos favoritos directamente en tu cuenta de CompraTica.
    </p>
  </div>
</div>

<script>
const cartBadge = document.getElementById('cartBadge');
const cantidadInicial = <?php echo $cantidadProductos; ?>;

if (cartBadge) {
  cartBadge.style.display = cantidadInicial > 0 ? 'flex' : 'none';
}

// Interacción suave para las tarjetas
document.querySelectorAll('.category-card').forEach(card => {
  card.addEventListener('click', function() {
    console.log('Categoría seleccionada:', this.querySelector('.category-title').textContent);
  });
});
</script>


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