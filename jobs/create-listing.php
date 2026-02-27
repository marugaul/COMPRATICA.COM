<?php
// jobs/create-listing.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Verificar sesión
if (!isset($_SESSION['employer_id']) || $_SESSION['employer_id'] <= 0) {
  header('Location: login.php');
  exit;
}

$pdo = db();
$employer_id = (int)$_SESSION['employer_id'];
$employer_name = $_SESSION['employer_name'] ?? 'Usuario';

// Variables para el formulario
$error = '';
$success = '';

// Obtener categorías
$stmt = $pdo->query("SELECT * FROM job_categories WHERE active = 1 ORDER BY parent_category, display_order");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$job_categories = array_filter($categories, fn($c) => str_starts_with($c['name'], 'EMP:'));
$service_categories = array_filter($categories, fn($c) => str_starts_with($c['name'], 'SERV:'));

// Cargar planes de precios
$planStmt = $pdo->query("SELECT * FROM job_pricing WHERE is_active=1 ORDER BY display_order ASC");
$pricing_plans = $planStmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    // Validar CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
      throw new Exception('Token de seguridad inválido');
    }

    // Validar campos requeridos
    $listing_type = $_POST['listing_type'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $pricing_plan_id = (int)($_POST['pricing_plan_id'] ?? 0);

    if (!in_array($listing_type, ['job', 'service'])) {
      throw new Exception('Tipo de publicación inválido');
    }

    if (empty($title) || strlen($title) < 5) {
      throw new Exception('El título debe tener al menos 5 caracteres');
    }

    if (empty($description) || strlen($description) < 20) {
      throw new Exception('La descripción debe tener al menos 20 caracteres');
    }

    if ($pricing_plan_id <= 0) {
      throw new Exception('Debes seleccionar un plan de publicación');
    }

    // Obtener detalles del plan
    $planStmt = $pdo->prepare("SELECT * FROM job_pricing WHERE id = ? LIMIT 1");
    $planStmt->execute([$pricing_plan_id]);
    $plan = $planStmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
      throw new Exception('Plan de precios inválido');
    }

    // Determinar estado de pago
    $payment_status = ($plan['price_usd'] == 0 && $plan['price_crc'] == 0) ? 'free' : 'pending';

    // Calcular fechas
    $start_date = date('Y-m-d H:i:s');
    $end_date = date('Y-m-d H:i:s', strtotime("+{$plan['duration_days']} days"));

    // Preparar datos para inserción
    $data = [
      'employer_id' => $employer_id,
      'listing_type' => $listing_type,
      'title' => $title,
      'description' => $description,
      'category' => $category,
      'location' => trim($_POST['location'] ?? ''),
      'province' => $_POST['province'] ?? '',
      'canton' => trim($_POST['canton'] ?? ''),
      'distrito' => trim($_POST['distrito'] ?? ''),
      'remote_allowed' => isset($_POST['remote_allowed']) ? 1 : 0,
      'requirements' => trim($_POST['requirements'] ?? ''),
      'benefits' => trim($_POST['benefits'] ?? ''),
      'contact_name' => trim($_POST['contact_name'] ?? ''),
      'contact_email' => trim($_POST['contact_email'] ?? ''),
      'contact_phone' => trim($_POST['contact_phone'] ?? ''),
      'contact_whatsapp' => trim($_POST['contact_whatsapp'] ?? ''),
      'application_url' => trim($_POST['application_url'] ?? ''),
      'pricing_plan_id' => $pricing_plan_id,
      'payment_status' => $payment_status,
      'is_active' => 1,
      'start_date' => $start_date,
      'end_date' => $end_date
    ];

    // Campos específicos de empleo
    if ($listing_type === 'job') {
      $data['job_type'] = $_POST['job_type'] ?? null;
      $data['salary_min'] = !empty($_POST['salary_min']) ? (float)$_POST['salary_min'] : null;
      $data['salary_max'] = !empty($_POST['salary_max']) ? (float)$_POST['salary_max'] : null;
      $data['salary_currency'] = $_POST['salary_currency'] ?? 'CRC';
      $data['salary_period'] = $_POST['salary_period'] ?? null;
      $data['service_price'] = null;
      $data['service_price_type'] = null;
    }
    // Campos específicos de servicio
    else {
      $data['service_price'] = !empty($_POST['service_price']) ? (float)$_POST['service_price'] : null;
      $data['service_price_type'] = $_POST['service_price_type'] ?? null;
      $data['job_type'] = null;
      $data['salary_min'] = null;
      $data['salary_max'] = null;
      $data['salary_currency'] = null;
      $data['salary_period'] = null;
    }

    // Insertar en la base de datos
    $sql = "INSERT INTO job_listings (
      employer_id, listing_type, title, description, category,
      job_type, salary_min, salary_max, salary_currency, salary_period,
      service_price, service_price_type,
      location, province, canton, distrito, remote_allowed,
      requirements, benefits,
      contact_name, contact_email, contact_phone, contact_whatsapp, application_url,
      pricing_plan_id, payment_status, is_active, start_date, end_date
    ) VALUES (
      :employer_id, :listing_type, :title, :description, :category,
      :job_type, :salary_min, :salary_max, :salary_currency, :salary_period,
      :service_price, :service_price_type,
      :location, :province, :canton, :distrito, :remote_allowed,
      :requirements, :benefits,
      :contact_name, :contact_email, :contact_phone, :contact_whatsapp, :application_url,
      :pricing_plan_id, :payment_status, :is_active, :start_date, :end_date
    )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);

    $listing_id = (int)$pdo->lastInsertId();

    // Redirigir según el tipo de plan
    if ($payment_status === 'free') {
      header('Location: dashboard.php?success=1');
    } else {
      header('Location: payment-selection.php?listing_id=' . $listing_id);
    }
    exit;

  } catch (Exception $e) {
    $error = $e->getMessage();
  }
}

