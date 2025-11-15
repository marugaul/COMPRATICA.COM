<?php 
declare(strict_types=1);

/**
 * process_checkout.php
 * - Crea órdenes (una por línea) con estado "Pendiente".
 * - Solo para el espacio (sale_id) elegido.
 * - IVA efectivo: COALESCE(ci.tax_rate, p.tax_rate) si existe; si no, 0.
 * - Envía correo de confirmación de pedido al comprador.
 * - Prepara return a PayPal con token "reauth" para rehidratar sesión en order-success.php.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// ===== LOGGING =====
$logFile = __DIR__ . '/logs/process_checkout.log';
$errorLogFile = __DIR__ . '/logs/process_errors.log';
if (!is_dir(dirname($logFile))) { @mkdir(dirname($logFile), 0777, true); }
ini_set('error_log', $errorLogFile);

function checkout_log(string $msg, $data = null): void {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] $msg";
    if ($data !== null) $line .= " | " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $line .= PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND);
}

// ===== SESIÓN =====
$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0700, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) ini_set('session.save_path', $__sessPath);

$__isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PHPSESSID');
    session_set_cookie_params([
        'lifetime'=>0,'path'=>'/','domain'=>'',
        'secure'=>$__isHttps,'httponly'=>true,'samesite'=>'Lax'
    ]);
    ini_set('session.use_strict_mode','0');
    ini_set('session.use_only_cookies','1');
    ini_set('session.gc_maxlifetime','86400');
    session_start();
}

// Normaliza uid → user_id
if ((!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) && !empty($_SESSION['uid'])) {
    $_SESSION['user_id'] = (int)$_SESSION['uid'];
}

checkout_log("========== PROCESS_CHECKOUT START ==========");
checkout_log("REQUEST_METHOD", $_SERVER['REQUEST_METHOD'] ?? 'CLI');
checkout_log("REQUEST_URI", $_SERVER['REQUEST_URI'] ?? '');
checkout_log("HTTP_REFERER", $_SERVER['HTTP_REFERER'] ?? '');
checkout_log("POST_DATA", $_POST);
checkout_log("SESSION", [
    'id' => session_id(),
    'uid' => $_SESSION['uid'] ?? null,
    'user_id' => $_SESSION['user_id'] ?? null,
    'csrf' => isset($_SESSION['csrf_token'])
]);

// ===== INCLUDES =====
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php'; // usa send_mail($to,$subject,$html)

// ===== CSRF =====
$csrf_ok = (isset($_POST['csrf_token'], $_SESSION['csrf_token'])
         && hash_equals((string)$_POST['csrf_token'], (string)$_SESSION['csrf_token']));
checkout_log("CSRF_CHECK", [
    'post'  => $_POST['csrf_token'] ?? 'missing',
    'sess'  => $_SESSION['csrf_token'] ?? 'missing',
    'match' => $csrf_ok
]);
if (!$csrf_ok) {
    $_SESSION['error'] = "Token de seguridad inválido";
    header('Location: checkout.php');
    exit;
}

// ===== LOGIN =====
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    $_SESSION['error'] = "Debes iniciar sesión para continuar";
    header('Location: login.php');
    exit;
}

// ===== FORM DATA =====
$sale_id        = isset($_POST['sale_id']) ? (int)$_POST['sale_id'] : 0;
$cart_id        = isset($_POST['cart_id']) ? (int)$_POST['cart_id'] : 0;
$payment_method = isset($_POST['payment_method']) ? trim((string)$_POST['payment_method']) : '';
$buyer_phone    = isset($_POST['customer_phone']) ? trim((string)$_POST['customer_phone']) : '';
$delivery_notes = isset($_POST['notes']) ? trim((string)$_POST['notes']) : '';

checkout_log("FORM_DATA", [
    'sale_id' => $sale_id,
    'cart_id' => $cart_id,
    'payment_method' => $payment_method,
    'has_phone' => $buyer_phone !== '',
    'has_notes' => $delivery_notes !== ''
]);

if ($sale_id <= 0) { $_SESSION['error'] = "ID de venta inválido"; header('Location: checkout.php'); exit; }
if ($cart_id <= 0) { $_SESSION['error'] = "ID de carrito inválido"; header('Location: checkout.php?sale_id='.$sale_id); exit; }
if (!in_array($payment_method, ['paypal','sinpe'], true)) {
    $_SESSION['error'] = "Método de pago inválido";
    header('Location: checkout.php?sale_id='.$sale_id); exit;
}

// ===== DB + USER =====
$pdo = db();

$st = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE id=?");
$st->execute([$user_id]);
$user = $st->fetch(PDO::FETCH_ASSOC);
if (!$user) { $_SESSION['error']="Usuario no encontrado"; header('Location: checkout.php?sale_id='.$sale_id); exit; }
if ($buyer_phone === '') $buyer_phone = (string)($user['phone'] ?? '');
checkout_log("USER_OK", ['email'=>$user['email'] ?? '']);

// ===== ITEMS (solo del sale_id) =====
$has_prod_tax_rate = false;
try { $probe = $pdo->query("SELECT tax_rate FROM products LIMIT 1"); if ($probe !== false) $has_prod_tax_rate = true; }
catch(Throwable $e){ $has_prod_tax_rate = false; }

$effTaxSelect = $has_prod_tax_rate
  ? ", COALESCE(ci.tax_rate, p.tax_rate) AS eff_tax_rate"
  : ", COALESCE(ci.tax_rate, 0) AS eff_tax_rate";

$sqlItems = "
  SELECT
    ci.product_id,
    ci.qty AS quantity,
    ci.unit_price,
    ci.tax_rate,
    ci.sale_id    AS ci_sale_id,
    p.name,
    p.price,
    p.sale_id     AS p_sale_id,
    p.affiliate_id,
    p.currency
    $effTaxSelect
  FROM cart_items ci
  JOIN products p ON p.id = ci.product_id
  WHERE ci.cart_id = ?
    AND (p.sale_id = ? OR ci.sale_id = ?)
  ORDER BY p.name
";
$st = $pdo->prepare($sqlItems);
$st->execute([$cart_id, $sale_id, $sale_id]);
$items = $st->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    $_SESSION['error'] = "No hay productos en el carrito para este espacio";
    header('Location: checkout.php?sale_id='.$sale_id); exit;
}
checkout_log("ITEMS_FOUND", ['count'=>count($items)]);

// ===== TOTALES =====
$subtotal = 0.0;
$tax_total = 0.0;
$affiliate_id = 0;

// Moneda (uniforme por espacio)
$currency = strtoupper((string)($items[0]['currency'] ?? 'CRC'));
if ($currency !== 'CRC' && $currency !== 'USD') $currency = 'CRC';

foreach ($items as $it) {
    $qty  = (float)($it['quantity'] ?? 1);
    $unit = isset($it['unit_price']) && $it['unit_price'] !== null ? (float)$it['unit_price'] : (float)($it['price'] ?? 0);
    $line = $qty * $unit;

    $raw = (float)($it['eff_tax_rate'] ?? 0);
    $tr  = 0.0;
    if ($raw > 1.0 && $raw <= 100.0) $tr = $raw/100.0; // porcentaje
    elseif ($raw >= 0.0 && $raw <= 1.0) $tr = $raw;    // fracción

    $subtotal += $line;
    $tax_total += ($line * $tr);

    if ($affiliate_id === 0) $affiliate_id = (int)$it['affiliate_id'];
}
$grand_total = $subtotal + $tax_total;

checkout_log("TOTALS", [
    'subtotal'=>$subtotal,'tax'=>$tax_total,'grand'=>$grand_total,
    'affiliate_id'=>$affiliate_id,'currency'=>$currency
]);

// ===== EXCHANGE RATE =====
$exchange_rate = 0.0;
try {
    $row = $pdo->query("SELECT exchange_rate FROM settings ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($row && (float)$row['exchange_rate']>0) $exchange_rate=(float)$row['exchange_rate'];
} catch(Throwable $e){}
if ($exchange_rate<=0) {
    $st=$pdo->prepare("SELECT val FROM settings WHERE `key`='exchange_rate' LIMIT 1");
    $st->execute(); $val=$st->fetchColumn();
    if ($val && (float)$val>0) $exchange_rate=(float)$val;
}
if ($exchange_rate<=0) $exchange_rate = 510.0;

// ===== INSERTAR ÓRDENES =====
$order_number = 'ORD-'.date('Ymd').'-'.strtoupper(substr(md5(uniqid('', true)), 0, 6));

try {
    $pdo->beginTransaction();

    $ins = $pdo->prepare("
      INSERT INTO orders (
        order_number, product_id, affiliate_id, sale_id,
        buyer_email, buyer_name, buyer_phone,
        qty, subtotal, tax, grand_total,
        payment_method, status, note, currency, exrate_used,
        created_at, updated_at
      ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $order_ids = [];
    foreach ($items as $it) {
        $qty  = (float)($it['quantity'] ?? 1);
        $unit = isset($it['unit_price']) && $it['unit_price'] !== null ? (float)$it['unit_price'] : (float)($it['price'] ?? 0);
        $line = $qty * $unit;

        $raw = (float)($it['eff_tax_rate'] ?? 0);
        $tr  = 0.0;
        if ($raw > 1.0 && $raw <= 100.0) $tr = $raw/100.0;
        elseif ($raw >= 0.0 && $raw <= 1.0) $tr = $raw;

        $line_tax = $line * $tr;
        $line_tot = $line + $line_tax;

        $ins->execute([
            $order_number,
            (int)$it['product_id'],
            (int)$affiliate_id,
            (int)$sale_id,
            (string)$user['email'],
            (string)$user['name'],
            (string)$buyer_phone,
            $qty,
            $line,
            $line_tax,
            $line_tot,
            $payment_method,
            'Pendiente',
            $delivery_notes,
            $currency,
            $exchange_rate,
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);

        $order_ids[] = (int)$pdo->lastInsertId();
    }

    $pdo->commit();
    checkout_log("ORDERS_CREATED", ['order_number'=>$order_number,'ids'=>$order_ids]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    checkout_log("ORDERS_TX_ERROR", ['err'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    $_SESSION['error'] = "Error al procesar la orden: " . $e->getMessage();
    header('Location: checkout.php?sale_id='.$sale_id);
    exit;
}

$first_order_id = $order_ids[0] ?? 0;

// ===== EMAIL CONFIRMACIÓN DE PEDIDO (Pendiente) =====
try {
    $detalle = '';
    foreach ($items as $it) {
        $qty  = (float)($it['quantity'] ?? 1);
        $unit = isset($it['unit_price']) && $it['unit_price'] !== null ? (float)$it['unit_price'] : (float)($it['price'] ?? 0);
        $line = $qty * $unit;
        $detalle .= '<tr>'.
            '<td>'.htmlspecialchars($it['name'] ?? 'Producto', ENT_QUOTES, 'UTF-8').'</td>'.
            '<td style="text-align:right">'.number_format($qty,2).'</td>'.
            '<td style="text-align:right">'.number_format($line,2).'</td>'.
            '</tr>';
    }
    $html = '
        <h2>Confirmación de pedido</h2>
        <p>Hola '.htmlspecialchars($user['name'] ?? 'Cliente', ENT_QUOTES, 'UTF-8').',</p>
        <p>Hemos recibido tu pedido con número <strong>'.htmlspecialchars($order_number, ENT_QUOTES, 'UTF-8').'</strong>.</p>
        <table width="100%" cellspacing="0" cellpadding="6" border="1" style="border-collapse:collapse">
            <thead><tr><th>Producto</th><th>Cant.</th><th>Total línea</th></tr></thead>
            <tbody>'.$detalle.'</tbody>
        </table>
        <p><strong>Subtotal:</strong> '.number_format($subtotal,2).' '.$currency.'<br>
           <strong>Impuestos:</strong> '.number_format($tax_total,2).' '.$currency.'<br>
           <strong>Total:</strong> '.number_format($grand_total,2).' '.$currency.'</p>
        <p>Estado actual: <strong>Pendiente de pago</strong>.</p>
    ';
    @send_mail((string)$user['email'], 'Confirmación de pedido '.$order_number, $html);
    checkout_log("EMAIL_SENT_ORDER_CONFIRM", ['to'=>$user['email'] ?? '']);
} catch (Throwable $e) {
    checkout_log("EMAIL_ERROR", ['err'=>$e->getMessage()]);
}

// ===== TOKEN reauth para order-success.php =====
function b64url(string $bin): string { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }
function sign_token(string $payloadJson, string $secret): string {
    return b64url(hash_hmac('sha256', $payloadJson, $secret, true));
}
$secret = defined('APP_KEY') ? (string)APP_KEY : (string)($GLOBALS['APP_KEY'] ?? 'supersecret_cambiar');
$payload = ['uid'=>$user_id, 'exp'=> time()+900];
$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$reauth = b64url($payloadJson) . '.' . sign_token($payloadJson, $secret);
checkout_log("REAUTH_TOKEN_BUILT", ['payload'=>['uid'=>$user_id,'exp'=>$payload['exp']]]);

// ===== REDIRECCIONES =====
if ($payment_method === 'sinpe') {
    $url = 'upload-proof.php?' . http_build_query([
        'order_id' => $first_order_id,
    ], '', '&', PHP_QUERY_RFC3986);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirigiendo...</title></head><body>
    <p>Redirigiendo a página de comprobante SINPE...</p>
    <script>location.href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'";</script>
    </body></html>';
    exit;
}

// PayPal
checkout_log("PAYPAL_FLOW_START", ['affiliate_id' => $affiliate_id]);

$st = $pdo->prepare("
    SELECT paypal_email
    FROM affiliate_payment_methods
    WHERE affiliate_id = ? AND active_paypal = 1
          AND paypal_email IS NOT NULL AND paypal_email <> ''
    LIMIT 1
");
$st->execute([$affiliate_id]);
$paypal_email = (string)$st->fetchColumn();

checkout_log("PAYPAL_EMAIL_QUERY", [
    'affiliate_id' => $affiliate_id,
    'paypal_email' => $paypal_email !== '' ? $paypal_email : 'NOT_FOUND'
]);

if ($paypal_email === '') {
    checkout_log("PAYPAL_ERROR_NO_EMAIL", ['affiliate_id' => $affiliate_id]);
    $_SESSION['error'] = "El vendedor no tiene configurado PayPal";
    header('Location: checkout.php?sale_id='.(int)$sale_id);
    exit;
}

// URLs
$scheme = $__isHttps ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base   = $scheme.'://'.$host;


/*$paypal_url = 'https://www.paypal.com/cgi-bin/webscr';*/

