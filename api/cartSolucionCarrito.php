<?php
declare(strict_types=1);

/**
 * API de carrito
 * - GET?action=get      → devuelve carrito agrupado por espacio (sale_id) con totales y cart_count
 * - POST?action=add     → agrega producto (usa precio de BD), devuelve ok y cart_count
 * - PATCH?action=update → actualiza cantidad
 * - DELETE?action=remove→ elimina ítem
 * - POST?action=log_error → bitácora desde el front
 */

$APP_BASE = dirname(__DIR__); // /home/comprati/public_html

require_once $APP_BASE . '/inc/security.php';   // csrf_check(), current_user_id(), ensure_json()
require_once $APP_BASE . '/includes/config.php';
require_once $APP_BASE . '/includes/db.php';

/* ========= Helpers ========= */
function respond_json(array $data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}

function cart_log(string $msg, array $ctx = []): void {
  $logDir = dirname(__DIR__) . '/logs';
  if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
  $line = sprintf(
    "[%s] API %s | IP:%s | SID:%s | vg_guest:%s | CTX:%s%s",
    date('Y-m-d H:i:s'),
    $msg,
    $_SERVER['REMOTE_ADDR'] ?? 'N/A',
    session_id() ?: '-',
    $_COOKIE['vg_guest'] ?? ($_SESSION['guest_sid'] ?? ''),
    json_encode($ctx, JSON_UNESCAPED_SLASHES),
    PHP_EOL
  );
  @file_put_contents($logDir . '/error_cart.log', $line, FILE_APPEND | LOCK_EX);
}

/* ========= Utilidades guest ========= */
function cart_set_guest_cookie(string $sid): void {
  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  setcookie('vg_guest', $sid, [
    'expires'  => time()+60*60*24*30,
    'path'     => '/',
    'domain'   => '.compratica.com',
    'secure'   => $isHttps,
    'httponly' => false,
    'samesite' => 'Lax',
  ]);
}

function cart_get_or_make_guest_sid(): string {
  $cookie = (string)($_COOKIE['vg_guest'] ?? '');
  if ($cookie !== '') {
    $_SESSION['guest_sid'] = $cookie;
    return $cookie;
  }
  if (!empty($_SESSION['guest_sid'])) {
    cart_set_guest_cookie($_SESSION['guest_sid']);
    return (string)$_SESSION['guest_sid'];
  }
  $sid = bin2hex(random_bytes(16));
  $_SESSION['guest_sid'] = $sid;
  cart_set_guest_cookie($sid);
  return $sid;
}

function cart_find_or_create_cart(PDO $db): array {
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
  }
  $sid = cart_get_or_make_guest_sid();
  $st = $db->prepare("SELECT * FROM carts WHERE guest_sid=?");
  $st->execute([$sid]);
  $c = $st->fetch(PDO::FETCH_ASSOC);
  if (!$c) {
    $db->prepare("INSERT INTO carts(guest_sid,currency) VALUES(?, 'CRC')")->execute([$sid]);
    $st = $db->prepare("SELECT * FROM carts WHERE guest_sid=?");
    $st->execute([$sid]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
  }
  return $c ?: ['id'=>null,'guest_sid'=>$sid,'currency'=>'CRC'];
}

/* ========= Inicialización ========= */
ensure_json(); // header JSON por defecto
$pdo = db();
$pdo->exec("PRAGMA foreign_keys=ON;");

