<?php
/**
 * upload-proof.php — Subir comprobante de pago SINPE
 * - Deja la orden en "En Revisión"
 * - Elimina ítems del carrito del sale_id del pedido para el usuario logueado (si hay sesión)
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php'; // <-- agregado para usar SMTP

// ============================================
// SESIÓN (evitar ini_set cuando ya está activa)
// ============================================
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
} // si ya está activa, NO tocar ini_set / params

// ============================================
// LOGGING
// ============================================
$logFile = __DIR__ . '/logs/upload_proof_debug.log';
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

debug_log("========== UPLOAD-PROOF START ==========");
debug_log("GET_PARAMS", $_GET);
debug_log("POST_PARAMS", $_POST);
debug_log("SESSION", [
  'uid' => $_SESSION['uid'] ?? 'N/A',
  'user_id' => $_SESSION['user_id'] ?? 'N/A',
  'email' => $_SESSION['email'] ?? 'N/A'
]);

// ============================================
// INPUT
// ============================================
$orderNumber = $_GET['order'] ?? $_GET['order_id'] ?? '';
$error = '';
$success = '';

debug_log("ORDER_NUMBER_RECEIVED", $orderNumber);

if (empty($orderNumber)) {
  debug_log("ERROR: No order number provided");
  header('Location: index.php');
  exit;
}

try {
  $pdo = db();
  debug_log("DB_CONNECTED");

  // Cargar orden por id o order_number
  $order = null;
  if (is_numeric($orderNumber)) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$orderNumber]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($order) $orderNumber = $order['order_number'];
    debug_log("ORDER_FOUND_BY_ID", $order ? ['id'=>$order['id'],'order_number'=>$orderNumber] : 'NOT_FOUND');
  }
  if (!$order) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    debug_log("ORDER_FOUND_BY_NUMBER", $order ? ['id'=>$order['id']] : 'NOT_FOUND');
  }
  if (!$order) {
    $error = 'Orden no encontrada';
  } elseif (!empty($order['proof_image'])) {
    $success = 'Ya has subido un comprobante para esta orden';
    debug_log("INFO: Proof already uploaded", $order['proof_image']);
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

      $allowedTypes = ['image/jpeg','image/png','image/jpg','image/webp'];
      if (!in_array($fileType, $allowedTypes, true)) {
        $error = 'Solo se permiten imágenes (JPG, PNG, WEBP)';
        debug_log("ERROR: Invalid file type", $fileType);
      } else {
        $uploadDir = __DIR__ . '/uploads/proofs/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0775, true); debug_log("UPLOAD_DIR_CREATED", $uploadDir); }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'proof_' . $orderNumber . '_' . time() . '.' . $ext;
        $destination = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
          debug_log("FILE_MOVED_SUCCESS", $filename);

          // 1) Actualizar órdenes del order_number → "En Revisión"
          $stmt = $pdo->prepare("
            UPDATE orders
               SET proof_image = ?,
                   status = 'En Revisión',
                   updated_at = datetime('now','localtime')
             WHERE order_number = ?
          ");
          $stmt->execute([$filename, $orderNumber]);
          debug_log("ORDER_UPDATED_TO_REVIEW", ['rows_affected'=>$stmt->rowCount()]);

          // 2) Eliminar ítems del carrito del sale_id del pedido para el usuario en sesión (si existe)
          try {
            $st = $pdo->prepare("
              SELECT DISTINCT p.sale_id
              FROM orders o
              JOIN products p ON p.id = o.product_id
              WHERE o.order_number = ?
              LIMIT 1
            ");
            $st->execute([$orderNumber]);
            $saleIdFromOrder = (int)($st->fetchColumn() ?: 0);

            $uid = (int)($_SESSION['user_id'] ?? 0);
            if ($saleIdFromOrder > 0 && $uid > 0) {
              $st = $pdo->prepare("SELECT id FROM carts WHERE user_id=? ORDER BY id DESC LIMIT 1");
              $st->execute([$uid]);
              $cartIdUser = (int)($st->fetchColumn() ?: 0);

              if ($cartIdUser > 0) {
                $del = $pdo->prepare("
                  DELETE FROM cart_items
                   WHERE cart_id = ?
                     AND (sale_id = ? OR product_id IN (SELECT id FROM products WHERE sale_id = ?))
                ");
                $del->execute([$cartIdUser, $saleIdFromOrder, $saleIdFromOrder]);
                debug_log("CART_CLEARED_SINPE", ['uid'=>$uid,'cart_id'=>$cartIdUser,'sale_id'=>$saleIdFromOrder,'deleted'=>$del->rowCount()]);
              } else {
                debug_log("CART_NOT_FOUND_FOR_UID", ['uid'=>$uid]);
              }
            } else {
              debug_log("CART_CLEAR_SKIPPED", ['sale_id'=>$saleIdFromOrder,'uid'=>$_SESSION['user_id'] ?? null]);
            }
          } catch (Throwable $e) {
            debug_log("CART_CLEAR_ERR", ['err'=>$e->getMessage()]);
          }

          // 3) Notificar (texto actualizado a "En Revisión") — AHORA por SMTP
          $stmt = $pdo->prepare("
            SELECT o.*, p.name as product_name, s.title as sale_title,
                   a.name as affiliate_name, a.email as affiliate_email
            FROM orders o
            JOIN products p ON o.product_id = p.id
            JOIN sales s ON o.sale_id = s.id
            JOIN affiliates a ON s.affiliate_id = a.id
            WHERE o.order_number = ?
            LIMIT 1
          ");
          $stmt->execute([$orderNumber]);
          $orderDetails = $stmt->fetch(PDO::FETCH_ASSOC);

          if ($orderDetails) {
            debug_log("ORDER_DETAILS_FETCHED", ['buyer'=>$orderDetails['buyer_email'],'seller'=>$orderDetails['affiliate_email']]);

            $site_url   = defined('SITE_URL') ? SITE_URL : 'https://compratica.com';
            $app_name   = defined('APP_NAME') ? APP_NAME : 'VentaGaraje Online';

            // Email al comprador (mantenemos el mismo texto, pero lo enviamos como HTML con nl2br)
            $buyer_subject = "Comprobante recibido - En Revisión - {$orderDetails['sale_title']}";
            $buyer_message = "Hola {$orderDetails['buyer_name']},\n\n";
            $buyer_message .= "Hemos recibido tu comprobante de pago para la orden {$orderNumber}.\n";
            $buyer_message .= "Tu pedido está en estado: EN REVISIÓN por el vendedor.\n\n";
            $buyer_message .= "Detalles de tu orden:\n";
            $buyer_message .= "- Producto: {$orderDetails['product_name']}\n";
            $buyer_message .= "- Cantidad: {$orderDetails['qty']}\n";
            $buyer_message .= "- Total: ₡" . number_format((float)$orderDetails['grand_total'], 2) . "\n\n";
            $buyer_message .= "Gracias por tu compra!\n{$app_name}";
            $buyer_html = nl2br(htmlspecialchars($buyer_message, ENT_QUOTES, 'UTF-8'));
            // Reply-To al afiliado (igual que antes en headers)
            @send_email($orderDetails['buyer_email'], $buyer_subject, $buyer_html, $orderDetails['affiliate_email'] ?? null, $orderDetails['affiliate_name'] ?? null);

            // Email al vendedor/afiliado (mismo contenido, vía SMTP)
            $seller_subject  = "Comprobante recibido - En Revisión - {$orderDetails['sale_title']}";
            $seller_message  = "Hola {$orderDetails['affiliate_name']},\n\n";
            $seller_message .= "El comprador ha subido el comprobante para la orden {$orderNumber}.\n";
            $seller_message .= "Estado actual: EN REVISIÓN.\n\n";
            $seller_message .= "Detalles del pedido:\n";
            $seller_message .= "- Comprador: {$orderDetails['buyer_name']}\n";
            $seller_message .= "- Email: {$orderDetails['buyer_email']}\n";
            $seller_message .= "- Teléfono: {$orderDetails['buyer_phone']}\n";
            $seller_message .= "- Producto: {$orderDetails['product_name']}\n";
            $seller_message .= "- Cantidad: {$orderDetails['qty']}\n";
            $seller_message .= "- Total: ₡" . number_format((float)$orderDetails['grand_total'], 2) . "\n\n";
            $seller_message .= "Comprobante: {$site_url}/uploads/proofs/{$filename}\n\n";
            $seller_message .= "Saludos,\n{$app_name}";
            $seller_html = nl2br(htmlspecialchars($seller_message, ENT_QUOTES, 'UTF-8'));
            // Reply-To al comprador
            @send_email($orderDetails['affiliate_email'], $seller_subject, $seller_html, $orderDetails['buyer_email'] ?? null, $orderDetails['buyer_name'] ?? null);
          }

          $success = '¡Comprobante subido! Tu pedido quedó en EN REVISIÓN y el vendedor lo confirmará.';

          // Recargar orden
          $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
          $stmt->execute([$orderNumber]);
          $order = $stmt->fetch(PDO::FETCH_ASSOC);

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
    .order-info { background: #f0f9ff; border: 2px solid #bae6fd; border-radius: 12px; padding: 20px; margin-bottom: 25px; text-align: center; }
    .order-info strong { font-size: 1.3rem; color: #0c4a6e; display: block; margin-top: 8px; font-family: 'Courier New', monospace; }
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
      <h1>📤 Subir Comprobante</h1>
      <p>Sube tu comprobante de pago SINPE</p>
    </div>

    <div class="content">
      <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <?php if (isset($order) && $order): ?>
        <div class="order-info">
          <div style="color: #0c4a6e; font-size: 0.9rem;">Orden</div>
          <strong><?= htmlspecialchars($orderNumber) ?></strong>
        </div>

        <?php if (empty($order['proof_image'])): ?>
          <div class="instructions">
            <h3>📋 En Revisión</h3>
            <ol>
              <li>Realiza la transferencia SINPE al número indicado por el vendedor</li>
              <li>Toma una captura de pantalla del comprobante</li>
              <li>Sube la imagen aquí (JPG, PNG o WEBP) — máx. 5MB</li>
              <li>Tu pedido quedará <strong>EN REVISIÓN</strong> por el vendedor</li>
            </ol>
          </div>

          <form method="post" enctype="multipart/form-data" id="uploadForm">
            <div class="upload-area" id="uploadArea" onclick="document.getElementById('fileInput').click()">
              <div class="icon">📸</div>
              <h3>Haz clic o arrastra tu comprobante aquí</h3>
              <p style="color: #6b7280; margin-top: 10px;">JPG, PNG o WEBP (máx. 5MB)</p>
              <input type="file" name="proof" id="fileInput" accept="image/*" required>
            </div>

            <div class="preview" id="preview" style="display: none;">
              <img id="previewImg" src="" alt="Vista previa">
            </div>

            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Subir Comprobante</button>
            <a href="order-success.php?order=<?= urlencode($orderNumber) ?>" class="btn btn-secondary">Volver</a>
          </form>
        <?php else: ?>
          <div class="alert alert-success">
            ✅ Ya has subido un comprobante para esta orden. Estado: <strong>EN REVISIÓN</strong>.
          </div>

          <div class="preview">
            <h3 style="margin-bottom: 15px;">Tu comprobante:</h3>
            <img src="uploads/proofs/<?= htmlspecialchars($order['proof_image']) ?>" alt="Comprobante">
          </div>

          <a href="order-success.php?order=<?= urlencode($orderNumber) ?>" class="btn btn-primary">Volver a mi orden</a>
        <?php endif; ?>
      <?php else: ?>
        <div class="alert alert-error">
          ⚠️ No se encontró la orden. Por favor verifica el enlace.
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
          const reader = new FileReader();
          reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
            submitBtn.disabled = false;
          };
          reader.readAsDataURL(file);
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
