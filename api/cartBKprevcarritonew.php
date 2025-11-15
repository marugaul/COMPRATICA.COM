<?php
declare(strict_types=1);

// Usa SIEMPRE la misma sesión que el sitio
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// ---------- Encabezados útiles ----------
if (!headers_sent()) {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
}

// ---------- Bitácora común ----------
function cart_log(string $msg, string $prefix='API'): void {
  $logDir = __DIR__ . '/../logs';
  if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
  $line = sprintf("[%s] %s %s | IP:%s%s",
    date('Y-m-d H:i:s'), $prefix, $msg, $_SERVER['REMOTE_ADDR'] ?? 'N/A', PHP_EOL
  );
  @file_put_contents($logDir . '/error_cart.log', $line, FILE_APPEND);
}

// ---------- Helpers locales ----------
function ensure_json(): void {
  if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
}
function current_user_id(): ?int {
  return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
}
function csrf_token_val(): string {
  return $_SESSION['csrf_token'] ?? '';
}

/** Lee el cuerpo crudo UNA SOLA VEZ y lo deja en $GLOBALS['__RAW_BODY__'] */
function read_raw_once(): string {
  if (!isset($GLOBALS['__RAW_BODY__'])) {
    $GLOBALS['__RAW_BODY__'] = file_get_contents('php://input') ?: '';
  }
  return $GLOBALS['__RAW_BODY__'];
}

/** CSRF con soporte de header/body/json y double-submit cookie (XSRF-TOKEN) */
function csrf_check(): void {
  // 1) Token del request
  $tokHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  $tokBody   = $_POST['csrf_token'] ?? '';
  $t = '';

  if ($tokHeader !== '') {
    $t = $tokHeader;
  } elseif ($tokBody !== '') {
    $t = $tokBody;
  } else {
    $raw = read_raw_once();
    if ($raw !== '') {
      $j = json_decode($raw, true);
      if (is_array($j) && !empty($j['csrf_token'])) {
        $t = (string)$j['csrf_token'];
      }
    }
  }

  // 2) Token esperado: sesión y/o cookie
  $sessTok   = $_SESSION['csrf_token'] ?? '';
  $cookieTok = $_COOKIE['XSRF-TOKEN'] ?? '';

  // 3) Acepta si coincide con sesión O si cookie == header/body (double-submit)
  $ok = false;
  if ($t !== '') {
    if ($sessTok !== '' && hash_equals($sessTok, $t)) $ok = true;
    if (!$ok && $cookieTok !== '' && hash_equals($cookieTok, $t)) $ok = true;
  }

  // DEBUG (recorta a 10 chars)
  cart_log(
    'DEBUG CSRF2: sid='.session_id().
    ' hdr='.substr($tokHeader,0,10).
    ' body='.substr($tokBody,0,10).
    ' t='.substr($t,0,10).
    ' sessTok='.substr($sessTok,0,10).
    ' cookieTok='.substr($cookieTok,0,10),
    'API'
  );

  if (!$ok) {
    http_response_code(419);
    echo json_encode(['ok'=>false,'error'=>'invalid_csrf']);
    exit;
  }
}

// ---------- Acciones utilitarias ----------
ensure_json();
$pdo = db();

// JS log desde frontend
if (isset($_GET['action']) && $_GET['action'] === 'log_error') {
  try {
    $raw = read_raw_once();
    $data = json_decode($raw, true);
    $msg  = $data['msg'] ?? 'Error sin mensaje';
    $product = $data['product'] ?? 'N/A';
    cart_log("JS_LOG ".trim($msg)." | product:".$product, 'API');
    echo json_encode(['ok'=>true]);
    exit;
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    exit;
  }
}

// Obtener CSRF vigente
if (isset($_GET['action']) && $_GET['action'] === 'get_csrf') {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  echo json_encode(['ok' => true, 'csrf_token' => $_SESSION['csrf_token']]);
  exit;
}

