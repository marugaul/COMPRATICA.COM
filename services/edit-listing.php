<?php
// services/edit-listing.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['agent_id']) || (int)$_SESSION['agent_id'] <= 0) {
    header('Location: login.php');
    exit;
}

$pdo        = db();
$agent_id   = (int)$_SESSION['agent_id'];
$agent_name = $_SESSION['agent_name'] ?? 'Usuario';
$listing_id = (int)($_GET['id'] ?? 0);
$msg = '';

if ($listing_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

// Cargar el servicio (debe pertenecer al agente)
$stmt = $pdo->prepare("SELECT * FROM service_listings WHERE id = ? AND agent_id = ? LIMIT 1");
$stmt->execute([$listing_id, $agent_id]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$listing) {
    header('Location: dashboard.php');
    exit;
}

// Cargar categorías SERV:
$categories = [];
try {
    $catStmt = $pdo->query("SELECT id, name FROM categories WHERE active=1 AND name LIKE 'SERV:%' ORDER BY display_order ASC");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Planes de precios
$pricing_plans = [];
try {
    $planStmt = $pdo->query("SELECT * FROM service_pricing WHERE is_active=1 ORDER BY display_order ASC");
    $pricing_plans = $planStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$provinces = ['San José','Alajuela','Cartago','Heredia','Guanacaste','Puntarenas','Limón'];
$common_skills = [
    'Microsoft Office','Google Workspace','Zoom/Teams','Inglés','Español',
    'Facturación electrónica','Contabilidad','Redes sociales','Diseño gráfico',
    'Fotografía','Edición de video','Mantenimiento eléctrico','Plomería',
    'Pintura','Soldadura','Carpintería','Fontanería','Jardinería',
    'Licencia de conducir B1','Vehículo propio','Computadora',
];

$existing_images = json_decode($listing['images'] ?? '[]', true) ?: [];
$existing_skills = json_decode($listing['skills'] ?? '[]', true) ?: [];

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
        $pricing_plan_id   = (int)($_POST['pricing_plan_id'] ?? (int)$listing['pricing_plan_id']);

        // Habilidades
        $selected_skills = $_POST['skills'] ?? [];
        $custom_skills   = array_filter(array_map('trim', explode(',', $_POST['custom_skills'] ?? '')));
        $all_skills      = array_values(array_unique(array_merge($selected_skills, $custom_skills)));
        $skills_json     = json_encode($all_skills);

        // Imágenes: combinar existentes + nuevas subidas
        $keep_images = $_POST['keep_images'] ?? [];
        $keep_images = array_values(array_filter($keep_images));
        $uploaded_images = $_POST['uploaded_image_urls'] ?? '';
        $new_images = [];
        if (!empty($uploaded_images)) {
            $decoded = json_decode($uploaded_images, true);
            if (is_array($decoded)) $new_images = $decoded;
        }
        $all_images  = array_values(array_unique(array_merge($keep_images, $new_images)));
        $images_json = json_encode($all_images);

        if ($title === '' || $description === '' || $category_id <= 0) {
            throw new RuntimeException('Título, descripción y categoría son requeridos.');
        }
        if ($contact_phone === '' && $contact_email === '' && $contact_whatsapp === '') {
            throw new RuntimeException('Debés proporcionar al menos un método de contacto.');
        }

        if (!in_array($service_type, ['presencial', 'virtual', 'ambos'])) $service_type = 'presencial';
        if (!in_array($price_type, ['hora', 'proyecto', 'mensual', 'consulta', 'negociable'])) $price_type = 'hora';
        if (!in_array($currency, ['CRC', 'USD'])) $currency = 'CRC';

        // Si cambia el plan, actualizar fechas y estado de pago
        $newPlanId = $pricing_plan_id;
        $updatePlan = ($newPlanId !== (int)$listing['pricing_plan_id']);
        $planStmt2 = $pdo->prepare("SELECT * FROM service_pricing WHERE id = ? LIMIT 1");
        $planStmt2->execute([$newPlanId]);
        $newPlan = $planStmt2->fetch(PDO::FETCH_ASSOC);

        $start_date     = $listing['start_date'];
        $end_date       = $listing['end_date'];
        $payment_status = $listing['payment_status'];

        if ($updatePlan && $newPlan) {
            $start_date     = date('Y-m-d H:i:s');
            $end_date       = date('Y-m-d H:i:s', strtotime("+{$newPlan['duration_days']} days"));
            $payment_status = ((float)$newPlan['price_usd'] == 0 && (float)$newPlan['price_crc'] == 0) ? 'free' : 'pending';
        }

        $updStmt = $pdo->prepare("
            UPDATE service_listings SET
                category_id = ?, title = ?, description = ?,
                service_type = ?, price_from = ?, price_to = ?, price_type = ?, currency = ?,
                province = ?, canton = ?, district = ?, location_description = ?,
                experience_years = ?, skills = ?, availability = ?, images = ?,
                contact_name = ?, contact_phone = ?, contact_email = ?, contact_whatsapp = ?, website = ?,
                pricing_plan_id = ?, start_date = ?, end_date = ?, payment_status = ?,
                updated_at = datetime('now')
            WHERE id = ? AND agent_id = ?
        ");
        $updStmt->execute([
            $category_id, $title, $description,
            $service_type, $price_from, $price_to, $price_type, $currency,
            $province, $canton, $district, $location_description,
            $experience_years, $skills_json, $availability, $images_json,
            $contact_name, $contact_phone, $contact_email, $contact_whatsapp, $website,
            $newPlanId, $start_date, $end_date, $payment_status,
            $listing_id, $agent_id
        ]);

        header('Location: dashboard.php?msg=updated');
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
  <title>Editar Servicio — <?php echo APP_NAME; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    :root {
      --primary: #1a6b3a; --primary-dark: #104d28; --primary-light: #e8f5e9;
      --white: #fff; --dark: #1a1a1a; --gray-700: #4a5568; --gray-600: #718096;
      --gray-300: #cbd5e0; --gray-100: #f7fafc; --bg: #f0f7f2;
      --danger: #e74c3c; --radius: 12px; --shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--dark); line-height: 1.6; }
    .header { background: var(--white); border-bottom: 2px solid var(--gray-300); padding: 1.25rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow); }
    .header h1 { font-size: 1.375rem; color: var(--primary); display: flex; align-items: center; gap: 0.75rem; }
    .header-actions { display: flex; gap: 1rem; }
    .btn { padding: 0.7rem 1.4rem; background: var(--primary); color: var(--white); border: none; border-radius: var(--radius); font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.9375rem; transition: all 0.2s; font-family: 'Inter', sans-serif; }
    .btn:hover { background: var(--primary-dark); }
    .btn-secondary { background: var(--gray-100); color: var(--dark); border: 1px solid var(--gray-300); }
    .btn-secondary:hover { background: var(--gray-300); }
    .btn-lg { padding: 1rem 2rem; font-size: 1.0625rem; }
    .container { max-width: 920px; margin: 2rem auto; padding: 0 1.5rem; }
    .alert { padding: 1rem 1.5rem; border-radius: var(--radius); margin-bottom: 1.5rem; border: 1px solid; }
    .alert.error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
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
    .radio-group { display: flex; gap: 1rem; flex-wrap: wrap; }
    .radio-card { flex: 1; min-width: 120px; }
    .radio-card input[type=radio] { display: none; }
    .radio-card label { display: block; padding: 0.875rem 1rem; border: 2px solid var(--gray-300); border-radius: var(--radius); text-align: center; cursor: pointer; transition: all 0.2s; font-weight: 600; margin: 0; background: var(--white); }
    .radio-card input[type=radio]:checked + label { border-color: var(--primary); background: var(--primary-light); color: var(--primary); }
    .radio-card label:hover { border-color: var(--primary); }
    .skills-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.6rem; }
    .skill-item { display: flex; align-items: center; gap: 0.5rem; }
    .skill-item input[type=checkbox] { width: auto; accent-color: var(--primary); }
    .skill-item label { font-weight: 400; color: var(--dark); margin: 0; }
    .price-row { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 1rem; }
    @media (max-width: 640px) { .price-row { grid-template-columns: 1fr 1fr; } }
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
    /* Images */
    .existing-images { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem; }
    .existing-img { position: relative; width: 100px; height: 100px; }
    .existing-img img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
    .existing-img .remove-btn { position: absolute; top: -6px; right: -6px; background: var(--danger); color: var(--white); border: none; border-radius: 50%; width: 22px; height: 22px; font-size: 0.75rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
    .image-upload-area { border: 2px dashed var(--gray-300); border-radius: var(--radius); padding: 1.5rem; text-align: center; background: var(--gray-100); cursor: pointer; margin-top: 1rem; }
    .image-upload-area:hover { border-color: var(--primary); }
    .image-upload-area i { font-size: 2rem; color: var(--gray-300); display: block; margin-bottom: 0.5rem; }
    .image-previews { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem; }
    .preview-item { position: relative; width: 100px; height: 100px; }
    .preview-item img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
    .preview-remove { position: absolute; top: -6px; right: -6px; background: var(--danger); color: var(--white); border: none; border-radius: 50%; width: 22px; height: 22px; font-size: 0.75rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }
    .upload-progress { font-size: 0.875rem; color: var(--primary); margin-top: 0.5rem; }
    .submit-bar { background: var(--white); border-top: 1px solid var(--gray-300); padding: 1.5rem 2rem; display: flex; justify-content: flex-end; gap: 1rem; position: sticky; bottom: 0; box-shadow: 0 -4px 12px rgba(0,0,0,0.06); }
    @media (max-width: 640px) { .header { flex-direction: column; gap: 1rem; } .form-card { padding: 1.5rem; } .submit-bar { flex-direction: column; } }
  </style>
</head>
<body>

<div class="header">
  <h1><i class="fas fa-edit"></i> Editar Servicio</h1>
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
            <option value="<?php echo $cat['id']; ?>" <?php echo (int)$cat['id'] === (int)$listing['category_id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars(preg_replace('/^SERV:\s*/', '', $cat['name'])); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Título del servicio <span class="req">*</span></label>
        <input type="text" name="title" required maxlength="120" value="<?php echo htmlspecialchars($listing['title']); ?>">
      </div>

      <div class="form-group">
        <label>Descripción detallada <span class="req">*</span></label>
        <textarea name="description" required><?php echo htmlspecialchars($listing['description']); ?></textarea>
      </div>

      <div class="form-group">
        <label>Modalidad del servicio</label>
        <div class="radio-group">
          <?php foreach (['presencial'=>'Presencial','virtual'=>'Virtual / En línea','ambos'=>'Presencial y Virtual'] as $val => $label): ?>
            <div class="radio-card">
              <input type="radio" name="service_type" value="<?php echo $val; ?>" id="st_<?php echo $val; ?>"
                     <?php echo $listing['service_type'] === $val ? 'checked' : ''; ?>>
              <label for="st_<?php echo $val; ?>"><?php echo $label; ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="form-group">
        <label>Años de experiencia</label>
        <input type="number" name="experience_years" min="0" max="60" style="max-width: 200px;"
               value="<?php echo (int)$listing['experience_years']; ?>">
      </div>
    </div>

    <!-- PRECIO -->
    <div class="form-card">
      <div class="section-title"><i class="fas fa-dollar-sign"></i> Precio y Tarifa</div>
      <div class="price-row">
        <div>
          <label>Precio desde</label>
          <input type="number" name="price_from" min="0" step="100" value="<?php echo (float)$listing['price_from']; ?>">
        </div>
        <div>
          <label>Precio hasta</label>
          <input type="number" name="price_to" min="0" step="100" value="<?php echo (float)$listing['price_to']; ?>">
        </div>
        <div>
          <label>Tipo de tarifa</label>
          <select name="price_type">
            <?php foreach (['hora'=>'Por hora','proyecto'=>'Por proyecto','mensual'=>'Mensual','consulta'=>'Por consulta','negociable'=>'Negociable'] as $val => $label): ?>
              <option value="<?php echo $val; ?>" <?php echo $listing['price_type'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Moneda</label>
          <select name="currency">
            <option value="CRC" <?php echo $listing['currency'] === 'CRC' ? 'selected' : ''; ?>>Colones (₡)</option>
            <option value="USD" <?php echo $listing['currency'] === 'USD' ? 'selected' : ''; ?>>Dólares ($)</option>
          </select>
        </div>
      </div>
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
              <option value="<?php echo $prov; ?>" <?php echo $listing['province'] === $prov ? 'selected' : ''; ?>><?php echo $prov; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Cantón</label>
          <input type="text" name="canton" value="<?php echo htmlspecialchars($listing['canton'] ?? ''); ?>">
        </div>
        <div>
          <label>Distrito</label>
          <input type="text" name="district" value="<?php echo htmlspecialchars($listing['district'] ?? ''); ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Área de cobertura</label>
        <input type="text" name="location_description" value="<?php echo htmlspecialchars($listing['location_description'] ?? ''); ?>">
      </div>
    </div>

    <!-- HABILIDADES -->
    <div class="form-card">
      <div class="section-title"><i class="fas fa-tools"></i> Habilidades y Herramientas</div>
      <div class="skills-grid">
        <?php foreach ($common_skills as $skill): ?>
          <div class="skill-item">
            <input type="checkbox" name="skills[]" value="<?php echo htmlspecialchars($skill); ?>"
                   id="skill_<?php echo md5($skill); ?>"
                   <?php echo in_array($skill, $existing_skills) ? 'checked' : ''; ?>>
            <label for="skill_<?php echo md5($skill); ?>"><?php echo htmlspecialchars($skill); ?></label>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="form-group" style="margin-top: 1.25rem;">
        <label>Otras habilidades (separadas por coma)</label>
        <?php
          $extra = array_diff($existing_skills, $common_skills);
          $extraStr = implode(', ', $extra);
        ?>
        <input type="text" name="custom_skills" value="<?php echo htmlspecialchars($extraStr); ?>">
      </div>
    </div>

    <!-- DISPONIBILIDAD -->
    <div class="form-card">
      <div class="section-title"><i class="fas fa-calendar-alt"></i> Disponibilidad</div>
      <div class="form-group">
        <label>Horario y días disponibles</label>
        <textarea name="availability"><?php echo htmlspecialchars($listing['availability'] ?? ''); ?></textarea>
      </div>
    </div>

    <!-- IMÁGENES -->
    <div class="form-card">
      <div class="section-title"><i class="fas fa-images"></i> Imágenes del Servicio</div>

      <?php if (!empty($existing_images)): ?>
        <p style="font-size: 0.9rem; color: var(--gray-600); margin-bottom: 0.75rem;">Imágenes actuales (hacé clic en la X para eliminar):</p>
        <div class="existing-images" id="existingImages">
          <?php foreach ($existing_images as $imgUrl): ?>
            <div class="existing-img" id="existing_<?php echo md5($imgUrl); ?>">
              <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="Imagen">
              <input type="hidden" name="keep_images[]" value="<?php echo htmlspecialchars($imgUrl); ?>">
              <button type="button" class="remove-btn" onclick="removeExisting(this, '<?php echo htmlspecialchars($imgUrl, ENT_QUOTES); ?>')">
                <i class="fas fa-times"></i>
              </button>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="image-upload-area" id="uploadArea" onclick="document.getElementById('imageFileInput').click()">
        <i class="fas fa-cloud-upload-alt"></i>
        <p><strong>Agregar más imágenes</strong></p>
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
          <input type="text" name="contact_name" required value="<?php echo htmlspecialchars($listing['contact_name'] ?? ''); ?>">
        </div>
        <div>
          <label>Teléfono <span class="req">*</span></label>
          <input type="tel" name="contact_phone" required value="<?php echo htmlspecialchars($listing['contact_phone'] ?? ''); ?>">
        </div>
      </div>
      <div class="form-row">
        <div>
          <label>Correo electrónico</label>
          <input type="email" name="contact_email" value="<?php echo htmlspecialchars($listing['contact_email'] ?? ''); ?>">
        </div>
        <div>
          <label>WhatsApp</label>
          <input type="tel" name="contact_whatsapp" value="<?php echo htmlspecialchars($listing['contact_whatsapp'] ?? ''); ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Sitio web o redes sociales</label>
        <input type="url" name="website" value="<?php echo htmlspecialchars($listing['website'] ?? ''); ?>">
      </div>
    </div>

    <!-- PLAN -->
    <div class="form-card">
      <div class="section-title"><i class="fas fa-rocket"></i> Plan de Publicación</div>
      <div class="plan-cards">
        <?php foreach ($pricing_plans as $plan): ?>
          <?php $isFree = ((float)$plan['price_usd'] == 0 && (float)$plan['price_crc'] == 0);
                $isCurrent = (int)$plan['id'] === (int)$listing['pricing_plan_id']; ?>
          <label class="plan-card <?php echo $isCurrent ? 'selected' : ''; ?>" id="planCard_<?php echo $plan['id']; ?>">
            <input type="radio" name="pricing_plan_id" value="<?php echo $plan['id']; ?>"
                   <?php echo $isCurrent ? 'checked' : ''; ?>
                   onchange="selectPlan(<?php echo $plan['id']; ?>)">
            <span class="plan-badge <?php echo $isFree ? 'free' : 'paid'; ?>"><?php echo $isFree ? 'GRATIS' : 'DESTACADO'; ?></span>
            <div class="plan-price">
              <?php if ($isFree): ?>$0<?php else: ?>$<?php echo number_format((float)$plan['price_usd'], 0); ?><small> USD</small><?php endif; ?>
            </div>
            <div class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></div>
            <div class="plan-desc"><?php echo htmlspecialchars($plan['description'] ?? ''); ?></div>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="submit-bar">
      <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
      <button type="submit" class="btn btn-lg"><i class="fas fa-save"></i> Guardar Cambios</button>
    </div>
  </form>
</div>

<script>
var uploadedUrls = [];
function updateHiddenField() {
  document.getElementById('uploadedImageUrls').value = JSON.stringify(uploadedUrls);
}
function handleFiles(files) {
  Array.from(files).forEach(function(file) {
    if (file.size > 5 * 1024 * 1024) { alert('La imagen ' + file.name + ' supera 5MB.'); return; }
    uploadFile(file);
  });
}
function uploadFile(file) {
  var progress = document.getElementById('uploadProgress');
  progress.textContent = 'Subiendo ' + file.name + '...';
  var fd = new FormData();
  fd.append('image', file);
  fetch('/services/upload-image.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.ok && data.url) {
        uploadedUrls.push(data.url);
        updateHiddenField();
        addPreview(data.url);
        progress.textContent = '';
      } else {
        progress.textContent = 'Error: ' + (data.error || 'No se pudo subir');
        progress.style.color = '#e74c3c';
      }
    })
    .catch(function() { progress.textContent = 'Error al subir.'; progress.style.color = '#e74c3c'; });
}
function addPreview(url) {
  var container = document.getElementById('imagePreviews');
  var item = document.createElement('div');
  item.className = 'preview-item'; item.dataset.url = url;
  item.innerHTML = '<img src="' + url + '" alt=""><button type="button" class="preview-remove" onclick="removePreview(this)"><i class="fas fa-times"></i></button>';
  container.appendChild(item);
}
function removePreview(btn) {
  var item = btn.closest('.preview-item');
  uploadedUrls = uploadedUrls.filter(function(u) { return u !== item.dataset.url; });
  updateHiddenField();
  item.remove();
}
function removeExisting(btn, url) {
  var item = btn.closest('.existing-img');
  item.remove();
}
// Drag and drop
var ua = document.getElementById('uploadArea');
ua.addEventListener('dragover', function(e) { e.preventDefault(); ua.style.borderColor = '#1a6b3a'; });
ua.addEventListener('dragleave', function() { ua.style.borderColor = ''; });
ua.addEventListener('drop', function(e) { e.preventDefault(); ua.style.borderColor = ''; handleFiles(e.dataTransfer.files); });

function selectPlan(id) {
  document.querySelectorAll('.plan-card').forEach(function(c) { c.classList.remove('selected'); });
  var card = document.getElementById('planCard_' + id);
  if (card) card.classList.add('selected');
}
</script>
</body>
</html>
