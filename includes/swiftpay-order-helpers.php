<?php
/**
 * includes/swiftpay-order-helpers.php
 * ─────────────────────────────────────────────────────────────────────
 * Funciones compartidas para crear órdenes después de un pago SwiftPay aprobado.
 * Usadas tanto por swiftpay-charge.php (flujo sin 3DS)
 * como por swiftpay-3ds-return.php (flujo con 3DS).
 *
 * Requiere: SwiftPayClient.php, mailer.php, email_template.php
 */

declare(strict_types=1);

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
        error_log('[swiftpay-order-helpers] crearOrden: contexto de sesión incompleto');
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
        error_log('[swiftpay-order-helpers] DB error: ' . $e->getMessage());
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

        $esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $contactBox = fn(string $name, string $email, string $phone, string $label) =>
            '<div style="margin:20px 0;padding:14px 16px;background:#f8f9fa;border-left:3px solid #e53935;border-radius:6px;">'
            . '<p style="margin:0 0 6px;font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.05em;">' . $label . '</p>'
            . '<p style="margin:0 0 4px;font-size:15px;font-weight:700;color:#1a202c;">' . $esc($name) . '</p>'
            . ($email ? '<p style="margin:0 0 4px;font-size:13px;color:#555;"><a href="mailto:' . $esc($email) . '" style="color:#e53935;text-decoration:none;">' . $esc($email) . '</a></p>' : '')
            . ($phone ? '<p style="margin:0;font-size:13px;color:#555;">📞 ' . $esc($phone) . '</p>' : '')
            . '</div>';

        // Correo al comprador
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

        // Correo al vendedor
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

        // Correo al admin
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
        error_log('[swiftpay-order-helpers] email error: ' . $e->getMessage());
    }

    return '/order-success.php?order=' . urlencode($orderNumber);
}

