<?php
declare(strict_types=1);
/**
 * emprendedoras-paypal-ipn.php
 * Recibe IPN de PayPal para suscripciones de emprendedoras.
 * Verifica el pago, activa la suscripción y envía emails.
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';

// Log
$logFile = __DIR__ . '/logs/paypal_ipn_emprendedoras.log';
if (!is_dir(dirname($logFile))) @mkdir(dirname($logFile), 0777, true);

function sub_log(string $msg, $data = null): void {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($data !== null) $line .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

sub_log('========== IPN SUBSCRIPTION RECEIVED ==========');

// Leer body raw
$raw = file_get_contents('php://input');
if (!$raw) {
    sub_log('EMPTY_PAYLOAD');
    http_response_code(200);
    echo 'OK';
    exit;
}

sub_log('RAW_POST', $raw);

// Verificar con PayPal
$isSandbox  = strpos($raw, 'test_ipn=1') !== false;
if (defined('PAYPAL_MODE') && PAYPAL_MODE !== 'live') $isSandbox = true;
$verifyUrl  = $isSandbox
    ? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr'
    : 'https://ipnpb.paypal.com/cgi-bin/webscr';

$ch = curl_init($verifyUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => 'cmd=_notify-validate&' . $raw,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded', 'Connection: close'],
]);
$resp = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

sub_log('VERIFY_RESPONSE', ['http' => $httpCode, 'resp' => trim((string)$resp)]);

if ($httpCode !== 200 || trim((string)$resp) !== 'VERIFIED') {
    sub_log('NOT_VERIFIED');
    http_response_code(200);
    echo 'IGNORED';
    exit;
}

// Parsear datos
$post = [];
parse_str($raw, $post);
sub_log('POST_PARSED', $post);

$paymentStatus = strtolower(trim($post['payment_status'] ?? ''));
$txnId         = $post['txn_id'] ?? '';
$receiverEmail = $post['receiver_email'] ?? '';
$customRaw     = $post['custom'] ?? '';

// Validar email receptor
$validEmails = [];
if (defined('PAYPAL_EMAIL') && PAYPAL_EMAIL) $validEmails[] = PAYPAL_EMAIL;
if ($isSandbox) $validEmails[] = 'sb-ttcma47147404@business.example.com';

if (!empty($validEmails)) {
    $emailOk = false;
    foreach ($validEmails as $e) {
        if (strcasecmp($receiverEmail, $e) === 0) { $emailOk = true; break; }
    }
    if (!$emailOk) {
        sub_log('BAD_RECEIVER', ['recv' => $receiverEmail, 'valid' => $validEmails]);
        http_response_code(200);
        echo 'BAD_RECEIVER';
        exit;
    }
}

// Solo procesar pagos completados
if ($paymentStatus !== 'completed') {
    sub_log('STATUS_NOT_COMPLETED', $paymentStatus);
    http_response_code(200);
    echo 'OK';
    exit;
}

// Decodificar custom
$custom = json_decode($customRaw, true);
sub_log('CUSTOM', $custom);

if (!is_array($custom) || ($custom['type'] ?? '') !== 'entrepreneur_subscription') {
    sub_log('NOT_SUBSCRIPTION_TYPE');
    http_response_code(200);
    echo 'OK';
    exit;
}

$userId        = (int)($custom['user_id']        ?? 0);
$planId        = (int)($custom['plan_id']         ?? 0);
$billingPeriod = (string)($custom['billing_period'] ?? 'monthly');

if ($userId <= 0 || $planId <= 0) {
    sub_log('MISSING_IDS', $custom);
    http_response_code(200);
    echo 'OK';
    exit;
}

$pdo = db();

// Idempotencia: verificar si ya se procesó este txn_id
$already = $pdo->prepare("SELECT 1 FROM entrepreneur_subscriptions WHERE payment_method='paypal' AND payment_date IS NOT NULL AND user_id=? AND plan_id=? AND status='active' LIMIT 1");
$already->execute([$userId, $planId]);
if ($already->fetchColumn()) {
    sub_log('ALREADY_ACTIVE', ['user_id' => $userId]);
    http_response_code(200);
    echo 'OK';
    exit;
}

try {
    $pdo->beginTransaction();

    // Obtener plan
    $plan = $pdo->prepare("SELECT * FROM entrepreneur_plans WHERE id = ?");
    $plan->execute([$planId]);
    $planData = $plan->fetch(PDO::FETCH_ASSOC);

    if (!$planData) {
        throw new Exception('Plan no encontrado: ' . $planId);
    }

    $endDate = $billingPeriod === 'annual'
        ? date('Y-m-d H:i:s', strtotime('+1 year'))
        : date('Y-m-d H:i:s', strtotime('+1 month'));

    // Cancelar suscripciones previas
    $pdo->prepare("UPDATE entrepreneur_subscriptions SET status='cancelled', updated_at=datetime('now') WHERE user_id=? AND status IN ('active','pending')")
        ->execute([$userId]);

    // Crear suscripción activa
    $pdo->prepare("
        INSERT INTO entrepreneur_subscriptions (user_id,plan_id,status,payment_method,payment_date,start_date,end_date,auto_renew,created_at,updated_at)
        VALUES (?,?,'active','paypal',datetime('now'),datetime('now'),?,0,datetime('now'),datetime('now'))
    ")->execute([$userId, $planId, $endDate]);

    $pdo->commit();
    sub_log('SUBSCRIPTION_ACTIVATED', ['user_id' => $userId, 'plan_id' => $planId]);

    // Obtener datos del usuario para emails
    $userStmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    $userName  = $user['name']  ?? 'Emprendedora';
    $userEmail = $user['email'] ?? '';

    // Precio en CRC (aproximado)
    $priceCRC = $billingPeriod === 'annual' ? (float)$planData['price_annual'] : (float)$planData['price_monthly'];

    // Email al cliente
    if ($userEmail) {
        $htmlCliente = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
            <div style='background:linear-gradient(135deg,#667eea,#764ba2);padding:40px;text-align:center;border-radius:16px 16px 0 0;'>
                <h1 style='color:#fff;margin:0;font-size:1.8rem;'>🌸 CompraTica Emprendedoras</h1>
            </div>
            <div style='background:#fff;padding:40px;border:1px solid #e0e0e0;'>
                <h2 style='color:#27ae60;'>✅ ¡Pago confirmado! Tu plan está activo</h2>
                <p>Hola <strong>" . htmlspecialchars($userName) . "</strong>,</p>
                <p>Tu pago por PayPal fue procesado exitosamente. Tu suscripción al <strong>" . htmlspecialchars($planData['name']) . "</strong> está ahora activa.</p>
                <div style='background:#f8f9ff;padding:20px;border-radius:12px;margin:20px 0;border-left:4px solid #667eea;'>
                    <p style='margin:5px 0;'><strong>Plan:</strong> " . htmlspecialchars($planData['name']) . "</p>
                    <p style='margin:5px 0;'><strong>Período:</strong> " . ($billingPeriod === 'annual' ? 'Anual' : 'Mensual') . "</p>
                    <p style='margin:5px 0;'><strong>ID Transacción PayPal:</strong> " . htmlspecialchars($txnId) . "</p>
                    <p style='margin:5px 0;'><strong>Válido hasta:</strong> " . date('d/m/Y', strtotime($endDate)) . "</p>
                </div>
                <p>Ya puedes acceder a tu dashboard y comenzar a vender.</p>
                <div style='text-align:center;margin:30px 0;'>
                    <a href='" . SITE_URL . "/emprendedoras-dashboard' style='background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:14px 30px;border-radius:50px;text-decoration:none;font-weight:bold;'>Ir a mi Dashboard</a>
                </div>
            </div>
            <div style='background:#f9fafb;padding:20px;text-align:center;border-radius:0 0 16px 16px;color:#666;font-size:0.85rem;'>
                CompraTica — El marketplace costarricense
            </div>
        </div>";
        @send_email($userEmail, '✅ Pago Confirmado - Suscripción Emprendedoras CompraTica', $htmlCliente);
        sub_log('EMAIL_CLIENTE_SENT', ['to' => $userEmail]);
    }

    // Email al admin
    $htmlAdmin = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;'>
        <h2 style='color:#27ae60;'>💰 Nueva Suscripción Emprendedora - PayPal</h2>
        <table style='width:100%;border-collapse:collapse;'>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Emprendedora:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($userName) . "</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Email:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($userEmail) . "</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Plan:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($planData['name']) . "</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Período:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . ($billingPeriod === 'annual' ? 'Anual' : 'Mensual') . "</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>TXN ID PayPal:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($txnId) . "</td></tr>
            <tr><td style='padding:8px;'><strong>Estado:</strong></td><td style='padding:8px;'><span style='color:#27ae60;font-weight:bold;'>ACTIVO ✅</span></td></tr>
        </table>
    </div>";
    @send_email(ADMIN_EMAIL, "[Emprendedoras] 💰 Suscripción PayPal Confirmada - {$userName}", $htmlAdmin);
    sub_log('EMAIL_ADMIN_SENT', ['to' => ADMIN_EMAIL]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sub_log('ERROR', ['msg' => $e->getMessage()]);
}

http_response_code(200);
sub_log('========== IPN DONE ==========');
echo 'OK';
