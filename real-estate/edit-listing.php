<?php
// real-estate/edit-listing.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Verificar autenticación
if (!isset($_SESSION['agent_id']) || $_SESSION['agent_id'] <= 0) {
  header('Location: login.php');
  exit;
}

$pdo = db();
$agent_id = (int)$_SESSION['agent_id'];
$agent_name = $_SESSION['agent_name'] ?? 'Usuario';
$listing_id = (int)($_GET['id'] ?? 0);

// Cargar la propiedad
if ($listing_id <= 0) {
  header('Location: dashboard.php');
  exit;
}

$stmt = $pdo->prepare("SELECT * FROM real_estate_listings WHERE id = ? AND agent_id = ? LIMIT 1");
$stmt->execute([$listing_id, $agent_id]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$listing) {
  header('Location: dashboard.php?error=not_found');
  exit;
}

// Verificar si la publicación está expirada
$isExpired = false;
$daysRemaining = 0;
if ($listing['end_date']) {
  $endDate = new DateTime($listing['end_date']);
  $now = new DateTime();
  $interval = $now->diff($endDate);
  $daysRemaining = (int)$interval->format('%r%a'); // negativo si expiró
  $isExpired = $daysRemaining < 0;
}

// Decodificar JSON
$features_arr = json_decode($listing['features'] ?? '[]', true) ?: [];
$images_arr = json_decode($listing['images'] ?? '[]', true) ?: [];

$msg = '';
$ok = false;

// Obtener categorías de bienes raíces
$categories = [];
try {
  $catStmt = $pdo->query("SELECT id, name, icon FROM categories WHERE active=1 AND name LIKE 'BR:%' ORDER BY display_order ASC");
  $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log('[edit-listing.php] Error al cargar categorías: ' . $e->getMessage());
}

