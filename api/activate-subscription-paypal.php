<?php
declare(strict_types=1);

$__sessPath = __DIR__ . '/../sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
} else {
    ini_set('session.save_path', '/tmp');
}
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json');

$logFile = __DIR__ . '/../logs/paypal_wallet_emprendedoras.log';
if (!is_dir(dirname($logFile))) @mkdir(dirname($logFile), 0755, true);

function _log_wallet(string $msg): void {
    global $logFile;
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

function _json_err(string $msg): void {
    _log_wallet('ERROR | ' . $msg);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// Auth check
if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) {
    _json_err('No autenticado');
}

$userId    = (int)$_SESSION['uid'];
$userName  = $_SESSION['name']  ?? 'Emprendedora';
$userEmail = $_SESSION['email'] ?? '';

// Parse request
$body = json_decode((string)file_get_contents('php://input'), true);
if (!$body) {
    _json_err('Request inválido');
}

$paypalOrderId = trim((string)($body['paypal_order_id'] ?? ''));
$planId        = (int)($body['plan_id'] ?? 0);
$billingPeriod = in_array($body['billing_period'] ?? '', ['monthly', 'annual'], true)
    ? $body['billing_period']
    : 'monthly';
$amountUsd = (float)($body['amount_usd'] ?? 0);

if (!$paypalOrderId || !$planId) {
    _json_err('Datos incompletos');
}

_log_wallet("REQUEST | user_id=$userId plan_id=$planId billing=$billingPeriod order_id=$paypalOrderId amount_usd=$amountUsd");

// ============================================================
// Verificar la orden con la API de PayPal v2
// ============================================================
$clientId   = defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : '';
$secret     = defined('PAYPAL_SECRET')    ? PAYPAL_SECRET    : '';
$mode       = defined('PAYPAL_MODE')      ? PAYPAL_MODE      : 'sandbox';
$apiBase    = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

if (!$clientId || !$secret) {
    _json_err('Credenciales PayPal no configuradas');
}

// Obtener access token
$ch = curl_init("$apiBase/v1/oauth2/token");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
    CURLOPT_USERPWD        => "$clientId:$secret",
    CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Accept-Language: en_US'],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$tokenJson  = curl_exec($ch);
$tokenError = curl_error($ch);
curl_close($ch);

if ($tokenError) {
    _json_err('Error conectando con PayPal: ' . $tokenError);
}

$tokenData   = json_decode((string)$tokenJson, true);
$accessToken = $tokenData['access_token'] ?? '';

if (!$accessToken) {
    _log_wallet('TOKEN_ERROR | ' . $tokenJson);
    _json_err('Error de autenticación con PayPal');
}

// Consultar la orden
$ch = curl_init("$apiBase/v2/checkout/orders/" . urlencode($paypalOrderId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer $accessToken",
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$orderJson  = curl_exec($ch);
$orderError = curl_error($ch);
curl_close($ch);

if ($orderError) {
    _json_err('Error verificando orden PayPal: ' . $orderError);
}

$orderData = json_decode((string)$orderJson, true);
_log_wallet('ORDER_STATUS | ' . ($orderData['status'] ?? 'unknown'));

if (($orderData['status'] ?? '') !== 'COMPLETED') {
    _json_err('El pago no fue completado (status: ' . ($orderData['status'] ?? 'unknown') . ')');
}

// Verificar monto recibido
$capturedAmount = (float)($orderData['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0);
if ($capturedAmount < ($amountUsd - 0.05)) { // tolerancia de $0.05
    _log_wallet("AMOUNT_MISMATCH | expected=$amountUsd captured=$capturedAmount");
    _json_err('El monto pagado no coincide con el precio del plan');
}

// ============================================================
// Activar suscripción en base de datos
// ============================================================
$pdo = db();

// Obtener plan
$stmt = $pdo->prepare("SELECT * FROM entrepreneur_plans WHERE id = ? AND is_active = 1");
$stmt->execute([$planId]);
$planData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$planData) {
    _json_err('Plan no encontrado');
}

// Idempotencia: verificar si ya existe suscripción activa con esta orden
$stmtCheck = $pdo->prepare(
    "SELECT id FROM entrepreneur_subscriptions WHERE user_id = ? AND payment_reference = ? AND status = 'active'"
);
$stmtCheck->execute([$userId, $paypalOrderId]);
if ($stmtCheck->fetch()) {
    _log_wallet("DUPLICATE | order_id=$paypalOrderId ya procesado");
    echo json_encode(['ok' => true]);
    exit;
}

$endDate = $billingPeriod === 'annual'
    ? date('Y-m-d H:i:s', strtotime('+1 year'))
    : date('Y-m-d H:i:s', strtotime('+1 month'));

try {
    // Cancelar suscripciones previas
    $pdo->prepare(
        "UPDATE entrepreneur_subscriptions SET status='cancelled', updated_at=datetime('now') WHERE user_id=? AND status='active'"
    )->execute([$userId]);

    // Crear nueva suscripción activa
    // Intentar con payment_reference (columna puede o no existir)
    try {
        $pdo->prepare("
            INSERT INTO entrepreneur_subscriptions
                (user_id, plan_id, status, payment_method, payment_reference, payment_date, start_date, end_date, auto_renew)
            VALUES (?, ?, 'active', 'paypal', ?, datetime('now'), datetime('now'), ?, 0)
        ")->execute([$userId, $planId, $paypalOrderId, $endDate]);
    } catch (PDOException $e) {
        // Si la columna payment_reference no existe, insertar sin ella
        $pdo->prepare("
            INSERT INTO entrepreneur_subscriptions
                (user_id, plan_id, status, payment_method, payment_date, start_date, end_date, auto_renew)
            VALUES (?, ?, 'active', 'paypal', datetime('now'), datetime('now'), ?, 0)
        ")->execute([$userId, $planId, $endDate]);
    }

    _log_wallet("ACTIVATED | user_id=$userId plan_id=$planId billing=$billingPeriod end_date=$endDate");
} catch (PDOException $e) {
    _log_wallet('DB_ERROR | ' . $e->getMessage());
    _json_err('Error al activar la suscripción. Por favor contacta soporte.');
}

// Limpiar sesión
unset($_SESSION['ent_sub_plan_id'], $_SESSION['ent_sub_billing'], $_SESSION['ent_sub_price']);

// ============================================================
// Enviar correos
// ============================================================
$stmtST2 = $pdo->prepare("SELECT seller_type FROM users WHERE id = ?");
$stmtST2->execute([$userId]);
$sellerType2  = $stmtST2->fetchColumn() ?: 'emprendedora';
$empLabel2    = $sellerType2 === 'emprendedor' ? 'Emprendedor' : 'Emprendedora';
$empHeader2   = $sellerType2 === 'emprendedor' ? '💼 CompraTica Emprendedores' : '🌸 CompraTica Emprendedoras';

$price        = $billingPeriod === 'annual' ? (float)$planData['price_annual'] : (float)$planData['price_monthly'];
$periodoLabel = $billingPeriod === 'annual' ? 'Anual' : 'Mensual';

$emailCliente = "
<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
    <div style='background:linear-gradient(135deg,#667eea,#764ba2);padding:40px;text-align:center;border-radius:16px 16px 0 0;'>
        <h1 style='color:#fff;margin:0;font-size:1.8rem;'>$empHeader2</h1>
    </div>
    <div style='background:#fff;padding:40px;border:1px solid #e0e0e0;'>
        <h2 style='color:#27ae60;'>Tu plan esta activo!</h2>
        <p>Hola <strong>" . htmlspecialchars($userName) . "</strong>,</p>
        <p>Tu suscripcion al <strong>" . htmlspecialchars($planData['name']) . "</strong> ha sido activada exitosamente mediante PayPal/Wallet.</p>
        <div style='background:#f8f9ff;padding:20px;border-radius:12px;margin:20px 0;border-left:4px solid #667eea;'>
            <p style='margin:5px 0;'><strong>Plan:</strong> " . htmlspecialchars($planData['name']) . "</p>
            <p style='margin:5px 0;'><strong>Periodo:</strong> $periodoLabel</p>
            <p style='margin:5px 0;'><strong>Monto:</strong> &#x20A1;" . number_format($price, 0) . "</p>
            <p style='margin:5px 0;'><strong>Orden PayPal:</strong> " . htmlspecialchars($paypalOrderId) . "</p>
        </div>
        <p>Ya puedes acceder a tu dashboard y comenzar a publicar tus productos.</p>
        <div style='text-align:center;margin:30px 0;'>
            <a href='" . SITE_URL . "/emprendedoras-dashboard' style='background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:14px 30px;border-radius:50px;text-decoration:none;font-weight:bold;'>Ir a mi Dashboard</a>
        </div>
    </div>
    <div style='background:#f9fafb;padding:20px;text-align:center;border-radius:0 0 16px 16px;color:#666;font-size:0.85rem;'>
        CompraTica - El marketplace costarricense
    </div>
</div>";

$emailAdmin = "
<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;'>
    <h2 style='color:#27ae60;'>Nueva Suscripcion $empLabel2 (Wallet)</h2>
    <table style='width:100%;border-collapse:collapse;'>
        <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>$empLabel2:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($userName) . "</td></tr>
        <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Email:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($userEmail) . "</td></tr>
        <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Plan:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($planData['name']) . "</td></tr>
        <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Periodo:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>$periodoLabel</td></tr>
        <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Monto:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>&#x20A1;" . number_format($price, 0) . " (US\$$capturedAmount)</td></tr>
        <tr><td style='padding:8px;'><strong>Orden PayPal:</strong></td><td style='padding:8px;'>" . htmlspecialchars($paypalOrderId) . "</td></tr>
    </table>
</div>";

try {
    send_email($userEmail, 'Tu Plan en CompraTica esta activo', $emailCliente);
} catch (Throwable $e) {
    _log_wallet('EMAIL_CLIENTE_ERR | ' . $e->getMessage());
}
try {
    send_email(ADMIN_EMAIL, "[Emprendedores] Nueva suscripcion Wallet - $userName", $emailAdmin);
} catch (Throwable $e) {
    _log_wallet('EMAIL_ADMIN_ERR | ' . $e->getMessage());
}

echo json_encode(['ok' => true]);
