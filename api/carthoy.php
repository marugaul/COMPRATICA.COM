<?php
declare(strict_types=1);

// Sesión y config del sitio
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// ---------- Encabezados útiles ----------
if (!headers_sent()) {
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
}

// ---------- Bitácora ----------
function cart_log(string $msg, string $prefix='API'): void {
  $logDir = __DIR__ . '/../logs';
  if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
  $line = sprintf("[%s] %s %s | IP:%s | SID:%s | vg_guest:%s%s",
    date('Y-m-d H:i:s'), $prefix, $msg,
    $_SERVER['REMOTE_ADDR'] ?? 'N/A',
    session_id() ?: 'N/A',
    $_COOKIE['vg_guest'] ?? '-',
    PHP_EOL
  );
  @file_put_contents($logDir . '/error_cart.log', $line, FILE_APPEND | LOCK_EX);
}

// ---------- Helpers comunes ----------
function ensure_json(): void {
  if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
}
function current_user_id(): ?int {
  return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
}
/** Lee el cuerpo crudo UNA SOLA VEZ */
function read_raw_once(): string {
  if (!isset($GLOBALS['__RAW_BODY__'])) {
    $GLOBALS['__RAW_BODY__'] = file_get_contents('php://input') ?: '';
  }
  return $GLOBALS['__RAW_BODY__'];
}

/** Cookie persistente para invitados: vg_guest (1 año, dominio .compratica.com) */
function get_or_set_guest_cookie(): string {
  $val = $_COOKIE['vg_guest'] ?? '';
  $needSet = false;
  if (!is_string($val) || strlen($val) < 16) {
    $val = bin2hex(random_bytes(16));
    $needSet = true;
  }
  if ($needSet) {
    // mismo dominio que definimos en config.php para la sesión
    $cookieDomain = '.compratica.com';
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    // setcookie con opciones (PHP 7.3+)
    setcookie('vg_guest', $val, [
      'expires'  => time() + 31536000,
      'path'     => '/',
      'domain'   => $cookieDomain,
      'secure'   => $isHttps,
      'httponly' => false,   // debe ser legible por JS si algún día lo necesitas
      'samesite' => 'Lax',
    ]);
    // también reflejar en $_COOKIE para esta ejecución
    $_COOKIE['vg_guest'] = $val;
  }
  return $val;
}

/** CSRF con header/body/json y double-submit cookie (XSRF-TOKEN) */
function csrf_check(): void {
  $tokHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  $tokBody   = $_POST['csrf_token'] ?? '';
  $t = $tokHeader !== '' ? $tokHeader : $tokBody;
  if ($t === '') {
    $raw = read_raw_once();
    if ($raw !== '') {
      $j = json_decode($raw, true);
      if (is_array($j) && !empty($j['csrf_token'])) $t = (string)$j['csrf_token'];
    }
  }
  $sessTok   = $_SESSION['csrf_token'] ?? '';
  $cookieTok = $_COOKIE['XSRF-TOKEN'] ?? '';
  $ok = ($t !== '') && (
    ($sessTok !== '' && hash_equals($sessTok, $t)) ||
    ($cookieTok !== '' && hash_equals($cookieTok, $t))
  );
  if (!$ok) {
    http_response_code(419);
    echo json_encode(['ok'=>false,'error'=>'invalid_csrf']);
    exit;
  }
}

// ---------- Iniciar ----------
ensure_json();
$pdo = db();
$guestCookie = get_or_set_guest_cookie(); // ← clave persistente para el invitado

