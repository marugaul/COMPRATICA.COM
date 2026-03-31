<?php
/**
 * api/swiftpay-charge.php
 * ─────────────────────────────────────────────────────────────────────
 * Endpoint AJAX que recibe datos de tarjeta, procesa el pago con SwiftPay
 * y, si es aprobado, crea la orden, descuenta stock, limpia el carrito
 * y envía correos de confirmación (igual que paypal-capture-order.php).
 *
 * Las credenciales de tarjeta NUNCA se loguean ni almacenan en texto plano.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/SwiftPayClient.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/email_template.php';
require_once __DIR__ . '/../includes/swiftpay-order-helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

// ── Campos de tarjeta ──────────────────────────────────────────────
$cardNumber  = trim((string)($body['card_number'] ?? ''));
$expiry      = preg_replace('/\D/', '', (string)($body['expiry'] ?? ''));
$cvv         = trim((string)($body['cvv'] ?? ''));
$amount      = trim((string)($body['amount'] ?? ''));
$currency    = strtoupper(trim((string)($body['currency'] ?? 'CRC')));
$description = trim((string)($body['description'] ?? 'Compra en CompraTica'));

// En sandbox SwiftPay requiere la palabra "3ds" en la descripción para activar el flujo 3D Secure
if (defined('SWIFTPAY_SANDBOX') && SWIFTPAY_SANDBOX && stripos($description, '3ds') === false) {
    $description .= ' 3ds';
}
$refId       = (int)($body['reference_id'] ?? 0);
$refTable    = trim((string)($body['reference_table'] ?? ''));

// Campos extra del formulario de checkout (teléfono, dirección, notas)
$customerPhone = trim((string)($body['customer_phone'] ?? ''));
$locationUrl   = trim((string)($body['location_url']   ?? ''));
$otrasSeñas    = trim((string)($body['otras_senas']    ?? ''));
$deliveryNotes = implode(' | ', array_filter([$locationUrl, $otrasSeñas]));

$errors = [];
if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19 || !ctype_digit($cardNumber))
    $errors[] = 'Número de tarjeta inválido';
if (strlen($expiry) !== 4 || !ctype_digit($expiry))
    $errors[] = 'Fecha de vencimiento inválida (use MMYY)';
if (strlen($cvv) < 3 || strlen($cvv) > 4 || !ctype_digit($cvv))
    $errors[] = 'CVV inválido';
if (!is_numeric($amount) || (float)$amount <= 0)
    $errors[] = 'Monto inválido';
if (!in_array($currency, ['CRC', 'USD'], true))
    $errors[] = 'Moneda inválida. Use CRC o USD';

if ($errors) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => implode('. ', $errors)]);
    exit;
}

// ── Procesar pago ──────────────────────────────────────────────────
try {
    $pdo    = db();
    $client = new SwiftPayClient($pdo);

    $result = $client->charge(
        amount:         $amount,
        currency:       $currency,
        description:    $description,
        card:           ['number' => $cardNumber, 'expiry' => $expiry, 'cvv' => $cvv],
        referenceId:    $refId,
        referenceTable: $refTable
    );

    // ── 3DS requerido (v2: form POST al ACS) ──────────────────────
    if ($result->needs3ds()) {
        // Guardar contexto para crear la orden cuando SwiftPay devuelva el resultado 3DS
        $_SESSION['swiftpay_3ds_ctx'] = [
            'ref_table'      => $refTable,
            'ref_id'         => $refId,
            'customer_phone' => $customerPhone,
            'delivery_notes' => $deliveryNotes,
        ];
        echo json_encode([
            'ok'                    => false,
            'pending_3ds'           => true,
            'action'                => $result->redirectUrl,
            'creq'                  => $result->creq,
            'three_ds_session_data' => $result->threeDSSessionData,
            'client_id'             => $result->clientId,
            'tx_id'                 => $result->txId,
        ]);
        exit;
    }

    // ── Pago aprobado ──────────────────────────────────────────────
    if ($result->isSuccess()) {
        $_SESSION['swiftpay_last'] = $result->toArray();

        // Elegir flujo según tabla de referencia
        if ($refTable === 'entrepreneur_orders') {
            $redirectUrl = crearOrdenEmprendedoraSwiftPay($pdo, $result, $refId, $customerPhone, $deliveryNotes);
        } elseif ($refTable === 'real_estate_listings') {
            $redirectUrl = crearOrdenRealEstateSwiftPay($pdo, $result, $refId);
        } else {
            $redirectUrl = crearOrdenSwiftPay($pdo, $result, $customerPhone, $deliveryNotes);
        }

        echo json_encode([
            'ok'           => true,
            'order_id'     => $result->orderId,
            'auth_code'    => $result->authCode,
            'tx_id'        => $result->txId,
            'redirect_url' => $redirectUrl,
        ]);
        exit;
    }

    // ── Pago rechazado ─────────────────────────────────────────────
    $msg = $result->errorMessage ?: 'Transacción declinada. Verificá los datos de tu tarjeta.';
    echo json_encode(['ok' => false, 'error' => $msg]);

} catch (SwiftPayException $e) {
    error_log('[swiftpay-charge] SwiftPayException: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Error al procesar el pago: ' . $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[swiftpay-charge] Error inesperado: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Error interno. Intentá de nuevo.']);
}


