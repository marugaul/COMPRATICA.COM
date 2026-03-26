<?php
/**
 * api/swiftpay-void.php — Anulación de transacción SwiftPay
 * ─────────────────────────────────────────────────────────
 * Solo accesible por el administrador de CompraTica.
 * Recibe el ID interno de la transacción, busca los datos en DB
 * y llama SwiftPayClient::void().
 *
 * POST JSON: { "tx_id": 123 }
 * Respuesta: { "ok": true, "message": "..." }
 *         o  { "ok": false, "error": "..." }
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/SwiftPayClient.php';

// ── Solo admin ───────────────────────────────────────────────────────────────
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado.']);
    exit;
}

// ── Solo POST ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$tx_id = (int)($body['tx_id'] ?? 0);

if ($tx_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'tx_id inválido.']);
    exit;
}

$pdo = db();

// ── Buscar la transacción ────────────────────────────────────────────────────
$tx = $pdo->prepare("SELECT * FROM swiftpay_transactions WHERE id = ? LIMIT 1");
$tx->execute([$tx_id]);
$row = $tx->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['ok' => false, 'error' => 'Transacción no encontrada.']);
    exit;
}

if ($row['status'] !== 'approved') {
    echo json_encode(['ok' => false, 'error' => 'Solo se pueden anular transacciones aprobadas. Estado actual: ' . $row['status']]);
    exit;
}

if ($row['type'] === 'void') {
    echo json_encode(['ok' => false, 'error' => 'Esta transacción ya es una anulación.']);
    exit;
}

// Verificar que tengamos los campos requeridos por SwiftPay
$missing = [];
foreach (['amount', 'currency', 'order_id', 'rrn', 'int_ref', 'auth_code'] as $field) {
    if (empty($row[$field])) $missing[] = $field;
}
if ($missing) {
    echo json_encode(['ok' => false, 'error' => 'Faltan datos para anular: ' . implode(', ', $missing)]);
    exit;
}

// ── Llamar a SwiftPay ────────────────────────────────────────────────────────
try {
    $client = new SwiftPayClient($pdo);
    $result = $client->void(
        amount:   $row['amount'],
        currency: $row['currency'],
        orderId:  $row['order_id'],
        rrn:      $row['rrn'],
        intRef:   $row['int_ref'],
        authCode: $row['auth_code']
    );

    if ($result->isSuccess()) {
        // Marcar la transacción original como anulada
        $pdo->prepare("UPDATE swiftpay_transactions SET status='voided', updated_at=datetime('now') WHERE id=?")
            ->execute([$tx_id]);

        echo json_encode([
            'ok'      => true,
            'message' => 'Transacción anulada correctamente.',
            'void_tx_id' => $result->txId,
        ]);
    } else {
        echo json_encode([
            'ok'    => false,
            'error' => $result->errorMessage ?: 'SwiftPay rechazó la anulación.',
        ]);
    }
} catch (SwiftPayException $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[swiftpay-void] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Error interno. Ver logs.']);
}