// JS log desde frontend
if (isset($_GET['action']) && $_GET['action'] === 'log_error') {
  try {
    $raw = read_raw_once();
    $data = json_decode($raw, true);
    $msg  = $data['msg'] ?? 'Error sin mensaje';
    $product = $data['product'] ?? 'N/A';
    cart_log("JS_LOG ".trim($msg)." | product:".$product, 'API');
    echo json_encode(['ok'=>true]); exit;
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

// ---------- DB bootstrap mínimo ----------
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

// ---------- helpers por ESPACIO (sale_id) ----------
function fetch_sale_title(PDO $db, int $sale_id): string {
  $st = $db->prepare("SELECT title FROM sales WHERE id=?");
  $st->execute([$sale_id]);
  return (string)($st->fetchColumn() ?: '');
}

/** Adopta carritos antiguos (por SID de sesión) al nuevo vg_guest si aplica */
function adopt_session_cart_to_cookie(PDO $db, string $cookieKey): void {
  $sessSid = session_id();
  if (!$sessSid || !$cookieKey || $sessSid === $cookieKey) return;

  // ¿Existe algún cart con guest_sid = SID actual?
  $st = $db->prepare("SELECT id FROM carts WHERE guest_sid=? LIMIT 1");
  $st->execute([$sessSid]);
  $oldCart = $st->fetch(PDO::FETCH_ASSOC);
  if ($oldCart) {
    // ¿Ya existe uno con cookieKey? Si sí, migrar items de oldCart->cookieCart y luego borrar oldCart
    $st2 = $db->prepare("SELECT id FROM carts WHERE guest_sid=? LIMIT 1");
    $st2->execute([$cookieKey]);
    $newCart = $st2->fetch(PDO::FETCH_ASSOC);

    if ($newCart) {
      // mover items
      $db->prepare("UPDATE cart_items SET cart_id=? WHERE cart_id=?")->execute([$newCart['id'], $oldCart['id']]);
      $db->prepare("DELETE FROM carts WHERE id=?")->execute([$oldCart['id']]);
      cart_log("ADOPT merge oldCart=".$oldCart['id']." -> newCart=".$newCart['id'], 'API_ADOPT');
    } else {
      // simplemente cambiar guest_sid
      $db->prepare("UPDATE carts SET guest_sid=? WHERE id=?")->execute([$cookieKey, $oldCart['id']]);
      cart_log("ADOPT switch cart=".$oldCart['id']." guest_sid=".$cookieKey, 'API_ADOPT');
    }
  }
}

/** Busca/crea carrito lógico para este sale_id usando:
 *  - usuario logueado (user_id) 1:1
 *  - invitado: guest_sid = vg_guest (no la SID de sesión PHP)
 */
function find_or_create_cart_for_sale(PDO $db, int $sale_id, string $guestCookie): array {
  if ($sale_id <= 0) {
    cart_log("sale_id faltante o inválido en find_or_create_cart_for_sale", 'API_ERR');
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'sale_required']);
    exit;
  }
  $uid = current_user_id();
  if ($uid) {
    $st = $db->prepare("
      SELECT c.*
      FROM carts c
      JOIN cart_items ci ON ci.cart_id = c.id
      JOIN products p ON p.id = ci.product_id
      WHERE c.user_id = ? AND p.sale_id = ?
      LIMIT 1
    ");
    $st->execute([$uid, $sale_id]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if ($c) return $c;
    $db->prepare("INSERT INTO carts(user_id,currency) VALUES(?, 'CRC')")->execute([$uid]);
    return ['id'=>$db->lastInsertId(),'user_id'=>$uid,'currency'=>'CRC'];
  } else {
    adopt_session_cart_to_cookie($db, $guestCookie);
    $st = $db->prepare("
      SELECT c.*
      FROM carts c
      JOIN cart_items ci ON ci.cart_id = c.id
      JOIN products p ON p.id = ci.product_id
      WHERE c.guest_sid = ? AND p.sale_id = ?
      LIMIT 1
    ");
    $st->execute([$guestCookie, $sale_id]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if ($c) return $c;

    $db->prepare("INSERT INTO carts(guest_sid,currency) VALUES(?, 'CRC')")->execute([$guestCookie]);
    return ['id'=>$db->lastInsertId(),'guest_sid'=>$guestCookie,'currency'=>'CRC'];
  }
}

/** Verifica que un product_id pertenezca a sale_id. */
function assert_product_in_sale(PDO $db, int $product_id, int $sale_id): void {
  $st = $db->prepare("SELECT sale_id FROM products WHERE id=?");
  $st->execute([$product_id]);
  $sale = (int)$st->fetchColumn();
  if ($sale !== $sale_id) {
    cart_log("Intento de mezclar espacios: product_id=$product_id pertenece a sale_id=$sale; pedido sale_id=$sale_id", 'API_ERR');
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'cross_sale_not_allowed']);
    exit;
  }
}

function read_sale_id(): int {
  $sale_id = (int)($_GET['sale_id'] ?? 0);
  if ($sale_id <= 0) $sale_id = (int)($_POST['sale_id'] ?? 0);
  if ($sale_id <= 0) {
    $raw = read_raw_once();
    if ($raw !== '') {
      $j = json_decode($raw, true);
      if (is_array($j) && !empty($j['sale_id'])) $sale_id = (int)$j['sale_id'];
    }
  }
  return $sale_id;
}

// Métricas de depuración cuando carrito viene vacío
function debug_counts(PDO $db, int $sale_id, string $guestCookie): array {
  $uid = current_user_id();
  $out = ['uid'=>$uid, 'vg_guest'=>$guestCookie, 'sale_id'=>$sale_id];

  if ($uid) {
    $st = $db->prepare("
      SELECT COUNT(*)
      FROM cart_items ci
      JOIN carts c ON c.id=ci.cart_id
      JOIN products p ON p.id=ci.product_id
      WHERE c.user_id=? AND p.sale_id=?
    ");
    $st->execute([$uid, $sale_id]);
    $out['items_for_sale_user'] = (int)$st->fetchColumn();

    $st = $db->prepare("SELECT COUNT(*) FROM cart_items ci JOIN carts c ON c.id=ci.cart_id WHERE c.user_id=?");
    $st->execute([$uid]);
    $out['items_all_user'] = (int)$st->fetchColumn();
  } else {
    $st = $db->prepare("
      SELECT COUNT(*)
      FROM cart_items ci
      JOIN carts c ON c.id=ci.cart_id
      JOIN products p ON p.id=ci.product_id
      WHERE c.guest_sid=? AND p.sale_id=?
    ");
    $st->execute([$guestCookie, $sale_id]);
    $out['items_for_sale_guest'] = (int)$st->fetchColumn();

    $st = $db->prepare("SELECT COUNT(*) FROM cart_items ci JOIN carts c ON c.id=ci.cart_id WHERE c.guest_sid=?");
    $st->execute([$guestCookie]);
    $out['items_all_guest'] = (int)$st->fetchColumn();
  }
  return $out;
}

// ---------- Router ----------
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'get';

switch ($method . ':' . $action) {
  case 'GET:get':
  case 'GET:':
    $sale_id = read_sale_id();
    if ($sale_id <= 0) {
      cart_log("GET carrito sin sale_id", 'API_WARN');
      echo json_encode(['ok'=>true, 'cart'=>null, 'items'=>[], 'sale_id'=>0, 'sale_title'=>'', 'warning'=>'sale_missing']);
      break;
    }

    $cart = find_or_create_cart_for_sale($pdo, $sale_id, $guestCookie);

    $st = $pdo->prepare("
      SELECT ci.id, ci.product_id, ci.variant_id, ci.qty, ci.unit_price, ci.tax_rate, ci.created_at,
             p.name AS product_name, p.image AS product_image, p.currency AS product_currency, p.sale_id
      FROM cart_items ci
      JOIN products p ON p.id = ci.product_id
      WHERE ci.cart_id=? AND p.sale_id=?
      ORDER BY ci.id DESC
    ");
    $st->execute([$cart['id'], $sale_id]);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);

    $sale_title = fetch_sale_title($pdo, $sale_id);

    if (count($items) === 0) {
      $dbg = debug_counts($pdo, $sale_id, $guestCookie);
      cart_log("GET vacío sale_id=$sale_id title=\"".$sale_title."\" dbg=".json_encode($dbg), 'API_INFO');
    }

    echo json_encode(['ok'=>true,'cart'=>$cart,'items'=>$items,'sale_id'=>$sale_id,'sale_title'=>$sale_title]);
    break;

  case 'POST:add':
    csrf_check();
    $sale_id = read_sale_id();
    if ($sale_id <= 0) {
      cart_log("ADD sin sale_id", 'API_ERR');
      http_response_code(400);
      echo json_encode(['ok'=>false,'error'=>'sale_required']);
      break;
    }

    $raw = read_raw_once();
    $payload = json_decode($raw, true);
    if (!is_array($payload) || empty($payload)) $payload = $_POST;

    $product = (int)($payload['product_id'] ?? 0);
    $qty     = max(1, (int)($payload['qty'] ?? 1));
    $variant = isset($payload['variant_id']) ? (int)$payload['variant_id'] : null;
    $price   = (float)($payload['unit_price'] ?? 0.0);
    $tax     = (float)($payload['tax_rate'] ?? 0.0);

    if ($product<=0 || $price<=0) {
      cart_log("params invalidos product=$product price=$price qty=$qty sale_id=$sale_id",'API_ADD');
      echo json_encode(['ok'=>false,'error'=>'invalid_params']); 
      break;
    }

    assert_product_in_sale($pdo, $product, $sale_id);
    $cart = find_or_create_cart_for_sale($pdo, $sale_id, $guestCookie);

    $sel = $pdo->prepare("SELECT id FROM cart_items WHERE cart_id=? AND product_id=? AND (variant_id IS ? OR variant_id=?)");
    $sel->execute([$cart['id'],$product,$variant,$variant]);
    $exist = $sel->fetchColumn();
    if ($exist) {
      $upd = $pdo->prepare("UPDATE cart_items SET qty=qty+? WHERE id=?");
      $upd->execute([$qty,$exist]);
      cart_log("ADD merged item id=$exist cart=".$cart['id']." sale_id=$sale_id qty+=$qty product=$product", 'API_ADD');
    } else {
      $ins = $pdo->prepare("INSERT INTO cart_items(cart_id,product_id,variant_id,qty,unit_price,tax_rate) VALUES(?,?,?,?,?,?)");
      $ins->execute([$cart['id'],$product,$variant,$qty,$price,$tax]);
      cart_log("ADD new item cart=".$cart['id']." sale_id=$sale_id product=$product qty=$qty price=$price", 'API_ADD');
    }
    echo json_encode(['ok'=>true]);
    break;

  case 'PATCH:update':
    csrf_check();
    $sale_id = read_sale_id();
    if ($sale_id <= 0) {
      cart_log("UPDATE sin sale_id", 'API_ERR');
      http_response_code(400);
      echo json_encode(['ok'=>false,'error'=>'sale_required']);
      break;
    }

    parse_str(read_raw_once(), $payload);
    $id  = (int)($payload['id'] ?? 0);
    $qty = max(1, (int)($payload['qty'] ?? 1));
    if ($id<=0) { echo json_encode(['ok'=>false,'error'=>'invalid_id']); break; }

    $cart = find_or_create_cart_for_sale($pdo, $sale_id, $guestCookie);

    $st = $pdo->prepare("SELECT p.sale_id FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE ci.id=? AND ci.cart_id=?");
    $st->execute([$id,$cart['id']]);
    $rowSale = (int)$st->fetchColumn();
    if ($rowSale !== $sale_id) {
      cart_log("UPDATE item=$id no pertenece a sale=$sale_id", 'API_ERR');
      http_response_code(400);
      echo json_encode(['ok'=>false,'error'=>'wrong_sale_item']);
      break;
    }

    $st = $pdo->prepare("UPDATE cart_items SET qty=? WHERE id=? AND cart_id=?");
    $st->execute([$qty,$id,$cart['id']]);
    cart_log("UPDATE ok item=$id qty=$qty sale_id=$sale_id cart=".$cart['id'], 'API_UPD');
    echo json_encode(['ok'=>true]);
    break;

  case 'DELETE:remove':
    csrf_check();
    $sale_id = read_sale_id();
    if ($sale_id <= 0) {
      cart_log("REMOVE sin sale_id", 'API_ERR');
      http_response_code(400);
      echo json_encode(['ok'=>false,'error'=>'sale_required']);
      break;
    }

    parse_str(read_raw_once(), $payload);
    $id  = (int)($payload['id'] ?? 0);
    if ($id<=0) { echo json_encode(['ok'=>false,'error'=>'invalid_id']); break; }

    $cart = find_or_create_cart_for_sale($pdo, $sale_id, $guestCookie);

    $st = $pdo->prepare("SELECT p.sale_id FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE ci.id=? AND ci.cart_id=?");
    $st->execute([$id,$cart['id']]);
    $rowSale = (int)$st->fetchColumn();
    if ($rowSale !== $sale_id) {
      cart_log("REMOVE item=$id no pertenece a sale=$sale_id", 'API_ERR');
      http_response_code(400);
      echo json_encode(['ok'=>false,'error'=>'wrong_sale_item']);
      break;
    }

    $st = $pdo->prepare("DELETE FROM cart_items WHERE id=? AND cart_id=?");
    $st->execute([$id,$cart['id']]);
    cart_log("REMOVE ok item=$id sale_id=$sale_id cart=".$cart['id'], 'API_DEL');
    echo json_encode(['ok'=>true]);
    break;

  default:
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'not_found']);
}
