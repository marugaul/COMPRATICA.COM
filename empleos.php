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

// Filtro por modalidad (remoto/presencial)
$remote = $_GET['remote'] ?? '';
if ($remote === 'yes') {
    $filters[] = "jl.remote_allowed = 1";
} elseif ($remote === 'no') {
    $filters[] = "jl.remote_allowed = 0";
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
  <title>Empleos en Costa Rica | Bolsa de Trabajo - CompraTica</title>

  <!-- SEO Meta Tags -->
  <meta name="description" content="🇨🇷 Bolsa de empleos en Costa Rica. Encuentra trabajos en tecnología, finanzas, salud, educación y más. Empleos remotos y presenciales actualizados diariamente. ¡Aplica ya!">
  <meta name="keywords" content="empleos costa rica, trabajo costa rica, ofertas empleo, bolsa trabajo cr, empleos san jose, empleos alajuela, empleos heredia, empleos remotos costa rica, trabajos tecnologia, empleos linkedin costa rica, stem jobs cr">
  <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
  <meta name="author" content="CompraTica">
  <link rel="canonical" href="https://compratica.com/empleos.php">

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://compratica.com/empleos.php">
  <meta property="og:title" content="Empleos en Costa Rica 🇨🇷 | Bolsa de Trabajo - CompraTica">
  <meta property="og:description" content="Encuentra empleos en Costa Rica. Ofertas de trabajo actualizadas en tecnología, administración, ventas, salud y más. Empleos remotos y presenciales.">
  <meta property="og:image" content="https://compratica.com/assets/img/og-empleos.jpg">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:locale" content="es_CR">
  <meta property="og:site_name" content="CompraTica">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:url" content="https://compratica.com/empleos.php">
  <meta name="twitter:title" content="Empleos en Costa Rica 🇨🇷 | Bolsa de Trabajo">
  <meta name="twitter:description" content="Encuentra empleos en Costa Rica. Ofertas actualizadas en tecnología, administración, ventas y más. ¡Aplica ya!">
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
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.25rem;
      align-items: end;
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

    @media (max-width: 768px) {
      .header {
        padding: 1rem 1.25rem;
      }

      .logo .text .main {
        font-size: 1.25rem;
      }

      .logo .text .sub {
        font-size: 0.6rem;
      }

      .main-wrapper {
        padding: 1.5rem 1rem;
      }

      .hero-section {
        padding: 2rem 1.5rem;
      }

      .hero-title {
        font-size: 2rem;
      }

      .hero-description {
        font-size: 1rem;
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
      }

      .filters-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
      }

      .filter-actions {
        grid-column: 1;
        flex-direction: row;
        gap: 0.5rem;
      }

      .btn-filter {
        flex: 1;
        font-size: 0.85rem;
        padding: 0.625rem 1rem;
      }

      .section-title {
        font-size: 1.75rem;
      }

      .section-subtitle {
        font-size: 1rem;
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
        width: 50px;
        height: 50px;
      }

      .job-title {
        font-size: 1.125rem;
      }

      .job-company {
        font-size: 0.9rem;
      }

      .job-meta {
        flex-direction: column;
        gap: 0.5rem;
        font-size: 0.85rem;
      }

      .job-description {
        font-size: 0.9rem;
      }

      .job-footer {
        flex-direction: column;
        gap: 0.75rem;
        align-items: stretch;
      }

      .job-footer > div:first-child {
        width: 100%;
      }

      .job-footer > div:last-child {
        width: 100%;
        flex-direction: column;
      }

      .btn-translate,
      .btn-apply {
        width: 100%;
        justify-content: center;
        font-size: 0.9rem;
        padding: 0.625rem 1rem;
      }

      .badge {
        font-size: 0.75rem;
        padding: 0.375rem 0.75rem;
      }
    }

    @media (max-width: 480px) {
      .logo .flag {
        font-size: 1.5rem;
      }

      .logo .text .main {
        font-size: 1.125rem;
      }

      .hero-title {
        font-size: 1.75rem;
      }

      .hero-stats {
        flex-direction: column;
        gap: 1rem;
      }

      .stat-item {
        width: 100%;
        text-align: center;
      }

      .filters-section {
        padding: 1rem;
      }

      .job-card {
        padding: 1rem;
      }

      .job-title {
        font-size: 1rem;
      }
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
        "urlTemplate": "https://compratica.com/empleos.php?search={search_term_string}"
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
    "url": "https://compratica.com/empleos.php",
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
        <div class="job-card" onclick="window.location.href='publicacion-detalle.php?id=<?php echo $job['id']; ?>'">
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

                <button class="btn-apply" onclick="event.stopPropagation(); window.location.href='publicacion-detalle.php?id=<?php echo $job['id']; ?>';">
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
    // Traducir título
    const titleRes = await fetch('/api/translate.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: `text=${encodeURIComponent(original.title)}&from=en&to=es`
    });
    const titleData = await titleRes.json();

    // Traducir descripción si existe
    let descData = null;
    if (original.description) {
      const descRes = await fetch('/api/translate.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `text=${encodeURIComponent(original.description)}&from=en&to=es`
      });
      descData = await descRes.json();
    }

    // Aplicar traducciones
    if (titleData.translated) {
      titleEl.textContent = titleData.translated;
    }
    if (descData && descData.translated && descEl) {
      descEl.textContent = descData.translated;
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

</body>
</html>
