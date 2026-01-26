<?php
// affiliate/edit_sale.php — Editar espacio existente
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';
aff_require_login();

$pdo = db();
$aff_id = (int)$_SESSION['aff_id'];
$msg = '';
$msgType = '';

// Helper de zona horaria
if (!function_exists('now_cr')) {
  function now_cr(): string {
    $tz = new DateTimeZone('America/Costa_Rica');
    return (new DateTime('now', $tz))->format('Y-m-d H:i:s');
  }
}

// Obtener sale_id
$sale_id = (int)($_GET['id'] ?? 0);

if ($sale_id <= 0) {
  header('Location: sales.php');
  exit;
}

// Verificar que el espacio pertenece al afiliado
$stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ? AND affiliate_id = ?");
$stmt->execute([$sale_id, $aff_id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
  header('Location: sales.php');
  exit;
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
  try {
    $title = trim($_POST['title'] ?? '');
    $start = trim($_POST['start_at'] ?? '');
    $end   = trim($_POST['end_at'] ?? '');

    if ($title === '' || $start === '' || $end === '') {
      throw new RuntimeException('Faltan datos obligatorios (título, inicio, fin).');
    }

    // Privacidad del espacio
    $isPrivate = !empty($_POST['is_private']) ? 1 : 0;
    $accessCode = null;

    if ($isPrivate) {
      $accessCode = trim($_POST['access_code'] ?? '');
      if (!preg_match('/^[0-9]{6}$/', $accessCode)) {
        throw new RuntimeException('El código de acceso debe ser exactamente 6 dígitos numéricos.');
      }
    }

    // Procesar nueva portada si se subió
    $img = $sale['cover_image']; // Mantener la actual por defecto

    if (!empty($_FILES['cover']['name']) && is_uploaded_file($_FILES['cover']['tmp_name'])) {
      @mkdir(__DIR__ . '/../uploads/affiliates', 0775, true);
      $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) $ext = 'jpg';
      $newImg = 'cover_' . uniqid() . '.' . $ext;

      if (move_uploaded_file($_FILES['cover']['tmp_name'], __DIR__ . '/../uploads/affiliates/' . $newImg)) {
        // Borrar imagen anterior si existe
        if ($sale['cover_image'] && file_exists(__DIR__ . '/../uploads/affiliates/' . $sale['cover_image'])) {
          @unlink(__DIR__ . '/../uploads/affiliates/' . $sale['cover_image']);
        }
        $img = $newImg;
      }
    }

    // UPDATE
    $sql = "UPDATE sales
            SET title = :title,
                cover_image = :cover,
                start_at = :start,
                end_at = :end,
                is_private = :private,
                access_code = :code,
                updated_at = :now
            WHERE id = :id AND affiliate_id = :aff";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':title'   => $title,
      ':cover'   => $img,
      ':start'   => $start,
      ':end'     => $end,
      ':private' => $isPrivate,
      ':code'    => $accessCode,
      ':now'     => now_cr(),
      ':id'      => $sale_id,
      ':aff'     => $aff_id
    ]);

    $msg = '✓ Espacio actualizado correctamente';
    $msgType = 'success';

    // Recargar datos actualizados
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ? AND affiliate_id = ?");
    $stmt->execute([$sale_id, $aff_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

  } catch (Throwable $e) {
    error_log("[affiliate/edit_sale.php] Update error: ".$e->getMessage());
    $msg = 'Error al actualizar: '.$e->getMessage();
    $msgType = 'error';
  }
}

// Convertir fechas al formato datetime-local (sin segundos)
$startFormatted = '';
$endFormatted = '';

if ($sale['start_at']) {
  try {
    $dt = new DateTime($sale['start_at']);
    $startFormatted = $dt->format('Y-m-d\TH:i');
  } catch (Exception $e) {
    $startFormatted = '';
  }
}

