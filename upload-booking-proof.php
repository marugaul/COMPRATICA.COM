<?php
/**
 * upload-booking-proof.php — Subir comprobante de pago SINPE para reservas de servicios
 * - Deja la reserva en "En Revisión"
 * - Envía correos al cliente y al afiliado
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';

// SESIÓN
$__isHttps  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$__sessPath = __DIR__ . '/sessions';

if (session_status() !== PHP_SESSION_ACTIVE) {
  if (!is_dir($__sessPath)) @mkdir($__sessPath, 0700, true);
  if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
  }
  session_name('PHPSESSID');
  if (PHP_VERSION_ID < 70300) {
    ini_set('session.cookie_lifetime', '0');
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_domain', '');
    ini_set('session.cookie_secure', $__isHttps ? '1' : '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_set_cookie_params(0, '/', '', $__isHttps, true);
  } else {
    session_set_cookie_params([
      'lifetime'=>0,'path'=>'/','domain'=>'',
      'secure'=>$__isHttps,'httponly'=>true,'samesite'=>'Lax',
    ]);
  }
  ini_set('session.use_strict_mode','0');
  ini_set('session.use_only_cookies','1');
  ini_set('session.gc_maxlifetime','86400');
  session_start();
}

// LOGGING
$logFile = __DIR__ . '/logs/upload_booking_proof_debug.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }

function debug_log($message, $data = null) {
  global $logFile;
  $timestamp = date('Y-m-d H:i:s');
  $logMessage = "[$timestamp] $message";
  if ($data !== null) { $logMessage .= " | " . json_encode($data, JSON_UNESCAPED_UNICODE); }
  $logMessage .= PHP_EOL;
  @file_put_contents($logFile, $logMessage, FILE_APPEND);
}

debug_log("========== UPLOAD-BOOKING-PROOF START ==========");
debug_log("GET_PARAMS", $_GET);
debug_log("POST_PARAMS", $_POST);
debug_log("SESSION", [
  'uid' => $_SESSION['uid'] ?? 'N/A',
  'user_id' => $_SESSION['user_id'] ?? 'N/A',
  'email' => $_SESSION['email'] ?? 'N/A'
]);

// INPUT
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$error = '';
$success = '';

debug_log("BOOKING_ID_RECEIVED", $booking_id);

if ($booking_id <= 0) {
  debug_log("ERROR: No booking_id provided");
  header('Location: index.php');
  exit;
}

try {
  $pdo = db();
  debug_log("DB_CONNECTED");

  // Cargar reserva
  $stmt = $pdo->prepare("
    SELECT
      sb.*,
      s.title as service_title,
      sc.name as category_name,
      a.name as affiliate_name,
      a.email as affiliate_email,
      a.phone as affiliate_phone
    FROM service_bookings sb
    INNER JOIN services s ON s.id = sb.service_id
    INNER JOIN service_categories sc ON sc.id = s.category_id
    INNER JOIN affiliates a ON a.id = sb.affiliate_id
    WHERE sb.id = ?
    LIMIT 1
  ");
  $stmt->execute([$booking_id]);
  $booking = $stmt->fetch(PDO::FETCH_ASSOC);

  debug_log("BOOKING_FOUND", $booking ? ['id'=>$booking['id'], 'service'=>$booking['service_title']] : 'NOT_FOUND');

  if (!$booking) {
    $error = 'Reserva no encontrada';
  } elseif (!empty($booking['proof_image'])) {
    $success = 'Ya has subido un comprobante para esta reserva';
    debug_log("INFO: Proof already uploaded", $booking['proof_image']);
  }

  // Procesar upload
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['proof']) && empty($success) && empty($error)) {
    debug_log("PROCESSING_UPLOAD", $_FILES['proof']);

    $file = $_FILES['proof'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
      $error = 'Error al subir el archivo (código: ' . $file['error'] . ')';
      debug_log("UPLOAD_ERROR", ['error_code' => $file['error']]);
    } else {
      // Detectar MIME
      $fileType = '';
      if (function_exists('mime_content_type')) {
        $fileType = mime_content_type($file['tmp_name']);
      } elseif (function_exists('finfo_file')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $fileType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
      } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $map = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','pdf'=>'application/pdf'];
        $fileType = $map[$ext] ?? 'application/octet-stream';
      }

      debug_log("FILE_TYPE_DETECTED", $fileType);

      $allowedTypes = ['image/jpeg','image/png','image/jpg','image/webp','application/pdf'];
      if (!in_array($fileType, $allowedTypes, true)) {
        $error = 'Solo se permiten imágenes (JPG, PNG, WEBP) o PDF';
        debug_log("ERROR: Invalid file type", $fileType);
      } else {
        $uploadDir = __DIR__ . '/uploads/booking_proofs/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0775, true); debug_log("UPLOAD_DIR_CREATED", $uploadDir); }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'booking_proof_' . $booking_id . '_' . time() . '.' . $ext;
        $destination = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
          debug_log("FILE_MOVED_SUCCESS", $filename);

          // Actualizar reserva → "En Revisión"
          $stmt = $pdo->prepare("
            UPDATE service_bookings
               SET proof_image = ?,
                   status = 'En Revisión',
                   updated_at = datetime('now')
             WHERE id = ?
          ");
          $stmt->execute([$filename, $booking_id]);
          debug_log("BOOKING_UPDATED_TO_REVIEW", ['rows_affected'=>$stmt->rowCount()]);

          // Notificar por correo
          $site_url   = defined('SITE_URL') ? SITE_URL : 'https://compratica.com';
          $app_name   = defined('APP_NAME') ? APP_NAME : 'Compratica';

          // Email al cliente
          $customer_subject = "Comprobante recibido - Reserva En Revisión - {$booking['service_title']}";
          $customer_message = "Hola {$booking['customer_name']},\n\n";
          $customer_message .= "Hemos recibido tu comprobante de pago para tu reserva #{$booking_id}.\n";
          $customer_message .= "Tu reserva está en estado: EN REVISIÓN por el proveedor del servicio.\n\n";
          $customer_message .= "Detalles de tu reserva:\n";
          $customer_message .= "- Servicio: {$booking['service_title']}\n";
          $customer_message .= "- Categoría: {$booking['category_name']}\n";
          $customer_message .= "- Fecha: " . date('d/m/Y', strtotime($booking['booking_date'])) . "\n";
          $customer_message .= "- Hora: " . date('g:i A', strtotime($booking['booking_time'])) . "\n";
          $customer_message .= "- Total: ₡" . number_format((float)$booking['total_amount'], 2) . "\n\n";
          $customer_message .= "El proveedor del servicio confirmará tu reserva pronto.\n\n";
          $customer_message .= "Gracias por tu reserva!\n{$app_name}";
          $customer_html = nl2br(htmlspecialchars($customer_message, ENT_QUOTES, 'UTF-8'));

          @send_email(
            $booking['customer_email'],
            $customer_subject,
            $customer_html,
            $booking['affiliate_email'] ?? null,
            $booking['affiliate_name'] ?? null
          );

          // Email al afiliado/proveedor
          $affiliate_subject  = "Nuevo comprobante de reserva - {$booking['service_title']}";
          $affiliate_message  = "Hola {$booking['affiliate_name']},\n\n";
          $affiliate_message .= "El cliente ha subido el comprobante para una reserva de tu servicio.\n";
          $affiliate_message .= "Estado actual: EN REVISIÓN.\n\n";
          $affiliate_message .= "Detalles de la reserva:\n";
          $affiliate_message .= "- Reserva ID: {$booking_id}\n";
          $affiliate_message .= "- Servicio: {$booking['service_title']}\n";
          $affiliate_message .= "- Cliente: {$booking['customer_name']}\n";
          $affiliate_message .= "- Email: {$booking['customer_email']}\n";
          $affiliate_message .= "- Teléfono: {$booking['customer_phone']}\n";
          $affiliate_message .= "- Fecha: " . date('d/m/Y', strtotime($booking['booking_date'])) . "\n";
          $affiliate_message .= "- Hora: " . date('g:i A', strtotime($booking['booking_time'])) . "\n";
          $affiliate_message .= "- Duración: {$booking['duration_minutes']} minutos\n";
          if (!empty($booking['address'])) {
            $affiliate_message .= "- Dirección: {$booking['address']}\n";
          }
          if (!empty($booking['notes'])) {
            $affiliate_message .= "- Notas: {$booking['notes']}\n";
          }
          $affiliate_message .= "- Total: ₡" . number_format((float)$booking['total_amount'], 2) . "\n\n";
          $affiliate_message .= "Comprobante: {$site_url}/uploads/booking_proofs/{$filename}\n\n";
          $affiliate_message .= "Por favor confirma la reserva cuando verifiques el pago.\n\n";
          $affiliate_message .= "Saludos,\n{$app_name}";
          $affiliate_html = nl2br(htmlspecialchars($affiliate_message, ENT_QUOTES, 'UTF-8'));

          @send_email(
            $booking['affiliate_email'],
            $affiliate_subject,
            $affiliate_html,
            $booking['customer_email'] ?? null,
            $booking['customer_name'] ?? null
          );

          $success = '¡Comprobante subido! Tu reserva quedó en EN REVISIÓN y el proveedor la confirmará pronto.';

          // Recargar reserva
          $stmt = $pdo->prepare("
            SELECT
              sb.*,
              s.title as service_title,
              sc.name as category_name,
              a.name as affiliate_name,
              a.email as affiliate_email,
              a.phone as affiliate_phone
            FROM service_bookings sb
            INNER JOIN services s ON s.id = sb.service_id
            INNER JOIN service_categories sc ON sc.id = s.category_id
            INNER JOIN affiliates a ON a.id = sb.affiliate_id
            WHERE sb.id = ?
            LIMIT 1
          ");
          $stmt->execute([$booking_id]);
          $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        } else {
          $error = 'Error al guardar el archivo';
          debug_log("ERROR: Failed to move uploaded file");
        }
      }
    }
  }

} catch (Throwable $e) {
  debug_log("EXCEPTION", ['message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'trace'=>$e->getTraceAsString()]);
  $error = 'Error del sistema: ' . $e->getMessage();
}

$APP_NAME = defined('APP_NAME') ? APP_NAME : 'Compratica';
if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Subir Comprobante - <?= htmlspecialchars($APP_NAME) ?></title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 40px 20px; }
    .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden; }
    .header { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white; padding: 30px; text-align: center; }
    .header h1 { font-size: 1.8rem; margin-bottom: 8px; }
    .header p { opacity: 0.9; }
    .content { padding: 30px; }
    .booking-info { background: #f0f9ff; border: 2px solid #bae6fd; border-radius: 12px; padding: 20px; margin-bottom: 25px; text-align: center; }
    .booking-info strong { font-size: 1.3rem; color: #0c4a6e; display: block; margin-top: 8px; font-family: 'Courier New', monospace; }
    .upload-area { border: 3px dashed #cbd5e1; border-radius: 12px; padding: 40px 20px; text-align: center; cursor: pointer; transition: all 0.3s; margin-bottom: 20px; }
    .upload-area:hover { border-color: #667eea; background: #f9fafb; }
    .upload-area.dragover { border-color: #667eea; background: #f0f9ff; }
    .upload-area .icon { font-size: 3rem; margin-bottom: 15px; }
    .upload-area input[type="file"] { display: none; }
    .preview { margin: 20px 0; text-align: center; }
    .preview img { max-width: 100%; max-height: 400px; border-radius: 12px; border: 2px solid #e5e7eb; }
    .btn { width: 100%; padding: 14px; border: none; border-radius: 10px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s; margin-top: 10px; display: inline-block; text-align: center; text-decoration: none; }
    .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3); }
    .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
    .btn-secondary { background: white; color: #374151; border: 2px solid #e5e7eb; }
    .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; }
    .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .instructions { background: #fef3c7; border: 2px solid #fbbf24; border-radius: 12px; padding: 20px; margin-bottom: 25px; }
    .instructions h3 { color: #92400e; margin-bottom: 10px; }
    .instructions ol { color: #78350f; margin-left: 20px; }
    .instructions li { margin: 8px 0; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>📤 Subir Comprobante de Pago</h1>
      <p>Sube tu comprobante de pago SINPE</p>
    </div>

    <div class="content">
      <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <?php if (isset($booking) && $booking): ?>
        <div class="booking-info">
          <div style="color: #0c4a6e; font-size: 0.9rem;">Reserva</div>
          <strong>#<?= htmlspecialchars($booking_id) ?></strong>
          <div style="margin-top: 10px; color: #475569;">
            <?= htmlspecialchars($booking['service_title']) ?>
          </div>
        </div>

        <?php if (empty($booking['proof_image'])): ?>
          <div class="instructions">
            <h3>📋 Instrucciones</h3>
            <ol>
              <li>Realiza la transferencia SINPE al número indicado por el proveedor del servicio</li>
              <li>Toma una captura de pantalla del comprobante</li>
              <li>Sube la imagen o PDF aquí (máx. 5MB)</li>
              <li>Tu reserva quedará <strong>EN REVISIÓN</strong> hasta que el proveedor confirme el pago</li>
            </ol>
          </div>

          <form method="post" enctype="multipart/form-data" id="uploadForm">
            <div class="upload-area" id="uploadArea" onclick="document.getElementById('fileInput').click()">
              <div class="icon">📸</div>
              <h3>Haz clic o arrastra tu comprobante aquí</h3>
              <p style="color: #6b7280; margin-top: 10px;">JPG, PNG, WEBP o PDF (máx. 5MB)</p>
              <input type="file" name="proof" id="fileInput" accept="image/*,application/pdf" required>
            </div>

            <div class="preview" id="preview" style="display: none;">
              <img id="previewImg" src="" alt="Vista previa">
            </div>

            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Subir Comprobante</button>
            <a href="servicios.php" class="btn btn-secondary">Volver a servicios</a>
          </form>
        <?php else: ?>
          <div class="alert alert-success">
            ✅ Ya has subido un comprobante para esta reserva. Estado: <strong>EN REVISIÓN</strong>.
          </div>

          <div class="preview">
            <h3 style="margin-bottom: 15px;">Tu comprobante:</h3>
            <?php if (pathinfo($booking['proof_image'], PATHINFO_EXTENSION) === 'pdf'): ?>
              <p style="font-size: 3rem;">📄</p>
              <p><?= htmlspecialchars($booking['proof_image']) ?></p>
              <a href="uploads/booking_proofs/<?= htmlspecialchars($booking['proof_image']) ?>" target="_blank" class="btn btn-secondary" style="margin-top: 1rem;">Ver PDF</a>
            <?php else: ?>
              <img src="uploads/booking_proofs/<?= htmlspecialchars($booking['proof_image']) ?>" alt="Comprobante">
            <?php endif; ?>
          </div>

          <a href="servicios.php" class="btn btn-primary">Volver a servicios</a>
        <?php endif; ?>
      <?php else: ?>
        <div class="alert alert-error">
          ⚠️ No se encontró la reserva. Por favor verifica el enlace.
        </div>
        <a href="index.php" class="btn btn-secondary">Volver al inicio</a>
      <?php endif; ?>
    </div>
  </div>

  <script>
    const fileInput = document.getElementById('fileInput');
    const uploadArea = document.getElementById('uploadArea');
    const preview = document.getElementById('preview');
    const previewImg = document.getElementById('previewImg');
    const submitBtn = document.getElementById('submitBtn');

    if (fileInput) {
      fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
          if (file.size > 5 * 1024 * 1024) { alert('El archivo es muy grande. Máximo 5MB.'); fileInput.value = ''; return; }

          // Si es PDF, no mostrar preview
          if (file.type === 'application/pdf') {
            preview.innerHTML = '<p style="font-size: 3rem;">📄</p><p>' + file.name + '</p>';
            preview.style.display = 'block';
            submitBtn.disabled = false;
          } else {
            const reader = new FileReader();
            reader.onload = function(e) {
              previewImg.src = e.target.result;
              preview.style.display = 'block';
              submitBtn.disabled = false;
            };
            reader.readAsDataURL(file);
          }
        }
      });

      uploadArea.addEventListener('dragover', function(e) { e.preventDefault(); uploadArea.classList.add('dragover'); });
      uploadArea.addEventListener('dragleave', function(e) { e.preventDefault(); uploadArea.classList.remove('dragover'); });
      uploadArea.addEventListener('drop', function(e) {
        e.preventDefault(); uploadArea.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
          fileInput.files = files;
          fileInput.dispatchEvent(new Event('change'));
        }
      });
    }
  </script>
</body>
</html>
