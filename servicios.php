<?php
/**
 * Página Principal de Servicios
 *
 * Muestra las categorías de servicios disponibles:
 * - Abogados
 * - Mantenimiento y Reparación
 * - Tutorías
 * - Fletes
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

// ============= LOG DE DEBUG =============
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$logFile = $logDir . '/servicios_debug.log';

function logDebug($msg, $data = null) {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($data) $line .= ' | ' . json_encode($data);
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

logDebug("SERVICIOS_START", ['uri' => $_SERVER['REQUEST_URI'] ?? '']);

// ============= CONFIGURACIÓN DE SESIONES =============
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

// Obtener servicios activos del sistema de afiliados
$pdo = db();
$servicios = [];

try {
    $stmt = $pdo->query("
        SELECT
            s.id,
            s.title,
            s.description,
            s.short_description,
            s.price_per_hour as service_price,
            'CRC' as salary_currency,
            'hourly' as service_price_type,
            s.slug,
            a.name as provider_name,
            a.name as company_name,
            NULL as location,
            s.created_at,
            s.is_active,
            0 as is_featured,
            NULL as image_1
        FROM services s
        INNER JOIN affiliates a ON a.id = s.affiliate_id
        WHERE s.is_active = 1
          AND a.is_active = 1
        ORDER BY s.created_at DESC
    ");
    $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    logDebug("ERROR_LOADING_SERVICES", ['error' => $e->getMessage()]);
}

logDebug("RENDERING_PAGE", ['services_count' => count($servicios)]);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Servicios Profesionales en Costa Rica | Abogados, Shuttle, Mantenimiento - CompraTica</title>

  <!-- SEO -->
  <meta name="description" content="Directorio de servicios profesionales en Costa Rica: abogados, shuttle aeropuerto, mantenimiento, tutorías y fletes. Contacta a los mejores profesionales ticos.">
  <meta name="keywords" content="servicios costa rica, abogados costa rica, shuttle aeropuerto, mantenimiento hogar, tutores costa rica, fletes san jose, profesionales ticos, directorio servicios">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://compratica.com/servicios.php">

  <!-- Open Graph -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://compratica.com/servicios.php">
  <meta property="og:title" content="Servicios Profesionales en Costa Rica">
  <meta property="og:description" content="Encuentra abogados, shuttle, mantenimiento y más servicios en Costa Rica.">
  <meta property="og:image" content="https://compratica.com/logo.png">

  <!-- Schema.org para Servicios -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "ItemList",
    "name": "Servicios Profesionales en Costa Rica",
    "description": "Directorio de servicios profesionales costarricenses",
    "url": "https://compratica.com/servicios.php",
    "itemListElement": [
      {"@type": "ListItem", "position": 1, "name": "Abogados", "url": "https://compratica.com/services_list.php?category=abogados"},
      {"@type": "ListItem", "position": 2, "name": "Shuttle Aeropuerto", "url": "https://compratica.com/shuttle_search.php"},
      {"@type": "ListItem", "position": 3, "name": "Mantenimiento", "url": "https://compratica.com/services_list.php?category=mantenimiento"},
      {"@type": "ListItem", "position": 4, "name": "Tutorías", "url": "https://compratica.com/services_list.php?category=tutorias"},
      {"@type": "ListItem", "position": 5, "name": "Fletes", "url": "https://compratica.com/services_list.php?category=fletes"}
    ]
  }
  </script>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">

  <!-- Soporte de emojis para todas las plataformas -->
  <link href="https://fonts.googleapis.com/css2?family=Noto+Color+Emoji&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="/assets/css/main.css">

  <style>
    :root {
      /* Colores Costa Rica */
      --cr-azul: #002b7f;
      --cr-azul-claro: #0041b8;
      --cr-rojo: #ce1126;
      --cr-rojo-claro: #e63946;

      /* Paleta Moderna */
      --primary: #1a73e8;
      --primary-dark: #1557b0;
      --secondary: #7c3aed;
      --accent: #10b981;
      --warning: #f59e0b;
      --danger: #ef4444;

      /* Neutros */
      --white: #ffffff;
      --gray-50: #f9fafb;
      --gray-100: #f3f4f6;
      --gray-200: #e5e7eb;
      --gray-300: #d1d5db;
      --gray-400: #9ca3af;
      --gray-500: #6b7280;
      --gray-600: #4b5563;
      --gray-700: #374151;
      --gray-800: #1f2937;
      --gray-900: #111827;

      /* Sombras */
      --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
      --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
      --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
      --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);

      --radius-sm: 8px;
      --radius: 12px;
      --radius-lg: 16px;
      --radius-xl: 24px;
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: var(--gray-50);
      color: var(--gray-900);
      line-height: 1.6;
      overflow-x: hidden;
    }

    a {
      text-decoration: none;
      color: inherit;
      transition: var(--transition);
    }

    /* Clase para emojis */
    .emoji {
      font-family: "Noto Color Emoji", "Apple Color Emoji", "Segoe UI Emoji", sans-serif;
      font-style: normal;
      font-weight: normal;
      line-height: 1;
    }

    /* Header */
    .header {
      background: var(--white);
      padding: 1.25rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
      border-bottom: 3px solid var(--cr-azul);
      box-shadow: 0 4px 12px rgba(0, 43, 127, 0.1);
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
    }

    .logo .text .main {
      color: var(--cr-azul);
      font-weight: 800;
      font-size: 1.5rem;
      letter-spacing: -0.02em;
    }

    .logo .text .sub {
      color: var(--primary);
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
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: var(--white);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: var(--transition);
      border: none;
      box-shadow: var(--shadow);
      font-size: 1.1rem;
    }

    .btn-icon:hover {
      transform: translateY(-2px) scale(1.05);
      box-shadow: var(--shadow-lg);
    }

    .cart-badge {
      position: absolute;
      top: -4px;
      right: -4px;
      min-width: 20px;
      height: 20px;
      border-radius: var(--radius-sm);
      background: var(--danger);
      color: var(--white);
      font-size: 0.7rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
    }

    /* Main Wrapper */
    .main-wrapper {
      max-width: 1280px;
      margin: 0 auto;
      padding: 2.5rem 2rem;
    }

    /* Hero Section */
    .hero-section {
      background: linear-gradient(135deg, rgba(26, 115, 232, 0.95), rgba(124, 58, 237, 0.95)),
                  url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120"><path fill="%23ffffff" fill-opacity="0.1" d="M0,64L48,69.3C96,75,192,85,288,80C384,75,480,53,576,48C672,43,768,53,864,58.7C960,64,1056,64,1152,58.7L1248,53L1248,120L1152,120C1056,120,960,120,864,120C768,120,672,120,576,120C480,120,384,120,288,120C192,120,96,120,48,120L0,120Z"></path></svg>');
      background-size: cover;
      background-position: center;
      border-radius: var(--radius-xl);
      padding: 4rem 3rem;
      margin-bottom: 3rem;
      color: var(--white);
      position: relative;
      overflow: hidden;
    }

    .hero-section::before {
      content: "";
      position: absolute;
      top: -50%;
      right: -10%;
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(255, 255, 255, 0.1), transparent 70%);
      border-radius: 50%;
    }

    .hero-content {
      position: relative;
      z-index: 2;
      max-width: 800px;
    }

    .hero-title {
      font-family: "Poppins", sans-serif;
      font-size: clamp(2.5rem, 5vw, 4rem);
      font-weight: 700;
      margin-bottom: 1.5rem;
      line-height: 1.1;
    }

    .hero-description {
      font-size: 1.25rem;
      margin-bottom: 2.5rem;
      line-height: 1.7;
      opacity: 0.95;
    }

    .hero-stats {
      display: flex;
      gap: 3rem;
      flex-wrap: wrap;
    }

    .stat-item {
      display: flex;
      flex-direction: column;
    }

    .stat-number {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 0.25rem;
    }

    .stat-label {
      font-size: 0.95rem;
      opacity: 0.9;
    }

    /* Search Bar */
    .search-section {
      background: var(--white);
      border-radius: var(--radius-lg);
      padding: 2rem;
      box-shadow: var(--shadow-lg);
      margin-bottom: 3rem;
    }

    .search-form {
      display: flex;
      gap: 1rem;
    }

    .search-input {
      flex: 1;
      padding: 1.25rem 1.5rem;
      border: 2px solid var(--gray-200);
      border-radius: var(--radius);
      font-size: 1rem;
      transition: var(--transition);
    }

    .search-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 4px rgba(26, 115, 232, 0.1);
    }

    .search-btn {
      padding: 1.25rem 2.5rem;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: var(--white);
      border: none;
      border-radius: var(--radius);
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .search-btn:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-lg);
    }

    /* Categories Grid */
    .section-header {
      text-align: center;
      margin-bottom: 3rem;
    }

    .section-title {
      font-family: "Poppins", sans-serif;
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--gray-900);
      margin-bottom: 0.75rem;
    }

    .section-subtitle {
      font-size: 1.125rem;
      color: var(--gray-600);
      max-width: 600px;
      margin: 0 auto;
    }

    .categories-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 2rem;
      margin-bottom: 3rem;
      align-items: stretch;
    }

    .category-card {
      background: var(--white);
      border-radius: var(--radius-lg);
      padding: 2.5rem;
      box-shadow: var(--shadow);
      transition: var(--transition);
      cursor: pointer;
      position: relative;
      overflow: hidden;
      border: 3px solid transparent;
      display: flex;
      flex-direction: column;
      height: 100%;
    }

    .category-card::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 6px;
      background: linear-gradient(90deg, var(--primary), var(--secondary));
      transform: scaleX(0);
      transform-origin: left;
      transition: transform 0.4s ease;
    }

    .category-card:hover::before {
      transform: scaleX(1);
    }

    .category-card:hover {
      transform: translateY(-10px);
      box-shadow: var(--shadow-xl);
      border-color: var(--primary);
    }

    .category-card.payment-required {
      border-color: var(--warning);
    }

    .category-icon {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: var(--white);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2.25rem;
      margin-bottom: 1.75rem;
      box-shadow: var(--shadow-md);
    }

    .category-title {
      font-size: 1.75rem;
      font-weight: 700;
      color: var(--gray-900);
      margin-bottom: 1rem;
    }

    .category-description {
      font-size: 1rem;
      color: var(--gray-600);
      line-height: 1.7;
      margin-bottom: 1.5rem;
      flex: 1 0 auto;
    }

    .category-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-top: 1.5rem;
      border-top: 2px solid var(--gray-100);
      font-size: 0.9rem;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .category-count {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-weight: 600;
      color: var(--gray-700);
      flex: 1 1 auto;
      min-width: fit-content;
    }

    .category-count .badge {
      background: var(--primary);
      color: var(--white);
      padding: 0.25rem 0.75rem;
      border-radius: 50px;
      font-size: 0.85rem;
      font-weight: 700;
      flex-shrink: 0;
    }

    .payment-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      border-radius: 50px;
      font-size: 0.85rem;
      font-weight: 600;
      background: var(--warning);
      color: var(--white);
      flex-shrink: 0;
      white-space: nowrap;
    }

    .category-link {
      color: var(--primary);
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 1rem;
    }

    .category-link:hover {
      color: var(--primary-dark);
      gap: 0.75rem;
    }

    /* Responsive */
    @media (max-width: 1024px) {
      .categories-grid {
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 1.5rem;
      }
    }

    @media (max-width: 768px) {
      .header {
        padding: 1rem 1.25rem;
      }

      .main-wrapper {
        padding: 1.75rem 1.25rem;
      }

      .hero-section {
        padding: 2.5rem 1.5rem;
      }

      .hero-stats {
        gap: 2rem;
      }

      .search-form {
        flex-direction: column;
      }

      .categories-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
      }

      .category-card {
        padding: 2rem;
      }

      .category-footer {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
      }

      .category-count {
        width: 100%;
      }

      .payment-badge {
        align-self: flex-start;
      }

      .stat-number {
        font-size: 2rem;
      }
    }

    @media (max-width: 480px) {
      .category-card {
        padding: 1.5rem;
      }

      .category-icon {
        width: 64px;
        height: 64px;
        font-size: 1.75rem;
      }

      .category-title {
        font-size: 1.5rem;
      }

      .section-title {
        font-size: 2rem;
      }
    }
    /* MENÚ HAMBURGUESA Y CARRITO - Estilos adicionales */
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
      background: var(--cr-azul);
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
      color: var(--gray-900);
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
      color: var(--gray-900);
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
      color: var(--gray-900);
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
      color: var(--gray-900);
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
      color: var(--accent);
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
      color: var(--gray-900);
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
      background: var(--accent);
      color: var(--white);
    }

    .cart-popover-btn.primary:hover {
      background: #0d9668;
    }
  </style>
