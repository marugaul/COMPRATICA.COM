<?php
declare(strict_types=1);
/**
 * api/paypal-create-order.php
 * Crea una orden en PayPal Orders API v2.
 * Llamado por el PayPal JS SDK (createOrder callback).
 * Retorna { id: "paypal_order_id" }
 *
 * IMPORTANTE: No incluir "payment_source" en el body.
 * El SDK maneja la fuente de pago; incluirlo causa PAYER_ACTION_REQUIRED (HTTP 200)
 * en vez de CREATED (HTTP 201), lo que rompe el flujo de botones.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

$logFile = __DIR__ . '/../logs/paypal_sdk.log';
if (!is_dir(dirname($logFile))) { @mkdir(dirname($logFile), 0777, true); }
function sdk_log(string $msg, $data = null): void {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] $msg";
    if ($data !== null) $line .= " | " . json_encode($data, JSON_UNESCAPED_UNICODE);
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PHPSESSID');
    session_start();
}

$user_id = (int)($_SESSION['user_id'] ?? $_SESSION['uid'] ?? 0);
if ($user_id <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$checkout = $_SESSION['checkout_paypal'] ?? null;
if (!$checkout || empty($checkout['order_number'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No hay sesión de pago activa. Por favor regresa al checkout.']);
    exit;
}

$order_number = (string)$checkout['order_number'];
$total_usd    = (float)($checkout['total_usd'] ?? 0);
$paypal_email = (string)($checkout['paypal_email'] ?? '');
$sale_id      = (int)($checkout['sale_id'] ?? 0);

if ($total_usd < 0.01) $total_usd = 0.01;
$amount_str = number_format($total_usd, 2, '.', '');

sdk_log("CREATE_START", [
    'order_number' => $order_number,
    'total_usd'    => $amount_str,
    'paypal_email' => $paypal_email,
    'user_id'      => $user_id,
]);

$mode      = defined('PAYPAL_MODE') ? PAYPAL_MODE : 'live';
$base_url  = ($mode === 'sandbox')
    ? 'https://api-m.sandbox.paypal.com'
    : 'https://api-m.paypal.com';
$client_id = defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : '';
$secret    = defined('PAYPAL_SECRET')    ? PAYPAL_SECRET    : '';

// --- Access token ---
$ch = curl_init($base_url . '/v1/oauth2/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_USERPWD        => "$client_id:$secret",
    CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$token_resp   = (string)curl_exec($ch);
$token_err    = curl_error($ch);
curl_close($ch);
$token_data   = json_decode($token_resp, true);
$access_token = (string)($token_data['access_token'] ?? '');

if ($access_token === '') {
    sdk_log("TOKEN_ERROR", ['err' => $token_err, 'resp' => $token_resp]);
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo autenticar con PayPal. Intenta de nuevo.']);
    exit;
}
sdk_log("TOKEN_OK");

// --- Construir orden ---
// NO incluir "payment_source": el SDK lo gestiona según el botón que presionó el usuario.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'compratica.com';

$purchase_unit = [
    'reference_id' => $order_number,
    'description'  => 'Orden ' . $order_number . ' — Compratica',
    'amount'       => [
        'currency_code' => 'USD',
        'value'         => $amount_str,
    ],
];

// Pago directo al vendedor (requiere que el vendedor tenga cuenta Business en PayPal)
if ($paypal_email !== '') {
    $purchase_unit['payee'] = ['email_address' => $paypal_email];
}

$order_payload = [
    'intent'         => 'CAPTURE',
    'purchase_units' => [$purchase_unit],
];

$body_json = (string)json_encode($order_payload, JSON_UNESCAPED_UNICODE);

// --- Crear orden ---
$ch = curl_init($base_url . '/v2/checkout/orders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body_json,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
        'PayPal-Request-Id: ' . $order_number . '-' . time(),
        'Prefer: return=representation',
    ],
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$order_resp = (string)curl_exec($ch);
$curl_err   = curl_error($ch);
$http_code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$order_data = json_decode($order_resp, true);
$pp_id      = (string)($order_data['id'] ?? '');
$pp_status  = (string)($order_data['status'] ?? '');

sdk_log("CREATE_RESP", [
    'http'   => $http_code,
    'id'     => $pp_id,
    'status' => $pp_status,
]);

// PayPal retorna 201+CREATED para el flujo de botones
if ($http_code !== 201 || $pp_id === '') {
    $err = $order_data['message'] ?? $order_data['error_description'] ?? 'Error al crear orden en PayPal';
    sdk_log("CREATE_ERROR", ['http' => $http_code, 'err' => $err, 'curl_err' => $curl_err, 'resp' => $order_data]);
    http_response_code(500);
    echo json_encode(['error' => $err]);
    exit;
}

// Guardar paypal_order_id en sesión para la captura
$_SESSION['checkout_paypal']['paypal_order_id'] = $pp_id;

sdk_log("CREATE_OK", ['paypal_order_id' => $pp_id]);
echo json_encode(['id' => $pp_id]);
