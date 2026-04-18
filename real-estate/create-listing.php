<?php
// real-estate/create-listing.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Verificar autenticación
$agent_id = (int)($_SESSION['agent_id'] ?? $_SESSION['user_id'] ?? $_SESSION['uid'] ?? 0);
if ($agent_id <= 0) {
  header('Location: login.php');
  exit;
}

$pdo = db();
$agent_name = $_SESSION['agent_name'] ?? $_SESSION['user_name'] ?? $_SESSION['name'] ?? 'Usuario';

$msg = '';
$ok = false;

// Obtener categorías de bienes raíces
$categories = [];
try {
  $catStmt = $pdo->query("SELECT id, name, icon FROM categories WHERE active=1 AND name LIKE 'BR:%' ORDER BY display_order ASC");
  $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log('[create-listing.php] Error al cargar categorías: ' . $e->getMessage());
}

// Obtener planes de precios
$pricing_plans = [];
try {
  $planStmt = $pdo->query("SELECT * FROM listing_pricing WHERE is_active=1 ORDER BY display_order ASC");
  $pricing_plans = $planStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log('[create-listing.php] Error al cargar planes: ' . $e->getMessage());
}

// Provincias de Costa Rica
$provinces = [
  'San José',
  'Alajuela',
  'Cartago',
  'Heredia',
  'Guanacaste',
  'Puntarenas',
  'Limón'
];

