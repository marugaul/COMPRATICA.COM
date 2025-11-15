<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../php_error.log');
error_reporting(E_ALL);

$__sessPath = __DIR__ . '/../sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0700, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}

$__isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (session_status() !== PHP_SESSION_ACTIVE) {
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
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $__isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    
    ini_set('session.use_strict_mode', '0');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '86400');
    session_start();
}

$logFile = __DIR__ . '/../logs/cart_api.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) @mkdir($logDir, 0775, true);

function logCart($label, $data = null) {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] $label";
    if ($data !== null) $line .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $line .= PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND);
}

logCart('SESSION_STATUS', [
    'status' => session_status(),
    'sid' => session_id(),
    'cookie_sent' => isset($_COOKIE['PHPSESSID']) ? $_COOKIE['PHPSESSID'] : 'none',
    'save_path' => session_save_path()
]);

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_GET['action'] ?? '';

if ($action === 'get') {
    logCart('GET_CART', ['count' => count($_SESSION['cart'])]);
    
    require_once __DIR__ . '/../includes/db.php';
    $pdo = db();
    
    $items = [];
    $total = 0;
    $currency = 'CRC';
    
    foreach ($_SESSION['cart'] as $key => $item) {
        $pid = (int)($item['product_id'] ?? 0);
        $qty = (int)($item['qty'] ?? 0);
        $unit = (float)($item['unit_price'] ?? 0);
        
        if ($pid <= 0 || $qty <= 0) continue;
        
        $stmt = $pdo->prepare("SELECT id, name, price, currency, image, image2 FROM products WHERE id = ? LIMIT 1");
        $stmt->execute([$pid]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($prod) {
            $currency = strtoupper($prod['currency'] ?? 'CRC');
            $img = $prod['image'] ?? $prod['image2'] ?? null;
            if ($img) $img = '/uploads/' . ltrim($img, '/');
            
            $items[] = [
                'product_id' => $pid,
                'product_name' => $prod['name'] ?? 'Producto',
                'product_image_url' => $img,
                'qty' => $qty,
                'unit_price' => $unit,
                'currency' => $currency,
            ];
            $total += $unit * $qty;
        }
    }
    
    echo json_encode([
        'ok' => true,
        'items' => $items,
        'cart_count' => array_sum(array_column($items, 'qty')),
        'total' => $total,
        'currency' => $currency,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    logCart('ADD_START');
    
    $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $cookieToken = $_COOKIE['vg_csrf'] ?? '';
    
    logCart('CSRF_CHECK', [
        'header_len' => strlen($headerToken),
        'cookie_len' => strlen($cookieToken),
        'match' => ($headerToken && $cookieToken && hash_equals($cookieToken, $headerToken)) ? 1 : 0
    ]);
    
    $raw = file_get_contents('php://input');
    logCart('ADD_RAW', ['raw' => $raw]);
    
    $payload = json_decode($raw, true);
    if (!$payload) {
        logCart('ADD_ERROR', ['msg' => 'Invalid JSON']);
        echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
        exit;
    }
    
    $pid = (int)($payload['product_id'] ?? 0);
    $qty = (int)($payload['qty'] ?? 1);
    $unit = (float)($payload['unit_price'] ?? 0);
    
    if ($pid <= 0 || $qty <= 0 || $unit < 0) {
        logCart('ADD_ERROR', ['msg' => 'Invalid params', 'pid' => $pid, 'qty' => $qty, 'unit' => $unit]);
        echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
        exit;
    }
    
    logCart('ADD_BEFORE', ['cart' => $_SESSION['cart']]);
    
    $key = (string)$pid;
    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['qty'] += $qty;
    } else {
        $_SESSION['cart'][$key] = [
            'product_id' => $pid,
            'qty' => $qty,
            'unit_price' => $unit,
        ];
    }
    
    logCart('ADD_AFTER', ['cart' => $_SESSION['cart']]);
    
    $count = 0;
    foreach ($_SESSION['cart'] as $it) $count += (int)($it['qty'] ?? 0);
    
    echo json_encode([
        'ok' => true,
        'cart_count' => $count,
        'message' => 'Producto agregado'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!$payload) {
        echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
        exit;
    }
    
    $pid = (int)($payload['product_id'] ?? 0);
    $qty = (int)($payload['qty'] ?? 0);
    
    if ($pid <= 0) {
        echo json_encode(['ok' => false, 'error' => 'ID inválido']);
        exit;
    }
    
    $key = (string)$pid;
    if ($qty <= 0) {
        unset($_SESSION['cart'][$key]);
    } else {
        if (isset($_SESSION['cart'][$key])) {
            $_SESSION['cart'][$key]['qty'] = $qty;
        }
    }
    
    $count = 0;
    foreach ($_SESSION['cart'] as $it) $count += (int)($it['qty'] ?? 0);
    
    echo json_encode(['ok' => true, 'cart_count' => $count]);
    exit;
}

if ($action === 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    $pid = (int)($payload['product_id'] ?? 0);
    
    if ($pid > 0) {
        unset($_SESSION['cart'][(string)$pid]);
    }
    
    $count = 0;
    foreach ($_SESSION['cart'] as $it) $count += (int)($it['qty'] ?? 0);
    
    echo json_encode(['ok' => true, 'cart_count' => $count]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Acción no válida']);