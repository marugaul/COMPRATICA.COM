<?php
declare(strict_types=1);
/**
 * api/paypal-capture-order-emp.php
 * Captura el pago PayPal de emprendedoras y envía notificaciones.
 *
 * POST { orderID: "PAYPAL_ORDER_ID", sid: 123 }
 * Retorna: { success: true } | { success: false, error: "..." }
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

$logFile = __DIR__ . '/../logs/paypal_sdk.log';
if (!is_dir(dirname($logFile))) { @mkdir(dirname($logFile), 0777, true); }
function emp_cap_log(string $msg, $data = null): void {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] [EMP-CAP] $msg";
    if ($data !== null) $line .= " | " . json_encode($data, JSON_UNESCAPED_UNICODE);
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PHPSESSID');
    session_start();
}

$input           = json_decode((string)file_get_contents('php://input'), true) ?? [];
$paypal_order_id = trim((string)($input['orderID'] ?? ''));
$sid             = (int)($input['sid'] ?? 0);

if ($paypal_order_id === '' || $sid <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parámetros requeridos: orderID y sid']);
    exit;
}

$pending = $_SESSION['emp_paypal_pending'][$sid] ?? null;
if (!$pending) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Sesión de pago expirada. Por favor recarga la página.']);
    exit;
}

emp_cap_log("CAPTURE_START", ['pp_id' => $paypal_order_id, 'sid' => $sid]);

$mode      = defined('PAYPAL_MODE') ? PAYPAL_MODE : 'live';
$base_url  = ($mode === 'sandbox') ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
$client_id = defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : '';
$secret    = defined('PAYPAL_SECRET')    ? PAYPAL_SECRET    : '';

// --- Access token ---
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
$token_resp = (string)curl_exec($ch);
$token_http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$token_data   = json_decode($token_resp, true);
$access_token = (string)($token_data['access_token'] ?? '');
if ($token_http !== 200 || $access_token === '') {
    emp_cap_log("TOKEN_ERROR", ['http' => $token_http]);
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'No se pudo conectar con PayPal']);
    exit;
}

// --- Capturar orden ---
$ch = curl_init($base_url . "/v2/checkout/orders/{$paypal_order_id}/capture");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => '{}',
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
        'Prefer: return=representation',
    ],
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$cap_resp = (string)curl_exec($ch);
$cap_http  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$cap_data  = json_decode($cap_resp, true);
$cap_status = (string)($cap_data['status'] ?? '');

emp_cap_log("CAPTURE_RESP", ['http' => $cap_http, 'status' => $cap_status]);

if ($cap_http !== 201 || $cap_status !== 'COMPLETED') {
    $err = $cap_data['message'] ?? 'Error al capturar el pago. Intenta de nuevo.';
    emp_cap_log("CAPTURE_ERROR", ['err' => $err]);
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $err]);
    exit;
}

// Obtener transaction ID
$txn_id = '';
foreach (($cap_data['purchase_units'] ?? []) as $pu) {
    foreach (($pu['payments']['captures'] ?? []) as $cap) {
        if (isset($cap['id'])) { $txn_id = $cap['id']; break 2; }
    }
}

emp_cap_log("CAPTURE_OK", ['txn_id' => $txn_id]);

// --- Crear registros en entrepreneur_orders ---
try {
    require_once __DIR__ . '/../includes/db.php';
    $pdo_ord = db();
    $insOrd = $pdo_ord->prepare("
        INSERT INTO entrepreneur_orders
            (product_id, seller_user_id, buyer_name, buyer_email, buyer_phone, quantity, total_price, status,
             payment_method, payment_ref, shipping_method, shipping_zone, shipping_cost, shipping_address,
             created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,'confirmed','paypal',?,?,?,?,?,?,?)
    ");
    $pp_buyer_name  = (string)($pending['buyer_name']  ?? '');
    $pp_buyer_email = (string)($pending['buyer_email'] ?? '');
    $pp_buyer_phone = (string)($pending['buyer_phone'] ?? '');
    $pp_ship_method = (string)($pending['shipping_method']  ?? '');
    $pp_ship_zone   = (string)($pending['shipping_zone']    ?? '');
    $pp_ship_cost   = (int)($pending['shipping_cost']       ?? 0);
    $pp_ship_addr   = (string)($pending['shipping_address'] ?? '');
    foreach ((array)($pending['items'] ?? []) as $it) {
        $oPid   = (int)($it['product_id'] ?? 0);
        $oQty   = (int)($it['qty'] ?? 1);
        $oPrice = (float)($it['price'] ?? 0);
        $insOrd->execute([
            $oPid, $sid, $pp_buyer_name, $pp_buyer_email, $pp_buyer_phone,
            $oQty, $oQty * $oPrice,
            $txn_id,
            $pp_ship_method, $pp_ship_zone, $pp_ship_cost, $pp_ship_addr,
            date('Y-m-d H:i:s'), date('Y-m-d H:i:s'),
        ]);
        if ($oPid > 0 && $oQty > 0) {
            $pdo_ord->prepare("UPDATE entrepreneur_products SET stock = stock - ?, updated_at = datetime('now') WHERE id = ? AND stock >= ?")
                ->execute([$oQty, $oPid, $oQty]);
        }
    }
    emp_cap_log("DB_ORDERS_OK", ['sid' => $sid]);
} catch (Throwable $e) {
    emp_cap_log("DB_ORDERS_ERROR", ['err' => $e->getMessage()]);
}

// --- Limpiar carrito de ese vendedor en sesión ---
$cartItems = $_SESSION['emp_cart'] ?? [];
foreach ($cartItems as $pid => $item) {
    if ((int)($item['seller_id'] ?? 0) === $sid) {
        unset($_SESSION['emp_cart'][$pid]);
    }
}
unset($_SESSION['emp_paypal_pending'][$sid]);

// --- Enviar notificaciones ---
try {
    $seller_name  = (string)($pending['seller_name']  ?? 'Vendedor/a');
    $seller_email = (string)($pending['seller_email'] ?? '');
    $buyer_name   = (string)($pending['buyer_name']   ?? '');
    $buyer_email  = (string)($pending['buyer_email']  ?? '');
    $total_crc    = (int)($pending['total_crc']    ?? 0);
    $total_usd    = (float)($pending['total_usd']  ?? 0);
    $items        = (array)($pending['items']       ?? []);
    $order_ref    = (string)($pending['order_ref']  ?? 'EMP-' . $sid);

    $items_html = '';
    foreach ($items as $it) {
        $qty   = (int)($it['qty'] ?? 1);
        $price = (float)($it['price'] ?? 0);
        $items_html .= '<tr>
            <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;">' . htmlspecialchars($it['name'] ?? '') . '</td>
            <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;text-align:center;">' . $qty . '</td>
            <td style="padding:8px 12px;border-bottom:1px solid #f0f0f0;text-align:right;font-weight:700;">₡' . number_format($qty * $price, 0) . '</td>
        </tr>';
    }
    $table = '<table style="width:100%;border-collapse:collapse;margin:16px 0;">
        <thead><tr style="background:#f8f9fa;">
            <th style="padding:8px 12px;text-align:left;font-size:12px;color:#666;">Producto</th>
            <th style="padding:8px 12px;text-align:center;font-size:12px;color:#666;">Cant.</th>
            <th style="padding:8px 12px;text-align:right;font-size:12px;color:#666;">Total</th>
        </tr></thead>
        <tbody>' . $items_html . '</tbody>
    </table>';

    $txn_safe = htmlspecialchars($txn_id, ENT_QUOTES, 'UTF-8');
    $ref_safe = htmlspecialchars($order_ref, ENT_QUOTES, 'UTF-8');

    // Correo al comprador
    if ($buyer_email !== '') {
        $body_buyer = '<h2 style="color:#166534;margin:0 0 8px;">✅ Pago confirmado</h2>
            <p>Ref: <strong>' . $ref_safe . '</strong></p>
            <p>Tu pago a <strong>' . htmlspecialchars($seller_name) . '</strong> fue procesado exitosamente.</p>
            ' . $table . '
            <p style="font-size:1.1em;font-weight:700;">Total pagado: ₡' . number_format($total_crc, 0) . ' (≈ US$' . number_format($total_usd, 2) . ')</p>
            <p style="font-size:12px;color:#999;">TXN PayPal: ' . $txn_safe . '</p>';
        @send_mail($buyer_email, '✅ Pago confirmado — CompraTica', $body_buyer);
        emp_cap_log("EMAIL_BUYER", ['to' => $buyer_email]);
    }

    // Correo al vendedor
    if ($seller_email !== '' && strtolower($seller_email) !== strtolower($buyer_email)) {
        $body_seller = '<h2 style="color:#1e293b;margin:0 0 8px;">💰 Pago recibido en tu tienda</h2>
            <p>Ref: <strong>' . $ref_safe . '</strong></p>
            <p>El comprador <strong>' . htmlspecialchars($buyer_name ?: $buyer_email) . '</strong> realizó un pago vía PayPal.</p>
            ' . $table . '
            <p style="font-size:1.1em;font-weight:700;">Total: ₡' . number_format($total_crc, 0) . ' (≈ US$' . number_format($total_usd, 2) . ')</p>
            <p style="font-size:12px;color:#999;">TXN PayPal: ' . $txn_safe . '</p>';
        @send_mail($seller_email, '[CompraTica] Pago recibido — ' . $order_ref, $body_seller);
        emp_cap_log("EMAIL_SELLER", ['to' => $seller_email]);
    }

    // Correo al admin
    $admin_email = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : '';
    if ($admin_email !== '' && strtolower($admin_email) !== strtolower($buyer_email)) {
        @send_mail($admin_email, '[CompraTica] PayPal Emprendedoras — ' . $order_ref,
            '<p>Pago capturado. Vendedor: <strong>' . htmlspecialchars($seller_name) . '</strong> | '
            . 'Comprador: ' . htmlspecialchars($buyer_email) . ' | Total: US$' . number_format($total_usd, 2)
            . ' | TXN: ' . $txn_safe . '</p>');
    }
} catch (Throwable $e) {
    emp_cap_log("EMAIL_ERROR", ['err' => $e->getMessage()]);
}

echo json_encode(['success' => true, 'txn_id' => $txn_id]);
