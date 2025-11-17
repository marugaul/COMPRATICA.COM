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
    .preview-container {
      margin-top: 1rem;
    }
    .preview-image {
      max-width: 300px;
      max-height: 200px;
      border-radius: 8px;
      margin-top: 0.5rem;
      border: 2px solid #e2e8f0;
    }
    .remove-image-btn {
      display: inline-block;
      margin-top: 0.5rem;
      padding: 0.5rem 1rem;
      background: #e53e3e;
      color: white;
      border-radius: 6px;
      text-decoration: none;
      font-size: 0.875rem;
      cursor: pointer;
      border: none;
    }
    .remove-image-btn:hover {
      background: #c53030;
    }
    .alert.success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
      padding: 1rem;
      border-radius: 6px;
      margin-bottom: 1rem;
    }
    .alert.error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
      padding: 1rem;
      border-radius: 6px;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body>
<header class="header">
  <div class="logo">✏️ Editar Espacio</div>
  <nav>
    <a class="btn" href="sales.php">← Volver a Mis Espacios</a>
    <a class="btn" href="dashboard.php">Panel</a>
  </nav>
</header>

<div class="container">
  <?php if ($msg): ?>
    <div class="alert <?= $msgType ?>">
      <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <h3>Editar: <?= htmlspecialchars($sale['title']) ?></h3>

    <form class="form" method="post" enctype="multipart/form-data" id="editForm">
      <label>
        Título *
        <input class="input" name="title" value="<?= htmlspecialchars($sale['title']) ?>" required>
      </label>

      <label>
        Portada actual
        <?php if ($sale['cover_image']): ?>
          <div class="preview-container">
            <img src="../uploads/affiliates/<?= htmlspecialchars($sale['cover_image']) ?>"
                 alt="Portada actual"
                 class="preview-image"
                 id="currentImage">
          </div>
        <?php else: ?>
          <div style="color: #999; font-style: italic; margin-top: 0.5rem;">
            Sin imagen
          </div>
        <?php endif; ?>
      </label>

      <label>
        Cambiar portada (opcional)
        <input class="input" type="file" name="cover" accept="image/*" id="newImage">
        <small style="color: #666; display: block; margin-top: 0.5rem;">
          Si subes una nueva imagen, reemplazará la actual
        </small>
      </label>

      <label>
        Fecha y Hora de Inicio *
        <small style="color:#666; display:block; margin-top:4px;">
          📅 Incluye la hora exacta (ej: 8:00 AM)
        </small>
        <input class="input" type="datetime-local" name="start_at" id="start_at"
               value="<?= htmlspecialchars($startFormatted) ?>" required>
      </label>

      <label>
        Fecha y Hora de Fin *
        <small style="color:#666; display:block; margin-top:4px;">
          📅 Incluye la hora exacta (ej: 6:00 PM)
        </small>
        <input class="input" type="datetime-local" name="end_at" id="end_at"
               value="<?= htmlspecialchars($endFormatted) ?>" required>
      </label>

      <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 1rem; margin: 1rem 0;">
        <h4 style="margin-top: 0; color: #495057;">🔒 Configuración de privacidad</h4>

        <label style="display: flex; align-items: center; cursor: pointer; margin-bottom: 1rem;">
          <input type="checkbox" name="is_private" id="is_private" value="1"
                 <?= !empty($sale['is_private']) ? 'checked' : '' ?>
                 style="width: auto; margin-right: 0.5rem;">
          <span><strong>Espacio privado</strong> - Requiere código de acceso para ver productos</span>
        </label>

        <div id="access_code_container" style="<?= !empty($sale['is_private']) ? '' : 'display: none;' ?>">
          <label>
            Código de acceso (6 dígitos)
            <small style="color:#666; display:block; margin-top:4px;">
              🔑 Los clientes necesitarán este código para acceder a los productos
            </small>
            <input class="input" type="text" name="access_code" id="access_code"
                   pattern="[0-9]{6}" maxlength="6" placeholder="Ej: 123456"
                   value="<?= htmlspecialchars($sale['access_code'] ?? '') ?>"
                   style="font-size: 1.2rem; letter-spacing: 0.3rem; font-family: monospace;">
            <small style="color:#999; display:block; margin-top:4px;">
              Solo números, exactamente 6 dígitos
            </small>
          </label>
        </div>
      </div>

      <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
        <button class="btn primary" type="submit" name="update" value="1">
          <i class="fas fa-save"></i> Guardar Cambios
        </button>
        <a class="btn" href="sales.php">Cancelar</a>
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
