<?php
/**
 * ====
 * STORE.PHP - Página de Tienda
 * Diseño elegante coherente con index.php
 * ====
 */

// CONFIGURACIÓN INICIAL
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

// SISTEMA DE LOGGING
$logFile = __DIR__ . '/logs/store_debug.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

function logStore($label, $data = null) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$label}";
    if ($data !== null) {
    $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $line .= ' | ' . $jsonData;
    }
    $line .= PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND);
}

logStore('STORE_START', [
    'sale_id' => $_GET['sale_id'] ?? 'none',
    'url' => $_SERVER['REQUEST_URI'] ?? ''
]);

// CONFIGURACIÓN DE SESIONES
$sessionPath = __DIR__ . '/sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0700, true);
}

if (is_dir($sessionPath) && is_writable($sessionPath)) {
    ini_set('session.save_path', $sessionPath);
}

logStore('SESSION_PATH_SET', ['path' => $sessionPath]);

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PHPSESSID');
    
    if (PHP_VERSION_ID < 70300) {
    ini_set('session.cookie_lifetime', '0');
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_domain', '');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_set_cookie_params(0, '/', '', $isHttps, true);
    } else {
    session_set_cookie_params([
    'lifetime' => 0,
    'path'    => '/',
    'domain'   => '',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
    ]);
    }
    
    ini_set('session.use_strict_mode', '0');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '86400');
    
    session_start();
}

logStore('SESSION_STARTED', ['sid' => session_id()]);

// HEADERS Y SEGURIDAD
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMESITE');
}
ini_set('default_charset', 'UTF-8');

// GESTIÓN DE CSRF TOKEN
$csrf = $_COOKIE['vg_csrf'] ?? '';

if (!preg_match('/^[a-f0-9]{32,128}$/i', $csrf)) {
    $csrf = bin2hex(random_bytes(32));
}

if (PHP_VERSION_ID < 70300) {
    setcookie('vg_csrf', $csrf, time() + 7200, '/', '', $isHttps, false);
} else {
    setcookie('vg_csrf', $csrf, [
    'expires'  => time() + 7200,
    'path'    => '/',
    'domain'   => '',
    'secure'   => $isHttps,
    'httponly' => false,
    'samesite' => 'Lax',
    ]);
}

logStore('CSRF_SET', ['csrf' => substr($csrf, 0, 16) . '...']);

// CONEXIÓN A BASE DE DATOS
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/live_embed.php';
require_once __DIR__ . '/includes/aff_chat_helpers.php';

logStore('CONFIG_LOADED');

$pdo = db();
initAffChatTables($pdo);
logStore('PDO_CONNECTED');

// VALIDACIÓN Y CARGA DE DATOS
$sale_id = filter_input(INPUT_GET, 'sale_id', FILTER_VALIDATE_INT);
logStore('SALE_ID_PARSED', ['sale_id' => $sale_id]);

