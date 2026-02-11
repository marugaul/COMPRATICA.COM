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

// Obtener categorías disponibles
$categories = [];
try {
  $cats = $pdo->query("SELECT id, name, icon FROM categories WHERE active=1 ORDER BY display_order ASC");
  $categories = $cats->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log('[venta-garaje.php] No se pudieron cargar categorías: ' . $e->getMessage());
}

// ============= ETAPA 1: FILTROS Y BÚSQUEDA =============
$busqueda = trim($_GET['buscar'] ?? '');
$filtroEstado = $_GET['estado'] ?? 'todas'; // 'todas', 'vivo', 'proximas'
$ordenamiento = $_GET['orden'] ?? 'inicio_asc'; // 'inicio_asc', 'inicio_desc', 'finalizando', 'recientes'
$filtroCategoria = trim($_GET['categoria'] ?? ''); // Categoría seleccionada

// Construir WHERE dinámico
$where = ["s.is_active = 1"];
$params = [];
$salesIdsFromProducts = [];

// Búsqueda MEJORADA: Incluye búsqueda en productos
if ($busqueda !== '') {
  // Buscar ventas que tienen productos con el término de búsqueda
  $stmtProducts = $pdo->prepare("
    SELECT DISTINCT p.sale_id
    FROM products p
    WHERE p.sale_id IS NOT NULL
      AND (p.name LIKE ? OR p.description LIKE ?)
  ");
  $stmtProducts->execute(["%$busqueda%", "%$busqueda%"]);
  $salesIdsFromProducts = $stmtProducts->fetchAll(PDO::FETCH_COLUMN);

  // Buscar por título de venta, nombre de afiliado, O ventas con productos que coinciden
  if (!empty($salesIdsFromProducts)) {
    $placeholders = implode(',', array_fill(0, count($salesIdsFromProducts), '?'));
    $where[] = "(s.title LIKE ? OR a.name LIKE ? OR s.id IN ($placeholders))";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    foreach ($salesIdsFromProducts as $sid) {
      $params[] = $sid;
    }
  } else {
    $where[] = "(s.title LIKE ? OR a.name LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
  }
}

// Filtro por CATEGORÍA: Buscar ventas que tienen productos de esa categoría
if ($filtroCategoria !== '') {
  $stmtCategoryProducts = $pdo->prepare("
    SELECT DISTINCT p.sale_id
    FROM products p
    WHERE p.sale_id IS NOT NULL
      AND p.category = ?
      AND p.active = 1
  ");
  $stmtCategoryProducts->execute([$filtroCategoria]);
  $salesIdsFromCategory = $stmtCategoryProducts->fetchAll(PDO::FETCH_COLUMN);

  if (!empty($salesIdsFromCategory)) {
    $placeholders = implode(',', array_fill(0, count($salesIdsFromCategory), '?'));
    $where[] = "s.id IN ($placeholders)";
    foreach ($salesIdsFromCategory as $sid) {
      $params[] = $sid;
    }
  } else {
    // Si no hay productos de esa categoría, forzar resultado vacío
    $where[] = "1=0";
  }
}

// Filtro por estado (requiere calcular en PHP después)
// Por ahora traemos todas las activas

$whereClause = implode(' AND ', $where);

// Ordenamiento
$orderBy = match($ordenamiento) {
  'inicio_desc' => 's.start_at DESC',
  'finalizando' => 's.end_at ASC',
  'recientes' => 's.created_at DESC',
  default => 's.start_at ASC'
};

// Consulta con contador de productos ACTIVOS
$stmt = $pdo->prepare("
  SELECT s.*,
         a.name AS affiliate_name,
         (SELECT COUNT(*) FROM products p WHERE p.sale_id = s.id AND p.active = 1) AS product_count
  FROM sales s
  JOIN affiliates a ON a.id = s.affiliate_id
  WHERE $whereClause
  ORDER BY $orderBy
");
$stmt->execute($params);
$allSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener productos coincidentes para mostrar (por búsqueda o categoría)
$matchingProducts = [];
if ($busqueda !== '' || $filtroCategoria !== '') {
  foreach ($allSales as $sale) {
    if ($busqueda !== '') {
      // Búsqueda por texto
      $stmtMatchProducts = $pdo->prepare("
        SELECT id, name, price, currency, image
        FROM products
        WHERE sale_id = ?
          AND (name LIKE ? OR description LIKE ?)
          AND active = 1
        LIMIT 10
      ");
      $stmtMatchProducts->execute([$sale['id'], "%$busqueda%", "%$busqueda%"]);
    } else {
      // Filtro por categoría
      $stmtMatchProducts = $pdo->prepare("
        SELECT id, name, price, currency, image
        FROM products
        WHERE sale_id = ?
          AND category = ?
          AND active = 1
        LIMIT 10
      ");
      $stmtMatchProducts->execute([$sale['id'], $filtroCategoria]);
    }

    $products = $stmtMatchProducts->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($products)) {
      $matchingProducts[$sale['id']] = $products;
    }
  }
}

// Filtrar por estado si es necesario
$nowTs = time();
$sales = [];
foreach ($allSales as $sale) {
  $st = strtotime($sale['start_at']);
  $en = strtotime($sale['end_at']);

  $estado = 'Finalizada';
  if ($nowTs >= $st && $nowTs <= $en) {
    $estado = 'En vivo';
  } elseif ($nowTs < $st) {
    $estado = 'Próxima';
  }

  // Aplicar filtro de estado
  if ($filtroEstado === 'todas' ||
      ($filtroEstado === 'vivo' && $estado === 'En vivo') ||
      ($filtroEstado === 'proximas' && $estado === 'Próxima')) {
    $sales[] = $sale;
  }
}

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

    .chip-private {
      background: linear-gradient(135deg, rgba(243, 156, 18, 0.15), rgba(243, 156, 18, 0.08));
      border: 1px solid rgba(243, 156, 18, 0.4);
      color: #d68910;
      font-weight: 600;
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

    /* ETAPA 2: Progress Bar */
    .progress-bar-container {
      margin-top: 0.75rem;
      width: 100%;
      height: 8px;
      background: linear-gradient(90deg, #e9ecef 0%, #dee2e6 100%);
      border-radius: 10px;
      overflow: hidden;
      position: relative;
    }

    .progress-bar {
      height: 100%;
      background: linear-gradient(90deg, var(--success) 0%, #229954 100%);
      border-radius: 10px;
      transition: width 0.5s ease-in-out;
      position: relative;
      box-shadow: 0 0 10px rgba(39, 174, 96, 0.5);
      animation: progress-glow 2s ease-in-out infinite;
    }

    @keyframes progress-glow {
      0%, 100% {
        box-shadow: 0 0 10px rgba(39, 174, 96, 0.5);
      }
      50% {
        box-shadow: 0 0 15px rgba(39, 174, 96, 0.8);
      }
    }

    .progress-label {
      margin-top: 0.5rem;
      text-align: center;
      font-size: 0.8125rem;
      color: var(--gray-600);
      font-weight: 600;
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

    /* FILTROS Y BÚSQUEDA - ETAPA 1 */
    .filters-section {
      background: var(--cr-blanco);
      border-radius: var(--radius);
      padding: 2rem;
      margin-bottom: 3rem;
      box-shadow: var(--shadow-md);
      border: 1px solid var(--gray-300);
    }

    .search-bar {
      display: flex;
      gap: 1rem;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
    }

    .search-input-group {
      flex: 1;
      min-width: 280px;
      position: relative;
    }

    .search-input {
      width: 100%;
      padding: 0.875rem 1rem 0.875rem 3rem;
      border: 2px solid var(--gray-300);
      border-radius: var(--radius-sm);
      font-size: 1rem;
      transition: var(--transition);
    }

    .search-input:focus {
      outline: none;
      border-color: var(--cr-azul);
      box-shadow: 0 0 0 3px rgba(0, 43, 127, 0.1);
    }

    .search-icon {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--gray-500);
      font-size: 1.125rem;
      pointer-events: none;
    }

    .search-btn {
      padding: 0.875rem 2rem;
      background: linear-gradient(135deg, var(--cr-azul) 0%, var(--cr-azul-claro) 100%);
      color: var(--cr-blanco);
      border: none;
      border-radius: var(--radius-sm);
      font-weight: 700;
      font-size: 1rem;
      cursor: pointer;
      transition: var(--transition);
      white-space: nowrap;
    }

    .search-btn:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .filters-row {
      display: flex;
      gap: 1rem;
      align-items: center;
      flex-wrap: wrap;
    }

    .filter-label {
      font-weight: 600;
      color: var(--gray-700);
      font-size: 0.9375rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .filter-pills {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .filter-pill {
      padding: 0.625rem 1.25rem;
      border: 2px solid var(--gray-300);
      border-radius: 999px;
      background: var(--cr-blanco);
      color: var(--gray-700);
      font-weight: 600;
      font-size: 0.875rem;
      text-decoration: none;
      transition: var(--transition);
      cursor: pointer;
    }

    .filter-pill:hover {
      border-color: var(--cr-azul);
      color: var(--cr-azul);
      background: rgba(0, 43, 127, 0.05);
    }

    .filter-pill.active {
      background: linear-gradient(135deg, var(--cr-azul) 0%, var(--cr-azul-claro) 100%);
      border-color: var(--cr-azul);
      color: var(--cr-blanco);
    }

    .filter-select {
      padding: 0.625rem 2.5rem 0.625rem 1rem;
      border: 2px solid var(--gray-300);
      border-radius: var(--radius-sm);
      font-size: 0.9375rem;
      font-weight: 600;
      color: var(--gray-700);
      background: var(--cr-blanco);
      cursor: pointer;
      transition: var(--transition);
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%234a5568' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 0.875rem center;
    }

    .filter-select:focus {
      outline: none;
      border-color: var(--cr-azul);
      box-shadow: 0 0 0 3px rgba(0, 43, 127, 0.1);
    }

    .results-count {
      margin-top: 1rem;
      padding-top: 1rem;
      border-top: 1px solid var(--gray-300);
      color: var(--gray-600);
      font-size: 0.9375rem;
    }

    .results-count strong {
      color: var(--cr-azul);
      font-weight: 700;
    }

    /* MEJORAS EN CARDS - ETAPA 1 */
    .card-location {
      margin: 0 1rem 0.75rem 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.875rem;
      color: var(--gray-600);
    }

    .card-location i {
      color: var(--cr-rojo);
    }

    .card-product-count {
      margin: 0 1rem 1rem 1rem;
      padding: 0.625rem 1rem;
      background: linear-gradient(135deg, rgba(0, 43, 127, 0.05), rgba(0, 43, 127, 0.02));
      border-left: 3px solid var(--cr-azul);
      border-radius: 4px;
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--cr-azul);
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex-wrap: wrap;
    }

    .card-product-count.low-stock {
      background: linear-gradient(135deg, rgba(243, 156, 18, 0.1), rgba(243, 156, 18, 0.05));
      border-left-color: var(--warning);
      color: #d68910;
    }

    .stock-badge {
      margin-left: auto;
      padding: 0.25rem 0.625rem;
      background: linear-gradient(135deg, var(--warning), #e67e22);
      color: white;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      animation: pulse-badge 1.5s ease-in-out infinite;
    }

    @keyframes pulse-badge {
      0%, 100% {
        transform: scale(1);
      }
      50% {
        transform: scale(1.05);
      }
    }

    .card-tags {
      margin: 0 1rem 1rem 1rem;
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }

    /* Botones de compartir */
    .share-buttons {
      display: flex;
      gap: 0.5rem;
      flex-shrink: 0;
    }

    .share-btn {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      transition: all 0.3s ease;
      font-size: 0.875rem;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .share-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .share-btn.whatsapp {
      background: linear-gradient(135deg, #25D366, #128C7E);
      color: white;
    }

    .share-btn.whatsapp:hover {
      background: linear-gradient(135deg, #128C7E, #075E54);
    }

    .share-btn.facebook {
      background: linear-gradient(135deg, #1877F2, #1864CC);
      color: white;
    }

    .share-btn.facebook:hover {
      background: linear-gradient(135deg, #1864CC, #1555B0);
    }

    .share-btn.instagram {
      background: linear-gradient(135deg, #E4405F, #C13584);
      color: white;
      border: none;
      cursor: pointer;
    }

    .share-btn.instagram:hover {
      background: linear-gradient(135deg, #C13584, #833AB4);
    }

    .share-btn.tiktok {
      background: linear-gradient(135deg, #000000, #1a1a1a);
      color: white;
      border: none;
      cursor: pointer;
    }

    .share-btn.tiktok:hover {
      background: linear-gradient(135deg, #1a1a1a, #333333);
    }

    .tag {
      padding: 0.375rem 0.75rem;
      background: var(--gray-100);
      border: 1px solid var(--gray-300);
      border-radius: 999px;
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--gray-700);
    }

    /* PRODUCTOS ENCONTRADOS */
    .found-products {
      margin: 0 1rem 1rem 1rem;
      padding: 1rem;
      background: linear-gradient(135deg, rgba(39, 174, 96, 0.08), rgba(39, 174, 96, 0.02));
      border-left: 3px solid var(--success);
      border-radius: var(--radius-sm);
    }

    .found-products-title {
      font-weight: 700;
      color: var(--success);
      font-size: 0.875rem;
      margin-bottom: 0.75rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .found-product-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.625rem;
      background: white;
      border-radius: 6px;
      margin-bottom: 0.5rem;
      transition: var(--transition);
      border: 1px solid var(--gray-200);
    }

    .found-product-item:last-child {
      margin-bottom: 0;
    }

    .found-product-item:hover {
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      transform: translateX(4px);
    }

    .found-product-img {
      width: 48px;
      height: 48px;
      object-fit: cover;
      border-radius: 4px;
      border: 1px solid var(--gray-300);
      flex-shrink: 0;
    }

    .found-product-info {
      flex: 1;
      min-width: 0;
    }

    .found-product-name {
      font-weight: 600;
      font-size: 0.875rem;
      color: var(--dark);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .found-product-price {
      font-size: 0.8125rem;
      color: var(--success);
      font-weight: 700;
      margin-top: 0.125rem;
    }

    .found-product-btn {
      padding: 0.5rem 0.875rem;
      background: linear-gradient(135deg, var(--success), #229954);
      color: white;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      font-size: 0.8125rem;
      cursor: pointer;
      transition: var(--transition);
      white-space: nowrap;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.375rem;
    }

    .found-product-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
    }

    /* RESPONSIVE */
    @media (max-width: 768px) {
      .header {
        padding: 0.875rem 1rem;
      }

      .filters-section {
        padding: 1.5rem;
      }

      .search-input-group {
        min-width: 100%;
      }

      .filters-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
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
  <a href="index" class="logo">
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
    
    <a href="servicios" class="menu-item">
      <i class="fas fa-briefcase"></i>
      <span>Servicios</span>
    </a>

    <a href="bienes-raices" class="menu-item">
      <i class="fas fa-home"></i>
      <span>Bienes Raíces</span>
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

<div class="container">
  <div class="page-header">
    <h1>Espacios de Venta de Garaje</h1>
    <p class="subtitle">Descubrí ventas de garaje activas y próximas cerca de ti. Tesoros únicos a precios increíbles</p>
  </div>

  <!-- ETAPA 1: FILTROS Y BÚSQUEDA -->
  <div class="filters-section">
    <form method="get" action="">
      <!-- Barra de búsqueda -->
      <div class="search-bar">
        <div class="search-input-group">
          <i class="fas fa-search search-icon"></i>
          <input
            type="text"
            name="buscar"
            class="search-input"
            placeholder="Buscar por título o vendedor..."
            value="<?= htmlspecialchars($busqueda) ?>"
          >
        </div>
        <button type="submit" class="search-btn">
          <i class="fas fa-search"></i> Buscar
        </button>
      </div>

      <!-- Filtros por estado -->
      <div class="filters-row">
        <span class="filter-label">
          <i class="fas fa-filter"></i> Estado:
        </span>
        <div class="filter-pills">
          <a href="?buscar=<?= urlencode($busqueda) ?>&estado=todas&orden=<?= urlencode($ordenamiento) ?>&categoria=<?= urlencode($filtroCategoria) ?>"
             class="filter-pill <?= $filtroEstado === 'todas' ? 'active' : '' ?>">
            Todas
          </a>
          <a href="?buscar=<?= urlencode($busqueda) ?>&estado=vivo&orden=<?= urlencode($ordenamiento) ?>&categoria=<?= urlencode($filtroCategoria) ?>"
             class="filter-pill <?= $filtroEstado === 'vivo' ? 'active' : '' ?>">
            🔴 En Vivo
          </a>
          <a href="?buscar=<?= urlencode($busqueda) ?>&estado=proximas&orden=<?= urlencode($ordenamiento) ?>&categoria=<?= urlencode($filtroCategoria) ?>"
             class="filter-pill <?= $filtroEstado === 'proximas' ? 'active' : '' ?>">
            🔜 Próximas
          </a>
        </div>
      </div>

      <!-- Ordenamiento -->
      <div class="filters-row" style="margin-top: 1rem;">
        <span class="filter-label">
          <i class="fas fa-sort"></i> Ordenar por:
        </span>
        <select name="orden" class="filter-select" onchange="this.form.submit()">
          <option value="inicio_asc" <?= $ordenamiento === 'inicio_asc' ? 'selected' : '' ?>>
            Fecha inicio (más cercana)
          </option>
          <option value="inicio_desc" <?= $ordenamiento === 'inicio_desc' ? 'selected' : '' ?>>
            Fecha inicio (más lejana)
          </option>
          <option value="finalizando" <?= $ordenamiento === 'finalizando' ? 'selected' : '' ?>>
            Finalizando pronto
          </option>
          <option value="recientes" <?= $ordenamiento === 'recientes' ? 'selected' : '' ?>>
            Más recientes
          </option>
        </select>
      </div>

      <!-- Filtro por Categoría -->
      <div class="filters-row" style="margin-top: 1rem;">
        <span class="filter-label">
          <i class="fas fa-folder-open"></i> Categoría:
        </span>
        <select name="categoria" class="filter-select" onchange="this.form.submit()">
          <option value="">Todas las categorías</option>
          <?php foreach($categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat['name']) ?>" <?= $filtroCategoria === $cat['name'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <input type="hidden" name="estado" value="<?= htmlspecialchars($filtroEstado) ?>">
    </form>

    <!-- Contador de resultados -->
    <div class="results-count">
      <i class="fas fa-info-circle"></i>
      Mostrando <strong><?= count($sales) ?></strong> venta<?= count($sales) !== 1 ? 's' : '' ?>
      <?php if ($busqueda): ?>
        que coinciden con "<strong><?= htmlspecialchars($busqueda) ?></strong>"
      <?php endif; ?>
      <?php if ($filtroCategoria): ?>
        en la categoría <strong><?= htmlspecialchars($filtroCategoria) ?></strong>
      <?php endif; ?>
    </div>
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
          <?php if (!empty($s['is_private'])): ?>
            <span class="chip chip-private">
              <i class="fas fa-lock"></i>
            </span>
          <?php endif; ?>
        </div>

        <div style="display: flex; align-items: center; justify-content: space-between; margin: 0 1rem;">
          <div style="flex: 1;">
            <h3 style="margin: 0;"><?php echo htmlspecialchars($s['title']); ?></h3>
            <p style="margin: 0.5rem 0 0 0;">
              <strong><?php echo htmlspecialchars($s['affiliate_name']); ?></strong>
            </p>
          </div>

          <!-- Botones de compartir -->
          <?php
            $saleUrl = (defined('APP_URL') ? APP_URL : 'https://compratica.com') . '/store.php?sale_id=' . (int)$s['id'];
            $whatsappText = urlencode('¡Mirá esta venta de garaje! ' . $s['title'] . ' - ' . $saleUrl);
            $facebookUrl = 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($saleUrl);
            $whatsappUrl = 'https://wa.me/?text=' . $whatsappText;
          ?>
          <div class="share-buttons">
            <a href="<?php echo $whatsappUrl; ?>" target="_blank" class="share-btn whatsapp" title="Compartir por WhatsApp">
              <i class="fab fa-whatsapp"></i>
            </a>
            <a href="<?php echo $facebookUrl; ?>" target="_blank" class="share-btn facebook" title="Compartir en Facebook">
              <i class="fab fa-facebook-f"></i>
            </a>
            <button onclick="copyToClipboard('<?php echo addslashes($saleUrl); ?>', 'Instagram')" class="share-btn instagram" title="Copiar link para Instagram">
              <i class="fab fa-instagram"></i>
            </button>
            <button onclick="copyToClipboard('<?php echo addslashes($saleUrl); ?>', 'TikTok')" class="share-btn tiktok" title="Copiar link para TikTok">
              <i class="fab fa-tiktok"></i>
            </button>
          </div>
        </div>

        <!-- ETAPA 1: Ubicación -->
        <?php if (!empty($s['location'])): ?>
        <div class="card-location">
          <i class="fas fa-map-marker-alt"></i>
          <span><?php echo htmlspecialchars($s['location']); ?></span>
        </div>
        <?php endif; ?>

        <!-- ETAPA 2: Contador de productos con badge de stock -->
        <?php if (!empty($s['product_count'])):
          $productCount = (int)$s['product_count'];
          $lowStock = $productCount > 0 && $productCount <= 5;
        ?>
        <div class="card-product-count<?php echo $lowStock ? ' low-stock' : ''; ?>">
          <i class="fas fa-box-open"></i>
          <?php echo $productCount; ?> producto<?php echo $productCount !== 1 ? 's' : ''; ?> disponible<?php echo $productCount !== 1 ? 's' : ''; ?>
          <?php if ($lowStock): ?>
            <span class="stock-badge">
              <i class="fas fa-exclamation-triangle"></i> Últimas unidades
            </span>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ETAPA 1: Tags/Categorías -->
        <?php
        $tags = [];
        if (!empty($s['tags'])) {
          $decoded = json_decode($s['tags'], true);
          if (is_array($decoded)) {
            $tags = $decoded;
          }
        }
        if (!empty($tags)):
        ?>
        <div class="card-tags">
          <?php foreach ($tags as $tag): ?>
          <span class="tag">
            <i class="fas fa-tag" style="font-size: 0.7rem;"></i>
            <?php echo htmlspecialchars($tag); ?>
          </span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- PRODUCTOS ENCONTRADOS en la búsqueda o categoría -->
        <?php if (!empty($matchingProducts[$s['id']])):
          $allProducts = $matchingProducts[$s['id']];
          $totalProducts = count($allProducts);
          $displayProducts = array_slice($allProducts, 0, 3); // Mostrar solo los primeros 3
        ?>
        <div class="found-products">
          <div class="found-products-title">
            <?php if ($busqueda !== ''): ?>
              <i class="fas fa-search"></i>
              <span>Productos que coinciden con "<?php echo htmlspecialchars($busqueda); ?>":</span>
            <?php else: ?>
              <i class="fas fa-folder-open"></i>
              <span>Productos en categoría <?php echo htmlspecialchars($filtroCategoria); ?>:</span>
            <?php endif; ?>
          </div>
          <?php foreach ($displayProducts as $product):
            $productImg = !empty($product['image']) ? 'uploads/' . ltrim($product['image'], '/') : 'assets/placeholder.jpg';
            $productPrice = $product['currency'] === 'USD'
              ? '$' . number_format($product['price'], 2)
              : '₡' . number_format($product['price'], 0);
          ?>
          <div class="found-product-item">
            <img src="<?php echo htmlspecialchars($productImg); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="found-product-img">
            <div class="found-product-info">
              <div class="found-product-name"><?php echo htmlspecialchars($product['name']); ?></div>
              <div class="found-product-price"><?php echo $productPrice; ?></div>
            </div>
            <a href="store?sale_id=<?php echo (int)$s['id']; ?>&product_id=<?php echo (int)$product['id']; ?>#product-<?php echo (int)$product['id']; ?>" class="found-product-btn">
              <i class="fas fa-shopping-cart"></i>
              <span>Ver producto</span>
            </a>
          </div>
          <?php endforeach; ?>

          <?php if ($totalProducts > 3): ?>
          <div style="margin-top: 0.75rem; text-align: center;">
            <a href="store?sale_id=<?php echo (int)$s['id']; ?>"
               class="found-product-btn"
               style="display: inline-flex; background: linear-gradient(135deg, var(--cr-azul), var(--cr-azul-claro));">
              <i class="fas fa-eye"></i>
              <span>Ver los <?php echo $totalProducts; ?> productos</span>
            </a>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

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

          <!-- ETAPA 2: Progress Bar -->
          <?php if ($state === 'En vivo'):
            $totalDuration = $en - $st;
            $elapsed = $nowTs - $st;
            $percentage = ($elapsed / $totalDuration) * 100;
            $percentage = max(0, min(100, $percentage)); // Clamp entre 0-100
          ?>
          <div class="progress-bar-container">
            <div class="progress-bar" style="width: <?php echo number_format($percentage, 2); ?>%"></div>
          </div>
          <div class="progress-label">
            <span><?php echo number_format($percentage, 0); ?>% transcurrido</span>
          </div>
          <?php endif; ?>
        </div>

        <div class="actions">
          <?php if ($state === 'En vivo'): ?>
            <a class="btn-primary" href="store?sale_id=<?php echo (int)$s['id']; ?>">
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
      <a href="index">Inicio</a>
      <a href="servicios">Servicios</a>
      <a href="venta-garaje">Venta de Garaje</a>
      <a href="bienes-raices">Bienes Raíces</a>
      <span style="opacity: 0.5; cursor: not-allowed;">Emprendedores (Muy Pronto)</span>
      <span style="opacity: 0.5; cursor: not-allowed;">Emprendedoras (Muy Pronto)</span>
    </div>
    <div class="footer-section">
      <h3>Para Emprendedores</h3>
      <a href="affiliate/register.php">Publicar mi venta</a>
      <a href="affiliate/login.php">Portal de Afiliados</a>
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
// ============= FUNCIÓN COPIAR LINK =============
function copyToClipboard(text, platform) {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(function() {
      alert('¡Link copiado! Ahora podés pegarlo en ' + platform);
    }).catch(function(err) {
      // Fallback para navegadores antiguos
      fallbackCopy(text, platform);
    });
  } else {
    fallbackCopy(text, platform);
  }
}

function fallbackCopy(text, platform) {
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