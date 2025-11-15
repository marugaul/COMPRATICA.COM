<?php
// /api/cart.php — API de carrito con logs en /api/error_log

// Archivo de log fijo dentro de /api
function api_log($msg, $ctx = null){
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($ctx !== null) {
        $line .= ' | ' . json_encode($ctx, JSON_UNESCAPED_UNICODE);
    }
    @error_log($line . PHP_EOL, 3, __DIR__ . '/error_log');
}

// Sesión para guardar el carrito (no usada para CSRF)
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.cookie_samesite', 'Lax');
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
    api_log('SESSION_START', ['sid' => session_id()]);
}

header('Content-Type: application/json; charset=UTF-8');

// Inicializar carrito
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_GET['action'] ?? 'get';

function respond($data, $code = 200){
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// CSRF: Double Submit Cookie (sin $_SESSION['csrf_token'])
function validate_csrf_and_log(){
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $cookieToken = $_COOKIE['vg_csrf'] ?? '';
    $ok = ($headerToken && $cookieToken && hash_equals($headerToken, $cookieToken));
    api_log('CSRF_CHECK', [
        'header_len' => strlen($headerToken ?: ''),
        'cookie_len' => strlen($cookieToken ?: ''),
        'match' => $ok ? 1 : 0
    ]);
    return $ok;
}

function cart_count(){
    $n = 0;
    foreach ($_SESSION['cart'] as $it) {
        $n += (int)($it['qty'] ?? 0);
    }
    return $n;
}

if ($action === 'get') {
    api_log('GET_CART', ['count' => cart_count()]);
    respond([
        'ok' => true,
        'cart_count' => cart_count(),
        'items' => array_values($_SESSION['cart'])
    ]);
}

if ($action === 'add') {
    api_log('ADD_START');

    if (!validate_csrf_and_log()) {
        api_log('ADD_CSRF_FAIL');
        respond(['ok' => false, 'error' => 'CSRF inválido'], 419);
    }

    $raw = file_get_contents('php://input');
    api_log('ADD_RAW', ['raw' => $raw]);

    $input = json_decode($raw, true);
    if (!$input || !isset($input['product_id'])) {
        api_log('ADD_BAD_INPUT', ['decoded' => $input]);
        respond(['ok' => false, 'error' => 'Datos inválidos'], 400);
    }

    $pid   = (int)$input['product_id'];
    $qty   = max(1, (int)($input['qty'] ?? 1));
    $price = (float)($input['unit_price'] ?? 0);

    api_log('ADD_BEFORE', ['cart' => $_SESSION['cart']]);

    if (!isset($_SESSION['cart'][$pid])) {
        $_SESSION['cart'][$pid] = ['product_id'=>$pid, 'qty'=>$qty, 'unit_price'=>$price];
    } else {
        $_SESSION['cart'][$pid]['qty'] += $qty;
    }

    api_log('ADD_AFTER', ['cart' => $_SESSION['cart']]);

    respond([
        'ok' => true,
        'cart_count' => cart_count(),
        'items' => array_values($_SESSION['cart'])
    ]);
}

if ($action === 'log_error') {
    $body = file_get_contents('php://input');
    api_log('FRONT_LOG', ['body' => $body]);
    respond(['ok' => true]);
}

respond(['ok' => false, 'error' => 'Acción no soportada'], 404);