if (!$sale_id || $sale_id <= 0) {
    logStore('ERROR_NO_SALE_ID');
    http_response_code(400);
    echo 'Falta sale_id válido';
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.*, a.name AS affiliate_name, a.phone AS affiliate_phone,
           COALESCE(a.is_live,0)          AS aff_is_live,
           a.live_title                   AS aff_live_title,
           a.live_link                    AS aff_live_link,
           COALESCE(a.live_type,'link')   AS aff_live_type,
           a.live_session_id              AS aff_live_session_id,
           COALESCE(s.chat_active,0)      AS chat_active
    FROM sales s
    JOIN affiliates a ON a.id = s.affiliate_id
    WHERE s.id = ? AND s.is_active = 1
    LIMIT 1
");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

logStore('SALE_QUERY_EXECUTED', ['found' => $sale ? 'yes' : 'no']);

if (!$sale) {
    logStore('ERROR_SALE_NOT_FOUND', ['sale_id' => $sale_id]);
    // Verificar si existe pero está inactivo
    $checkStmt = $pdo->prepare("SELECT title, is_active FROM sales WHERE id = ? LIMIT 1");
    $checkStmt->execute([$sale_id]);
    $inactiveSale = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if ($inactiveSale && !$inactiveSale['is_active']) {
        // Existe pero está inactivo: mostrar página de "Venta finalizada"
        http_response_code(404);
        ?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <base href="/">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Espacio no disponible – CompraTica</title>
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:system-ui,sans-serif;background:#f0f2f5;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2rem;text-align:center}
    .card{background:#fff;border-radius:16px;padding:3rem 2.5rem;max-width:460px;width:100%;box-shadow:0 8px 32px rgba(0,0,0,.08)}
    .icon{font-size:3.5rem;margin-bottom:1.25rem;color:#cbd5e0}
    h1{font-size:1.5rem;font-weight:700;color:#2d3748;margin-bottom:.75rem}
    p{color:#718096;font-size:1rem;line-height:1.6;margin-bottom:1.75rem}
    .badge{display:inline-flex;align-items:center;gap:.5rem;background:#fff7ed;color:#c05621;border:1px solid #fbd38d;border-radius:999px;padding:.4rem 1rem;font-size:.85rem;font-weight:600;margin-bottom:1.5rem}
    .btn{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.75rem;background:#2c3e50;color:#fff;text-decoration:none;border-radius:999px;font-weight:600;font-size:.95rem;transition:background .2s}
    .btn:hover{background:#34495e}
  </style>
</head>
<body>
  <div class="card">
    <div class="icon"><i class="fas fa-store-slash"></i></div>
    <span class="badge"><i class="fas fa-clock"></i> Venta finalizada</span>
    <h1><?= htmlspecialchars($inactiveSale['title'], ENT_QUOTES) ?></h1>
    <p>Esta venta de garaje ya no está activa. Podés explorar otras ventas disponibles en CompraTica.</p>
    <a href="/venta-garaje" class="btn"><i class="fas fa-store"></i> Ver ventas activas</a>
  </div>
</body>
</html><?php
        exit;
    }

    // No existe: redirigir al catálogo
    header('Location: /venta-garaje', true, 302);
    exit;
}

// Redirigir URL antigua (?sale_id=) a URL limpia (/tienda/id-slug)
$cleanUrl = 'https://compratica.com' . clean_url_tienda((int)$sale_id, $sale['title'] ?? '');
if (!str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/tienda/')) {
    header('Location: ' . $cleanUrl, true, 301);
    exit;
}

logStore('SALE_LOADED', [
    'sale_id' => $sale['id'],
    'title' => $sale['title']
]);

// 🔒 VALIDACIÓN DE ESPACIO PRIVADO
$accessGranted = true;
$accessError = '';

if (!empty($sale['is_private'])) {
    logStore('PRIVATE_SPACE_DETECTED', ['sale_id' => $sale_id]);

    // Inicializar array de accesos en sesión si no existe
    if (!isset($_SESSION['private_sales_access'])) {
        $_SESSION['private_sales_access'] = [];
    }

    // Procesar envío de código
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['access_code'])) {
        $submittedCode = trim($_POST['access_code'] ?? '');
        logStore('ACCESS_CODE_SUBMITTED', ['code_length' => strlen($submittedCode)]);

        if ($submittedCode === $sale['access_code']) {
            // Código correcto - guardar en sesión
            $_SESSION['private_sales_access'][$sale_id] = true;
            logStore('ACCESS_GRANTED', ['sale_id' => $sale_id]);
            $accessGranted = true;
        } else {
            // Código incorrecto
            logStore('ACCESS_DENIED', ['sale_id' => $sale_id]);
            $accessError = 'Código incorrecto. Por favor, verifica e intenta nuevamente.';
            $accessGranted = false;
        }
    } else {
        // Verificar si ya tiene acceso en sesión
        $accessGranted = !empty($_SESSION['private_sales_access'][$sale_id]);
        logStore('ACCESS_CHECK', ['has_access' => $accessGranted]);
    }

    // Si no tiene acceso, mostrar formulario y detener
    if (!$accessGranted) {
        logStore('SHOWING_ACCESS_FORM');
        require __DIR__ . '/views/access_form.php';
        exit;
    }
}

$productsStmt = $pdo->prepare("
    SELECT p.id, p.name, p.price, p.currency,
    p.image, p.image2, p.description, p.stock
    FROM products p
    WHERE p.sale_id = ?
    ORDER BY p.id DESC
");
$productsStmt->execute([$sale_id]);
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

logStore('PRODUCTS_LOADED', ['count' => count($products)]);

// FUNCIONES AUXILIARES
function getProductImage(array $product): string {
    $image = $product['image'] ?? null;
    $image2 = $product['image2'] ?? null;
    
    if ($image) {
    return '/uploads/' . ltrim($image, '/');
    }

    if ($image2) {
    return '/uploads/' . ltrim($image2, '/');
    }
    
    return 'assets/placeholder.jpg';
}

function h(?string $string): string {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

function formatPrice(float $price, string $currency): string {
    $currency = strtoupper($currency);
    
    if ($currency === 'USD') {
    return '$' . number_format($price, 2);
    }
    
    return '₡' . number_format($price, 0);
}

logStore('RENDERING_HTML');

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
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <base href="/">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($sale['title']) ?> - <?= h($APP_NAME) ?></title>
  <meta name="description" content="Venta de garaje de <?= h($sale['affiliate_name']) ?> en CompraTica. Encuentra productos usados y nuevos con pago por SINPE en Costa Rica.">
  <meta name="robots" content="index, follow, max-image-preview:large">
  <link rel="canonical" href="<?= htmlspecialchars($cleanUrl, ENT_QUOTES) ?>">

  <!-- Open Graph -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= htmlspecialchars($cleanUrl, ENT_QUOTES) ?>">
  <meta property="og:title" content="<?= h($sale['title']) ?> — CompraTica Venta de Garaje">
  <meta property="og:description" content="Venta de garaje de <?= h($sale['affiliate_name']) ?> en CompraTica. Compra con SINPE desde Costa Rica.">
  <meta property="og:image" content="https://compratica.com/assets/img/og-venta-garaje.jpg">
  <meta property="og:locale" content="es_CR">
  <meta property="og:site_name" content="CompraTica">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:url" content="<?= htmlspecialchars($cleanUrl, ENT_QUOTES) ?>">
  <meta name="twitter:title" content="<?= h($sale['title']) ?> — CompraTica Venta de Garaje">
  <meta name="twitter:description" content="Venta de garaje de <?= h($sale['affiliate_name']) ?> en CompraTica. Compra con SINPE desde Costa Rica.">
  <meta name="twitter:image" content="https://compratica.com/assets/img/og-venta-garaje.jpg">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    :root {
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
    --bg-primary: #f0f2f5;
    --shadow-sm: 0 1px 3px 0 rgba(0,0,0,.06);
    --shadow-md: 0 4px 12px rgba(0,0,0,.08);
    --shadow-lg: 0 12px 24px rgba(0,0,0,.10);
    --transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
    --radius: 10px;
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

    /* HERO SECTION */
    .hero {
    background: linear-gradient(135deg, #1a2a3a 0%, #2c3e50 60%, #3d5a70 100%);
    color: var(--white);
    padding: 2.75rem 2rem 2.5rem;
    text-align: center;
    position: relative;
    overflow: hidden;
    }
    .hero::before {
    content:'';
    position:absolute;
    inset:0;
    background: radial-gradient(ellipse at 70% 50%, rgba(52,152,219,.18) 0%, transparent 65%);
    pointer-events:none;
    }

    .hero-title {
    font-size: 2.25rem;
    font-weight: 800;
    margin-bottom: 0.4rem;
    letter-spacing: -0.025em;
    line-height: 1.2;
    }

    .hero-seller {
    font-size: 1rem;
    opacity: 0.75;
    margin-bottom: 1.25rem;
    font-weight: 500;
    letter-spacing: .01em;
    }

    .hero-actions {
    display: flex;
    justify-content: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
    }

    .hero-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1.25rem;
    border-radius: 999px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    }
    .hero-btn.whatsapp { background: #25D366; color: #fff; }
    .hero-btn.whatsapp:hover { background: #1da952; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37,211,102,.35); }
    .hero-btn.outline { background: rgba(255,255,255,.12); color: #fff; border: 1.5px solid rgba(255,255,255,.35); }
    .hero-btn.outline:hover { background: rgba(255,255,255,.22); transform: translateY(-2px); }

    .hero-info {
    display: flex;
    justify-content: center;
    gap: 1.5rem;
    flex-wrap: wrap;
    }

    .hero-info-item {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.9rem;
    opacity: 0.85;
    background: rgba(255,255,255,.08);
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,.12);
    }

    /* SEARCH + SORT BAR */
    .products-controls {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
    }

    .search-wrap {
    flex: 1;
    min-width: 180px;
    position: relative;
    }
    .search-wrap i {
    position: absolute;
    left: 0.9rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-500);
    font-size: 0.95rem;
    pointer-events: none;
    }
    .search-input {
    width: 100%;
    padding: 0.65rem 1rem 0.65rem 2.5rem;
    border: 1.5px solid var(--gray-300);
    border-radius: var(--radius);
    font-size: 0.95rem;
    background: var(--white);
    color: var(--gray-900);
    outline: none;
    transition: var(--transition);
    font-family: inherit;
    }
    .search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(52,152,219,.12); }

    .sort-select {
    padding: 0.65rem 2rem 0.65rem 0.9rem;
    border: 1.5px solid var(--gray-300);
    border-radius: var(--radius);
    font-size: 0.9rem;
    background: var(--white);
    color: var(--gray-700);
    outline: none;
    cursor: pointer;
    font-family: inherit;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23718096' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.7rem center;
    transition: var(--transition);
    }
    .sort-select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(52,152,219,.12); }

    .products-count-label {
    font-size: 0.9rem;
    color: var(--gray-500);
    white-space: nowrap;
    }

    /* CONTAINER */
    .container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
    }

    /* PRODUCTS GRID */
    .products-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
    }

    .products-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--primary);
    }

    .products-count {
    color: var(--gray-500);
    font-size: 1rem;
    }

    .products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 1.25rem;
    }

    .product-card {
    background: var(--white);
    border: 1px solid #e2e8f0;
    border-radius: var(--radius);
    overflow: hidden;
    transition: var(--transition);
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-sm);
    }

    .product-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
    border-color: #cbd5e0;
    }

    .product-card[data-hidden="true"] { display: none; }

    /* Categoría badge */
    .product-category-badge {
    display: inline-block;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--accent);
    background: rgba(52,152,219,.1);
    border-radius: 4px;
    padding: 2px 7px;
    margin-bottom: 0.4rem;
    }

    /* GALERÍA ESTILO EBAY */
    .product-image-gallery {
    display: flex;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--gray-100);
    }

    .gallery-thumbnails {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    }

    .gallery-thumb {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border: 2px solid var(--gray-300);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    background: var(--white);
    }

    .gallery-thumb:hover {
    border-color: var(--accent);
    transform: scale(1.05);
    }

    .gallery-thumb.active {
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
    }

    .gallery-main-wrapper {
    flex: 1;
    position: relative;
    background: var(--white);
    border-radius: 4px;
    overflow: hidden;
    min-height: 240px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: zoom-in;
    }

    .product-image {
    width: 100%;
    height: 240px;
    object-fit: contain;
    background: var(--white);
    transition: transform 0.2s;
    }

    .gallery-main-wrapper:hover .product-image {
    transform: scale(1.02);
    }

    .zoom-icon {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    background: rgba(0, 0, 0, 0.6);
    color: var(--white);
    padding: 0.5rem 0.75rem;
    border-radius: 4px;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.2s;
    }

    .gallery-main-wrapper:hover .zoom-icon {
    opacity: 1;
    }

    /* LIGHTBOX/MODAL ZOOM */
    .lightbox {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.95);
    z-index: 9999;
    }

    .lightbox.active {
    display: flex;
    align-items: center;
    justify-content: center;
    }

    .lightbox-content {
    position: relative;
    max-width: 90%;
    max-height: 90%;
    display: flex;
    gap: 1rem;
    }

    .lightbox-thumbnails {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    }

    .lightbox-thumb {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    background: var(--white);
    }

    .lightbox-thumb:hover {
    border-color: rgba(255, 255, 255, 0.8);
    transform: scale(1.05);
    }

    .lightbox-thumb.active {
    border-color: var(--white);
    box-shadow: 0 0 20px rgba(255, 255, 255, 0.5);
    }

    .lightbox-main {
    position: relative;
    max-width: 800px;
    max-height: 90vh;
    overflow: hidden;
    }

    .lightbox-image {
    max-width: 100%;
    max-height: 90vh;
    object-fit: contain;
    cursor: zoom-in;
    transition: transform 0.3s ease;
    }

    .lightbox-image.zoomed {
    cursor: zoom-out;
    transform: scale(2);
    }

    .lightbox-close {
    position: absolute;
    top: 1.5rem;
    right: 1.5rem;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--gray-900);
    transition: all 0.2s;
    z-index: 10000;
    }

    .lightbox-close:hover {
    background: var(--white);
    transform: scale(1.1);
    }

    .lightbox-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: var(--gray-900);
    transition: all 0.2s;
    }

    .lightbox-nav:hover {
    background: var(--white);
    transform: translateY(-50%) scale(1.1);
    }

    .lightbox-nav.prev {
    left: 1rem;
    }

    .lightbox-nav.next {
    right: 1rem;
    }

    @media (max-width: 768px) {
    .product-image-gallery {
    flex-direction: column-reverse;
    }

    .gallery-thumbnails {
    flex-direction: row;
    overflow-x: auto;
    }

    .lightbox-content {
    flex-direction: column-reverse;
    }

    .lightbox-thumbnails {
    flex-direction: row;
    overflow-x: auto;
    max-width: 90vw;
    }
    }

    .product-body {
    padding: 1.25rem;
    flex: 1;
    display: flex;
    flex-direction: column;
    }

    .product-name {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--gray-900);
    margin-bottom: 0.5rem;
    line-height: 1.4;
    }

    .product-description {
    color: var(--gray-600);
    font-size: 0.9rem;
    margin-bottom: 1rem;
    flex: 1;
    line-height: 1.5;
    }

    .product-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-top: auto;
    }

    /* Botones de compartir producto */
    .share-buttons-product {
      display: flex;
      gap: 0.375rem;
      flex-shrink: 0;
    }

    .share-btn-product {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      transition: all 0.3s ease;
      font-size: 0.75rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .share-btn-product:hover {
      transform: translateY(-2px) scale(1.1);
      box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
    }

    .share-btn-product.whatsapp {
      background: linear-gradient(135deg, #25D366, #128C7E);
      color: white;
    }

    .share-btn-product.whatsapp:hover {
      background: linear-gradient(135deg, #128C7E, #075E54);
    }

    .share-btn-product.facebook {
      background: linear-gradient(135deg, #1877F2, #1864CC);
      color: white;
    }

    .share-btn-product.facebook:hover {
      background: linear-gradient(135deg, #1864CC, #1555B0);
    }

    .share-btn-product.instagram {
      background: linear-gradient(135deg, #E4405F, #C13584);
      color: white;
      border: none;
      cursor: pointer;
    }

    .share-btn-product.instagram:hover {
      background: linear-gradient(135deg, #C13584, #833AB4);
    }

    .share-btn-product.tiktok {
      background: linear-gradient(135deg, #000000, #1a1a1a);
      color: white;
      border: none;
      cursor: pointer;
    }

    .share-btn-product.tiktok:hover {
      background: linear-gradient(135deg, #1a1a1a, #333333);
    }

    .product-price {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
    }

    .btn-add-cart {
    padding: 0.75rem 1.5rem;
    background: var(--primary);
    color: var(--white);
    border: none;
    border-radius: var(--radius);
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    }

    .btn-add-cart:hover {
    background: var(--primary-light);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    }

    .btn-add-cart:disabled {
    background: var(--gray-300);
    cursor: not-allowed;
    transform: none;
    }

    .product-stock {
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    display: inline-block;
    margin-bottom: 0.5rem;
    }

    .stock-available {
    background: #d4edda;
    color: #155724;
    }

    .stock-low {
    background: #fff3cd;
    color: #856404;
    }

    .stock-out {
    background: #f8d7da;
    color: #721c24;
    }

    .product-card.out-of-stock {
    opacity: 0.6;
    }

    .product-card.out-of-stock .product-image {
    filter: grayscale(50%);
    }

    /* EMPTY STATE */
    .empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    }

    .empty-state i {
    font-size: 4rem;
    color: var(--gray-300);
    margin-bottom: 1.5rem;
    }

    .empty-state h3 {
    font-size: 1.75rem;
    color: var(--gray-700);
    margin-bottom: 0.75rem;
    }

    .empty-state p {
    color: var(--gray-500);
    margin-bottom: 2rem;
    }

    .btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 1.75rem;
    background: var(--primary);
    color: var(--white);
    text-decoration: none;
    border-radius: var(--radius);
    font-weight: 600;
    transition: var(--transition);
    }

    .btn-primary:hover {
    background: var(--primary-light);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    }

    /* TOAST NOTIFICATION */
    .toast {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    background: var(--success);
    color: var(--white);
    padding: 1rem 1.5rem;
    border-radius: var(--radius);
    box-shadow: var(--shadow-lg);
    display: none;
    align-items: center;
    gap: 0.75rem;
    z-index: 1000;
    animation: slideIn 0.3s ease;
    }

    .toast.show {
    display: flex;
    }

    @keyframes slideIn {
    from {
    transform: translateX(100%);
    opacity: 0;
    }
    to {
    transform: translateX(0);
    opacity: 1;
    }
    }

    @media (max-width: 768px) {
    .container { padding: 1rem; }
    .hero { padding: 1.75rem 1rem 1.5rem; }
    .hero-title { font-size: 1.6rem; }
    .hero-seller { font-size: 0.9rem; }
    .hero-info { gap: 0.5rem; }
    .hero-info-item { font-size: 0.8rem; padding: 0.3rem 0.65rem; }
    .products-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
    .product-image { height: 180px; }
    .gallery-main-wrapper { min-height: 180px; }
    .product-body { padding: 0.85rem; }
    .product-name { font-size: 0.95rem; }
    .product-price { font-size: 1.2rem; }
    .products-controls { gap: 0.5rem; }
    .sort-select { font-size: 0.85rem; padding: 0.6rem 1.75rem 0.6rem 0.75rem; }
    .toast { bottom: 1rem; right: 1rem; left: 1rem; }
    }
    @media (max-width: 400px) {
    .products-grid { grid-template-columns: 1fr; }
    }
  </style>

  <!-- JSON-LD Event (Venta de Garaje) -->
  <script type="application/ld+json">
  <?php
  $schemaItems = [];
  foreach ($products as $i => $p) {
      $pImg = getProductImage($p);
      $item = [
        '@type'    => 'Offer',
        'name'     => $p['name'],
        'price'    => (float)$p['price'],
        'priceCurrency' => 'CRC',
        'availability'  => (int)($p['stock'] ?? 1) > 0
                             ? 'https://schema.org/InStock'
                             : 'https://schema.org/SoldOut',
      ];
      if ($pImg) $item['image'] = $pImg;
      if (!empty($p['description'])) $item['description'] = substr($p['description'], 0, 160);
      $schemaItems[] = $item;
  }
  $schemaEvent = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Event',
    'name'        => $sale['title'],
    'description' => 'Venta de garaje de ' . ($sale['affiliate_name'] ?? 'CompraTica') . ' en Costa Rica.',
    'url'         => $cleanUrl,
    'startDate'   => $sale['start_at'] ?? '',
    'endDate'     => $sale['end_at']   ?? '',
    'eventStatus' => 'https://schema.org/EventScheduled',
    'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode',
    'organizer'   => ['@type' => 'Person', 'name' => $sale['affiliate_name'] ?? 'CompraTica'],
    'offers'      => $schemaItems,
  ];
  echo json_encode($schemaEvent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
  ?>
  </script>
</head>
<body>

<!-- HEADER -->
<header class="header">
  <a href="venta-garaje" class="logo">
    <i class="fas fa-store"></i>
    <?= h($APP_NAME) ?>
  </a>

  <div class="header-nav">
    <a href="cart" class="btn-icon" title="Carrito">
    <i class="fas fa-shopping-cart"></i>
    <span id="cartBadge" class="cart-badge" style="display:none;">0</span>
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
      <a href="my_orders" class="menu-link">
        <i class="fas fa-box"></i> Mis Órdenes
      </a>
      <a href="cart" class="menu-link">
        <i class="fas fa-shopping-cart"></i> Mi Carrito
      </a>
    <?php else: ?>
      <a href="login" class="menu-link">
        <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
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

    <a href="index" class="menu-link">
      <i class="fas fa-home"></i> Inicio
    </a>
    <a href="/servicios" class="menu-link">
      <i class="fas fa-concierge-bell"></i> Empleos y Servicios
    </a>
    <a href="venta-garaje" class="menu-link">
      <i class="fas fa-tags"></i> Venta de Garaje
    </a>
    <a href="bienes-raices" class="menu-link">
      <i class="fas fa-building"></i> Bienes Raíces
    </a>
    <a href="emprendedores-catalogo" class="menu-link">
      <i class="fas fa-store"></i> Emprendedores
    </a>

    <!-- ── VENDEDORES ────────────────────────── -->
    <div style="display:flex;align-items:center;gap:8px;margin:14px 0 6px;padding:0 4px;">
      <div style="flex:1;height:1px;background:#e5e7eb;"></div>
      <span style="font-size:.8rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;white-space:nowrap;">
        <i class="fas fa-store-alt" style="color:#10b981;margin-right:4px;"></i>Para Vendedores
      </span>
      <div style="flex:1;height:1px;background:#e5e7eb;"></div>
    </div>

    <a href="select-publication-type.php" class="menu-link">
      <i class="fas fa-bullhorn"></i> Publicar mi venta
    </a>
    <a href="affiliate/login.php" class="menu-link">
      <i class="fas fa-tags"></i> Portal Venta Garaje
    </a>
    <a href="/jobs_service/login.php" class="menu-link">
      <i class="fas fa-briefcase"></i> Portal Empleos y Servicios
    </a>
    <a href="/real-estate/login.php" class="menu-link">
      <i class="fas fa-home"></i> Portal Bienes Raíces
    </a>
    <a href="emprendedores-dashboard" class="menu-link">
      <i class="fas fa-store"></i> Portal Emprendedores
    </a>
    <a href="admin/login.php" class="menu-link">
      <i class="fas fa-user-shield"></i> Administrador
    </a>

    <?php if ($isLoggedIn): ?>
      <div style="height:1px;background:#e5e7eb;margin:8px 0;"></div>
      <a href="logout" class="menu-link" style="color:#ef4444;">
        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- HERO SECTION -->
<section class="hero">
  <h1 class="hero-title"><?= h($sale['title']) ?></h1>
  <p class="hero-seller"><i class="fas fa-user-circle" style="opacity:.6;margin-right:4px;"></i><?= h($sale['affiliate_name']) ?></p>

  <div class="hero-actions">
    <?php if (!empty($sale['affiliate_phone'])): ?>
      <?php $waPhone = preg_replace('/\D/', '', $sale['affiliate_phone']); ?>
      <a href="https://wa.me/506<?= $waPhone ?>?text=<?= urlencode('Hola, vi tu venta de garaje "' . $sale['title'] . '" en CompraTica y me interesa un producto.') ?>"
         target="_blank" rel="noopener" class="hero-btn whatsapp">
        <i class="fab fa-whatsapp"></i> Contactar vendedor
      </a>
    <?php endif; ?>
    <a href="venta-garaje" class="hero-btn outline">
      <i class="fas fa-store"></i> Ver más ventas
    </a>
  </div>

  <div class="hero-info">
    <div class="hero-info-item">
      <i class="fas fa-boxes"></i>
      <?= count($products) ?> producto<?= count($products) !== 1 ? 's' : '' ?>
    </div>
    <div class="hero-info-item">
      <i class="fas fa-calendar-alt"></i>
      <?= date('d/m/Y', strtotime($sale['start_at'])) ?> – <?= date('d/m/Y', strtotime($sale['end_at'])) ?>
    </div>
    <div class="hero-info-item countdown-item"
         data-start="<?= h(date('d/m/Y H:i:s', strtotime($sale['start_at']))) ?>"
         data-end="<?= h(date('d/m/Y H:i:s', strtotime($sale['end_at']))) ?>">
      <i class="fas fa-hourglass-half"></i>
      <span class="countdown-text">…</span>
    </div>
  </div>

  <?php
    // Validar que el live sigue activo antes de renderizar el panel
    if (!empty($sale['aff_is_live'])) {
        $liveType  = $sale['aff_live_type'] ?? 'link';
        $liveSid   = $sale['aff_live_session_id'] ?? '';
        if ($liveType === 'camera' && $liveSid) {
            $camCheck = $pdo->prepare("SELECT status FROM live_cam_sessions WHERE id=? LIMIT 1");
            $camCheck->execute([$liveSid]);
            $camRow = $camCheck->fetch(PDO::FETCH_ASSOC);
            if (!$camRow || $camRow['status'] !== 'active') {
                // Sesión de cámara terminada — apagar el live en la BD
                $pdo->prepare("UPDATE affiliates SET is_live=0, live_session_id=NULL WHERE id=?")
                    ->execute([$sale['affiliate_id']]);
                $sale['aff_is_live'] = 0;
            }
        }
    }
  ?>
  <?php if (!empty($sale['aff_is_live'])): ?>
  <?php
    $liveType  = $sale['aff_live_type'] ?? 'link';
    $liveTitle = h($sale['aff_live_title'] ?: 'EN VIVO');
    $liveLink  = $sale['aff_live_link'] ?? '';
    $liveSid   = $sale['aff_live_session_id'] ?? '';
    $embed     = $liveLink ? parseLiveUrl($liveLink) : [];
  ?>
  <style>
  @keyframes store-live-pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
  .store-live-wrap {
    margin-top: 1.4rem;
    background: rgba(0,0,0,.35);
    border: 1.5px solid rgba(239,68,68,.7);
    border-radius: 14px;
    padding: 12px 14px;
    max-width: 380px;
    width: 100%;
    backdrop-filter: blur(6px);
  }
  .store-live-header {
    display: flex; align-items: center; gap: 10px;
    justify-content: space-between; margin-bottom: 10px;
  }
  .store-live-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: #ef4444; color: white; border-radius: 20px;
    padding: 3px 11px; font-size: .8rem; font-weight: 700;
    letter-spacing: .03em;
  }
  .store-live-badge .dot {
    width: 7px; height: 7px; background: white; border-radius: 50%;
    animation: store-live-pulse 1.2s infinite;
  }
  .store-live-title { color: rgba(255,255,255,.9); font-size: .88rem; font-weight: 600; }
  .store-live-toggle {
    background: none; border: none; color: rgba(255,255,255,.5);
    cursor: pointer; font-size: 1rem; padding: 2px 6px; border-radius: 6px;
    transition: color .2s;
  }
  .store-live-toggle:hover { color: white; }
  .store-live-player { border-radius: 10px; overflow: hidden; background: #000; aspect-ratio: 16/9; }
  .store-live-player iframe, .store-live-player video { width:100%; height:100%; display:block; border:0; }
  .store-live-link-btn {
    display: flex; align-items: center; gap: 10px;
    background: rgba(255,255,255,.1); border: 1.5px solid rgba(255,255,255,.2);
    border-radius: 10px; padding: 12px 16px; text-decoration: none;
    color: white; font-weight: 600; font-size: .92rem; transition: background .2s;
  }
  .store-live-link-btn:hover { background: rgba(255,255,255,.18); }
  .store-live-link-btn i { font-size: 1.4rem; }
  </style>

  <div class="store-live-wrap" id="storeLiveWrap">
    <div class="store-live-header">
      <span class="store-live-badge"><span class="dot"></span> EN VIVO</span>
      <span class="store-live-title"><?= $liveTitle ?></span>
      <button class="store-live-toggle" onclick="document.getElementById('storeLiveBody').style.display=document.getElementById('storeLiveBody').style.display==='none'?'':'none'; this.innerHTML=this.innerHTML.includes('chevron-down')?'<i class=\'fas fa-chevron-up\'></i>':'<i class=\'fas fa-chevron-down\'></i>';" title="Minimizar/Expandir">
        <i class="fas fa-chevron-up"></i>
      </button>
    </div>

    <div id="storeLiveBody">
      <?php if ($liveType === 'camera' && $liveSid): ?>
        <!-- ── Cámara del vendedor ── -->
        <div class="store-live-player" id="storeLivePlayerWrap">
          <video id="storeLiveVideo" autoplay playsinline muted
                 style="background:#000;cursor:pointer;width:100%;height:100%;"
                 onclick="this.muted=false;this.play();"></video>
        </div>
        <p id="storeLiveStatus" style="color:rgba(255,255,255,.6);font-size:.75rem;margin:6px 0 0;text-align:center;">
          Conectando… (tocá el video si no hay sonido)
        </p>
        <script>
        (function(){
          const SID   = <?= json_encode($liveSid) ?>;
          const AFF   = <?= (int)$sale['affiliate_id'] ?>;
          const video = document.getElementById('storeLiveVideo');
          const statusEl = document.getElementById('storeLiveStatus');
          function setStatus(msg) { if (statusEl) statusEl.innerHTML = msg; }

          // ── Detectar iOS o falta de soporte WebM ─────────────────────
          const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
                        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
          const noWebM = typeof MediaSource === 'undefined' ||
                         !MediaSource.isTypeSupported('video/webm; codecs="vp8,vorbis"');
          if (isIOS || noWebM) {
            document.getElementById('storeLivePlayerWrap').innerHTML =
              '<div style="display:flex;align-items:center;justify-content:center;height:100%;padding:20px;text-align:center;color:#aaa;font-size:.85rem;">' +
              '<div><i class="fas fa-mobile-alt" style="font-size:2rem;margin-bottom:10px;display:block;"></i>' +
              'La transmisión en vivo no es compatible con iOS/iPadOS.<br>Abrí la tienda desde Chrome o Firefox en Android o computadora.</div></div>';
            setStatus('');
            // Verificar cada 5s si termina para ocultar el panel
            setInterval(async()=>{
              try{const r=await fetch('/api/aff-live-status.php?affiliate_id='+AFF);const d=await r.json();
              if(!d.is_live){const w=document.getElementById('storeLiveWrap');if(w)w.style.display='none';}}catch(e){}
            }, 5000);
            return;
          }

          // ── Sliding window: init chunk + últimos MAX_WIN chunks ──────────
          // Mantiene el lag mínimo (~2-3s): solo se descarga y reproduce
          // el segmento reciente, no toda la transmisión desde el principio.
          const MAX_WIN  = 7;   // init + 7 chunks recientes ≈ 3.5s a 500ms/chunk
          let initBuf    = null; // primer chunk siempre presente (header WebM)
          const recent   = [];   // ventana deslizante
          let nextChunk  = 0, blobUrl = null, playing = false, ended = false;

          function buildBlob() {
            const parts = initBuf ? [initBuf, ...recent] : [...recent];
            return new Blob(parts.map(b => new Uint8Array(b)), { type: 'video/webm' });
          }

          function playBlob() {
            if (blobUrl) URL.revokeObjectURL(blobUrl);
            const blob = buildBlob();
            blobUrl = URL.createObjectURL(blob);
            video.src = blobUrl;
            video.addEventListener('loadedmetadata', () => {
              // Saltar al borde del live (casi el final del blob)
              if (video.duration && isFinite(video.duration) && video.duration > 0.5)
                video.currentTime = Math.max(0, video.duration - 0.5);
              video.play().then(() => {
                setStatus('<i class="fas fa-circle" style="color:#ef4444;font-size:.5rem;animation:store-live-pulse 1.2s infinite;"></i> EN VIVO 🔴 — tocá para activar sonido');
              }).catch(() => {
                setStatus('▶ Tocá el video para ver la transmisión');
              });
            }, { once: true });
          }

          async function poll() {
            if (ended) return;
            try {
              const r = await fetch('/api/live-cam-poll.php?session_id=' + SID);
              if (!r.ok) { setTimeout(poll, 3000); return; }
              const d = await r.json();

              if (d.ended) {
                ended = true;
                document.getElementById('storeLiveWrap').style.display = 'none';
                if (blobUrl) URL.revokeObjectURL(blobUrl);
                return;
              }

              // Descargar chunks nuevos
              let gotNew = false;
              while (nextChunk < d.chunk_count) {
                const cr = await fetch('/api/live-cam-serve.php?session_id=' + SID + '&index=' + nextChunk);
                if (!cr.ok) break;
                const buf = await cr.arrayBuffer();
                if (nextChunk === 0) {
                  initBuf = buf;          // header siempre guardado
                } else {
                  recent.push(buf);
                  if (recent.length > MAX_WIN) recent.shift(); // ventana deslizante
                }
                nextChunk++;
                gotNew = true;
              }

              // Primera reproducción (necesita init + al menos 1 chunk de datos)
              if (!playing && initBuf && recent.length >= 1) {
                playing = true;
                playBlob();
              }

              // Actualizar el blob con los chunks recientes cuando el video para
              if (playing && gotNew && (video.ended || video.paused)) {
                playBlob();
              }
            } catch(e) {}
            setTimeout(poll, 800);
          }

          poll();

          // Fallback: verificar cada 5s si el live terminó
          setInterval(async () => {
            try {
              const r = await fetch('/api/aff-live-status.php?affiliate_id=' + AFF);
              const d = await r.json();
              if (!d.is_live) {
                ended = true;
                if (blobUrl) URL.revokeObjectURL(blobUrl);
                const w = document.getElementById('storeLiveWrap');
                if (w) w.style.display = 'none';
              }
            } catch(e) {}
          }, 5000);
        })();
        </script>

      <?php elseif (!empty($embed['embedUrl'])): ?>
        <!-- ── YouTube / Facebook embed ── -->
        <div class="store-live-player">
          <iframe src="<?= h($embed['embedUrl']) ?>"
                  allow="autoplay; fullscreen; encrypted-media"
                  allowfullscreen loading="lazy"></iframe>
        </div>
        <script>
        // Verificar cada 5s si el live sigue activo (modo link)
        (function(){
          const AFF_ID = <?= (int)$sale['affiliate_id'] ?>;
          setInterval(async () => {
            try {
              const r = await fetch('/api/aff-live-status.php?affiliate_id=' + AFF_ID);
              const d = await r.json();
              if (!d.is_live) {
                const w = document.getElementById('storeLiveWrap');
                if (w) w.style.display = 'none';
              }
            } catch(e) {}
          }, 5000);
        })();
        </script>

      <?php elseif ($liveLink): ?>
        <!-- ── Instagram / TikTok: solo enlace ── -->
        <a href="<?= h($liveLink) ?>" target="_blank" rel="noopener" class="store-live-link-btn">
          <i class="<?= h($embed['icon'] ?? 'fas fa-video') ?>" style="color:<?= h($embed['color'] ?? '#ef4444') ?>;"></i>
          <span>Ver en vivo en <?= h($embed['platform'] ?? 'Live') ?><br>
            <small style="font-weight:400;opacity:.7;">Abre en nueva pestaña</small>
          </span>
          <i class="fas fa-external-link-alt" style="margin-left:auto;opacity:.5;"></i>
        </a>
        <script>
        (function(){
          const AFF_ID = <?= (int)$sale['affiliate_id'] ?>;
          setInterval(async () => {
            try {
              const r = await fetch('/api/aff-live-status.php?affiliate_id=' + AFF_ID);
              const d = await r.json();
              if (!d.is_live) {
                const w = document.getElementById('storeLiveWrap');
                if (w) w.style.display = 'none';
              }
            } catch(e) {}
          }, 15000);
        })();
        </script>

      <?php else: ?>
        <!-- ── Live activo sin link ── -->
        <p style="color:rgba(255,255,255,.7);font-size:.88rem;margin:0;text-align:center;padding:8px 0;">
          El vendedor está transmitiendo en vivo ahora.
        </p>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; /* aff_is_live */ ?>

  <?php if (!empty($sale['chat_active'])): ?>
  <!-- ── CHAT DEL ESPACIO (cliente) ─────────────────────────────────────── -->
  <div id="store-chat-wrap" style="margin-top:1.4rem; max-width:420px; width:100%;">
    <style>
    #store-chat-wrap .sc-box { background:rgba(0,0,0,.28); border:1.5px solid rgba(16,185,129,.5); border-radius:14px; padding:12px 14px; backdrop-filter:blur(6px); }
    #store-chat-wrap .sc-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; gap:8px; }
    #store-chat-wrap .sc-badge { display:inline-flex; align-items:center; gap:6px; background:#10b981; color:white; border-radius:20px; padding:3px 11px; font-size:.8rem; font-weight:700; }
    #store-chat-wrap .sc-badge .dot { width:7px; height:7px; background:white; border-radius:50%; animation:store-live-pulse 1.2s infinite; }
    #store-chat-wrap .sc-toggle { background:none; border:none; color:rgba(255,255,255,.5); cursor:pointer; font-size:1rem; padding:2px 6px; border-radius:6px; transition:color .2s; }
    #store-chat-wrap .sc-toggle:hover { color:white; }
    #store-chat-log { background:rgba(0,0,0,.2); border-radius:10px; padding:10px; height:200px; overflow-y:auto; display:flex; flex-direction:column; gap:6px; margin-bottom:10px; }
    .scc-msg { max-width:88%; }
    .scc-msg.from-client { align-self:flex-start; }
    .scc-msg.from-affiliate { align-self:flex-end; }
    .scc-bubble { padding:8px 11px; border-radius:11px; font-size:.85rem; word-break:break-word; line-height:1.4; }
    .scc-msg.from-client .scc-bubble { background:rgba(255,255,255,.15); color:white; border-bottom-left-radius:3px; }
    .scc-msg.from-affiliate .scc-bubble { background:#10b981; color:white; border-bottom-right-radius:3px; }
    .scc-meta { font-size:.7rem; color:rgba(255,255,255,.45); margin-top:2px; padding:0 3px; }
    .scc-meta.right { text-align:right; }
    #store-chat-inp-wrap { display:flex; gap:7px; }
    #store-chat-inp { flex:1; background:rgba(255,255,255,.12); border:1.5px solid rgba(255,255,255,.2); border-radius:9px; padding:9px 12px; font-size:.87rem; color:white; font-family:inherit; }
    #store-chat-inp::placeholder { color:rgba(255,255,255,.45); }
    #store-chat-inp:focus { outline:none; border-color:#10b981; }
    #store-chat-send { background:#10b981; border:none; color:white; border-radius:9px; padding:9px 14px; cursor:pointer; font-size:.88rem; font-weight:700; transition:opacity .2s; }
    #store-chat-send:hover { opacity:.85; }
    #store-chat-login-note { color:rgba(255,255,255,.55); font-size:.82rem; text-align:center; padding:8px 0; }
    #store-chat-login-note a { color:#6ee7b7; }
    #store-chat-toggle-btn { background:none; border:none; color:rgba(255,255,255,.6); cursor:pointer; font-size:.82rem; width:100%; text-align:right; padding:2px 0 8px; }
    </style>
    <div class="sc-box">
      <div class="sc-header">
        <span class="sc-badge">
          <span class="dot"></span>
          Chat en Vivo
        </span>
        <button class="sc-toggle" onclick="document.getElementById('store-chat-body').style.display = document.getElementById('store-chat-body').style.display==='none' ? '' : 'none'">
          <i class="fas fa-chevron-down"></i>
        </button>
      </div>
      <div id="store-chat-body">
        <div id="store-chat-log">
          <div id="store-chat-empty" style="text-align:center; color:rgba(255,255,255,.4); font-size:.82rem; padding:14px;">
            <i class="fas fa-comment-slash" style="display:block; font-size:1.4rem; margin-bottom:6px; opacity:.5;"></i>
            Sé el primero en preguntar...
          </div>
        </div>
        <?php if ($isLoggedIn): ?>
        <div id="store-chat-inp-wrap">
          <input type="text" id="store-chat-inp" maxlength="500" placeholder="Escribe tu pregunta...">
          <button id="store-chat-send" onclick="storeChatSend()">
            <i class="fas fa-paper-plane"></i>
          </button>
        </div>
        <?php else: ?>
        <p id="store-chat-login-note">
          <a href="/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI'] ?? '') ?>">Iniciá sesión</a>
          para participar en el chat.
        </p>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <script>
  (function(){
    const SALE_ID = <?= (int)$sale_id ?>;
    let lastId = 0;
    const log   = document.getElementById('store-chat-log');
    const empty = document.getElementById('store-chat-empty');
    const inp   = document.getElementById('store-chat-inp');

    function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function poll() {
      fetch('/api/aff-chat-poll.php?sale_id=' + SALE_ID + '&last_id=' + lastId, {credentials:'same-origin'})
      .then(r => r.json())
      .then(data => {
        if (data.messages && data.messages.length) {
          if (empty) empty.style.display = 'none';
          data.messages.forEach(appendMsg);
          lastId = data.messages[data.messages.length-1].id;
        }
        if (data.is_banned) {
          if (inp) { inp.disabled = true; inp.placeholder = 'Fuiste bloqueado en este espacio.'; }
          const btn = document.getElementById('store-chat-send');
          if (btn) btn.disabled = true;
        }
      })
      .catch(()=>{});
      setTimeout(poll, 3000);
    }

    function appendMsg(m) {
      if (!log) return;
      const isAff = (m.sender_type === 'affiliate');
      const wrap  = document.createElement('div');
      wrap.className = 'scc-msg ' + (isAff ? 'from-affiliate' : 'from-client');
      wrap.innerHTML =
        (!isAff ? `<div class="scc-meta">${escHtml(m.sender_name)}</div>` : '') +
        `<div class="scc-bubble">${escHtml(m.message)}</div>` +
        `<div class="scc-meta${isAff?' right':''}">${m.time||''}</div>`;
      log.appendChild(wrap);
      log.scrollTop = log.scrollHeight;
    }

    window.storeChatSend = function() {
      if (!inp) return;
      const txt = inp.value.trim();
      if (!txt) return;
      const body = new URLSearchParams({ sale_id: SALE_ID, message: txt });
      inp.value = '';
      fetch('/api/aff-chat-send.php', { method:'POST', credentials:'same-origin', body })
      .then(r => r.json())
      .then(d => {
        if (d.ok) poll();
        else { inp.value = txt; alert(d.msg || 'Error al enviar'); }
      })
      .catch(()=>{ inp.value = txt; });
    };

    if (inp) {
      inp.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); storeChatSend(); } });
    }

    poll();
  })();
  </script>
  <!-- ── FIN CHAT CLIENTE ──────────────────────────────────────────────────── -->
  <?php endif; /* chat_active */ ?>

</section>

<!-- PRODUCTS -->
<div class="container">
  <div class="products-header">
    <h2 class="products-title">Productos</h2>
  </div>

  <div class="products-controls">
    <div class="search-wrap">
      <i class="fas fa-search"></i>
      <input type="text" id="searchInput" class="search-input" placeholder="Buscar producto…" oninput="filterProducts()">
    </div>
    <select id="sortSelect" class="sort-select" onchange="filterProducts()">
      <option value="default">Orden original</option>
      <option value="price-asc">Precio: menor a mayor</option>
      <option value="price-desc">Precio: mayor a menor</option>
      <option value="name-asc">Nombre A–Z</option>
    </select>
    <span id="resultsCount" class="products-count-label"><?= count($products) ?> productos</span>
  </div>

  <?php if (empty($products)): ?>
    <div class="empty-state">
    <i class="fas fa-box-open"></i>
    <h3>No hay productos disponibles</h3>
    <p>Este espacio aún no tiene productos publicados</p>
    <a href="venta-garaje" class="btn-primary">
    <i class="fas fa-arrow-left"></i> Volver al inicio
    </a>
    </div>
  <?php else: ?>
    <div class="products-grid">
    <?php foreach ($products as $product):
    $stock = (int)($product['stock'] ?? 0);
    $isOutOfStock = $stock <= 0;

    // Preparar imágenes para galería
    $images = [];
    if (!empty($product['image'])) {
      $images[] = '/uploads/' . ltrim($product['image'], '/');
    }
    if (!empty($product['image2'])) {
      $images[] = '/uploads/' . ltrim($product['image2'], '/');
    }
    if (empty($images)) {
      $images[] = 'assets/placeholder.jpg';
    }
    ?>
    <div id="product-<?= (int)$product['id'] ?>"
         class="product-card <?= $isOutOfStock ? 'out-of-stock' : '' ?>"
         data-product-id="<?= (int)$product['id'] ?>"
         data-name="<?= h(strtolower($product['name'] . ' ' . ($product['description'] ?? ''))) ?>"
         data-price="<?= (float)$product['price'] ?>"
         data-currency="<?= h($product['currency']) ?>">
    <!-- Galería de imágenes estilo eBay -->
    <div class="product-image-gallery">
      <?php if (count($images) > 1): ?>
        <div class="gallery-thumbnails">
          <?php foreach ($images as $idx => $img): ?>
            <img
              src="<?= h($img) ?>"
              alt="Miniatura <?= $idx + 1 ?>"
              class="gallery-thumb <?= $idx === 0 ? 'active' : '' ?>"
              data-index="<?= $idx ?>"
              onclick="switchImage(this)"
              onerror="this.src='/assets/img/placeholder-product.svg'"
            >
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="gallery-main-wrapper" onclick="openLightbox(<?= (int)$product['id'] ?>)">
        <img
          id="main-image-<?= (int)$product['id'] ?>"
          src="<?= h($images[0]) ?>"
          alt="<?= h($product['name']) ?>"
          class="product-image"
          loading="lazy"
          onerror="this.src='/assets/img/placeholder-product.svg'"
          data-images='<?= json_encode($images, JSON_HEX_QUOT | JSON_HEX_APOS) ?>'
        >
        <div class="zoom-icon">
          <i class="fas fa-search-plus"></i>
          <span>Ampliar</span>
        </div>
      </div>
    </div>
    <div class="product-body">
    <?php if (!empty($product['category'])): ?>
      <span class="product-category-badge"><?= h($product['category']) ?></span>
    <?php endif; ?>
    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem;">
      <h3 class="product-name" style="margin-bottom: 0;"><?= h($product['name']) ?></h3>

      <!-- Botones de compartir producto -->
      <?php
        $productUrl = (defined('APP_URL') ? APP_URL : 'https://compratica.com') . '/store.php?sale_id=' . (int)$sale_id . '&product_id=' . (int)$product['id'] . '#product-' . (int)$product['id'];
        $productWhatsappText = urlencode('¡Mirá este producto! ' . $product['name'] . ' - ' . formatPrice((float)$product['price'], $product['currency']) . ' - ' . $productUrl);
        $productFacebookUrl = 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($productUrl);
        $productWhatsappUrl = 'https://wa.me/?text=' . $productWhatsappText;
      ?>
      <div class="share-buttons-product">
        <a href="<?= $productWhatsappUrl ?>" target="_blank" class="share-btn-product whatsapp" title="Compartir por WhatsApp">
          <i class="fab fa-whatsapp"></i>
        </a>
        <a href="<?= $productFacebookUrl ?>" target="_blank" class="share-btn-product facebook" title="Compartir en Facebook">
          <i class="fab fa-facebook-f"></i>
        </a>
        <button onclick="copyToClipboardProduct('<?= addslashes($productUrl) ?>', 'Instagram')" class="share-btn-product instagram" title="Copiar link para Instagram">
          <i class="fab fa-instagram"></i>
        </button>
        <button onclick="copyToClipboardProduct('<?= addslashes($productUrl) ?>', 'TikTok')" class="share-btn-product tiktok" title="Copiar link para TikTok">
          <i class="fab fa-tiktok"></i>
        </button>
      </div>
    </div>

    <?php if ($isOutOfStock): ?>
    <span class="product-stock stock-out">
    <i class="fas fa-times-circle"></i> Sin stock
    </span>
    <?php elseif ($stock <= 5): ?>
    <span class="product-stock stock-low">
    <i class="fas fa-exclamation-triangle"></i> Últimas <?= $stock ?> unidades
    </span>
    <?php else: ?>
    <span class="product-stock stock-available">
    <i class="fas fa-check-circle"></i> Disponible (<?= $stock ?>)
    </span>
    <?php endif; ?>
    
    <?php if (!empty($product['description'])): ?>
    <p class="product-description"><?= h($product['description']) ?></p>
    <?php endif; ?>
    <div class="product-footer">
    <div class="product-price">
    <?= formatPrice((float)$product['price'], $product['currency']) ?>
    </div>
    <button 
    class="btn-add-cart"
    data-product-id="<?= (int)$product['id'] ?>"
    data-sale-id="<?= (int)$sale_id ?>"
    <?= $isOutOfStock ? 'disabled' : '' ?>
    >
    <i class="fas fa-<?= $isOutOfStock ? 'ban' : 'cart-plus' ?>"></i>
    <?= $isOutOfStock ? 'Agotado' : 'Agregar' ?>
    </button>
    </div>
    </div>
    </div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- TOAST NOTIFICATION -->
<div id="toast" class="toast">
  <i class="fas fa-check-circle"></i>
  <span id="toast-message">Producto agregado al carrito</span>
</div>

<!-- LIGHTBOX PARA ZOOM -->
<div id="lightbox" class="lightbox">
  <button class="lightbox-close" onclick="closeLightbox()">
    <i class="fas fa-times"></i>
  </button>

  <div class="lightbox-content">
    <div id="lightbox-thumbnails" class="lightbox-thumbnails">
      <!-- Miniaturas se cargarán dinámicamente -->
    </div>

    <div class="lightbox-main">
      <button class="lightbox-nav prev" onclick="navigateLightbox(-1)" style="display:none;">
        <i class="fas fa-chevron-left"></i>
      </button>

      <img
        id="lightbox-image"
        src=""
        alt="Imagen ampliada"
        class="lightbox-image"
        onclick="toggleZoom()"
      >

      <button class="lightbox-nav next" onclick="navigateLightbox(1)" style="display:none;">
        <i class="fas fa-chevron-right"></i>
      </button>
    </div>
  </div>
</div>

<script>
// ========== FUNCIÓN COPIAR LINK ==========
function copyToClipboardProduct(text, platform) {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(function() {
      alert('¡Link copiado! Ahora podés pegarlo en ' + platform);
    }).catch(function(err) {
      fallbackCopyProduct(text, platform);
    });
  } else {
    fallbackCopyProduct(text, platform);
  }
}

function fallbackCopyProduct(text, platform) {
  const textArea = document.createElement('textarea');
  textArea.value = text;
  textArea.style.position = 'fixed';
  textArea.style.left = '-999999px';
  document.body.appendChild(textArea);
  textArea.select();
  try {
    document.execCommand('copy');
    alert('¡Link copiado! Ahora podés pegarlo en ' + platform);
  } catch (err) {
    alert('No se pudo copiar el link. Por favor, copialo manualmente: ' + text);
  }
  document.body.removeChild(textArea);
}

// ========== GALERÍA DE IMÁGENES ==========

// Cambiar imagen principal al hacer clic en miniatura
function switchImage(thumb) {
  const card = thumb.closest('.product-card');
  const mainImg = card.querySelector('.product-image');
  const allThumbs = card.querySelectorAll('.gallery-thumb');

  // Actualizar imagen principal
  mainImg.src = thumb.src;

  // Actualizar estado activo de miniaturas
  allThumbs.forEach(t => t.classList.remove('active'));
  thumb.classList.add('active');
}

// Variables globales del lightbox
let currentLightboxImages = [];
let currentLightboxIndex = 0;

// Abrir lightbox con zoom
function openLightbox(productId) {
  const mainImg = document.getElementById(`main-image-${productId}`);
  if (!mainImg) return;

  // Obtener todas las imágenes del producto
  try {
    currentLightboxImages = JSON.parse(mainImg.dataset.images || '[]');
  } catch (e) {
    currentLightboxImages = [mainImg.src];
  }

  if (currentLightboxImages.length === 0) {
    currentLightboxImages = [mainImg.src];
  }

  // Encontrar índice actual
  currentLightboxIndex = 0;
  for (let i = 0; i < currentLightboxImages.length; i++) {
    if (mainImg.src.includes(currentLightboxImages[i])) {
      currentLightboxIndex = i;
      break;
    }
  }

  // Renderizar lightbox
  renderLightbox();

  // Mostrar lightbox
  const lightbox = document.getElementById('lightbox');
  lightbox.classList.add('active');
  document.body.style.overflow = 'hidden';
}

// Cerrar lightbox
function closeLightbox() {
  const lightbox = document.getElementById('lightbox');
  lightbox.classList.remove('active');
  document.body.style.overflow = '';

  // Resetear zoom
  const img = document.getElementById('lightbox-image');
  img.classList.remove('zoomed');
}

// Renderizar contenido del lightbox
function renderLightbox() {
  const lightboxImg = document.getElementById('lightbox-image');
  const thumbnailsContainer = document.getElementById('lightbox-thumbnails');
  const prevBtn = document.querySelector('.lightbox-nav.prev');
  const nextBtn = document.querySelector('.lightbox-nav.next');

  // Actualizar imagen principal
  lightboxImg.src = currentLightboxImages[currentLightboxIndex];
  lightboxImg.classList.remove('zoomed');

  // Mostrar/ocultar botones de navegación
  if (currentLightboxImages.length > 1) {
    prevBtn.style.display = 'flex';
    nextBtn.style.display = 'flex';
  } else {
    prevBtn.style.display = 'none';
    nextBtn.style.display = 'none';
  }

  // Renderizar miniaturas
  if (currentLightboxImages.length > 1) {
    thumbnailsContainer.innerHTML = currentLightboxImages.map((img, idx) => `
      <img
        src="${img}"
        alt="Miniatura ${idx + 1}"
        class="lightbox-thumb ${idx === currentLightboxIndex ? 'active' : ''}"
        onclick="setLightboxImage(${idx})"
      >
    `).join('');
  } else {
    thumbnailsContainer.innerHTML = '';
  }
}

// Cambiar imagen en lightbox
function setLightboxImage(index) {
  currentLightboxIndex = index;
  renderLightbox();
}

// Navegar entre imágenes
function navigateLightbox(direction) {
  currentLightboxIndex += direction;

  // Loop circular
  if (currentLightboxIndex < 0) {
    currentLightboxIndex = currentLightboxImages.length - 1;
  } else if (currentLightboxIndex >= currentLightboxImages.length) {
    currentLightboxIndex = 0;
  }

  renderLightbox();
}

// Toggle zoom
function toggleZoom() {
  const img = document.getElementById('lightbox-image');
  img.classList.toggle('zoomed');
}

// Cerrar con ESC y navegar con flechas
document.addEventListener('keydown', (e) => {
  const lightbox = document.getElementById('lightbox');

  if (!lightbox.classList.contains('active')) return;

  if (e.key === 'Escape') {
    closeLightbox();
  } else if (e.key === 'ArrowLeft') {
    navigateLightbox(-1);
  } else if (e.key === 'ArrowRight') {
    navigateLightbox(1);
  }
});

// Cerrar al hacer clic fuera de la imagen
document.getElementById('lightbox').addEventListener('click', function(e) {
  if (e.target === this) {
    closeLightbox();
  }
});

// ========== MENÚ HAMBURGUESA ==========
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

// CARRITO
const API = '/api/cart.php';
const cartBadge = document.getElementById('cartBadge');
const toast = document.getElementById('toast');
const toastMessage = document.getElementById('toast-message');

function getCsrfToken() {
  return (document.cookie.match(/(?:^|;\s*)vg_csrf=([^;]+)/) || [])[1] || '';
}

function showToast(message, type = 'success') {
  toastMessage.textContent = message;
  toast.classList.add('show');
  if (type === 'error') {
    toast.style.background = '#c0392b';
  } else {
    toast.style.background = '#27ae60';
  }
  setTimeout(() => {
    toast.classList.remove('show');
  }, 3000);
}

async function updateCartBadge() {
  try {
    const response = await fetch(API + '?action=get', {
    credentials: 'include',
    cache: 'no-store'
    });
    const data = await response.json();
    
    let totalCount = 0;
    if (data.ok && data.groups) {
    data.groups.forEach(group => {
    group.items.forEach(item => {
    totalCount += item.qty;
    });
    });
    }
    
    cartBadge.textContent = totalCount;
    cartBadge.style.display = totalCount > 0 ? 'inline-block' : 'none';
  } catch (error) {
    console.error('Error al actualizar el badge:', error);
  }
}

async function addToCart(productId, saleId) {
  try {
    const token = getCsrfToken();
    const response = await fetch(API + '?action=add', {
    method: 'POST',
    headers: {
    'Content-Type': 'application/json',
    'X-CSRF-Token': token
    },
    body: JSON.stringify({
    product_id: productId,
    sale_id: saleId,
    qty: 1
    }),
    credentials: 'include'
    });
    
    const data = await response.json();
    
    if (data.ok) {
    showToast('✓ Producto agregado al carrito', 'success');
    updateCartBadge();
    } else {
    // Usar 'error' o 'message' del backend, con fallback
    const errorMsg = data.error || data.message || 'Error al agregar producto';
    showToast('✗ ' + errorMsg, 'error');
    }
  } catch (error) {
    console.error('Error:', error);
    showToast('✗ Error de conexión. Intenta de nuevo', 'error');
  }
}

// Event listeners para botones de agregar
document.querySelectorAll('.btn-add-cart').forEach(button => {
  button.addEventListener('click', function() {
    // Verificar si el botón está deshabilitado (sin stock)
    if (this.disabled) {
    showToast('✗ Este producto está agotado', 'error');
    return;
    }
    
    const productId = parseInt(this.dataset.productId);
    const saleId = parseInt(this.dataset.saleId);
    
    this.disabled = true;
    const originalText = this.innerHTML;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
    
    addToCart(productId, saleId).finally(() => {
    setTimeout(() => {
    this.disabled = false;
    this.innerHTML = originalText;
    }, 500);
    });
  });
});

// Cargar badge al inicio
updateCartBadge();

// ========== BÚSQUEDA Y ORDENAMIENTO ==========
function filterProducts() {
  const q = (document.getElementById('searchInput').value || '').toLowerCase().trim();
  const sort = document.getElementById('sortSelect').value;
  const grid = document.querySelector('.products-grid');
  const cards = [...grid.querySelectorAll('.product-card')];

  // Filtrar
  let visible = cards.filter(card => {
    const name = (card.dataset.name || '').toLowerCase();
    const match = !q || name.includes(q);
    card.dataset.hidden = match ? 'false' : 'true';
    return match;
  });

  // Ordenar
  if (sort !== 'default') {
    visible.sort((a, b) => {
      if (sort === 'price-asc' || sort === 'price-desc') {
        const pa = parseFloat(a.dataset.price) || 0;
        const pb = parseFloat(b.dataset.price) || 0;
        return sort === 'price-asc' ? pa - pb : pb - pa;
      }
      if (sort === 'name-asc') {
        return (a.dataset.name || '').localeCompare(b.dataset.name || '', 'es');
      }
      return 0;
    });
    visible.forEach(c => grid.appendChild(c));
  }

  // Contador
  const countEl = document.getElementById('resultsCount');
  if (countEl) {
    const total = cards.length;
    countEl.textContent = visible.length === total
      ? `${total} producto${total !== 1 ? 's' : ''}`
      : `${visible.length} de ${total} producto${total !== 1 ? 's' : ''}`;
  }

  // Empty state dinámico
  let emptyMsg = grid.querySelector('.search-empty');
  if (visible.length === 0) {
    if (!emptyMsg) {
      emptyMsg = document.createElement('p');
      emptyMsg.className = 'search-empty';
      emptyMsg.style.cssText = 'grid-column:1/-1;text-align:center;padding:3rem;color:#718096;font-size:1.05rem;';
      emptyMsg.innerHTML = '<i class="fas fa-search" style="display:block;font-size:2rem;margin-bottom:.75rem;opacity:.4;"></i>Sin resultados para "<strong>' + q + '</strong>"';
      grid.appendChild(emptyMsg);
    }
  } else if (emptyMsg) {
    emptyMsg.remove();
  }
}
</script>

<!-- SCRIPT DEL CONTADOR (soporta múltiples elementos y usa start/end) -->
<script>
(function(){
  function pad(n){ return n < 10 ? '0' + n : n; }

  // parse "dd/mm/YYYY HH:MM:SS" -> Date local
  function parseDMY_HMS(s){
    if(!s) return null;
    var parts = s.trim().split(' ');
    var d = (parts[0] || '').split('/');
    if (d.length !== 3) return null;
    var t = (parts[1] || '00:00:00').split(':');
    var day = parseInt(d[0],10), month = parseInt(d[1],10) - 1, year = parseInt(d[2],10);
    var hh = parseInt(t[0] || 0, 10), mm = parseInt(t[1] || 0, 10), ss = parseInt(t[2] || 0, 10);
    return new Date(year, month, day, hh, mm, ss);
  }

  function formatRemaining(ms){
    var total = Math.floor(ms / 1000);
    if (total < 0) total = 0;
    var days = Math.floor(total / 86400);
    var hours = Math.floor((total % 86400) / 3600);
    var minutes = Math.floor((total % 3600) / 60);
    var seconds = total % 60;
    var diasTxt = days > 0 ? days + (days === 1 ? ' día ' : ' días ') : '';
    return diasTxt + pad(hours) + ':' + pad(minutes) + ':' + pad(seconds);
  }

  // Selecciona todos los contadores (soporta múltiples "espacios")
  var items = document.querySelectorAll('.countdown-item');
  if (!items || items.length === 0) return;

  // Inicializa estado por cada elemento
  var elements = [];
  items.forEach(function(el){
    var startStr = el.getAttribute('data-start') || '';
    var endStr = el.getAttribute('data-end') || '';
    var startDate = parseDMY_HMS(startStr);
    var endDate = parseDMY_HMS(endStr);
    var textEl = el.querySelector('.countdown-text');

    // si no hay fechas, no mostrar nada
    if (!textEl) return;

    elements.push({
      el: el,
      start: startDate,
      end: endDate,
      textEl: textEl,
      finished: false
    });
  });

  function updateAll(){
    var now = new Date();
    elements.forEach(function(item){
      if (item.finished) return;

      var start = item.start;
      var end = item.end;
      var textEl = item.textEl;

      if (start && now < start) {
        // Antes de iniciar
        var diff = start - now;
        textEl.textContent = 'Faltan: ' + formatRemaining(diff) + ' para iniciar la venta';
        return;
      }

      if (end && now <= end) {
        // Durante la venta
        var diff = end - now;
        textEl.textContent = 'Faltan: ' + formatRemaining(diff) + ' para finalizar la venta';
        return;
      }

      // Después del end (o si no hay end)
      textEl.textContent = 'Venta finalizada';
      item.finished = true;
    });

    // Si todos finalizaron, detenemos el intervalo
    if (elements.every(function(it){ return it.finished; })) {
      clearInterval(intervalId);
    }
  }

  // Primera actualización y luego cada segundo
  updateAll();
  var intervalId = setInterval(updateAll, 1000);
})();

// ============= SCROLL A PRODUCTO ESPECÍFICO =============
(function() {
  // Si hay un hash en la URL (ej: #product-17), hacer scroll al producto
  if (window.location.hash) {
    setTimeout(function() {
      const target = document.querySelector(window.location.hash);
      if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        // Resaltar brevemente el producto
        target.style.transition = 'box-shadow 0.3s ease';
        target.style.boxShadow = '0 0 0 4px rgba(52, 152, 219, 0.5)';
        setTimeout(function() {
          target.style.boxShadow = '';
        }, 2000);
      }
    }, 300); // Pequeño delay para asegurar que el DOM esté listo
  }
})();
</script>

<?php require_once __DIR__ . '/includes/chat-support.php'; ?>
</body>
</html>