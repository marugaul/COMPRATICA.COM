<?php
declare(strict_types=1);
/**
 * api/paypal-capture-order.php
 * Captura el pago de una orden PayPal Orders API v2 y, solo si el pago
 * es exitoso, crea las órdenes en la BD (estado "Pagado") y envía correos.
 *
 * Recibe:  POST { orderID: "PAYPAL_ORDER_ID" }
 * Retorna: { success: true, redirect_url: "..." }
 *          { success: false, error: "..." }
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

$logFile = __DIR__ . '/../logs/paypal_sdk.log';
if (!is_dir(dirname($logFile))) { @mkdir(dirname($logFile), 0777, true); }
function cap_log(string $msg, $data = null): void {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] CAPTURE $msg";
    if ($data !== null) $line .= " | " . json_encode($data, JSON_UNESCAPED_UNICODE);
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/email_template.php';

header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PHPSESSID');
    session_start();
}

$user_id = (int)($_SESSION['user_id'] ?? $_SESSION['uid'] ?? 0);
if ($user_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$checkout = $_SESSION['checkout_paypal'] ?? null;
if (!$checkout || empty($checkout['order_number'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Sesión de pago expirada. Por favor regresa al checkout.']);
    exit;
}

$input           = json_decode((string)file_get_contents('php://input'), true);
$paypal_order_id = trim((string)($input['orderID'] ?? ''));
if ($paypal_order_id === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'orderID requerido']);
    exit;
}

// Extraer datos del checkout de sesión
$order_number   = (string)$checkout['order_number'];
$items          = (array)($checkout['items'] ?? []);
$user_email     = (string)($checkout['user_email'] ?? '');
$user_name      = (string)($checkout['user_name'] ?? 'Cliente');
$user_phone     = (string)($checkout['user_phone'] ?? '');
$subtotal       = (float)($checkout['subtotal'] ?? 0);
$tax_total      = (float)($checkout['tax_total'] ?? 0);
$grand_total    = (float)($checkout['grand_total'] ?? 0);
$currency       = (string)($checkout['currency'] ?? 'CRC');
$exchange_rate  = (float)($checkout['exchange_rate'] ?? 510);
$affiliate_id   = (int)($checkout['affiliate_id'] ?? 0);
$sale_id        = (int)($checkout['sale_id'] ?? 0);
$cart_id        = (int)($checkout['cart_id'] ?? 0);
$delivery_notes = (string)($checkout['delivery_notes'] ?? '');
$reauth         = (string)($checkout['reauth'] ?? '');

cap_log("START", [
    'paypal_order_id' => $paypal_order_id,
    'order_number'    => $order_number,
    'user_id'         => $user_id,
    'grand_total'     => $grand_total,
    'currency'        => $currency,
]);

// ===== PayPal: Access Token =====
$mode      = defined('PAYPAL_MODE') ? PAYPAL_MODE : 'live';
$base_url  = ($mode === 'sandbox')
    ? 'https://api-m.sandbox.paypal.com'
    : 'https://api-m.paypal.com';
$client_id = defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : '';
$secret    = defined('PAYPAL_SECRET')    ? PAYPAL_SECRET    : '';

$ch = curl_init($base_url . '/v1/oauth2/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_USERPWD        => "$client_id:$secret",
    CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$token_resp   = (string)curl_exec($ch);
curl_close($ch);
$access_token = (string)(json_decode($token_resp, true)['access_token'] ?? '');

if ($access_token === '') {
    cap_log("TOKEN_ERROR");
    echo json_encode(['success' => false, 'error' => 'No se pudo autenticar con PayPal']);
    exit;
}

// ===== PayPal: Capturar pago =====
$ch = curl_init($base_url . '/v2/checkout/orders/' . urlencode($paypal_order_id) . '/capture');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => '{}',
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
        'Prefer: return=representation',
    ],
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$capture_resp = (string)curl_exec($ch);
$http_code    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$capture_data = json_decode($capture_resp, true);
$pp_status    = (string)($capture_data['status'] ?? '');

cap_log("CAPTURE_RESP", ['http' => $http_code, 'status' => $pp_status]);

if ($http_code !== 201 || $pp_status !== 'COMPLETED') {
    $err = $capture_data['message'] ?? $capture_data['error_description'] ?? 'Pago no completado';
    cap_log("CAPTURE_FAILED", ['http' => $http_code, 'err' => $err, 'resp' => $capture_data]);
    echo json_encode(['success' => false, 'error' => 'Error al procesar el pago: ' . $err]);
    exit;
}

// ID de transacción de la captura
$txn_id = (string)(
    $capture_data['purchase_units'][0]['payments']['captures'][0]['id'] ?? $paypal_order_id
);
cap_log("CAPTURE_OK", ['txn_id' => $txn_id]);

// ===== BD: Crear órdenes (estado Pagado, ya que el pago fue exitoso) =====
$pdo = db();
try {
    $pdo->beginTransaction();

    $ins = $pdo->prepare("
      INSERT INTO orders (
        order_number, product_id, affiliate_id, sale_id,
        buyer_email, buyer_name, buyer_phone,
        qty, subtotal, tax, grand_total,
        payment_method, status, paypal_txn_id,
        note, currency, exrate_used,
        created_at, updated_at
      ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $order_ids = [];
    foreach ($items as $it) {
        $qty  = (float)($it['quantity'] ?? 1);
        $pid  = (int)($it['product_id'] ?? 0);

        // Verificar stock disponible antes de crear la orden (dentro de la transacción)
        if ($pid > 0 && $qty > 0) {
            $stk = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
            $stk->execute([$pid]);
            $availableStock = (float)($stk->fetchColumn() ?? 0);
            if ($availableStock < $qty) {
                $pdo->rollBack();
                $productName = htmlspecialchars($it['name'] ?? 'producto');
                cap_log("STOCK_INSUFICIENTE", ['product_id' => $pid, 'stock' => $availableStock, 'qty' => $qty]);
                echo json_encode(['success' => false, 'error' => "Lo sentimos, el producto \"$productName\" ya no tiene suficiente stock disponible."]);
                exit;
            }
        }

        $unit = isset($it['unit_price']) && $it['unit_price'] !== null
            ? (float)$it['unit_price']
            : (float)($it['price'] ?? 0);
        $line = $qty * $unit;

        $raw = (float)($it['eff_tax_rate'] ?? 0);
        $tr  = 0.0;
        if ($raw > 1.0 && $raw <= 100.0)     $tr = $raw / 100.0;
        elseif ($raw >= 0.0 && $raw <= 1.0)  $tr = $raw;

        $line_tax = $line * $tr;
        $line_tot = $line + $line_tax;

        $ins->execute([
            $order_number,
            $pid,
            $affiliate_id,
            $sale_id,
            $user_email,
            $user_name,
            $user_phone,
            $qty,
            $line,
            $line_tax,
            $line_tot,
            'paypal',
            'Pagado',
            $txn_id,
            $delivery_notes,
            $currency,
            $exchange_rate,
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s'),
        ]);
        $order_ids[] = (int)$pdo->lastInsertId();

        // Descontar stock de forma atómica (solo si hay suficiente)
        if ($pid > 0 && $qty > 0) {
            $pdo->prepare("
                UPDATE products
                   SET stock = stock - ?,
                       updated_at = datetime('now')
                 WHERE id = ? AND stock >= ?
            ")->execute([$qty, $pid, $qty]);
        }
    }

    // Limpiar carrito del sale_id
    if ($cart_id > 0 && $sale_id > 0) {
        $pdo->prepare("
            DELETE FROM cart_items
             WHERE cart_id = ?
               AND (sale_id = ? OR product_id IN (SELECT id FROM products WHERE sale_id = ?))
        ")->execute([$cart_id, $sale_id, $sale_id]);
    } elseif ($user_id > 0 && $sale_id > 0) {
        $st = $pdo->prepare("SELECT id FROM carts WHERE user_id=? ORDER BY id DESC LIMIT 1");
        $st->execute([$user_id]);
        $fc = (int)($st->fetchColumn() ?: 0);
        if ($fc > 0) {
            $pdo->prepare("
                DELETE FROM cart_items
                 WHERE cart_id = ?
                   AND (sale_id = ? OR product_id IN (SELECT id FROM products WHERE sale_id = ?))
            ")->execute([$fc, $sale_id, $sale_id]);
        }
    }

    $pdo->commit();
    cap_log("ORDERS_CREATED_PAGADO", ['order_number' => $order_number, 'ids' => $order_ids, 'txn' => $txn_id]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    cap_log("DB_ERROR", ['err' => $e->getMessage()]);
    echo json_encode(['success' => false, 'error' => 'Error al registrar la orden. Contacta soporte con TXN: ' . $txn_id]);
    exit;
}

$first_order_id = $order_ids[0] ?? 0;

// ===== Emails de confirmación de pago =====
try {
    // Buscar datos del afiliado (vendedor)
    $aff_email = '';
    $aff_name  = 'Vendedor';
    if ($affiliate_id > 0) {
        $st = $pdo->prepare("SELECT email, name FROM affiliates WHERE id=? LIMIT 1");
        $st->execute([$affiliate_id]);
        $aff_row  = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $aff_email = strtolower(trim((string)($aff_row['email'] ?? '')));
        $aff_name  = (string)($aff_row['name'] ?? 'Vendedor');
    }

    // Buscar título de la venta
    $sale_title = '';
    try {
        $st = $pdo->prepare("SELECT title FROM sales WHERE id=? LIMIT 1");
        $st->execute([$sale_id]);
        $sale_title = (string)($st->fetchColumn() ?: '');
    } catch (Throwable $e) {}

    // Construir items para el template de email
    $email_items_paypal = [];
    foreach ($items as $it) {
        $qty  = (float)($it['quantity'] ?? 1);
        $unit = isset($it['unit_price']) && $it['unit_price'] !== null
            ? (float)$it['unit_price'] : (float)($it['price'] ?? 0);
        $email_items_paypal[] = ['name' => $it['name'] ?? 'Producto', 'qty' => $qty, 'unit_price' => $unit, 'line_total' => $qty * $unit];
    }

    $buyer_email_lower = strtolower($user_email);
    $order_no_safe_pp  = htmlspecialchars($order_number, ENT_QUOTES, 'UTF-8');
    $txn_safe          = htmlspecialchars($txn_id, ENT_QUOTES, 'UTF-8');

    // --- Correo al comprador ---
    if ($user_email !== '') {
        $sale_tag = $sale_title ? ' &mdash; <em>' . htmlspecialchars($sale_title, ENT_QUOTES, 'UTF-8') . '</em>' : '';
        $body_buyer_pp = '
          <div style="text-align:center;margin-bottom:24px;">
            <span style="font-size:40px;">&#10003;</span>
            <h2 style="margin:8px 0 4px;font-size:22px;color:#2e7d32;">Pago confirmado</h2>
            <p style="margin:0;font-size:13px;color:#999;">Orden: <strong style="color:#333;">' . $order_no_safe_pp . '</strong>' . $sale_tag . '</p>
          </div>
          <p style="font-size:15px;margin:0 0 16px;">Hola <strong>' . htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
          <p style="font-size:15px;margin:0 0 20px;color:#555;">Tu pago fue procesado exitosamente. Aquí está el resumen de tu compra:</p>
          ' . email_product_table($email_items_paypal, $currency) . '
          ' . email_total_block($grand_total, 0, $grand_total, $currency) . '
          <p style="margin:20px 0 0;font-size:13px;color:#999;">Estado: ' . email_status_badge('Pagado') . '</p>
          <p style="margin:8px 0 0;font-size:12px;color:#bbb;">TXN PayPal: ' . $txn_safe . '</p>
        ';
        @send_mail($user_email, 'Pago confirmado — Orden ' . $order_number, email_html($body_buyer_pp));
        cap_log("EMAIL_BUYER", ['to' => $user_email]);
    }

    // --- Correo al vendedor (afiliado) ---
    if ($aff_email !== '' && $aff_email !== $buyer_email_lower) {
        $body_aff_pp = '
          <h2 style="margin:0 0 4px;font-size:22px;color:#333;">Pago recibido en tu tienda</h2>
          <p style="margin:0 0 20px;font-size:13px;color:#999;">Orden: <strong style="color:#333;">' . $order_no_safe_pp . '</strong></p>
          <p style="font-size:15px;margin:0 0 16px;">Hola <strong>' . htmlspecialchars($aff_name, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
          <p style="font-size:15px;margin:0 0 20px;color:#555;">
            Se confirmó el pago de
            <strong>' . htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8') . '</strong>
            (<a href="mailto:' . htmlspecialchars($user_email, ENT_QUOTES, 'UTF-8') . '" style="color:#e53935;">' . htmlspecialchars($user_email, ENT_QUOTES, 'UTF-8') . '</a>).
          </p>
          ' . email_product_table($email_items_paypal, $currency) . '
          ' . email_total_block($grand_total, 0, $grand_total, $currency) . '
          <p style="margin:20px 0 0;font-size:13px;color:#999;">Estado: ' . email_status_badge('Pagado') . '</p>
          <p style="margin:8px 0 0;font-size:12px;color:#bbb;">TXN PayPal: ' . $txn_safe . '</p>
        ';
        @send_mail($aff_email, '[COMPRATICA] Pago recibido — Orden ' . $order_number, email_html($body_aff_pp));
        cap_log("EMAIL_AFFILIATE", ['to' => $aff_email]);
    }

    // --- Correo al admin ---
    $admin_email = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : '';
    if ($admin_email !== ''
        && strtolower($admin_email) !== $buyer_email_lower
        && strtolower($admin_email) !== $aff_email
    ) {
        $body_admin_pp = '
          <h2 style="margin:0 0 4px;font-size:20px;color:#333;">Pago PayPal confirmado</h2>
          <p style="margin:0 0 20px;font-size:13px;color:#999;">Orden: <strong style="color:#333;">' . $order_no_safe_pp . '</strong></p>
          <p style="font-size:14px;margin:0 0 8px;"><strong>Comprador:</strong> ' . htmlspecialchars($user_email, ENT_QUOTES, 'UTF-8') . '</p>
          ' . email_product_table($email_items_paypal, $currency) . '
          ' . email_total_block($grand_total, 0, $grand_total, $currency) . '
          <p style="margin:20px 0 0;font-size:12px;color:#bbb;">TXN: ' . $txn_safe . '</p>
        ';
        @send_mail($admin_email, '[COMPRATICA] Pago PayPal — ' . $order_number, email_html($body_admin_pp));
    }
} catch (Throwable $e) {
    cap_log("EMAIL_ERROR", ['err' => $e->getMessage()]);
}

// Limpiar sesión checkout_paypal
unset($_SESSION['checkout_paypal']);

// Construir URL de éxito
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'compratica.com';
$redirect_url = $scheme . '://' . $host . '/order-success.php?' . http_build_query(
    ['order_id' => $first_order_id, 'reauth' => $reauth],
    '', '&', PHP_QUERY_RFC3986
);

cap_log("SUCCESS", ['order_number' => $order_number, 'first_order_id' => $first_order_id, 'redirect' => $redirect_url]);
echo json_encode(['success' => true, 'redirect_url' => $redirect_url]);