$paypal_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

$paypal_return = $base . '/order-success.php?' . http_build_query([
    'order_id' => $first_order_id,
    'reauth'   => $reauth
], '', '&', PHP_QUERY_RFC3986);

$paypal_cancel = $base . '/checkout.php?' . http_build_query([
    'sale_id' => $sale_id
], '', '&', PHP_QUERY_RFC3986);

$paypal_notify = $base . '/paypal_ipn.php';

checkout_log("PAYPAL_URLS", [
    'base' => $base,
    'return' => $paypal_return,
    'cancel' => $paypal_cancel,
    'notify' => $paypal_notify,
    'paypal_url' => $paypal_url
]);

// Cargar líneas para el carrito PayPal
$st = $pdo->prepare("
    SELECT o.id, o.qty, o.grand_total, o.currency, p.name
    FROM orders o
    JOIN products p ON p.id = o.product_id
    WHERE o.order_number = ?
    ORDER BY o.id ASC
");
$st->execute([$order_number]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

checkout_log("PAYPAL_ITEMS", [
    'order_number' => $order_number,
    'items_count' => count($rows),
    'items' => $rows
]);

// Form auto-submit (IMPORTANTE: rm=1 para volver por GET y que SameSite=Lax envíe cookie)
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Redirigiendo a PayPal...</title>
  <style>
    body{font-family:Arial,sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;background:#f5f5f5}
    .loader{text-align:center}
    .spinner{border:4px solid #f3f3f3;border-top:4px solid #0070ba;border-radius:50%;width:50px;height:50px;animation:spin 1s linear infinite;margin:0 auto 20px}
    @keyframes spin{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}
  </style>
</head>
<body>
  <div class="loader">
    <div class="spinner"></div>
    <p>Redirigiendo a PayPal...</p>
    <p style="color:#666;font-size:14px">Por favor espera...</p>
  </div>
  <form id="paypal_form" action="<?= htmlspecialchars($paypal_url, ENT_QUOTES, 'UTF-8') ?>" method="post">
    <input type="hidden" name="cmd" value="_cart">
    <input type="hidden" name="upload" value="1">
    <input type="hidden" name="business" value="<?= htmlspecialchars($paypal_email, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="currency_code" value="USD">
    <input type="hidden" name="return" value="<?= htmlspecialchars($paypal_return, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="cancel_return" value="<?= htmlspecialchars($paypal_cancel, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="notify_url" value="<?= htmlspecialchars($paypal_notify, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="rm" value="1"><!-- GET: mantiene cookie con SameSite=Lax -->
    <?php
    $custom_data = [
        'order_id'     => (int)$first_order_id,
        'order_number' => $order_number,
        'affiliate_id' => (int)$affiliate_id,
        'cart_id'      => (int)$cart_id,
        'sale_id'      => (int)$sale_id,
        'uid'          => (int)$user_id
    ];
    $custom_json = json_encode($custom_data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    checkout_log("PAYPAL_CUSTOM_FIELD", [
        'data' => $custom_data,
        'json' => $custom_json,
        'length' => strlen($custom_json)
    ]);
    ?>
    <input type="hidden" name="custom" value="<?= htmlspecialchars($custom_json, ENT_QUOTES, 'UTF-8') ?>">
<?php
$i = 1;
$paypal_items_log = [];
foreach ($rows as $r) {
    $qty        = (float)($r['qty'] ?? 1);
    $line_total = (float)($r['grand_total'] ?? 0);
    $cur_line   = strtoupper((string)($r['currency'] ?? $currency));
    if ($cur_line !== 'CRC' && $cur_line !== 'USD') $cur_line = 'CRC';

    // Convertir a USD
    $total_usd = ($cur_line === 'USD') ? $line_total : ($line_total / $exchange_rate);
    
    // PayPal requiere mínimo $0.01 USD total por línea
    $original_usd = $total_usd;
    if ($total_usd < 0.01) {
        $total_usd = 0.01;
        checkout_log("PAYPAL_AMOUNT_ADJUSTED", [
            'item' => $i,
            'product' => $r['name'] ?? 'Item '.$i,
            'original_crc' => $line_total,
            'original_usd' => number_format($original_usd, 4),
            'adjusted_usd' => 0.01
        ]);
    }
    
    // Calcular precio unitario
    $amount_usd = $total_usd / $qty;
    $amount_usd_formatted = number_format($amount_usd, 2, '.', '');
    
    $paypal_items_log[] = [
        'item_number' => $i,
        'name' => $r['name'] ?? 'Item '.$i,
        'qty' => $qty,
        'original_total_crc' => $line_total,
        'original_total_usd' => number_format($original_usd, 4),
        'final_total_usd' => number_format($total_usd, 4),
        'unit_price_usd' => $amount_usd_formatted
    ];
?>
    <input type="hidden" name="item_name_<?= $i ?>" value="<?= htmlspecialchars((string)($r['name'] ?? ('Item '.$i)), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="item_number_<?= $i ?>" value="<?= (int)$i ?>">
    <input type="hidden" name="amount_<?= $i ?>" value="<?= $amount_usd_formatted ?>">
    <input type="hidden" name="quantity_<?= $i ?>" value="<?= $qty ?>">
<?php
    $i++;
}
checkout_log("PAYPAL_FORM_ITEMS_FINAL", $paypal_items_log);
?>
  </form>
  <script>document.getElementById("paypal_form").submit();</script>
</body>
</html>