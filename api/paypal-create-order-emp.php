<?php
declare(strict_types=1);
/**
 * api/paypal-create-order-emp.php
 * Crea una orden PayPal Orders API v2 para emprendedoras.
 * Reutiliza la misma lógica que paypal-create-order.php pero
 * lee de $_SESSION['emp_paypal_pending'][$sid].
 *
 * GET/POST param: sid (seller_id)
 * Retorna: { id: "PAYPAL_ORDER_ID" }
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

$logFile = __DIR__ . '/../logs/paypal_sdk.log';
if (!is_dir(dirname($logFile))) { @mkdir(dirname($logFile), 0777, true); }
function emp_sdk_log(string $msg, $data = null): void {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] [EMP] $msg";
    if ($data !== null) $line .= " | " . json_encode($data, JSON_UNESCAPED_UNICODE);
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PHPSESSID');
    session_start();
}

// Obtener seller_id del request
$input = json_decode((string)file_get_contents('php://input'), true) ?? [];
$sid   = (int)($_GET['sid'] ?? $input['sid'] ?? 0);

if ($sid <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetro sid requerido']);
    exit;
}

$pending = $_SESSION['emp_paypal_pending'][$sid] ?? null;
if (!$pending) {
    http_response_code(400);
    echo json_encode(['error' => 'Sesión de pago expirada. Por favor recarga la página.']);
    exit;
}

$paypal_email = (string)($pending['paypal_email'] ?? '');
$total_usd    = (float)($pending['total_usd'] ?? 0);
$seller_name  = (string)($pending['seller_name'] ?? 'Vendedor/a');
$order_ref    = 'EMP-' . $sid . '-' . time();

if ($total_usd < 0.01) $total_usd = 0.01;
$amount_str = number_format($total_usd, 2, '.', '');

emp_sdk_log("CREATE_START", [
    'sid'          => $sid,
    'paypal_email' => $paypal_email,
    'total_usd'    => $amount_str,
]);

$mode      = defined('PAYPAL_MODE') ? PAYPAL_MODE : 'live';
$base_url  = ($mode === 'sandbox') ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
$client_id = defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : '';
$secret    = defined('PAYPAL_SECRET')    ? PAYPAL_SECRET    : '';

if ($client_id === '' || $secret === '') {
    emp_sdk_log("NO_CREDENTIALS");
    http_response_code(503);
    echo json_encode(['error' => 'PayPal no configurado']);
    exit;
}

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
$token_resp = (string)curl_exec($ch);
$token_http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$token_data   = json_decode($token_resp, true);
$access_token = (string)($token_data['access_token'] ?? '');
if ($token_http !== 200 || $access_token === '') {
    emp_sdk_log("TOKEN_ERROR", ['http' => $token_http]);
    http_response_code(503);
    echo json_encode(['error' => 'No se pudo conectar con PayPal. Intenta de nuevo.']);
    exit;
}

// --- Construir orden ---
$purchase_unit = [
    'reference_id' => $order_ref,
    'description'  => 'Compra en CompraTica — ' . $seller_name,
    'amount'       => [
        'currency_code' => 'USD',
        'value'         => $amount_str,
    ],
];

// Pago directo al vendedor/a
if ($paypal_email !== '') {
    $purchase_unit['payee'] = ['email_address' => $paypal_email];
}

$order_payload = [
    'intent'         => 'CAPTURE',
    'purchase_units' => [$purchase_unit],
];

$body_json = (string)json_encode($order_payload, JSON_UNESCAPED_UNICODE);

$ch = curl_init($base_url . '/v2/checkout/orders');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body_json,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
        'PayPal-Request-Id: ' . $order_ref,
        'Prefer: return=representation',
    ],
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$order_resp = (string)curl_exec($ch);
$http_code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$order_data = json_decode($order_resp, true);
$pp_id      = (string)($order_data['id'] ?? '');

emp_sdk_log("CREATE_RESP", ['http' => $http_code, 'pp_id' => $pp_id]);

if ($http_code !== 201 || $pp_id === '') {
    $details = $order_data['details'] ?? [];
    $issues  = array_column($details, 'issue');
    if (in_array('PAYEE_ACCOUNT_RESTRICTED', $issues, true)) {
        $err = 'El vendedor/a tiene la cuenta PayPal restringida. Usa SINPE o contacta directamente.';
    } else {
        $err = $order_data['message'] ?? 'Error al crear orden en PayPal';
    }
    emp_sdk_log("CREATE_ERROR", ['err' => $err, 'resp' => $order_data]);
    http_response_code(422);
    echo json_encode(['error' => $err]);
    exit;
}

// Guardar pp_order_id en sesión para la captura
$_SESSION['emp_paypal_pending'][$sid]['paypal_order_id'] = $pp_id;
$_SESSION['emp_paypal_pending'][$sid]['order_ref']       = $order_ref;

emp_sdk_log("CREATE_OK", ['pp_id' => $pp_id, 'order_ref' => $order_ref]);
echo json_encode(['id' => $pp_id]);