if ($sale['end_at']) {
  try {
    $dt = new DateTime($sale['end_at']);
    $endFormatted = $dt->format('Y-m-d\TH:i');
  } catch (Exception $e) {
    $endFormatted = '';
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Editar Espacio</title>
  <link rel="stylesheet" href="../assets/style.css?v=23">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* Variables de color corporativas */
    :root {
      --primary: #2c3e50;
      --primary-light: #34495e;
      --accent: #3498db;
      --accent-hover: #2980b9;
      --success: #27ae60;
      --warning: #f39c12;
      --danger: #e74c3c;
      --gray-50: #f9fafb;
      --gray-100: #f3f4f6;
      --gray-200: #e5e7eb;
      --gray-300: #d1d5db;
      --gray-600: #6b7280;
      --gray-800: #1f2937;
    }

    body {
      background: var(--gray-50);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* Header empresarial */
    .header {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      box-shadow: 0 2px 12px rgba(0,0,0,0.1);
      padding: 1.5rem 2rem;
    }

    .header .logo {
      font-size: 1.25rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    /* Cards mejorados */
    .card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
      border: 1px solid var(--gray-200);
      padding: 2rem;
      margin-bottom: 2rem;
      transition: box-shadow 0.3s ease;
    }

    .card h3 {
      color: var(--primary);
      font-size: 1.5rem;
      font-weight: 600;
      margin: 0 0 1.5rem 0;
      padding-bottom: 1rem;
      border-bottom: 2px solid var(--gray-100);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    /* Formulario mejorado */
    .form label {
      font-weight: 500;
      color: var(--gray-800);
      margin-bottom: 0.5rem;
      display: block;
    }

    .form .input {
      border: 2px solid var(--gray-200);
      border-radius: 8px;
      padding: 0.75rem 1rem;
      font-size: 1rem;
      transition: all 0.3s ease;
      width: 100%;
    }

    .form .input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
      outline: none;
    }

    /* Botones mejorados */
    .btn {
      padding: 0.625rem 1.25rem;
      border-radius: 6px;
      font-weight: 500;
      font-size: 0.875rem;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
      border: none;
      cursor: pointer;
    }

    .btn.primary {
      background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
      color: white;
      box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
    }

    .btn.primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
    }

    /* Privacy section */
    .privacy-section {
      background: var(--gray-50);
      border: 2px solid var(--gray-200);
      border-radius: 8px;
      padding: 1.5rem;
      margin: 1.5rem 0;
    }

    .privacy-section h4 {
      margin-top: 0;
      color: var(--primary);
      font-size: 1.1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    /* Alert mejorado */
    .alert {
      padding: 1rem 1.25rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      border-left: 4px solid;
    }

    .alert.success {
      background: rgba(39, 174, 96, 0.1);
      border-color: var(--success);
      color: #155724;
    }

    .alert.error {
      background: rgba(231, 76, 60, 0.1);
      border-color: var(--danger);
      color: #c0392b;
    }

    /* Preview de imagen */
    .preview-container {
      margin-top: 1rem;
      padding: 1rem;
      background: var(--gray-50);
      border-radius: 8px;
      border: 2px dashed var(--gray-300);
    }

    .preview-image {
      max-width: 100%;
      max-height: 300px;
      border-radius: 8px;
      display: block;
      margin: 0 auto;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    /* Grid para formulario */
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
    }

    @media (max-width: 768px) {
      .form-grid {
        grid-template-columns: 1fr;
      }
    }

    #access_code {
      transition: all 0.3s ease;
    }

    #generateCodeBtn {
      transition: all 0.3s ease;
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      color: white;
      border: none;
      border-radius: 6px;
      padding: 0.75rem 1.5rem;
      cursor: pointer;
      font-weight: 600;
      font-size: 0.9rem;
      white-space: nowrap;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    #generateCodeBtn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(245,87,108,0.4);
    }

    .button-group {
      display: flex;
      gap: 1rem;
      margin-top: 1.5rem;
      flex-wrap: wrap;
    }
  </style>
</head>
<body>
<header class="header" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); box-shadow: 0 2px 12px rgba(0,0,0,0.1); padding: 1rem 2rem;">
  <div class="logo" style="font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; color: white;">
    <i class="fas fa-user-tie"></i>
    Panel de Afiliado
  </div>
  <nav style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
    <a class="nav-btn" href="dashboard.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-th-large"></i>
      <span>Dashboard</span>
    </a>
    <a class="nav-btn" href="sales.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-store-alt"></i>
      <span>Espacios</span>
    </a>
    <a class="nav-btn" href="../index" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-store"></i>
      <span>Ver Tienda</span>
    </a>
  </nav>
