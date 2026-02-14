<?php
// real-estate/create-listing.php
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

    // Imágenes (URLs separadas por comas o líneas)
    $images_input = trim($_POST['images'] ?? '');
    $images_array = [];
    if ($images_input !== '') {
      // Dividir por comas o saltos de línea
      $images_array = preg_split('/[\r\n,]+/', $images_input);
      $images_array = array_filter(array_map('trim', $images_array));
    }
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

    // Insertar la publicación
    $insertStmt = $pdo->prepare("
      INSERT INTO real_estate_listings (
        agent_id, user_id, category_id, title, description, price, currency,
        location, province, canton, district,
        bedrooms, bathrooms, area_m2, parking_spaces,
        features, images,
        contact_name, contact_phone, contact_email, contact_whatsapp,
        listing_type, pricing_plan_id,
        is_active, start_date, end_date, payment_status,
        created_at, updated_at
      ) VALUES (
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?,
        ?, ?, ?, ?,
        ?, ?,
        1, ?, ?, ?,
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
      // Redirigir a página de pago (por implementar)
      header('Location: dashboard.php?msg=created_pending&listing_id=' . $listing_id);
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
    }
  </style>
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
              <input type="text" name="canton" placeholder="Ej: Escazú">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Distrito</label>
              <input type="text" name="district" placeholder="Ej: San Rafael">
            </div>

            <div class="form-group">
              <label>Ubicación Específica</label>
              <input type="text" name="location" placeholder="Ej: 200m oeste de la iglesia">
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

          <div class="form-group">
            <label>URLs de Imágenes</label>
            <textarea name="images" placeholder="Ingresá las URLs de las imágenes, una por línea&#10;https://ejemplo.com/imagen1.jpg&#10;https://ejemplo.com/imagen2.jpg"></textarea>
            <p class="help-text">Ingresá una URL por línea. Podés usar servicios como Imgur, Dropbox, o Google Drive para alojar tus imágenes.</p>
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
              <input type="tel" name="contact_phone" placeholder="8888-8888">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>WhatsApp</label>
              <input type="tel" name="contact_whatsapp" placeholder="8888-8888">
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
        <button type="submit" class="btn">
          <i class="fas fa-save"></i> Publicar Propiedad
        </button>
      </div>
    </form>
  </div>
</body>
</html>
