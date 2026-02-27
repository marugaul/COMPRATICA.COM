<?php
// api/process-paypal-payment.php
// Endpoint para procesar pagos de PayPal y activar publicaciones automáticamente
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

  // Obtener datos del pago
  $json = file_get_contents('php://input');
  $data = json_decode($json, true);

  $listing_id = (int)($data['listing_id'] ?? 0);
  $listing_type = $data['listing_type'] ?? '';
  $paypal_order_id = $data['paypal_order_id'] ?? '';
  $payer_email = $data['payer_email'] ?? '';
  $amount = (float)($data['amount'] ?? 0);

  if ($listing_id <= 0) {
    throw new Exception('ID de publicación inválido');
  }

  if (!in_array($listing_type, ['real_estate', 'job', 'service'])) {
    throw new Exception('Tipo de publicación inválido');
  }

  if (empty($paypal_order_id)) {
    throw new Exception('ID de orden de PayPal inválido');
  }

  // Determinar tabla y campo de usuario
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
  $stmt = $pdo->prepare("SELECT id, payment_status, pricing_plan_id FROM $table WHERE id = ? AND $user_id_field = ?");
  $stmt->execute([$listing_id, $user_id]);
  $listing = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$listing) {
    throw new Exception('Publicación no encontrada o no tienes permiso');
  }

  if ($listing['payment_status'] === 'confirmed') {
    throw new Exception('Esta publicación ya fue pagada');
  }

  // TODO: Verificar el pago con la API de PayPal
  // Por ahora, confiamos en los datos recibidos del cliente
  // En producción, DEBE verificarse con PayPal Server-Side

  /*
  // Ejemplo de verificación con PayPal API (comentado por ahora):
  $paypal_client_id = PAYPAL_CLIENT_ID ?? '';
  $paypal_secret = PAYPAL_SECRET ?? '';

  // Obtener token de acceso
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://api-m.paypal.com/v1/oauth2/token');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERPWD, "$paypal_client_id:$paypal_secret");
  curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
  $response = curl_exec($ch);
  curl_close($ch);

  $token_data = json_decode($response, true);
  $access_token = $token_data['access_token'] ?? '';

  // Verificar la orden
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, "https://api-m.paypal.com/v2/checkout/orders/$paypal_order_id");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token",
    "Content-Type: application/json"
  ]);
  $response = curl_exec($ch);
  curl_close($ch);

  $order_data = json_decode($response, true);

  if ($order_data['status'] !== 'COMPLETED') {
    throw new Exception('El pago no está completado');
  }
  */

  // Registrar el pago
  $pdo->beginTransaction();

  // Actualizar estado de la publicación
  $stmt = $pdo->prepare("
    UPDATE $table
    SET payment_status = 'confirmed',
        payment_id = ?,
        payment_date = datetime('now'),
        is_active = 1
    WHERE id = ?
  ");
  $stmt->execute([$paypal_order_id, $listing_id]);

  // Registrar en historial de pagos
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS payment_history (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      listing_type TEXT NOT NULL,
      listing_id INTEGER NOT NULL,
      user_id INTEGER NOT NULL,
      payment_method TEXT NOT NULL,
      payment_id TEXT NOT NULL,
      payer_email TEXT,
      amount REAL NOT NULL,
      currency TEXT DEFAULT 'USD',
      status TEXT DEFAULT 'completed',
      created_at TEXT DEFAULT (datetime('now'))
    )
  ");

  $stmt = $pdo->prepare("
    INSERT INTO payment_history (listing_type, listing_id, user_id, payment_method, payment_id, payer_email, amount, currency, status)
    VALUES (?, ?, ?, 'paypal', ?, ?, ?, 'USD', 'completed')
  ");
  $stmt->execute([$listing_type, $listing_id, $user_id, $paypal_order_id, $payer_email, $amount]);

  $pdo->commit();

  // Enviar email de confirmación (opcional)
  // TODO: Implementar envío de email

  echo json_encode([
    'ok' => true,
    'message' => '¡Pago confirmado! Tu publicación está ahora activa.',
    'listing_id' => $listing_id
  ]);

} catch (Exception $e) {
  if (isset($pdo) && $pdo->inTransaction()) {
    $pdo->rollBack();
  }

  http_response_code(400);
  echo json_encode([
    'ok' => false,
    'error' => $e->getMessage()
  ]);
}
