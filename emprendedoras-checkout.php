<?php
/**
 * emprendedoras-checkout.php
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
    header('Location: emprendedoras-carrito.php');
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
            'sinpe_phone'   => $item['sinpe_phone'],
            'paypal_email'  => $item['paypal_email'],
            'accepts_sinpe' => $item['accepts_sinpe'],
            'accepts_paypal'=> $item['accepts_paypal'],
            'items'         => [],
            'subtotal'      => 0,
        ];
    }
    $groups[$sid]['items'][]   = $item;
    $groups[$sid]['subtotal'] += $item['qty'] * $item['price'];
}
$grandTotal = array_sum(array_column($groups, 'subtotal'));

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
                } catch (Throwable $e) { /* non-blocking */ }

                // Armar lista de productos del grupo
                $productLines = '';
                foreach ($group['items'] as $it) {
                    $productLines .= "<tr><td style='padding:6px 12px;border-bottom:1px solid #f0f0f0;'>{$it['name']}</td><td style='padding:6px 12px;border-bottom:1px solid #f0f0f0;text-align:center;'>{$it['qty']}</td><td style='padding:6px 12px;border-bottom:1px solid #f0f0f0;text-align:right;'>₡" . number_format($it['price'] * $it['qty'], 0) . "</td></tr>";
                }
                $subtotalFmt = '₡' . number_format($group['subtotal'], 0);

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
                                <tr><td colspan='2' style='padding:8px 12px;font-weight:bold;text-align:right;'>TOTAL A COBRAR</td><td style='padding:8px 12px;font-weight:bold;text-align:right;color:#667eea;'>{$subtotalFmt}</td></tr>
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
                            <p style='color:#999;font-size:0.85rem;text-align:center;'>CompraTica — El Mercadito de Emprendedoras Costarricenses</p>
                        </div>
                    </div>";
                    try {
                        send_email($group['seller_email'], "🛍️ Nuevo pedido SINPE - {$buyerNameP}", $htmlSeller, $buyerEmailP, $buyerNameP);
                    } catch (Throwable $e) { /* log silencioso */ }
                }

                // ── Email al ADMIN ───────────────────────────────────────────
                $htmlAdmin = "
                <div style='font-family:sans-serif;max-width:600px;margin:0 auto;padding:20px;'>
                    <h2 style='color:#667eea;'>[Emprendedoras] Nuevo comprobante SINPE</h2>
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
                    send_email(ADMIN_EMAIL, "[Emprendedoras] SINPE - {$group['seller_name']} / {$buyerNameP}", $htmlAdmin);
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
                            <p style='color:#999;font-size:0.85rem;text-align:center;'>CompraTica — El Mercadito de Emprendedoras Costarricenses</p>
                        </div>
                    </div>";
                    try {
                        send_email($buyerEmailP, '✅ Comprobante recibido - CompraTica Emprendedoras', $htmlBuyer);
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | CompraTica Emprendedoras</title>
    <style>#cartButton{display:none!important;}</style>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/compratica-header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-wrap { max-width: 820px; margin: 40px auto; padding: 0 20px 60px; }
        h1 { font-size: 1.8rem; font-weight: 800; color: #333; margin-bottom: 6px; }
        .back-link { color: #667eea; text-decoration: none; font-size: 0.9rem; }

        /* Panel por emprendedora */
        .seller-panel {
            background: white; border-radius: 18px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.09); margin-bottom: 36px; overflow: hidden;
        }
        .seller-panel-header {
            background: linear-gradient(135deg, #ff6b9d, #ec4899);
            color: white; padding: 18px 24px; display: flex; align-items: center; gap: 12px;
        }
        .seller-avatar {
            width: 42px; height: 42px; background: rgba(255,255,255,0.3);
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; font-weight: 800; font-size: 1.1rem; flex-shrink: 0;
        }
        .seller-panel-header h2 { margin: 0; font-size: 1.15rem; }
        .seller-panel-header .subtotal-badge {
            margin-left: auto; background: rgba(255,255,255,0.25);
            padding: 6px 14px; border-radius: 20px; font-weight: 800; font-size: 1.1rem;
        }

        /* Lista items */
        .items-list { padding: 16px 24px; border-bottom: 1px solid #f0f0f0; }
        .item-row { display: flex; gap: 14px; align-items: center; margin-bottom: 12px; }
        .item-row:last-child { margin-bottom: 0; }
        .item-row img { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; background: #f5f5f5; flex-shrink: 0; }
        .item-row-name { font-weight: 700; color: #333; font-size: 0.95rem; }
        .item-row-meta { color: #888; font-size: 0.82rem; }
        .item-row-total { margin-left: auto; font-weight: 800; color: #667eea; white-space: nowrap; }

        /* Métodos de pago */
        .pay-tabs { display: flex; gap: 0; border-bottom: 2px solid #f0f0f0; }
        .pay-tab {
            flex: 1; padding: 14px; text-align: center; cursor: pointer; font-weight: 700;
            color: #888; border-bottom: 3px solid transparent; transition: all 0.2s; font-size: 0.95rem;
        }
        .pay-tab.active { color: #667eea; border-bottom-color: #667eea; }
        .pay-tab:hover { color: #667eea; }

        .pay-panel { padding: 24px; display: none; }
        .pay-panel.active { display: block; }

        /* SINPE */
        .sinpe-number-box {
            background: linear-gradient(135deg, #e8fff8, #d1fae5);
            border: 2px solid #00b09b; border-radius: 14px; padding: 20px;
            text-align: center; margin-bottom: 20px;
        }
        .sinpe-number-box .phone { font-size: 2.2rem; font-weight: 900; color: #00b09b; letter-spacing: 3px; }
        .sinpe-number-box .label { color: #555; font-size: 0.88rem; margin-bottom: 8px; }
        .btn-whatsapp {
            display: inline-flex; align-items: center; gap: 8px;
            background: #25D366; color: white; padding: 10px 22px;
            border-radius: 10px; font-weight: 700; text-decoration: none;
            font-size: 0.9rem; margin-top: 10px; transition: all 0.2s;
        }
        .btn-whatsapp:hover { background: #128C7E; }

        .evidence-form h4 { font-size: 1rem; color: #333; margin-bottom: 16px; }
        .form-row { margin-bottom: 14px; }
        .form-row label { display: block; font-weight: 600; color: #444; margin-bottom: 6px; font-size: 0.9rem; }
        .form-row input[type=text],
        .form-row input[type=email],
        .form-row input[type=tel] {
            width: 100%; padding: 10px 14px; border: 2px solid #e0e0e0;
            border-radius: 10px; font-size: 0.95rem; box-sizing: border-box;
        }
        .form-row input:focus { border-color: #667eea; outline: none; }

        .upload-area {
            border: 2px dashed #c0c0c0; border-radius: 14px; padding: 28px;
            text-align: center; cursor: pointer; transition: all 0.2s; background: #fafafa;
        }
        .upload-area:hover, .upload-area.drag { border-color: #667eea; background: #f0f4ff; }
        .upload-area i { font-size: 2.5rem; color: #aaa; margin-bottom: 10px; }
        .upload-area .hint { color: #888; font-size: 0.85rem; margin-top: 6px; }
        .upload-area input[type=file] { display: none; }
        .file-preview { margin-top: 10px; font-size: 0.9rem; color: #333; display: none; }

        .btn-send {
            width: 100%; padding: 15px; margin-top: 18px;
            background: linear-gradient(135deg, #00b09b, #00d2a0);
            color: white; border: none; border-radius: 12px;
            font-size: 1.05rem; font-weight: 700; cursor: pointer; transition: all 0.3s;
        }
        .btn-send:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,176,155,0.4); }

        /* PayPal */
        .paypal-panel { text-align: center; }
        .paypal-desc { color: #555; margin-bottom: 20px; }
        .btn-paypal {
            display: inline-flex; align-items: center; gap: 10px;
            background: linear-gradient(135deg, #003087, #009cde);
            color: white; padding: 14px 36px; border-radius: 12px;
            font-size: 1.05rem; font-weight: 700; text-decoration: none; transition: all 0.3s;
        }
        .btn-paypal:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,48,135,0.3); }

        /* Mensajes */
        .alert-success {
            background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46;
            padding: 16px 20px; border-radius: 12px; margin-bottom: 24px;
            font-weight: 600;
        }
        .alert-error {
            background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b;
            padding: 14px 18px; border-radius: 10px; margin-bottom: 16px; font-size: 0.9rem;
        }
        .all-done {
            text-align: center; background: #d1fae5; border-radius: 16px;
            padding: 48px 24px; margin-top: 20px;
        }
        .all-done i { font-size: 4rem; color: #00b09b; margin-bottom: 16px; display: block; }
        .all-done h2 { color: #065f46; margin-bottom: 10px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="page-wrap">
    <p><a href="emprendedoras-carrito.php" class="back-link"><i class="fas fa-arrow-left"></i> Volver al carrito</a></p>
    <h1><i class="fas fa-lock" style="color:#667eea;"></i> Finalizar Compra</h1>
    <p style="color:#666;margin-bottom:28px;">El pago se realiza directamente a cada emprendedora.</p>

    <?php foreach ($successMessages as $sid => $msg): ?>
        <div class="alert-success"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
    <?php endforeach; ?>

    <?php if (empty($groups)): ?>
    <div class="all-done">
        <i class="fas fa-check-circle"></i>
        <h2>¡Todo listo!</h2>
        <p style="color:#555;">Tus comprobantes fueron enviados. Las emprendedoras te contactarán pronto.</p>
        <a href="emprendedoras-catalogo.php" style="display:inline-block;margin-top:20px;background:#00b09b;color:white;padding:12px 28px;border-radius:10px;text-decoration:none;font-weight:700;">
            <i class="fas fa-store"></i> Seguir comprando
        </a>
    </div>

    <?php else: foreach ($groups as $sid => $group): ?>

    <div class="seller-panel" id="panel-<?= $sid ?>">
        <div class="seller-panel-header">
            <div class="seller-avatar"><?= strtoupper(substr($group['seller_name'], 0, 1)) ?></div>
            <div>
                <h2><?= htmlspecialchars($group['seller_name']) ?></h2>
                <div style="font-size:0.85rem;opacity:0.9;">Emprendedora verificada ✓</div>
            </div>
            <div class="subtotal-badge">₡<?= number_format($group['subtotal'], 0) ?></div>
        </div>

        <!-- Items -->
        <div class="items-list">
            <?php foreach ($group['items'] as $item): ?>
            <div class="item-row">
                <?php if ($item['image']): ?>
                    <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                <?php else: ?>
                    <div style="width:60px;height:60px;background:#f5f5f5;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-image" style="color:#ccc;"></i></div>
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
        <?php $hasSinpe = $group['accepts_sinpe'] && $group['sinpe_phone'];
              $hasPaypal = $group['accepts_paypal'] && $group['paypal_email'];
              $defaultTab = $hasSinpe ? 'sinpe' : ($hasPaypal ? 'paypal' : '');
        ?>

        <?php if ($hasSinpe || $hasPaypal): ?>
        <div class="pay-tabs">
            <?php if ($hasSinpe): ?>
                <div class="pay-tab <?= $defaultTab === 'sinpe' ? 'active' : '' ?>"
                     onclick="switchTab(<?= $sid ?>, 'sinpe')">
                    <i class="fas fa-mobile-alt"></i> SINPE Móvil
                </div>
            <?php endif; ?>
            <?php if ($hasPaypal): ?>
                <div class="pay-tab <?= $defaultTab === 'paypal' ? 'active' : '' ?>"
                     onclick="switchTab(<?= $sid ?>, 'paypal')">
                    <i class="fab fa-paypal"></i> PayPal
                </div>
            <?php endif; ?>
        </div>

        <!-- Panel SINPE -->
        <?php if ($hasSinpe): ?>
        <div class="pay-panel <?= $defaultTab === 'sinpe' ? 'active' : '' ?>" id="tab-<?= $sid ?>-sinpe">
            <div class="sinpe-number-box">
                <div class="label"><i class="fas fa-mobile-alt"></i> Número SINPE Móvil de <?= htmlspecialchars($group['seller_name']) ?></div>
                <div class="phone"><?= htmlspecialchars($group['sinpe_phone']) ?></div>
                <div style="color:#555;font-size:0.85rem;margin-top:6px;">Monto a transferir: <strong>₡<?= number_format($group['subtotal'], 0) ?></strong></div>
                <?php
                $waPhone = preg_replace('/\D/', '', $group['sinpe_phone']);
                $waMsg   = urlencode('Hola ' . $group['seller_name'] . ', te acabo de hacer una transferencia SINPE de ₡' . number_format($group['subtotal'], 0) . ' por mi pedido en CompraTica.');
                ?>
                <a href="https://wa.me/506<?= $waPhone ?>?text=<?= $waMsg ?>" target="_blank" rel="noopener" class="btn-whatsapp">
                    <i class="fab fa-whatsapp"></i> Avisar por WhatsApp
                </a>
            </div>

            <div class="evidence-form">
                <h4><i class="fas fa-upload" style="color:#667eea;"></i> Adjunta tu comprobante de pago</h4>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="sinpe_upload">
                    <input type="hidden" name="seller_id" value="<?= $sid ?>">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
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
                        <i class="fas fa-cloud-upload-alt"></i>
                        <div><strong>Haz clic o arrastra</strong> tu comprobante aquí</div>
                        <div class="hint">JPG, PNG, WEBP o PDF — máx. 5 MB</div>
                        <input type="file" id="file-<?= $sid ?>" name="comprobante_<?= $sid ?>"
                               accept="image/jpeg,image/png,image/webp,application/pdf"
                               onchange="showPreview(this, <?= $sid ?>)">
                        <div class="file-preview" id="preview-<?= $sid ?>"><i class="fas fa-file-check"></i> <span></span></div>
                    </div>

                    <button type="submit" class="btn-send">
                        <i class="fas fa-paper-plane"></i> Enviar Comprobante
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Panel PayPal -->
        <?php if ($hasPaypal): ?>
        <?php
            $ppAmount = number_format($group['subtotal'] / 650, 2, '.', '');
            $ppEmail  = $group['paypal_email'];
            $ppItems  = implode(', ', array_map(fn($i) => $i['name'], $group['items']));
        ?>
        <div class="pay-panel <?= $defaultTab === 'paypal' && !$hasSinpe ? 'active' : '' ?>" id="tab-<?= $sid ?>-paypal">
            <div class="paypal-panel">
                <p class="paypal-desc">
                    Serás redirigido a PayPal para pagar <strong>US$<?= $ppAmount ?></strong>
                    (≈ ₡<?= number_format($group['subtotal'], 0) ?>).
                </p>
                <a href="https://www.paypal.com/paypalme/<?= urlencode($ppEmail) ?>/<?= $ppAmount ?>USD"
                   target="_blank" rel="noopener" class="btn-paypal">
                    <i class="fab fa-paypal"></i> Pagar con PayPal
                </a>
                <p style="color:#888;font-size:0.82rem;margin-top:14px;">
                    Después de pagar, envía captura por WhatsApp o correo a la emprendedora.
                </p>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div style="padding:24px;color:#888;text-align:center;">
            <i class="fas fa-info-circle"></i> Esta emprendedora no tiene métodos de pago configurados.
            Contáctala directamente.
        </div>
        <?php endif; ?>

    </div><!-- /.seller-panel -->

    <?php endforeach; endif; ?>

    <!-- Resumen total -->
    <?php if (!empty($groups)): ?>
    <div style="background:white;border-radius:14px;box-shadow:0 4px 15px rgba(0,0,0,0.07);padding:20px 24px;display:flex;justify-content:space-between;align-items:center;">
        <div>
            <div style="color:#888;font-size:0.88rem;"><?= count($groups) ?> emprendedora<?= count($groups) > 1 ? 's' : '' ?></div>
            <div style="font-weight:800;font-size:1.2rem;color:#333;">Total: ₡<?= number_format($grandTotal, 0) ?></div>
        </div>
        <div style="color:#888;font-size:0.82rem;text-align:right;">Pago directo<br>a cada emprendedora</div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
function switchTab(sid, tab) {
    document.querySelectorAll('#panel-' + sid + ' .pay-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('#panel-' + sid + ' .pay-panel').forEach(p => p.classList.remove('active'));
    event.currentTarget.classList.add('active');
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
</script>
</body>
</html>