/* ========= Esquema mínimo (idempotente) ========= */
$pdo->exec("
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
  sale_id INTEGER,
  variant_id INTEGER,
  qty INTEGER NOT NULL,
  unit_price NUMERIC NOT NULL,
  tax_rate NUMERIC DEFAULT 0.0,
  created_at TEXT DEFAULT (datetime('now')),
  FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_items_cart ON cart_items(cart_id);
CREATE INDEX IF NOT EXISTS idx_items_sale ON cart_items(sale_id);
");

/* ========= Endpoint auxiliar para log desde front ========= */
if (($_GET['action'] ?? '') === 'log_error' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  cart_log('CLIENT_LOG', is_array($data)?$data:['raw'=>$raw]);
  respond_json(['ok'=>true]);
}

/* ========= Router ========= */
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'get';

/* =========================================================
   GET:get  → carrito agrupado por sale_id con totales
   ========================================================= */
if ($method === 'GET' && ($action === 'get' || $action === '')) {
  $cart = cart_find_or_create_cart($pdo);

  $sql = "
    SELECT
      ci.id           AS item_id,
      ci.cart_id,
      ci.product_id,
      ci.variant_id,
      ci.qty,
      ci.unit_price,
      ci.tax_rate,
      COALESCE(ci.sale_id, p.sale_id) AS sale_id,
      p.name          AS product_name,
      p.image         AS product_image,
      p.currency      AS currency,
      s.title         AS sale_title,
      a.id            AS affiliate_id,
      a.name          AS affiliate_name
    FROM cart_items ci
    JOIN products p   ON p.id = ci.product_id
    LEFT JOIN sales s ON s.id = COALESCE(ci.sale_id, p.sale_id)
    LEFT JOIN affiliates a ON a.id = s.affiliate_id
    WHERE ci.cart_id = ?
    ORDER BY s.title ASC, ci.id DESC
  ";
  $st = $pdo->prepare($sql);
  $st->execute([$cart['id']]);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  $groups = [];
  $cart_count = 0;
  foreach ($rows as $r) {
    $sid = (int)$r['sale_id'];
    if (!isset($groups[$sid])) {
      $groups[$sid] = [
        'sale_id'        => $sid,
        'sale_title'     => (string)($r['sale_title'] ?? 'Espacio'),
        'affiliate_id'   => (int)($r['affiliate_id'] ?? 0),
        'affiliate_name' => (string)($r['affiliate_name'] ?? ''),
        'currency'       => strtoupper((string)($r['currency'] ?? 'CRC')),
        'items' => [],
        'totals' => [
          'count' => 0,
          'subtotal' => 0.0,
          'tax_total' => 0.0,
          'grand_total' => 0.0,
        ],
      ];
    }
    $lineTotal = (float)$r['unit_price'] * (int)$r['qty'];
    $taxLine   = $lineTotal * (float)$r['tax_rate'];

    $groups[$sid]['items'][] = [
      'item_id'       => (int)$r['item_id'],
      'product_id'    => (int)$r['product_id'],
      'product_name'  => (string)$r['product_name'],
      'product_image' => (string)($r['product_image'] ?? ''),
      'qty'           => (int)$r['qty'],
      'unit_price'    => (float)$r['unit_price'],
      'tax_rate'      => (float)$r['tax_rate'],
      'line_total'    => $lineTotal,
      'tax_line'      => $taxLine,
    ];

    $groups[$sid]['totals']['count']      += (int)$r['qty'];
    $groups[$sid]['totals']['subtotal']   += $lineTotal;
    $groups[$sid]['totals']['tax_total']  += $taxLine;
    $groups[$sid]['totals']['grand_total'] = $groups[$sid]['totals']['subtotal'] + $groups[$sid]['totals']['tax_total'];

    $cart_count += (int)$r['qty'];
  }

  respond_json([
    'ok'         => true,
    'groups'     => array_values($groups),
    'cart_count' => $cart_count,
  ]);
}

/* =========================================================
   POST:add → agrega (precio desde BD) y devuelve cart_count
   ========================================================= */
if ($method === 'POST' && $action === 'add') {
  csrf_check();

  $payload = json_decode(file_get_contents('php://input'), true);
  if (!is_array($payload)) $payload = $_POST;

  $product = (int)($payload['product_id'] ?? 0);
  $qty     = max(1, (int)($payload['qty'] ?? 1));
  if ($product <= 0) respond_json(['ok'=>false,'error'=>'invalid_params'], 400);

  $p = $pdo->prepare("SELECT id, sale_id, price, currency, stock, active FROM products WHERE id=?");
  $p->execute([$product]);
  $prow = $p->fetch(PDO::FETCH_ASSOC);
  if (!$prow || (int)$prow['active'] !== 1) respond_json(['ok'=>false,'error'=>'product_not_found'],404);
  if ((int)$prow['stock'] < 1) respond_json(['ok'=>false,'error'=>'out_of_stock'],409);

  $saleId = (int)$prow['sale_id'];
  $price  = (float)$prow['price'];
  $cart   = cart_find_or_create_cart($pdo);

  // Merge por (product_id, sale_id) — no mezclar espacios
  $sel = $pdo->prepare("
    SELECT id FROM cart_items
     WHERE cart_id=? AND product_id=? AND COALESCE(sale_id,0)=?
  ");
  $sel->execute([$cart['id'],$product,$saleId]);
  $exist = $sel->fetchColumn();

  if ($exist) {
    $upd = $pdo->prepare("UPDATE cart_items SET qty=qty+?, unit_price=? WHERE id=?");
    $upd->execute([$qty,$price,$exist]);
    cart_log("ADD merge", ['item_id'=>$exist,'cart_id'=>$cart['id'],'sale_id'=>$saleId,'product'=>$product,'qty+'=>$qty]);
  } else {
    $ins = $pdo->prepare("INSERT INTO cart_items(cart_id,product_id,qty,unit_price,tax_rate,sale_id)
                          VALUES(?,?,?,?,?,?)");
    $ins->execute([$cart['id'],$product,$qty,$price,0.0,$saleId]);
    cart_log("ADD new", ['cart_id'=>$cart['id'],'sale_id'=>$saleId,'product'=>$product,'qty'=>$qty,'price'=>$price]);
  }

  // Recalcular cart_count
  $cst = $pdo->prepare("SELECT SUM(qty) FROM cart_items WHERE cart_id=?");
  $cst->execute([$cart['id']]);
  $cart_count = (int)$cst->fetchColumn();

  respond_json(['ok'=>true,'cart_count'=>$cart_count]);
}

/* =========================================================
   PATCH:update → cambia cantidad
   ========================================================= */
if ($method === 'PATCH' && $action === 'update') {
  csrf_check();
  parse_str(file_get_contents('php://input'), $payload);
  $id  = (int)($payload['id'] ?? 0);
  $qty = max(1, (int)($payload['qty'] ?? 1));
  if ($id<=0) respond_json(['ok'=>false,'error'=>'invalid_id'], 400);

  $cart = cart_find_or_create_cart($pdo);
  $st = $pdo->prepare("UPDATE cart_items SET qty=? WHERE id=? AND cart_id=?");
  $st->execute([$qty,$id,$cart['id']]);

  respond_json(['ok'=>true]);
}

/* =========================================================
   DELETE:remove → elimina ítem
   ========================================================= */
if ($method === 'DELETE' && $action === 'remove') {
  csrf_check();
  parse_str(file_get_contents('php://input'), $payload);
  $id  = (int)($payload['id'] ?? 0);
  if ($id<=0) respond_json(['ok'=>false,'error'=>'invalid_id'], 400);

  $cart = cart_find_or_create_cart($pdo);
  $st = $pdo->prepare("DELETE FROM cart_items WHERE id=? AND cart_id=?");
  $st->execute([$id,$cart['id']]);

  respond_json(['ok'=>true]);
}

/* =========================================================
   Not found
   ========================================================= */
respond_json(['ok'=>false,'error'=>'not_found'],404);
