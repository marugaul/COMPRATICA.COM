<?php
// api/upload-sinpe-receipt.php
// Endpoint para subir comprobantes de pago SINPE Móvil
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();

// Verificar autenticación
if (!isset($_SESSION['agent_id']) && !isset($_SESSION['employer_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'No autenticado']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
  exit;
}

try {
  $pdo = db();

  // Obtener datos del formulario
  $listing_id = (int)($_POST['listing_id'] ?? 0);
  $listing_type = $_POST['listing_type'] ?? ''; // 'real_estate', 'job', 'service'

  if ($listing_id <= 0) {
    throw new Exception('ID de publicación inválido');
  }

  if (!in_array($listing_type, ['real_estate', 'job', 'service'])) {
    throw new Exception('Tipo de publicación inválido');
  }

  // Verificar que se subió un archivo
  if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
    throw new Exception('No se recibió el archivo o hubo un error en la carga');
  }

  $file = $_FILES['receipt'];
  $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
  $max_size = 5 * 1024 * 1024; // 5MB

  // Validar tipo de archivo
  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime = finfo_file($finfo, $file['tmp_name']);
  finfo_close($finfo);

  if (!in_array($mime, $allowed_types)) {
    throw new Exception('Tipo de archivo no permitido. Solo JPG, PNG, WEBP o PDF');
  }

  // Validar tamaño
  if ($file['size'] > $max_size) {
    throw new Exception('El archivo es muy grande. Máximo 5MB');
  }

  // Crear directorio si no existe
  $upload_dir = __DIR__ . '/../uploads/payment-receipts';
  if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
  }

  // Generar nombre único para el archivo
  $extension = match($mime) {
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
    default => 'jpg'
  };

  $filename = sprintf(
    '%s_%d_%s.%s',
    $listing_type,
    $listing_id,
    bin2hex(random_bytes(8)),
    $extension
  );

  $filepath = $upload_dir . '/' . $filename;

  // Mover archivo
  if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    throw new Exception('Error al guardar el archivo');
  }

  $file_url = '/uploads/payment-receipts/' . $filename;

  // Actualizar la base de datos según el tipo de publicación
  switch ($listing_type) {
    case 'real_estate':
      $table = 'real_estate_listings';
      $user_id_field = 'agent_id';
      $user_id = (int)$_SESSION['agent_id'];
      break;

    case 'job':
      $table = 'job_listings';
      $user_id_field = 'employer_id';
      $user_id = (int)$_SESSION['employer_id'];
      break;

    case 'service':
      $table = 'service_listings';
      $user_id_field = 'agent_id';
      $user_id = (int)$_SESSION['agent_id'];
      break;
  }

  // Verificar que la publicación pertenece al usuario
  $stmt = $pdo->prepare("SELECT id, payment_status FROM $table WHERE id = ? AND $user_id_field = ?");
  $stmt->execute([$listing_id, $user_id]);
  $listing = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$listing) {
    // Eliminar archivo subido
    unlink($filepath);
    throw new Exception('Publicación no encontrada o no tienes permiso');
  }

  // Guardar referencia del comprobante
  // Crear tabla para comprobantes si no existe
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS payment_receipts (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      listing_type TEXT NOT NULL,
      listing_id INTEGER NOT NULL,
      user_id INTEGER NOT NULL,
      receipt_url TEXT NOT NULL,
      status TEXT DEFAULT 'pending',
      uploaded_at TEXT DEFAULT (datetime('now')),
      reviewed_at TEXT,
      reviewed_by INTEGER,
      notes TEXT
    )
  ");

  $stmt = $pdo->prepare("
    INSERT INTO payment_receipts (listing_type, listing_id, user_id, receipt_url, status)
    VALUES (?, ?, ?, ?, 'pending')
  ");
  $stmt->execute([$listing_type, $listing_id, $user_id, $file_url]);

  // Actualizar estado de la publicación a 'pending_review' si es necesario
  $pdo->prepare("
    UPDATE $table
    SET payment_status = 'pending_review'
    WHERE id = ? AND payment_status = 'pending'
  ")->execute([$listing_id]);

  // Enviar notificación por email al admin (opcional)
  // TODO: Implementar envío de email

  echo json_encode([
    'ok' => true,
    'message' => 'Comprobante subido correctamente. Tu publicación será revisada en breve.',
    'receipt_url' => $file_url
  ]);

} catch (Exception $e) {
  http_response_code(400);
  echo json_encode([
    'ok' => false,
    'error' => $e->getMessage()
  ]);
}
