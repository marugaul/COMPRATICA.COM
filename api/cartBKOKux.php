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

require_once __DIR__ . '/../includes/db.php';

// ============= HELPER: Obtener o crear cart_id =============
function get_or_create_cart_id() {
    $pdo = db();
    $uid = (int)($_SESSION['uid'] ?? 0);
    $guest_sid = (string)($_SESSION['guest_sid'] ?? ($_COOKIE['vg_guest'] ?? ''));
    
    if ($guest_sid === '') {
        $guest_sid = bin2hex(random_bytes(16));
        $_SESSION['guest_sid'] = $guest_sid;
        if (!headers_sent()) {
            setcookie('vg_guest', $guest_sid, [
                'expires' => time() + 86400*30,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        logCart('NEW_GUEST_SID', ['guest_sid' => $guest_sid]);
    }
    
    $cartId = null;
    if ($uid > 0) {
        $st = $pdo->prepare("SELECT id FROM carts WHERE user_id=? ORDER BY id DESC LIMIT 1");
        $st->execute([$uid]);
        $cartId = $st->fetchColumn();
    }
    
    if (!$cartId && $guest_sid !== '') {
        $st = $pdo->prepare("SELECT id FROM carts WHERE guest_sid=? ORDER BY id DESC LIMIT 1");
        $st->execute([$guest_sid]);
        $cartId = $st->fetchColumn();
    }
    
    if (!$cartId) {
        $st = $pdo->prepare("INSERT INTO carts (user_id, guest_sid, currency, created_at, updated_at) VALUES (?, ?, 'CRC', datetime('now'), datetime('now'))");
        $st->execute([$uid > 0 ? $uid : null, $guest_sid]);
        $cartId = (int)$pdo->lastInsertId();
        logCart('NEW_CART', ['cart_id' => $cartId, 'uid' => $uid, 'guest_sid' => $guest_sid]);
    }
    
    return (int)$cartId;
}

// ============= SYNC: Sincronización inteligente BD ↔ Sesión =============
function smart_sync_cart($cart_id) {
    $pdo = db();
    
    // 1. Obtener items de la BD
    $sql = "
        SELECT 
            ci.sale_id,
            ci.product_id,
            ci.qty,
            ci.unit_price,
            p.name AS product_name,
            p.image AS product_image,
            p.currency,
            s.title AS sale_title,
            s.affiliate_id
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        LEFT JOIN sales s ON s.id = ci.sale_id
        WHERE ci.cart_id = ?
        ORDER BY ci.sale_id, p.name
    ";
    
    $st = $pdo->prepare($sql);
    $st->execute([$cart_id]);
    $dbRows = $st->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Construir mapa de sale_ids en BD
    $dbSaleIds = [];
    foreach ($dbRows as $r) {
        $dbSaleIds[(int)$r['sale_id']] = true;
    }
    
    // 3. Obtener grupos actuales de la sesión
    $sessionGroups = $_SESSION['cart']['groups'] ?? [];
    
    // 4. Eliminar de sesión los sale_ids que YA NO están en BD (fueron pagados)
    $cleanedGroups = [];
    foreach ($sessionGroups as $group) {
        $saleId = (int)($group['sale_id'] ?? 0);
        if (isset($dbSaleIds[$saleId])) {
            // Este sale_id todavía existe en BD, mantenerlo
            $cleanedGroups[] = $group;
        } else {
            logCart('REMOVED_PAID_SALE', ['sale_id' => $saleId]);
        }
    }
    
    // 5. Si la BD tiene items, reconstruir desde BD (puede haber nuevos productos)
    if (count($dbRows) > 0) {
        $groups = [];
        foreach ($dbRows as $r) {
            $sale_id = (int)$r['sale_id'];
            if (!isset($groups[$sale_id])) {
                $groups[$sale_id] = [
                    'sale_id' => $sale_id,
                    'sale_title' => $r['sale_title'] ?? 'Espacio #'.$sale_id,
                    'affiliate_id' => (int)($r['affiliate_id'] ?? 0),
                    'currency' => strtoupper($r['currency'] ?? 'CRC'),
                    'items' => [],
                    'totals' => ['count' => 0, 'subtotal' => 0, 'tax_total' => 0, 'grand_total' => 0]
                ];
            }
            
            $qty = (float)$r['qty'];
            $unit = (float)$r['unit_price'];
            $line = $qty * $unit;
            $tax = $line * 0.13;
            
            $img = $r['product_image'] ?? null;
            if ($img) $img = 'uploads/' . ltrim($img, '/');
            
            $groups[$sale_id]['items'][] = [
                'product_id' => (int)$r['product_id'],
                'product_name' => $r['product_name'] ?? 'Producto',
                'product_image_url' => $img,
                'qty' => $qty,
                'unit_price' => $unit,
                'line_total' => $line,
            ];
            
            $groups[$sale_id]['totals']['count'] += $qty;
            $groups[$sale_id]['totals']['subtotal'] += $line;
            $groups[$sale_id]['totals']['tax_total'] += $tax;
            $groups[$sale_id]['totals']['grand_total'] += $line + $tax;
        }
        
        $_SESSION['cart']['groups'] = array_values($groups);
        
        logCart('SMART_SYNC_COMPLETE', [
            'cart_id' => $cart_id,
            'groups_count' => count($groups),
            'total_items' => count($dbRows)
        ]);
    } else {
        // BD vacía, usar grupos limpios de sesión
        $_SESSION['cart']['groups'] = $cleanedGroups;
        
        logCart('SMART_SYNC_SESSION_ONLY', [
            'groups_count' => count($cleanedGroups)
        ]);
    }
}

// ============= GET =============
if (($_GET['action'] ?? '') === 'get') {
    logCart('GET_CART_START');
    
    try {
        $cart_id = get_or_create_cart_id();
        
        // ✅ SIEMPRE hacer sincronización inteligente
        smart_sync_cart($cart_id);
        
        $groups = $_SESSION['cart']['groups'] ?? [];
        $total_count = 0;
        $grand_total = 0;
        $currency = 'CRC';
        
        foreach ($groups as $g) {
            $total_count += (int)($g['totals']['count'] ?? 0);
            $grand_total += (float)($g['totals']['grand_total'] ?? 0);
            if (!empty($g['currency'])) $currency = $g['currency'];
        }
        
        echo json_encode([
            'ok' => true,
            'groups' => $groups,
            'cart_count' => $total_count,
            'total' => $grand_total,
            'currency' => $currency,
        ], JSON_UNESCAPED_UNICODE);
        
    } catch(Throwable $e) {
        logCart('GET_ERROR', ['msg' => $e->getMessage()]);
        echo json_encode(['ok' => false, 'error' => 'Error al cargar carrito']);
    }
    exit;
}

// ============= ADD =============
if (($_GET['action'] ?? '') === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    logCart('ADD_START');
    
    $raw = file_get_contents('php://input');
    logCart('ADD_RAW', ['raw' => $raw]);
    
    $payload = json_decode($raw, true);
    if (!$payload) {
        logCart('ADD_ERROR', ['msg' => 'Invalid JSON']);
        echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
        exit;
    }
    
    $pid = (int)($payload['product_id'] ?? 0);
    $qty = (float)($payload['qty'] ?? 1);
    $unit = (float)($payload['unit_price'] ?? 0);
    $sale_id = (int)($payload['sale_id'] ?? 0);
    
    logCart('ADD_PARAMS_RECEIVED', ['pid' => $pid, 'sale_id' => $sale_id, 'qty' => $qty, 'unit' => $unit]);
    
    if ($pid <= 0 || $qty <= 0 || $unit < 0) {
        logCart('ADD_ERROR', ['msg' => 'Invalid params', 'pid' => $pid, 'qty' => $qty, 'unit' => $unit]);
        echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
        exit;
    }
    
    try {
        $pdo = db();
        
        if ($sale_id <= 0) {
            $st = $pdo->prepare("SELECT sale_id FROM products WHERE id=? LIMIT 1");
            $st->execute([$pid]);
            $sale_id = (int)$st->fetchColumn();
            logCart('ADD_SALE_ID_FROM_DB', ['product_id' => $pid, 'sale_id' => $sale_id]);
        }
        
        if ($sale_id <= 0) {
            logCart('ADD_ERROR', ['msg' => 'No se pudo determinar sale_id', 'pid' => $pid]);
            echo json_encode(['ok' => false, 'error' => 'Producto no válido']);
            exit;
        }
        
        $cart_id = get_or_create_cart_id();
        
        $st = $pdo->prepare("SELECT id, qty FROM cart_items WHERE cart_id=? AND product_id=? AND sale_id=? LIMIT 1");
        $st->execute([$cart_id, $pid, $sale_id]);
        $existing = $st->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            $new_qty = (float)$existing['qty'] + $qty;
            $st = $pdo->prepare("UPDATE cart_items SET qty=? WHERE id=?");
            $st->execute([$new_qty, $existing['id']]);
            logCart('ADD_UPDATED', ['cart_item_id' => $existing['id'], 'new_qty' => $new_qty]);
        } else {
            $st = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, sale_id, qty, unit_price) VALUES (?, ?, ?, ?, ?)");
            $st->execute([$cart_id, $pid, $sale_id, $qty, $unit]);
            $item_id = $pdo->lastInsertId();
            logCart('ADD_INSERTED', ['cart_item_id' => $item_id, 'cart_id' => $cart_id, 'product_id' => $pid, 'sale_id' => $sale_id]);
        }
        
        $st = $pdo->prepare("UPDATE carts SET updated_at=datetime('now') WHERE id=?");
        $st->execute([$cart_id]);
        
        smart_sync_cart($cart_id);
        
        $total_count = 0;
        foreach (($_SESSION['cart']['groups'] ?? []) as $g) {
            $total_count += (int)($g['totals']['count'] ?? 0);
        }
        
        echo json_encode([
            'ok' => true,
            'cart_count' => $total_count,
            'message' => 'Producto agregado'
        ], JSON_UNESCAPED_UNICODE);
        
    } catch(Throwable $e) {
        logCart('ADD_EXCEPTION', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        echo json_encode(['ok' => false, 'error' => 'Error al agregar producto: ' . $e->getMessage()]);
    }
    exit;
}

// ============= UPDATE =============
if (($_GET['action'] ?? '') === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    
    $pid = (int)($payload['product_id'] ?? 0);
    $sale_id = (int)($payload['sale_id'] ?? 0);
    $qty = (float)($payload['qty'] ?? 0);
    
    if ($pid <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
        exit;
    }
    
    try {
        $pdo = db();
        $cart_id = get_or_create_cart_id();
        
        if ($sale_id <= 0) {
            $st = $pdo->prepare("SELECT sale_id FROM cart_items WHERE cart_id=? AND product_id=? LIMIT 1");
            $st->execute([$cart_id, $pid]);
            $sale_id = (int)$st->fetchColumn();
        }
        
        if ($qty <= 0) {
            if ($sale_id > 0) {
                $st = $pdo->prepare("DELETE FROM cart_items WHERE cart_id=? AND product_id=? AND sale_id=?");
                $st->execute([$cart_id, $pid, $sale_id]);
            } else {
                $st = $pdo->prepare("DELETE FROM cart_items WHERE cart_id=? AND product_id=?");
                $st->execute([$cart_id, $pid]);
            }
            logCart('UPDATE_DELETED', ['pid' => $pid, 'sale_id' => $sale_id]);
        } else {
            if ($sale_id > 0) {
                $st = $pdo->prepare("UPDATE cart_items SET qty=? WHERE cart_id=? AND product_id=? AND sale_id=?");
                $st->execute([$qty, $cart_id, $pid, $sale_id]);
            } else {
                $st = $pdo->prepare("UPDATE cart_items SET qty=? WHERE cart_id=? AND product_id=?");
                $st->execute([$qty, $cart_id, $pid]);
            }
            logCart('UPDATE_QTY', ['pid' => $pid, 'sale_id' => $sale_id, 'qty' => $qty]);
        }
        
        smart_sync_cart($cart_id);
        
        $total_count = 0;
        foreach (($_SESSION['cart']['groups'] ?? []) as $g) {
            $total_count += (int)($g['totals']['count'] ?? 0);
        }
        
        echo json_encode(['ok' => true, 'cart_count' => $total_count]);
        
    } catch(Throwable $e) {
        logCart('UPDATE_ERROR', ['msg' => $e->getMessage()]);
        echo json_encode(['ok' => false, 'error' => 'Error al actualizar']);
    }
    exit;
}

// ============= REMOVE =============
if (($_GET['action'] ?? '') === 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    
    $pid = (int)($payload['product_id'] ?? 0);
    $sale_id = (int)($payload['sale_id'] ?? 0);
    
    if ($pid <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
        exit;
    }
    
    try {
        $pdo = db();
        $cart_id = get_or_create_cart_id();
        
        if ($sale_id > 0) {
            $st = $pdo->prepare("DELETE FROM cart_items WHERE cart_id=? AND product_id=? AND sale_id=?");
            $st->execute([$cart_id, $pid, $sale_id]);
        } else {
            $st = $pdo->prepare("DELETE FROM cart_items WHERE cart_id=? AND product_id=?");
            $st->execute([$cart_id, $pid]);
        }
        
        logCart('REMOVE', ['pid' => $pid, 'sale_id' => $sale_id]);
        
        smart_sync_cart($cart_id);
        
        $total_count = 0;
        foreach (($_SESSION['cart']['groups'] ?? []) as $g) {
            $total_count += (int)($g['totals']['count'] ?? 0);
        }
        
        echo json_encode(['ok' => true, 'cart_count' => $total_count]);
        
    } catch(Throwable $e) {
        logCart('REMOVE_ERROR', ['msg' => $e->getMessage()]);
        echo json_encode(['ok' => false, 'error' => 'Error al eliminar']);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Acción no válida']);