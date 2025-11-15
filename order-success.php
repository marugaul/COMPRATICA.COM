<?php
declare(strict_types=1);

/**
 * order-success.php
 * - Mantiene sesión (rehidrata con ?reauth) sin destruirla
 * - Envia "En Revisión" al reseller SIEMPRE (una sola vez por orden) tomando email desde affiliates
 *   * Estrategia destinatarios:
 *     (A) order_items → products(affiliate_id) → affiliates(email,name)
 *     (B) fallback: orders.product_id → products.affiliate_id → affiliates
 *     (C) fallback: grupo de sesión por sale_id
 *     (D) último fallback: info@compratica.com
 * - Items del correo:
 *     (1) order_items; si no hay → (2) orders → products (fila sintética)
 * - Limpia del carrito SOLO lo del sale_id (BD + sesión)
 * - Upsert portable en order_meta (INSERT y si falla, UPDATE)
 * - Contador 4→0 visible
 * - Logs robustos (RID, tiempos, SQL, filas, errores)
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// ---------- LOGGING ----------
$RID = 'RID-' . substr(bin2hex(random_bytes(6)), 0, 12);
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
ini_set('log_errors', '1');
ini_set('error_log', $logDir . '/php_errors_order_success.log');

$logFile = $logDir . '/order_success.log';
function os_log(string $msg, $data=null, ?string $rid=null): void {
  global $logFile; $ts=date('Y-m-d H:i:s');
  $line="[$ts] ".($rid?"$rid ":"").$msg;
  if ($data!==null) $line.=" | ".json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  $line.="\n"; @file_put_contents($logFile, $line, FILE_APPEND);
}
function os_ms(float $t0): float { return round((microtime(true)-$t0)*1000, 1); }

set_error_handler(function($sev,$msg,$file,$line) use($RID){
  os_log('PHP_ERROR',['sev'=>$sev,'msg'=>$msg,'file'=>$file,'line'=>$line],$RID);
  return false;
});
set_exception_handler(function($ex) use($RID){
  os_log('PHP_EXCEPTION',['type'=>get_class($ex),'msg'=>$ex->getMessage(),'file'=>$ex->getFile(),'line'=>$ex->getLine()],$RID);
});
register_shutdown_function(function() use($RID){
  $e=error_get_last();
  if($e && in_array($e['type']??0,[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)){
    os_log('PHP_FATAL',$e,$RID);
  }
});

os_log("========== ORDER SUCCESS PAGE ==========", null, $RID);
os_log("ENV", [
  'php'=>PHP_VERSION,
  'sapi'=>PHP_SAPI,
  'https'=>$_SERVER['HTTPS']??null,
  'host'=>$_SERVER['HTTP_HOST']??null,
  'uri'=>$_SERVER['REQUEST_URI']??null,
  'ip'=>$_SERVER['REMOTE_ADDR']??null
], $RID);
os_log("GET_PARAMS", $_GET, $RID);

// ---------- INCLUDES ----------
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
if (is_file(__DIR__ . '/includes/mailer.php')) {
  require_once __DIR__ . '/includes/mailer.php';
  os_log('MAILER_INCLUDED', ['send_email_exists'=>function_exists('send_email')], $RID);
} else {
  os_log('MAILER_MISSING', null, $RID);
}

// ---------- SESIÓN ----------
$__isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0700, true);

if (session_status() !== PHP_SESSION_ACTIVE) {
  ini_set('session.save_path', $__sessPath);
  session_name('PHPSESSID');
  if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','domain'=>'','secure'=>$__isHttps,'httponly'=>true,'samesite'=>'Lax']);
  } else {
    ini_set('session.cookie_lifetime','0');
    ini_set('session.cookie_path','/');
    ini_set('session.cookie_secure',$__isHttps?'1':'0');
    ini_set('session.cookie_httponly','1');
    ini_set('session.cookie_samesite','Lax');
    session_set_cookie_params(0,'/','',$__isHttps,true);
  }
  ini_set('session.use_only_cookies','1');
  ini_set('session.gc_maxlifetime','86400');
  session_start();
  os_log('SESSION_STARTED', ['sid'=>session_id()], $RID);
} else {
  os_log('SESSION_ALREADY_ACTIVE', ['sid'=>session_id()], $RID);
}

// ---------- Helpers Reauth ----------
function b64url_dec(string $s): string { $r=strtr($s,'-_','+/'); $pad=strlen($r)%4; if($pad)$r.=str_repeat('=',4-$pad); return base64_decode($r,true)?:''; }
function b64url_enc(string $bin): string { return rtrim(strtr(base64_encode($bin),'+/','-_'),'='); }
function sign_hmac(string $payloadJson, string $secret): string { return b64url_enc(hash_hmac('sha256',$payloadJson,$secret,true)); }

$reauth = isset($_GET['reauth']) ? (string)$_GET['reauth'] : '';
if ($reauth !== '') {
  os_log('REAUTH_START', ['reauth_len'=>strlen($reauth)], $RID);
  $parts=explode('.',$reauth,2);
  if (count($parts)===2){
    $payloadJson=b64url_dec($parts[0]); $sig=$parts[1];
    $secret=defined('APP_KEY')?(string)APP_KEY:'supersecret';
    $expected=sign_hmac($payloadJson,$secret);
    if (hash_equals($expected,$sig)){
      $payload=json_decode($payloadJson,true); $uid=(int)($payload['uid']??0); $exp=(int)($payload['exp']??0);
      if($uid>0 && $exp>time()){
        $_SESSION['uid']=$uid; $_SESSION['user_id']=$uid;
        if (PHP_VERSION_ID>=70300) {
          setcookie(session_name(),session_id(),['expires'=>0,'path'=>'/','domain'=>'','secure'=>$__isHttps,'httponly'=>true,'samesite'=>'Lax']);
        }
        os_log('REAUTH_OK', ['uid'=>$uid], $RID);
      } else {
        os_log('REAUTH_BAD_UID_OR_EXP', ['uid'=>$uid,'exp'=>$exp], $RID);
      }
    } else {
      os_log('REAUTH_BAD_SIGNATURE', null, $RID);
    }
  } else {
    os_log('REAUTH_BAD_FORMAT', $reauth, $RID);
  }
}
os_log('COOKIE_POST_REAUTH', ['cookie_name'=>session_name(),'cookie_val'=>$_COOKIE[session_name()]??'no-cookie','sid'=>session_id()], $RID);
os_log('SESSION_POST_REAUTH', $_SESSION, $RID);

// ---------- order_meta portable ----------
function ensure_order_meta(PDO $pdo, string $rid): void {
  try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_meta (order_id INTEGER NOT NULL, meta_key TEXT NOT NULL, meta_value TEXT, UNIQUE(order_id, meta_key))");
    os_log('META_TABLE_OK', null, $rid);
  } catch (Throwable $e) { os_log('META_CREATE_ERR', $e->getMessage(), $rid); }
}
function meta_get(PDO $pdo, int $orderId, string $key, string $rid): ?string {
  try {
    $s = $pdo->prepare("SELECT meta_value FROM order_meta WHERE order_id=:o AND meta_key=:k");
    $t0=microtime(true); $s->execute([':o'=>$orderId, ':k'=>$key]);
    $v = $s->fetchColumn();
    os_log('META_GET', ['key'=>$key, 'exists'=>$v!==false, 'ms'=>os_ms($t0)], $rid);
    return $v!==false ? (string)$v : null;
  } catch (Throwable $e) { os_log('META_GET_ERR', ['key'=>$key, 'err'=>$e->getMessage()], $rid); return null; }
}
function meta_set_ts(PDO $pdo, int $orderId, string $key, string $rid): void {
  $now = gmdate('c');
  try {
    $ins = $pdo->prepare("INSERT INTO order_meta (order_id, meta_key, meta_value) VALUES (:o,:k,:v)");
    $t0=microtime(true); $ins->execute([':o'=>$orderId, ':k'=>$key, ':v'=>$now]);
    os_log('META_INSERT', ['key'=>$key, 'order_id'=>$orderId, 'ms'=>os_ms($t0)], $rid);
  } catch (Throwable $e) {
    os_log('META_INSERT_ERR', ['key'=>$key, 'err'=>$e->getMessage()], $rid);
    try {
      $upd = $pdo->prepare("UPDATE order_meta SET meta_value=:v WHERE order_id=:o AND meta_key=:k");
      $t1=microtime(true); $upd->execute([':v'=>$now, ':o'=>$orderId, ':k'=>$key]);
      os_log('META_UPDATE', ['key'=>$key, 'order_id'=>$orderId, 'ms'=>os_ms($t1)], $rid);
    } catch (Throwable $e2) {
      os_log('META_UPDATE_ERR', ['key'=>$key, 'err'=>$e2->getMessage()], $rid);
    }
  }
}

// ---------- DB ----------
$pdo = db();
try { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Throwable $e) { os_log('PDO_ATTR_ERR', $e->getMessage(), $RID); }

// ---------- Cargar orden ----------
$orderKey = $_GET['order'] ?? $_GET['order_id'] ?? '';
$order = null;
if ($orderKey !== '') {
  try {
    $sql = "SELECT o.*, p.sale_id AS product_sale_id
              FROM orders o
              LEFT JOIN products p ON p.id = o.product_id
             WHERE o.order_number = :k OR o.id = :i
             LIMIT 1";
    $st = $pdo->prepare($sql);
    os_log('ORDER_LOAD_SQL', ['sql'=>$sql, 'params'=>['k'=>$orderKey, 'i'=>(int)$orderKey]], $RID);
    $t0=microtime(true);
    $st->execute([':k'=>$orderKey, ':i'=>(int)$orderKey]);
    $order = $st->fetch(PDO::FETCH_ASSOC);
    os_log('ORDER_LOADED', $order ? ['order_id'=>(int)$order['id'],'status'=>$order['status'],'payment_method'=>$order['payment_method'],'ms'=>os_ms($t0)] : 'NOT_FOUND', $RID);
  } catch (Throwable $e) { os_log('ORDER_LOAD_ERR', ['err'=>$e->getMessage(),'order_key'=>$orderKey], $RID); }
} else {
  os_log('ORDER_KEY_MISSING', null, $RID);
}

$saleId = 0;
if ($order) {
  $saleId = (int)($order['product_sale_id'] ?? 0);
  os_log('SALE_ID_SOURCE', ['step'=>'orders_products','sale_id'=>$saleId], $RID);
  if ($saleId <= 0) {
    try {
      $q = $pdo->prepare("SELECT DISTINCT COALESCE(oi.sale_id,p.sale_id) FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=:oid LIMIT 1");
      $q->execute([':oid'=>(int)$order['id']]);
      $saleId = (int)($q->fetchColumn() ?: 0);
      os_log('SALE_ID_SOURCE', ['step'=>'order_items_products','sale_id'=>$saleId], $RID);
    } catch (Throwable $e) { os_log('SALE_ID_ERR', $e->getMessage(), $RID); }
  }
  if ($saleId <= 0 && !empty($_SESSION['cart']['groups']) && is_array($_SESSION['cart']['groups'])) {
    $gr = $_SESSION['cart']['groups']; if (count($gr)===1) { $saleId = (int)($gr[0]['sale_id'] ?? 0); os_log('SALE_ID_SOURCE', ['step'=>'session_group_single','sale_id'=>$saleId], $RID); }
  }
}

// ---------- Helpers: destinatarios reseller ----------
/**
 * Obtiene correos de afiliados con trazas SQL y fallbacks:
 * 1) order_items → products → affiliates
 * 1b) si no hay order_items: orders.product_id → products → affiliates
 * 2) grupo de sesión por sale_id
 * 3) fallback info@compratica.com
 */