// ---------- DB: crear tablas si no existen ----------
$pdo->exec("
PRAGMA foreign_keys=ON;
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT UNIQUE,
  name TEXT,
  phone TEXT,
  password_hash TEXT,
  status TEXT DEFAULT 'active',
  created_at TEXT DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS user_social_accounts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  provider TEXT NOT NULL,
  provider_user_id TEXT NOT NULL,
  email TEXT,
  created_at TEXT DEFAULT (datetime('now')),
  UNIQUE(provider, provider_user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS carts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER,
  guest_sid TEXT,
  currency TEXT DEFAULT 'CRC',
  created_at TEXT DEFAULT (datetime('now')),
  updated_at TEXT DEFAULT (datetime('now')),
  UNIQUE(user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_carts_guest ON carts(guest_sid);
CREATE TABLE IF NOT EXISTS cart_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  cart_id INTEGER NOT NULL,
  product_id INTEGER NOT NULL,
  variant_id INTEGER,
  qty INTEGER NOT NULL,
  unit_price NUMERIC NOT NULL,
  tax_rate NUMERIC DEFAULT 0.0,
  created_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_items_cart ON cart_items(cart_id);
");

// ---------- Carrito helpers ----------
function get_guest_sid(): string {
  if (empty($_SESSION['guest_sid'])) $_SESSION['guest_sid'] = bin2hex(random_bytes(16));
  return (string)$_SESSION['guest_sid'];
}

function find_or_create_cart(PDO $db): array {
  $uid = current_user_id();
  if ($uid) {
    $st = $db->prepare("SELECT * FROM carts WHERE user_id=?");
    $st->execute([$uid]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if (!$c) {
      $db->prepare("INSERT INTO carts(user_id,currency) VALUES(?, 'CRC')")->execute([$uid]);
      $c = ['id'=>$db->lastInsertId(),'user_id'=>$uid,'currency'=>'CRC'];
    }
    return $c;
  } else {
    $sid = get_guest_sid();
    $st = $db->prepare("SELECT * FROM carts WHERE guest_sid=?");
    $st->execute([$sid]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if (!$c) {
      $db->prepare("INSERT INTO carts(guest_sid,currency) VALUES(?, 'CRC')")->execute([$sid]);
      $c = ['id'=>$db->lastInsertId(),'guest_sid'=>$sid,'currency'=>'CRC'];
    }
    return $c;
  }
}

// ---------- Router ----------
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'get';

switch ($method . ':' . $action) {
  case 'GET:get':
  case 'GET:':
    $cart = find_or_create_cart($pdo);
    $st = $pdo->prepare("SELECT * FROM cart_items WHERE cart_id=?");
    $st->execute([$cart['id']]);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok'=>true,'cart'=>$cart,'items'=>$items]);
    break;

  case 'POST:add':
    csrf_check();
    // Acepta JSON o x-www-form-urlencoded
    $raw = read_raw_once();
    $payload = json_decode($raw, true);
    if (!is_array($payload) || empty($payload)) $payload = $_POST;

    $product = (int)($payload['product_id'] ?? 0);
    $qty     = max(1, (int)($payload['qty'] ?? 1));
    $variant = isset($payload['variant_id']) ? (int)$payload['variant_id'] : null;
    $price   = (float)($payload['unit_price'] ?? 0.0);
    $tax     = (float)($payload['tax_rate'] ?? 0.0);

    if ($product<=0 || $price<=0) {
      cart_log("params invalidos product=$product price=$price qty=$qty",'API_ADD');
      echo json_encode(['ok'=>false,'error'=>'invalid_params']); 
      break;
    }

    $cart = find_or_create_cart($pdo);

    // Fusiona si existe
    $sel = $pdo->prepare("SELECT id FROM cart_items WHERE cart_id=? AND product_id=? AND (variant_id IS ? OR variant_id=?)");
    $sel->execute([$cart['id'],$product,$variant,$variant]);
    $exist = $sel->fetchColumn();
    if ($exist) {
      $upd = $pdo->prepare("UPDATE cart_items SET qty=qty+? WHERE id=?");
      $upd->execute([$qty,$exist]);
    } else {
      $ins = $pdo->prepare("INSERT INTO cart_items(cart_id,product_id,variant_id,qty,unit_price,tax_rate) VALUES(?,?,?,?,?,?)");
      $ins->execute([$cart['id'],$product,$variant,$qty,$price,$tax]);
    }
    echo json_encode(['ok'=>true]);
    break;

  case 'PATCH:update':
    csrf_check();
    parse_str(read_raw_once(), $payload);
    $id  = (int)($payload['id'] ?? 0);
    $qty = max(1, (int)($payload['qty'] ?? 1));
    if ($id<=0) { echo json_encode(['ok'=>false,'error'=>'invalid_id']); break; }
    $cart = find_or_create_cart($pdo);
    $st = $pdo->prepare("UPDATE cart_items SET qty=? WHERE id=? AND cart_id=?");
    $st->execute([$qty,$id,$cart['id']]);
    echo json_encode(['ok'=>true]);
    break;

  case 'DELETE:remove':
    csrf_check();
    parse_str(read_raw_once(), $payload);
    $id  = (int)($payload['id'] ?? 0);
    if ($id<=0) { echo json_encode(['ok'=>false,'error'=>'invalid_id']); break; }
    $cart = find_or_create_cart($pdo);
    $st = $pdo->prepare("DELETE FROM cart_items WHERE id=? AND cart_id=?");
    $st->execute([$id,$cart['id']]);
    echo json_encode(['ok'=>true]);
    break;

  default:
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'not_found']);
}
