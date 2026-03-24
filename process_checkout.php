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
require_once __DIR__ . '/includes/email_template.php';

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
$delivery_notes_raw = isset($_POST['notes'])       ? trim((string)$_POST['notes'])       : '';
$location_url       = isset($_POST['location_url'])  ? trim((string)$_POST['location_url'])  : '';
$otras_senas        = isset($_POST['otras_senas'])   ? trim((string)$_POST['otras_senas'])   : '';

// Combinar dirección de entrega y otras señas en delivery_notes
$delivery_parts = array_filter([$delivery_notes_raw]);
if ($location_url !== '') $delivery_parts[] = 'Ubicación: ' . $location_url;
if ($otras_senas  !== '') $delivery_parts[] = 'Otras señas: ' . $otras_senas;
$delivery_notes = implode(' | ', $delivery_parts);

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

// ===== TOKEN reauth =====
function b64url(string $bin): string { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }
function sign_token(string $payloadJson, string $secret): string {
    return b64url(hash_hmac('sha256', $payloadJson, $secret, true));
}
function build_reauth(int $uid): string {
    $secret = defined('APP_KEY') ? (string)APP_KEY : 'supersecret_cambiar';
    $payload = ['uid' => $uid, 'exp' => time() + 1800];
    $pj = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return b64url($pj) . '.' . sign_token($pj, $secret);
}

// ===== HELPER: insertar órdenes en BD =====
function insert_orders(
    PDO $pdo, string $order_number, array $items,
    int $affiliate_id, int $sale_id,
    string $buyer_email, string $buyer_name, string $buyer_phone,
    string $payment_method, string $status,
    string $delivery_notes, string $currency, float $exchange_rate
): array {
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
        $pid  = (int)($it['product_id'] ?? 0);

        // Verificar stock disponible antes de insertar (dentro de la transacción)
        if ($pid > 0 && $qty > 0) {
            $stk = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
            $stk->execute([$pid]);
            $availableStock = (float)($stk->fetchColumn() ?? 0);
            if ($availableStock < $qty) {
                throw new RuntimeException('Sin stock: ' . ($it['name'] ?? 'producto') . ' (disponible: ' . (int)$availableStock . ')');
            }
        }

        $unit = isset($it['unit_price']) && $it['unit_price'] !== null
            ? (float)$it['unit_price']
            : (float)($it['price'] ?? 0);
        $line = $qty * $unit;

        $raw = (float)($it['eff_tax_rate'] ?? 0);
        $tr  = 0.0;
        if ($raw > 1.0 && $raw <= 100.0)      $tr = $raw / 100.0;
        elseif ($raw >= 0.0 && $raw <= 1.0)   $tr = $raw;

        $line_tax = $line * $tr;
        $line_tot = $line + $line_tax;

        $ins->execute([
            $order_number, $pid, $affiliate_id, $sale_id,
            $buyer_email, $buyer_name, $buyer_phone,
            $qty, $line, $line_tax, $line_tot,
            $payment_method, $status,
            $delivery_notes, $currency, $exchange_rate,
            date('Y-m-d H:i:s'), date('Y-m-d H:i:s')
        ]);
        $order_ids[] = (int)$pdo->lastInsertId();

        // Descontar stock de forma atómica
        if ($pid > 0 && $qty > 0) {
            $pdo->prepare("
                UPDATE products
                   SET stock = stock - ?,
                       updated_at = datetime('now')
                 WHERE id = ? AND stock >= ?
            ")->execute([$qty, $pid, $qty]);
        }
    }
    return $order_ids;
}