// Características comunes de propiedades
$available_features = [
  'Piscina',
  'Jardín',
  'Terraza',
  'Balcón',
  'Aire Acondicionado',
  'Calentador de Agua',
  'Cocina Equipada',
  'Línea Blanca',
  'Amueblado',
  'Seguridad 24/7',
  'Portón Eléctrico',
  'Cisterna',
  'Tanque de Agua',
  'Zona de Lavandería',
  'Cuarto de Servicio',
  'Área de BBQ',
  'Gimnasio',
  'Sala de Juegos',
  'Oficina/Estudio',
  'Walk-in Closet'
];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    // Validar campos requeridos
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $currency = trim($_POST['currency'] ?? 'CRC');
    $listing_type = trim($_POST['listing_type'] ?? 'sale');
    $pricing_plan_id = (int)($_POST['pricing_plan_id'] ?? 0);

    // Ubicación
    $province = trim($_POST['province'] ?? '');
    $canton = trim($_POST['canton'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $latitude  = $_POST['latitude']  !== '' ? (float)$_POST['latitude']  : null;
    $longitude = $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;

    // Detalles de la propiedad
    $bedrooms = (int)($_POST['bedrooms'] ?? 0);
    $bathrooms = (int)($_POST['bathrooms'] ?? 0);
    $area_m2 = (float)($_POST['area_m2'] ?? 0);
    $parking_spaces = (int)($_POST['parking_spaces'] ?? 0);

    // Contacto
    $contact_name = trim($_POST['contact_name'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $contact_whatsapp = trim($_POST['contact_whatsapp'] ?? '');

    // Características (checkboxes)
    $selected_features = $_POST['features'] ?? [];
    $features_json = json_encode($selected_features);

    // Imágenes (URLs separadas por comas o líneas + imágenes subidas)
    $images_input = trim($_POST['images'] ?? '');
    $images_array = [];
    if ($images_input !== '') {
      // Dividir por comas o saltos de línea
      $images_array = preg_split('/[\r\n,]+/', $images_input);
      $images_array = array_filter(array_map('trim', $images_array));
    }

    // Agregar imágenes subidas (si las hay)
    $uploaded_images = $_POST['uploaded_image_urls'] ?? '';
    if (!empty($uploaded_images)) {
      $uploaded_array = json_decode($uploaded_images, true);
      if (is_array($uploaded_array)) {
        $images_array = array_merge($images_array, $uploaded_array);
      }
    }

    // Convertir URLs de Google Drive al formato correcto
    $images_array = array_map('convert_google_drive_url', $images_array);

    $images_json = json_encode($images_array);

    // Validaciones
    if ($title === '' || $description === '' || $category_id <= 0 || $price <= 0 || $pricing_plan_id <= 0) {
      throw new RuntimeException('Por favor completá todos los campos requeridos.');
    }

    if ($contact_phone === '' && $contact_email === '' && $contact_whatsapp === '') {
      throw new RuntimeException('Debés proporcionar al menos un método de contacto.');
    }

    if (!in_array($currency, ['CRC', 'USD'])) {
      $currency = 'CRC';
    }

    if (!in_array($listing_type, ['sale', 'rent'])) {
      $listing_type = 'sale';
    }

    // Obtener información del plan de precios
    $planStmt = $pdo->prepare("SELECT * FROM listing_pricing WHERE id = ? LIMIT 1");
    $planStmt->execute([$pricing_plan_id]);
    $plan = $planStmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
      throw new RuntimeException('Plan de precios inválido.');
    }

    // Calcular fechas de inicio y fin
    $start_date = date('Y-m-d H:i:s');
    $end_date = date('Y-m-d H:i:s', strtotime("+{$plan['duration_days']} days"));

    // Determinar estado de pago
    $payment_status = ($plan['price_usd'] == 0 && $plan['price_crc'] == 0) ? 'free' : 'pending';

    // Solo activar si el plan es gratis, de lo contrario esperar aprobación del admin
    $is_active = ($payment_status === 'free') ? 1 : 0;

    // Insertar la publicación
    $insertStmt = $pdo->prepare("
      INSERT INTO real_estate_listings (
        agent_id, user_id, category_id, title, description, price, currency,
        location, province, canton, district, latitude, longitude,
        bedrooms, bathrooms, area_m2, parking_spaces,
        features, images,
        contact_name, contact_phone, contact_email, contact_whatsapp,
        listing_type, pricing_plan_id,
        is_active, start_date, end_date, payment_status,
        created_at, updated_at
      ) VALUES (
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?,
        ?, ?, ?, ?,
        ?, ?,
        ?, ?, ?, ?,
        datetime('now'), datetime('now')
      )
    ");

    $insertStmt->execute([
      $agent_id,
      $agent_id, // user_id = agent_id para compatibilidad
      $category_id,
      $title,
      $description,
      $price,
      $currency,
      $location,
      $province,
      $canton,
      $district,
      $latitude,
      $longitude,
      $bedrooms,
      $bathrooms,
      $area_m2,
      $parking_spaces,
      $features_json,
      $images_json,
      $contact_name,
      $contact_phone,
      $contact_email,
      $contact_whatsapp,
      $listing_type,
      $pricing_plan_id,
      $is_active,
      $start_date,
      $end_date,
      $payment_status
    ]);

    $listing_id = (int)$pdo->lastInsertId();

    // Si el plan es gratuito, activar inmediatamente
    if ($payment_status === 'free') {
      header('Location: dashboard.php?msg=created_free');
      exit;
    } else {
      // Redirigir a página de pago
      header('Location: payment-selection.php?listing_id=' . $listing_id);
      exit;
    }

  } catch (Throwable $e) {
    $msg = "Error: " . $e->getMessage();
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Crear Propiedad — <?php echo APP_NAME; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    :root {
      --primary: #002b7f;
      --primary-dark: #001d5c;
      --white: #fff;
      --dark: #1a1a1a;
      --gray-700: #4a5568;
      --gray-600: #718096;
      --gray-300: #cbd5e0;
      --gray-100: #f7fafc;
      --bg: #f8f9fa;
      --success: #27ae60;
      --danger: #e74c3c;
      --radius: 12px;
      --shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      color: var(--dark);
      line-height: 1.6;
    }
    .header {
      background: var(--white);
      border-bottom: 2px solid var(--gray-300);
      padding: 1.5rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: var(--shadow);
    }
    .header h1 {
      font-size: 1.5rem;
      color: var(--primary);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .header-actions {
      display: flex;
      gap: 1rem;
      align-items: center;
    }
    .btn {
      padding: 0.75rem 1.5rem;
      background: var(--primary);
      color: var(--white);
      border: none;
      border-radius: var(--radius);
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
      transition: all 0.2s;
      font-size: 1rem;
    }
    .btn:hover {
      background: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,43,127,0.2);
    }
    .btn-secondary {
      background: var(--gray-300);
      color: var(--dark);
    }
    .btn-secondary:hover {
      background: var(--gray-600);
      color: var(--white);
    }
    .container {
      max-width: 900px;
      margin: 2rem auto;
      padding: 0 2rem;
    }
    .form-card {
      background: var(--white);
      border-radius: var(--radius);
      padding: 2.5rem;
      box-shadow: var(--shadow);
      margin-bottom: 2rem;
    }
    .form-section {
      margin-bottom: 2.5rem;
    }
    .form-section:last-child {
      margin-bottom: 0;
    }
    .section-title {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 1.5rem;
      padding-bottom: 0.75rem;
      border-bottom: 2px solid var(--gray-100);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .form-group {
      margin-bottom: 1.5rem;
    }
    .form-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-bottom: 1.5rem;
    }
    label {
      display: block;
      margin-bottom: 0.5rem;
      color: var(--gray-700);
      font-weight: 600;
      font-size: 0.9375rem;
    }
    label .required {
      color: var(--danger);
      margin-left: 0.25rem;
    }
    input[type="text"],
    input[type="email"],
    input[type="tel"],
    input[type="number"],
    select,
    textarea {
      width: 100%;
      padding: 0.875rem 1rem;
      border: 2px solid var(--gray-300);
      border-radius: var(--radius);
      font-size: 1rem;
      font-family: 'Inter', sans-serif;
      transition: border-color 0.2s;
    }
    input:focus,
    select:focus,
    textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(0,43,127,0.1);
    }
    textarea {
      min-height: 120px;
      resize: vertical;
    }
    .checkbox-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 0.75rem;
    }
    .checkbox-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .checkbox-item input[type="checkbox"] {
      width: auto;
      cursor: pointer;
    }
    .checkbox-item label {
      margin: 0;
      font-weight: 500;
      cursor: pointer;
    }
    .pricing-plans {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1rem;
    }
    .plan-card {
      border: 2px solid var(--gray-300);
      border-radius: var(--radius);
      padding: 1.5rem;
      cursor: pointer;
      transition: all 0.2s;
      position: relative;
    }
    .plan-card:hover {
      border-color: var(--primary);
      box-shadow: 0 4px 12px rgba(0,43,127,0.15);
    }
    .plan-card input[type="radio"] {
      position: absolute;
      opacity: 0;
    }
    .plan-card input[type="radio"]:checked + .plan-content {
      background: rgba(0,43,127,0.05);
    }
    .plan-card input[type="radio"]:checked ~ .plan-checkmark {
      display: block;
    }
    .plan-content {
      padding: 0.5rem;
      border-radius: var(--radius);
      transition: background 0.2s;
    }
    .plan-name {
      font-weight: 700;
      font-size: 1.125rem;
      margin-bottom: 0.5rem;
      color: var(--dark);
    }
    .plan-price {
      font-size: 1.5rem;
      font-weight: 800;
      color: var(--success);
      margin-bottom: 0.5rem;
    }
    .plan-duration {
      font-size: 0.875rem;
      color: var(--gray-600);
    }
    .plan-checkmark {
      display: none;
      position: absolute;
      top: 1rem;
      right: 1rem;
      width: 24px;
      height: 24px;
      background: var(--success);
      color: var(--white);
      border-radius: 50%;
      text-align: center;
      line-height: 24px;
      font-size: 0.875rem;
    }
    .alert {
      padding: 1rem 1.5rem;
      border-radius: var(--radius);
      margin-bottom: 1.5rem;
      font-weight: 500;
    }
    .alert.error {
      background: #fee;
      color: #c33;
      border: 1px solid #fcc;
    }
    .help-text {
      font-size: 0.875rem;
      color: var(--gray-600);
      margin-top: 0.25rem;
    }
    .submit-section {
      display: flex;
      gap: 1rem;
      justify-content: flex-end;
      margin-top: 2rem;
      padding-top: 2rem;
      border-top: 2px solid var(--gray-100);
    }
    /* Estilos para Drag & Drop de imágenes */
    .drop-zone {
      border: 2px dashed var(--gray-300);
      border-radius: var(--radius);
      padding: 40px;
      text-align: center;
      background: var(--gray-100);
      transition: all 0.3s ease;
      cursor: pointer;
    }
    .drop-zone:hover, .drop-zone.drag-over {
      border-color: var(--primary);
      background: #e3f2fd;
      transform: scale(1.02);
    }
    .drop-zone.drag-over {
      border-style: solid;
      box-shadow: 0 0 20px rgba(0,43,127,0.2);
    }
    .upload-button {
      display: inline-block;
      padding: 12px 24px;
      background: var(--primary);
      color: var(--white);
      border-radius: var(--radius);
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      margin-top: 10px;
    }
    .upload-button:hover {
      background: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,43,127,0.3);
    }
    .image-preview-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
      gap: 15px;
      margin-top: 20px;
    }
    .image-preview-item {
      position: relative;
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      transition: all 0.2s;
      background: var(--gray-100);
    }
    .image-preview-item:hover {
      transform: translateY(-5px);
      box-shadow: 0 6px 16px rgba(0,0,0,0.25);
    }
    .image-preview-item img {
      width: 100%;
      height: 150px;
      object-fit: cover;
      display: block;
    }
    .image-preview-remove {
      position: absolute;
      top: 5px;
      right: 5px;
      background: var(--danger);
      color: var(--white);
      border: none;
      border-radius: 50%;
      width: 28px;
      height: 28px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      transition: all 0.2s;
      opacity: 0.9;
    }
    .image-preview-remove:hover {
      opacity: 1;
      transform: scale(1.1);
      box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }
    .image-preview-uploading {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.7);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--white);
      font-size: 12px;
    }
    .spinner {
      border: 3px solid rgba(255,255,255,0.3);
      border-top: 3px solid var(--white);
      border-radius: 50%;
      width: 30px;
      height: 30px;
      animation: spin 1s linear infinite;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    @media (max-width: 768px) {
      .container {
        padding: 0 1rem;
      }
      .form-card {
        padding: 1.5rem;
      }
      .header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
      }
      .submit-section {
        flex-direction: column-reverse;
      }
      .image-preview-container {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
      }
    }
  /* Mapa */
  .map-picker-wrap { margin-top: 1.25rem; }
  .map-search-row { display: flex; gap: 0.5rem; margin-bottom: 0.75rem; }
  .map-search-row input { flex: 1; padding: 0.6rem 0.9rem; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 0.9rem; }
  .map-search-row button { padding: 0.6rem 1rem; background: #002b7f; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 0.85rem; white-space: nowrap; }
  .map-search-row button:hover { background: #001a50; }
  #map-picker { height: 320px; border-radius: 10px; border: 1px solid #cbd5e0; }
  .map-coords { font-size: 0.82rem; color: #718096; margin-top: 0.5rem; }
  </style>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>
  <div class="header">
    <h1><i class="fas fa-home"></i> Nueva Propiedad</h1>
    <div class="header-actions">
      <span><?php echo htmlspecialchars($agent_name); ?></span>
      <a href="dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver al Dashboard
      </a>
    </div>
  </div>

  <div class="container">
    <?php if ($msg): ?>
      <div class="alert error"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <!-- INFORMACIÓN BÁSICA -->
      <div class="form-card">
        <div class="form-section">
          <h2 class="section-title">
            <i class="fas fa-info-circle"></i>
            Información Básica
          </h2>

          <div class="form-group">
            <label>Título de la Propiedad <span class="required">*</span></label>
            <input type="text" name="title" required placeholder="Ej: Casa de 3 habitaciones en Escazú">
          </div>

          <div class="form-group">
            <label>Descripción <span class="required">*</span></label>
            <textarea name="description" required placeholder="Describe la propiedad, sus características principales, ventajas, cercanía a servicios, etc."></textarea>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Categoría <span class="required">*</span></label>
              <select name="category_id" required>
                <option value="">Seleccionar categoría</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>">
                    <?= str_replace('BR: ', '', htmlspecialchars($cat['name'])) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label>Tipo de Operación <span class="required">*</span></label>
              <select name="listing_type" required>
                <option value="sale">Venta</option>
                <option value="rent">Alquiler</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Precio <span class="required">*</span></label>
              <input type="number" name="price" step="0.01" min="0" required placeholder="0.00">
            </div>

            <div class="form-group">
              <label>Moneda <span class="required">*</span></label>
              <select name="currency" required>
                <option value="CRC">₡ Colones (CRC)</option>
                <option value="USD">$ Dólares (USD)</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- UBICACIÓN -->
      <div class="form-card">
        <div class="form-section">
          <h2 class="section-title">
            <i class="fas fa-map-marker-alt"></i>
            Ubicación
          </h2>

          <div class="form-row">
            <div class="form-group">
              <label>Provincia</label>
              <select name="province">
                <option value="">Seleccionar provincia</option>
                <?php foreach ($provinces as $prov): ?>
                  <option value="<?= htmlspecialchars($prov) ?>"><?= htmlspecialchars($prov) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label>Cantón</label>
              <select name="canton" id="canton-select" disabled>
                <option value="">Seleccione provincia primero</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Distrito</label>
              <select name="district" id="district-select" disabled>
                <option value="">Seleccione cantón primero</option>
              </select>
            </div>

            <div class="form-group">
              <label>Ubicación Específica</label>
              <input type="text" name="location" placeholder="Ej: 200m oeste de la iglesia">
            </div>
          </div>

          <!-- MAPA -->
          <div class="map-picker-wrap">
            <label style="font-weight:600;display:block;margin-bottom:0.5rem;">
              <i class="fas fa-map-pin" style="color:#002b7f;"></i> Ubicación en el Mapa <span style="font-weight:400;color:#718096;font-size:0.85rem;">(opcional)</span>
            </label>
            <div class="map-search-row">
              <input type="text" id="map-search-input" placeholder="Buscar dirección en Costa Rica...">
              <button type="button" onclick="searchMapAddress()"><i class="fas fa-search"></i> Buscar</button>
              <button type="button" onclick="clearMapPin()" style="background:#e53e3e;"><i class="fas fa-times"></i> Limpiar</button>
            </div>
            <div id="map-picker"></div>
            <div class="map-coords" id="map-coords-display">Hacé clic en el mapa para marcar la ubicación exacta de la propiedad.</div>
            <input type="hidden" name="latitude"  id="input-latitude"  value="">
            <input type="hidden" name="longitude" id="input-longitude" value="">
          </div>
        </div>
      </div>

      <!-- DETALLES DE LA PROPIEDAD -->
      <div class="form-card">
        <div class="form-section">
          <h2 class="section-title">
            <i class="fas fa-bed"></i>
            Detalles de la Propiedad
          </h2>

          <div class="form-row">
            <div class="form-group">
              <label>Habitaciones</label>
              <input type="number" name="bedrooms" min="0" value="0" placeholder="0">
            </div>

            <div class="form-group">
              <label>Baños</label>
              <input type="number" name="bathrooms" min="0" value="0" placeholder="0">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Área (m²)</label>
              <input type="number" name="area_m2" step="0.01" min="0" value="0" placeholder="0.00">
            </div>

            <div class="form-group">
              <label>Espacios de Parqueo</label>
              <input type="number" name="parking_spaces" min="0" value="0" placeholder="0">
            </div>
          </div>
        </div>
      </div>

      <!-- CARACTERÍSTICAS -->
      <div class="form-card">
        <div class="form-section">
          <h2 class="section-title">
            <i class="fas fa-check-circle"></i>
            Características Adicionales
          </h2>

          <div class="checkbox-grid">
            <?php foreach ($available_features as $feature): ?>
              <div class="checkbox-item">
                <input type="checkbox" name="features[]" value="<?= htmlspecialchars($feature) ?>" id="feat_<?= md5($feature) ?>">
                <label for="feat_<?= md5($feature) ?>"><?= htmlspecialchars($feature) ?></label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- IMÁGENES -->
      <div class="form-card">
        <div class="form-section">
          <h2 class="section-title">
            <i class="fas fa-images"></i>
            Imágenes
          </h2>

          <!-- Límites de fotos por plan -->
          <div class="photo-limit-info" id="photoLimitInfo" style="background: #e3f2fd; border-left: 4px solid #2196F3; padding: 12px; margin-bottom: 20px; border-radius: 4px;">
            <i class="fas fa-info-circle" style="color: #2196F3;"></i>
            <span id="photoLimitText">Seleccioná un plan para ver el límite de fotos</span>
          </div>

          <!-- Opción 1: Subir archivos (Drag & Drop + Botón) -->
          <div class="form-group">
            <label>
              <i class="fas fa-cloud-upload-alt"></i> Subir Imágenes
              <span style="color: #666; font-size: 0.9em;">(Recomendado)</span>
            </label>

            <!-- Área de drag & drop -->
            <div id="dropZone" class="drop-zone">
              <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #666; margin-bottom: 10px;"></i>
              <p style="margin: 10px 0; font-size: 16px; color: #333;">Arrastrá las imágenes aquí</p>
              <p style="margin: 5px 0; color: #666;">o</p>
              <label for="fileInput" class="upload-button">
                <i class="fas fa-folder-open"></i> Seleccionar Archivos
              </label>
              <input type="file" id="fileInput" name="uploaded_images[]" multiple accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
              <p style="margin-top: 15px; font-size: 13px; color: #666;">JPG, PNG, GIF o WebP - Máx. 10MB por imagen</p>
            </div>

            <!-- Vista previa de imágenes -->
            <div id="imagePreview" class="image-preview-container"></div>

            <!-- Input oculto para almacenar URLs de imágenes subidas -->
            <input type="hidden" id="uploadedImageUrls" name="uploaded_image_urls" value="">
          </div>

          <div style="text-align: center; margin: 20px 0; color: #999; font-weight: bold;">
            - O -
          </div>

          <!-- Opción 2: URLs de imágenes (opción actual mantenida) -->
          <div class="form-group">
            <label>
              <i class="fas fa-link"></i> URLs de Imágenes
              <span style="color: #666; font-size: 0.9em;">(Google Drive, Imgur, Dropbox, etc.)</span>
            </label>
            <textarea id="imageUrlsTextarea" name="images" placeholder="Ingresá las URLs de las imágenes, una por línea&#10;https://ejemplo.com/imagen1.jpg&#10;https://ejemplo.com/imagen2.jpg"></textarea>
            <p class="help-text">
              <i class="fas fa-info-circle"></i>
              Ingresá una URL por línea. Podés usar servicios como Imgur, Dropbox, o Google Drive para alojar tus imágenes.
            </p>
          </div>
        </div>
      </div>

      <!-- CONTACTO -->
      <div class="form-card">
        <div class="form-section">
          <h2 class="section-title">
            <i class="fas fa-phone"></i>
            Información de Contacto
          </h2>

          <div class="form-row">
            <div class="form-group">
              <label>Nombre de Contacto</label>
              <input type="text" name="contact_name" placeholder="Tu nombre o de la inmobiliaria" value="<?= htmlspecialchars($agent_name) ?>">
            </div>

            <div class="form-group">
              <label>Teléfono</label>
              <input type="tel" name="contact_phone" placeholder="8888-8888" pattern="[\d\s\-\+]{7,15}" title="Ingrese un número de teléfono válido (ej: 88888888 o 8888-8888)">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>WhatsApp</label>
              <input type="tel" name="contact_whatsapp" placeholder="8888-8888" pattern="[\d\s\-\+]{7,15}" title="Ingrese un número de WhatsApp válido (ej: 50688888888 o +506 8888 8888)">
              <p class="help-text">Los clientes podrán contactarte directamente por WhatsApp</p>
            </div>

            <div class="form-group">
              <label>Email</label>
              <input type="email" name="contact_email" placeholder="correo@ejemplo.com">
            </div>
          </div>
        </div>
      </div>

      <!-- PLAN DE PUBLICACIÓN -->
      <div class="form-card">
        <div class="form-section">
          <h2 class="section-title">
            <i class="fas fa-tag"></i>
            Plan de Publicación
          </h2>

          <div class="pricing-plans">
            <?php foreach ($pricing_plans as $index => $plan): ?>
              <label class="plan-card" for="plan_<?= $plan['id'] ?>">
                <input type="radio" name="pricing_plan_id" value="<?= $plan['id'] ?>" id="plan_<?= $plan['id'] ?>" <?= $index === 0 ? 'required' : '' ?>>
                <div class="plan-content">
                  <div class="plan-name"><?= htmlspecialchars($plan['name']) ?></div>
                  <div class="plan-price">
                    <?php if ($plan['price_usd'] == 0 && $plan['price_crc'] == 0): ?>
                      Gratis
                    <?php else: ?>
                      $<?= number_format($plan['price_usd'], 2) ?> / ₡<?= number_format($plan['price_crc'], 0) ?>
                    <?php endif; ?>
                  </div>
                  <div class="plan-duration"><?= $plan['duration_days'] ?> días</div>
                  <div style="background: #e3f2fd; padding: 0.5rem; border-radius: 6px; margin-top: 0.5rem; font-size: 0.9rem;">
                    <i class="fas fa-camera" style="color: #1976d2;"></i>
                    Hasta <strong><?= (int)($plan['max_photos'] ?? 3) ?> fotos</strong>
                  </div>
                  <?php if ($plan['description']): ?>
                    <p class="help-text" style="margin-top: 0.5rem;"><?= htmlspecialchars($plan['description']) ?></p>
                  <?php endif; ?>
                </div>
                <div class="plan-checkmark"><i class="fas fa-check"></i></div>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- BOTONES DE ACCIÓN -->
      <div class="submit-section">
        <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn" id="submitBtn">
          <i class="fas fa-save"></i> <span id="submitBtnText">Publicar Propiedad</span>
        </button>
      </div>

      <!-- Modal de pago (se muestra si es plan de pago) -->
      <div id="paymentModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 12px; max-width: 500px; width: 90%;">
          <h3 style="margin-bottom: 1rem; color: #002b7f;">
            <i class="fas fa-credit-card"></i> Información de Pago
          </h3>
          <p style="margin-bottom: 1.5rem; color: #4a5568;">
            Tu propiedad será creada y quedará pendiente de pago. Una vez que confirmés el pago, será activada automáticamente.
          </p>
          <div style="background: #f7fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <p style="font-weight: 600; color: #002b7f; margin-bottom: 0.5rem;">
              Plan seleccionado: <span id="selectedPlanName"></span>
            </p>
            <p style="font-size: 1.5rem; font-weight: 800; color: #27ae60; margin: 0;">
              <span id="selectedPlanPrice"></span>
            </p>
          </div>
          <div style="display: flex; gap: 1rem;">
            <button type="button" onclick="closePaymentModal()" style="flex: 1; padding: 0.875rem; background: #cbd5e0; color: #1a1a1a; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
              Cancelar
            </button>
            <button type="button" onclick="confirmPublish()" style="flex: 1; padding: 0.875rem; background: #002b7f; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
              <i class="fas fa-check"></i> Continuar
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>

  <script>
    // =========================
    // Sistema de Upload de Imágenes
    // =========================

    // Información de planes (precio, límites de fotos, etc.)
    const plansData = <?= json_encode($pricing_plans) ?>;

    // Crear objeto de límites de fotos desde los datos de la BD
    const photoLimits = {};
    const planNames = {};
    plansData.forEach(plan => {
      photoLimits[plan.id] = parseInt(plan.max_photos) || 3;
      planNames[plan.id] = plan.name;
    });

    // Estado global
    let uploadedImages = [];  // URLs de imágenes subidas
    let selectedPlan = 0;
    let maxPhotos = 3;  // Por defecto

    // Elementos DOM
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const imagePreview = document.getElementById('imagePreview');
    const uploadedImageUrls = document.getElementById('uploadedImageUrls');
    const imageUrlsTextarea = document.getElementById('imageUrlsTextarea');
    const photoLimitInfo = document.getElementById('photoLimitInfo');
    const photoLimitText = document.getElementById('photoLimitText');
    const pricingPlanRadios = document.querySelectorAll('input[name="pricing_plan_id"]');
    const submitBtn = document.getElementById('submitBtn');
    const submitBtnText = document.getElementById('submitBtnText');
    const paymentModal = document.getElementById('paymentModal');

    // =========================
    // Actualizar límite de fotos cuando cambia el plan
    // =========================
    pricingPlanRadios.forEach(radio => {
      radio.addEventListener('change', function() {
        selectedPlan = this.value;
        maxPhotos = photoLimits[selectedPlan] || 3;

        if (selectedPlan && planNames[selectedPlan]) {
          photoLimitText.innerHTML = `<strong>${planNames[selectedPlan]}</strong>: Podés subir hasta <strong>${maxPhotos} fotos</strong>`;
        } else {
          photoLimitText.textContent = 'Seleccioná un plan para ver el límite de fotos';
        }

        // Actualizar texto del botón según el plan
        const plan = plansData.find(p => p.id == selectedPlan);
        if (plan && (plan.price_usd > 0 || plan.price_crc > 0)) {
          submitBtnText.innerHTML = '<i class="fas fa-credit-card"></i> Continuar al Pago';
        } else {
          submitBtnText.innerHTML = '<i class="fas fa-save"></i> Publicar Propiedad';
        }

        updatePhotoCount();
      });
    });

    // =========================
    // Drag & Drop
    // =========================
    dropZone.addEventListener('dragover', (e) => {
      e.preventDefault();
      dropZone.classList.add('drag-over');
    });

    dropZone.addEventListener('dragleave', () => {
      dropZone.classList.remove('drag-over');
    });

    dropZone.addEventListener('drop', (e) => {
      e.preventDefault();
      dropZone.classList.remove('drag-over');

      const files = Array.from(e.dataTransfer.files).filter(file =>
        file.type.startsWith('image/')
      );

      if (files.length > 0) {
        handleFiles(files);
      }
    });

    // Click en la zona para abrir selector de archivos
    dropZone.addEventListener('click', (e) => {
      if (e.target === dropZone || e.target.closest('.drop-zone') && !e.target.closest('.upload-button')) {
        fileInput.click();
      }
    });

    // =========================
    // Selector de archivos
    // =========================
    fileInput.addEventListener('change', (e) => {
      const files = Array.from(e.target.files);
      if (files.length > 0) {
        handleFiles(files);
      }
      // Limpiar input para permitir seleccionar el mismo archivo nuevamente
      e.target.value = '';
    });

    // =========================
    // Procesar archivos seleccionados
    // =========================
    function handleFiles(files) {
      const currentCount = uploadedImages.length + countUrlImages();

      if (currentCount + files.length > maxPhotos) {
        alert(`Tu plan permite máximo ${maxPhotos} fotos. Ya tenés ${currentCount} imagen(es).`);
        return;
      }

      files.forEach((file, index) => {
        // Crear preview inmediato
        const previewId = 'preview-' + Date.now() + '-' + index;
        createImagePreview(file, previewId);

        // Subir archivo
        uploadImage(file, previewId);
      });
    }

    // =========================
    // Crear vista previa de imagen
    // =========================
    function createImagePreview(file, previewId) {
      const reader = new FileReader();

      reader.onload = (e) => {
        const previewItem = document.createElement('div');
        previewItem.className = 'image-preview-item';
        previewItem.id = previewId;
        previewItem.innerHTML = `
          <img src="${e.target.result}" alt="Preview">
          <button type="button" class="image-preview-remove" onclick="removeImage('${previewId}')" disabled>
            <i class="fas fa-times"></i>
          </button>
          <div class="image-preview-uploading">
            <div class="spinner"></div>
          </div>
        `;
        imagePreview.appendChild(previewItem);
      };

      reader.readAsDataURL(file);
    }

    // =========================
    // Subir imagen al servidor
    // =========================
    function uploadImage(file, previewId) {
      const formData = new FormData();
      formData.append('images[]', file);
      formData.append('pricing_plan_id', selectedPlan);
      formData.append('current_images_count', uploadedImages.length + countUrlImages());
      formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');

      fetch('upload-image.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        const previewElement = document.getElementById(previewId);

        if (data.success && data.images.length > 0) {
          // Upload exitoso
          const imageUrl = data.images[0];
          uploadedImages.push(imageUrl);

          // Remover spinner
          const uploading = previewElement.querySelector('.image-preview-uploading');
          if (uploading) uploading.remove();

          // Habilitar botón de eliminar
          const removeBtn = previewElement.querySelector('.image-preview-remove');
          if (removeBtn) {
            removeBtn.disabled = false;
            removeBtn.dataset.url = imageUrl;
          }

          // Actualizar input oculto
          updateUploadedImagesInput();

          // Mostrar advertencia si la API de moderación no está configurada
          if (data.moderation_status && data.moderation_status.service === 'Validación básica') {
            console.warn('Moderación de contenido no configurada. Configure Sightengine API en config.php');
          }
        } else {
          // Upload fallido
          alert('Error al subir imagen: ' + (data.error || 'Error desconocido'));
          previewElement.remove();
        }

        updatePhotoCount();
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error al subir imagen. Por favor intentá nuevamente.');
        const previewElement = document.getElementById(previewId);
        if (previewElement) previewElement.remove();
      });
    }

    // =========================
    // Eliminar imagen
    // =========================
    window.removeImage = function(previewId) {
      const previewElement = document.getElementById(previewId);
      const removeBtn = previewElement.querySelector('.image-preview-remove');
      const imageUrl = removeBtn.dataset.url;

      // Remover de la lista
      uploadedImages = uploadedImages.filter(url => url !== imageUrl);

      // Remover del DOM
      previewElement.remove();

      // Actualizar input oculto
      updateUploadedImagesInput();
      updatePhotoCount();
    };

    // =========================
    // Actualizar input oculto con URLs de imágenes subidas
    // =========================
    function updateUploadedImagesInput() {
      uploadedImageUrls.value = JSON.stringify(uploadedImages);
    }

    // =========================
    // Contar imágenes de URLs
    // =========================
    function countUrlImages() {
      const urls = imageUrlsTextarea.value.trim();
      if (!urls) return 0;
      return urls.split(/[\r\n,]+/).filter(url => url.trim()).length;
    }

    // =========================
    // Actualizar contador de fotos
    // =========================
    function updatePhotoCount() {
      const total = uploadedImages.length + countUrlImages();
      if (total > 0) {
        photoLimitText.innerHTML = `<strong>${planNames[selectedPlan] || 'Plan seleccionado'}</strong>: ${total} de ${maxPhotos} fotos`;

        if (total >= maxPhotos) {
          photoLimitInfo.style.background = '#fff3cd';
          photoLimitInfo.style.borderColor = '#ffc107';
          dropZone.style.opacity = '0.5';
          dropZone.style.pointerEvents = 'none';
        } else {
          photoLimitInfo.style.background = '#e3f2fd';
          photoLimitInfo.style.borderColor = '#2196F3';
          dropZone.style.opacity = '1';
          dropZone.style.pointerEvents = 'auto';
        }
      }
    }

    // Monitorear cambios en el textarea de URLs
    imageUrlsTextarea.addEventListener('input', updatePhotoCount);

    // =========================
    // Funciones del modal de pago
    // =========================
    function showPaymentModal(planId) {
      const plan = plansData.find(p => p.id == planId);
      if (!plan) return;

      document.getElementById('selectedPlanName').textContent = plan.name;
      const priceText = plan.price_usd > 0 || plan.price_crc > 0
        ? `$${parseFloat(plan.price_usd).toFixed(2)} / ₡${parseFloat(plan.price_crc).toLocaleString('es-CR', {minimumFractionDigits: 0})}`
        : 'Gratis';
      document.getElementById('selectedPlanPrice').textContent = priceText;

      paymentModal.style.display = 'flex';
    }

    function closePaymentModal() {
      paymentModal.style.display = 'none';
    }

    function confirmPublish() {
      // Cerrar modal y enviar el formulario
      closePaymentModal();
      document.querySelector('form').submit();
    }

    // Cerrar modal al hacer clic fuera
    paymentModal.addEventListener('click', function(e) {
      if (e.target === paymentModal) {
        closePaymentModal();
      }
    });

    // =========================
    // Validación antes de enviar formulario
    // =========================
    document.querySelector('form').addEventListener('submit', function(e) {
      e.preventDefault();

      const total = uploadedImages.length + countUrlImages();

      // Combinar URLs de imágenes subidas con URLs del textarea
      if (uploadedImages.length > 0) {
        const existingUrls = imageUrlsTextarea.value.trim();
        const combinedUrls = existingUrls
          ? existingUrls + '\n' + uploadedImages.join('\n')
          : uploadedImages.join('\n');
        imageUrlsTextarea.value = combinedUrls;
      }

      if (total > maxPhotos) {
        alert(`Tu plan permite máximo ${maxPhotos} fotos. Tenés ${total} imagen(es).`);
        return false;
      }

      // Verificar si se seleccionó un plan
      const selectedPlanRadio = document.querySelector('input[name="pricing_plan_id"]:checked');
      if (!selectedPlanRadio) {
        alert('Por favor seleccioná un plan de publicación.');
        return false;
      }

      const planId = selectedPlanRadio.value;
      const plan = plansData.find(p => p.id == planId);

      // Si es plan de pago, mostrar modal de confirmación
      if (plan && (plan.price_usd > 0 || plan.price_crc > 0)) {
        showPaymentModal(planId);
        return false;
      }

      // Si es gratis, enviar directamente
      this.submit();
    });
  </script>

  <script>
    // =========================
    // Cantones y Distritos de Costa Rica
    // =========================
    const crData = {
      "San José": {
        "San José": ["Carmen","Merced","Hospital","Catedral","Zapote","San Francisco de Dos Ríos","Uruca","Mata Redonda","Pavas","Hatillo","San Sebastián"],
        "Escazú": ["Escazú","San Antonio","San Rafael"],
        "Desamparados": ["Desamparados","San Miguel","San Juan de Dios","San Rafael Arriba","San Antonio","Frailes","Patarrá","San Cristóbal","Rosario","Damas","San Rafael Abajo","Gravilias","Los Guido"],
        "Puriscal": ["Santiago","Mercedes Sur","Barbacoas","Grifo Alto","San Rafael","Candelarita","Desamparaditos","San Antonio","Chires"],
        "Tarrazú": ["San Marcos","San Lorenzo","San Carlos"],
        "Aserrí": ["Aserrí","Tarbaca","Vuelta de Jorco","San Gabriel","La Legua","Monterrey","Salitrillos"],
        "Mora": ["Colón","Guayabo","Tabarcia","Piedras Negras","Picagres","Jaris","Quitirrisí"],
        "Goicoechea": ["Guadalupe","San Francisco","Calle Blancos","Mata de Plátano","Ipís","Rancho Redondo","Purral"],
        "Santa Ana": ["Santa Ana","Salitral","Pozos","Uruca","Piedades","Brasil"],
        "Alajuelita": ["Alajuelita","San Josecito","San Antonio","Concepción","San Felipe"],
        "Vásquez de Coronado": ["San Isidro","San Rafael","Dulce Nombre de Jesús","Patalillo","Cascajal"],
        "Acosta": ["San Ignacio","Guaitil","Palmichal","Cangrejal","Sabanillas"],
        "Tibás": ["San Juan","Cinco Esquinas","Anselmo Llorente","León XIII","Colima"],
        "Moravia": ["San Vicente","San Jerónimo","La Trinidad"],
        "Montes de Oca": ["San Pedro","Sabanilla","Mercedes","San Rafael"],
        "Turrubares": ["San Pablo","San Pedro","San Juan de Mata","San Luis","Carara"],
        "Dota": ["Santa María","Jardín","Copey"],
        "Curridabat": ["Curridabat","Granadilla","Sánchez","Tirrases"],
        "Pérez Zeledón": ["San Isidro de El General","El General","Daniel Flores","Rivas","San Pedro","Platanares","Pejibaye","Cajón","Barú","Río Nuevo","Páramo","La Amistad"],
        "León Cortés Castro": ["San Pablo","San Andrés","Llano Bonito","San Isidro","Santa Cruz","San Antonio"]
      },
      "Alajuela": {
        "Alajuela": ["Alajuela","San José","Carrizal","San Antonio","Guácima","San Isidro","Sabanilla","San Rafael","Río Segundo","Desamparados","Turrúcares","Tambor","Garita","Sarapiquí"],
        "San Ramón": ["San Ramón","Santiago","San Juan","Piedades Norte","Piedades Sur","San Rafael","San Isidro","Ángeles","Alfaro","Volio","Concepción","Zapotal","Peñas Blancas","San Lorenzo"],
        "Grecia": ["Grecia","San Isidro","San José","San Roque","Tacares","Rodríguez","Puente de Piedra","Bolívar"],
        "San Mateo": ["San Mateo","Desmonte","Jesús María","Labrador"],
        "Atenas": ["Atenas","Jesús","Mercedes","San Isidro","Concepción","San José","Santa Eulalia","Escobal"],
        "Naranjo": ["Naranjo","San Miguel","San José","Cirrí Sur","San Jerónimo","San Juan","El Rosario","Palmitos"],
        "Palmares": ["Palmares","Zaragoza","Buenos Aires","Santiago","Candelaria","Esquipulas","La Granja"],
        "Poás": ["San Juan","San Luis","Carrillos","Sabana Redonda"],
        "Orotina": ["Orotina","El Mastate","Hacienda Vieja","Coyolar","La Ceiba"],
        "San Carlos": ["Quesada","Florencia","Buenavista","Aguas Zarcas","Venecia","Pital","La Fortuna","La Tigra","La Palmera","Venado","Cutris","Monterrey","Pocosol"],
        "Zarcero": ["Zarcero","Laguna","Tapesco","Guadalupe","Palmira","Zapote","Brisas"],
        "Valverde Vega": ["Sarchí Norte","Sarchí Sur","Toro Amarillo","San Pedro","Rodríguez"],
        "Upala": ["Upala","Aguas Claras","San José o Pizote","Bijagua","Delicias","Dos Ríos","Yolillal","Canalete"],
        "Los Chiles": ["Los Chiles","Caño Negro","El Amparo","San Jorge"],
        "Guatuso": ["San Rafael","Buenavista","Cote","Katira"]
      },
      "Cartago": {
        "Cartago": ["Oriental","Occidental","Carmen","San Nicolás","Aguacaliente","Guadalupe","Corralillo","Tierra Blanca","Dulce Nombre","Llano Grande","Quebradilla"],
        "Paraíso": ["Paraíso","Santiago","Orosi","Cachí","Llanos de Santa Lucía"],
        "La Unión": ["Tres Ríos","San Diego","San Juan","San Rafael","Concepción","Dulce Nombre","San Ramón","Río Azul"],
        "Jiménez": ["Juan Viñas","Tucurrique","Pejibaye"],
        "Turrialba": ["Turrialba","La Suiza","Peralta","Santa Cruz","Santa Teresita","Pavones","Tuis","Tayutic","Santa Rosa","Tres Equis","La Isabel","Chirripó"],
        "Alvarado": ["Pacayas","Cervantes","Capellades"],
        "Oreamuno": ["San Rafael","Cot","Potrero Cerrado","Cipreses","Santa Rosa"],
        "El Guarco": ["El Tejar","San Isidro","Tobosi","Patio de Agua"]
      },
      "Heredia": {
        "Heredia": ["Heredia","Mercedes","San Francisco","Ulloa","Varablanca"],
        "Barva": ["Barva","San Pedro","San Pablo","San Roque","Santa Lucía","San José de la Montaña"],
        "Santo Domingo": ["Santo Domingo","San Vicente","San Miguel","Paracito","Santo Tomás","Santa Rosa","Tures","Pará"],
        "Santa Bárbara": ["Santa Bárbara","San Pedro","San Juan","Jesús","Santo Domingo","Purabá"],
        "San Rafael": ["San Rafael","San Josecito","Santiago","Ángeles","León XIII"],
        "San Isidro": ["San Isidro","San José","Concepción","San Francisco"],
        "Belén": ["San Antonio","La Ribera","La Asunción"],
        "Flores": ["San Joaquín","Barrantes","Llorente"],
        "San Pablo": ["San Pablo","Rincón de Sabanilla"],
        "Sarapiquí": ["Puerto Viejo","La Virgen","Las Horquetas","Llanuras del Gaspar","Cureña"]
      },
      "Guanacaste": {
        "Liberia": ["Liberia","Cañas Dulces","Mayorga","Nacascolo","Curubandé"],
        "Nicoya": ["Nicoya","Mansión","San Antonio","Quebrada Honda","Sámara","Nosara","Belén de Nosarita"],
        "Santa Cruz": ["Santa Cruz","Bolsón","Veintisiete de Abril","Tempate","Cartagena","Cuajiniquil","Diriá","Cabo Velas","Tamarindo"],
        "Bagaces": ["Bagaces","La Fortuna","Mogote","Río Naranjo"],
        "Carrillo": ["Filadelfia","Palmira","Sardinal","Belén"],
        "Cañas": ["Cañas","Palmira","San Miguel","Bebedero","Porozal"],
        "Abangares": ["Las Juntas","Sierra","San Juan","Colorado"],
        "Tilarán": ["Tilarán","Quebrada Grande","Tronadora","Santa Rosa","Líbano","Tierras Morenas","Arenal","Cabeceras"],
        "Nandayure": ["Carmona","Santa Rita","Zapotal","San Pablo","Porvenir","Bejuco"],
        "La Cruz": ["La Cruz","Santa Cecilia","La Garita","Santa Elena"],
        "Hojancha": ["Hojancha","Monte Romo","Puerto Carrillo","Huacas","Matambú"]
      },
      "Puntarenas": {
        "Puntarenas": ["Puntarenas","Pitahaya","Chomes","Lepanto","Paquera","Manzanillo","Guacimal","Barranca","Cóbano","Chacarita","Chira","Acapulco","El Roble","Arancibia"],
        "Esparza": ["Espíritu Santo","San Juan Grande","Macacona","San Rafael","San Jerónimo","Caldera"],
        "Buenos Aires": ["Buenos Aires","Volcán","Potrero Grande","Boruca","Pilas","Colinas","Chánguena","Biolley","Brunka"],
        "Montes de Oro": ["Miramar","La Unión","San Isidro"],
        "Osa": ["Puerto Cortés","Palmar","Sierpe","Bahía Ballena","Piedras Blancas","Bahía Drake"],
        "Quepos": ["Quepos","Savegre","Naranjito"],
        "Golfito": ["Golfito","Puerto Jiménez","Guaycará","Pavón"],
        "Coto Brus": ["San Vito","Sabalito","Aguabuena","Limoncito","Pittier","Gutiérrez Braun"],
        "Parrita": ["Parrita"],
        "Corredores": ["Corredor","La Cuesta","Canoas","Laurel"],
        "Garabito": ["Jacó","Tárcoles"]
      },
      "Limón": {
        "Limón": ["Limón","Valle La Estrella","Río Blanco","Matama"],
        "Pococí": ["Guápiles","Jiménez","Rita","Roxana","Cariari","Colorado","La Colonia"],
        "Siquirres": ["Siquirres","Pacuarito","Florida","Germania","El Cairo","Alegría","Reventazón"],
        "Talamanca": ["Bratsi","Sixaola","Cahuita","Telire"],
        "Matina": ["Matina","Batán","Carrandi"],
        "Guácimo": ["Guácimo","Mercedes","Pocora","Río Jiménez","Duacarí"]
      }
    };

    const provinceSelect  = document.querySelector('select[name="province"]');
    const cantonSelect    = document.getElementById('canton-select');
    const districtSelect  = document.getElementById('district-select');

    provinceSelect.addEventListener('change', function () {
      const province = this.value;
      // Resetear cantón y distrito
      cantonSelect.innerHTML  = '<option value="">Seleccionar cantón</option>';
      districtSelect.innerHTML = '<option value="">Seleccione cantón primero</option>';
      districtSelect.disabled = true;

      if (province && crData[province]) {
        Object.keys(crData[province]).forEach(function (canton) {
          const opt = document.createElement('option');
          opt.value = canton;
          opt.textContent = canton;
          cantonSelect.appendChild(opt);
        });
        cantonSelect.disabled = false;
      } else {
        cantonSelect.innerHTML = '<option value="">Seleccione provincia primero</option>';
        cantonSelect.disabled = true;
      }
    });

    cantonSelect.addEventListener('change', function () {
      const province = provinceSelect.value;
      const canton   = this.value;
      districtSelect.innerHTML = '<option value="">Seleccionar distrito</option>';

      if (province && canton && crData[province] && crData[province][canton]) {
        crData[province][canton].forEach(function (district) {
          const opt = document.createElement('option');
          opt.value = district;
          opt.textContent = district;
          districtSelect.appendChild(opt);
        });
        districtSelect.disabled = false;
      } else {
        districtSelect.innerHTML = '<option value="">Seleccione cantón primero</option>';
        districtSelect.disabled = true;
      }
    });
  </script>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    // ── Mapa picker ──────────────────────────────────────────────
    var mapPicker, mapMarker;

    function initMap() {
      mapPicker = L.map('map-picker').setView([9.7489, -83.7534], 7);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
      }).addTo(mapPicker);

      mapMarker = null;
      mapPicker.on('click', function(e) { setMapPin(e.latlng.lat, e.latlng.lng); });

      setTimeout(function() { mapPicker.invalidateSize(); }, 300);
    }

    function setMapPin(lat, lng) {
      if (mapMarker) mapPicker.removeLayer(mapMarker);
      mapMarker = L.marker([lat, lng], { draggable: true }).addTo(mapPicker);
      mapMarker.on('dragend', function(e) {
        var p = e.target.getLatLng();
        updateCoords(p.lat, p.lng);
      });
      updateCoords(lat, lng);
      mapPicker.setView([lat, lng], 15);
    }

    function updateCoords(lat, lng) {
      document.getElementById('input-latitude').value  = lat.toFixed(7);
      document.getElementById('input-longitude').value = lng.toFixed(7);
      document.getElementById('map-coords-display').textContent =
        'Lat: ' + lat.toFixed(6) + '  |  Lng: ' + lng.toFixed(6);
    }

    function clearMapPin() {
      if (mapMarker) { mapPicker.removeLayer(mapMarker); mapMarker = null; }
      document.getElementById('input-latitude').value  = '';
      document.getElementById('input-longitude').value = '';
      document.getElementById('map-coords-display').textContent =
        'Hacé clic en el mapa para marcar la ubicación exacta de la propiedad.';
    }

    function searchMapAddress() {
      var q = document.getElementById('map-search-input').value.trim();
      if (!q) return;
      fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=cr&q=' + encodeURIComponent(q), {
        headers: { 'Accept-Language': 'es' }
      })
      .then(function(r){ return r.json(); })
      .then(function(data) {
        if (data && data.length > 0) {
          setMapPin(parseFloat(data[0].lat), parseFloat(data[0].lon));
        } else {
          alert('No se encontró la dirección. Probá con más detalles.');
        }
      })
      .catch(function(){ alert('Error al buscar. Intentá de nuevo.'); });
    }

    document.getElementById('map-search-input').addEventListener('keydown', function(e) {
      if (e.key === 'Enter') { e.preventDefault(); searchMapAddress(); }
    });

    if (document.readyState === 'complete') {
      initMap();
    } else {
      window.addEventListener('load', initMap);
    }
  </script>
</body>
</html>
