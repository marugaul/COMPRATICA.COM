<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

// ============= LOG DE DEBUG =============
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$logFile = $logDir . '/bienes_raices_debug.log';

function logDebug($msg, $data = null) {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($data) $line .= ' | ' . json_encode($data);
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

logDebug("BIENES_RAICES_START", ['uri' => $_SERVER['REQUEST_URI'] ?? '']);

// Cargar configuración (config.php ya maneja la sesión automáticamente)
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

logDebug("AFTER_CONFIG_LOAD", [
    'session_status' => session_status(),
    'session_id' => session_id(),
    'session_data' => $_SESSION
]);

// Inicializar carrito si no existe
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = ['groups' => []];
}

// Calcular cantidad de productos en el carrito
$cantidadProductos = 0;
if (isset($_SESSION['cart']['groups']) && is_array($_SESSION['cart']['groups'])) {
    foreach ($_SESSION['cart']['groups'] as $group) {
        if (isset($group['items']) && is_array($group['items'])) {
            foreach ($group['items'] as $item) {
                $cantidadProductos += (int)($item['qty'] ?? 0);
            }
        }
    }
}

// Verificar si el usuario está logueado (usando helper de config.php)
$isLoggedIn = is_logged_in();
$userName = $_SESSION['name'] ?? 'Usuario';

logDebug("USER_CHECK", [
    'isLoggedIn' => $isLoggedIn,
    'uid' => $_SESSION['uid'] ?? null,
    'userName' => $userName
]);

// Configurar charset para la respuesta
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
ini_set('default_charset', 'UTF-8');

$pdo = db();

// Obtener categorías de bienes raíces (las que empiezan con "BR:")
$categories = [];
try {
  $cats = $pdo->query("SELECT id, name, icon FROM categories WHERE active=1 AND name LIKE 'BR:%' ORDER BY display_order ASC");
  $categories = $cats->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log('[bienes-raices.php] No se pudieron cargar categorías: ' . $e->getMessage());
}

// ============= FILTROS Y BÚSQUEDA =============
$busqueda = trim($_GET['buscar'] ?? '');
$filtroCategoria = trim($_GET['categoria'] ?? '');
$filtroTipo = $_GET['tipo'] ?? 'todas'; // 'todas', 'venta', 'alquiler'
$filtroProvincia = trim($_GET['provincia'] ?? '');
$ordenamiento = $_GET['orden'] ?? 'recientes'; // 'recientes', 'precio_asc', 'precio_desc'

// Construir WHERE dinámico
$where = ["l.is_active = 1"];
// Solo mostrar propiedades con pago confirmado (free o confirmed)
$where[] = "(l.payment_status = 'free' OR l.payment_status = 'confirmed')";
$params = [];

// Filtro por búsqueda
if ($busqueda !== '') {
  $where[] = "(l.title LIKE ? OR l.description LIKE ? OR l.location LIKE ?)";
  $params[] = "%$busqueda%";
  $params[] = "%$busqueda%";
  $params[] = "%$busqueda%";
}

// Filtro por categoría
if ($filtroCategoria !== '') {
  $where[] = "c.name = ?";
  $params[] = $filtroCategoria;
}

// Filtro por tipo (venta/alquiler)
if ($filtroTipo === 'venta') {
  $where[] = "l.listing_type = 'sale'";
} elseif ($filtroTipo === 'alquiler') {
  $where[] = "l.listing_type = 'rent'";
}

// Filtro por provincia
if ($filtroProvincia !== '') {
  $where[] = "l.province = ?";
  $params[] = $filtroProvincia;
}

// Solo mostrar propiedades con fechas vigentes
$where[] = "(l.start_date IS NULL OR l.start_date <= datetime('now'))";
$where[] = "(l.end_date IS NULL OR l.end_date >= datetime('now'))";

$whereClause = implode(' AND ', $where);

// Ordenamiento
$orderBy = match($ordenamiento) {
  'precio_asc' => 'l.price ASC',
  'precio_desc' => 'l.price DESC',
  'area_desc' => 'l.area_m2 DESC',
  default => 'l.created_at DESC'
};

