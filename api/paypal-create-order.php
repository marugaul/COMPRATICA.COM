<?php
declare(strict_types=1);
/**
 * api/paypal-create-order.php
 * Crea una orden en PayPal Orders API v2.
 * Llamado por el PayPal JS SDK (createOrder callback).
 * Retorna { id: "paypal_order_id" }
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

// Sesión
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

$pending = $_SESSION['pending_paypal'] ?? null;
if (!$pending || empty($pending['order_number'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No hay orden pendiente en sesión']);
    exit;
}

$order_number = (string)$pending['order_number'];
$total_usd    = (float)($pending['total_usd'] ?? 0);
$paypal_email = (string)($pending['paypal_email'] ?? '');
$sale_id      = (int)($pending['sale_id'] ?? 0);

if ($total_usd < 0.01) $total_usd = 0.01;
$amount_str = number_format($total_usd, 2, '.', '');

sdk_log("CREATE_ORDER_START", [
    'order_number' => $order_number,
    'total_usd'    => $amount_str,
    'paypal_email' => $paypal_email,
    'user_id'      => $user_id,
]);

// Config PayPal
$mode       = defined('PAYPAL_MODE') ? PAYPAL_MODE : 'live';
$base_url   = ($mode === 'sandbox')
    ? 'https://api-m.sandbox.paypal.com'
    : 'https://api-m.paypal.com';
$client_id  = defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : '';
$secret     = defined('PAYPAL_SECRET')    ? PAYPAL_SECRET    : '';

// --- Obtener access token ---
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
$token_resp = curl_exec($ch);
$token_err  = curl_error($ch);
curl_close($ch);

$token_data   = json_decode((string)$token_resp, true);
$access_token = (string)($token_data['access_token'] ?? '');

if ($access_token === '') {
    sdk_log("TOKEN_ERROR", ['err' => $token_err, 'resp' => $token_resp]);
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo autenticar con PayPal']);
    exit;
}
sdk_log("TOKEN_OK");

// --- Construir cuerpo de la orden ---
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'compratica.com';
$base     = $scheme . '://' . $host;

$purchase_unit = [
    'reference_id' => $order_number,
    'description'  => 'Orden ' . $order_number,
    'amount'       => [
        'currency_code' => 'USD',
        'value'         => $amount_str,
    ],
];

// Pago directo al vendedor (requiere cuenta PayPal Business del vendedor)
if ($paypal_email !== '') {
    $purchase_unit['payee'] = ['email_address' => $paypal_email];
}

$order_payload = [
    'intent'         => 'CAPTURE',
    'purchase_units' => [$purchase_unit],
    'payment_source' => [
        'paypal' => [
            'experience_context' => [
                'brand_name'       => 'Compratica',
                'landing_page'     => 'BILLING',
                'user_action'      => 'PAY_NOW',
                'return_url'       => $base . '/order-success.php',
                'cancel_url'       => $base . '/checkout.php?sale_id=' . $sale_id,
            ],
        ],
    ],
];

$body_json = (string)json_encode($order_payload, JSON_UNESCAPED_UNICODE);

// --- Crear orden en PayPal ---
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
$order_resp  = curl_exec($ch);
$order_err   = curl_error($ch);
$http_code   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$order_data = json_decode((string)$order_resp, true);
sdk_log("CREATE_ORDER_RESP", ['http' => $http_code, 'id' => $order_data['id'] ?? null, 'status' => $order_data['status'] ?? null]);

if ($http_code !== 201 || empty($order_data['id'])) {
    $err = $order_data['message'] ?? $order_data['error_description'] ?? 'Error al crear orden en PayPal';
    sdk_log("CREATE_ORDER_ERROR", ['http' => $http_code, 'err' => $err, 'resp' => $order_data, 'curl_err' => $order_err]);
    http_response_code(500);
    echo json_encode(['error' => $err]);
    exit;
}

// Guardar paypal_order_id en sesión para la captura
$_SESSION['pending_paypal']['paypal_order_id'] = $order_data['id'];

sdk_log("CREATE_ORDER_OK", ['paypal_order_id' => $order_data['id']]);
echo json_encode(['id' => $order_data['id']]);