</head>
<body>

<!-- HEADER -->
<header class="header">
  <a href="index" class="logo">
    <span class="flag emoji">🇨🇷</span>
    <div class="text">
      <span class="main">CompraTica</span>
      <span class="sub">Servicios Ticos</span>
    </div>
  </a>
  <nav class="header-nav">
    <a href="empleos" class="btn-icon" title="Ver Empleos" aria-label="Ver empleos">
      <i class="fas fa-briefcase"></i>
    </a>

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
        <a href="cart" class="cart-popover-btn secondary">
          Ver carrito
        </a>
        <a href="checkout" id="checkoutBtn" class="cart-popover-btn primary">
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
      <a href="my_orders" class="menu-item">
        <i class="fas fa-box"></i>
        <span>Mis Órdenes</span>
      </a>
      <a href="cart" class="menu-item">
        <i class="fas fa-shopping-cart"></i>
        <span>Mi Carrito</span>
      </a>
      <div class="menu-divider"></div>
    <?php else: ?>
      <a href="login" class="menu-item primary">
        <i class="fas fa-sign-in-alt"></i>
        <span>Iniciar Sesión</span>
      </a>
      <div class="menu-divider"></div>
    <?php endif; ?>

    <a href="index" class="menu-item">
      <i class="fas fa-home"></i>
      <span>Inicio</span>
    </a>

    <a href="empleos" class="menu-item">
      <i class="fas fa-briefcase"></i>
      <span>Empleos</span>
    </a>

    <a href="servicios" class="menu-item">
      <i class="fas fa-tools"></i>
      <span>Servicios</span>
    </a>

    <a href="venta-garaje" class="menu-item">
      <i class="fas fa-tags"></i>
      <span>Venta de Garaje</span>
    </a>

    <div class="menu-item" style="opacity: 0.5; cursor: not-allowed;">
      <i class="fas fa-rocket"></i>
      <span>Emprendedores - Muy Pronto</span>
    </div>

    <div class="menu-item" style="opacity: 0.5; cursor: not-allowed;">
      <i class="fas fa-crown"></i>
      <span>Emprendedoras - Muy Pronto</span>
    </div>

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
      <a href="logout" class="menu-item danger">
        <i class="fas fa-sign-out-alt"></i>
        <span>Cerrar Sesión</span>
      </a>
    <?php endif; ?>
  </div>
