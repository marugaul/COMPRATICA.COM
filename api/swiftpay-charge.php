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

    // ── 3DS requerido ──────────────────────────────────────────────
    if ($result->needs3ds()) {
        echo json_encode([
            'ok'           => false,
            'pending_3ds'  => true,
            'redirect_url' => $result->redirectUrl,
            'client_id'    => $result->clientId,
            'tx_id'        => $result->txId,
        ]);
        exit;
    }

    // ── Pago aprobado ──────────────────────────────────────────────
    if ($result->isSuccess()) {
        $_SESSION['swiftpay_last'] = $result->toArray();

        // Crear orden, descontar stock, limpiar carrito y enviar emails
        $redirectUrl = crearOrdenSwiftPay($pdo, $result, $customerPhone, $deliveryNotes);

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

// ══════════════════════════════════════════════════════════════════════
// Crea la orden en DB, descuenta stock, limpia carrito y envía emails.
// El cobro ya fue aprobado por SwiftPay — errores aquí NO cancelan el pago.
// ══════════════════════════════════════════════════════════════════════
function crearOrdenSwiftPay(PDO $pdo, SwiftPayResult $result, string $customerPhone, string $deliveryNotes): string
{
    // Contexto guardado al renderizar checkout.php
    $ctx = $_SESSION['swiftpay_checkout'] ?? [];

    $orderNumber   = (string)($ctx['order_number']  ?? ('ORD-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6))));
    $userId        = (int)($ctx['user_id']       ?? 0);
    $userName      = (string)($ctx['user_name']  ?? '');
    $userEmail     = (string)($ctx['user_email'] ?? '');
    $userPhone     = $customerPhone ?: (string)($ctx['user_phone'] ?? '');
    $items         = (array)($ctx['items']       ?? []);
    $grandTotal    = (float)($ctx['grand_total'] ?? 0);
    $currency      = (string)($ctx['currency']   ?? 'CRC');
    $exchangeRate  = (float)($ctx['exchange_rate'] ?? 510.0);
    $affiliateId   = (int)($ctx['affiliate_id']  ?? 0);
    $saleId        = (int)($ctx['sale_id']        ?? 0);
    $cartId        = (int)($ctx['cart_id']        ?? 0);
    $txnId         = $result->orderId ?: $result->authCode;

    if (empty($items) || $saleId <= 0) {
        error_log('[swiftpay-charge] crearOrden: contexto de sesión incompleto');
        return '/checkout.php?payment=ok';
    }

    // ── Crear órdenes en DB ────────────────────────────────────────
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

        foreach ($items as $it) {
            $qty  = (float)($it['quantity'] ?? 1);
            $pid  = (int)($it['product_id'] ?? 0);
            $unit = isset($it['unit_price']) && $it['unit_price'] !== null
                  ? (float)$it['unit_price'] : (float)($it['product_price'] ?? 0);
            $line = $qty * $unit;
            $raw  = (float)($it['tax_rate'] ?? 0);
            $tr   = ($raw > 1.0 && $raw <= 100.0) ? $raw / 100.0 : (($raw >= 0.0 && $raw <= 1.0) ? $raw : 0.0);
            $lineTax = $line * $tr;
            $lineTot = $line + $lineTax;

            // Verificar stock
            if ($pid > 0 && $qty > 0) {
                $stock = (float)($pdo->prepare("SELECT stock FROM products WHERE id=?")
                    ->execute([$pid]) ? $pdo->query("SELECT stock FROM products WHERE id=$pid")->fetchColumn() : 999);
            }

            $ins->execute([
                $orderNumber, $pid, $affiliateId, $saleId,
                $userEmail, $userName, $userPhone,
                $qty, $line, $lineTax, $lineTot,
                'card', 'Pagado', $txnId,
                $deliveryNotes, $currency, $exchangeRate,
                date('Y-m-d H:i:s'), date('Y-m-d H:i:s'),
            ]);

            // Marcar email de vendedor como ya enviado para que order-success.php no lo duplique
            $newId = (int)$pdo->lastInsertId();
            if ($newId > 0) {
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS order_meta (order_id INTEGER NOT NULL, meta_key TEXT NOT NULL, meta_value TEXT, UNIQUE(order_id, meta_key))");
                    $pdo->prepare("INSERT OR IGNORE INTO order_meta (order_id, meta_key, meta_value) VALUES (?,?,?)")
                        ->execute([$newId, 'reseller_notified_review_at', date('Y-m-d H:i:s')]);
                } catch (Throwable $e) { /* no crítico */ }
            }

            // Descontar stock atómicamente
            if ($pid > 0 && $qty > 0) {
                $pdo->prepare("UPDATE products SET stock = stock - ?, updated_at = datetime('now') WHERE id = ? AND stock >= ?")
                    ->execute([$qty, $pid, $qty]);
            }
        }

        // Limpiar carrito
        if ($cartId > 0 && $saleId > 0) {
            $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ? AND (sale_id = ? OR product_id IN (SELECT id FROM products WHERE sale_id = ?))")
                ->execute([$cartId, $saleId, $saleId]);
        }

        $pdo->commit();
        unset($_SESSION['swiftpay_checkout']);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[swiftpay-charge] DB error: ' . $e->getMessage());
        // El cobro ya se hizo — redirigir de todas formas
        return '/checkout.php?payment=ok&sale_id=' . $saleId;
    }

    // ── Emails de confirmación ─────────────────────────────────────
    try {
        // Datos del afiliado (vendedor)
        $affEmail = '';
        $affName  = 'Vendedor';
        $affPhone = '';
        if ($affiliateId > 0) {
            $st = $pdo->prepare("SELECT email, name, phone FROM affiliates WHERE id=? LIMIT 1");
            $st->execute([$affiliateId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $affEmail = strtolower(trim((string)($row['email'] ?? '')));
            $affName  = (string)($row['name']  ?? 'Vendedor');
            $affPhone = (string)($row['phone'] ?? '');
        }

        // Título de la tienda
        $saleTitle = '';
        try {
            $st = $pdo->prepare("SELECT title FROM sales WHERE id=? LIMIT 1");
            $st->execute([$saleId]);
            $saleTitle = (string)($st->fetchColumn() ?: '');
        } catch (Throwable $e) {}

        // Items para el template
        $emailItems = [];
        foreach ($items as $it) {
            $qty  = (float)($it['quantity'] ?? 1);
            $unit = isset($it['unit_price']) && $it['unit_price'] !== null
                  ? (float)$it['unit_price'] : (float)($it['product_price'] ?? 0);
            $emailItems[] = ['name' => $it['name'] ?? 'Producto', 'qty' => $qty, 'unit_price' => $unit, 'line_total' => $qty * $unit];
        }

        $buyerEmailLower = strtolower($userEmail);
        $orderSafe = htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8');
        $txnSafe   = htmlspecialchars($txnId, ENT_QUOTES, 'UTF-8');
        $saleTag   = $saleTitle ? ' &mdash; <em>' . htmlspecialchars($saleTitle, ENT_QUOTES, 'UTF-8') . '</em>' : '';

        // Helpers de contacto reutilizables
        $esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $contactBox = fn(string $name, string $email, string $phone, string $label) =>
            '<div style="margin:20px 0;padding:14px 16px;background:#f8f9fa;border-left:3px solid #e53935;border-radius:6px;">'
            . '<p style="margin:0 0 6px;font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.05em;">' . $label . '</p>'
            . '<p style="margin:0 0 4px;font-size:15px;font-weight:700;color:#1a202c;">' . $esc($name) . '</p>'
            . ($email ? '<p style="margin:0 0 4px;font-size:13px;color:#555;"><a href="mailto:' . $esc($email) . '" style="color:#e53935;text-decoration:none;">' . $esc($email) . '</a></p>' : '')
            . ($phone ? '<p style="margin:0;font-size:13px;color:#555;">📞 ' . $esc($phone) . '</p>' : '')
            . '</div>';

        // ── Correo al comprador ───────────────────────────────────
        if ($userEmail !== '') {
            $body = '
              <div style="text-align:center;margin-bottom:24px;">
                <span style="font-size:40px;">&#10003;</span>
                <h2 style="margin:8px 0 4px;font-size:22px;color:#2e7d32;">Pago con tarjeta confirmado</h2>
                <p style="margin:0;font-size:13px;color:#999;">Orden: <strong style="color:#333;">' . $orderSafe . '</strong>' . $saleTag . '</p>
              </div>
              <p style="font-size:15px;margin:0 0 16px;">Hola <strong>' . $esc($userName) . '</strong>,</p>
              <p style="font-size:15px;margin:0 0 20px;color:#555;">Tu pago fue procesado exitosamente. Aquí está el resumen de tu compra:</p>
              ' . email_product_table($emailItems, $currency) . '
              ' . email_total_block($grandTotal, 0, $grandTotal, $currency) . '
              <p style="margin:20px 0 0;font-size:13px;color:#999;">Estado: ' . email_status_badge('Pagado') . '</p>
              ' . ($affName ? $contactBox($affName, $affEmail, $affPhone, 'Datos del vendedor') : '') . '
              <p style="margin:8px 0 0;font-size:12px;color:#bbb;">Referencia SwiftPay: ' . $txnSafe . '</p>';
            @send_mail($userEmail, 'Pago confirmado — Orden ' . $orderNumber, email_html($body));
        }

        // ── Correo al vendedor ────────────────────────────────────
        if ($affEmail !== '' && $affEmail !== $buyerEmailLower) {
            $body = '
              <h2 style="margin:0 0 4px;font-size:22px;color:#333;">Pago con tarjeta recibido en tu tienda</h2>
              <p style="margin:0 0 20px;font-size:13px;color:#999;">Orden: <strong style="color:#333;">' . $orderSafe . '</strong></p>
              <p style="font-size:15px;margin:0 0 16px;">Hola <strong>' . $esc($affName) . '</strong>,</p>
              <p style="font-size:15px;margin:0 0 4px;color:#555;">Se confirmó el pago con tarjeta por el siguiente pedido:</p>
              ' . email_product_table($emailItems, $currency) . '
              ' . email_total_block($grandTotal, 0, $grandTotal, $currency) . '
              <p style="margin:20px 0 0;font-size:13px;color:#999;">Estado: ' . email_status_badge('Pagado') . '</p>
              ' . $contactBox($userName, $userEmail, $userPhone, 'Datos del comprador') . '
              <p style="margin:8px 0 0;font-size:12px;color:#bbb;">Referencia SwiftPay: ' . $txnSafe . '</p>';
            @send_mail($affEmail, '[COMPRATICA] Pago con tarjeta recibido — Orden ' . $orderNumber, email_html($body));
        }

        // ── Correo al admin ───────────────────────────────────────
        $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : '';
        if ($adminEmail !== '' && strtolower($adminEmail) !== $buyerEmailLower && strtolower($adminEmail) !== $affEmail) {
            $body = '
              <h2 style="margin:0 0 4px;font-size:20px;color:#333;">Pago con tarjeta (SwiftPay) confirmado</h2>
              <p style="margin:0 0 20px;font-size:13px;color:#999;">Orden: <strong style="color:#333;">' . $orderSafe . '</strong></p>
              <p style="font-size:14px;margin:0 0 8px;"><strong>Comprador:</strong> ' . htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') . '</p>
              ' . email_product_table($emailItems, $currency) . '
              ' . email_total_block($grandTotal, 0, $grandTotal, $currency) . '
              <p style="margin:20px 0 0;font-size:12px;color:#bbb;">TXN SwiftPay: ' . $txnSafe . '</p>';
            @send_mail($adminEmail, '[COMPRATICA] Pago SwiftPay — ' . $orderNumber, email_html($body));
        }

    } catch (Throwable $e) {
        error_log('[swiftpay-charge] email error: ' . $e->getMessage());
    }

    return '/order-success.php?order=' . urlencode($orderNumber);
}