// ══════════════════════════════════════════════════════════════════════
// Crea una orden de emprendedora en DB, descuenta stock, limpia carrito
// y envía correos de confirmación.
// ══════════════════════════════════════════════════════════════════════
function crearOrdenEmprendedoraSwiftPay(PDO $pdo, SwiftPayResult $result, int $sellerId, string $customerPhone, string $deliveryNotes): string
{
    $ctx = $_SESSION['swiftpay_checkout_emp'][$sellerId] ?? [];

    $sellerName  = (string)($ctx['seller_name']  ?? '');
    $sellerEmail = (string)($ctx['seller_email'] ?? '');
    $buyerName   = (string)($ctx['buyer_name']   ?? '');
    $buyerEmail  = (string)($ctx['buyer_email']  ?? '');
    $buyerPhone  = $customerPhone ?: (string)($ctx['buyer_phone'] ?? '');
    $items       = (array)($ctx['items']         ?? []);
    $total       = (float)($ctx['total']         ?? 0);
    $txnId       = $result->orderId ?: $result->authCode;

    if (empty($items)) {
        error_log('[swiftpay-order-helpers] crearOrdenEmprendedora: contexto vacío para seller ' . $sellerId);
        return '/emprendedoras-checkout.php?payment=ok';
    }

    // ── Crear registros en entrepreneur_orders ─────────────────────
    $shippingMethod  = (string)($ctx['shipping_method']  ?? '');
    $shippingZone    = (string)($ctx['shipping_zone']    ?? '');
    $shippingCost    = (int)($ctx['shipping_cost']       ?? 0);
    $shippingAddress = (string)($ctx['shipping_address'] ?? '');

    try {
        $pdo->beginTransaction();
        $ins = $pdo->prepare("
            INSERT INTO entrepreneur_orders
                (product_id, seller_user_id, buyer_name, buyer_email, buyer_phone, quantity, total_price, status, notes,
                 payment_method, payment_ref, shipping_method, shipping_zone, shipping_cost, shipping_address,
                 created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        foreach ($items as $it) {
            $pid   = (int)($it['product_id'] ?? $it['id'] ?? 0);
            $qty   = (int)($it['qty'] ?? 1);
            $price = (float)($it['price'] ?? 0);
            $ins->execute([
                $pid, $sellerId, $buyerName, $buyerEmail, $buyerPhone,
                $qty, $qty * $price, 'confirmed',
                trim($deliveryNotes),
                'swiftpay', $txnId,
                $shippingMethod, $shippingZone, $shippingCost, $shippingAddress,
                date('Y-m-d H:i:s'), date('Y-m-d H:i:s'),
            ]);
            if ($pid > 0 && $qty > 0) {
                $pdo->prepare("UPDATE entrepreneur_products SET stock = stock - ?, updated_at = datetime('now') WHERE id = ? AND stock >= ?")
                    ->execute([$qty, $pid, $qty]);
            }
        }

        $cartItems = $_SESSION['emp_cart'] ?? [];
        foreach ($cartItems as $k => $it) {
            if ((int)($it['seller_id'] ?? 0) === $sellerId) {
                unset($_SESSION['emp_cart'][$k]);
            }
        }
        unset($_SESSION['swiftpay_checkout_emp'][$sellerId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[swiftpay-order-helpers] DB error emprendedora: ' . $e->getMessage());
        return '/emprendedoras-checkout.php?payment=ok';
    }

    // ── Emails de confirmación ─────────────────────────────────────
    try {
        $esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $contactBox = fn(string $name, string $email, string $phone, string $label) =>
            '<div style="margin:20px 0;padding:14px 16px;background:#f8f9fa;border-left:3px solid #667eea;border-radius:6px;">'
            . '<p style="margin:0 0 6px;font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.05em;">' . $label . '</p>'
            . '<p style="margin:0 0 4px;font-size:15px;font-weight:700;color:#1a202c;">' . $esc($name) . '</p>'
            . ($email ? '<p style="margin:0 0 4px;font-size:13px;color:#555;"><a href="mailto:' . $esc($email) . '" style="color:#667eea;text-decoration:none;">' . $esc($email) . '</a></p>' : '')
            . ($phone ? '<p style="margin:0;font-size:13px;color:#555;">&#128222; ' . $esc($phone) . '</p>' : '')
            . '</div>';

        $emailItems = [];
        foreach ($items as $it) {
            $qty   = (int)($it['qty'] ?? 1);
            $price = (float)($it['price'] ?? 0);
            $emailItems[] = ['name' => $it['name'] ?? 'Producto', 'qty' => $qty, 'unit_price' => $price, 'line_total' => $qty * $price];
        }
        $txnSafe = htmlspecialchars($txnId, ENT_QUOTES, 'UTF-8');

        if ($buyerEmail !== '') {
            $body = '
              <div style="text-align:center;margin-bottom:24px;">
                <span style="font-size:40px;">&#10003;</span>
                <h2 style="margin:8px 0 4px;font-size:22px;color:#2e7d32;">Pago con tarjeta confirmado</h2>
              </div>
              <p style="font-size:15px;margin:0 0 20px;color:#555;">Tu pago fue procesado exitosamente.</p>
              ' . email_product_table($emailItems, 'CRC') . '
              ' . email_total_block($total, 0, $total, 'CRC') . '
              ' . $contactBox($sellerName, $sellerEmail, '', 'Datos del vendedor/a') . '
              <p style="margin:8px 0 0;font-size:12px;color:#bbb;">Ref. SwiftPay: ' . $txnSafe . '</p>';
            @send_mail($buyerEmail, 'Pago confirmado — CompraTica Emprendedores', email_html($body));
        }

        if ($sellerEmail !== '' && strtolower($sellerEmail) !== strtolower($buyerEmail)) {
            $body = '
              <h2 style="margin:0 0 16px;font-size:22px;color:#333;">Pago con tarjeta recibido</h2>
              <p style="font-size:15px;margin:0 0 4px;color:#555;">Hola <strong>' . $esc($sellerName) . '</strong>, recibiste un pago con tarjeta:</p>
              ' . email_product_table($emailItems, 'CRC') . '
              ' . email_total_block($total, 0, $total, 'CRC') . '
              ' . $contactBox($buyerName, $buyerEmail, $buyerPhone, 'Datos del comprador') . '
              <p style="margin:8px 0 0;font-size:12px;color:#bbb;">Ref. SwiftPay: ' . $txnSafe . '</p>';
            @send_mail($sellerEmail, '[CompraTica] Pago con tarjeta — Emprendedores', email_html($body));
        }

        $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : '';
        if ($adminEmail !== '' && strtolower($adminEmail) !== strtolower($buyerEmail)) {
            $body = '
              <h2 style="margin:0 0 4px;font-size:20px;color:#333;">SwiftPay Emprendedoras</h2>
              <p>Comprador: ' . $esc($buyerName) . ' &lt;' . $esc($buyerEmail) . '&gt;</p>
              <p>Vendedor: ' . $esc($sellerName) . ' &lt;' . $esc($sellerEmail) . '&gt;</p>
              ' . email_product_table($emailItems, 'CRC') . '
              ' . email_total_block($total, 0, $total, 'CRC') . '
              <p>Ref: ' . $txnSafe . '</p>';
            @send_mail($adminEmail, '[ADMIN] SwiftPay Emprendedora — ' . $txnId, email_html($body));
        }
    } catch (Throwable $e) {
        error_log('[swiftpay-order-helpers] Email emprendedora error: ' . $e->getMessage());
    }

    return '/emprendedoras-checkout.php?payment=ok';
}

// ══════════════════════════════════════════════════════════════════════
// Activa una publicación de Bienes Raíces tras pago SwiftPay aprobado.
// ══════════════════════════════════════════════════════════════════════
function crearOrdenRealEstateSwiftPay(PDO $pdo, SwiftPayResult $result, int $listingId): string
{
    try {
        $pdo->prepare("
            UPDATE real_estate_listings
            SET payment_status = 'confirmed',
                is_active      = 1,
                payment_id     = ?,
                payment_date   = datetime('now'),
                updated_at     = datetime('now')
            WHERE id = ?
        ")->execute([$result->txId ?? $result->orderId ?? '', $listingId]);
    } catch (Throwable $e) {
        error_log('[swiftpay-order-helpers] real_estate update error: ' . $e->getMessage());
    }

    return '/real-estate/dashboard.php?msg=payment_success';
}
