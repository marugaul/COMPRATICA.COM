<?php
// services/create-listing.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['agent_id']) || (int)$_SESSION['agent_id'] <= 0) {
    header('Location: login.php');
    exit;
}

$pdo       = db();
$agent_id  = (int)$_SESSION['agent_id'];
$agent_name = $_SESSION['agent_name'] ?? 'Usuario';
$msg = '';
$ok  = false;

// Cargar categorías de servicios
$categories = [];
try {
    $catStmt = $pdo->query("SELECT id, name, icon FROM categories WHERE active=1 AND name LIKE 'SERV:%' ORDER BY display_order ASC");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('[services/create-listing.php] Error al cargar categorías: ' . $e->getMessage());
}

// Cargar planes de precios
$pricing_plans = [];
try {
    $planStmt = $pdo->query("SELECT * FROM service_pricing WHERE is_active=1 ORDER BY display_order ASC");
    $pricing_plans = $planStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('[services/create-listing.php] Error al cargar planes: ' . $e->getMessage());
}

// Provincias de Costa Rica
$provinces = ['San José','Alajuela','Cartago','Heredia','Guanacaste','Puntarenas','Limón'];

// Habilidades / herramientas comunes por categoría
$common_skills = [
    'Microsoft Office', 'Google Workspace', 'Zoom/Teams', 'Inglés', 'Español',
    'Facturación electrónica', 'Contabilidad', 'Redes sociales', 'Diseño gráfico',
    'Fotografía', 'Edición de video', 'Mantenimiento eléctrico', 'Plomería',
    'Pintura', 'Soldadura', 'Carpintería', 'Fontanería', 'Jardinería',
    'Licencia de conducir B1', 'Vehículo propio', 'Computadora',
];

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $service_type = trim($_POST['service_type'] ?? 'presencial');
        $price_from  = (float)($_POST['price_from'] ?? 0);
        $price_to    = (float)($_POST['price_to'] ?? 0);
        $price_type  = trim($_POST['price_type'] ?? 'hora');
        $currency    = trim($_POST['currency'] ?? 'CRC');

        $province             = trim($_POST['province'] ?? '');
        $canton               = trim($_POST['canton'] ?? '');
        $district             = trim($_POST['district'] ?? '');
        $location_description = trim($_POST['location_description'] ?? '');
        $experience_years     = max(0, (int)($_POST['experience_years'] ?? 0));
        $availability         = trim($_POST['availability'] ?? '');

        $contact_name      = trim($_POST['contact_name'] ?? '');
        $contact_phone     = trim($_POST['contact_phone'] ?? '');
        $contact_email     = trim($_POST['contact_email'] ?? '');
        $contact_whatsapp  = trim($_POST['contact_whatsapp'] ?? '');
        $website           = trim($_POST['website'] ?? '');

        $pricing_plan_id = (int)($_POST['pricing_plan_id'] ?? 0);

        // Habilidades
        $selected_skills = $_POST['skills'] ?? [];
        $custom_skills   = array_filter(array_map('trim', explode(',', $_POST['custom_skills'] ?? '')));
        $all_skills      = array_values(array_unique(array_merge($selected_skills, $custom_skills)));
        $skills_json     = json_encode($all_skills);

        // Imágenes subidas via upload-image.php
        $uploaded_images = $_POST['uploaded_image_urls'] ?? '';
        $images_array    = [];
        if (!empty($uploaded_images)) {
            $decoded = json_decode($uploaded_images, true);
            if (is_array($decoded)) $images_array = $decoded;
        }
        $images_json = json_encode($images_array);

        // Validaciones
        if ($title === '' || $description === '' || $category_id <= 0 || $pricing_plan_id <= 0) {
            throw new RuntimeException('Por favor completá todos los campos requeridos (título, descripción, categoría y plan).');
        }
        if ($contact_phone === '' && $contact_email === '' && $contact_whatsapp === '') {
            throw new RuntimeException('Debés proporcionar al menos un método de contacto.');
        }
        if (!in_array($service_type, ['presencial', 'virtual', 'ambos'])) $service_type = 'presencial';
        if (!in_array($price_type, ['hora', 'proyecto', 'mensual', 'consulta', 'negociable'])) $price_type = 'hora';
        if (!in_array($currency, ['CRC', 'USD'])) $currency = 'CRC';

        // Obtener plan de precios
        $planStmt = $pdo->prepare("SELECT * FROM service_pricing WHERE id = ? LIMIT 1");
        $planStmt->execute([$pricing_plan_id]);
        $plan = $planStmt->fetch(PDO::FETCH_ASSOC);
        if (!$plan) throw new RuntimeException('Plan de precios inválido.');

        $start_date     = date('Y-m-d H:i:s');
        $end_date       = date('Y-m-d H:i:s', strtotime("+{$plan['duration_days']} days"));
        $payment_status = ((float)$plan['price_usd'] == 0 && (float)$plan['price_crc'] == 0) ? 'free' : 'pending';

        $insertStmt = $pdo->prepare("
            INSERT INTO service_listings (
                agent_id, category_id, title, description,
                service_type, price_from, price_to, price_type, currency,
                province, canton, district, location_description,
                experience_years, skills, availability, images,
                contact_name, contact_phone, contact_email, contact_whatsapp, website,
                pricing_plan_id, is_active, start_date, end_date, payment_status,
                created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, 1, ?, ?, ?,
                datetime('now'), datetime('now')
            )
        ");
        $insertStmt->execute([
            $agent_id, $category_id, $title, $description,
            $service_type, $price_from, $price_to, $price_type, $currency,
            $province, $canton, $district, $location_description,
            $experience_years, $skills_json, $availability, $images_json,
            $contact_name, $contact_phone, $contact_email, $contact_whatsapp, $website,
            $pricing_plan_id, $start_date, $end_date, $payment_status
        ]);

        $listing_id = (int)$pdo->lastInsertId();

        if ($payment_status === 'free') {
            header('Location: dashboard.php?msg=created_free');
        } else {
            header('Location: payment-selection.php?listing_id=' . $listing_id);
        }
        exit;

    } catch (Throwable $e) {
        $msg = 'Error: ' . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Publicar Servicio — <?php echo APP_NAME; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    :root {
      --primary: #1a6b3a;
      --primary-dark: #104d28;
      --primary-light: #e8f5e9;
      --white: #fff;
      --dark: #1a1a1a;
      --gray-700: #4a5568;
      --gray-600: #718096;
      --gray-300: #cbd5e0;
      --gray-100: #f7fafc;
      --bg: #f0f7f2;
      --danger: #e74c3c;
      --success: #27ae60;
      --radius: 12px;
      --shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--dark); line-height: 1.6; }

    .header { background: var(--white); border-bottom: 2px solid var(--gray-300); padding: 1.25rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow); }
    .header h1 { font-size: 1.375rem; color: var(--primary); display: flex; align-items: center; gap: 0.75rem; }
    .header-actions { display: flex; gap: 1rem; align-items: center; }

    .btn { padding: 0.7rem 1.4rem; background: var(--primary); color: var(--white); border: none; border-radius: var(--radius); font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.9375rem; transition: all 0.2s; font-family: 'Inter', sans-serif; }
    .btn:hover { background: var(--primary-dark); transform: translateY(-1px); }
    .btn-secondary { background: var(--gray-100); color: var(--dark); border: 1px solid var(--gray-300); }
    .btn-secondary:hover { background: var(--gray-300); transform: none; }
    .btn-lg { padding: 1rem 2rem; font-size: 1.0625rem; }

    .container { max-width: 920px; margin: 2rem auto; padding: 0 1.5rem; }

    .alert { padding: 1rem 1.5rem; border-radius: var(--radius); margin-bottom: 1.5rem; border: 1px solid; }
    .alert.error   { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    .alert.success { background: #d4edda; color: #155724; border-color: #c3e6cb; }

    .form-card { background: var(--white); border-radius: var(--radius); padding: 2.5rem; box-shadow: var(--shadow); margin-bottom: 1.5rem; }
    .section-title { font-size: 1.125rem; font-weight: 700; color: var(--primary); margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 2px solid var(--primary-light); display: flex; align-items: center; gap: 0.65rem; }

    .form-group { margin-bottom: 1.5rem; }
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem; }
    label { display: block; margin-bottom: 0.4rem; color: var(--gray-700); font-weight: 600; font-size: 0.9375rem; }
    label .req { color: var(--danger); margin-left: 0.2rem; }
    input[type=text], input[type=email], input[type=tel], input[type=url], input[type=number], select, textarea {
      width: 100%; padding: 0.875rem 1rem; border: 2px solid var(--gray-300); border-radius: var(--radius); font-size: 1rem; font-family: 'Inter', sans-serif; transition: border-color 0.2s; background: var(--white);
    }
    input:focus, select:focus, textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,58,0.1); }
    textarea { min-height: 110px; resize: vertical; }

    /* Radio group */
    .radio-group { display: flex; gap: 1rem; flex-wrap: wrap; }
    .radio-card { flex: 1; min-width: 120px; }
    .radio-card input[type=radio] { display: none; }
    .radio-card label {
      display: block; padding: 0.875rem 1rem; border: 2px solid var(--gray-300); border-radius: var(--radius);
      text-align: center; cursor: pointer; transition: all 0.2s; font-weight: 600; margin: 0;
      background: var(--white);
    }
    .radio-card input[type=radio]:checked + label { border-color: var(--primary); background: var(--primary-light); color: var(--primary); }
    .radio-card label:hover { border-color: var(--primary); }

    /* Skills */
    .skills-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.6rem; }
    .skill-item { display: flex; align-items: center; gap: 0.5rem; }
    .skill-item input[type=checkbox] { width: auto; accent-color: var(--primary); }
    .skill-item label { font-weight: 400; color: var(--dark); margin: 0; }

    /* Price row */
    .price-row { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 1rem; }
    @media (max-width: 640px) { .price-row { grid-template-columns: 1fr 1fr; } }

    /* Plans */
    .plan-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
    .plan-card { border: 2px solid var(--gray-300); border-radius: var(--radius); padding: 1.5rem; cursor: pointer; transition: all 0.2s; position: relative; text-align: center; }
    .plan-card:hover { border-color: var(--primary); }
    .plan-card.selected { border-color: var(--primary); background: var(--primary-light); }
    .plan-card input[type=radio] { position: absolute; opacity: 0; }
    .plan-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.78rem; font-weight: 700; margin-bottom: 0.75rem; }
    .plan-badge.free { background: #d4edda; color: #155724; }
    .plan-badge.paid { background: #fff3cd; color: #856404; }
    .plan-price { font-size: 1.75rem; font-weight: 700; color: var(--primary); }
    .plan-price small { font-size: 1rem; font-weight: 400; color: var(--gray-600); }
    .plan-name { font-weight: 600; margin-top: 0.5rem; }
    .plan-desc { font-size: 0.875rem; color: var(--gray-600); margin-top: 0.25rem; }
    .plan-card.selected .plan-price { color: var(--primary-dark); }

    /* Image upload */
    .image-upload-area { border: 2px dashed var(--gray-300); border-radius: var(--radius); padding: 2rem; text-align: center; background: var(--gray-100); transition: border-color 0.2s; cursor: pointer; }
    .image-upload-area:hover { border-color: var(--primary); }
    .image-upload-area.uploading { border-color: var(--primary); background: var(--primary-light); }
    .image-upload-area i { font-size: 2.5rem; color: var(--gray-300); margin-bottom: 1rem; display: block; }
    .image-previews { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem; }
    .preview-item { position: relative; width: 100px; height: 100px; }
    .preview-item img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
    .preview-remove { position: absolute; top: -6px; right: -6px; background: var(--danger); color: var(--white); border: none; border-radius: 50%; width: 22px; height: 22px; font-size: 0.75rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
    .upload-progress { font-size: 0.875rem; color: var(--primary); margin-top: 0.5rem; }

    /* Submit */
    .submit-bar { background: var(--white); border-top: 1px solid var(--gray-300); padding: 1.5rem 2rem; display: flex; justify-content: flex-end; gap: 1rem; position: sticky; bottom: 0; box-shadow: 0 -4px 12px rgba(0,0,0,0.06); }

    @media (max-width: 640px) {
      .header { flex-direction: column; gap: 1rem; align-items: flex-start; }
      .form-card { padding: 1.5rem; }
      .submit-bar { flex-direction: column; }
    }
  </style>
</head>
<body>

<div class="header">
  <h1><i class="fas fa-plus-circle"></i> Publicar Servicio</h1>
  <div class="header-actions">
    <span><?php echo htmlspecialchars($agent_name); ?></span>
    <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
  </div>
</div>

<div class="container">
  <?php if ($msg): ?>
    <div class="alert error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($msg); ?></div>
  <?php endif; ?>

  <form method="post" id="mainForm">
    <input type="hidden" name="uploaded_image_urls" id="uploadedImageUrls" value="[]">

    <!-- INFORMACIÓN BÁSICA -->
    <div class="form-card">
      <div class="section-title"><i class="fas fa-info-circle"></i> Información del Servicio</div>

      <div class="form-group">
        <label>Categoría <span class="req">*</span></label>
        <select name="category_id" required>
          <option value="">-- Seleccioná la categoría --</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>">
              <?php echo htmlspecialchars(preg_replace('/^SERV:\s*/', '', $cat['name'])); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Título del servicio <span class="req">*</span></label>
        <input type="text" name="title" required maxlength="120" placeholder="Ej: Abogado familiar, Plomero certificado, Clases de inglés...">
      </div>

      <div class="form-group">
        <label>Descripción detallada <span class="req">*</span></label>
        <textarea name="description" required placeholder="Describí en detalle qué ofrecés, tu experiencia, en qué te especializás, por qué elegirte..."></textarea>
      </div>

      <div class="form-group">
        <label>Modalidad del servicio</label>
        <div class="radio-group">
          <div class="radio-card">
            <input type="radio" name="service_type" value="presencial" id="st_presencial" checked>
            <label for="st_presencial"><i class="fas fa-map-marker-alt"></i> Presencial</label>
          </div>
          <div class="radio-card">
            <input type="radio" name="service_type" value="virtual" id="st_virtual">
            <label for="st_virtual"><i class="fas fa-laptop"></i> Virtual / En línea</label>
          </div>
          <div class="radio-card">
            <input type="radio" name="service_type" value="ambos" id="st_ambos">
            <label for="st_ambos"><i class="fas fa-globe"></i> Presencial y Virtual</label>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label>Años de experiencia</label>
        <input type="number" name="experience_years" min="0" max="60" value="0" style="max-width: 200px;">
      </div>
    </div>

    <!-- PRECIO -->
    <div class="form-card">
      <div class="section-title"><i class="fas fa-dollar-sign"></i> Precio y Tarifa</div>
      <div class="price-row">
        <div>
          <label>Precio desde <span class="req">*</span></label>
          <input type="number" name="price_from" min="0" step="100" value="0">
        </div>
        <div>
          <label>Precio hasta (opcional)</label>
          <input type="number" name="price_to" min="0" step="100" value="0">
        </div>
        <div>
          <label>Tipo de tarifa</label>
          <select name="price_type">
            <option value="hora">Por hora</option>
            <option value="proyecto">Por proyecto</option>
            <option value="mensual">Mensual</option>
            <option value="consulta">Por consulta</option>
            <option value="negociable">Negociable</option>
          </select>
        </div>
        <div>
          <label>Moneda</label>
          <select name="currency">
            <option value="CRC">Colones (₡)</option>
            <option value="USD">Dólares ($)</option>
          </select>
        </div>
      </div>
      <p style="font-size: 0.875rem; color: var(--gray-600);"><i class="fas fa-info-circle"></i> Si el precio es negociable o varía, podés dejar 0 y aclararlo en la descripción.</p>
    </div>

    <!-- UBICACIÓN -->
    <div class="form-card">
      <div class="section-title"><i class="fas fa-map-marker-alt"></i> Ubicación y Cobertura</div>
      <div class="form-row">
        <div>
          <label>Provincia</label>
          <select name="province">
            <option value="">-- Seleccioná --</option>
            <?php foreach ($provinces as $prov): ?>
              <option value="<?php echo $prov; ?>"><?php echo $prov; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Cantón</label>
          <input type="text" name="canton" placeholder="Ej: San José, Alajuela...">
        </div>
        <div>
          <label>Distrito</label>
          <input type="text" name="district" placeholder="Ej: Escazú, Curridabat...">
        </div>
      </div>
      <div class="form-group">
        <label>Descripción del área de cobertura</label>
        <input type="text" name="location_description" placeholder="Ej: Cubro todo el Valle Central, o servicios online a toda Costa Rica">
      </div>
    </div>

    <!-- HABILIDADES -->
    <div class="form-card">
      <div class="section-title"><i class="fas fa-tools"></i> Habilidades y Herramientas</div>
      <p style="font-size: 0.9rem; color: var(--gray-600); margin-bottom: 1rem;">Seleccioná las que apliquen:</p>
      <div class="skills-grid">
        <?php foreach ($common_skills as $skill): ?>
          <div class="skill-item">
            <input type="checkbox" name="skills[]" value="<?php echo htmlspecialchars($skill); ?>" id="skill_<?php echo md5($skill); ?>">
            <label for="skill_<?php echo md5($skill); ?>"><?php echo htmlspecialchars($skill); ?></label>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="form-group" style="margin-top: 1.25rem;">
        <label>Otras habilidades (separadas por coma)</label>
        <input type="text" name="custom_skills" placeholder="Ej: AutoCAD, Python, Corte y confección...">
      </div>
    </div>

    <!-- DISPONIBILIDAD -->
    <div class="form-card">
      <div class="section-title"><i class="fas fa-calendar-alt"></i> Disponibilidad</div>
      <div class="form-group">
        <label>Horario y días disponibles</label>
        <textarea name="availability" placeholder="Ej: Lunes a viernes de 8am a 6pm. Sábados de 8am a 12pm. Urgencias disponibles 24/7."></textarea>
      </div>
    </div>

    <!-- IMÁGENES -->
    <div class="form-card">
      <div class="section-title"><i class="fas fa-images"></i> Imágenes del Servicio</div>
      <p style="font-size: 0.9rem; color: var(--gray-600); margin-bottom: 1rem;">Subí fotos de tu trabajo, certificados, portafolio o tu local (máx. 6 imágenes, 5MB cada una).</p>

      <div class="image-upload-area" id="uploadArea" onclick="document.getElementById('imageFileInput').click()">
        <i class="fas fa-cloud-upload-alt"></i>
        <p><strong>Hacé clic o arrastrá imágenes aquí</strong></p>
        <p style="font-size: 0.875rem; color: var(--gray-600);">JPG, PNG o WEBP · Máx. 5MB por imagen</p>
        <div class="upload-progress" id="uploadProgress"></div>
      </div>
      <input type="file" id="imageFileInput" accept="image/*" multiple style="display:none" onchange="handleFiles(this.files)">
      <div class="image-previews" id="imagePreviews"></div>
    </div>

    <!-- CONTACTO -->
    <div class="form-card">
      <div class="section-title"><i class="fas fa-address-card"></i> Información de Contacto</div>
      <div class="form-row">
        <div>
          <label>Nombre de contacto <span class="req">*</span></label>
          <input type="text" name="contact_name" required>
        </div>
        <div>
          <label>Teléfono <span class="req">*</span></label>
          <input type="tel" name="contact_phone" required placeholder="Ej: 8888-8888">
        </div>
      </div>
      <div class="form-row">
        <div>
          <label>Correo electrónico</label>
          <input type="email" name="contact_email">
        </div>
        <div>
          <label>WhatsApp</label>
          <input type="tel" name="contact_whatsapp" placeholder="Ej: 50688888888">
        </div>
      </div>
      <div class="form-group">
        <label>Sitio web o redes sociales (opcional)</label>
        <input type="url" name="website" placeholder="https://...">
      </div>
    </div>

    <!-- PLAN DE PUBLICACIÓN -->
    <div class="form-card">
      <div class="section-title"><i class="fas fa-rocket"></i> Plan de Publicación</div>
      <div class="plan-cards">
        <?php foreach ($pricing_plans as $plan): ?>
          <?php $isFree = ((float)$plan['price_usd'] == 0 && (float)$plan['price_crc'] == 0); ?>
          <label class="plan-card <?php echo $isFree ? 'selected' : ''; ?>" id="planCard_<?php echo $plan['id']; ?>">
            <input type="radio" name="pricing_plan_id" value="<?php echo $plan['id']; ?>"
                   <?php echo $isFree ? 'checked' : ''; ?>
                   onchange="selectPlan(<?php echo $plan['id']; ?>)">
            <span class="plan-badge <?php echo $isFree ? 'free' : 'paid'; ?>">
              <?php echo $isFree ? 'GRATIS' : 'DESTACADO'; ?>
            </span>
            <div class="plan-price">
              <?php if ($isFree): ?>
                $0
              <?php else: ?>
                $<?php echo number_format((float)$plan['price_usd'], 0); ?>
                <small>USD</small>
              <?php endif; ?>
            </div>
            <div class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></div>
            <div class="plan-desc"><?php echo htmlspecialchars($plan['description'] ?? ''); ?></div>
            <?php if (!$isFree): ?>
              <div style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--gray-600);">
                ₡<?php echo number_format((float)$plan['price_crc'], 0, '.', ','); ?> CRC
              </div>
            <?php endif; ?>
          </label>
        <?php endforeach; ?>
      </div>
      <p style="margin-top: 1rem; font-size: 0.875rem; color: var(--gray-600);">
        <i class="fas fa-info-circle"></i>
        Los planes de pago se activan al confirmar el pago via SINPE Móvil o PayPal. Podés empezar gratis por 7 días sin ningún compromiso.
      </p>
    </div>

    <div class="submit-bar">
      <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
      <button type="submit" class="btn btn-lg"><i class="fas fa-paper-plane"></i> Publicar Servicio</button>
    </div>
  </form>
</div>

<script>
// ---- Image upload ----
var uploadedUrls = [];

function updateHiddenField() {
  document.getElementById('uploadedImageUrls').value = JSON.stringify(uploadedUrls);
}

function handleFiles(files) {
  var arr = Array.from(files);
  if (uploadedUrls.length + arr.length > 6) {
    alert('Podés subir máximo 6 imágenes.');
    return;
  }
  arr.forEach(function(file) {
    if (file.size > 5 * 1024 * 1024) {
      alert('La imagen ' + file.name + ' supera 5MB.');
      return;
    }
    uploadFile(file);
  });
}

function uploadFile(file) {
  var progress = document.getElementById('uploadProgress');
  var area     = document.getElementById('uploadArea');
  progress.textContent = 'Subiendo ' + file.name + '...';
  area.classList.add('uploading');

  var fd = new FormData();
  fd.append('image', file);

  fetch('/services/upload-image.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      area.classList.remove('uploading');
      if (data.ok && data.url) {
        uploadedUrls.push(data.url);
        updateHiddenField();
        addPreview(data.url);
        progress.textContent = '';
      } else {
        progress.textContent = 'Error: ' + (data.error || 'No se pudo subir la imagen');
        progress.style.color = '#e74c3c';
      }
    })
    .catch(function() {
      area.classList.remove('uploading');
      progress.textContent = 'Error al subir la imagen.';
      progress.style.color = '#e74c3c';
    });
}

function addPreview(url) {
  var container = document.getElementById('imagePreviews');
  var item = document.createElement('div');
  item.className = 'preview-item';
  item.dataset.url = url;
  item.innerHTML = '<img src="' + url + '" alt="Imagen"><button type="button" class="preview-remove" onclick="removePreview(this)"><i class="fas fa-times"></i></button>';
  container.appendChild(item);
}

function removePreview(btn) {
  var item = btn.closest('.preview-item');
  var url  = item.dataset.url;
  uploadedUrls = uploadedUrls.filter(function(u) { return u !== url; });
  updateHiddenField();
  item.remove();
}

// Drag and drop
var uploadArea = document.getElementById('uploadArea');
uploadArea.addEventListener('dragover', function(e) { e.preventDefault(); uploadArea.style.borderColor = '#1a6b3a'; });
uploadArea.addEventListener('dragleave', function()  { uploadArea.style.borderColor = ''; });
uploadArea.addEventListener('drop', function(e) {
  e.preventDefault();
  uploadArea.style.borderColor = '';
  handleFiles(e.dataTransfer.files);
});

// ---- Plan selection ----
function selectPlan(id) {
  document.querySelectorAll('.plan-card').forEach(function(c) { c.classList.remove('selected'); });
  var card = document.getElementById('planCard_' + id);
  if (card) card.classList.add('selected');
}
</script>
</body>
</html>
