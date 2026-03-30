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

/**
 * Decodifica TODAS las entidades HTML (nombradas y numéricas)
 */
function decodeAllEntities($text) {
    // Decodificar entidades nombradas y numéricas
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Decodificar entidades numéricas que quedaron (&#8211;, &#8217;, etc.)
    $text = preg_replace_callback('/&#(\d+);/', function($matches) {
        return mb_chr((int)$matches[1], 'UTF-8');
    }, $text);

    // Decodificar entidades hexadecimales (&#x2013;, etc.)
    $text = preg_replace_callback('/&#x([0-9a-f]+);/i', function($matches) {
        return mb_chr(hexdec($matches[1]), 'UTF-8');
    }, $text);

    return $text;
}

/**
 * Extrae un resumen limpio de la descripción para el listado
 * Elimina URLs, información redundante, y formatea para visualización
 */
function getDescriptionSummary($description, $maxLength = 250) {
    // Decodificar entidades
    $text = decodeAllEntities($description);

    // Remover URLs
    $text = preg_replace('/(https?:\/\/[^\s<>"\']+)/i', '', $text);

    // Remover markdown básico (**texto**)
    $text = preg_replace('/\*\*([^*]+)\*\*/i', '$1', $text);

    // Remover líneas que son solo etiquetas (Empresa:, Ubicación:, etc.)
    $lines = explode("\n", $text);
    $cleanLines = [];
    foreach ($lines as $line) {
        $line = trim($line);
        // Saltar líneas vacías o que son solo etiquetas
        if (empty($line) || preg_match('/^(Empresa:|Ubicación:|Location:|Company:)\s*$/i', $line)) {
            continue;
        }
        // Saltar líneas que empiezan con emoji de trabajo
        if (preg_match('/^[🧑‍💼💼📢🔎🔍👔💻🏢]\s*\|?\s*$/u', $line)) {
            continue;
        }
        $cleanLines[] = $line;
    }

    $text = implode(' ', $cleanLines);

    // Limpiar espacios múltiples
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    // Truncar si es muy largo
    if (mb_strlen($text) > $maxLength) {
        $text = mb_substr($text, 0, $maxLength);
        // Cortar en la última palabra completa
        $lastSpace = mb_strrpos($text, ' ');
        if ($lastSpace !== false) {
            $text = mb_substr($text, 0, $lastSpace);
        }
        $text .= '...';
    }

    return $text;
}

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

// Obtener empleos activos con filtros
$pdo = db();
$empleos = [];
$filters = [];
$params = [];

// Construir query con filtros
$baseQuery = "
    SELECT
        jl.*,
        u.company_name,
        u.company_logo
    FROM job_listings jl
    LEFT JOIN users u ON u.id = jl.employer_id
    WHERE jl.listing_type = 'job'
      AND jl.is_active = 1
      AND (u.status = 'active' OR jl.import_source IS NOT NULL)
";

// Filtro de búsqueda por texto
$search = trim($_GET['q'] ?? '');
if ($search) {
    $filters[] = "(jl.title LIKE ? OR jl.description LIKE ? OR jl.location LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Filtro por tipo de empleo
$jobType = $_GET['type'] ?? '';
if ($jobType && in_array($jobType, ['full-time', 'part-time', 'freelance', 'contract', 'internship'])) {
    $filters[] = "jl.job_type = ?";
    $params[] = $jobType;
}

// Filtro por modalidad (remoto/presencial/híbrida)
$remote = $_GET['remote'] ?? '';
if ($remote === 'yes') {
    // Remoto: puede ser remote_allowed=1 O job_type='remote'
    $filters[] = "(jl.remote_allowed = 1 OR jl.job_type = 'remote')";
} elseif ($remote === 'no') {
    // Presencial: puede ser remote_allowed=0 O job_type='onsite'
    $filters[] = "(jl.remote_allowed = 0 OR jl.job_type = 'onsite')";
} elseif ($remote === 'hybrid') {
    // Híbrida: solo job_type='hybrid'
    $filters[] = "jl.job_type = 'hybrid'";
}

// Filtro por categoría
$category = $_GET['category'] ?? '';
if ($category) {
    $filters[] = "jl.category = ?";
    $params[] = $category;
}

// Filtro por ubicación/país
$location = trim($_GET['location'] ?? '');
if ($location) {
    $filters[] = "jl.location LIKE ?";
    $params[] = '%' . $location . '%';
}

// Filtro por empresa
$company = trim($_GET['company'] ?? '');
if ($company) {
    $filters[] = "u.company_name = ?";
    $params[] = $company;
}

// Agregar filtros al query
if (!empty($filters)) {
    $baseQuery .= " AND " . implode(" AND ", $filters);
}

$baseQuery .= " ORDER BY jl.is_featured DESC, jl.created_at DESC";

try {
    $stmt = $pdo->prepare($baseQuery);
    $stmt->execute($params);
    $empleos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading jobs: " . $e->getMessage());
}

// Obtener datos únicos para los filtros
$locations = $pdo->query("SELECT DISTINCT location FROM job_listings WHERE location IS NOT NULL AND location != '' AND is_active = 1 ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);

// Obtener empresas únicas (de usuarios empleadores)
$companiesQuery = "
    SELECT DISTINCT u.company_name
    FROM job_listings jl
    LEFT JOIN users u ON u.id = jl.employer_id
    WHERE jl.listing_type = 'job'
    AND jl.is_active = 1
    AND u.company_name IS NOT NULL
    AND u.company_name != ''
    ORDER BY u.company_name
";
$companies = $pdo->query($companiesQuery)->fetchAll(PDO::FETCH_COLUMN);

// Obtener TODAS las categorías de empleos (incluso si no tienen empleos activos)
// Esto muestra el catálogo completo para que el usuario pueda explorar
$categoriesQuery = "
    SELECT DISTINCT name
    FROM job_categories
    WHERE name LIKE 'EMP:%'
    AND active = 1

    UNION

    SELECT DISTINCT category
    FROM job_listings
    WHERE category IS NOT NULL
    AND category != ''
    AND is_active = 1

    ORDER BY name
";
$categories = $pdo->query($categoriesQuery)->fetchAll(PDO::FETCH_COLUMN);

// Si no hay categorías, usar lista predefinida
if (empty($categories)) {
    $categories = [
        'EMP:Technology',
        'EMP:Administration',
        'EMP:Sales',
        'EMP:Health',
        'EMP:Education',
        'EMP:Construction',
        'EMP:Hospitality',
        'EMP:Transport',
        'EMP:Customer Service',
        'EMP:Legal'
    ];
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Empleos en Costa Rica y Otros Países | Bolsa de Trabajo - CompraTica</title>

  <!-- SEO Meta Tags -->
  <meta name="description" content="🌎 Bolsa de empleos en Costa Rica y otros países. Encuentra trabajos remotos, presenciales e híbridos en tecnología, finanzas, salud, educación y más. ¡Aplica ya!">
  <meta name="keywords" content="empleos costa rica, trabajo costa rica, ofertas empleo, bolsa trabajo cr, empleos san jose, empleos alajuela, empleos heredia, empleos remotos costa rica, trabajos tecnologia, empleos linkedin costa rica, stem jobs cr">
  <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
  <meta name="author" content="CompraTica">
  <link rel="canonical" href="https://compratica.com/empleos">

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://compratica.com/empleos">
  <meta property="og:title" content="Empleos en Costa Rica y Otros Países 🌎 | Bolsa de Trabajo - CompraTica">
  <meta property="og:description" content="Encuentra empleos en Costa Rica y otros países. Ofertas de trabajo remotas, presenciales e híbridas en tecnología, administración, ventas, salud y más.">
  <meta property="og:image" content="https://compratica.com/assets/img/og-empleos.jpg">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:locale" content="es_CR">
  <meta property="og:site_name" content="CompraTica">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:url" content="https://compratica.com/empleos">
  <meta name="twitter:title" content="Empleos en Costa Rica y Otros Países 🌎 | Bolsa de Trabajo">
  <meta name="twitter:description" content="Encuentra empleos en Costa Rica y otros países. Ofertas remotas, presenciales e híbridas en tecnología, administración, ventas y más. ¡Aplica ya!">
  <meta name="twitter:image" content="https://compratica.com/assets/img/og-empleos.jpg">

  <!-- Geo Tags -->
  <meta name="geo.region" content="CR">
  <meta name="geo.placename" content="Costa Rica">
  <meta name="geo.position" content="9.7489;-83.7534">
  <meta name="ICBM" content="9.7489, -83.7534">

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
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    html {
      -webkit-text-size-adjust: 100%;
      -ms-text-size-adjust: 100%;
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
      -webkit-tap-highlight-color: transparent;
      touch-action: manipulation;
      user-select: none;
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
      margin-bottom: 1.5rem;
      line-height: 1.7;
      opacity: 0.95;
    }

    /* ── Toggle Empleos / Servicios ── */
    .section-toggle {
      display: inline-flex;
      background: rgba(0,0,0,0.25);
      border-radius: 999px;
      padding: 5px;
      margin-bottom: 2rem;
      gap: 4px;
    }
    .section-toggle a {
      display: flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.5rem 1.4rem;
      border-radius: 999px;
      font-weight: 600;
      font-size: 0.95rem;
      text-decoration: none;
      transition: background 0.2s, color 0.2s;
      color: rgba(255,255,255,0.75);
    }
    .section-toggle a.active {
      background: #fff;
      color: #1a3a5c;
      box-shadow: 0 2px 8px rgba(0,0,0,0.18);
    }
    .section-toggle a:not(.active):hover {
      background: rgba(255,255,255,0.15);
      color: #fff;
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
      -webkit-line-clamp: 4;
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

    /* Filtros */
    .filters-section {
      background: var(--white);
      border-radius: var(--radius-lg);
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: var(--shadow);
    }

    .filters-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.25rem;
      align-items: end;
    }

    @media (max-width: 1024px) {
      .filters-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    .filter-item {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }

    .filter-item label {
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--gray-700);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .filter-item label i {
      color: var(--primary);
    }

    .filter-item input,
    .filter-item select {
      padding: 0.75rem 1rem;
      border: 2px solid var(--gray-200);
      border-radius: var(--radius);
      font-size: 0.95rem;
      transition: var(--transition);
      background: var(--white);
      color: var(--gray-900);
    }

    .filter-item input:focus,
    .filter-item select:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
    }

    .filter-actions {
      display: flex;
      gap: 0.75rem;
    }

    .btn-filter {
      flex: 1;
      padding: 0.75rem 1.5rem;
      border-radius: var(--radius);
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      border: none;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: var(--white);
    }

    .btn-filter:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .btn-clear {
      background: var(--gray-200);
      color: var(--gray-700);
      text-decoration: none;
    }

    .btn-clear:hover {
      background: var(--gray-300);
    }

    /* Botón de traducción */
    .btn-translate {
      padding: 0.5rem 1rem;
      background: var(--white);
      color: var(--primary);
      border: 2px solid var(--primary);
      border-radius: var(--radius);
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.875rem;
    }

    .btn-translate:hover {
      background: var(--primary);
      color: var(--white);
    }

    .btn-translate.translating {
      opacity: 0.6;
      pointer-events: none;
    }

    .btn-translate i.fa-spin {
      animation: fa-spin 1s linear infinite;
    }

    @keyframes fa-spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    /* Badge de idioma */
    .badge.language {
      background: var(--secondary);
      color: var(--white);
    }

    @media (max-width: 1024px) {
      .main-wrapper {
        padding: 2rem 1.5rem;
      }

      .hero-section {
        padding: 3rem 2rem;
      }

      .hero-title {
        font-size: 2.5rem;
      }

      .hero-stats {
        gap: 2rem;
      }
    }

    @media (max-width: 768px) {
      .header {
        padding: 1rem 1.25rem;
        flex-wrap: nowrap;
      }

      .logo .flag {
        font-size: 1.75rem;
      }

      .logo .text .main {
        font-size: 1.25rem;
      }

      .logo .text .sub {
        font-size: 0.6rem;
      }

      .header-nav {
        gap: 0.5rem;
      }

      .btn-icon {
        width: 2.5rem;
        height: 2.5rem;
        font-size: 1rem;
      }

      .main-wrapper {
        padding: 1.5rem 1rem;
      }

      .hero-section {
        padding: 2rem 1.5rem;
        border-radius: var(--radius-lg);
      }

      .hero-title {
        font-size: 2rem;
        margin-bottom: 1rem;
      }

      .hero-description {
        font-size: 1rem;
        margin-bottom: 2rem;
      }

      .hero-stats {
        gap: 1.5rem;
      }

      .stat-number {
        font-size: 2rem;
      }

      .stat-label {
        font-size: 0.85rem;
      }

      .filters-section {
        padding: 1.5rem 1rem;
        border-radius: var(--radius);
      }

      .filters-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
      }

      .filter-item label {
        font-size: 0.8125rem;
      }

      .filter-item input,
      .filter-item select {
        padding: 0.625rem 0.875rem;
        font-size: 0.875rem;
      }

      .filter-actions {
        grid-column: 1;
        flex-direction: row;
        gap: 0.5rem;
      }

      .btn-filter {
        flex: 1;
        font-size: 0.875rem;
        padding: 0.625rem 1rem;
      }

      .section-header {
        margin-bottom: 2rem;
      }

      .section-title {
        font-size: 1.75rem;
      }

      .section-subtitle {
        font-size: 1rem;
      }

      .jobs-grid {
        gap: 1.25rem;
      }

      .job-card {
        flex-direction: column;
        padding: 1.25rem;
        gap: 1rem;
      }

      .job-card:hover {
        transform: translateY(-2px);
      }

      .job-logo {
        width: 60px;
        height: 60px;
      }

      .job-title {
        font-size: 1.125rem;
        line-height: 1.3;
      }

      .job-company {
        font-size: 0.9rem;
        margin-bottom: 0.75rem;
      }

      .job-meta {
        flex-wrap: wrap;
        gap: 0.75rem;
        font-size: 0.85rem;
        margin-bottom: 0.875rem;
      }

      .job-description {
        font-size: 0.9rem;
        line-height: 1.5;
      }

      .job-footer {
        flex-direction: column;
        gap: 0.75rem;
        align-items: stretch;
        padding-top: 0.875rem;
      }

      .job-footer > div:first-child {
        width: 100%;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
      }

      .job-footer > div:last-child {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 0.625rem;
      }

      .btn-translate,
      .btn-apply {
        width: 100%;
        justify-content: center;
        font-size: 0.9rem;
        padding: 0.75rem 1rem;
      }

      .badge {
        font-size: 0.75rem;
        padding: 0.375rem 0.75rem;
        white-space: nowrap;
      }

      .cart-popover-actions {
        flex-direction: column;
      }

      .cart-popover-btn {
        width: 100%;
      }
    }

    @media (max-width: 480px) {
      .header {
        padding: 0.875rem 1rem;
      }

      .logo .flag {
        font-size: 1.5rem;
      }

      .logo .text .main {
        font-size: 1.125rem;
      }

      .logo .text .sub {
        display: none;
      }

      .btn-icon {
        width: 2.25rem;
        height: 2.25rem;
        font-size: 0.9rem;
      }

      .main-wrapper {
        padding: 1rem 0.75rem;
      }

      .hero-section {
        padding: 1.5rem 1.25rem;
        margin-bottom: 2rem;
      }

      .hero-title {
        font-size: 1.75rem;
        margin-bottom: 0.875rem;
      }

      .hero-description {
        font-size: 0.9375rem;
        margin-bottom: 1.5rem;
      }

      .hero-stats {
        flex-direction: column;
        gap: 1rem;
      }

      .stat-item {
        width: 100%;
        text-align: center;
      }

      .stat-number {
        font-size: 1.75rem;
      }

      .stat-label {
        font-size: 0.8125rem;
      }

      .filters-section {
        padding: 1rem 0.875rem;
      }

      .filter-item label {
        font-size: 0.75rem;
      }

      .filter-item input,
      .filter-item select {
        padding: 0.5rem 0.75rem;
        font-size: 0.8125rem;
      }

      .btn-filter {
        font-size: 0.8125rem;
        padding: 0.5rem 0.875rem;
      }

      .section-header {
        margin-bottom: 1.5rem;
      }

      .section-title {
        font-size: 1.5rem;
      }

      .section-subtitle {
        font-size: 0.9375rem;
      }

      .jobs-grid {
        gap: 1rem;
      }

      .job-card {
        padding: 1rem;
        gap: 0.875rem;
      }

      .job-logo {
        width: 50px;
        height: 50px;
      }

      .job-title {
        font-size: 1rem;
        line-height: 1.3;
      }

      .job-company {
        font-size: 0.8125rem;
        margin-bottom: 0.625rem;
      }

      .job-meta {
        gap: 0.625rem;
        font-size: 0.8125rem;
        margin-bottom: 0.75rem;
      }

      .job-meta-item {
        font-size: 0.8125rem;
      }

      .job-description {
        font-size: 0.875rem;
        margin-bottom: 0.75rem;
      }

      .job-footer {
        padding-top: 0.75rem;
        gap: 0.625rem;
      }

      .btn-translate,
      .btn-apply {
        font-size: 0.875rem;
        padding: 0.625rem 0.875rem;
      }

      .badge {
        font-size: 0.6875rem;
        padding: 0.3125rem 0.625rem;
      }

      .empty-state {
        padding: 3rem 1.5rem;
      }

      .empty-state i {
        font-size: 3rem;
      }

      .empty-state h3 {
        font-size: 1.125rem;
      }

      .empty-state p {
        font-size: 0.9375rem;
      }

      #cart-popover {
        width: calc(100vw - 1.5rem);
        right: -0.75rem;
      }

      #hamburger-menu {
        width: 280px;
        right: -280px;
      }

      .menu-header {
        padding: 1.25rem;
      }

      .menu-user-avatar {
        width: 40px;
        height: 40px;
        font-size: 1.125rem;
      }

      .menu-user-info h3 {
        font-size: 1rem;
      }

      .menu-user-info p {
        font-size: 0.8125rem;
      }

      .menu-item {
        padding: 0.75rem 1.25rem;
        font-size: 0.875rem;
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
      max-width: 100vw;
      height: 100vh;
      background: var(--white);
      box-shadow: -4px 0 20px rgba(0, 0, 0, 0.1);
      z-index: 1000;
      transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
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

    @media (max-width: 420px) {
      #cart-popover {
        position: fixed;
        top: auto;
        bottom: 0;
        left: 0;
        right: 0;
        width: 100%;
        max-width: 100%;
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        max-height: 70vh;
      }
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

  <!-- Schema.org JSON-LD para JobPosting -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "CompraTica",
    "url": "https://compratica.com",
    "potentialAction": {
      "@type": "SearchAction",
      "target": {
        "@type": "EntryPoint",
        "urlTemplate": "https://compratica.com/empleos?search={search_term_string}"
      },
      "query-input": "required name=search_term_string"
    }
  }
  </script>

  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "CollectionPage",
    "name": "Empleos en Costa Rica",
    "description": "Bolsa de empleos en Costa Rica. Encuentra trabajo en tecnología, finanzas, salud, educación y más áreas.",
    "url": "https://compratica.com/empleos",
    "inLanguage": "es-CR",
    "about": {
      "@type": "Thing",
      "name": "Empleos en Costa Rica"
    },
    "provider": {
      "@type": "Organization",
      "name": "CompraTica",
      "url": "https://compratica.com",
      "logo": {
        "@type": "ImageObject",
        "url": "https://compratica.com/assets/img/logo.png"
      }
    }
  }
  </script>
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
    <a href="servicios" class="btn-icon" title="Ver Servicios" aria-label="Ver servicios">
      <i class="fas fa-concierge-bell"></i>
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
    <?php else: ?>
      <a href="login" class="menu-item primary">
        <i class="fas fa-sign-in-alt"></i>
        <span>Iniciar Sesión</span>
      </a>
    <?php endif; ?>

    <!-- ── CLIENTES ─────────────────────────── -->
    <div style="display:flex;align-items:center;gap:8px;margin:14px 0 6px;padding:0 4px;">
      <div style="flex:1;height:1px;background:#e5e7eb;"></div>
      <span style="font-size:.8rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;white-space:nowrap;">
        <i class="fas fa-shopping-bag" style="color:#3b82f6;margin-right:4px;"></i>Para Clientes
      </span>
      <div style="flex:1;height:1px;background:#e5e7eb;"></div>
    </div>

    <a href="index" class="menu-item">
      <i class="fas fa-home"></i>
      <span>Inicio</span>
    </a>

    <a href="/servicios" class="menu-item">
      <i class="fas fa-concierge-bell"></i>
      <span>Empleos y Servicios</span>
    </a>

    <a href="venta-garaje" class="menu-item">
      <i class="fas fa-tags"></i>
      <span>Venta de Garaje</span>
    </a>

    <a href="bienes-raices" class="menu-item">
      <i class="fas fa-building"></i>
      <span>Bienes Raíces</span>
    </a>

    <a href="emprendedores-catalogo" class="menu-item">
      <i class="fas fa-store"></i>
      <span>Emprendedores</span>
    </a>

    <!-- ── VENDEDORES ────────────────────────── -->
    <div style="display:flex;align-items:center;gap:8px;margin:14px 0 6px;padding:0 4px;">
      <div style="flex:1;height:1px;background:#e5e7eb;"></div>
      <span style="font-size:.8rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;white-space:nowrap;">
        <i class="fas fa-store-alt" style="color:#10b981;margin-right:4px;"></i>Para Vendedores
      </span>
      <div style="flex:1;height:1px;background:#e5e7eb;"></div>
    </div>

    <a href="select-publication-type.php" class="menu-item">
      <i class="fas fa-bullhorn"></i>
      <span>Publicar mi venta</span>
    </a>

    <a href="affiliate/login.php" class="menu-item">
      <i class="fas fa-tags"></i>
      <span>Portal Venta Garaje</span>
    </a>

    <a href="/jobs_service/login.php" class="menu-item">
      <i class="fas fa-briefcase"></i>
      <span>Portal Empleos y Servicios</span>
    </a>

    <a href="/real-estate/login.php" class="menu-item">
      <i class="fas fa-home"></i>
      <span>Portal Bienes Raíces</span>
    </a>

    <a href="emprendedores-dashboard" class="menu-item">
      <i class="fas fa-store"></i>
      <span>Portal Emprendedoras/Emprendedores</span>
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
  <section class="hero-section">
    <div class="hero-content">
      <h1 class="hero-title">
        Empleos en Costa Rica y Otros Países <span class="emoji">💼</span>
      </h1>
      <p class="hero-description">
        Encuentra tu próximo empleo en las mejores empresas. Ofertas remotas, presenciales e híbridas
        en tecnología, administración, ventas, salud y mucho más.
        <br><strong>🔄 Actualizado 2 veces al día</strong> con las últimas oportunidades.
      </p>

      <div class="section-toggle">
        <a href="/empleos" class="active">💼 Empleos</a>
        <a href="/servicios">🔧 Servicios</a>
        <a href="/transporte">🚗 Transporte</a>
      </div>

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

  <!-- Filtros -->
  <div class="filters-section">
    <form method="GET" action="" id="filters-form">
      <div class="filters-grid">
        <!-- Búsqueda -->
        <div class="filter-item">
          <label><i class="fas fa-search"></i> Buscar</label>
          <input type="text" name="q" placeholder="Cargo, empresa, palabra clave..." value="<?php echo htmlspecialchars($search); ?>">
        </div>

        <!-- Tipo de empleo -->
        <div class="filter-item">
          <label><i class="fas fa-briefcase"></i> Tipo</label>
          <select name="type">
            <option value="">Todos</option>
            <option value="full-time" <?php echo $jobType === 'full-time' ? 'selected' : ''; ?>>Tiempo Completo</option>
            <option value="part-time" <?php echo $jobType === 'part-time' ? 'selected' : ''; ?>>Medio Tiempo</option>
            <option value="freelance" <?php echo $jobType === 'freelance' ? 'selected' : ''; ?>>Freelance</option>
            <option value="contract" <?php echo $jobType === 'contract' ? 'selected' : ''; ?>>Contrato</option>
            <option value="internship" <?php echo $jobType === 'internship' ? 'selected' : ''; ?>>Pasantía</option>
          </select>
        </div>

        <!-- Modalidad -->
        <div class="filter-item">
          <label><i class="fas fa-laptop-house"></i> Modalidad</label>
          <select name="remote">
            <option value="">Todas</option>
            <option value="yes" <?php echo $remote === 'yes' ? 'selected' : ''; ?>>Remoto</option>
            <option value="no" <?php echo $remote === 'no' ? 'selected' : ''; ?>>Presencial</option>
            <option value="hybrid" <?php echo $remote === 'hybrid' ? 'selected' : ''; ?>>Híbrida</option>
          </select>
        </div>

        <!-- Ubicación -->
        <div class="filter-item">
          <label><i class="fas fa-map-marker-alt"></i> Ubicación</label>
          <select name="location">
            <option value="">Todas</option>
            <?php foreach ($locations as $loc): ?>
              <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $location === $loc ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($loc); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Empresa -->
        <div class="filter-item">
          <label><i class="fas fa-building"></i> Empresa</label>
          <select name="company">
            <option value="">Todas</option>
            <?php foreach ($companies as $comp): ?>
              <option value="<?php echo htmlspecialchars($comp); ?>" <?php echo $company === $comp ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($comp); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Categoría -->
        <div class="filter-item">
          <label><i class="fas fa-tags"></i> Categoría</label>
          <select name="category">
            <option value="">Todas</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars(str_replace('EMP:', '', $cat)); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Botones -->
        <div class="filter-item filter-actions">
          <button type="submit" class="btn-filter">
            <i class="fas fa-filter"></i> Filtrar
          </button>
          <a href="empleos.php" class="btn-filter btn-clear">
            <i class="fas fa-times"></i> Limpiar
          </a>
        </div>
      </div>
    </form>
  </div>

  <div class="section-header">
    <h2 class="section-title">
      <?php echo count($empleos); ?> Empleos Encontrados
    </h2>
    <p class="section-subtitle">
      <?php if ($search || $jobType || $remote || $location || $category): ?>
        Mostrando resultados filtrados
      <?php else: ?>
        Encuentra el trabajo perfecto para ti
      <?php endif; ?>
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
        <div class="job-card" onclick="window.location.href='<?php echo clean_url_publicacion((int)$job['id'], $job['title'] ?? ''); ?>'">
          <?php if ($job['company_logo']): ?>
            <img src="<?php echo htmlspecialchars($job['company_logo']); ?>" alt="Logo" class="job-logo">
          <?php else: ?>
            <div class="job-logo" style="display: flex; align-items: center; justify-content: center; font-size: 2rem; color: var(--gray-400);">
              <i class="fas fa-building"></i>
            </div>
          <?php endif; ?>

          <div class="job-content">
            <h3 class="job-title"><?php echo htmlspecialchars(decodeAllEntities($job['title'])); ?></h3>

            <div class="job-company">
              <i class="fas fa-building"></i>
              <?php echo htmlspecialchars(decodeAllEntities($job['company_name'] ?? 'Empresa')); ?>
            </div>

            <div class="job-meta">
              <?php if ($job['location']): ?>
                <div class="job-meta-item">
                  <i class="fas fa-map-marker-alt"></i>
                  <?php echo htmlspecialchars($job['location']); ?>
                </div>
              <?php endif; ?>

              <?php if ($job['start_date']): ?>
                <div class="job-meta-item">
                  <i class="fas fa-calendar"></i>
                  <?php
                  $publishDate = new DateTime($job['start_date']);
                  $now = new DateTime();
                  $diff = $now->diff($publishDate);

                  if ($diff->days == 0) {
                    echo 'Hoy';
                  } elseif ($diff->days == 1) {
                    echo 'Hace 1 día';
                  } elseif ($diff->days < 7) {
                    echo 'Hace ' . $diff->days . ' días';
                  } elseif ($diff->days < 30) {
                    $weeks = floor($diff->days / 7);
                    echo 'Hace ' . $weeks . ($weeks == 1 ? ' semana' : ' semanas');
                  } else {
                    echo $publishDate->format('d/m/Y');
                  }
                  ?>
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

              <?php
              // Mostrar modalidad de trabajo
              $workModality = null;
              $workModalityIcon = 'fa-building';

              if ($job['job_type'] === 'remote') {
                $workModality = 'Remoto';
                $workModalityIcon = 'fa-home';
              } elseif ($job['job_type'] === 'hybrid') {
                $workModality = 'Híbrida';
                $workModalityIcon = 'fa-laptop-house';
              } elseif ($job['job_type'] === 'onsite') {
                $workModality = 'Presencial';
                $workModalityIcon = 'fa-building';
              } elseif ($job['remote_allowed']) {
                $workModality = 'Remoto';
                $workModalityIcon = 'fa-home';
              }
              ?>

              <?php if ($workModality): ?>
                <div class="job-meta-item">
                  <i class="fas <?php echo $workModalityIcon; ?>"></i>
                  <?php echo $workModality; ?>
                </div>
              <?php endif; ?>
            </div>

            <?php if ($job['description']): ?>
              <div class="job-description">
                <?php echo htmlspecialchars(getDescriptionSummary($job['description'], 280)); ?>
              </div>
            <?php endif; ?>

            <div class="job-footer">
              <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                <?php if ($job['is_featured']): ?>
                  <span class="badge featured">
                    <i class="fas fa-star"></i>
                    Destacado
                  </span>
                <?php endif; ?>

                <?php if ($job['category']): ?>
                  <span class="badge job-type">
                    <?php echo htmlspecialchars(str_replace('EMP:', '', $job['category'])); ?>
                  </span>
                <?php endif; ?>

                <?php if ($job['import_source']): ?>
                  <span class="badge language" title="Empleo internacional - Haz clic en Traducir">
                    <i class="fas fa-globe"></i>
                    <?php echo $job['remote_allowed'] ? 'Remoto Internacional' : 'Internacional'; ?>
                  </span>
                <?php endif; ?>
              </div>

              <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <?php if ($job['import_source']): ?>
                  <button class="btn-translate" onclick="event.stopPropagation(); translateJob(<?php echo $job['id']; ?>, this);">
                    <i class="fas fa-language"></i>
                    Traducir
                  </button>
                <?php endif; ?>

                <button class="btn-apply" onclick="event.stopPropagation(); window.location.href='<?php echo clean_url_publicacion((int)$job['id'], $job['title'] ?? ''); ?>';">
                  Ver Detalles
                  <i class="fas fa-arrow-right"></i>
                </button>
              </div>
            </div>
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

// Sistema de traducción automática
const translations = new Map();

async function translateJob(jobId, button) {
  const card = button.closest('.job-card');
  const titleEl = card.querySelector('.job-title');
  const descEl = card.querySelector('.job-description');
  const companyEl = card.querySelector('.job-company');

  // Si ya está traducido, volver al original
  if (translations.has(jobId)) {
    const original = translations.get(jobId);
    titleEl.textContent = original.title;
    if (descEl) descEl.textContent = original.description;
    button.innerHTML = '<i class="fas fa-language"></i> Traducir';
    translations.delete(jobId);
    return;
  }

  // Guardar textos originales
  const original = {
    title: titleEl.textContent,
    description: descEl ? descEl.textContent : '',
    company: companyEl ? companyEl.textContent.trim() : ''
  };

  // Mostrar estado de carga
  button.classList.add('translating');
  button.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Traduciendo...';

  try {
    // Traducir título (detección automática de idioma)
    const titleRes = await fetch('/api/translate.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: `text=${encodeURIComponent(original.title)}&from=auto&to=es`
    });
    const titleData = await titleRes.json();

    // Traducir descripción si existe (detección automática de idioma)
    let descData = null;
    if (original.description) {
      const descRes = await fetch('/api/translate.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `text=${encodeURIComponent(original.description)}&from=auto&to=es`
      });
      descData = await descRes.json();
    }

    // Aplicar traducciones (normalizar espacios y saltos de línea)
    if (titleData.translated) {
      // Normalizar el título: eliminar saltos de línea y espacios múltiples
      const normalizedTitle = titleData.translated.replace(/\s+/g, ' ').trim();
      titleEl.textContent = normalizedTitle;
    }
    if (descData && descData.translated && descEl) {
      // Normalizar la descripción: eliminar saltos de línea y espacios múltiples
      const normalizedDesc = descData.translated.replace(/\s+/g, ' ').trim();
      descEl.textContent = normalizedDesc;
    }

    // Guardar para poder revertir
    translations.set(jobId, original);

    // Cambiar botón
    button.innerHTML = '<i class="fas fa-undo"></i> Ver Original';
    button.classList.remove('translating');

  } catch (error) {
    console.error('Error traduciendo:', error);
    alert('Error al traducir. Por favor intenta de nuevo.');
    button.classList.remove('translating');
    button.innerHTML = '<i class="fas fa-language"></i> Traducir';
  }
}

// Auto-submit de filtros al cambiar
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('filters-form');
  if (!form) return;

  const selects = form.querySelectorAll('select');
  selects.forEach(select => {
    select.addEventListener('change', () => {
      form.submit();
    });
  });

  // Enter en búsqueda
  const searchInput = form.querySelector('input[name="q"]');
  if (searchInput) {
    searchInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        form.submit();
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/chat-support.php'; ?>
</body>
</html>
