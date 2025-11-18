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

logDebug("SERVICIOS_START", ['uri' => $_SERVER['REQUEST_URI']]);

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

// Obtener categorías de servicios
$pdo = db();
$categories = [];
$totalServices = 0;

try {
    $stmt = $pdo->query("
        SELECT
            sc.*,
            COUNT(s.id) as service_count
        FROM service_categories sc
        LEFT JOIN services s ON s.category_id = sc.id AND s.is_active = 1
        WHERE sc.is_active = 1
        GROUP BY sc.id
        ORDER BY sc.display_order ASC, sc.name ASC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($categories as $cat) {
        $totalServices += (int)$cat['service_count'];
    }
} catch (Exception $e) {
    logDebug("ERROR_LOADING_CATEGORIES", ['error' => $e->getMessage()]);
}

logDebug("RENDERING_PAGE", ['categories_count' => count($categories), 'total_services' => $totalServices]);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Servicios Profesionales Ticos — <?php echo APP_NAME; ?></title>

  <!-- SEO -->
  <meta name="description" content="Encuentra servicios profesionales en Costa Rica: abogados, mantenimiento, tutorías y fletes. Reserva online con los mejores profesionales ticos.">
  <meta name="keywords" content="servicios costa rica, abogados, mantenimiento, tutorías, fletes, profesionales ticos">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2rem;
      margin-bottom: 3rem;
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
    }

    .category-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-top: 1.5rem;
      border-top: 2px solid var(--gray-100);
      font-size: 0.9rem;
    }

    .category-count {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-weight: 600;
      color: var(--gray-700);
    }

    .category-count .badge {
      background: var(--primary);
      color: var(--white);
      padding: 0.25rem 0.75rem;
      border-radius: 50px;
      font-size: 0.85rem;
      font-weight: 700;
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

      .stat-number {
        font-size: 2rem;
      }
    }
  </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/header.php'; ?>

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
          <div class="stat-number"><?php echo count($categories); ?></div>
          <div class="stat-label">Categorías</div>
        </div>
        <div class="stat-item">
          <div class="stat-number"><?php echo $totalServices; ?>+</div>
          <div class="stat-label">Servicios</div>
        </div>
        <div class="stat-item">
          <div class="stat-number">100%</div>
          <div class="stat-label">Ticos</div>
        </div>
      </div>
    </div>
  </section>

  <!-- Search Section -->
  <section class="search-section">
    <form class="search-form" action="services_search.php" method="GET">
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

  <!-- Categories Section -->
  <div class="section-header">
    <h2 class="section-title">Explorá Nuestros Servicios</h2>
    <p class="section-subtitle">
      Seleccioná la categoría que necesitás y encontrá a los mejores profesionales
    </p>
  </div>

  <div class="categories-grid">
    <?php if (empty($categories)): ?>
      <div style="grid-column: 1/-1; text-align: center; padding: 3rem;">
        <i class="fas fa-box-open" style="font-size: 4rem; color: var(--gray-300); margin-bottom: 1rem;"></i>
        <h3 style="color: var(--gray-600); margin-bottom: 0.5rem;">No hay categorías disponibles aún</h3>
        <p style="color: var(--gray-500);">Estamos trabajando para traerte los mejores servicios</p>
      </div>
    <?php else: ?>
      <?php foreach ($categories as $cat): ?>
        <a href="services_list.php?category=<?php echo urlencode($cat['slug']); ?>" class="category-card <?php echo $cat['requires_online_payment'] ? 'payment-required' : ''; ?>">
          <div class="category-icon">
            <i class="<?php echo htmlspecialchars($cat['icon']); ?>"></i>
          </div>

          <h3 class="category-title"><?php echo htmlspecialchars($cat['name']); ?></h3>

          <p class="category-description">
            <?php echo htmlspecialchars($cat['description']); ?>
          </p>

          <div class="category-footer">
            <div class="category-count">
              <span class="badge"><?php echo (int)$cat['service_count']; ?></span>
              <span>servicios disponibles</span>
            </div>
            <?php if ($cat['requires_online_payment']): ?>
              <span class="payment-badge">
                <i class="fas fa-credit-card"></i>
                Pago online
              </span>
            <?php endif; ?>
          </div>

          <div style="margin-top: 1.5rem;">
            <span class="category-link">
              Ver servicios
              <i class="fas fa-arrow-right"></i>
            </span>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script src="/assets/js/services.js"></script>
<script>
const cartBadge = document.getElementById('cartBadge');
const cantidadInicial = <?php echo $cantidadProductos; ?>;

if (cartBadge) {
  cartBadge.style.display = cantidadInicial > 0 ? 'flex' : 'none';
}
</script>

</body>
</html>
