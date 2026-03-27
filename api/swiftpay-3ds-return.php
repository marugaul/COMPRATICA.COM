<?php
/**
 * api/swiftpay-3ds-return.php
 * ─────────────────────────────────────────────────────────────────────
 * Página de retorno 3DS: SwiftPay redirige al usuario aquí luego de
 * completar la validación 3D Secure en el banco.
 *
 * URL configurada como page_result en SwiftPayClient::returnUrl()
 * Ejemplo: /api/swiftpay-3ds-return.php?clientId=550e8400-...
 *
 * Este archivo consulta el resultado final y redirige al usuario
 * a la página de éxito o de error del checkout.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/SwiftPayClient.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$clientId = trim($_GET['clientId'] ?? '');

// Log completo de lo que SwiftPay envía al hacer el callback
error_log('[swiftpay-3ds-return] GET params: '  . json_encode($_GET));
error_log('[swiftpay-3ds-return] POST params: ' . json_encode($_POST));

// URL de redirección final (ajustar según el flujo de tu checkout)
$successUrl = '/checkout.php?payment=ok';
$errorUrl   = '/checkout.php?payment=error';

if (empty($clientId)) {
    header('Location: ' . $errorUrl . '&reason=missing_client_id');
    exit;
}

try {
    $pdo    = db();
    $client = new SwiftPayClient($pdo);

    // Esperar 2s para que SwiftPay procese el resultado del 3DS
    sleep(2);
    $result = $client->get3dsResult($clientId);

    // Si aún devuelve CONFIRMED (pending), reintentar una vez más tras 3s
    if ($result->needs3ds()) {
        sleep(3);
        $result = $client->get3dsResult($clientId);
    }

    if ($result->isSuccess()) {
        $_SESSION['swiftpay_last'] = $result->toArray();
        header('Location: ' . $successUrl . '&order_id=' . urlencode($result->orderId));
        exit;
    }

    $errMsg = urlencode($result->errorMessage ?: 'Validación 3DS fallida');
    header('Location: ' . $errorUrl . '&reason=' . $errMsg);
    exit;

} catch (SwiftPayException $e) {
    $msg = $e->getMessage();
    error_log('[swiftpay-3ds-return] SwiftPayException: ' . $msg);
    header('Location: ' . $errorUrl . '&reason=' . urlencode(substr($msg, 0, 120)));
    exit;
} catch (Throwable $e) {
    $msg = $e->getMessage();
    error_log('[swiftpay-3ds-return] Error inesperado: ' . $msg);
    header('Location: ' . $errorUrl . '&reason=' . urlencode(substr($msg, 0, 120)));
    exit;
}