</header>

<div class="container">
  <?php if ($msg): ?>
    <div class="alert <?= $msgType ?>">
      <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <h3><i class="fas fa-store"></i> Editar: <?= htmlspecialchars($sale['title']) ?></h3>

    <form class="form" method="post" enctype="multipart/form-data" id="editForm">
      <label>
        <i class="fas fa-tag"></i> Título del Espacio *
        <input class="input" name="title" value="<?= htmlspecialchars($sale['title']) ?>" placeholder="Ej: Venta de Garage 2026" required>
      </label>

      <label>
        <i class="fas fa-image"></i> Portada Actual
        <?php if ($sale['cover_image']): ?>
          <div class="preview-container">
            <img src="../uploads/affiliates/<?= htmlspecialchars($sale['cover_image']) ?>"
                 alt="Portada actual"
                 class="preview-image"
                 id="currentImage">
          </div>
        <?php else: ?>
          <div style="color: var(--gray-600); font-style: italic; margin-top: 0.5rem; padding: 2rem; text-align: center; background: var(--gray-50); border-radius: 8px;">
            <i class="fas fa-image" style="font-size: 2rem; color: var(--gray-300); display: block; margin-bottom: 0.5rem;"></i>
            Sin imagen de portada
          </div>
        <?php endif; ?>
      </label>

      <label>
        <i class="fas fa-upload"></i> Cambiar Portada (Opcional)
        <input class="input" type="file" name="cover" accept="image/*" id="newImage">
        <small style="color: var(--gray-600); display: block; margin-top: 0.5rem;">
          Si subes una nueva imagen, reemplazará la actual
        </small>
      </label>

      <div class="form-grid">
        <label>
          <i class="fas fa-calendar-alt"></i> Fecha y Hora de Inicio *
          <small style="color: var(--gray-600); display:block; margin-top:4px;">
            Incluye la hora exacta (ej: 8:00 AM)
          </small>
          <input class="input" type="datetime-local" name="start_at" id="start_at"
                 value="<?= htmlspecialchars($startFormatted) ?>" required>
        </label>

        <label>
          <i class="fas fa-calendar-check"></i> Fecha y Hora de Fin *
          <small style="color: var(--gray-600); display:block; margin-top:4px;">
            Incluye la hora exacta (ej: 6:00 PM)
          </small>
          <input class="input" type="datetime-local" name="end_at" id="end_at"
                 value="<?= htmlspecialchars($endFormatted) ?>" required>
        </label>
      </div>

      <div class="privacy-section">
        <h4><i class="fas fa-lock"></i> Configuración de Privacidad</h4>

        <label style="display: flex; align-items: center; cursor: pointer; margin-bottom: 1rem;">
          <input type="checkbox" name="is_private" id="is_private" value="1"
                 <?= !empty($sale['is_private']) ? 'checked' : '' ?>
                 style="width: auto; margin-right: 0.5rem;">
          <span><strong>Espacio privado</strong> - Requiere código de acceso para ver productos</span>
        </label>

        <div id="access_code_container" style="<?= !empty($sale['is_private']) ? '' : 'display: none;' ?>">
          <label>
            <i class="fas fa-key"></i> Código de Acceso (6 dígitos)
            <small style="color: var(--gray-600); display:block; margin-top:4px;">
              Los clientes necesitarán este código para acceder a los productos
            </small>
            <div style="display: flex; gap: 0.75rem; align-items: stretch;">
              <input class="input" type="text" name="access_code" id="access_code"
                     pattern="[0-9]{6}" maxlength="6" placeholder="Ej: 123456"
                     value="<?= htmlspecialchars($sale['access_code'] ?? '') ?>"
                     style="flex: 1; font-size: 1.2rem; letter-spacing: 0.3rem; font-family: 'Courier New', monospace; text-align: center;">
              <button type="button" id="generateCodeBtn">
                <i class="fas fa-sync-alt"></i> Generar Nuevo
              </button>
            </div>
            <small style="color: var(--gray-600); display:block; margin-top:4px;">
              Solo números, exactamente 6 dígitos
            </small>
          </label>
        </div>
      </div>

      <div class="button-group">
        <button class="btn primary" type="submit" name="update" value="1">
          <i class="fas fa-save"></i> Guardar Cambios
        </button>
        <a class="btn" href="sales.php"><i class="fas fa-times"></i> Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Control de espacio privado
  const isPrivateCheckbox = document.getElementById('is_private');
  const accessCodeContainer = document.getElementById('access_code_container');
  const accessCodeInput = document.getElementById('access_code');

  // Mostrar/ocultar campo de código según checkbox
  isPrivateCheckbox.addEventListener('change', function() {
    if (this.checked) {
      accessCodeContainer.style.display = 'block';
      accessCodeInput.required = true;
      // Generar código automático si está vacío
      if (!accessCodeInput.value) {
        accessCodeInput.value = Math.floor(100000 + Math.random() * 900000).toString();
      }
    } else {
      accessCodeContainer.style.display = 'none';
      accessCodeInput.required = false;
    }
  });

  // Validar que solo sean números
  accessCodeInput.addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
  });

  // 🔄 Botón para generar nuevo código
  const generateCodeBtn = document.getElementById('generateCodeBtn');
  if (generateCodeBtn) {
    generateCodeBtn.addEventListener('click', function() {
      // Generar código de 6 dígitos aleatorio
      const newCode = Math.floor(100000 + Math.random() * 900000).toString();
      accessCodeInput.value = newCode;

      // Efecto visual de confirmación
      const originalText = this.innerHTML;
      this.innerHTML = '<i class="fas fa-check"></i> ¡Generado!';
      this.style.background = 'linear-gradient(135deg, #56ab2f 0%, #a8e063 100%)';

      setTimeout(() => {
        this.innerHTML = originalText;
        this.style.background = 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)';
      }, 1500);

      // Animar el input
      accessCodeInput.style.transform = 'scale(1.05)';
      accessCodeInput.style.background = '#fff3cd';
      setTimeout(() => {
        accessCodeInput.style.transform = 'scale(1)';
        accessCodeInput.style.background = '';
      }, 300);
    });
  }

  // Preview de nueva imagen
  const newImageInput = document.getElementById('newImage');
  const currentImage = document.getElementById('currentImage');

  if (newImageInput) {
    newImageInput.addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(event) {
          if (currentImage) {
            currentImage.src = event.target.result;
          } else {
            // Crear preview si no existe imagen actual
            const preview = document.createElement('img');
            preview.src = event.target.result;
            preview.className = 'preview-image';
            preview.id = 'currentImage';
            const container = document.querySelector('.preview-container') ||
                            newImageInput.parentElement.querySelector('div') ||
                            (() => {
                              const div = document.createElement('div');
                              div.className = 'preview-container';
                              newImageInput.parentElement.appendChild(div);
                              return div;
                            })();
            container.innerHTML = '';
            container.appendChild(preview);
          }
        };
        reader.readAsDataURL(file);
      }
    });
  }
});
</script>
</body>
</html>
