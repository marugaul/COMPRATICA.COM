<?php
declare(strict_types=1);
/**
 * api/paypal-capture-order.php
 * Captura el pago de una orden PayPal Orders API v2,
 * actualiza la BD y limpia el carrito.
 * Llamado por el PayPal JS SDK (onApprove callback).
 * Recibe: { orderID: "..." }
 * Retorna: { success: true, redirect_url: "..." } o { success: false, error: "..." }
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

function cap_log(string $msg, $data = null): void {
    $logFile = __DIR__ . '/../logs/paypal_sdk.log';
    $ts   = date('Y-m-d H:i:s');
    $line = "[$ts] CAPTURE $msg";
    if ($data !== null) $line .= " | " . json_encode($data, JSON_UNESCAPED_UNICODE);
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json');

// Sesión
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

$pending = $_SESSION['pending_paypal'] ?? null;
if (!$pending || empty($pending['order_number'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No hay orden pendiente en sesión']);
    exit;
}

$input          = json_decode((string)file_get_contents('php://input'), true);
$paypal_order_id = trim((string)($input['orderID'] ?? ''));
if ($paypal_order_id === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'orderID requerido']);
    exit;
}

$order_number   = (string)$pending['order_number'];
$first_order_id = (int)($pending['first_order_id'] ?? 0);
$sale_id        = (int)($pending['sale_id'] ?? 0);
$cart_id        = (int)($pending['cart_id'] ?? 0);
$reauth         = (string)($pending['reauth'] ?? '');

cap_log("START", [
    'paypal_order_id' => $paypal_order_id,
    'order_number'    => $order_number,
    'user_id'         => $user_id,
]);

// Config PayPal
$mode      = defined('PAYPAL_MODE') ? PAYPAL_MODE : 'live';
$base_url  = ($mode === 'sandbox')
    ? 'https://api-m.sandbox.paypal.com'
    : 'https://api-m.paypal.com';
$client_id = defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : '';
$secret    = defined('PAYPAL_SECRET')    ? PAYPAL_SECRET    : '';

// --- Obtener access token ---
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
$token_resp   = curl_exec($ch);
curl_close($ch);
$token_data   = json_decode((string)$token_resp, true);
$access_token = (string)($token_data['access_token'] ?? '');

if ($access_token === '') {
    cap_log("TOKEN_ERROR");
    echo json_encode(['success' => false, 'error' => 'No se pudo autenticar con PayPal']);
    exit;
}

// --- Capturar la orden ---
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
$capture_resp = curl_exec($ch);
$http_code    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$capture_data = json_decode((string)$capture_resp, true);
cap_log("CAPTURE_RESP", [
    'http'   => $http_code,
    'status' => $capture_data['status'] ?? null,
]);

if ($http_code !== 201 || ($capture_data['status'] ?? '') !== 'COMPLETED') {
    $err = $capture_data['message'] ?? $capture_data['error_description'] ?? 'Error al capturar el pago';
    cap_log("CAPTURE_FAILED", ['http' => $http_code, 'err' => $err, 'resp' => $capture_data]);
    echo json_encode(['success' => false, 'error' => $err]);
    exit;
}

// Extraer transaction ID de la captura
$txn_id = (string)(
    $capture_data['purchase_units'][0]['payments']['captures'][0]['id'] ?? $paypal_order_id
);
cap_log("CAPTURE_OK", ['txn_id' => $txn_id]);

// --- Actualizar BD ---
$pdo = db();
try {
    $pdo->beginTransaction();

    // 1) Marcar órdenes como Pagado (idempotente via COALESCE)
    $pdo->prepare("
        UPDATE orders
           SET status        = 'Pagado',
               paypal_txn_id = COALESCE(?, paypal_txn_id),
               updated_at    = datetime('now')
         WHERE order_number  = ?
    ")->execute([$txn_id, $order_number]);
    cap_log("ORDERS_MARKED_PAGADO", ['order_number' => $order_number]);

    // 2) Limpiar carrito del sale_id
    if ($cart_id > 0 && $sale_id > 0) {
        $del = $pdo->prepare("
            DELETE FROM cart_items
             WHERE cart_id  = ?
               AND (sale_id = ? OR product_id IN (SELECT id FROM products WHERE sale_id = ?))
        ");
        $del->execute([$cart_id, $sale_id, $sale_id]);
        cap_log("CART_CLEARED_BY_CART_ID", ['deleted' => $del->rowCount()]);
    } elseif ($user_id > 0 && $sale_id > 0) {
        $st = $pdo->prepare("SELECT id FROM carts WHERE user_id=? ORDER BY id DESC LIMIT 1");
        $st->execute([$user_id]);
        $fc = (int)($st->fetchColumn() ?: 0);
        if ($fc > 0) {
            $del = $pdo->prepare("
                DELETE FROM cart_items
                 WHERE cart_id  = ?
                   AND (sale_id = ? OR product_id IN (SELECT id FROM products WHERE sale_id = ?))
            ");
            $del->execute([$fc, $sale_id, $sale_id]);
            cap_log("CART_CLEARED_BY_UID", ['cart_id' => $fc, 'deleted' => $del->rowCount()]);
        }
    }

    $pdo->commit();
    cap_log("TX_COMMIT");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    cap_log("DB_ERROR", ['err' => $e->getMessage()]);
    echo json_encode(['success' => false, 'error' => 'Error al actualizar la base de datos']);
    exit;
}

// --- Emails de confirmación ---
try {
    $st = $pdo->prepare("
        SELECT o.buyer_email, o.buyer_name, o.currency,
               s.title       AS sale_title,
               a.email       AS affiliate_email,
               a.name        AS affiliate_name
          FROM orders o
     LEFT JOIN sales      s ON s.id = o.sale_id
     LEFT JOIN affiliates a ON a.id = o.affiliate_id
         WHERE o.order_number = ?
         LIMIT 1
    ");
    $st->execute([$order_number]);
    $head = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    $st = $pdo->prepare("
        SELECT o.qty, o.grand_total, p.name AS product_name
          FROM orders  o
          JOIN products p ON p.id = o.product_id
         WHERE o.order_number = ?
    ");
    $st->execute([$order_number]);
    $lines = $st->fetchAll(PDO::FETCH_ASSOC);

    $list_html = '';
    $total     = 0.0;
    foreach ($lines as $ln) {
        $total     += (float)$ln['grand_total'];
        $list_html .= '<li>'
            . htmlspecialchars((string)$ln['product_name'], ENT_QUOTES, 'UTF-8')
            . ' — x' . (float)$ln['qty'] . '</li>';
    }

    $buyer_email     = (string)($head['buyer_email']     ?? '');
    $buyer_name      = (string)($head['buyer_name']      ?? 'Cliente');
    $currency        = strtoupper((string)($head['currency'] ?? 'CRC'));
    $sale_title      = (string)($head['sale_title']      ?? '');
    $affiliate_email = (string)($head['affiliate_email'] ?? '');
    $affiliate_name  = (string)($head['affiliate_name']  ?? 'Vendedor');

    // Correo al comprador
    if ($buyer_email !== '') {
        $html_buyer = "
            <h2 style='color:#27ae60;'>✅ Pago Confirmado</h2>
            <p>Hola <strong>" . htmlspecialchars($buyer_name, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
            <p>Tu pago fue <strong>confirmado exitosamente</strong>"
            . ($sale_title ? " para <strong>" . htmlspecialchars($sale_title, ENT_QUOTES, 'UTF-8') . "</strong>" : "")
            . ".</p>
            <p><strong>Orden:</strong> " . htmlspecialchars($order_number, ENT_QUOTES, 'UTF-8') . "</p>
            <ul>$list_html</ul>
            <p><strong>Total:</strong> " . number_format($total, 2) . " $currency</p>
        ";
        @send_mail($buyer_email, "✅ Pago Confirmado — Orden $order_number", $html_buyer);
        cap_log("EMAIL_BUYER_SENT", ['to' => $buyer_email]);
    }

    // Correo al vendedor (afiliado)
    if ($affiliate_email !== '' && strtolower($affiliate_email) !== strtolower($buyer_email)) {
        $html_aff = "
            <h2>Pago recibido en tu tienda</h2>
            <p>Hola <strong>" . htmlspecialchars($affiliate_name, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
            <p>Se confirmó el pago de la orden <strong>" . htmlspecialchars($order_number, ENT_QUOTES, 'UTF-8') . "</strong>.</p>
            <ul>$list_html</ul>
            <p><strong>Total:</strong> " . number_format($total, 2) . " $currency</p>
        ";
        @send_mail($affiliate_email, "[COMPRATICA] Pago confirmado — Orden $order_number", $html_aff);
        cap_log("EMAIL_AFFILIATE_SENT", ['to' => $affiliate_email]);
    }

    // Notificación al admin
    $admin_email = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : '';
    if ($admin_email !== ''
        && strtolower($admin_email) !== strtolower($buyer_email)
        && strtolower($admin_email) !== strtolower($affiliate_email)
    ) {
        $html_admin = "
            <h2>Pago confirmado — Orden $order_number</h2>
            <p>Comprador: " . htmlspecialchars($buyer_email, ENT_QUOTES, 'UTF-8') . "</p>
            <ul>$list_html</ul>
            <p><strong>Total:</strong> " . number_format($total, 2) . " $currency</p>
            <p><strong>PayPal TXN:</strong> " . htmlspecialchars($txn_id, ENT_QUOTES, 'UTF-8') . "</p>
        ";
        @send_mail($admin_email, "[COMPRATICA] Pago confirmado — Orden $order_number", $html_admin);
    }
} catch (Throwable $e) {
    cap_log("EMAIL_ERROR", ['err' => $e->getMessage()]);
}

// --- Limpiar sesión pending_paypal ---
unset($_SESSION['pending_paypal']);

// --- URL de redirección ---
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'compratica.com';
$redirect_url = $scheme . '://' . $host . '/order-success.php?' . http_build_query(
    ['order_id' => $first_order_id, 'reauth' => $reauth],
    '', '&', PHP_QUERY_RFC3986
);

cap_log("SUCCESS", ['redirect' => $redirect_url]);
echo json_encode(['success' => true, 'redirect_url' => $redirect_url]);
