<?php
/**
 * emprendedores-checkout.php
 * Checkout para productos de emprendedoras.
 * Pago agrupado por emprendedora: SINPE (evidencia + email) o PayPal.
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);
require_once __DIR__ . '/includes/config.php'; // maneja sesión con el path correcto
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php';

$isLoggedIn = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
$buyerName  = $_SESSION['name']  ?? '';
$buyerEmail = $_SESSION['email'] ?? '';

// Agrupar carrito por vendedor
$cartItems = $_SESSION['emp_cart'] ?? [];
if (empty($cartItems)) {
    header('Location: emprendedores-carrito.php');
    exit;
}

$groups = [];
foreach ($cartItems as $item) {
    $sid = (int)$item['seller_id'];
    if (!isset($groups[$sid])) {
        $groups[$sid] = [
            'seller_id'     => $sid,
            'seller_name'   => $item['seller_name'],
            'seller_email'  => $item['seller_email'],
            'seller_type'   => $item['seller_type'] ?? 'emprendedora',
            'sinpe_phone'   => $item['sinpe_phone'],
            'paypal_email'  => $item['paypal_email'],
            'accepts_sinpe' => $item['accepts_sinpe'],
            'accepts_paypal'=> $item['accepts_paypal'],
            'accepts_card'  => $item['accepts_card'] ?? 0,
            'items'         => [],
            'subtotal'      => 0,
        ];
    }
    $groups[$sid]['items'][]   = $item;
    $groups[$sid]['subtotal'] += $item['qty'] * $item['price'];
}

// Incluir costo de envío por vendedor (guardado en sesión por el carrito)
$shippingChoices = $_SESSION['emp_shipping'] ?? [];
foreach ($groups as $sid => &$g) {
    $ch = $shippingChoices[$sid] ?? null;
    $g['shipping_method'] = $ch['method']     ?? '';
    $g['shipping_zone']   = $ch['zone_name']  ?? '';
    $g['shipping_cost']   = (int)($ch['zone_price'] ?? 0);
    $g['shipping_address']= $ch['address']    ?? '';
    $g['total'] = $g['subtotal'] + $g['shipping_cost'];
}
unset($g);
$grandTotal = array_sum(array_column($groups, 'total'));

// ─── Manejo POST: upload comprobante SINPE ────────────────────────────────────
$successMessages = [];
$errorMessages   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sinpe_upload') {
    $sellerId   = (int)($_POST['seller_id'] ?? 0);
    $buyerNameP = trim($_POST['buyer_name']  ?? $buyerName);
    $buyerEmailP= trim($_POST['buyer_email'] ?? $buyerEmail);
    $buyerPhone = trim($_POST['buyer_phone'] ?? '');

    if (!isset($groups[$sellerId])) {
        $errorMessages[$sellerId] = 'Vendedor no encontrado.';
    } elseif (!filter_var($buyerEmailP, FILTER_VALIDATE_EMAIL) && empty($buyerPhone)) {
        $errorMessages[$sellerId] = 'Ingresa tu correo o teléfono para que te contacten.';
    } elseif (!isset($_FILES['comprobante_' . $sellerId]) || $_FILES['comprobante_' . $sellerId]['error'] !== UPLOAD_ERR_OK) {
        $errorMessages[$sellerId] = 'Debes adjuntar el comprobante de SINPE.';
    } else {
        $file = $_FILES['comprobante_' . $sellerId];
        $mime = '';
        if (function_exists('finfo_open')) {
            $fi   = finfo_open(FILEINFO_MIME_TYPE);
            $mime = (string)finfo_file($fi, $file['tmp_name']);
            finfo_close($fi);
        } else {
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $mime = match($ext) { 'pdf' => 'application/pdf', 'png' => 'image/png', 'webp' => 'image/webp', default => 'image/jpeg' };
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!in_array($mime, $allowed)) {
            $errorMessages[$sellerId] = 'Tipo no permitido. Usa JPG, PNG, WEBP o PDF.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errorMessages[$sellerId] = 'El archivo no puede superar 5 MB.';
        } else {
            $uploadDir = __DIR__ . '/uploads/sinpe-receipts';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
            $extMap   = ['application/pdf' => 'pdf', 'image/png' => 'png', 'image/webp' => 'webp'];
            $ext      = $extMap[$mime] ?? 'jpg';
            $filename = sprintf('sinpe_emp_%d_%d_%s.%s', $sellerId, time(), bin2hex(random_bytes(6)), $ext);
            $filepath = $uploadDir . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                $errorMessages[$sellerId] = 'Error al guardar el archivo. Intenta de nuevo.';
            } else {
                $receiptUrl = SITE_URL . '/uploads/sinpe-receipts/' . $filename;
                $group      = $groups[$sellerId];

                // Guardar en BD
                try {
                    $pdo = db();
                    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_receipts (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        listing_type TEXT, listing_id INTEGER,
                        user_id INTEGER, receipt_url TEXT NOT NULL,
                        status TEXT DEFAULT 'pending',
                        uploaded_at TEXT DEFAULT (datetime('now')),
                        reviewed_at TEXT, reviewed_by INTEGER, notes TEXT
                    )");
                    $uid = (int)($_SESSION['uid'] ?? 0);
                    $pdo->prepare("INSERT INTO payment_receipts (listing_type,listing_id,user_id,receipt_url,status,notes) VALUES ('emp_product',?,?,?,'pending',?)")
                        ->execute([$sellerId, $uid ?: null, $receiptUrl, "Comprador: {$buyerNameP} | {$buyerEmailP} | {$buyerPhone}"]);

                    // Crear registros en entrepreneur_orders para cada producto
                    $insOrd = $pdo->prepare("
                        INSERT INTO entrepreneur_orders
                            (product_id, seller_user_id, buyer_name, buyer_email, buyer_phone, quantity, total_price, status,
                             payment_method, receipt_url, shipping_method, shipping_zone, shipping_cost, shipping_address,
                             created_at, updated_at)
                        VALUES (?,?,?,?,?,?,?,'pending','sinpe',?,?,?,?,?,?,?)
                    ");
                    foreach ($group['items'] as $oi) {
                        $oPid  = (int)($oi['product_id'] ?? $oi['id'] ?? 0);
                        $oQty  = (int)($oi['qty'] ?? 1);
                        $oPrice= (float)($oi['price'] ?? 0);
                        $insOrd->execute([
                            $oPid, $sellerId, $buyerNameP, $buyerEmailP, $buyerPhone,
                            $oQty, $oQty * $oPrice,
                            $receiptUrl,
                            $group['shipping_method'] ?? '', $group['shipping_zone'] ?? '',
                            (int)($group['shipping_cost'] ?? 0), $group['shipping_address'] ?? '',
                            date('Y-m-d H:i:s'), date('Y-m-d H:i:s'),
                        ]);
                    }
                } catch (Throwable $e) { /* non-blocking */ }

                // Armar lista de productos del grupo
                $productLines = '';
                foreach ($group['items'] as $it) {
                    $productLines .= "<tr><td style='padding:6px 12px;border-bottom:1px solid #f0f0f0;'>{$it['name']}</td><td style='padding:6px 12px;border-bottom:1px solid #f0f0f0;text-align:center;'>{$it['qty']}</td><td style='padding:6px 12px;border-bottom:1px solid #f0f0f0;text-align:right;'>₡" . number_format($it['price'] * $it['qty'], 0) . "</td></tr>";
                }
                $subtotalFmt = '₡' . number_format($group['subtotal'], 0);
                $totalFmt    = '₡' . number_format($group['total'], 0);
                $shipMethod  = $group['shipping_method'] ?? '';
                $shipLabel   = match($shipMethod) {
                    'pickup'  => 'Retiro en local',
                    'free'    => 'Envío gratis',
                    'express' => 'Envío express' . ($group['shipping_zone'] ? ' — '.$group['shipping_zone'] : ''),
                    'mooving' => 'Envío Mooving' . ($group['shipping_zone'] ? ' — '.$group['shipping_zone'] : ''),
                    default   => ''
                };
                $shipLine = $shipMethod ? "
                    <tr style='background:#fffbeb;'>
                        <td colspan='2' style='padding:8px 12px;color:#92400e;'>
                            🚚 " . htmlspecialchars($shipLabel) . ($group['shipping_address'] ? ' · ' . htmlspecialchars($group['shipping_address']) : '') . "
                        </td>
                        <td style='padding:8px 12px;text-align:right;color:#92400e;font-weight:700;'>
                            " . ($group['shipping_cost'] > 0 ? '₡' . number_format($group['shipping_cost'], 0) : 'Gratis') . "
                        </td>
                    </tr>" : '';

                // ── Email a la EMPRENDEDORA ──────────────────────────────────
                if (filter_var($group['seller_email'], FILTER_VALIDATE_EMAIL)) {
                    $htmlSeller = "
                    <div style='font-family:sans-serif;max-width:600px;margin:0 auto;'>
                        <div style='background:linear-gradient(135deg,#667eea,#764ba2);padding:28px;text-align:center;border-radius:12px 12px 0 0;'>
                            <h1 style='color:white;margin:0;font-size:1.5rem;'>🎉 ¡Nuevo pedido con comprobante SINPE!</h1>
                        </div>
                        <div style='background:white;padding:28px;border:1px solid #e0e0e0;border-radius:0 0 12px 12px;'>
                            <p>Hola <strong>{$group['seller_name']}</strong>,</p>
                            <p>Un cliente ha enviado el comprobante de pago por SINPE para los siguientes productos:</p>
                            <table style='width:100%;border-collapse:collapse;margin:16px 0;'>
                                <tr style='background:#f5f5f5;'><th style='padding:8px 12px;text-align:left;'>Producto</th><th style='padding:8px 12px;'>Cantidad</th><th style='padding:8px 12px;text-align:right;'>Total</th></tr>
                                {$productLines}
                                {$shipLine}
                                <tr><td colspan='2' style='padding:8px 12px;font-weight:bold;text-align:right;'>TOTAL A COBRAR</td><td style='padding:8px 12px;font-weight:bold;text-align:right;color:#667eea;'>{$totalFmt}</td></tr>
                            </table>
                            <div style='background:#f0f7ff;padding:16px;border-radius:10px;margin:16px 0;'>
                                <strong>📋 Datos del comprador:</strong><br>
                                Nombre: {$buyerNameP}<br>
                                " . ($buyerEmailP ? "Correo: {$buyerEmailP}<br>" : '') . "
                                " . ($buyerPhone ? "Teléfono: {$buyerPhone}<br>" : '') . "
                            </div>
                            <div style='background:#fff8e1;padding:16px;border-radius:10px;'>
                                <strong>🧾 Comprobante adjunto:</strong><br>
                                <a href='{$receiptUrl}' style='color:#667eea;'>Ver comprobante</a>
                            </div>
                            <p style='margin-top:20px;color:#555;'>Verifica el comprobante en tu SINPE Móvil y coordina la entrega con tu cliente.</p>
                            <hr style='border:none;border-top:1px solid #e0e0e0;margin:20px 0;'>
                            <p style='color:#999;font-size:0.85rem;text-align:center;'>CompraTica — El Mercadito de Emprendedores Costarricenses</p>
                        </div>
                    </div>";
                    try {
                        send_email($group['seller_email'], "🛍️ Nuevo pedido SINPE - {$buyerNameP}", $htmlSeller, $buyerEmailP, $buyerNameP);
                    } catch (Throwable $e) { /* log silencioso */ }
                }

                // ── Email al ADMIN ───────────────────────────────────────────
                $htmlAdmin = "
                <div style='font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px;'>
                    <h2 style='color:#667eea;'>[Emprendedores] Nuevo comprobante SINPE</h2>
                    <p><strong>Vendedora:</strong> {$group['seller_name']} ({$group['seller_email']})</p>
                    <p><strong>Comprador:</strong> {$buyerNameP} | {$buyerEmailP} | {$buyerPhone}</p>
                    <p><strong>Total:</strong> {$subtotalFmt}</p>
                    <table style='width:100%;border-collapse:collapse;'>
                        <tr style='background:#f5f5f5;'><th style='padding:6px;text-align:left;'>Producto</th><th>Cant.</th><th>Total</th></tr>
                        {$productLines}
                    </table>
                    <p><a href='{$receiptUrl}'>Ver comprobante</a></p>
                </div>";
                try {
                    send_email(ADMIN_EMAIL, "[Emprendedores] SINPE - {$group['seller_name']} / {$buyerNameP}", $htmlAdmin);
                } catch (Throwable $e) { /* log silencioso */ }

                // ── Email de confirmación al COMPRADOR ───────────────────────
                if (filter_var($buyerEmailP, FILTER_VALIDATE_EMAIL)) {
                    $htmlBuyer = "
                    <div style='font-family:sans-serif;max-width:600px;margin:0 auto;'>
                        <div style='background:linear-gradient(135deg,#00b09b,#00d2a0);padding:28px;text-align:center;border-radius:12px 12px 0 0;'>
                            <h1 style='color:white;margin:0;'>✅ ¡Comprobante recibido!</h1>
                        </div>
                        <div style='background:white;padding:28px;border:1px solid #e0e0e0;border-radius:0 0 12px 12px;'>
                            <p>Hola <strong>{$buyerNameP}</strong>,</p>
                            <p>Hemos recibido tu comprobante de pago SINPE para tu pedido a <strong>{$group['seller_name']}</strong>.</p>
                            <table style='width:100%;border-collapse:collapse;margin:16px 0;'>
                                <tr style='background:#f5f5f5;'><th style='padding:8px;text-align:left;'>Producto</th><th>Cant.</th><th style='text-align:right;'>Total</th></tr>
                                {$productLines}
                                <tr><td colspan='2' style='padding:8px;font-weight:bold;'>TOTAL PAGADO</td><td style='padding:8px;font-weight:bold;text-align:right;color:#00b09b;'>{$subtotalFmt}</td></tr>
                            </table>
                            <p style='color:#555;'>La emprendedora revisará tu comprobante y te contactará para coordinar la entrega. ¡Gracias por apoyar a las emprendedoras costarricenses! ❤️</p>
                            <hr style='border:none;border-top:1px solid #e0e0e0;margin:20px 0;'>
                            <p style='color:#999;font-size:0.85rem;text-align:center;'>CompraTica — El Mercadito de Emprendedores Costarricenses</p>
                        </div>
                    </div>";
                    try {
                        send_email($buyerEmailP, '✅ Comprobante recibido - CompraTica Emprendedores', $htmlBuyer);
                    } catch (Throwable $e) { /* log silencioso */ }
                }

                // Marcar grupo como pagado en sesión
                $successMessages[$sellerId] = "✅ Comprobante enviado a <strong>{$group['seller_name']}</strong>. Te contactarán pronto para coordinar la entrega.";
                unset($_SESSION['emp_cart'][$item['product_id']]);
                // Eliminar todos los items de este vendedor del carrito
                foreach ($cartItems as $k => $citem) {
                    if ((int)$citem['seller_id'] === $sellerId) {
                        unset($_SESSION['emp_cart'][$citem['product_id']]);
                    }
                }
                // Recargar
                $cartItems = $_SESSION['emp_cart'];
                unset($groups[$sellerId]);
            }
        }
    }
}

