<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/security.php';
require_once __DIR__ . '/../includes/db.php';

/* ========= Helpers ========= */
function respond_json(array $data, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}

/* ========= Bitácora ========= */
function cart_log(string $msg): void {
  $logDir = __DIR__ . '/../logs';
  if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
  $line = sprintf("[%s] API %s | IP:%s | SID:%s | vg_guest:%s%s",
    date('Y-m-d H:i:s'),
    $msg,
    $_SERVER['REMOTE_ADDR'] ?? 'N/A',
    session_id() ?: '-',
    $_COOKIE['vg_guest'] ?? ($_SESSION['guest_sid'] ?? ''),
    PHP_EOL
  );
  @file_put_contents($logDir . '/error_cart.log', $line, FILE_APPEND | LOCK_EX);
}

/* ========= Endpoint log_error ========= */
if (isset($_GET['action']) && $_GET['action'] === 'log_error') {
  try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $msg  = $data['msg'] ?? 'Error sin mensaje';
    cart_log("JS_LOG: ".$msg);
    respond_json(['ok' => true]);
  } catch (Throwable $e) {
    respond_json(['ok' => false, 'error' => $e->getMessage()], 500);
  }
}

/* ========= Main ========= */
ensure_json();
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'get';

/* ========= Add ========= */
if ($method === 'POST' && $action === 'add') {
  csrf_check();
  $payload = json_decode(file_get_contents('php://input'), true);
  if (!is_array($payload)) $payload = $_POST;

  $product = (int)($payload['product_id'] ?? 0);
  $qty     = max(1, (int)($payload['qty'] ?? 1));

  if ($product <= 0) respond_json(['ok'=>false,'error'=>'invalid_params'],400);

  $p = $pdo->prepare("SELECT id, sale_id, price, currency, stock, active FROM products WHERE id=?");
  $p->execute([$product]);
  $prow = $p->fetch(PDO::FETCH_ASSOC);
  if (!$prow || (int)$prow['active'] !== 1) respond_json(['ok'=>false,'error'=>'product_not_found'],404);
  if ((int)$prow['stock'] < 1) respond_json(['ok'=>false,'error'=>'out_of_stock'],409);

  $saleId = (int)$prow['sale_id'];
  $price  = (float)$prow['price'];
  $tax    = 0.0;
  $cart   = cart_find_or_create_cart($pdo, true);

  $sel = $pdo->prepare("SELECT id FROM cart_items WHERE cart_id=? AND product_id=?");
  $sel->execute([$cart['id'],$product]);
  $exist = $sel->fetchColumn();

  if ($exist) {
    $upd = $pdo->prepare("UPDATE cart_items SET qty=qty+?, unit_price=? WHERE id=?");
    $upd->execute([$qty,$price,$exist]);
    cart_log("ADD merged item=$exist cart={$cart['id']} sale_id=$saleId qty+=$qty product=$product");
  } else {
    $ins = $pdo->prepare("INSERT INTO cart_items(cart_id,product_id,qty,unit_price,tax_rate,sale_id)
                          VALUES(?,?,?,?,?,?)");
    $ins->execute([$cart['id'],$product,$qty,$price,$tax,$saleId]);
    cart_log("ADD new cart={$cart['id']} sale_id=$saleId product=$product qty=$qty price=$price");
  }

  respond_json(['ok'=>true]);
}

/* ========= Not found ========= */
respond_json(['ok'=>false,'error'=>'not_found'],404);
