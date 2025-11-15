<?php
declare(strict_types=1);
$APP_BASE = dirname(__DIR__);
require_once $APP_BASE.'/inc/security.php';
/* === Compat shim: normalize input (JSON/GET to $_POST) + accept legacy names === */
// 1) If Content-Type is JSON or raw body looks like JSON, decode and merge into $_POST
if (empty($_POST)) {
  $raw = file_get_contents('php://input');
  if (is_string($raw) && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) { foreach ($decoded as $k=>$v) { $_POST[$k] = $v; } }
  }
}
// 2) As a last resort, if POST is still empty and there's a query string, merge GET
if (empty($_POST) && !empty($_GET)) {
  foreach ($_GET as $k=>$v) { if (!isset($_POST[$k])) $_POST[$k] = $v; }
}
// 3) Legacy field names -> canonical
if (isset($_POST['product_id']) && !isset($_POST['product'])) { $_POST['product'] = $_POST['product_id']; }
if (isset($_POST['id'])         && !isset($_POST['product'])) { $_POST['product'] = $_POST['id']; } // some callers send "id"
if (isset($_POST['quantity'])   && !isset($_POST['qty']))     { $_POST['qty']     = $_POST['quantity']; }

require_once $APP_BASE.'/includes/config.php';
require_once $APP_BASE.'/includes/db.php';

function respond_json($arr,$code=200){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  $arr['csrf_token'] = $_SESSION['csrf_token'] ?? '';
  echo json_encode($arr);
  exit;
}

function cart_log($msg,$ctx=[]){
  $dir=dirname(__DIR__).'/logs';
  if(!is_dir($dir))mkdir($dir,0775,true);
  file_put_contents($dir.'/error_cart.log','['.date('c').'] '.$msg.' '.json_encode($ctx).PHP_EOL,FILE_APPEND);
}

/* --- helpers para carrito --- */
function cart_get_or_make_guest_sid(): string {
  $cookie = $_COOKIE['vg_guest'] ?? '';
  if ($cookie) return $cookie;
  $sid = bin2hex(random_bytes(16));
  setcookie('vg_guest', $sid, time()+60*60*24*30, '/', '.compratica.com', true, false);
  $_SESSION['guest_sid'] = $sid;
  return $sid;
}

function cart_find_or_create_cart(PDO $pdo): array {
  $sid = cart_get_or_make_guest_sid();
  $st=$pdo->prepare("SELECT * FROM carts WHERE guest_sid=?");
  $st->execute([$sid]);
  $c=$st->fetch(PDO::FETCH_ASSOC);
  if(!$c){
    $pdo->prepare("INSERT INTO carts(guest_sid,currency)VALUES(?, 'CRC')")->execute([$sid]);
    $st=$pdo->prepare("SELECT * FROM carts WHERE guest_sid=?");
    $st->execute([$sid]);
    $c=$st->fetch(PDO::FETCH_ASSOC);
  }
  return $c;
}

/* --- conexión y esquema --- */
$pdo=db();
$pdo->exec("PRAGMA foreign_keys=ON;");
$pdo->exec("
CREATE TABLE IF NOT EXISTS carts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  guest_sid TEXT,
  currency TEXT DEFAULT 'CRC',
  created_at TEXT DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS cart_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  cart_id INTEGER NOT NULL,
  product_id INTEGER NOT NULL,
  qty INTEGER NOT NULL,
  unit_price NUMERIC NOT NULL DEFAULT 0,
  FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE
);
");

$action=$_GET['action']??'get';

/* --- GET --- */
if($action==='get'){
  $cart=cart_find_or_create_cart($pdo);
  $st=$pdo->prepare("SELECT * FROM cart_items WHERE cart_id=?");
  $st->execute([$cart['id']]);
  respond_json(['ok'=>true,'rows'=>$st->fetchAll(PDO::FETCH_ASSOC)]);
}

/* --- ADD --- */
if($action==='add'){
  csrf_check();
  $cart = cart_find_or_create_cart($pdo);

  // aceptar ambos nombres
  $pid = (int)($_POST['product'] ?? ($_POST['product_id'] ?? 0));
  $qty = (int)($_POST['qty'] ?? ($_POST['quantity'] ?? 1));

  if ($pid <= 0) {
    cart_log('ADD invalid_params', $_POST);
    respond_json(['ok'=>false,'error'=>'invalid_params'],400);
  }

  // buscar precio del producto
  $stp=$pdo->prepare("SELECT price FROM products WHERE id=?");
  $stp->execute([$pid]);
  $price=$stp->fetchColumn();
  if(!$price){
    cart_log('ADD product_not_found', ['pid'=>$pid]);
    respond_json(['ok'=>false,'error'=>'product_not_found'],404);
  }

  $st=$pdo->prepare("INSERT INTO cart_items(cart_id,product_id,qty,unit_price)VALUES(?,?,?,?)");
  $st->execute([$cart['id'],$pid,$qty,$price]);
  cart_log('ADD ok', ['cart_id'=>$cart['id'],'product'=>$pid,'qty'=>$qty,'price'=>$price]);
  respond_json(['ok'=>true,'cart_id'=>$cart['id'],'product'=>$pid,'price'=>$price]);
}

/* --- UPDATE --- */
if($action==='update'){
  csrf_check();
  $cart=cart_find_or_create_cart($pdo);
  $id=(int)($_POST['id']??0);
  $qty=(int)($_POST['qty']??($_POST['quantity']??1));
  $st=$pdo->prepare("UPDATE cart_items SET qty=? WHERE id=? AND cart_id=?");
  $st->execute([$qty,$id,$cart['id']]);
  cart_log('UPDATE',['cart_id'=>$cart['id'],'id'=>$id,'qty'=>$qty]);
  respond_json(['ok'=>true]);
}

/* --- REMOVE --- */
if($action==='remove'){
  csrf_check();
  $cart=cart_find_or_create_cart($pdo);
  $id=(int)($_POST['id']??0);
  $st=$pdo->prepare("DELETE FROM cart_items WHERE id=? AND cart_id=?");
  $st->execute([$id,$cart['id']]);
  cart_log('REMOVE',['cart_id'=>$cart['id'],'id'=>$id]);
  respond_json(['ok'=>true]);
}

/* --- Not Found --- */
respond_json(['ok'=>false,'error'=>'not_found'],404);