// Consulta de propiedades
$stmt = $pdo->prepare("
  SELECT l.*,
         c.name AS category_name,
         c.icon AS category_icon
  FROM real_estate_listings l
  LEFT JOIN categories c ON c.id = l.category_id
  WHERE $whereClause
  ORDER BY $orderBy
");
$stmt->execute($params);
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener provincias únicas para el filtro
$provinces = [];
try {
  $provStmt = $pdo->query("SELECT DISTINCT province FROM real_estate_listings WHERE province IS NOT NULL AND province != '' ORDER BY province");
  $provinces = $provStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
  error_log('[bienes-raices.php] Error al cargar provincias: ' . $e->getMessage());
}

logDebug("RENDERING_PAGE", ['listings_count' => count($listings)]);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bienes Raíces — <?php echo APP_NAME; ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <link rel="stylesheet" href="/assets/css/main.css">

  <style>
    :root {
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
      --success: #27ae60;
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

    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 3rem 2rem;
    }

    .page-header {
      text-align: center;
      margin-bottom: 3rem;
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
      margin-bottom: 1rem;
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

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
      gap: 2rem;
    }

    @media (max-width: 640px) {
      .grid {
        grid-template-columns: 1fr;
      }
    }

    .property-card {
      border: 1px solid var(--gray-300);
      border-radius: var(--radius);
      overflow: hidden;
      background: var(--cr-blanco);
      box-shadow: var(--shadow-sm);
      transition: var(--transition);
      cursor: pointer;
    }

    .property-card:hover {
      transform: translateY(-8px);
      box-shadow: var(--shadow-xl);
      border-color: var(--cr-azul);
    }

    .property-img-container {
      position: relative;
      width: 100%;
      height: 240px;
      overflow: hidden;
      background: var(--gray-100);
    }

    .property-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.3s ease;
    }

    .property-card:hover .property-img {
      transform: scale(1.05);
    }

    .property-badge {
      position: absolute;
      top: 1rem;
      left: 1rem;
      padding: 0.5rem 1rem;
      border-radius: var(--radius-sm);
      font-size: 0.875rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      background: var(--cr-azul);
      color: var(--cr-blanco);
    }

    .property-badge.rent {
      background: var(--cr-rojo);
    }

    .property-content {
      padding: 1.5rem;
    }

    .property-price {
      font-size: 1.75rem;
      font-weight: 800;
      color: var(--success);
      margin-bottom: 0.75rem;
    }

    .property-title {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--dark);
      margin-bottom: 0.75rem;
      line-height: 1.4;
    }

    .property-location {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--gray-600);
      font-size: 0.875rem;
      margin-bottom: 1rem;
    }

    .property-location i {
      color: var(--cr-rojo);
    }

    .property-features {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 0.75rem;
      margin-bottom: 1rem;
    }

    .feature-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.875rem;
      color: var(--gray-700);
    }

    .feature-item i {
      color: var(--cr-azul);
      width: 20px;
      text-align: center;
    }

    .property-category {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      background: var(--gray-100);
      border-radius: var(--radius-sm);
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--gray-700);
      margin-bottom: 1rem;
    }

    .property-actions {
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

    .btn-whatsapp {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.875rem;
      background: linear-gradient(135deg, #25D366, #128C7E);
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

    .btn-whatsapp:hover {
      background: linear-gradient(135deg, #128C7E, #075E54);
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    @media (max-width: 768px) {
      .filters-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
      }

      .search-input-group {
        min-width: 100%;
      }

      h1 {
        font-size: 2rem;
      }

      .container {
        padding: 2rem 1.25rem;
      }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container">
  <div class="page-header">
    <h1>🏡 Bienes Raíces en Costa Rica</h1>
    <p class="subtitle">Encontrá tu propiedad ideal. Casas, apartamentos, locales y terrenos en todo el país</p>
  </div>

  <!-- FILTROS Y BÚSQUEDA -->
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
            placeholder="Buscar por ubicación, título o descripción..."
            value="<?= htmlspecialchars($busqueda) ?>"
          >
        </div>
        <button type="submit" class="search-btn">
          <i class="fas fa-search"></i> Buscar
        </button>
      </div>

      <!-- Filtros por tipo (Venta/Alquiler) -->
      <div class="filters-row">
        <span class="filter-label">
          <i class="fas fa-filter"></i> Tipo:
        </span>
        <div class="filter-pills">
          <a href="?buscar=<?= urlencode($busqueda) ?>&tipo=todas&categoria=<?= urlencode($filtroCategoria) ?>&provincia=<?= urlencode($filtroProvincia) ?>&orden=<?= urlencode($ordenamiento) ?>"
             class="filter-pill <?= $filtroTipo === 'todas' ? 'active' : '' ?>">
            Todas
          </a>
          <a href="?buscar=<?= urlencode($busqueda) ?>&tipo=venta&categoria=<?= urlencode($filtroCategoria) ?>&provincia=<?= urlencode($filtroProvincia) ?>&orden=<?= urlencode($ordenamiento) ?>"
             class="filter-pill <?= $filtroTipo === 'venta' ? 'active' : '' ?>">
            🏷️ Venta
          </a>
          <a href="?buscar=<?= urlencode($busqueda) ?>&tipo=alquiler&categoria=<?= urlencode($filtroCategoria) ?>&provincia=<?= urlencode($filtroProvincia) ?>&orden=<?= urlencode($ordenamiento) ?>"
             class="filter-pill <?= $filtroTipo === 'alquiler' ? 'active' : '' ?>">
            🔑 Alquiler
          </a>
        </div>
      </div>

      <!-- Filtro por Categoría -->
      <div class="filters-row">
        <span class="filter-label">
          <i class="fas fa-folder-open"></i> Categoría:
        </span>
        <select name="categoria" class="filter-select" onchange="this.form.submit()">
          <option value="">Todas las categorías</option>
          <?php foreach($categories as $cat):
            $displayName = str_replace('BR: ', '', $cat['name']);
          ?>
            <option value="<?= htmlspecialchars($cat['name']) ?>" <?= $filtroCategoria === $cat['name'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($displayName) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Filtro por Provincia -->
      <?php if (!empty($provinces)): ?>
      <div class="filters-row">
        <span class="filter-label">
          <i class="fas fa-map-marker-alt"></i> Provincia:
        </span>
        <select name="provincia" class="filter-select" onchange="this.form.submit()">
          <option value="">Todas las provincias</option>
          <?php foreach($provinces as $prov): ?>
            <option value="<?= htmlspecialchars($prov) ?>" <?= $filtroProvincia === $prov ? 'selected' : '' ?>>
              <?= htmlspecialchars($prov) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <!-- Ordenamiento -->
      <div class="filters-row">
        <span class="filter-label">
          <i class="fas fa-sort"></i> Ordenar por:
        </span>
        <select name="orden" class="filter-select" onchange="this.form.submit()">
          <option value="recientes" <?= $ordenamiento === 'recientes' ? 'selected' : '' ?>>
            Más recientes
          </option>
          <option value="precio_asc" <?= $ordenamiento === 'precio_asc' ? 'selected' : '' ?>>
            Precio (menor a mayor)
          </option>
          <option value="precio_desc" <?= $ordenamiento === 'precio_desc' ? 'selected' : '' ?>>
            Precio (mayor a menor)
          </option>
          <option value="area_desc" <?= $ordenamiento === 'area_desc' ? 'selected' : '' ?>>
            Área (mayor a menor)
          </option>
        </select>
      </div>

      <input type="hidden" name="tipo" value="<?= htmlspecialchars($filtroTipo) ?>">
    </form>

    <!-- Contador de resultados -->
    <div class="results-count">
      <i class="fas fa-info-circle"></i>
      Mostrando <strong><?= count($listings) ?></strong> propiedad<?= count($listings) !== 1 ? 'es' : '' ?>
      <?php if ($busqueda): ?>
        que coinciden con "<strong><?= htmlspecialchars($busqueda) ?></strong>"
      <?php endif; ?>
      <?php if ($filtroCategoria): ?>
        en la categoría <strong><?= str_replace('BR: ', '', htmlspecialchars($filtroCategoria)) ?></strong>
      <?php endif; ?>
    </div>
  </div>

  <!-- GRID DE PROPIEDADES -->
  <div class="grid">
    <?php foreach ($listings as $listing):
      $images = !empty($listing['images']) ? json_decode($listing['images'], true) : [];
      $firstImage = !empty($images) && is_array($images) ? convert_google_drive_url($images[0]) : 'assets/placeholder.jpg';

      // Formatear precio
      $price = $listing['price'];
      $currency = $listing['currency'] ?? 'CRC';
      $priceFormatted = $currency === 'USD'
        ? '$' . number_format($price, 2)
        : '₡' . number_format($price, 0);

      // Tipo de operación
      $operationType = $listing['listing_type'] === 'sale' ? 'Venta' : 'Alquiler';
      $badgeClass = $listing['listing_type'] === 'sale' ? '' : 'rent';

      // Características
      $features = !empty($listing['features']) ? json_decode($listing['features'], true) : [];

      // Nombre de categoría sin prefijo "BR:"
      $categoryDisplay = str_replace('BR: ', '', $listing['category_name'] ?? '');

      // URL de WhatsApp
      $whatsappPhone = $listing['contact_whatsapp'] ?? $listing['contact_phone'] ?? '';
      $whatsappPhone = preg_replace('/[^0-9]/', '', $whatsappPhone);
      $whatsappMessage = urlencode("Hola, me interesa la propiedad: " . $listing['title']);
      $whatsappUrl = "https://wa.me/506{$whatsappPhone}?text={$whatsappMessage}";
    ?>
      <div class="property-card" onclick="window.location.href='propiedad-detalle?id=<?= (int)$listing['id'] ?>'">
        <div class="property-img-container">
          <img src="<?= htmlspecialchars($firstImage) ?>" alt="<?= htmlspecialchars($listing['title']) ?>" class="property-img">
          <div class="property-badge <?= $badgeClass ?>"><?= $operationType ?></div>
        </div>

        <div class="property-content">
          <div class="property-price"><?= $priceFormatted ?></div>
          <h3 class="property-title"><?= htmlspecialchars($listing['title']) ?></h3>

          <?php if (!empty($listing['location'])): ?>
          <div class="property-location">
            <i class="fas fa-map-marker-alt"></i>
            <span><?= htmlspecialchars($listing['location']) ?></span>
          </div>
          <?php endif; ?>

          <div class="property-category">
            <i class="<?= htmlspecialchars($listing['category_icon'] ?? 'fa-home') ?>"></i>
            <?= htmlspecialchars($categoryDisplay) ?>
          </div>

          <div class="property-features">
            <?php if ($listing['bedrooms'] > 0): ?>
            <div class="feature-item">
              <i class="fas fa-bed"></i>
              <span><?= (int)$listing['bedrooms'] ?> habitaciones</span>
            </div>
            <?php endif; ?>

            <?php if ($listing['bathrooms'] > 0): ?>
            <div class="feature-item">
              <i class="fas fa-bath"></i>
              <span><?= (int)$listing['bathrooms'] ?> baños</span>
            </div>
            <?php endif; ?>

            <?php if ($listing['area_m2'] > 0): ?>
            <div class="feature-item">
              <i class="fas fa-ruler-combined"></i>
              <span><?= number_format($listing['area_m2'], 0) ?> m²</span>
            </div>
            <?php endif; ?>

            <?php if ($listing['parking_spaces'] > 0): ?>
            <div class="feature-item">
              <i class="fas fa-car"></i>
              <span><?= (int)$listing['parking_spaces'] ?> parqueos</span>
            </div>
            <?php endif; ?>
          </div>

          <div class="property-actions" onclick="event.stopPropagation()">
            <a href="propiedad-detalle?id=<?= (int)$listing['id'] ?>" class="btn-primary">
              <i class="fas fa-eye"></i> Ver detalles
            </a>
            <?php if (!empty($whatsappPhone)): ?>
            <a href="<?= $whatsappUrl ?>" target="_blank" class="btn-whatsapp" title="Contactar por WhatsApp">
              <i class="fab fa-whatsapp"></i>
            </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if (empty($listings)): ?>
      <div class="property-card">
        <div style="padding: 3rem 2rem; text-align: center;">
          <i class="fas fa-home" style="font-size: 3rem; color: var(--gray-300); margin-bottom: 1rem;"></i>
          <h3 style="margin: 0 0 0.5rem 0;">No hay propiedades disponibles</h3>
          <p style="margin: 0; color: var(--gray-500);">Intenta ajustar los filtros de búsqueda.</p>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="/assets/js/cart.js"></script>
</body>
</html>
