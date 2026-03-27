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

// SwiftPay envía su propio "uuid" y "success" al page_result.
// Nosotros también incluimos nuestro "clientId" en la URL del page_result para lookup en DB.
$swiftpayUuid = trim($_GET['uuid']      ?? '');   // UUID de SwiftPay → para getResult3ds
$ourClientId  = trim($_GET['clientId']  ?? '');   // Nuestro UUID → para buscar en DB

// Log completo de lo que SwiftPay envía al hacer el callback
error_log('[swiftpay-3ds-return] GET params: '  . json_encode($_GET));
error_log('[swiftpay-3ds-return] POST params: ' . json_encode($_POST));

// URL de redirección final
$successUrl = '/checkout.php?payment=ok';
$errorUrl   = '/checkout.php?payment=error';

if (empty($swiftpayUuid) && empty($ourClientId)) {
    header('Location: ' . $errorUrl . '&reason=missing_uuid');
    exit;
}

try {
    $pdo    = db();
    $client = new SwiftPayClient($pdo);

    $uuidUsed = $swiftpayUuid ?: $ourClientId;
    error_log('[swiftpay-3ds-return] swiftpayUuid=' . $swiftpayUuid . ' ourClientId=' . $ourClientId . ' uuidUsed=' . $uuidUsed);

    $result = $client->get3dsResult($uuidUsed, $ourClientId);

    error_log('[swiftpay-3ds-return] result: approved=' . ($result->approved ? 'true' : 'false')
        . ' pending3ds=' . ($result->pending3ds ? 'true' : 'false')
        . ' errorMessage=' . $result->errorMessage
        . ' rawResponse=' . json_encode($result->rawResponse));

    if ($result->isSuccess()) {
        $_SESSION['swiftpay_last'] = $result->toArray();
        header('Location: ' . $successUrl . '&order_id=' . urlencode($result->orderId));
        exit;
    }

    if ($result->needs3ds()) {
        // getResult3ds sigue devolviendo CONFIRMED — el 3DS no se procesó aún
        header('Location: ' . $errorUrl . '&reason=' . urlencode('3DS pendiente: SwiftPay aún no procesó el resultado'));
        exit;
    }

    $errMsg = $result->errorMessage ?: json_encode($result->rawResponse);
    header('Location: ' . $errorUrl . '&reason=' . urlencode(substr($errMsg, 0, 200)));
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
