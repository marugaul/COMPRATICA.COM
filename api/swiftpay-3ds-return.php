<?php
/**
 * api/swiftpay-3ds-return.php
 * ─────────────────────────────────────────────────────────────────────
 * Página de retorno 3DS: SwiftPay redirige al usuario aquí luego de
 * completar la validación 3D Secure en el banco.
 *
 * SwiftPay envía: ?uuid={swiftpay-uuid}&success=true/false
 * Nosotros incluimos: ?clientId={nuestro-uuid} en el page_result
 *
 * Este archivo consulta el resultado final con getResult3ds,
 * crea la orden (igual que un pago sin 3DS) y redirige al usuario.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/SwiftPayClient.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/email_template.php';
require_once __DIR__ . '/../includes/swiftpay-order-helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// SwiftPay envía su propio "uuid" al page_result.
// Nosotros incluimos "clientId" en la URL del page_result para lookup en DB.
$swiftpayUuid = trim($_GET['uuid']     ?? '');
$ourClientId  = trim($_GET['clientId'] ?? '');

error_log('[swiftpay-3ds-return] GET params: ' . json_encode($_GET));

if (empty($swiftpayUuid) && empty($ourClientId)) {
    header('Location: /checkout.php?payment=error&reason=missing_uuid');
    exit;
}

try {
    $pdo    = db();
    $client = new SwiftPayClient($pdo);

    $result = $client->get3dsResult($swiftpayUuid ?: $ourClientId, $ourClientId);

    if ($result->isSuccess()) {
        $_SESSION['swiftpay_last'] = $result->toArray();

        // Recuperar contexto guardado al inicio del flujo 3DS
        $ctx3ds        = $_SESSION['swiftpay_3ds_ctx'] ?? [];
        $refTable      = (string)($ctx3ds['ref_table']      ?? '');
        $refId         = (int)($ctx3ds['ref_id']            ?? 0);
        $customerPhone = (string)($ctx3ds['customer_phone'] ?? '');
        $deliveryNotes = (string)($ctx3ds['delivery_notes'] ?? '');
        unset($_SESSION['swiftpay_3ds_ctx']);

        // Crear orden igual que el flujo sin 3DS
        if ($refTable === 'entrepreneur_orders') {
            $redirectUrl = crearOrdenEmprendedoraSwiftPay($pdo, $result, $refId, $customerPhone, $deliveryNotes);
        } else {
            $redirectUrl = crearOrdenSwiftPay($pdo, $result, $customerPhone, $deliveryNotes);
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    $errMsg = urlencode($result->errorMessage ?: 'Validación 3DS fallida');
    header('Location: /checkout.php?payment=error&reason=' . $errMsg);
    exit;

} catch (SwiftPayException $e) {
    $msg = $e->getMessage();
    error_log('[swiftpay-3ds-return] SwiftPayException: ' . $msg);
    header('Location: /checkout.php?payment=error&reason=' . urlencode(substr($msg, 0, 120)));
    exit;
} catch (Throwable $e) {
    $msg = $e->getMessage();
    error_log('[swiftpay-3ds-return] Error inesperado: ' . $msg);
    header('Location: /checkout.php?payment=error&reason=' . urlencode(substr($msg, 0, 120)));
    exit;
}
