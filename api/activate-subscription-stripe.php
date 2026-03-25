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

$logFile = __DIR__ . '/../logs/stripe_emprendedoras.log';
if (!is_dir(dirname($logFile))) @mkdir(dirname($logFile), 0755, true);

function _log_stripe(string $msg): void {
    global $logFile;
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

function _json_err_stripe(string $msg): void {
    _log_stripe('ERROR | ' . $msg);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// Auth check
if (!isset($_SESSION['uid']) || (int)$_SESSION['uid'] <= 0) {
    _json_err_stripe('No autenticado');
}

$userId    = (int)$_SESSION['uid'];
$userName  = $_SESSION['name']  ?? 'Emprendedora';
$userEmail = $_SESSION['email'] ?? '';

// Parse request
$body = json_decode((string)file_get_contents('php://input'), true);
if (!$body) {
    _json_err_stripe('Request inválido');
}

$paymentIntentId = trim((string)($body['payment_intent_id'] ?? ''));
$planId          = (int)($body['plan_id'] ?? 0);
$billingPeriod   = in_array($body['billing_period'] ?? '', ['monthly', 'annual'], true)
    ? $body['billing_period']
    : 'monthly';
$amountUsd = (float)($body['amount_usd'] ?? 0);

if (!$paymentIntentId || !$planId) {
    _json_err_stripe('Datos incompletos');
}

_log_stripe("REQUEST | user_id=$userId plan_id=$planId billing=$billingPeriod pi=$paymentIntentId amount_usd=$amountUsd");

// ============================================================
// Verificar el PaymentIntent con la API de Stripe
// ============================================================
$stripeSecretKey = defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : '';

if (!$stripeSecretKey) {
    _json_err_stripe('Stripe no está configurado. Por favor usa otro método de pago.');
}

$ch = curl_init('https://api.stripe.com/v1/payment_intents/' . urlencode($paymentIntentId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET        => true,
    CURLOPT_USERPWD        => $stripeSecretKey . ':',
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$piJson  = curl_exec($ch);
$piError = curl_error($ch);
curl_close($ch);

if ($piError) {
    _json_err_stripe('Error conectando con Stripe: ' . $piError);
}

$piData = json_decode((string)$piJson, true);
_log_stripe('PI_STATUS | ' . ($piData['status'] ?? 'unknown'));

if (($piData['status'] ?? '') !== 'succeeded') {
    _json_err_stripe('El pago no fue completado (status: ' . ($piData['status'] ?? 'unknown') . ')');
}

// Verificar monto (Stripe usa centavos de USD)
$capturedAmountCents = (int)($piData['amount_received'] ?? 0);
$capturedAmountUsd   = $capturedAmountCents / 100;
$expectedCents       = (int)round($amountUsd * 100);

if ($capturedAmountCents < ($expectedCents - 5)) { // tolerancia de $0.05
    _log_stripe("AMOUNT_MISMATCH | expected_cents=$expectedCents captured_cents=$capturedAmountCents");
    _json_err_stripe('El monto pagado no coincide con el precio del plan');
}

// ============================================================
// Activar suscripción en base de datos
// ============================================================
$pdo = db();

$stmt = $pdo->prepare("SELECT * FROM entrepreneur_plans WHERE id = ? AND is_active = 1");
$stmt->execute([$planId]);
$planData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$planData) {
    _json_err_stripe('Plan no encontrado');
}

// Idempotencia: verificar si ya existe suscripción activa con este payment intent
$stmtCheck = $pdo->prepare(
    "SELECT id FROM entrepreneur_subscriptions WHERE user_id = ? AND payment_reference = ? AND status = 'active'"
);
$stmtCheck->execute([$userId, $paymentIntentId]);
if ($stmtCheck->fetch()) {
    _log_stripe("DUPLICATE | pi=$paymentIntentId ya procesado");
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
    try {
        $pdo->prepare("
            INSERT INTO entrepreneur_subscriptions
                (user_id, plan_id, status, payment_method, payment_reference, payment_date, start_date, end_date, auto_renew)
            VALUES (?, ?, 'active', 'stripe', ?, datetime('now'), datetime('now'), ?, 0)
        ")->execute([$userId, $planId, $paymentIntentId, $endDate]);
    } catch (PDOException $e) {
        // Si la columna payment_reference no existe, insertar sin ella
        $pdo->prepare("
            INSERT INTO entrepreneur_subscriptions
                (user_id, plan_id, status, payment_method, payment_date, start_date, end_date, auto_renew)
            VALUES (?, ?, 'active', 'stripe', datetime('now'), datetime('now'), ?, 0)
        ")->execute([$userId, $planId, $endDate]);
    }

    _log_stripe("ACTIVATED | user_id=$userId plan_id=$planId billing=$billingPeriod end_date=$endDate");
} catch (PDOException $e) {
    _log_stripe('DB_ERROR | ' . $e->getMessage());
    _json_err_stripe('Error al activar la suscripción. Por favor contacta soporte.');
}

// Limpiar sesión
unset($_SESSION['ent_sub_plan_id'], $_SESSION['ent_sub_billing'], $_SESSION['ent_sub_price']);

// ============================================================
// Enviar correos
// ============================================================
$stmtST3 = $pdo->prepare("SELECT seller_type FROM users WHERE id = ?");
$stmtST3->execute([$userId]);
$sellerType3  = $stmtST3->fetchColumn() ?: 'emprendedora';
$empLabel3    = $sellerType3 === 'emprendedor' ? 'Emprendedor' : 'Emprendedora';
$empHeader3   = $sellerType3 === 'emprendedor' ? '💼 CompraTica Emprendedores' : '🌸 CompraTica Emprendedoras';

$price        = $billingPeriod === 'annual' ? (float)$planData['price_annual'] : (float)$planData['price_monthly'];
$periodoLabel = $billingPeriod === 'annual' ? 'Anual' : 'Mensual';

$emailCliente = "
<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
    <div style='background:linear-gradient(135deg,#667eea,#764ba2);padding:40px;text-align:center;border-radius:16px 16px 0 0;'>
        <h1 style='color:#fff;margin:0;font-size:1.8rem;'>$empHeader3</h1>
    </div>
    <div style='background:#fff;padding:40px;border:1px solid #e0e0e0;'>
        <h2 style='color:#27ae60;'>✅ ¡Tu plan está activo!</h2>
        <p>Hola <strong>" . htmlspecialchars($userName) . "</strong>,</p>
        <p>Tu suscripción al <strong>" . htmlspecialchars($planData['name']) . "</strong> ha sido activada exitosamente mediante Stripe.</p>
        <div style='background:#f8f9ff;padding:20px;border-radius:12px;margin:20px 0;border-left:4px solid #667eea;'>
            <p style='margin:5px 0;'><strong>Plan:</strong> " . htmlspecialchars($planData['name']) . "</p>
            <p style='margin:5px 0;'><strong>Período:</strong> $periodoLabel</p>
            <p style='margin:5px 0;'><strong>Monto:</strong> &#x20A1;" . number_format($price, 0) . " (US\$" . number_format($capturedAmountUsd, 2) . ")</p>
        </div>
        <p>Ya puedes acceder a tu dashboard y comenzar a publicar tus productos.</p>
        <div style='text-align:center;margin:30px 0;'>
            <a href='" . SITE_URL . "/emprendedoras-dashboard' style='background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:14px 30px;border-radius:50px;text-decoration:none;font-weight:bold;'>Ir a mi Dashboard</a>
        </div>
    </div>
    <div style='background:#f9fafb;padding:20px;text-align:center;border-radius:0 0 16px 16px;color:#666;font-size:0.85rem;'>
        CompraTica — El marketplace costarricense
    </div>
</div>";

$emailAdmin = "
<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;'>
    <h2 style='color:#27ae60;'>✅ Nueva Suscripción $empLabel3 (Stripe)</h2>
    <table style='width:100%;border-collapse:collapse;'>
        <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>$empLabel3:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($userName) . "</td></tr>
        <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Email:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($userEmail) . "</td></tr>
        <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Plan:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($planData['name']) . "</td></tr>
        <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Período:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>$periodoLabel</td></tr>
        <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Monto:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>&#x20A1;" . number_format($price, 0) . " (US\$" . number_format($capturedAmountUsd, 2) . ")</td></tr>
        <tr><td style='padding:8px;'><strong>PaymentIntent:</strong></td><td style='padding:8px;'>" . htmlspecialchars($paymentIntentId) . "</td></tr>
    </table>
</div>";

try {
    send_email($userEmail, '✅ Tu Plan en CompraTica está activo', $emailCliente);
} catch (Throwable $e) {
    _log_stripe('EMAIL_CLIENTE_ERR | ' . $e->getMessage());
}
try {
    send_email(ADMIN_EMAIL, "[Emprendedores] Nueva suscripción Stripe - $userName", $emailAdmin);
} catch (Throwable $e) {
    _log_stripe('EMAIL_ADMIN_ERR | ' . $e->getMessage());
}

echo json_encode(['ok' => true]);