// ===== SINPE: crear orden y redirigir a comprobante =====
if ($payment_method === 'sinpe') {
    $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
    try {
        $pdo->beginTransaction();
        $order_ids = insert_orders(
            $pdo, $order_number, $items,
            $affiliate_id, $sale_id,
            (string)$user['email'], (string)$user['name'], $buyer_phone,
            'sinpe', 'Pendiente',
            $delivery_notes, $currency, $exchange_rate
        );
        $pdo->commit();
        checkout_log("SINPE_ORDERS_CREATED", ['order_number' => $order_number, 'ids' => $order_ids]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        checkout_log("SINPE_TX_ERROR", ['err' => $e->getMessage()]);
        $_SESSION['error'] = "Error al procesar la orden: " . $e->getMessage();
        header('Location: checkout.php?sale_id=' . $sale_id);
        exit;
    }

    $first_order_id = $order_ids[0] ?? 0;

    // Email "Pendiente de pago" al comprador
    try {
        $detalle = '';
        foreach ($items as $it) {
            $qty  = (float)($it['quantity'] ?? 1);
            $unit = isset($it['unit_price']) && $it['unit_price'] !== null ? (float)$it['unit_price'] : (float)($it['price'] ?? 0);
            $line = $qty * $unit;
            $detalle .= '<tr><td>' . htmlspecialchars($it['name'] ?? 'Producto', ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="text-align:right">' . number_format($qty, 2) . '</td>'
                . '<td style="text-align:right">' . number_format($line, 2) . '</td></tr>';
        }
        // Build items array for the template helper
        $email_items = [];
        foreach ($items as $it) {
            $qty  = (float)($it['quantity'] ?? 1);
            $unit = isset($it['unit_price']) && $it['unit_price'] !== null ? (float)$it['unit_price'] : (float)($it['price'] ?? 0);
            $email_items[] = ['name' => $it['name'] ?? 'Producto', 'qty' => $qty, 'unit_price' => $unit, 'line_total' => $qty * $unit];
        }
        $client_name = htmlspecialchars($user['name'] ?? 'Cliente', ENT_QUOTES, 'UTF-8');
        $order_no_safe = htmlspecialchars($order_number, ENT_QUOTES, 'UTF-8');
        $body_buyer = '
          <h2 style="margin:0 0 4px;font-size:22px;color:#333;">Confirmación de pedido</h2>
          <p style="margin:0 0 20px;font-size:13px;color:#999;">Orden: <strong style="color:#333;">' . $order_no_safe . '</strong></p>
          <p style="font-size:15px;margin:0 0 16px;">Hola <strong>' . $client_name . '</strong>,</p>
          <p style="font-size:15px;margin:0 0 20px;color:#555;">
            Hemos recibido tu pedido. A continuación el detalle:
          </p>
          ' . email_product_table($email_items, $currency) . '
          ' . email_total_block($grand_total, 0, $grand_total, $currency) . '
          <div style="margin-top:24px;padding:16px;background:#fff8e1;border-left:4px solid #f59e0b;border-radius:4px;">
            <p style="margin:0;font-size:14px;color:#78350f;">
              <strong>Siguiente paso:</strong> Realiza el pago por SINPE Móvil y sube tu comprobante para que podamos confirmar tu pedido.
            </p>
          </div>
          <p style="margin:24px 0 0;font-size:13px;color:#999;">Estado actual: ' . email_status_badge('Pendiente de pago') . '</p>
        ';
        $html_buyer = email_html($body_buyer);
        @send_mail((string)$user['email'], 'Pedido recibido — ' . $order_number, $html_buyer);

        $admin_email      = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : '';
        $buyer_email_lower = strtolower((string)($user['email'] ?? ''));
        if ($admin_email !== '' && strtolower($admin_email) !== $buyer_email_lower) {
            $body_admin = '
              <h2 style="margin:0 0 4px;font-size:20px;color:#333;">Pedido SINPE pendiente</h2>
              <p style="margin:0 0 20px;font-size:13px;color:#999;">Orden: <strong style="color:#333;">' . $order_no_safe . '</strong></p>
              <p style="font-size:14px;margin:0 0 8px;"><strong>Comprador:</strong> ' . htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>
              ' . email_product_table($email_items, $currency) . '
              ' . email_total_block($grand_total, 0, $grand_total, $currency) . '
              <p style="margin:20px 0 0;font-size:13px;color:#999;">Estado: ' . email_status_badge('Pendiente de pago') . '</p>
            ';
            @send_mail($admin_email, '[COMPRATICA] Pedido SINPE — ' . $order_number, email_html($body_admin));
        }
    } catch (Throwable $e) {
        checkout_log("SINPE_EMAIL_ERROR", ['err' => $e->getMessage()]);
    }

    $url = 'upload-proof.php?' . http_build_query(['order_id' => $first_order_id], '', '&', PHP_QUERY_RFC3986);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirigiendo...</title></head><body>'
        . '<p>Redirigiendo a página de comprobante SINPE...</p>'
        . '<script>location.href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '";</script>'
        . '</body></html>';
    exit;
}

// ===== PAYPAL: validar email del vendedor =====
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
    header('Location: checkout.php?sale_id=' . (int)$sale_id);
    exit;
}

// Pre-generar order_number (se insertará a BD solo tras captura exitosa)
$order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

// Total en USD para PayPal
$total_usd_sdk = ($currency === 'CRC' && $exchange_rate > 0)
    ? round($grand_total / $exchange_rate, 2)
    : round($grand_total, 2);
if ($total_usd_sdk < 0.01) $total_usd_sdk = 0.01;

// Guardar todos los datos necesarios en sesión para los endpoints del SDK
$_SESSION['checkout_paypal'] = [
    'order_number'   => $order_number,
    'user_id'        => $user_id,
    'user_name'      => (string)($user['name'] ?? ''),
    'user_email'     => (string)($user['email'] ?? ''),
    'user_phone'     => $buyer_phone,
    'items'          => $items,
    'subtotal'       => $subtotal,
    'tax_total'      => $tax_total,
    'grand_total'    => $grand_total,
    'currency'       => $currency,
    'exchange_rate'  => $exchange_rate,
    'affiliate_id'   => $affiliate_id,
    'sale_id'        => $sale_id,
    'cart_id'        => $cart_id,
    'delivery_notes' => $delivery_notes,
    'paypal_email'   => $paypal_email,
    'total_usd'      => $total_usd_sdk,
    'reauth'         => build_reauth($user_id),
];
checkout_log("PAYPAL_SDK_READY", [
    'order_number' => $order_number,
    'total_usd'    => $total_usd_sdk,
    'paypal_email' => $paypal_email,
]);

$paypal_client_id = defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Completar pago — Compratica</title>
  <script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars($paypal_client_id, ENT_QUOTES, 'UTF-8') ?>&currency=USD&intent=capture&enable-funding=googlepay,applepay,card&disable-funding=paylater,venmo" data-namespace="paypal_sdk"></script>
  <style>
    *{box-sizing:border-box}
    body{font-family:Arial,sans-serif;display:flex;justify-content:center;align-items:flex-start;min-height:100vh;margin:0;background:#f5f5f5;padding:20px}
    .card{background:#fff;border-radius:8px;padding:30px;max-width:460px;width:100%;box-shadow:0 2px 12px rgba(0,0,0,.1)}
    h2{margin:0 0 8px;color:#333;font-size:1.4em}
    .order-ref{font-size:.85em;color:#888;margin:0 0 18px}
    .total{font-size:1.2em;font-weight:bold;color:#0070ba;margin:0 0 24px;padding:12px;background:#f0f7ff;border-radius:6px}
    #paypal-button-container{min-height:50px}
    #sdk-error{color:#c0392b;padding:10px 14px;background:#fdf0f0;border:1px solid #f5c6cb;border-radius:6px;display:none;margin-top:14px;font-size:.95em}
    .tip{font-size:.8em;color:#aaa;margin-top:12px;text-align:center}
    .back-link{display:block;text-align:center;margin-top:18px;padding:10px;border:1px solid #ddd;border-radius:6px;color:#555;text-decoration:none;font-size:.95em;transition:background .15s}
    .back-link:hover{background:#f5f5f5}
  </style>
</head>
<body>
  <div class="card">
    <h2>Completar pago</h2>
    <p class="order-ref">Orden: <?= htmlspecialchars($order_number, ENT_QUOTES, 'UTF-8') ?></p>
    <p class="total">Total: $<?= number_format($total_usd_sdk, 2) ?> USD</p>
    <div id="paypal-button-container"></div>
    <div id="sdk-error"></div>
    <p class="tip">Puedes pagar con tu cuenta PayPal o directamente con tarjeta de crédito/débito.</p>
    <a href="checkout.php?sale_id=<?= (int)$sale_id ?>" class="back-link">← Volver al checkout / elegir otro método de pago</a>
  </div>
  <script>
  var checkoutUrl = 'checkout.php?sale_id=<?= (int)$sale_id ?>';

  paypal_sdk.Buttons({
    style: { layout: 'vertical', label: 'pay', shape: 'rect', color: 'blue' },

    createOrder: function() {
      return fetch('/api/paypal-create-order.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' }
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.id) {
          showError(data.error || 'No se pudo iniciar el pago. Intenta de nuevo.');
          // Devolver promesa que nunca resuelve para que el SDK de PayPal
          // no muestre su propio mensaje de error genérico encima del nuestro
          return new Promise(function() {});
        }
        return data.id;
      })
      .catch(function(e) {
        showError('No se pudo conectar con PayPal. Verifica tu conexión e intenta de nuevo.');
        return new Promise(function() {});
      });
    },

    onApprove: function(data) {
      return fetch('/api/paypal-capture-order.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ orderID: data.orderID })
      })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (res.success) {
          window.location.href = res.redirect_url;
        } else {
          showError(res.error || 'Error al procesar el pago. Intenta de nuevo.');
        }
      })
      .catch(function(e) { showError('Error inesperado: ' + e.message); });
    },

    onCancel: function() {
      window.location.href = checkoutUrl;
    },

    onError: function(err) {
      showError('Error de PayPal: ' + (err.message || String(err)));
    }
  }).render('#paypal-button-container');

  function showError(msg) {
    var el = document.getElementById('sdk-error');
    el.textContent = msg;
    el.style.display = 'block';
  }
  </script>
</body>
</html>