// Obtener planes de precios
$pricing_plans = [];
try {
  $planStmt = $pdo->query("SELECT * FROM listing_pricing WHERE is_active=1 ORDER BY display_order ASC");
  $pricing_plans = $planStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log('[edit-listing.php] Error al cargar planes: ' . $e->getMessage());
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
    $renew_plan_id = (int)($_POST['renew_plan_id'] ?? 0);

    // Ubicación
    $province = trim($_POST['province'] ?? '');
    $canton = trim($_POST['canton'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $location = trim($_POST['location'] ?? '');

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
    if ($title === '' || $description === '' || $category_id <= 0 || $price <= 0) {
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

    // Procesar renovación de plan si se seleccionó uno
    $updatePlanFields = '';
    $planParams = [];
    $new_payment_status = null;
    if ($renew_plan_id > 0) {
      // Obtener información del nuevo plan
      $planStmt = $pdo->prepare("SELECT * FROM listing_pricing WHERE id = ? AND is_active = 1 LIMIT 1");
      $planStmt->execute([$renew_plan_id]);
      $newPlan = $planStmt->fetch(PDO::FETCH_ASSOC);

      if ($newPlan) {
        // Calcular nuevas fechas
        $new_start_date = date('Y-m-d H:i:s');
        $new_end_date = date('Y-m-d H:i:s', strtotime("+{$newPlan['duration_days']} days"));
        $new_payment_status = ($newPlan['price_usd'] == 0 && $newPlan['price_crc'] == 0) ? 'free' : 'pending';

        $updatePlanFields = ', pricing_plan_id = ?, start_date = ?, end_date = ?, payment_status = ?';
        $planParams = [$renew_plan_id, $new_start_date, $new_end_date, $new_payment_status];
      }
    }

    // Actualizar la publicación
    $updateStmt = $pdo->prepare("
      UPDATE real_estate_listings SET
        category_id = ?,
        title = ?,
        description = ?,
        price = ?,
        currency = ?,
        location = ?,
        province = ?,
        canton = ?,
        district = ?,
        bedrooms = ?,
        bathrooms = ?,
        area_m2 = ?,
        parking_spaces = ?,
        features = ?,
        images = ?,
        contact_name = ?,
        contact_phone = ?,
        contact_email = ?,
        contact_whatsapp = ?,
        listing_type = ?,
        updated_at = datetime('now')
        $updatePlanFields
      WHERE id = ? AND agent_id = ?
    ");

    $executeParams = [
      $category_id,
      $title,
      $description,
      $price,
      $currency,
      $location,
      $province,
      $canton,
      $district,
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
      $listing_type
    ];

    // Agregar parámetros del plan si se renovó
    if (!empty($planParams)) {
      $executeParams = array_merge($executeParams, $planParams);
    }

    // Agregar ID de listing y agent_id al final
    $executeParams[] = $listing_id;
    $executeParams[] = $agent_id;

    $updateStmt->execute($executeParams);

    // Redirigir con mensaje apropiado
    if ($renew_plan_id > 0) {
      // Si es plan de pago, redirigir a opciones de pago
      if ($new_payment_status === 'pending') {
        header('Location: payment-selection.php?listing_id=' . $listing_id);
      } else {
        // Plan gratuito - renovación completada
        header('Location: dashboard.php?msg=renewed');
      }
    } else {
      header('Location: dashboard.php?msg=updated');
    }
    exit;

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
  <title>Editar Propiedad — <?php echo APP_NAME; ?></title>
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
    .btn-danger {
      background: var(--danger);
      color: var(--white);
    }
    .btn-danger:hover {
      background: #c0392b;
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
      justify-content: space-between;
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
  </style>
</head>
<body>
  <div class="header">
    <h1><i class="fas fa-edit"></i> Editar Propiedad</h1>
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
            <input type="text" name="title" required placeholder="Ej: Casa de 3 habitaciones en Escazú" value="<?= htmlspecialchars($listing['title']) ?>">
          </div>

          <div class="form-group">
            <label>Descripción <span class="required">*</span></label>
            <textarea name="description" required placeholder="Describe la propiedad, sus características principales, ventajas, cercanía a servicios, etc."><?= htmlspecialchars($listing['description']) ?></textarea>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Categoría <span class="required">*</span></label>
              <select name="category_id" required>
                <option value="">Seleccionar categoría</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $listing['category_id'] ? 'selected' : '' ?>>
                    <?= str_replace('BR: ', '', htmlspecialchars($cat['name'])) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label>Tipo de Operación <span class="required">*</span></label>
              <select name="listing_type" required>
                <option value="sale" <?= $listing['listing_type'] === 'sale' ? 'selected' : '' ?>>Venta</option>
                <option value="rent" <?= $listing['listing_type'] === 'rent' ? 'selected' : '' ?>>Alquiler</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Precio <span class="required">*</span></label>
              <input type="number" name="price" step="0.01" min="0" required placeholder="0.00" value="<?= htmlspecialchars($listing['price']) ?>">
            </div>

            <div class="form-group">
              <label>Moneda <span class="required">*</span></label>
              <select name="currency" required>
                <option value="CRC" <?= $listing['currency'] === 'CRC' ? 'selected' : '' ?>>₡ Colones (CRC)</option>
                <option value="USD" <?= $listing['currency'] === 'USD' ? 'selected' : '' ?>>$ Dólares (USD)</option>
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
                  <option value="<?= htmlspecialchars($prov) ?>" <?= $listing['province'] === $prov ? 'selected' : '' ?>><?= htmlspecialchars($prov) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label>Cantón</label>
              <input type="text" name="canton" placeholder="Ej: Escazú" value="<?= htmlspecialchars($listing['canton']) ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Distrito</label>
              <input type="text" name="district" placeholder="Ej: San Rafael" value="<?= htmlspecialchars($listing['district']) ?>">
            </div>

            <div class="form-group">
              <label>Ubicación Específica</label>
              <input type="text" name="location" placeholder="Ej: 200m oeste de la iglesia" value="<?= htmlspecialchars($listing['location']) ?>">
            </div>
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
              <input type="number" name="bedrooms" min="0" placeholder="0" value="<?= htmlspecialchars($listing['bedrooms']) ?>">
            </div>

            <div class="form-group">
              <label>Baños</label>
              <input type="number" name="bathrooms" min="0" placeholder="0" value="<?= htmlspecialchars($listing['bathrooms']) ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Área (m²)</label>
              <input type="number" name="area_m2" step="0.01" min="0" placeholder="0.00" value="<?= htmlspecialchars($listing['area_m2']) ?>">
            </div>

            <div class="form-group">
              <label>Espacios de Parqueo</label>
              <input type="number" name="parking_spaces" min="0" placeholder="0" value="<?= htmlspecialchars($listing['parking_spaces']) ?>">
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
                <input type="checkbox" name="features[]" value="<?= htmlspecialchars($feature) ?>" id="feat_<?= md5($feature) ?>" <?= in_array($feature, $features_arr) ? 'checked' : '' ?>>
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
            <span id="photoLimitText">Plan: <?= htmlspecialchars($listing['pricing_plan_name'] ?? 'Desconocido') ?> - Límite según plan</span>
          </div>

          <!-- Imágenes existentes -->
          <?php if (!empty($images_arr)): ?>
          <div class="existing-images-section" style="margin-bottom: 20px;">
            <label style="font-weight: 600; margin-bottom: 10px; display: block;">
              <i class="fas fa-check-circle" style="color: #27ae60;"></i> Imágenes Actuales
            </label>
            <div class="image-preview-container" id="existingImages">
              <?php foreach ($images_arr as $idx => $imgUrl): ?>
              <div class="image-preview-item" id="existing-<?= $idx ?>">
                <img src="<?= htmlspecialchars($imgUrl) ?>" alt="Imagen <?= $idx + 1 ?>">
                <button type="button" class="image-preview-remove" onclick="removeExistingImage(<?= $idx ?>, '<?= htmlspecialchars(addslashes($imgUrl)) ?>')">
                  <i class="fas fa-times"></i>
                </button>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Opción 1: Subir nuevas imágenes (Drag & Drop + Botón) -->
          <div class="form-group">
            <label>
              <i class="fas fa-cloud-upload-alt"></i> Subir Nuevas Imágenes
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

            <!-- Vista previa de nuevas imágenes -->
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
              <i class="fas fa-link"></i> Agregar URLs de Imágenes
              <span style="color: #666; font-size: 0.9em;">(Google Drive, Imgur, Dropbox, etc.)</span>
            </label>
            <textarea id="imageUrlsTextarea" name="images" placeholder="Ingresá las URLs de las imágenes, una por línea&#10;https://ejemplo.com/imagen1.jpg&#10;https://ejemplo.com/imagen2.jpg"><?= implode("\n", $images_arr) ?></textarea>
            <p class="help-text">
              <i class="fas fa-info-circle"></i>
              Ingresá una URL por línea. Podés usar servicios como Imgur, Dropbox, o Google Drive para alojar tus imágenes.
            </p>
          </div>
        </div>
      </div>

      <!-- ESTADO DE LA PUBLICACIÓN Y RENOVACIÓN -->
      <?php if ($isExpired || $daysRemaining <= 7): ?>
      <div class="form-card" style="border: 2px solid <?= $isExpired ? '#e74c3c' : '#f39c12' ?>; background: <?= $isExpired ? '#fee' : '#fff3cd' ?>;">
        <div class="form-section">
          <h2 class="section-title" style="color: <?= $isExpired ? '#e74c3c' : '#f39c12' ?>;">
            <i class="fas fa-<?= $isExpired ? 'exclamation-triangle' : 'clock' ?>"></i>
            <?= $isExpired ? 'Publicación Expirada' : 'Publicación por Vencer' ?>
          </h2>

          <?php if ($isExpired): ?>
            <div style="background: white; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
              <p style="color: #e74c3c; font-weight: 600; margin-bottom: 1rem;">
                <i class="fas fa-times-circle"></i> Tu publicación expiró hace <?= abs($daysRemaining) ?> día<?= abs($daysRemaining) !== 1 ? 's' : '' ?> y ya no es visible en el listado público.
              </p>
              <p style="color: #4a5568; margin-bottom: 0;">
                Seleccioná un nuevo plan abajo para renovar tu publicación y que vuelva a ser visible.
              </p>
            </div>
          <?php else: ?>
            <div style="background: white; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
              <p style="color: #f39c12; font-weight: 600; margin-bottom: 0.5rem;">
                <i class="fas fa-hourglass-half"></i> Tu publicación vence en <?= $daysRemaining ?> día<?= $daysRemaining !== 1 ? 's' : '' ?>.
              </p>
              <p style="color: #4a5568; margin-bottom: 0;">
                Podés renovar tu plan ahora para extender la vigencia de tu publicación.
              </p>
            </div>
          <?php endif; ?>

          <!-- Selector de plan de renovación -->
          <div class="form-group">
            <label style="font-size: 1.125rem; color: #002b7f;">
              <i class="fas fa-sync-alt"></i> Renovar Plan de Publicación
            </label>
            <p class="help-text" style="margin-bottom: 1rem;">
              Seleccioná un plan para extender la vigencia de tu publicación. Los cambios se aplicarán al guardar.
            </p>

            <div class="pricing-plans" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
              <?php foreach ($pricing_plans as $plan): ?>
                <label class="plan-card" for="renew_plan_<?= $plan['id'] ?>" style="border: 2px solid #cbd5e0; border-radius: 12px; padding: 1.5rem; cursor: pointer; transition: all 0.2s; position: relative;">
                  <input type="radio" name="renew_plan_id" value="<?= $plan['id'] ?>" id="renew_plan_<?= $plan['id'] ?>" style="position: absolute; opacity: 0;">
                  <div class="plan-content" style="padding: 0.5rem; border-radius: 12px; transition: background 0.2s;">
                    <div class="plan-name" style="font-weight: 700; font-size: 1.125rem; margin-bottom: 0.5rem; color: #1a1a1a;">
                      <?= htmlspecialchars($plan['name']) ?>
                    </div>
                    <div class="plan-price" style="font-size: 1.5rem; font-weight: 800; color: #27ae60; margin-bottom: 0.5rem;">
                      <?php if ($plan['price_usd'] == 0 && $plan['price_crc'] == 0): ?>
                        Gratis
                      <?php else: ?>
                        $<?= number_format($plan['price_usd'], 2) ?> / ₡<?= number_format($plan['price_crc'], 0) ?>
                      <?php endif; ?>
                    </div>
                    <div class="plan-duration" style="font-size: 0.875rem; color: #718096;">
                      <?= $plan['duration_days'] ?> días
                    </div>
                    <div style="background: #e3f2fd; padding: 0.5rem; border-radius: 6px; margin-top: 0.5rem; font-size: 0.9rem;">
                      <i class="fas fa-camera" style="color: #1976d2;"></i>
                      Hasta <strong><?= (int)($plan['max_photos'] ?? 3) ?> fotos</strong>
                    </div>
                    <?php if ($plan['description']): ?>
                      <p class="help-text" style="margin-top: 0.5rem;"><?= htmlspecialchars($plan['description']) ?></p>
                    <?php endif; ?>
                  </div>
                  <div class="plan-checkmark" style="display: none; position: absolute; top: 1rem; right: 1rem; width: 24px; height: 24px; background: #27ae60; color: white; border-radius: 50%; text-align: center; line-height: 24px; font-size: 0.875rem;">
                    <i class="fas fa-check"></i>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <style>
            .plan-card:hover {
              border-color: #002b7f !important;
              box-shadow: 0 4px 12px rgba(0,43,127,0.15);
            }
            .plan-card input[type="radio"]:checked + .plan-content {
              background: rgba(0,43,127,0.05);
            }
            .plan-card input[type="radio"]:checked ~ .plan-checkmark {
              display: block !important;
            }
          </style>
        </div>
      </div>
      <?php endif; ?>

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
              <input type="text" name="contact_name" placeholder="Tu nombre o de la inmobiliaria" value="<?= htmlspecialchars($listing['contact_name']) ?>">
            </div>

            <div class="form-group">
              <label>Teléfono</label>
              <input type="tel" name="contact_phone" placeholder="8888-8888" value="<?= htmlspecialchars($listing['contact_phone']) ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>WhatsApp</label>
              <input type="tel" name="contact_whatsapp" placeholder="8888-8888" value="<?= htmlspecialchars($listing['contact_whatsapp']) ?>">
              <p class="help-text">Los clientes podrán contactarte directamente por WhatsApp</p>
            </div>

            <div class="form-group">
              <label>Email</label>
              <input type="email" name="contact_email" placeholder="correo@ejemplo.com" value="<?= htmlspecialchars($listing['contact_email']) ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- BOTONES DE ACCIÓN -->
      <div class="submit-section">
        <a href="dashboard.php" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn">
          <i class="fas fa-save"></i> Guardar Cambios
        </button>
      </div>
    </form>
  </div>

  <script>
    // =========================
    // Sistema de Upload de Imágenes (Edición)
    // =========================

    // Información de planes desde la BD
    const plansData = <?= json_encode($pricing_plans) ?>;

    // Crear objeto de límites de fotos desde los datos de la BD
    const photoLimits = {};
    const planNames = {};
    plansData.forEach(plan => {
      photoLimits[plan.id] = parseInt(plan.max_photos) || 3;
      planNames[plan.id] = plan.name;
    });

    // Estado global
    let uploadedImages = [];  // URLs de imágenes recién subidas
    let existingImages = <?= json_encode($images_arr) ?>;  // Imágenes que ya existen
    let removedExistingImages = [];  // Imágenes existentes que se marcaron para eliminar
    let selectedPlan = <?= (int)$listing['pricing_plan_id'] ?>;
    let maxPhotos = photoLimits[selectedPlan] || 3;

    // Elementos DOM
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const imagePreview = document.getElementById('imagePreview');
    const uploadedImageUrls = document.getElementById('uploadedImageUrls');
    const imageUrlsTextarea = document.getElementById('imageUrlsTextarea');
    const photoLimitInfo = document.getElementById('photoLimitInfo');
    const photoLimitText = document.getElementById('photoLimitText');

    // =========================
    // Actualizar contador de fotos
    // =========================
    function updatePhotoCount() {
      const existingCount = existingImages.length - removedExistingImages.length;
      const newCount = uploadedImages.length + countUrlImages();
      const total = existingCount + newCount;

      photoLimitText.innerHTML = `
        <strong>${planNames[selectedPlan] || 'Plan actual'}</strong>:
        ${total} de ${maxPhotos} fotos
        <span style="color: #666;">(${existingCount} existentes + ${newCount} nuevas)</span>
      `;

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

    // =========================
    // Eliminar imagen existente
    // =========================
    window.removeExistingImage = function(index, url) {
      if (!confirm('¿Estás seguro de eliminar esta imagen?')) {
        return;
      }

      const element = document.getElementById('existing-' + index);
      if (element) {
        element.remove();
        removedExistingImages.push(url);

        // Actualizar el textarea para reflejar la eliminación
        const currentUrls = imageUrlsTextarea.value.split('\n').map(u => u.trim()).filter(u => u);
        const newUrls = currentUrls.filter(u => u !== url);
        imageUrlsTextarea.value = newUrls.join('\n');

        updatePhotoCount();
      }
    };

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
      const existingCount = existingImages.length - removedExistingImages.length;
      const currentNewCount = uploadedImages.length + countUrlImages();
      const currentTotal = existingCount + currentNewCount;

      if (currentTotal + files.length > maxPhotos) {
        alert(`Tu plan permite máximo ${maxPhotos} fotos. Ya tenés ${currentTotal} imagen(es).`);
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
      const existingCount = existingImages.length - removedExistingImages.length;
      const currentNewCount = uploadedImages.length + countUrlImages();

      const formData = new FormData();
      formData.append('images[]', file);
      formData.append('pricing_plan_id', selectedPlan);
      formData.append('current_images_count', existingCount + currentNewCount);
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
    // Eliminar imagen nueva
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
    // Contar imágenes de URLs nuevas en el textarea
    // =========================
    function countUrlImages() {
      const currentUrls = imageUrlsTextarea.value.trim();
      if (!currentUrls) return 0;

      const urls = currentUrls.split(/[\r\n,]+/).map(u => u.trim()).filter(u => u);

      // Filtrar URLs que no sean de las imágenes existentes originales
      const newUrls = urls.filter(url => !existingImages.includes(url));

      return newUrls.length;
    }

    // Monitorear cambios en el textarea de URLs
    imageUrlsTextarea.addEventListener('input', updatePhotoCount);

    // =========================
    // Validación antes de enviar formulario
    // =========================
    document.querySelector('form').addEventListener('submit', function(e) {
      const existingCount = existingImages.length - removedExistingImages.length;
      const newCount = uploadedImages.length + countUrlImages();
      const total = existingCount + newCount;

      // Combinar URLs de imágenes subidas con URLs del textarea
      if (uploadedImages.length > 0) {
        const existingUrls = imageUrlsTextarea.value.trim();
        const combinedUrls = existingUrls
          ? existingUrls + '\n' + uploadedImages.join('\n')
          : uploadedImages.join('\n');
        imageUrlsTextarea.value = combinedUrls;
      }

      if (total > maxPhotos) {
        e.preventDefault();
        alert(`Tu plan permite máximo ${maxPhotos} fotos. Tenés ${total} imagen(es) en total.`);
        return false;
      }
    });

    // =========================
    // Event listener para cambio de plan
    // =========================
    document.querySelectorAll('input[name="renew_plan_id"]').forEach(radio => {
      radio.addEventListener('change', function() {
        if (this.checked) {
          const newPlanId = parseInt(this.value);
          selectedPlan = newPlanId;
          maxPhotos = photoLimits[selectedPlan] || 3;
          console.log(`Plan cambiado a: ${planNames[selectedPlan]} (ID: ${selectedPlan}), Máximo de fotos: ${maxPhotos}`);
          updatePhotoCount();
        }
      });
    });

    // Inicializar contador
    updatePhotoCount();
  </script>
</body>
</html>
