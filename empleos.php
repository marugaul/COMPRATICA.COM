<?php
/**
 * Página Pública de Empleos
 * Muestra todos los empleos activos de job_listings
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

// Configuración de sesiones
$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
} else {
    ini_set('session.save_path', '/tmp');
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

// Detectar HTTPS
$__isHttps = false;
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') $__isHttps = true;
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') $__isHttps = true;
if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') $__isHttps = true;
if (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false) $__isHttps = true;

$host = $_SERVER['HTTP_HOST'] ?? parse_url(defined('BASE_URL') ? BASE_URL : '', PHP_URL_HOST) ?? '';
$cookieDomain = '';
if ($host && strpos($host, 'localhost') === false && !filter_var($host, FILTER_VALIDATE_IP)) {
    $clean = preg_replace('/^www\./i', '', $host);
    if (filter_var($clean, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        $cookieDomain = $clean;
    }
}

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

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cantidadProductos = 0;
foreach ($_SESSION['cart'] as $it) { $cantidadProductos += (int)($it['qty'] ?? 0); }

$isLoggedIn = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
$userName = $_SESSION['name'] ?? 'Usuario';

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

// Obtener empleos activos
$pdo = db();
$empleos = [];

try {
    $stmt = $pdo->query("
        SELECT
            jl.*,
            u.company_name,
            u.company_logo
        FROM job_listings jl
        INNER JOIN users u ON u.id = jl.employer_id
        WHERE jl.listing_type = 'job'
          AND jl.is_active = 1
          AND u.status = 'active'
        ORDER BY jl.is_featured DESC, jl.created_at DESC
    ");
    $empleos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading jobs: " . $e->getMessage());
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Empleos en Costa Rica | Bolsa de Trabajo - CompraTica</title>

  <meta name="description" content="Encuentra empleos en Costa Rica. Ofertas de trabajo actualizadas en tecnología, administración, ventas, salud y más. Tu próximo empleo te espera.">
  <meta name="keywords" content="empleos costa rica, trabajo costa rica, ofertas de empleo, bolsa de trabajo, empleos san jose">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://compratica.com/empleos.php">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Color+Emoji&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/main.css">

  <style>
    :root {
      --cr-azul: #002b7f;
      --cr-azul-claro: #0041b8;
      --cr-rojo: #ce1126;
      --primary: #1a73e8;
      --primary-dark: #1557b0;
      --secondary: #7c3aed;
      --accent: #10b981;
      --warning: #f59e0b;
      --danger: #ef4444;
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

    .emoji {
      font-family: "Noto Color Emoji", "Apple Color Emoji", "Segoe UI Emoji", sans-serif;
      font-style: normal;
      font-weight: normal;
      line-height: 1;
    }

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

    .main-wrapper {
      max-width: 1280px;
      margin: 0 auto;
      padding: 2.5rem 2rem;
    }

    .hero-section {
      background: linear-gradient(135deg, rgba(26, 115, 232, 0.95), rgba(124, 58, 237, 0.95));
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

    .jobs-grid {
      display: grid;
      gap: 1.5rem;
      margin-bottom: 3rem;
    }

    .job-card {
      background: var(--white);
      border-radius: var(--radius-lg);
      padding: 2rem;
      box-shadow: var(--shadow);
      transition: var(--transition);
      cursor: pointer;
      border: 2px solid transparent;
      display: flex;
      gap: 1.5rem;
    }

    .job-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-xl);
      border-color: var(--primary);
    }

    .job-logo {
      width: 80px;
      height: 80px;
      border-radius: var(--radius);
      object-fit: cover;
      background: var(--gray-100);
      flex-shrink: 0;
    }

    .job-content {
      flex: 1;
      min-width: 0;
    }

    .job-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--gray-900);
      margin-bottom: 0.5rem;
    }

    .job-company {
      font-size: 1rem;
      color: var(--gray-600);
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .job-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      margin-bottom: 1rem;
      font-size: 0.9rem;
      color: var(--gray-600);
    }

    .job-meta-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .job-description {
      color: var(--gray-700);
      margin-bottom: 1rem;
      line-height: 1.6;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .job-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-top: 1rem;
      border-top: 1px solid var(--gray-200);
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      border-radius: 50px;
      font-size: 0.85rem;
      font-weight: 600;
    }

    .badge.featured {
      background: var(--warning);
      color: var(--white);
    }

    .badge.job-type {
      background: var(--gray-100);
      color: var(--gray-700);
    }

    .btn-apply {
      padding: 0.75rem 1.5rem;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: var(--white);
      border: none;
      border-radius: var(--radius);
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .btn-apply:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .empty-state {
      text-align: center;
      padding: 4rem 2rem;
      color: var(--gray-500);
    }

    .empty-state i {
      font-size: 4rem;
      margin-bottom: 1rem;
      color: var(--gray-300);
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

      .job-card {
        flex-direction: column;
        padding: 1.5rem;
      }

      .job-logo {
        width: 60px;
        height: 60px;
      }

      .job-meta {
        flex-direction: column;
        gap: 0.5rem;
      }

      .job-footer {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
      }
    }
  </style>
</head>
<body>

<header class="header">
  <a href="index" class="logo">
    <span class="flag emoji">🇨🇷</span>
    <div class="text">
      <span class="main">CompraTica</span>
      <span class="sub">Empleos Ticos</span>
    </div>
  </a>
  <nav class="header-nav">
    <a href="index" class="btn-icon" title="Inicio">
      <i class="fas fa-home"></i>
    </a>
    <a href="servicios" class="btn-icon" title="Servicios">
      <i class="fas fa-tools"></i>
    </a>
  </nav>
</header>

<div class="main-wrapper">
  <section class="hero-section">
    <div class="hero-content">
      <h1 class="hero-title">
        Empleos en Costa Rica <span class="emoji">💼</span>
      </h1>
      <p class="hero-description">
        Encuentra tu próximo empleo en las mejores empresas de Costa Rica.
        Ofertas actualizadas en tecnología, administración, ventas, salud y mucho más.
      </p>

      <div class="hero-stats">
        <div class="stat-item">
          <div class="stat-number"><?php echo count($empleos); ?></div>
          <div class="stat-label">Empleos Disponibles</div>
        </div>
        <div class="stat-item">
          <div class="stat-number">100%</div>
          <div class="stat-label">Verificados</div>
        </div>
        <div class="stat-item">
          <div class="stat-number">Gratis</div>
          <div class="stat-label">Para Aplicar</div>
        </div>
      </div>
    </div>
  </section>

  <div class="section-header">
    <h2 class="section-title">Ofertas de Empleo</h2>
    <p class="section-subtitle">
      Encuentra el trabajo perfecto para ti
    </p>
  </div>

  <?php if (empty($empleos)): ?>
    <div class="empty-state">
      <i class="fas fa-briefcase"></i>
      <h3>No hay empleos disponibles en este momento</h3>
      <p>Vuelve pronto para ver nuevas oportunidades laborales</p>
    </div>
  <?php else: ?>
    <div class="jobs-grid">
      <?php foreach ($empleos as $job): ?>
        <div class="job-card" onclick="window.location.href='publicacion-detalle.php?id=<?php echo $job['id']; ?>'">
          <?php if ($job['company_logo']): ?>
            <img src="<?php echo htmlspecialchars($job['company_logo']); ?>" alt="Logo" class="job-logo">
          <?php else: ?>
            <div class="job-logo" style="display: flex; align-items: center; justify-content: center; font-size: 2rem; color: var(--gray-400);">
              <i class="fas fa-building"></i>
            </div>
          <?php endif; ?>

          <div class="job-content">
            <h3 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h3>

            <div class="job-company">
              <i class="fas fa-building"></i>
              <?php echo htmlspecialchars($job['company_name'] ?? 'Empresa'); ?>
            </div>

            <div class="job-meta">
              <?php if ($job['location']): ?>
                <div class="job-meta-item">
                  <i class="fas fa-map-marker-alt"></i>
                  <?php echo htmlspecialchars($job['location']); ?>
                </div>
              <?php endif; ?>

              <?php if ($job['job_type']): ?>
                <div class="job-meta-item">
                  <i class="fas fa-clock"></i>
                  <?php
                  $jobTypes = [
                    'full-time' => 'Tiempo Completo',
                    'part-time' => 'Medio Tiempo',
                    'freelance' => 'Freelance',
                    'contract' => 'Contrato',
                    'internship' => 'Pasantía'
                  ];
                  echo $jobTypes[$job['job_type']] ?? $job['job_type'];
                  ?>
                </div>
              <?php endif; ?>

              <?php if ($job['salary_min'] && $job['salary_max']): ?>
                <div class="job-meta-item">
                  <i class="fas fa-money-bill-wave"></i>
                  <?php
                  $currency = $job['salary_currency'] === 'USD' ? '$' : '₡';
                  echo $currency . number_format($job['salary_min']) . ' - ' . $currency . number_format($job['salary_max']);
                  if ($job['salary_period']) {
                    $periods = [
                      'hour' => '/hora',
                      'day' => '/día',
                      'week' => '/sem',
                      'month' => '/mes',
                      'year' => '/año'
                    ];
                    echo $periods[$job['salary_period']] ?? '';
                  }
                  ?>
                </div>
              <?php endif; ?>

              <?php if ($job['remote_allowed']): ?>
                <div class="job-meta-item">
                  <i class="fas fa-home"></i>
                  Remoto
                </div>
              <?php endif; ?>
            </div>

            <?php if ($job['description']): ?>
              <div class="job-description">
                <?php echo nl2br(htmlspecialchars($job['description'])); ?>
              </div>
            <?php endif; ?>

            <div class="job-footer">
              <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <?php if ($job['is_featured']): ?>
                  <span class="badge featured">
                    <i class="fas fa-star"></i>
                    Destacado
                  </span>
                <?php endif; ?>

                <?php if ($job['category']): ?>
                  <span class="badge job-type">
                    <?php echo htmlspecialchars($job['category']); ?>
                  </span>
                <?php endif; ?>
              </div>

              <button class="btn-apply">
                Ver Detalles
                <i class="fas fa-arrow-right"></i>
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

</body>
</html>