// ─── Exchange rate + session PayPal pending ───────────────────────────────────
$emp_exchange_rate = 650.0;
if (!empty(array_filter(array_column($groups, 'accepts_paypal')))) {
    try {
        $pdo_er = db();
        $row_er = $pdo_er->query("SELECT exchange_rate FROM settings ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($row_er && (float)$row_er['exchange_rate'] > 0) $emp_exchange_rate = (float)$row_er['exchange_rate'];
    } catch (Throwable $e) {}
}

foreach ($groups as $sid => $group) {
    if ($group['accepts_paypal'] && $group['paypal_email']) {
        $total_crc_pp = (int)$group['total'];
        $total_usd_pp = round($total_crc_pp / $emp_exchange_rate, 2);
        if ($total_usd_pp < 0.01) $total_usd_pp = 0.01;
        $items_for_pp = [];
        foreach ($group['items'] as $it) {
            $items_for_pp[] = ['product_id' => (int)($it['product_id'] ?? $it['id'] ?? 0), 'name' => $it['name'], 'qty' => $it['qty'], 'price' => $it['price']];
        }
        $_SESSION['emp_paypal_pending'][$sid] = [
            'seller_name'    => $group['seller_name'],
            'seller_email'   => $group['seller_email'],
            'paypal_email'   => $group['paypal_email'],
            'buyer_name'     => $buyerName,
            'buyer_email'    => $buyerEmail,
            'total_crc'      => $total_crc_pp,
            'total_usd'      => $total_usd_pp,
            'items'          => $items_for_pp,
            'order_ref'      => 'EMP-' . $sid,
            'shipping_method'=> $group['shipping_method'] ?? '',
            'shipping_zone'  => $group['shipping_zone'] ?? '',
            'shipping_cost'  => (int)($group['shipping_cost'] ?? 0),
            'shipping_address'=> $group['shipping_address'] ?? '',
        ];
    }

    // Guardar contexto SwiftPay por vendedor para que swiftpay-charge.php pueda crear la orden
    if (!empty($group['accepts_card'])) {
        $_SESSION['swiftpay_checkout_emp'][$sid] = [
            'seller_id'    => $sid,
            'seller_name'  => $group['seller_name'],
            'seller_email' => $group['seller_email'],
            'buyer_name'   => $buyerName,
            'buyer_email'  => $buyerEmail,
            'buyer_phone'  => '',  // se sobreescribe con lo que manda el widget
            'items'          => $group['items'],
            'total'          => $group['total'],
            'shipping_cost'  => $group['shipping_cost'],
            'shipping_method'=> $group['shipping_method'],
            'shipping_zone'  => $group['shipping_zone'] ?? '',
            'shipping_address'=> $group['shipping_address'] ?? '',
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | CompraTica Emprendedores</title>
    <style>#cartButton{display:none!important;}</style>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/compratica-header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if (defined('PAYPAL_CLIENT_ID') && PAYPAL_CLIENT_ID): ?>
    <script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars(PAYPAL_CLIENT_ID, ENT_QUOTES) ?>&currency=USD&intent=capture&enable-funding=googlepay,applepay,card&disable-funding=paylater,venmo" data-namespace="paypal_sdk"></script>
    <?php endif; ?>
    <style>
        /* ── VARIABLES ── */
        :root {
            --primary: #1e293b;
            --primary-light: #334155;
            --accent: #3b82f6;
            --accent-green: #10b981;
            --accent-green-dark: #059669;
            --danger: #ef4444;
            --warning: #f59e0b;
            --gray-50:  #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-700: #334155;
            --gray-900: #0f172a;
            --white: #ffffff;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.10);
            --radius: 10px;
            --transition: all 0.22s cubic-bezier(0.4,0,0.2,1);
        }

        body { background: var(--gray-50); font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

        /* ── PAGE WRAP ── */
        .page-wrap {
            max-width: 780px; margin: 0 auto; padding: 28px 20px 72px;
        }

        /* ── BREADCRUMB / BACK LINK ── */
        .back-link {
            display: inline-flex; align-items: center; gap: 6px;
            color: var(--gray-500); text-decoration: none; font-size: 0.875rem;
            padding: 6px 0; transition: var(--transition); margin-bottom: 20px;
        }
        .back-link:hover { color: var(--accent); }

        /* ── PAGE TITLE ── */
        .checkout-title {
            font-size: 1.6rem; font-weight: 800; color: var(--gray-900);
            display: flex; align-items: center; gap: 10px; margin-bottom: 4px;
        }
        .checkout-title i { color: var(--accent); }
        .checkout-subtitle { color: var(--gray-500); font-size: 0.9rem; margin-bottom: 28px; }

        /* ── SELLER PANEL ── */
        .seller-panel {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 14px;
            box-shadow: var(--shadow-md);
            margin-bottom: 28px;
            overflow: hidden;
        }

        /* Header neutro — con barra de color lateral según tipo */
        .seller-panel-header {
            background: var(--primary);
            color: var(--white);
            padding: 18px 24px;
            display: flex; align-items: center; gap: 14px;
            position: relative;
        }
        .seller-panel-header::before {
            content: '';
            position: absolute; left: 0; top: 0; bottom: 0;
            width: 5px;
        }
        .seller-panel-header.type-emprendedora::before { background: #f472b6; }
        .seller-panel-header.type-emprendedor::before  { background: #60a5fa; }

        .seller-avatar {
            width: 44px; height: 44px;
            background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.25);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 1.15rem; flex-shrink: 0;
        }
        .seller-panel-header h2 { margin: 0; font-size: 1.05rem; font-weight: 700; }
        .seller-panel-header .seller-badge {
            font-size: 0.78rem; opacity: 0.75; margin-top: 2px;
        }
        .seller-panel-header .subtotal-badge {
            margin-left: auto;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 14px; border-radius: 20px;
            font-weight: 800; font-size: 1rem; white-space: nowrap;
        }

        /* ── SHIPPING INFO BAR ── */
        .shipping-bar {
            padding: 10px 24px;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            font-size: 0.83rem; color: var(--gray-500);
            display: flex; gap: 16px; align-items: center; flex-wrap: wrap;
        }

        /* ── ITEMS LIST ── */
        .items-list { padding: 18px 24px; border-bottom: 1px solid var(--gray-200); }
        .item-row {
            display: flex; gap: 14px; align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-100);
        }
        .item-row:last-child { border-bottom: none; padding-bottom: 0; }
        .item-row img {
            width: 58px; height: 58px; object-fit: cover;
            border-radius: var(--radius); background: var(--gray-100);
            flex-shrink: 0; border: 1px solid var(--gray-200);
        }
        .item-img-placeholder {
            width: 58px; height: 58px; background: var(--gray-100);
            border-radius: var(--radius); display: flex; align-items: center;
            justify-content: center; flex-shrink: 0; border: 1px solid var(--gray-200);
            color: var(--gray-400);
        }
        .item-row-name { font-weight: 600; color: var(--gray-900); font-size: 0.93rem; }
        .item-row-meta { color: var(--gray-400); font-size: 0.8rem; margin-top: 2px; }
        .item-row-total {
            margin-left: auto; font-weight: 700; color: var(--gray-700);
            white-space: nowrap; font-size: 0.95rem;
        }

        /* ── PAYMENT SECTION TITLE ── */
        .pay-section-title {
            padding: 18px 24px 12px;
            font-size: 0.78rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.08em; color: var(--gray-400);
            border-bottom: 1px solid var(--gray-200);
        }

        /* ── PAYMENT METHOD CARDS ── */
        .pay-methods { padding: 16px 24px; display: flex; flex-direction: column; gap: 10px; }
        .pay-method-card {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 18px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
            background: var(--white);
            user-select: none;
        }
        .pay-method-card:hover { border-color: var(--accent); background: #f0f7ff; }
        .pay-method-card.selected { border-color: var(--accent); background: #eff6ff; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .pay-method-icon {
            width: 38px; height: 38px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
        }
        .pay-method-icon.sinpe  { background: #d1fae5; color: var(--accent-green-dark); }
        .pay-method-icon.paypal { background: #dbeafe; color: #1d4ed8; }
        .pay-method-label { font-weight: 700; color: var(--gray-900); font-size: 0.93rem; }
        .pay-method-desc  { color: var(--gray-400); font-size: 0.8rem; margin-top: 1px; }
        .pay-method-card input[type=radio] { margin-left: auto; accent-color: var(--accent); width: 18px; height: 18px; cursor: pointer; }

        /* ── PAY PANELS ── */
        .pay-panel { display: none; padding: 0 24px 24px; }
        .pay-panel.active { display: block; }

        /* ── SINPE BOX ── */
        .sinpe-number-box {
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border: 1.5px solid #6ee7b7;
            border-radius: 12px; padding: 22px 20px;
            text-align: center; margin-bottom: 22px;
        }
        .sinpe-number-box .label {
            color: var(--gray-500); font-size: 0.82rem; margin-bottom: 6px;
            display: flex; align-items: center; justify-content: center; gap: 5px;
        }
        .sinpe-number-box .phone {
            font-size: 2rem; font-weight: 900; color: var(--accent-green-dark);
            letter-spacing: 4px; margin: 8px 0;
        }
        .sinpe-number-box .amount {
            color: var(--gray-500); font-size: 0.88rem;
        }
        .sinpe-number-box .amount strong { color: var(--gray-900); }
        .btn-whatsapp {
            display: inline-flex; align-items: center; gap: 8px;
            background: #25D366; color: white; padding: 10px 22px;
            border-radius: 8px; font-weight: 700; text-decoration: none;
            font-size: 0.88rem; margin-top: 12px; transition: var(--transition);
        }
        .btn-whatsapp:hover { background: #128C7E; transform: translateY(-1px); }

        /* ── EVIDENCE FORM ── */
        .evidence-form { }
        .evidence-title {
            font-size: 0.85rem; font-weight: 700; color: var(--gray-700);
            margin-bottom: 14px; display: flex; align-items: center; gap: 6px;
        }
        .form-row { margin-bottom: 12px; }
        .form-row label {
            display: block; font-weight: 600; color: var(--gray-700);
            margin-bottom: 5px; font-size: 0.85rem;
        }
        .form-row input[type=text],
        .form-row input[type=email],
        .form-row input[type=tel] {
            width: 100%; padding: 10px 14px;
            border: 1.5px solid var(--gray-200);
            border-radius: var(--radius); font-size: 0.93rem;
            box-sizing: border-box; background: var(--white);
            color: var(--gray-900); transition: var(--transition);
        }
        .form-row input:focus { border-color: var(--accent); outline: none; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }

        /* ── UPLOAD AREA ── */
        .upload-area {
            border: 2px dashed var(--gray-200); border-radius: 12px; padding: 28px 20px;
            text-align: center; cursor: pointer; transition: var(--transition);
            background: var(--gray-50); margin-bottom: 16px;
        }
        .upload-area:hover, .upload-area.drag {
            border-color: var(--accent); background: #eff6ff;
        }
        .upload-area .upload-icon { font-size: 2rem; color: var(--gray-400); margin-bottom: 8px; }
        .upload-area .upload-main { font-weight: 600; color: var(--gray-700); font-size: 0.9rem; }
        .upload-area .hint { color: var(--gray-400); font-size: 0.8rem; margin-top: 4px; }
        .upload-area input[type=file] { display: none; }
        .file-preview {
            margin-top: 10px; font-size: 0.85rem; color: var(--accent-green-dark);
            display: none; font-weight: 600;
        }

        /* ── SEND BUTTON ── */
        .btn-send {
            width: 100%; padding: 14px;
            background: var(--primary); color: white;
            border: none; border-radius: var(--radius);
            font-size: 1rem; font-weight: 700; cursor: pointer; transition: var(--transition);
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-send:hover { background: var(--primary-light); transform: translateY(-1px); box-shadow: var(--shadow-md); }

        /* ── PAYPAL PANEL ── */
        .paypal-panel { text-align: center; padding: 10px 0; }
        .paypal-desc { color: var(--gray-500); margin-bottom: 20px; font-size: 0.9rem; line-height: 1.6; }
        .btn-paypal {
            display: inline-flex; align-items: center; gap: 10px;
            background: #003087; color: white; padding: 14px 36px;
            border-radius: var(--radius); font-size: 1rem; font-weight: 700;
            text-decoration: none; transition: var(--transition);
        }
        .btn-paypal:hover { background: #002070; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(0,48,135,0.25); }

        /* ── ALERTS ── */
        .alert-success {
            background: #f0fdf4; border: 1px solid #86efac; color: #166534;
            padding: 14px 18px; border-radius: var(--radius); margin-bottom: 20px;
            font-weight: 600; font-size: 0.9rem;
            display: flex; align-items: center; gap: 8px;
        }
        .alert-error {
            background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b;
            padding: 12px 16px; border-radius: var(--radius); margin: 0 24px 16px;
            font-size: 0.875rem;
        }

        /* ── ALL DONE ── */
        .all-done {
            text-align: center; background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 14px; padding: 52px 24px; margin-top: 20px;
            box-shadow: var(--shadow-md);
        }
        .all-done .done-icon {
            width: 72px; height: 72px; background: #d1fae5;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px; font-size: 2rem; color: var(--accent-green-dark);
        }
        .all-done h2 { color: var(--gray-900); margin-bottom: 8px; }
        .all-done p { color: var(--gray-500); margin-bottom: 24px; }
        .btn-continue {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--primary); color: white;
            padding: 12px 28px; border-radius: var(--radius);
            text-decoration: none; font-weight: 700; font-size: 0.95rem;
            transition: var(--transition);
        }
        .btn-continue:hover { background: var(--primary-light); }

        /* ── GRAND TOTAL CARD ── */
        .grand-total-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 14px;
            box-shadow: var(--shadow-md);
            padding: 20px 24px;
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 8px;
        }
        .grand-total-label { color: var(--gray-500); font-size: 0.85rem; margin-bottom: 2px; }
        .grand-total-amount { font-weight: 800; font-size: 1.4rem; color: var(--gray-900); }
        .grand-total-note { color: var(--gray-400); font-size: 0.78rem; text-align: right; line-height: 1.4; }

        /* ── RESPONSIVE ── */
        @media (max-width: 640px) {
            .page-wrap { padding: 16px 12px 48px; }
            .form-cols-2 { display: block !important; }
            .form-cols-2 > div { margin-bottom: 10px; }
            .seller-panel-header { padding: 14px 16px; }
            .items-list, .pay-methods, .pay-panel { padding-left: 16px; padding-right: 16px; }
            .sinpe-number-box .phone { font-size: 1.6rem; letter-spacing: 2px; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="page-wrap">
    <a href="emprendedores-carrito.php" class="back-link"><i class="fas fa-arrow-left"></i> Volver al carrito</a>

    <h1 class="checkout-title"><i class="fas fa-lock"></i> Finalizar Compra</h1>
    <p class="checkout-subtitle">El pago se realiza directamente a cada vendedor/a.</p>

    <?php foreach ($successMessages as $sid => $msg): ?>
        <div class="alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
    <?php endforeach; ?>

    <?php if (empty($groups)): ?>
    <div class="all-done">
        <div class="done-icon"><i class="fas fa-check"></i></div>
        <h2>¡Todo listo!</h2>
        <p>Tus comprobantes fueron enviados. Los vendedores te contactarán pronto para coordinar la entrega.</p>
        <a href="emprendedores-catalogo.php" class="btn-continue"><i class="fas fa-store"></i> Seguir comprando</a>
    </div>

    <?php else: foreach ($groups as $sid => $group): ?>

    <?php $sellerType = $group['seller_type'] ?? 'emprendedora'; ?>
    <div class="seller-panel" id="panel-<?= $sid ?>">
        <div class="seller-panel-header type-<?= htmlspecialchars($sellerType) ?>">
            <div class="seller-avatar"><?= strtoupper(substr($group['seller_name'], 0, 1)) ?></div>
            <div>
                <h2><?= htmlspecialchars($group['seller_name']) ?></h2>
                <div class="seller-badge"><?= $sellerType === 'emprendedor' ? 'Emprendedor verificado' : 'Emprendedora verificada' ?> ✓</div>
            </div>
            <div class="subtotal-badge">₡<?= number_format($group['total'], 0) ?></div>
        </div>

        <?php if (!empty($group['shipping_method'])): ?>
        <div class="shipping-bar">
            <span>
                <i class="fas fa-<?= $group['shipping_method']==='pickup'?'store':($group['shipping_method']==='free'?'gift':($group['shipping_method']==='mooving'?'motorcycle':'shipping-fast')) ?>"></i>
                <?php
                    echo match($group['shipping_method']) {
                        'pickup'  => 'Retiro en local',
                        'free'    => 'Envío gratis',
                        'express' => 'Envío express' . ($group['shipping_zone'] ? ' — '.$group['shipping_zone'] : ''),
                        'mooving' => 'Envío Mooving' . ($group['shipping_zone'] ? ' — '.$group['shipping_zone'] : ''),
                        default   => ''
                    };
                ?>
            </span>
            <?php if (!empty($group['shipping_address'])): ?>
            <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($group['shipping_address']) ?></span>
            <?php endif; ?>
            <?php if ($group['shipping_cost'] > 0): ?>
            <span style="color:var(--warning);font-weight:700;"><i class="fas fa-truck"></i> ₡<?= number_format($group['shipping_cost'], 0) ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Items -->
        <div class="items-list">
            <?php foreach ($group['items'] as $item): ?>
            <div class="item-row">
                <?php if ($item['image']): ?>
                    <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                <?php else: ?>
                    <div class="item-img-placeholder"><i class="fas fa-image"></i></div>
                <?php endif; ?>
                <div>
                    <div class="item-row-name"><?= htmlspecialchars($item['name']) ?></div>
                    <div class="item-row-meta">Cantidad: <?= $item['qty'] ?> × ₡<?= number_format($item['price'], 0) ?></div>
                </div>
                <div class="item-row-total">₡<?= number_format($item['qty'] * $item['price'], 0) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (isset($errorMessages[$sid])): ?>
            <div class="alert-error" style="margin:16px 24px;"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($errorMessages[$sid]) ?></div>
        <?php endif; ?>

        <!-- Tabs de pago -->
        <?php $hasSinpe  = $group['accepts_sinpe'] && $group['sinpe_phone'];
              $hasPaypal = $group['accepts_paypal'] && $group['paypal_email'];
              $hasCard   = !empty($group['accepts_card']);
              $defaultTab = $hasSinpe ? 'sinpe' : ($hasPaypal ? 'paypal' : ($hasCard ? 'card' : ''));
        ?>

        <?php if ($hasSinpe || $hasPaypal || $hasCard): ?>
        <div class="pay-section-title"><i class="fas fa-credit-card"></i> Método de Pago</div>
        <div class="pay-methods">
            <?php if ($hasSinpe): ?>
                <div class="pay-method-card <?= $defaultTab === 'sinpe' ? 'selected' : '' ?>"
                     onclick="switchTab(<?= $sid ?>, 'sinpe', this)">
                    <div class="pay-method-icon sinpe"><i class="fas fa-mobile-alt"></i></div>
                    <div>
                        <div class="pay-method-label">SINPE Móvil</div>
                        <div class="pay-method-desc">Transferencia instantánea</div>
                    </div>
                    <input type="radio" name="pay_method_<?= $sid ?>" value="sinpe" <?= $defaultTab === 'sinpe' ? 'checked' : '' ?>>
                </div>
            <?php endif; ?>
            <?php if ($hasPaypal): ?>
                <div class="pay-method-card <?= $defaultTab === 'paypal' && !$hasSinpe ? 'selected' : '' ?>"
                     onclick="switchTab(<?= $sid ?>, 'paypal', this)">
                    <div class="pay-method-icon paypal"><i class="fab fa-paypal"></i></div>
                    <div>
                        <div class="pay-method-label">PayPal</div>
                        <div class="pay-method-desc">Pago con tarjeta o cuenta PayPal</div>
                    </div>
                    <input type="radio" name="pay_method_<?= $sid ?>" value="paypal" <?= $defaultTab === 'paypal' && !$hasSinpe ? 'checked' : '' ?>>
                </div>
            <?php endif; ?>
            <?php if ($hasCard): ?>
                <div class="pay-method-card <?= $defaultTab === 'card' ? 'selected' : '' ?>"
                     onclick="switchTab(<?= $sid ?>, 'card', this)">
                    <div class="pay-method-icon" style="background:linear-gradient(135deg,#0d1b3e,#1a3a8f);color:#fff;">
                        <img src="/assets/img/swiftpay-logo.png" alt="SwiftPay" style="height:22px;border-radius:3px;"
                             onerror="this.outerHTML='<i class=\'fas fa-credit-card\'></i>'">
                    </div>
                    <div>
                        <div class="pay-method-label">Pago con Tarjeta</div>
                        <div class="pay-method-desc">Visa, Mastercard, Amex</div>
                    </div>
                    <input type="radio" name="pay_method_<?= $sid ?>" value="card" <?= $defaultTab === 'card' ? 'checked' : '' ?>>
                </div>
            <?php endif; ?>
        </div>

        <!-- Panel SINPE -->
        <?php if ($hasSinpe): ?>
        <div class="pay-panel <?= $defaultTab === 'sinpe' ? 'active' : '' ?>" id="tab-<?= $sid ?>-sinpe">
            <div class="sinpe-number-box">
                <div class="label"><i class="fas fa-mobile-alt"></i> Número SINPE Móvil de <?= htmlspecialchars($group['seller_name']) ?></div>
                <div class="phone"><?= htmlspecialchars($group['sinpe_phone']) ?></div>
                <div class="amount">
                    Monto a transferir: <strong>₡<?= number_format($group['total'], 0) ?></strong>
                    <?php if ($group['shipping_cost'] > 0): ?>
                    <span style="color:var(--warning);font-size:.8rem;"> (incluye ₡<?= number_format($group['shipping_cost'],0) ?> de envío)</span>
                    <?php endif; ?>
                </div>
                <?php
                $waPhone = preg_replace('/\D/', '', $group['sinpe_phone']);
                $waMsg   = urlencode('Hola ' . $group['seller_name'] . ', te acabo de hacer una transferencia SINPE de ₡' . number_format($group['total'], 0) . ' por mi pedido en CompraTica.');
                ?>
                <a href="https://wa.me/506<?= $waPhone ?>?text=<?= $waMsg ?>" target="_blank" rel="noopener" class="btn-whatsapp">
                    <i class="fab fa-whatsapp"></i> Avisar por WhatsApp
                </a>
            </div>

            <div class="evidence-form">
                <div class="evidence-title"><i class="fas fa-upload" style="color:var(--accent);"></i> Adjunta tu comprobante de pago</div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="sinpe_upload">
                    <input type="hidden" name="seller_id" value="<?= $sid ?>">

                    <div class="form-cols-2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-row">
                            <label>Tu nombre</label>
                            <input type="text" name="buyer_name" value="<?= htmlspecialchars($buyerName) ?>" placeholder="Nombre completo" required>
                        </div>
                        <div class="form-row">
                            <label>Tu teléfono</label>
                            <input type="tel" name="buyer_phone" placeholder="8888-8888">
                        </div>
                    </div>
                    <div class="form-row">
                        <label>Tu correo electrónico</label>
                        <input type="email" name="buyer_email" value="<?= htmlspecialchars($buyerEmail) ?>" placeholder="correo@ejemplo.com">
                    </div>

                    <div class="upload-area" id="drop-<?= $sid ?>"
                         onclick="document.getElementById('file-<?= $sid ?>').click()"
                         ondragover="event.preventDefault();this.classList.add('drag')"
                         ondragleave="this.classList.remove('drag')"
                         ondrop="handleDrop(event, <?= $sid ?>)">
                        <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                        <div class="upload-main"><strong>Haz clic o arrastra</strong> tu comprobante aquí</div>
                        <div class="hint">JPG, PNG, WEBP o PDF — máx. 5 MB</div>
                        <input type="file" id="file-<?= $sid ?>" name="comprobante_<?= $sid ?>"
                               accept="image/jpeg,image/png,image/webp,application/pdf"
                               onchange="showPreview(this, <?= $sid ?>)">
                        <div class="file-preview" id="preview-<?= $sid ?>"><i class="fas fa-check-circle"></i> <span></span></div>
                    </div>

                    <button type="submit" class="btn-send">
                        <i class="fas fa-paper-plane"></i> Enviar Comprobante
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Panel PayPal SDK -->
        <?php if ($hasPaypal): ?>
        <?php $ppAmountUsd = number_format(round($group['total'] / $emp_exchange_rate, 2), 2); ?>
        <div class="pay-panel <?= $defaultTab === 'paypal' && !$hasSinpe ? 'active' : '' ?>" id="tab-<?= $sid ?>-paypal">
            <div class="paypal-panel">
                <p class="paypal-desc">
                    Pago seguro — tarjeta, Google Pay, Apple Pay o cuenta PayPal.<br>
                    <strong>US$<?= $ppAmountUsd ?></strong> (≈ ₡<?= number_format($group['total'], 0) ?>)
                </p>
                <div id="paypal-buttons-<?= $sid ?>"></div>
                <div id="paypal-error-<?= $sid ?>" style="display:none;color:#991b1b;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:10px 14px;margin-top:12px;font-size:.88rem;"></div>
                <div id="paypal-success-<?= $sid ?>" style="display:none;text-align:center;padding:20px 0;">
                    <div style="color:#166534;font-size:1.1rem;font-weight:700;"><i class="fas fa-check-circle"></i> ¡Pago completado!</div>
                    <p style="color:#64748b;font-size:.9rem;margin-top:8px;">Recibirás un correo de confirmación. ¡Gracias!</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Panel Pago con Tarjeta (SwiftPay) -->
        <?php if ($hasCard): ?>
        <div class="pay-panel <?= $defaultTab === 'card' ? 'active' : '' ?>" id="tab-<?= $sid ?>-card">
            <div style="padding:16px 0 0;">
              <?php
                $sp_amount          = number_format($group['total'], 2, '.', '');
                $sp_currency        = 'CRC';
                $sp_description     = 'Compra en CompraTica a ' . $group['seller_name'];
                $sp_reference_id    = (int)$group['seller_id'];
                $sp_reference_table = 'entrepreneur_orders';
                $sp_sale_id         = (int)$group['seller_id'];  // usado para lookup de sesión
                $sp_extra_fields    = [];
                $sp_success_url     = '/emprendedoras-checkout.php?payment=ok';
                $sp_cancel_url      = '';
                include __DIR__ . '/views/swiftpay-button.php';
              ?>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div style="padding:24px;color:var(--gray-400);text-align:center;font-size:.9rem;">
            <i class="fas fa-info-circle"></i> Este vendedor/a no tiene métodos de pago configurados.
            Contácta directamente.
        </div>
        <?php endif; ?>

    </div><!-- /.seller-panel -->

    <?php endforeach; endif; ?>

    <!-- Resumen total -->
    <?php if (!empty($groups)): ?>
    <div class="grand-total-card">
        <div>
            <div class="grand-total-label"><?= count($groups) ?> vendedor<?= count($groups) > 1 ? 'es/as' : '/a' ?></div>
            <div class="grand-total-amount">₡<?= number_format($grandTotal, 0) ?></div>
        </div>
        <div class="grand-total-note">Pago directo<br>a cada vendedor/a</div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
document.querySelectorAll('#hamburger-menu a').forEach(function(a) {
    if (a.getAttribute('href') === 'cart' || a.getAttribute('href') === '/cart') {
        a.setAttribute('href', '/emprendedores-carrito.php');
    }
});

function switchTab(sid, tab, cardEl) {
    // Update card selection
    document.querySelectorAll('#panel-' + sid + ' .pay-method-card').forEach(c => c.classList.remove('selected'));
    if (cardEl) {
        cardEl.classList.add('selected');
        const radio = cardEl.querySelector('input[type=radio]');
        if (radio) radio.checked = true;
    }
    // Show correct panel
    document.querySelectorAll('#panel-' + sid + ' .pay-panel').forEach(p => p.classList.remove('active'));
    const panel = document.getElementById('tab-' + sid + '-' + tab);
    if (panel) panel.classList.add('active');
}

function showPreview(input, sid) {
    const preview = document.getElementById('preview-' + sid);
    if (input.files && input.files[0]) {
        preview.style.display = 'block';
        preview.querySelector('span').textContent = input.files[0].name;
    }
}

function handleDrop(event, sid) {
    event.preventDefault();
    document.getElementById('drop-' + sid).classList.remove('drag');
    const input = document.getElementById('file-' + sid);
    if (event.dataTransfer.files.length) {
        const dt = new DataTransfer();
        dt.items.add(event.dataTransfer.files[0]);
        input.files = dt.files;
        showPreview(input, sid);
    }
}

function showPaypalError(sid, msg) {
    var el = document.getElementById('paypal-error-' + sid);
    if (el) { el.textContent = msg; el.style.display = 'block'; }
}
</script>

<?php if (defined('PAYPAL_CLIENT_ID') && PAYPAL_CLIENT_ID): ?>
<script>
<?php foreach ($groups as $sid => $group): ?>
<?php if ($group['accepts_paypal'] && $group['paypal_email']): ?>
(function(sid) {
    if (typeof paypal_sdk === 'undefined') {
        var c = document.getElementById('paypal-buttons-' + sid);
        if (c) c.innerHTML = '<p style="color:#64748b;font-size:.85rem;text-align:center;">PayPal no disponible en este momento.</p>';
        return;
    }
    paypal_sdk.Buttons({
        style: { layout: 'vertical', label: 'pay', shape: 'rect', color: 'blue' },
        createOrder: function() {
            return fetch('/api/paypal-create-order-emp.php?sid=' + sid, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.id) {
                    showPaypalError(sid, data.error || 'No se pudo iniciar el pago. Intenta de nuevo.');
                    return new Promise(function() {});
                }
                return data.id;
            })
            .catch(function() {
                showPaypalError(sid, 'No se pudo conectar con PayPal. Verifica tu conexión.');
                return new Promise(function() {});
            });
        },
        onApprove: function(data) {
            return fetch('/api/paypal-capture-order-emp.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ orderID: data.orderID, sid: sid })
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    var btns = document.getElementById('paypal-buttons-' + sid);
                    if (btns) btns.style.display = 'none';
                    var ok = document.getElementById('paypal-success-' + sid);
                    if (ok) ok.style.display = 'block';
                } else {
                    showPaypalError(sid, res.error || 'Error al procesar el pago. Intenta de nuevo.');
                }
            })
            .catch(function(e) { showPaypalError(sid, 'Error inesperado: ' + e.message); });
        },
        onError: function(err) {
            showPaypalError(sid, 'Error de PayPal: ' + (err.message || String(err)));
        }
    }).render('#paypal-buttons-' + sid);
})(<?= (int)$sid ?>);
<?php endif; ?>
<?php endforeach; ?>
</script>
<?php endif; ?>
</body>
</html>
