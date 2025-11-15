<?php
declare(strict_types=1);
/**
 * paypal_ipn.php — verificación IPN robusta (sandbox/live), idempotencia y logs extendidos
 * Flujo:
 *  - Verifica IPN con PayPal (auto sandbox/live)
 *  - Valida receiver_email, estado, currency/amount
 *  - Marca órdenes (mismo order_number) como Pagado (idempotente)
 *  - Descuenta stock si aplica
 *  - Limpia carrito por sale_id
 *  - Envía correos (comprador, afiliado, admin)
 *  - Deja trazabilidad completa en logs
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';
ini_set('display_errors', '0');
error_reporting(E_ALL);
// ---------- LOG ----------
$logFile = __DIR__ . '/logs/paypal_ipn.log';
if (!is_dir(dirname($logFile))) { @mkdir(dirname($logFile), 0777, true); }
function ipn_log(string $msg, $data = null): void {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] $msg";
    if ($data !== null) { $line .= " | " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); }
    $line .= PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND);
}
ipn_log("========== PAYPAL IPN RECEIVED ==========");
ipn_log("SERVER_META", [
    'ip'   => $_SERVER['REMOTE_ADDR'] ?? '',
    'ua'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'host' => $_SERVER['HTTP_HOST'] ?? '',
    'uri'  => $_SERVER['REQUEST_URI'] ?? '',
    'tls'  => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'
]);
// ---------- Leer POST RAW (sin reconstruir) ----------
$raw = file_get_contents('php://input');
if ($raw === false) { $raw = ''; }
ipn_log("RAW_POST", $raw !== '' ? $raw : 'EMPTY');
if ($raw === '') { http_response_code(200); echo "NO_CONTENT"; ipn_log("EARLY_EXIT_NO_CONTENT"); exit; }
// ---------- Verificar con PayPal (sandbox/live automático) ----------
$verifyBody = 'cmd=_notify-validate&' . $raw;
// Detectar sandbox por flag del payload o por constante de config (opcional)
$isSandbox = (strpos($raw, 'test_ipn=1') !== false);
if (defined('PAYPAL_SANDBOX') && PAYPAL_SANDBOX) { $isSandbox = true; }
$verifyUrl = $isSandbox
  ? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr'
  : 'https://ipnpb.paypal.com/cgi-bin/webscr';
ipn_log("IPN_VERIFY_START", ['endpoint'=>$verifyUrl, 'sandbox'=>$isSandbox]);
$ch = curl_init($verifyUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => false,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $verifyBody,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_FORBID_REUSE   => true,
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/x-www-form-urlencoded',
        'Connection: close',
        'User-Agent: Compratica-IPN/1.3',
    ],
    CURLOPT_TIMEOUT        => 30,
]);
$resp = curl_exec($ch);
$err  = curl_error($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$respTrim = is_string($resp) ? trim($resp) : '';
ipn_log("IPN_VERIFY", ['http'=>$http, 'resp'=>$respTrim, 'err'=>$err, 'sandbox'=>$isSandbox]);
if (!($http === 200 && hash_equals('VERIFIED', $respTrim))) {
    ipn_log("NOT_VERIFIED", ['http'=>$http, 'resp'=>$respTrim]);
    http_response_code(200); echo "IGNORED"; ipn_log("EARLY_EXIT_NOT_VERIFIED"); exit;
}
// ---------- Parsear POST ----------
$post = [];
parse_str($raw, $post);
ipn_log("POST_PARSED", $post);
// Campos relevantes
$receiver_email = (string)($post['receiver_email'] ?? '');
$payment_status = strtolower(trim((string)($post['payment_status'] ?? '')));
$txn_id         = (string)($post['txn_id'] ?? '');
$mc_gross       = (string)($post['mc_gross'] ?? '0');
$payment_gross  = (string)($post['payment_gross'] ?? $mc_gross);
$mc_currency    = strtoupper((string)($post['mc_currency'] ?? ''));
$customRaw      = (string)($post['custom'] ?? '');
$item_number    = isset($post['item_number']) ? (int)$post['item_number'] : 0;
// ---------- Validar receiver_email (live + sandbox) ----------
$validEmails = [];
if (defined('PAYPAL_EMAIL') && PAYPAL_EMAIL) {
    // En config.php puedes poner string o array
    $validEmails = is_array(PAYPAL_EMAIL) ? PAYPAL_EMAIL : [PAYPAL_EMAIL];
}
if ($isSandbox) {
    // [AQUÍ AJUSTA] — tu correo de negocio SANDBOX real (del log)
    $validEmails[] = 'sb-ttcma47147404@business.example.com';
}
if (!empty($validEmails)) {
    $ok = false;
    foreach ($validEmails as $e) {
        if (strcasecmp($receiver_email, (string)$e) === 0) { $ok = true; break; }
    }
    if (!$ok) {
        ipn_log("RECEIVER_EMAIL_MISMATCH", ['recv'=>$receiver_email, 'valid'=>$validEmails, 'sandbox'=>$isSandbox]);
        http_response_code(200); echo "BAD_RECEIVER"; ipn_log("EARLY_EXIT_BAD_RECEIVER"); exit;
    }
}
ipn_log("RECEIVER_EMAIL_OK", ['receiver_email'=>$receiver_email]);
// ---------- Validar estado de pago ----------
ipn_log("PAYMENT_STATUS_CHECK", ['status'=>$payment_status]);
if ($payment_status !== 'completed') {
    ipn_log("STATUS_NOT_COMPLETED_EXIT", $payment_status);
    http_response_code(200); echo "OK"; exit;
}
// ---------- Decodificar custom ----------
$order_id = 0;
$order_number = '';
$cart_id = 0;
$sale_id = 0;
$affiliate_id = 0;
$uid = 0;
$custom = json_decode($customRaw, true);
if (is_array($custom)) {
    $order_id     = (int)($custom['order_id']     ?? 0);
    $order_number = (string)($custom['order_number'] ?? '');
    $cart_id      = (int)($custom['cart_id']      ?? 0);
    $sale_id      = (int)($custom['sale_id']      ?? 0);
    $affiliate_id = (int)($custom['affiliate_id'] ?? 0);
    $uid          = (int)($custom['uid']          ?? 0);
    ipn_log("CUSTOM_JSON", ['order_id'=>$order_id,'order_number'=>$order_number,'cart_id'=>$cart_id,'sale_id'=>$sale_id,'uid'=>$uid,'affiliate_id'=>$affiliate_id]);
} elseif (ctype_digit($customRaw)) {
    $order_id = (int)$customRaw;
    ipn_log("CUSTOM_LEGACY", ['order_id'=>$order_id]);
} elseif ($item_number > 0) {
    $order_id = $item_number;
    ipn_log("CUSTOM_ITEM_NUMBER", ['order_id'=>$order_id]);
}
// ---------- DB ----------
$pdo = db();
try {
    // Forzar modo estricto de errores por seguridad de transacción
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    ipn_log("DB_READY");
} catch (Throwable $e) {
    ipn_log("DB_ATTR_ERR", ['err'=>$e->getMessage()]);
}
// Completar order_number si falta
if ($order_number === '' && $order_id > 0) {
    $st = $pdo->prepare("SELECT order_number FROM orders WHERE id=? LIMIT 1");
    $st->execute([$order_id]);
    $order_number = (string)($st->fetchColumn() ?: '');
}
if ($order_number === '') {
    ipn_log("MISSING_ORDER_NUMBER", ['order_id'=>$order_id]);
    http_response_code(200); echo "NO_ORDER_NUMBER"; ipn_log("EARLY_EXIT_NO_ORDER_NUMBER"); exit;
}
ipn_log("ORDER_NUMBER_OK", ['order_number'=>$order_number]);
// Snapshot estados antes
$st = $pdo->prepare("SELECT id, status FROM orders WHERE order_number=?");
$st->execute([$order_number]);
$before = $st->fetchAll(PDO::FETCH_ASSOC);
ipn_log("ORDER_STATES_BEFORE", $before ?: 'none');
// Resolver sale_id si falta
if ($sale_id <= 0) {
    $st = $pdo->prepare("
        SELECT DISTINCT p.sale_id
          FROM orders o
          JOIN products p ON p.id = o.product_id
         WHERE o.order_number = ?
         LIMIT 1
    ");
    $st->execute([$order_number]);
    $sale_id = (int)($st->fetchColumn() ?: 0);
}
ipn_log("SALE_ID_RESOLVED", $sale_id);
// Resolver cart_id por uid si no vino
if ($cart_id <= 0 && $uid > 0) {
    $st = $pdo->prepare("SELECT id FROM carts WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $st->execute([$uid]);
    $cart_id = (int)($st->fetchColumn() ?: 0);
}
ipn_log("CART_ID_RESOLVED", ['uid'=>$uid, 'cart_id'=>$cart_id]);
// Detectar si ya estaban pagados (idempotencia stock)
$alreadyMarked = true;
foreach ($before as $r) {
    if (strcasecmp((string)$r['status'], 'Pagado') !== 0) { $alreadyMarked = false; break; }
}
ipn_log("ALREADY_MARKED_CHECK", ['alreadyMarked'=>$alreadyMarked]);
// ---------- Idempotencia por txn_id ----------
if ($txn_id === '') {
    ipn_log("MISSING_TXN_ID");
    http_response_code(200); echo "OK"; ipn_log("EARLY_EXIT_MISSING_TXN"); exit;
}
try {
    ipn_log("IDEMPOTENCY_CHECK_START", ['txn_id'=>$txn_id]);
    $st = $pdo->prepare("SELECT 1 FROM orders WHERE paypal_txn_id = ? LIMIT 1");
    $st->execute([$txn_id]);
    if ($st->fetchColumn()) {
        ipn_log("DUPLICATE_TXN_BY_ORDER_PAYPAL_TXN_ID", $txn_id);
        http_response_code(200); echo "OK"; ipn_log("EARLY_EXIT_DUP_TXN"); exit;
    }
    ipn_log("IDEMPOTENCY_CHECK_OK");
} catch (Throwable $e) {
    ipn_log("IDEMPOTENCY_ERR", ['err'=>$e->getMessage()]);
    http_response_code(200); echo "OK"; ipn_log("EARLY_EXIT_IDEMPOTENCY_ERR"); exit;
}
// ---------- Validar currency & amount ----------
try {
    ipn_log("AMOUNT_CHECK_START");
    $st = $pdo->prepare("
        SELECT o.qty, o.grand_total, o.currency, p.name AS product_name
          FROM orders o
          JOIN products p ON p.id = o.product_id
         WHERE o.order_number = ?
    ");
    $st->execute([$order_number]);
    $lines = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$lines) {
        ipn_log("AMOUNT_CHECK_NO_LINES", ['order_number'=>$order_number]);
    }
    $dbCurrency = strtoupper((string)($lines[0]['currency'] ?? $mc_currency));
    $sum = 0.0;
    foreach ($lines as $ln) { $sum += (float)$ln['grand_total']; }
    $ipnAmount = (float)($payment_gross !== '' ? $payment_gross : $mc_gross);
    $tolerance = 0.02; // 2 centavos
    $currencyOk = ($dbCurrency === '' || $mc_currency === '' || strtoupper($mc_currency) === $dbCurrency);
    $amountOk   = (abs($ipnAmount - $sum) <= $tolerance);
    ipn_log("AMOUNT_CHECK", [
        'db_sum'=>$sum, 'ipn_amount'=>$ipnAmount, 'mc_currency'=>$mc_currency, 'db_currency'=>$dbCurrency,
        'currency_ok'=>$currencyOk, 'amount_ok'=>$amountOk
    ]);
    // Nota: En sandbox no abortamos por mismatch leve; en PROD podrías exigir ambos true.
} catch (Throwable $e) {
    ipn_log("AMOUNT_CHECK_ERR", ['err'=>$e->getMessage()]);
    // Continuamos para no bloquear sandbox
}
ipn_log("PRE_TX_BEGIN");
// ---------- Transacción principal ----------
try {
    ipn_log("TX_BEGIN");
    $pdo->beginTransaction();
    // 1) Marcar "Pagado"
    ipn_log("UPDATE_PAID_START", ['order_number'=>$order_number, 'txn_id'=>$txn_id]);
    $up = $pdo->prepare("
        UPDATE orders
           SET status='Pagado',
               paypal_txn_id = COALESCE(?, paypal_txn_id),
               updated_at = datetime('now')
         WHERE order_number = ?
    ");
    $up->execute([$txn_id, $order_number]);
    $affected = $up->rowCount();
    ipn_log("UPDATE_PAID_DONE", ['rows'=>$affected]);
    if ($affected > 0) {
        ipn_log("ORDER_STATUS_UPDATED_TO_PAGADO", ['order_number'=>$order_number]);
    } else {
        ipn_log("ORDER_STATUS_NOT_UPDATED", ['order_number'=>$order_number]);
    }
    // 2) Descontar stock (si no estaba marcado)
    if (!$alreadyMarked) {
        ipn_log("STOCK_UPDATE_START");
        $st = $pdo->prepare("
            SELECT product_id, SUM(qty) AS total_qty
              FROM orders
             WHERE order_number = ?
             GROUP BY product_id
        ");
        $st->execute([$order_number]);
        $agg = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($agg as $a) {
            $pid = (int)$a['product_id'];
            $qty = (int)$a['total_qty'];
            if ($pid > 0 && $qty > 0) {
                $pdo->prepare("
                    UPDATE products
                       SET stock = CASE WHEN stock >= ? THEN stock - ? ELSE 0 END,
                           updated_at = datetime('now')
                     WHERE id = ?
                ")->execute([$qty, $qty, $pid]);
                ipn_log("STOCK_UPDATED", ['product_id'=>$pid, 'qty'=>$qty]);
            } else {
                ipn_log("STOCK_SKIP_ROW", ['product_id'=>$pid ?? null, 'qty'=>$qty ?? null]);
            }
        }
        ipn_log("STOCK_UPDATE_DONE");
    } else {
        ipn_log("STOCK_SKIP_IDEMPOTENT");
    }
    // 3) Limpiar carrito del sale_id
    if ($sale_id > 0) {
        if ($cart_id > 0) {
            ipn_log("CART_CLEAR_BY_CART_ID_START", ['cart_id'=>$cart_id, 'sale_id'=>$sale_id]);
            $del = $pdo->prepare("
                DELETE FROM cart_items
                 WHERE cart_id = ?
                   AND (sale_id = ? OR product_id IN (SELECT id FROM products WHERE sale_id = ?))
            ");
            $del->execute([$cart_id, $sale_id, $sale_id]);
            ipn_log("CART_CLEARED_BY_CART_ID", ['deleted'=>$del->rowCount()]);
        } elseif ($uid > 0) {
            ipn_log("CART_CLEAR_BY_UID_START", ['uid'=>$uid, 'sale_id'=>$sale_id]);
            $st = $pdo->prepare("SELECT id FROM carts WHERE user_id=? ORDER BY id DESC LIMIT 1");
            $st->execute([$uid]);
            $fallback_cart = (int)($st->fetchColumn() ?: 0);
            if ($fallback_cart > 0) {
                $del = $pdo->prepare("
                    DELETE FROM cart_items
                     WHERE cart_id = ?
                       AND (sale_id = ? OR product_id IN (SELECT id FROM products WHERE sale_id = ?))
                ");
                $del->execute([$fallback_cart, $sale_id, $sale_id]);
                ipn_log("CART_CLEARED_BY_UID", ['cart_id'=>$fallback_cart, 'deleted'=>$del->rowCount()]);
            } else {
                ipn_log("NO_CART_FOUND_FOR_UID", ['uid'=>$uid]);
            }
        } else {
            ipn_log("SKIP_CART_CLEAR_NO_IDS", ['uid'=>$uid, 'cart_id'=>$cart_id]);
        }
    } else {
        ipn_log("SKIP_CART_CLEAR_NO_SALE_ID");
    }
    $pdo->commit();
    ipn_log("TX_COMMIT");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ipn_log("DB_ERROR", ['err'=>$e->getMessage()]);
    http_response_code(200); echo "OK"; ipn_log("EARLY_EXIT_DB_ERROR"); exit;
}
// Snapshot después (para confirmar estado "Pagado" en log)
$st = $pdo->prepare("SELECT id, status FROM orders WHERE order_number=?");
$st->execute([$order_number]);
$after = $st->fetchAll(PDO::FETCH_ASSOC);
ipn_log("ORDER_STATES_AFTER", $after ?: 'none');
// ---------- Emails ----------
try {
    ipn_log("EMAIL_BLOCK_START");
    $st = $pdo->prepare("
        SELECT o.buyer_email, o.buyer_name, o.currency, o.order_number,
               s.title AS sale_title, a.name AS affiliate_name, a.email AS affiliate_email
          FROM orders o
     LEFT JOIN sales s ON s.id = o.sale_id
     LEFT JOIN affiliates a ON a.id = o.affiliate_id
         WHERE o.order_number = ?
         LIMIT 1
    ");
    $st->execute([$order_number]);
    $head = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $st = $pdo->prepare("
        SELECT o.qty, o.grand_total, p.name AS product_name
          FROM orders o
          JOIN products p ON p.id = o.product_id
         WHERE o.order_number = ?
    ");
    $st->execute([$order_number]);
    $lines = $st->fetchAll(PDO::FETCH_ASSOC);
    $total = 0.0; $listHtml = '';
    foreach ($lines as $ln) {
        $total += (float)$ln['grand_total'];
        $listHtml .= '<li>'.htmlspecialchars((string)$ln['product_name'], ENT_QUOTES, 'UTF-8').' — x'.(float)$ln['qty'].'</li>';
    }
    $buyer_email     = (string)($head['buyer_email'] ?? '');
    $buyer_name      = (string)($head['buyer_name'] ?? 'Cliente');
    $currency        = strtoupper((string)($head['currency'] ?? 'CRC'));
    $sale_title      = (string)($head['sale_title'] ?? 'Compra');
    $affiliate_email = (string)($head['affiliate_email'] ?? '');
    $affiliate_name  = (string)($head['affiliate_name'] ?? 'Vendedor');
    // Correo comprador
    if ($buyer_email !== '') {
        $subject = "✅ Pago Confirmado - Orden {$order_number}";
        $html = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
                <h2 style='color:#27ae60;'>✅ Pago Confirmado</h2>
                <p>Hola <strong>".htmlspecialchars($buyer_name, ENT_QUOTES, 'UTF-8')."</strong>,</p>
                <p>Tu pago por PayPal fue <strong>confirmado exitosamente</strong> para <strong>".htmlspecialchars($sale_title, ENT_QUOTES, 'UTF-8')."</strong>.</p>
                <div style='background:#f7fafc;padding:15px;border-radius:8px;margin:20px 0;'>
                    <h3 style='margin-top:0;color:#2c3e50;'>Productos:</h3>
                    <ul style='list-style:none;padding:0;'>{$listHtml}</ul>
                </div>
                <p style='font-size:18px;'><strong>Total:</strong> ".number_format($total,2)." ".$currency."</p>
                <p style='color:#718096;font-size:14px;'><strong>ID Transacción PayPal:</strong> ".htmlspecialchars($txn_id, ENT_QUOTES, 'UTF-8')."</p>
                <p>El vendedor procederá con el envío/entrega de tu pedido.</p>
                <hr style='border:none;border-top:1px solid #e2e8f0;margin:30px 0;'>
                <p style='color:#718096;font-size:12px;'>Gracias por tu compra en ".htmlspecialchars(APP_NAME ?? 'Marketplace', ENT_QUOTES, 'UTF-8')."</p>
            </div>
        ";
        try {
            send_mail($buyer_email, $subject, $html);
            ipn_log("EMAIL_SENT_BUYER", ['to'=>$buyer_email, 'status'=>'sent']);
        } catch (Throwable $e) {
            ipn_log("EMAIL_ERR_BUYER", ['err'=>$e->getMessage(), 'to'=>$buyer_email]);
        }
    } else {
        ipn_log("EMAIL_SKIP_BUYER_EMPTY", null);
    }
    // Correo afiliado/vendedor
    if ($affiliate_email !== '') {
        $subject = "🎉 ¡Nueva Venta Confirmada! - Orden {$order_number}";
        $st = $pdo->prepare("SELECT buyer_name, buyer_email, buyer_phone, shipping_address FROM orders WHERE order_number = ? LIMIT 1");
        $st->execute([$order_number]);
        $buyerData = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $html = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
                <h2 style='color:#27ae60;'>🎉 ¡Nueva Venta Confirmada por PayPal!</h2>
                <p>Hola <strong>".htmlspecialchars($affiliate_name, ENT_QUOTES, 'UTF-8')."</strong>,</p>
                <p>Se ha confirmado un pago por PayPal para tu espacio <strong>".htmlspecialchars($sale_title, ENT_QUOTES, 'UTF-8')."</strong>.</p>
                <div style='background:#e6f7ff;padding:15px;border-left:4px solid #3498db;margin:20px 0;'>
                    <h3 style='margin-top:0;color:#2c3e50;'>📦 Información del Pedido</h3>
                    <p style='margin:5px 0;'><strong>Orden:</strong> {$order_number}</p>
                    <p style='margin:5px 0;'><strong>Estado:</strong> <span style='color:#27ae60;font-weight:bold;'>PAGADO</span></p>
                    <p style='margin:5px 0;'><strong>Total:</strong> ".number_format($total,2)." ".$currency."</p>
                </div>
                <div style='background:#f7fafc;padding:15px;border-radius:8px;margin:20px 0;'>
                    <h3 style='margin-top:0;color:#2c3e50;'>👤 Datos del Comprador</h3>
                    <p style='margin:5px 0;'><strong>Nombre:</strong> ".htmlspecialchars($buyerData['buyer_name'] ?? '', ENT_QUOTES, 'UTF-8')."</p>
                    <p style='margin:5px 0;'><strong>Email:</strong> <a href='mailto:".htmlspecialchars($buyerData['buyer_email'] ?? '', ENT_QUOTES, 'UTF-8')."'>".htmlspecialchars($buyerData['buyer_email'] ?? '', ENT_QUOTES, 'UTF-8')."</a></p>
                    <p style='margin:5px 0;'><strong>Teléfono:</strong> ".htmlspecialchars($buyerData['buyer_phone'] ?? '', ENT_QUOTES, 'UTF-8')."</p>
                    ".(!empty($buyerData['shipping_address']) ? "<p style='margin:5px 0;'><strong>Dirección:</strong> ".htmlspecialchars($buyerData['shipping_address'], ENT_QUOTES, 'UTF-8')."</p>" : "")."
                </div>
                <div style='background:#fff4e6;padding:15px;border-radius:8px;margin:20px 0;'>
                    <h3 style='margin-top:0;color:#2c3e50;'>📋 Productos Vendidos</h3>
                    <ul style='list-style:none;padding:0;'>{$listHtml}</ul>
                </div>
                <div style='background:#e8f5e9;padding:15px;border-radius:8px;margin:20px 0;'>
                    <p style='margin:0;'><strong>✅ Próximos Pasos:</strong></p>
                    <ol style='margin:10px 0;padding-left:20px;'>
                        <li>Contacta al comprador para coordinar la entrega</li>
                        <li>Prepara el/los producto(s)</li>
                        <li>Una vez entregado, marca la orden como 'Entregado' en tu panel</li>
                    </ol>
                </div>
                <hr style='border:none;border-top:1px solid #e2e8f0;margin:30px 0;'>
                <p style='color:#718096;font-size:12px;'>ID Transacción PayPal: ".htmlspecialchars($txn_id, ENT_QUOTES, 'UTF-8')."</p>
                <p style='color:#718096;font-size:12px;'>Este es un correo automático de ".htmlspecialchars(APP_NAME ?? 'Marketplace', ENT_QUOTES, 'UTF-8')."</p>
            </div>
        ";
        try {
            send_mail($affiliate_email, $subject, $html);
            ipn_log("EMAIL_SENT_SELLER", ['to'=>$affiliate_email, 'status'=>'sent']);
        } catch (Throwable $e) {
            ipn_log("EMAIL_ERR_SELLER", ['err'=>$e->getMessage(), 'to'=>$affiliate_email]);
        }
    } else {
        ipn_log("EMAIL_SKIP_SELLER_EMPTY", null);
    }
    // Correo admin
    if (defined('ADMIN_EMAIL') && ADMIN_EMAIL) {
        try {
            send_mail(ADMIN_EMAIL, "[Admin] 💰 Pago Confirmado PayPal - {$order_number}", $html);
            ipn_log("EMAIL_SENT_ADMIN", ['to'=>ADMIN_EMAIL, 'status'=>'sent']);
        } catch (Throwable $e) {
            ipn_log("EMAIL_ERR_ADMIN", ['err'=>$e->getMessage(), 'to'=>ADMIN_EMAIL]);
        }
    } else {
        ipn_log("EMAIL_SKIP_ADMIN_EMPTY", null);
    }
    ipn_log("EMAIL_BLOCK_END");
} catch (Throwable $e) {
    ipn_log("EMAIL_BLOCK_ERR", ['err'=>$e->getMessage()]);
}
http_response_code(200);
ipn_log("========== IPN DONE ==========");
echo "OK";