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
  <title><?php echo APP_NAME; ?> — Marketplace de Emprendedores</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  
  <!-- Soporte de emojis para todas las plataformas -->
  <link href="https://fonts.googleapis.com/css2?family=Noto+Color+Emoji&display=swap" rel="stylesheet">
  
  <style>
    :root {
      /* Colores de la Bandera de Costa Rica */
      --cr-azul: #002b7f;
      --cr-azul-claro: #0041b8;
      --cr-rojo: #ce1126;
      --cr-rojo-claro: #e63946;
      --cr-blanco: #ffffff;
      --cr-gris: #f8f9fa;
      --dark: #1a1a1a;
      --gray-700: #4a5568;
      --gray-500: #718096;
      --gray-300: #e2e8f0;
      --gray-100: #f7fafc;

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

    /* Clase para emojis - Asegura visibilidad en todas las plataformas */
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
      text-decoration: none;
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

    /* HERO */
    .hero {
      background: var(--gradient-cr);
      padding: 6rem 2rem;
      text-align: center;
      color: var(--cr-blanco);
      position: relative;
      overflow: hidden;
    }

    .hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background:
        linear-gradient(180deg,
          var(--cr-azul) 0%,
          var(--cr-azul) 23%,
          var(--cr-blanco) 23%,
          var(--cr-blanco) 30%,
          var(--cr-rojo) 30%,
          var(--cr-rojo) 70%,
          var(--cr-blanco) 70%,
          var(--cr-blanco) 77%,
          var(--cr-azul) 77%,
          var(--cr-azul) 100%
        );
      opacity: 0.15;
      z-index: 0;
    }

    .hero::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-image:
        radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
      z-index: 0;
    }

    .hero-content {
      max-width: 900px;
      margin: 0 auto;
      position: relative;
      z-index: 1;
    }

    .hero-badge {
      display: inline-block;
      background: rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(10px);
      padding: 0.5rem 1.5rem;
      border-radius: 50px;
      font-size: 0.9rem;
      font-weight: 600;
      margin-bottom: 2rem;
      border: 2px solid rgba(255, 255, 255, 0.3);
    }

    .hero-badge .flag-emoji {
      font-size: 1.2rem;
      margin-right: 0.5rem;
      line-height: 1;
    }

    .hero h1 {
      font-family: 'Playfair Display', serif;
      font-size: 3.5rem;
      font-weight: 900;
      margin-bottom: 1.5rem;
      line-height: 1.2;
      text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }

    .hero h1 .cr-flag {
      display: inline-block;
      font-size: 3rem;
      vertical-align: middle;
      margin-left: 0.5rem;
      animation: wave 2s ease-in-out infinite;
      line-height: 1;
    }

    @keyframes wave {
      0%, 100% { transform: rotate(0deg); }
      25% { transform: rotate(10deg); }
      75% { transform: rotate(-10deg); }
    }

    .hero p {
      font-size: 1.35rem;
      margin-bottom: 2.5rem;
      opacity: 0.95;
      font-weight: 400;
      line-height: 1.7;
    }

    .hero-buttons {
      display: flex;
      gap: 1.25rem;
      justify-content: center;
      flex-wrap: wrap;
    }

    .btn-hero {
      display: inline-flex;
      align-items: center;
      gap: 0.75rem;
      padding: 1rem 2.5rem;
      border-radius: 50px;
      font-size: 1.1rem;
      font-weight: 600;
      text-decoration: none;
      transition: var(--transition);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    .btn-hero-primary {
      background: var(--cr-blanco);
      color: var(--cr-azul);
      border: 3px solid var(--cr-blanco);
    }

    .btn-hero-primary:hover {
      background: var(--cr-azul);
      color: var(--cr-blanco);
      transform: translateY(-3px);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
    }

    .btn-hero-secondary {
      background: transparent;
      color: var(--cr-blanco);
      border: 3px solid var(--cr-blanco);
    }

    .btn-hero-secondary:hover {
      background: var(--cr-blanco);
      color: var(--cr-rojo);
      transform: translateY(-3px);
    }

    /* CATEGORÍAS */
    .categories-section {
      padding: 5rem 2rem;
      max-width: 1400px;
      margin: 0 auto;
    }

    .section-header {
      text-align: center;
      margin-bottom: 4rem;
    }

    .section-title {
      font-family: 'Playfair Display', serif;
      font-size: 2.75rem;
      font-weight: 700;
      color: var(--cr-azul);
      margin-bottom: 1rem;
    }

    .section-subtitle {
      font-size: 1.2rem;
      color: var(--gray-700);
      max-width: 700px;
      margin: 0 auto;
    }

    .categories-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2rem;
      margin-top: 3rem;
    }

    .category-card {
      position: relative;
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow-lg);
      transition: var(--transition);
      text-decoration: none;
      display: block;
      height: 400px;
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      border: 3px solid transparent;
      animation: fadeInUp 0.6s ease-out backwards;
    }

    .category-card:nth-child(1) { animation-delay: 0.1s; }
    .category-card:nth-child(2) { animation-delay: 0.2s; }
    .category-card:nth-child(3) { animation-delay: 0.3s; }
    .category-card:nth-child(4) { animation-delay: 0.4s; }

    .category-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      transition: var(--transition);
    }

    .category-card:hover {
      transform: translateY(-8px);
      box-shadow: var(--shadow-xl);
      border-color: var(--cr-azul);
    }

    .category-card:hover::before {
      opacity: 0.7;
    }

    .category-content {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      padding: 2rem;
      color: var(--cr-blanco);
      z-index: 1;
      background: linear-gradient(to top, rgba(0, 0, 0, 0.9) 0%, transparent 100%);
    }

    .category-icon {
      font-size: 3rem;
      margin-bottom: 1rem;
      display: inline-block;
      filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
    }

    .category-title {
      font-size: 1.75rem;
      font-weight: 700;
      margin-bottom: 0.75rem;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .category-description {
      font-size: 1rem;
      opacity: 0.95;
      line-height: 1.6;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .category-servicios {
      background-image: url('https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=800&q=80');
      background-size: cover;
      background-position: center;
    }

    .category-servicios::before {
      background: linear-gradient(135deg, rgba(0, 43, 127, 0.5) 0%, rgba(0, 65, 184, 0.5) 100%);
    }

    .category-garaje {
      background-image: url('https://images.unsplash.com/photo-1556740749-887f6717d7e4?w=800&q=80');
      background-size: cover;
      background-position: center;
    }

    .category-garaje::before {
      background: linear-gradient(135deg, rgba(206, 17, 38, 0.5) 0%, rgba(230, 57, 70, 0.5) 100%);
    }

    .category-emprendedores {
      background-image: url('https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=800&q=80');
      background-size: cover;
      background-position: center;
    }

    .category-emprendedores::before {
      background: linear-gradient(135deg, rgba(0, 43, 127, 0.5) 0%, rgba(206, 17, 38, 0.5) 100%);
    }

    .category-emprendedoras {
      background-image: url('https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=800&q=80');
      background-size: cover;
      background-position: center;
    }

    .category-emprendedoras::before {
      background: linear-gradient(135deg, rgba(206, 17, 38, 0.5) 0%, rgba(0, 43, 127, 0.5) 100%);
    }

    /* ESTADÍSTICAS */
    .stats-section {
      background: var(--gradient-cr);
      padding: 4rem 2rem;
      color: var(--cr-blanco);
      margin: 4rem 0;
      position: relative;
      overflow: hidden;
    }

    .stats-flag-bg {
      position: absolute;
      font-size: 30rem;
      opacity: 0.05;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      line-height: 1;
      pointer-events: none;
    }

    .stats-container {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 3rem;
      text-align: center;
      position: relative;
      z-index: 1;
    }

    .stat-item {
      padding: 2rem;
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border-radius: var(--radius);
      border: 2px solid rgba(255, 255, 255, 0.2);
      transition: var(--transition);
    }

    .stat-item:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: translateY(-5px);
    }

    .stat-number {
      font-size: 3.5rem;
      font-weight: 800;
      margin-bottom: 0.5rem;
      line-height: 1;
    }

    .stat-label {
      font-size: 1.1rem;
      opacity: 0.9;
      font-weight: 500;
    }

    .stat-flag {
      font-size: 1.5rem;
      margin-left: 0.3rem;
      line-height: 1;
    }

    /* SECCIÓN PURA VIDA */
    .pura-vida-section {
      padding: 5rem 2rem;
      background: var(--cr-blanco);
      text-align: center;
    }

    .pura-vida-content {
      max-width: 800px;
      margin: 0 auto;
    }

    .pura-vida-title {
      font-family: 'Playfair Display', serif;
      font-size: 3rem;
      color: var(--cr-azul);
      margin-bottom: 1.5rem;
    }

    .pura-vida-flag {
      font-size: 2.5rem;
      margin-left: 0.5rem;
      line-height: 1;
    }

    .pura-vida-text {
      font-size: 1.3rem;
      color: var(--gray-700);
      line-height: 1.8;
      margin-bottom: 2rem;
    }

    .pura-vida-icons {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 2rem;
      margin-top: 3rem;
      max-width: 900px;
      margin-left: auto;
      margin-right: auto;
    }

    .pv-icon {
      text-align: center;
    }

    .pv-icon-img {
      width: 100%;
      height: 180px;
      object-fit: cover;
      border-radius: 12px;
      margin-bottom: 1rem;
      display: block;
      box-shadow: 0 4px 12px rgba(0, 43, 127, 0.2);
      transition: var(--transition);
      border: 3px solid transparent;
      cursor: pointer;
    }

    .pv-icon:hover .pv-icon-img {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0, 43, 127, 0.3);
      border-color: var(--cr-azul);
    }

    .pv-icon-text {
      font-size: 1rem;
      color: var(--cr-azul);
      font-weight: 600;
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
      .hero h1 {
        font-size: 2.25rem;
      }

      .hero h1 .cr-flag {
        font-size: 2rem;
      }

      .hero p {
        font-size: 1.1rem;
      }

      .section-title {
        font-size: 2rem;
      }

      .categories-grid {
        grid-template-columns: 1fr;
      }

      .category-card {
        height: 350px;
      }

      .stats-container {
        grid-template-columns: 1fr;
        gap: 2rem;
      }

      .hero-buttons {
        flex-direction: column;
        align-items: center;
      }

      .btn-hero {
        width: 100%;
        max-width: 300px;
        justify-content: center;
      }

      .logo .text .main {
        font-size: 1.2rem;
      }

      .logo .text .sub {
        font-size: 0.65rem;
      }

      .pura-vida-title {
        font-size: 2rem;
      }

      .pura-vida-text {
        font-size: 1.1rem;
      }

      .pura-vida-icons {
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
      }

      .pv-icon-img {
        height: 140px;
      }
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
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

<!-- HEADER -->
<header class="header">
  <a href="index.php" class="logo">
    <span class="flag emoji">🇨🇷</span>
    <div class="text">
      <span class="main">CompraTica</span>
      <span class="sub">100% COSTARRICENSE</span>
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
    
    <a href="emprendedores.php" class="menu-item">
      <i class="fas fa-rocket"></i>
      <span>Emprendedores</span>
    </a>
    
    <a href="emprendedoras.php" class="menu-item">
      <i class="fas fa-crown"></i>
      <span>Emprendedoras</span>
    </a>
    
    <div class="menu-divider"></div>
    
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


<!-- HERO SECTION -->
<section class="hero">
  <div class="hero-content">
    <div class="hero-badge">
      <span class="flag-emoji emoji">🇨🇷</span> ORGULLO COSTARRICENSE
    </div>
    <h1>
      Hecho en Costa Rica, Para Ticos
      <span class="cr-flag emoji">🇨🇷</span>
    </h1>
    <p>El primer marketplace 100% costarricense que conecta emprendedores ticos con compradores nacionales. Apoyemos lo nuestro y fortalezcamos nuestra economía local.</p>
    <div class="hero-buttons">
      <a href="#categorias" class="btn-hero btn-hero-primary">
        <i class="fas fa-compass"></i>
        Explorar Ahora
      </a>
      <a href="register.php" class="btn-hero btn-hero-secondary">
        <i class="fas fa-rocket"></i>
        Únete como Emprendedor
      </a>
    </div>
  </div>
</section>

<!-- CATEGORÍAS -->
<section class="categories-section" id="categorias">
  <div class="section-header">
    <h2 class="section-title">Descubrí Nuestro Mercado Tico</h2>
    <p class="section-subtitle">Todo lo que necesitás, hecho por ticos para ticos. Productos y servicios 100% costarricenses.</p>
  </div>

  <div class="categories-grid">
    <!-- SERVICIOS -->
    <a href="servicios.php" class="category-card category-servicios">
      <div class="category-content">
        <div class="category-icon">
          <i class="fas fa-briefcase"></i>
        </div>
        <h3 class="category-title">Servicios</h3>
        <p class="category-description">Encontrá profesionales ticos de primer nivel: diseño, fotografía, consultoría, reparaciones y mucho más</p>
      </div>
    </a>

    <!-- VENTA DE GARAJE -->
    <a href="venta-garaje.php" class="category-card category-garaje">
      <div class="category-content">
        <div class="category-icon">
          <i class="fas fa-tags"></i>
        </div>
        <h3 class="category-title">Venta de Garaje</h3>
        <p class="category-description">Descubrí tesoros únicos y productos de segunda mano en perfecto estado a precios que te van a encantar, mae</p>
      </div>
    </a>

    <!-- EMPRENDEDORES -->
    <a href="emprendedores.php" class="category-card category-emprendedores">
      <div class="category-content">
        <div class="category-icon">
          <i class="fas fa-rocket"></i>
        </div>
        <h3 class="category-title">Emprendedores</h3>
        <p class="category-description">Productos innovadores hechos por talentosos emprendedores ticos que están revolucionando el mercado nacional</p>
      </div>
    </a>

    <!-- EMPRENDEDORAS -->
    <a href="emprendedoras.php" class="category-card category-emprendedoras">
      <div class="category-content">
        <div class="category-icon">
          <i class="fas fa-crown"></i>
        </div>
        <h3 class="category-title">Emprendedoras</h3>
        <p class="category-description">Apoyá a mujeres ticas emprendedoras con productos únicos, artesanales y de la más alta calidad nacional</p>
      </div>
    </a>
  </div>
</section>

<!-- ESTADÍSTICAS -->
<section class="stats-section">
  <span class="stats-flag-bg emoji">🇨🇷</span>
  <div class="stats-container">
    <div class="stat-item">
      <div class="stat-number">500+</div>
      <div class="stat-label">Emprendedores Ticos</div>
    </div>
    <div class="stat-item">
      <div class="stat-number">10K+</div>
      <div class="stat-label">Productos Costarricenses</div>
    </div>
    <div class="stat-item">
      <div class="stat-number">5K+</div>
      <div class="stat-label">Ticos Satisfechos</div>
    </div>
    <div class="stat-item">
      <div class="stat-number">100%</div>
      <div class="stat-label">Orgullo Nacional <span class="stat-flag emoji">🇨🇷</span></div>
    </div>
  </div>
</section>

<!-- SECCIÓN PURA VIDA -->
<section class="pura-vida-section">
  <div class="pura-vida-content">
    <h2 class="pura-vida-title">¡Pura Vida, Mae! <span class="pura-vida-flag emoji">🇨🇷</span></h2>
    <p class="pura-vida-text">
      Somos más que un marketplace. Somos una comunidad de ticos apoyando ticos.
      Cada compra que hacés fortalece nuestra economía local y ayuda a que emprendedores
      costarricenses cumplan sus sueños. Juntos construimos un Costa Rica más próspero.
    </p>
    <div class="pura-vida-icons">
      <div class="pv-icon">
        <img
          src="https://cdn.getyourguide.com/image/format=auto,fit=contain,gravity=auto,quality=60,width=1440,height=650,dpr=1/tour_img/f75f22af67b8873946d5bb70e701aa3ae65305c9198890fca5ee43ae567d7093.jpg"
          alt="Volcán Arenal"
          class="pv-icon-img"
          id="pv-arenal-img"
          title="Hacé clic una vez para activar los sonidos y luego pasá el mouse">
        <span class="pv-icon-text">Volcán Arenal</span>
      </div>
      <div class="pv-icon">
        <img
          src="https://images.unsplash.com/photo-1559056199-641a0ac8b55e?w=400&h=300&fit=crop"
          alt="Café Costarricense"
          class="pv-icon-img"
          id="pv-cafe-img"
          title="Hacé clic una vez para activar los sonidos y luego pasá el mouse">
        <span class="pv-icon-text">Café de Altura</span>
      </div>
      <div class="pv-icon">
        <img
          src="https://costarica.org/wp-content/uploads/2017/05/Caribbean.jpg"
          alt="Playas de Costa Rica"
          class="pv-icon-img"
          id="pv-caribe-img"
          title="Hacé clic una vez para activar los sonidos y luego pasá el mouse">
        <span class="pv-icon-text">Playas del Caribe</span>
      </div>
      <div class="pv-icon">
        <img
          src="/imagenes/yiguirro.jpg"
          alt="Yigüirro"
          class="pv-icon-img"
          id="pv-yiguirro-img"
          title="Hacé clic una vez para activar los sonidos y luego pasá el mouse">
        <span class="pv-icon-text">Yigüirro Nacional</span>
      </div>
    </div>
  </div>
</section>

<!-- Audios de la sección Pura Vida -->
<audio id="audioArenal" src="/sonidos/arenal.mp3" preload="auto"></audio>
<audio id="audioCafe" src="/sonidos/cafe.mp3" preload="auto"></audio>
<audio id="audioCaribe" src="/sonidos/caribe.mp3" preload="auto"></audio>
<audio id="audioYiguirro" src="/sonidos/yiguirro.mp3" preload="auto"></audio>

<!-- FOOTER -->
<footer class="site-footer">
  <div class="footer-content">
    <div class="footer-section">
      <h3><span class="footer-section-flag emoji">🇨🇷</span> CompraTica</h3>
      <p>El marketplace costarricense que une a emprendedores ticos con compradores nacionales. Hecho en Costa Rica, con orgullo y dedicación.</p>
    </div>
    <div class="footer-section">
      <h3>Enlaces Rápidos</h3>
      <a href="servicios.php">Servicios</a>
      <a href="venta-garaje.php">Venta de Garaje</a>
      <a href="emprendedores.php">Emprendedores</a>
      <a href="emprendedoras.php">Emprendedoras</a>
    </div>
    <div class="footer-section">
      <h3>Para Emprendedores</h3>
      <a href="affiliate/login.php">Portal de Afiliados</a>
      <a href="register.php">Registrarse</a>
      <a href="admin/login.php">Administración</a>
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
document.addEventListener('DOMContentLoaded', function () {
  // Carrito badge
  const cartBadge = document.getElementById('cartBadge');
  const cantidadInicial = <?php echo $cantidadProductos; ?>;

  if (cantidadInicial > 0) {
    cartBadge.style.display = 'inline-block';
  } else {
    cartBadge.style.display = 'none';
  }

  // Smooth scroll
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    });
  });

  // Sonidos Pura Vida (Arenal, Café, Caribe, Yigüirro)
  const soundMap = [
    { imgId: 'pv-arenal-img',  audioId: 'audioArenal'  },
    { imgId: 'pv-cafe-img',    audioId: 'audioCafe'    },
    { imgId: 'pv-caribe-img',  audioId: 'audioCaribe'  },
    { imgId: 'pv-yiguirro-img',audioId: 'audioYiguirro'}
  ];

  let audioDesbloqueado = false;

  function attachHoverSound(imgId, audioId) {
    const img = document.getElementById(imgId);
    const audio = document.getElementById(audioId);
    if (!img || !audio) return;

    function reproducirDesdeInicio() {
      try {
        audio.currentTime = 0;
        const p = audio.play();
        if (p && p.catch) {
          p.catch(err => console.warn('Error al reproducir audio (' + audioId + '):', err));
        }
      } catch (e) {
        console.warn('No se pudo reproducir audio (' + audioId + '):', e);
      }
    }

    // Primer clic en cualquier imagen: desbloquea audio para todas
    img.addEventListener('click', function () {
      if (!audioDesbloqueado) {
        audioDesbloqueado = true;
        reproducirDesdeInicio();
        setTimeout(function () {
          audio.pause();
          audio.currentTime = 0;
        }, 200);
      }
    });

    img.addEventListener('mouseenter', function () {
      if (!audioDesbloqueado) return;
      reproducirDesdeInicio();
    });

    img.addEventListener('mouseleave', function () {
      audio.pause();
      audio.currentTime = 0;
    });
  }

  soundMap.forEach(cfg => attachHoverSound(cfg.imgId, cfg.audioId));
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