function get_reseller_targets(PDO $pdo, int $orderId, int $saleId, array $sessionCart, string $rid): array {
  $targets = [];

  // 1) DB: via order_items
  try {
    $sql = "
      SELECT DISTINCT a.email AS email, a.name AS name
        FROM order_items oi
        JOIN products   p ON p.id = oi.product_id
        JOIN affiliates a ON a.id = p.affiliate_id
       WHERE oi.order_id = :oid
         AND a.email IS NOT NULL AND a.email <> ''
    ";
    $stmt = $pdo->prepare($sql);
    os_log('SQL_MAIL_TARGETS_DB_PREP', ['sql'=>$sql, 'params'=>['oid'=>$orderId]], $rid);
    $t0 = microtime(true); $stmt->execute([':oid'=>$orderId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    os_log('SQL_MAIL_TARGETS_DB_ROWS', ['count'=>count($rows), 'ms'=>os_ms($t0)], $rid);

    // Traza extendida (qué trajo el JOIN por producto)
    try {
      $sqlTrace = "
        SELECT 
          oi.order_id,
          oi.product_id,
          p.affiliate_id,
          a.email AS affiliate_email,
          a.name  AS affiliate_name
        FROM order_items oi
        LEFT JOIN products   p ON p.id = oi.product_id
        LEFT JOIN affiliates a ON a.id = p.affiliate_id
        WHERE oi.order_id = :oid
        ORDER BY oi.product_id
      ";
      $stT = $pdo->prepare($sqlTrace);
      os_log('SQL_MAIL_TARGETS_TRACE_PREP', ['sql'=>$sqlTrace, 'params'=>['oid'=>$orderId]], $rid);
      $tT = microtime(true); $stT->execute([':oid'=>$orderId]);
      $rowsT = $stT->fetchAll(PDO::FETCH_ASSOC) ?: [];
      os_log('SQL_MAIL_TARGETS_TRACE_ROWS', ['count'=>count($rowsT), 'ms'=>os_ms($tT)], $rid);
      foreach ($rowsT as $idx=>$tr) {
        os_log('SQL_MAIL_TARGETS_TRACE_ROW', ['idx'=>$idx, 'row'=>$tr], $rid);
      }
    } catch (Throwable $eT) {
      os_log('SQL_MAIL_TARGETS_TRACE_ERR', $eT->getMessage(), $rid);
    }

    foreach ($rows as $i => $r) {
      os_log('SQL_MAIL_TARGETS_DB_ROW', ['idx'=>$i, 'email'=>$r['email'] ?? null, 'name'=>$r['name'] ?? null], $rid);
      $email = trim((string)($r['email'] ?? ''));
      if ($email !== '') $targets[] = ['email'=>$email, 'name'=>(string)($r['name'] ?? 'Afiliado')];
    }
  } catch (Throwable $e) {
    os_log('MAIL_SOURCES_DB_ERR', $e->getMessage(), $rid);
  }

  // 1b) DB fallback: orders.product_id → products → affiliates
  if (!$targets) {
    try {
      $sql2 = "
        SELECT a.email AS email, a.name AS name
          FROM orders o
          JOIN products   p ON p.id = o.product_id
          JOIN affiliates a ON a.id = p.affiliate_id
         WHERE o.id = :oid
           AND a.email IS NOT NULL AND a.email <> ''
         LIMIT 5
      ";
      $st2 = $pdo->prepare($sql2);
      os_log('SQL_MAIL_TARGETS_ORDERS_PREP', ['sql'=>$sql2, 'params'=>['oid'=>$orderId]], $rid);
      $t1 = microtime(true); $st2->execute([':oid'=>$orderId]);
      $rows2 = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
      os_log('SQL_MAIL_TARGETS_ORDERS_ROWS', ['count'=>count($rows2), 'ms'=>os_ms($t1)], $rid);

      foreach ($rows2 as $j => $r2) {
        os_log('SQL_MAIL_TARGETS_ORDERS_ROW', ['idx'=>$j, 'email'=>$r2['email'] ?? null, 'name'=>$r2['name'] ?? null], $rid);
        $email = trim((string)($r2['email'] ?? ''));
        if ($email !== '') $targets[] = ['email'=>$email, 'name'=>(string)($r2['name'] ?? 'Afiliado')];
      }
    } catch (Throwable $e2) {
      os_log('MAIL_SOURCES_ORDERS_ERR', $e2->getMessage(), $rid);
    }
  }

  // 2) Desde sesión: grupo del sale_id
  if ($saleId > 0 && !empty($sessionCart['groups']) && is_array($sessionCart['groups'])) {
    $added = 0;
    foreach ($sessionCart['groups'] as $g) {
      $gSale = (int)($g['sale_id'] ?? 0);
      if ($gSale !== $saleId) continue;
      $em = trim((string)($g['affiliate_email'] ?? ''));
      $nm = (string)($g['affiliate_name'] ?? 'Afiliado');
      if ($em !== '') { $targets[] = ['email'=>$em, 'name'=>$nm]; $added++; }
    }
    os_log('MAIL_SOURCES_SESSION', ['added_from_session'=>$added], $rid);
  }

  // 3) Fallback final
  if (!$targets) $targets[] = ['email'=>'info@compratica.com', 'name'=>'Compratica'];

  // Unificar por email
  $uniq = [];
  foreach ($targets as $t) $uniq[$t['email']] = $t;
  $targets = array_values($uniq);

  os_log('MAIL_SOURCES_FINAL', ['count'=>count($targets), 'targets'=>$targets], $rid);
  return $targets;
}

// ---------- Acciones ----------
if ($order) {
  ensure_order_meta($pdo, $RID);

  $orderId  = (int)$order['id'];
  $status   = (string)$order['status']; // puede venir "Pendiente"
  $uid      = (int)($_SESSION['user_id'] ?? $_SESSION['uid'] ?? 0);
  $guestSid = $_SESSION['guest_sid'] ?? ($_COOKIE['guest_sid'] ?? null);
  $custMail = (string)($order['customer_email'] ?? $order['email'] ?? '');
  $custName = (string)($order['customer_name'] ?? $order['name'] ?? '');

  // (1) Notificar “En Revisión” SIEMPRE (una vez por orden)
  $already = meta_get($pdo, $orderId, 'reseller_notified_review_at', $RID);
  if ($already === null) {
    $targets = get_reseller_targets($pdo, $orderId, $saleId, $_SESSION['cart'] ?? [], $RID);

    // Items para el cuerpo (con fallback si no hay order_items)
    $items = [];
    try {
      $it = $pdo->prepare("SELECT product_name, qty, unit_price, line_total FROM order_items WHERE order_id=:oid");
      $t0=microtime(true); $it->execute([':oid'=>$orderId]); $items = $it->fetchAll(PDO::FETCH_ASSOC) ?: [];
      os_log('MAIL_ITEMS', ['count'=>count($items), 'ms'=>os_ms($t0)], $RID);
      foreach ($items as $ix=>$rowI) { os_log('MAIL_ITEM_ROW', ['idx'=>$ix, 'row'=>$rowI], $RID); }
    } catch (Throwable $e) {
      os_log('MAIL_ITEMS_ERR', $e->getMessage(), $RID);
    }

    // Fallback: orders → products (1 fila)
    if (!$items) {
      try {
        $sqlOne = "
          SELECT p.name AS product_name,
                 1       AS qty,
                 COALESCE(o.grand_total, o.subtotal, 0) AS unit_price,
                 COALESCE(o.grand_total, o.subtotal, 0) AS line_total
            FROM orders o
            JOIN products p ON p.id = o.product_id
           WHERE o.id = :oid
           LIMIT 1
        ";
        $stOne = $pdo->prepare($sqlOne);
        os_log('MAIL_ITEMS_FALLBACK_PREP', ['sql'=>$sqlOne, 'params'=>['oid'=>$orderId]], $RID);
        $t1 = microtime(true); $stOne->execute([':oid'=>$orderId]);
        $rowOne = $stOne->fetch(PDO::FETCH_ASSOC);
        os_log('MAIL_ITEMS_FALLBACK_ROW', ['found'=>!!$rowOne, 'row'=>$rowOne, 'ms'=>os_ms($t1)], $RID);
        if ($rowOne) $items = [$rowOne];
      } catch (Throwable $e2) {
        os_log('MAIL_ITEMS_FALLBACK_ERR', $e2->getMessage(), $RID);
      }
    }

    $sentAny = false;
    foreach ($targets as $t) {
      $to = $t['email']; $aname = $t['name'] ?? 'Afiliado';

      ob_start(); ?>
      <div style="font-family:Arial,Helvetica,sans-serif">
        <h2>Pedido en revisión #<?= (int)$orderId ?></h2>
        <p><b>Afiliado:</b> <?= htmlspecialchars($aname) ?></p>
        <p><b>Estado:</b> <?= htmlspecialchars($status ?: 'En Revisión') ?></p>
        <p><b>Cliente:</b> <?= htmlspecialchars($custName ?: $custMail ?: 'Cliente Compratica') ?></p>
        <hr>
        <table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse">
          <tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Total</th></tr>
          <?php foreach ($items as $itx): ?>
            <tr>
              <td><?= htmlspecialchars((string)($itx['product_name'] ?? 'Producto')) ?></td>
              <td align="right"><?= (int)($itx['qty'] ?? 1) ?></td>
              <td align="right"><?= number_format((float)($itx['unit_price'] ?? 0), 2) ?></td>
              <td align="right"><?= number_format((float)($itx['line_total'] ?? 0), 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
        <p>
          <a href="<?= htmlspecialchars((string)($_SERVER['REQUEST_SCHEME'] ?? ($__isHttps ? 'https' : 'http'))) ?>://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'compratica.com') ?>/admin/order.php?id=<?= (int)$orderId ?>">
            Ver pedido en el panel
          </a>
        </p>
      </div>
      <?php
      $html = ob_get_clean();
      $hasFn = function_exists('send_email');
      os_log('MAIL_TRY', ['to'=>$to,'replyTo'=>$custMail ?: null,'replyName'=>$custName ?: null,'fn_exists'=>$hasFn], $RID);

      $tSend = microtime(true);
      $ok = $hasFn ? send_email($to, "Pedido en revisión #{$orderId}", $html, ($custMail ?: null), ($custName ?: null)) : false;
      os_log($ok ? 'EMAIL_SENT_RESELLER_REVIEW' : 'EMAIL_RESELLER_REVIEW_FAIL', ['to'=>$to, 'order_id'=>$orderId, 'ms'=>os_ms($tSend)], $RID);
      if ($ok) $sentAny = true;
    }

    if ($sentAny) meta_set_ts($pdo, $orderId, 'reseller_notified_review_at', $RID);
    else os_log('EMAIL_RESELLER_REVIEW_NONE_SENT', ['order_id'=>$orderId], $RID);

  } else {
    os_log('EMAIL_RESELLER_REVIEW_SKIPPED_ALREADY', ['order_id'=>$orderId, 'at'=>$already], $RID);
  }

  // (2) Limpieza selectiva por sale_id
  if ($saleId > 0) {
    $alreadyClear = meta_get($pdo, $orderId, 'cleared_cart_at', $RID);
    if ($alreadyClear === null) {
      // Usuario autenticado: todos sus carts -> borrar SOLO items del sale_id
      if ($uid > 0) {
        try {
          $stmt = $pdo->prepare("SELECT id FROM carts WHERE user_id=?");
          $t0=microtime(true); $stmt->execute([$uid]);
          $cartIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
          os_log('CART_IDS_BY_USER', ['uid'=>$uid,'count'=>count($cartIds),'ms'=>os_ms($t0)], $RID);

          $deletedUser = 0;
          if ($cartIds) {
            $in = implode(',', array_fill(0, count($cartIds), '?'));
            $sqlDel = "DELETE FROM cart_items WHERE cart_id IN ($in) AND (sale_id = ? OR product_id IN (SELECT id FROM products WHERE sale_id = ?))";
            $params = array_merge($cartIds, [$saleId, $saleId]);
            $del = $pdo->prepare($sqlDel);
            $t1=microtime(true); $del->execute($params);
            os_log('CART_DELETE_SQL', ['sql'=>$sqlDel,'params_count'=>count($params),'ms'=>os_ms($t1)], $RID);
            $deletedUser = (int)$del->rowCount();
          }
          os_log("CART_PARTIAL_CLEAR_USER", ['uid'=>$uid,'sale_id'=>$saleId,'deleted'=>$deletedUser,'cart_ids'=>$cartIds ?? []], $RID);
        } catch (Throwable $e) {
          os_log("CART_PARTIAL_CLEAR_USER_ERR", $e->getMessage(), $RID);
        }
      }

      // Invitado por guest_sid
      $guestSid = $guestSid ?: ($_COOKIE['guest_sid'] ?? null);
      if (!empty($guestSid)) {
        try {
          $stmt = $pdo->prepare("SELECT id FROM carts WHERE guest_sid=?");
          $t2=microtime(true); $stmt->execute([$guestSid]);
          $gCartIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
          os_log('CART_IDS_BY_GUEST', ['guest_sid'=>$guestSid,'count'=>count($gCartIds),'ms'=>os_ms($t2)], $RID);

          $deletedGuest = 0;
          if ($gCartIds) {
            $in = implode(',', array_fill(0, count($gCartIds), '?'));
            $sql = "DELETE FROM cart_items WHERE cart_id IN ($in) AND (sale_id = ? OR product_id IN (SELECT id FROM products WHERE sale_id = ?))";
            $params = array_merge($gCartIds, [$saleId, $saleId]);
            $del = $pdo->prepare($sql);
            $t3=microtime(true); $del->execute($params);
            $deletedGuest = (int)$del->rowCount();
          }
          os_log("CART_PARTIAL_CLEAR_GUEST", ['guest_sid'=>$guestSid,'sale_id'=>$saleId,'deleted'=>$deletedGuest,'cart_ids'=>$gCartIds ?? []], $RID);
        } catch (Throwable $e) {
          os_log("CART_PARTIAL_CLEAR_GUEST_ERR", $e->getMessage(), $RID);
        }
      }

      // Sesión: remover grupo del sale_id
      if (!empty($_SESSION['cart']['groups']) && is_array($_SESSION['cart']['groups'])) {
        $before = count($_SESSION['cart']['groups']);
        $_SESSION['cart']['groups'] = array_values(array_filter(
          $_SESSION['cart']['groups'],
          fn($g) => (int)($g['sale_id'] ?? 0) !== $saleId
        ));
        $after = count($_SESSION['cart']['groups']);
        os_log("SESSION_CART_GROUPS_FILTERED", ['sale_id'=>$saleId,'before'=>$before,'after'=>$after], $RID);
      } else {
        os_log("SESSION_CART_GROUPS_EMPTY_OR_INVALID", null, $RID);
      }

      meta_set_ts($pdo, $orderId, 'cleared_cart_at', $RID);
      os_log("CART_PARTIAL_CLEARED_DONE", ['order_id'=>$orderId,'status'=>$status,'sale_id'=>$saleId], $RID);
    } else {
      os_log("CART_PARTIAL_SKIPPED_ALREADY", ['order_id'=>$orderId,'at'=>$alreadyClear], $RID);
    }
  } else {
    os_log("CART_PARTIAL_SKIP", ['reason'=>'sale_id_missing'], $RID);
  }
}

// ---------- Render ----------
if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Pago completado</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Fallback por si JS está desactivado -->
  <meta http-equiv="refresh" content="4;url=index.php">
  <style>
    body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Ubuntu,Arial,sans-serif;background:#f5f5f5;margin:0;padding:0;display:flex;align-items:center;justify-content:center;height:100vh;}
    .box{background:#fff;color:#111;text-align:center;border-radius:16px;padding:36px 28px;box-shadow:0 10px 30px rgba(0,0,0,0.12);max-width:420px;}
    h1{font-size:1.4rem;margin-bottom:10px;color:#111;}
    p{color:#444;margin-bottom:20px;}
    a.btn{display:inline-block;background:#4f46e5;color:#fff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:600;transition:.2s;}
    a.btn:hover{background:#4338ca;}
    .muted{color:#666;font-size:.9rem;margin-top:8px;}
  </style>
</head>
<body>
  <div class="box">
    <h1>✅ ¡Listo!</h1>
    <p>Tu pedido fue registrado y está <strong><?= htmlspecialchars($order['status'] ?? 'En Revisión') ?></strong> por el vendedor.</p>
    <a class="btn" href="index.php">Volver a la tienda</a>
    <div class="muted">Te redirigiremos automáticamente en <span id="countdown">4</span> segundos…</div>
  </div>
  <script>
    (function(){
      var s=4, el=document.getElementById('countdown');
      if(el) el.textContent=String(s);
      var t=setInterval(function(){
        s-=1;
        if(el) el.textContent=String(Math.max(0,s));
        if(s<=0){ clearInterval(t); location.href='index.php'; }
      },1000);
    })();
  </script>
</body>
</html>