// Generar CSRF token
if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Provincias de Costa Rica
$provincias = [
  'San José', 'Alajuela', 'Cartago', 'Heredia',
  'Guanacaste', 'Puntarenas', 'Limón'
];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Crear Publicación — <?php echo APP_NAME; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    :root {
      --primary: #27ae60;
      --primary-dark: #229954;
      --white: #ffffff;
      --dark: #1a1a1a;
      --gray-700: #4a5568;
      --gray-500: #718096;
      --gray-300: #cbd5e0;
      --gray-100: #f7fafc;
      --bg: #f8f9fa;
      --radius: 8px;
      --danger: #e74c3c;
      --warning: #f39c12;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      color: var(--dark);
    }

    .header {
      background: var(--white);
      border-bottom: 1px solid var(--gray-300);
      padding: 1.5rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .header h1 {
      font-size: 1.5rem;
      color: var(--dark);
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: 1rem;
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
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.25s;
      font-size: 1rem;
    }

    .btn:hover {
      background: var(--primary-dark);
    }

    .btn-secondary {
      background: var(--gray-300);
      color: var(--dark);
    }

    .btn-secondary:hover {
      background: var(--gray-500);
      color: var(--white);
    }

    .container {
      max-width: 900px;
      margin: 2rem auto;
      padding: 0 2rem;
    }

    .card {
      background: var(--white);
      padding: 2rem;
      border-radius: var(--radius);
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      margin-bottom: 2rem;
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 600;
      color: var(--gray-700);
    }

    .form-group label.required::after {
      content: ' *';
      color: var(--danger);
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 0.75rem;
      border: 1px solid var(--gray-300);
      border-radius: var(--radius);
      font-size: 1rem;
      font-family: inherit;
      transition: all 0.25s;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.1);
    }

    .form-group textarea {
      resize: vertical;
      min-height: 120px;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }

    .alert {
      padding: 1rem 1.5rem;
      border-radius: var(--radius);
      margin-bottom: 1.5rem;
    }

    .alert-error {
      background: #fee;
      color: #c33;
      border: 1px solid #fcc;
    }

    .alert-success {
      background: #efe;
      color: #3c3;
      border: 1px solid #cfc;
    }

    .type-selector {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin-bottom: 2rem;
    }

    .type-option {
      padding: 1.5rem;
      border: 2px solid var(--gray-300);
      border-radius: var(--radius);
      cursor: pointer;
      text-align: center;
      transition: all 0.25s;
    }

    .type-option:hover {
      border-color: var(--primary);
      background: rgba(39, 174, 96, 0.05);
    }

    .type-option.active {
      border-color: var(--primary);
      background: rgba(39, 174, 96, 0.1);
    }

    .type-option input[type="radio"] {
      display: none;
    }

    .type-option i {
      font-size: 2rem;
      color: var(--primary);
      margin-bottom: 0.5rem;
    }

    .type-option h3 {
      font-size: 1.25rem;
      margin-bottom: 0.25rem;
    }

    .checkbox-group {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .checkbox-group input[type="checkbox"] {
      width: auto;
    }

    .help-text {
      font-size: 0.875rem;
      color: var(--gray-500);
      margin-top: 0.25rem;
    }

    .form-actions {
      display: flex;
      gap: 1rem;
      margin-top: 2rem;
    }

    .hidden {
      display: none;
    }

    .plan-option {
      position: relative;
    }

    .plan-option:hover {
      border-color: var(--primary) !important;
      background: rgba(39, 174, 96, 0.05);
    }

    .plan-option.selected {
      border-color: var(--primary) !important;
      background: rgba(39, 174, 96, 0.1);
    }

    @media (max-width: 768px) {
      .form-row {
        grid-template-columns: 1fr;
      }

      .type-selector {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="header">
    <h1><i class="fas fa-plus-circle"></i> Nueva Publicación</h1>
    <div class="user-info">
      <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($employer_name); ?></span>
      <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>
  </div>

  <div class="container">
    <?php if ($error): ?>
      <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <form method="POST" id="createListingForm">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

      <!-- Selector de tipo -->
      <div class="card">
        <h2 style="margin-bottom: 1rem;">¿Qué querés publicar?</h2>
        <div class="type-selector">
          <label class="type-option active" data-type="job">
            <input type="radio" name="listing_type" value="job" checked>
            <div>
              <i class="fas fa-briefcase"></i>
              <h3>Empleo</h3>
              <p class="help-text">Oferta de trabajo</p>
            </div>
          </label>
          <label class="type-option" data-type="service">
            <input type="radio" name="listing_type" value="service">
            <div>
              <i class="fas fa-tools"></i>
              <h3>Servicio</h3>
              <p class="help-text">Ofrezco un servicio</p>
            </div>
          </label>
        </div>
      </div>

      <!-- Información básica -->
      <div class="card">
        <h2 style="margin-bottom: 1rem;">Información Básica</h2>

        <div class="form-group">
          <label class="required">Título</label>
          <input type="text" name="title" required maxlength="200"
            placeholder="Ej: Desarrollador Web Full Stack / Servicio de Plomería">
        </div>

        <div class="form-group">
          <label class="required">Descripción</label>
          <textarea name="description" required
            placeholder="Describí en detalle la oferta de empleo o servicio..."></textarea>
        </div>

        <div class="form-group">
          <label>Categoría</label>
          <select name="category">
            <option value="">Seleccionar categoría</option>
            <optgroup label="Empleos" id="job-categories">
              <?php foreach ($job_categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat['name']); ?>">
                  <?php echo htmlspecialchars(str_replace('EMP: ', '', $cat['name'])); ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
            <optgroup label="Servicios" id="service-categories" class="hidden">
              <?php foreach ($service_categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat['name']); ?>">
                  <?php echo htmlspecialchars(str_replace('SERV: ', '', $cat['name'])); ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          </select>
        </div>
      </div>

      <!-- Detalles de empleo (solo para empleos) -->
      <div class="card job-specific">
        <h2 style="margin-bottom: 1rem;">Detalles del Empleo</h2>

        <div class="form-group">
          <label>Tipo de Empleo</label>
          <select name="job_type">
            <option value="">Seleccionar tipo</option>
            <option value="full-time">Tiempo Completo</option>
            <option value="part-time">Medio Tiempo</option>
            <option value="freelance">Freelance</option>
            <option value="contract">Por Contrato</option>
            <option value="internship">Pasantía</option>
          </select>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Salario Mínimo</label>
            <input type="number" name="salary_min" step="0.01" min="0" placeholder="Ej: 500000">
          </div>
          <div class="form-group">
            <label>Salario Máximo</label>
            <input type="number" name="salary_max" step="0.01" min="0" placeholder="Ej: 800000">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Moneda</label>
            <select name="salary_currency">
              <option value="CRC">Colones (₡)</option>
              <option value="USD">Dólares ($)</option>
            </select>
          </div>
          <div class="form-group">
            <label>Período</label>
            <select name="salary_period">
              <option value="">Seleccionar período</option>
              <option value="hour">Por Hora</option>
              <option value="day">Por Día</option>
              <option value="week">Por Semana</option>
              <option value="month">Por Mes</option>
              <option value="year">Por Año</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Detalles de servicio (solo para servicios) -->
      <div class="card service-specific hidden">
        <h2 style="margin-bottom: 1rem;">Detalles del Servicio</h2>

        <div class="form-row">
          <div class="form-group">
            <label>Precio</label>
            <input type="number" name="service_price" step="0.01" min="0" placeholder="Ej: 25000">
          </div>
          <div class="form-group">
            <label>Tipo de Precio</label>
            <select name="service_price_type">
              <option value="">Seleccionar tipo</option>
              <option value="fixed">Precio Fijo</option>
              <option value="hourly">Por Hora</option>
              <option value="daily">Por Día</option>
              <option value="negotiable">Negociable</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Ubicación -->
      <div class="card">
        <h2 style="margin-bottom: 1rem;">Ubicación</h2>

        <div class="form-group">
          <label>Provincia</label>
          <select name="province">
            <option value="">Seleccionar provincia</option>
            <?php foreach ($provincias as $prov): ?>
              <option value="<?php echo htmlspecialchars($prov); ?>"><?php echo htmlspecialchars($prov); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Cantón</label>
            <input type="text" name="canton" placeholder="Ej: San José">
          </div>
          <div class="form-group">
            <label>Distrito</label>
            <input type="text" name="distrito" placeholder="Ej: Carmen">
          </div>
        </div>

        <div class="form-group">
          <label>Dirección Exacta</label>
          <input type="text" name="location" placeholder="Ej: 100m norte de la iglesia">
        </div>

        <div class="form-group checkbox-group">
          <input type="checkbox" name="remote_allowed" id="remote_allowed">
          <label for="remote_allowed">Se permite trabajo remoto</label>
        </div>
      </div>

      <!-- Requisitos y beneficios -->
      <div class="card">
        <h2 style="margin-bottom: 1rem;">Requisitos y Beneficios</h2>

        <div class="form-group">
          <label>Requisitos</label>
          <textarea name="requirements"
            placeholder="Listá los requisitos necesarios para aplicar..."></textarea>
          <p class="help-text">Experiencia, educación, habilidades, etc.</p>
        </div>

        <div class="form-group">
          <label>Beneficios</label>
          <textarea name="benefits"
            placeholder="¿Qué beneficios ofrecés?"></textarea>
          <p class="help-text">Seguro médico, aguinaldo, vacaciones, etc.</p>
        </div>
      </div>

      <!-- Información de contacto -->
      <div class="card">
        <h2 style="margin-bottom: 1rem;">Información de Contacto</h2>

        <div class="form-group">
          <label>Nombre de Contacto</label>
          <input type="text" name="contact_name" placeholder="Nombre de la persona de contacto">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Email de Contacto</label>
            <input type="email" name="contact_email" placeholder="correo@ejemplo.com">
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
            <p class="help-text">Número con código de país: +506</p>
          </div>
          <div class="form-group">
            <label>URL de Aplicación</label>
            <input type="url" name="application_url" placeholder="https://ejemplo.com/aplicar">
          </div>
        </div>
      </div>

      <!-- Plan de Publicación -->
      <div class="card">
        <h2 style="margin-bottom: 1rem;"><i class="fas fa-rocket"></i> Plan de Publicación</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
          <?php foreach ($pricing_plans as $plan): ?>
            <?php $isFree = ((float)$plan['price_usd'] == 0 && (float)$plan['price_crc'] == 0); ?>
            <label class="plan-option <?php echo $isFree ? 'selected' : ''; ?>" style="border: 2px solid var(--gray-300); border-radius: 8px; padding: 1.5rem; cursor: pointer; transition: all 0.25s; text-align: center;">
              <input type="radio" name="pricing_plan_id" value="<?php echo $plan['id']; ?>" <?php echo $isFree ? 'checked' : ''; ?> style="display: none;">
              <div style="font-size: 0.75rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem;">
                <?php echo $isFree ? '✓ GRATIS' : '★ DESTACADO'; ?>
              </div>
              <div style="font-size: 1.5rem; font-weight: 700; color: var(--dark); margin-bottom: 0.25rem;">
                <?php if ($isFree): ?>
                  $0
                <?php else: ?>
                  $<?php echo number_format((float)$plan['price_usd'], 2); ?>
                <?php endif; ?>
              </div>
              <div style="font-weight: 600; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($plan['name']); ?></div>
              <div style="font-size: 0.875rem; color: var(--gray-500);"><?php echo htmlspecialchars($plan['description'] ?? ''); ?></div>
              <?php if (!$isFree): ?>
                <div style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--gray-500);">
                  ₡<?php echo number_format((float)$plan['price_crc'], 0, '.', ','); ?> CRC
                </div>
              <?php endif; ?>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="help-text"><i class="fas fa-info-circle"></i> Los planes de pago se activan al confirmar el pago via SINPE Móvil o PayPal. Podés empezar gratis sin compromiso.</p>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn">
          <i class="fas fa-check"></i> Publicar
        </button>
        <a href="dashboard.php" class="btn btn-secondary">
          <i class="fas fa-times"></i> Cancelar
        </a>
      </div>
    </form>
  </div>

  <script>
    // Cambiar entre tipo de publicación
    const typeOptions = document.querySelectorAll('.type-option');
    const jobSpecific = document.querySelector('.job-specific');
    const serviceSpecific = document.querySelector('.service-specific');
    const jobCategories = document.getElementById('job-categories');
    const serviceCategories = document.getElementById('service-categories');

    typeOptions.forEach(option => {
      option.addEventListener('click', function() {
        // Actualizar UI
        typeOptions.forEach(opt => opt.classList.remove('active'));
        this.classList.add('active');

        // Marcar radio
        const radio = this.querySelector('input[type="radio"]');
        radio.checked = true;

        const type = this.dataset.type;

        // Mostrar/ocultar secciones específicas
        if (type === 'job') {
          jobSpecific.classList.remove('hidden');
          serviceSpecific.classList.add('hidden');
          jobCategories.classList.remove('hidden');
          serviceCategories.classList.add('hidden');
        } else {
          jobSpecific.classList.add('hidden');
          serviceSpecific.classList.remove('hidden');
          jobCategories.classList.add('hidden');
          serviceCategories.classList.remove('hidden');
        }
      });
    });

    // Selección de planes
    const planOptions = document.querySelectorAll('.plan-option');
    planOptions.forEach(option => {
      option.addEventListener('click', function() {
        planOptions.forEach(opt => opt.classList.remove('selected'));
        this.classList.add('selected');
        this.querySelector('input[type="radio"]').checked = true;
      });
    });
  </script>
</body>
</html>
