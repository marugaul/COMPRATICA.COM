<?php
/**
 * api/swiftpay-charge.php
 * ─────────────────────────────────────────────────────────────────────
 * Endpoint AJAX que recibe datos de tarjeta desde el formulario,
 * los procesa vía SwiftPayClient y devuelve JSON.
 *
 * El frontend (views/swiftpay-button.php) llama aquí via fetch().
 * Las credenciales de tarjeta NUNCA se loguean ni almacenan en texto plano.
 *
 * Request (POST JSON):
 *   {
 *     "card_number": "4111111111111111",
 *     "expiry": "1228",           // MMYY
 *     "cvv": "123",
 *     "amount": "15000.00",
 *     "currency": "CRC",          // CRC | USD
 *     "description": "Compra en CompraTica",
 *     "reference_id": 123,        // ID de orden local (opcional)
 *     "reference_table": "orders" // tabla local (opcional)
 *   }
 *
 * Response (JSON):
 *   Éxito:     { "ok": true,  "order_id": "...", "auth_code": "..." }
 *   3DS:       { "ok": false, "pending_3ds": true, "redirect_url": "https://..." }
 *   Error:     { "ok": false, "error": "mensaje legible" }
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/SwiftPayClient.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

// Leer body JSON
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido']);
    exit;
}

// ── Validar campos requeridos ──────────────────────────────────────
$cardNumber  = trim((string)($body['card_number'] ?? ''));
$expiry      = preg_replace('/\D/', '', (string)($body['expiry'] ?? ''));
$cvv         = trim((string)($body['cvv'] ?? ''));
$amount      = trim((string)($body['amount'] ?? ''));
$currency    = strtoupper(trim((string)($body['currency'] ?? 'CRC')));
$description = trim((string)($body['description'] ?? 'Compra en CompraTica'));
$refId       = (int)($body['reference_id'] ?? 0);
$refTable    = trim((string)($body['reference_table'] ?? ''));

$errors = [];
if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19 || !ctype_digit($cardNumber)) {
    $errors[] = 'Número de tarjeta inválido';
}
if (strlen($expiry) !== 4 || !ctype_digit($expiry)) {
    $errors[] = 'Fecha de vencimiento inválida (use MMYY)';
}
if (strlen($cvv) < 3 || strlen($cvv) > 4 || !ctype_digit($cvv)) {
    $errors[] = 'CVV inválido';
}
if (!is_numeric($amount) || (float)$amount <= 0) {
    $errors[] = 'Monto inválido';
}
if (!in_array($currency, ['CRC', 'USD'], true)) {
    $errors[] = 'Moneda inválida. Use CRC o USD';
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => implode('. ', $errors)]);
    exit;
}

// ── Procesar pago ─────────────────────────────────────────────────
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

    // ── 3DS requerido → redirigir usuario ─────────────────────────
    if ($result->needs3ds()) {
        echo json_encode([
            'ok'          => false,
            'pending_3ds' => true,
            'redirect_url' => $result->redirectUrl,
            'client_id'   => $result->clientId,
            'tx_id'       => $result->txId,
        ]);
        exit;
    }

    // ── Pago aprobado ──────────────────────────────────────────────
    if ($result->isSuccess()) {
        // Guardar en sesión para confirmar en la página de éxito
        $_SESSION['swiftpay_last'] = $result->toArray();

        echo json_encode([
            'ok'        => true,
            'order_id'  => $result->orderId,
            'auth_code' => $result->authCode,
            'rrn'       => $result->rrn,
            'tx_id'     => $result->txId,
            'mode'      => $client->getMode(),
        ]);
        exit;
    }

    // ── Pago rechazado ─────────────────────────────────────────────
    $msg = $result->errorMessage ?: 'Transacción declinada. Verificá los datos de tu tarjeta.';
    echo json_encode(['ok' => false, 'error' => $msg, '_debug_raw' => $result->rawResponse]);

} catch (SwiftPayException $e) {
    error_log('[swiftpay-charge] SwiftPayException: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Error al procesar el pago: ' . $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[swiftpay-charge] Error inesperado: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Error interno. Intentá de nuevo.']);
}