</aside>

<div class="main-wrapper">
  <!-- Hero Section -->
  <section class="hero-section">
    <div class="hero-content">
      <h1 class="hero-title">
        Servicios Profesionales Ticos <span class="emoji">🇨🇷</span>
      </h1>
      <p class="hero-description">
        Conectá con los mejores profesionales de Costa Rica. Desde abogados hasta técnicos,
        encontrá el servicio que necesitás y reservá directamente online.
      </p>

      <div class="hero-stats">
        <div class="stat-item">
          <div class="stat-number"><?php echo count($servicios); ?></div>
          <div class="stat-label">Servicios Disponibles</div>
        </div>
        <div class="stat-item">
          <div class="stat-number">100%</div>
          <div class="stat-label">Verificados</div>
        </div>
        <div class="stat-item">
          <div class="stat-number">Directo</div>
          <div class="stat-label">Sin Intermediarios</div>
        </div>
      </div>
    </div>
  </section>

  <!-- Search Section -->
  <section class="search-section">
    <form class="search-form" action="services_search" method="GET">
      <input
        type="text"
        name="q"
        class="search-input"
        placeholder="¿Qué servicio estás buscando? (ej: abogado, plomero, tutor de matemáticas...)"
        required
      >
      <button type="submit" class="search-btn">
        <i class="fas fa-search"></i>
        Buscar
      </button>
    </form>
  </section>

  <!-- Services Section -->
  <div class="section-header">
    <h2 class="section-title">Servicios Disponibles</h2>
    <p class="section-subtitle">
      Encuentra el servicio que necesitas
    </p>
  </div>

  <?php if (empty($servicios)): ?>
    <div style="text-align: center; padding: 4rem 2rem; color: var(--gray-500);">
      <i class="fas fa-tools" style="font-size: 4rem; color: var(--gray-300); margin-bottom: 1rem;"></i>
      <h3 style="color: var(--gray-600); margin-bottom: 0.5rem;">No hay servicios disponibles en este momento</h3>
      <p>Vuelve pronto para ver nuevas ofertas de servicios</p>
    </div>
  <?php else: ?>
    <div class="categories-grid">
      <?php foreach ($servicios as $service): ?>
        <div class="category-card" onclick="window.location.href='publicacion-detalle.php?id=<?php echo $service['id']; ?>'" style="cursor: pointer;">
          <?php if ($service['is_featured']): ?>
            <div style="margin-bottom: 1rem;">
              <span class="badge" style="background: var(--warning); color: var(--white);">
                <i class="fas fa-star"></i>
                Destacado
              </span>
            </div>
          <?php endif; ?>

          <?php if ($service['image_1']): ?>
            <div style="width: 100%; height: 200px; margin-bottom: 1.5rem; border-radius: var(--radius); overflow: hidden;">
              <img src="<?php echo htmlspecialchars($service['image_1']); ?>" alt="<?php echo htmlspecialchars($service['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
            </div>
          <?php else: ?>
            <div class="category-icon">
              <i class="fas fa-tools"></i>
            </div>
          <?php endif; ?>

          <h3 class="category-title"><?php echo htmlspecialchars($service['title']); ?></h3>

          <p class="category-description">
            <?php echo nl2br(htmlspecialchars(substr($service['description'], 0, 150))); ?>
            <?php if (strlen($service['description']) > 150) echo '...'; ?>
          </p>

          <div class="category-footer">
            <div class="category-count">
              <?php if ($service['service_price']): ?>
                <?php
                $currency = $service['salary_currency'] === 'USD' ? '$' : '₡';
                echo '<strong style="font-size: 1.25rem; color: var(--accent);">' . $currency . number_format($service['service_price']) . '</strong>';
                if ($service['service_price_type']) {
                  $types = [
                    'fixed' => '',
                    'hourly' => '/hora',
                    'daily' => '/día',
                    'negotiable' => ' (negociable)'
                  ];
                  echo $types[$service['service_price_type']] ?? '';
                }
                ?>
              <?php else: ?>
                <span>Precio a consultar</span>
              <?php endif; ?>
            </div>

            <?php if ($service['location']): ?>
              <span style="font-size: 0.85rem; color: var(--gray-600);">
                <i class="fas fa-map-marker-alt"></i>
                <?php echo htmlspecialchars($service['location']); ?>
              </span>
            <?php endif; ?>
          </div>

          <div style="margin-top: 1.5rem;">
            <span class="category-link">
              Ver detalles
              <i class="fas fa-arrow-right"></i>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
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

// Badge inicial del carrito
const cartBadge = document.getElementById('cartBadge');
const cantidadInicial = <?php echo $cantidadProductos; ?>;
if (cartBadge) {
  cartBadge.style.display = cantidadInicial > 0 ? 'flex' : 'none';
}
</script>

</body>
</html>
