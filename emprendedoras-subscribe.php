<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL);

$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
} else {
    ini_set('session.save_path', '/tmp');
}
session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/mailer.php';

// Verificar login
if (!isset($_SESSION['uid']) || $_SESSION['uid'] <= 0) {
    header('Location: emprendedoras-login.php');
    exit;
}

$userId    = (int)$_SESSION['uid'];
$userName  = $_SESSION['name']  ?? 'Emprendedora';
$userEmail = $_SESSION['email'] ?? '';
$planId    = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;
$step      = $_GET['step'] ?? 'select';

$pdo = db();

// Obtener plan
$stmt = $pdo->prepare("SELECT * FROM entrepreneur_plans WHERE id = ? AND is_active = 1");
$stmt->execute([$planId]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plan) {
    header('Location: emprendedoras-planes.php');
    exit;
}

$features  = json_decode($plan['features'] ?? '[]', true) ?: [];
$error     = '';
$success   = '';

// ============================================================
// PASO 1: Selección de período y método de pago
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'select_method') {
    $billingPeriod = $_POST['billing_period'] ?? 'monthly';
    $paymentMethod = $_POST['payment_method'] ?? '';
    $price         = $billingPeriod === 'annual' ? (float)$plan['price_annual'] : (float)$plan['price_monthly'];

    if (!in_array($paymentMethod, ['free', 'sinpe', 'paypal', 'stripe'], true) && $price > 0) {
        $error = 'Selecciona un método de pago.';
    } else {
        // --- Plan gratuito: activar de inmediato ---
        if ($price == 0) {
            $endDate = $billingPeriod === 'annual'
                ? date('Y-m-d H:i:s', strtotime('+1 year'))
                : date('Y-m-d H:i:s', strtotime('+1 month'));

            // Cancelar suscripciones previas
            $pdo->prepare("UPDATE entrepreneur_subscriptions SET status='cancelled', updated_at=datetime('now') WHERE user_id=? AND status='active'")
                ->execute([$userId]);

            // Crear suscripción activa
            $pdo->prepare("
                INSERT INTO entrepreneur_subscriptions (user_id,plan_id,status,payment_method,payment_date,start_date,end_date,auto_renew)
                VALUES (?,?,'active','free',datetime('now'),datetime('now'),?,0)
            ")->execute([$userId, $planId, $endDate]);

            // Emails — try/catch para no bloquear el redirect
            $logFile = __DIR__ . '/logs/emprendedoras_subscribe.log';
            if (!is_dir(dirname($logFile))) @mkdir(dirname($logFile), 0755, true);
            try {
                send_email($userEmail, '✅ Tu Plan Gratuito en CompraTica está activo',
                    _email_cliente_activado($userName, $plan['name'], $billingPeriod, 0));
            } catch (Throwable $e) {
                @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] EMAIL_FREE_CLIENTE | ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            }
            try {
                send_email(ADMIN_EMAIL, "[Emprendedoras] Nueva suscripción gratuita - {$userName}",
                    _email_admin_nueva_sub($userName, $userEmail, $plan['name'], $billingPeriod, 0, 'Gratuito'));
            } catch (Throwable $e) {
                @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] EMAIL_FREE_ADMIN | ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            }

            header('Location: emprendedoras-dashboard.php?suscrita=1');
            exit;
        }

        // --- SINPE: guardar selección en sesión y redirigir a paso SINPE ---
        if ($paymentMethod === 'sinpe') {
            $_SESSION['ent_sub_plan_id']       = $planId;
            $_SESSION['ent_sub_billing']        = $billingPeriod;
            $_SESSION['ent_sub_price']          = $price;
            header("Location: emprendedoras-subscribe.php?plan_id={$planId}&step=sinpe");
            exit;
        }

        // --- PayPal / Apple Pay / Google Pay: guardar en sesión y mostrar botones SDK ---
        if ($paymentMethod === 'paypal') {
            $_SESSION['ent_sub_plan_id']   = $planId;
            $_SESSION['ent_sub_billing']   = $billingPeriod;
            $_SESSION['ent_sub_price']     = $price;
            header("Location: emprendedoras-subscribe.php?plan_id={$planId}&step=wallet");
            exit;
        }

        // --- Stripe: guardar en sesión y mostrar formulario Stripe ---
        if ($paymentMethod === 'stripe') {
            $_SESSION['ent_sub_plan_id']   = $planId;
            $_SESSION['ent_sub_billing']   = $billingPeriod;
            $_SESSION['ent_sub_price']     = $price;
            header("Location: emprendedoras-subscribe.php?plan_id={$planId}&step=stripe");
            exit;
        }
    }
}

// ============================================================
// PASO 2 – SINPE: subir comprobante
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_sinpe') {
    $billingPeriod = $_SESSION['ent_sub_billing'] ?? 'monthly';
    $price         = (float)($_SESSION['ent_sub_price'] ?? 0);

    do { // bloque único para poder usar break en validaciones
        // Validar archivo
        if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Debes subir el comprobante de pago.';
            break;
        }

        $file    = $_FILES['comprobante'];
        $mime    = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = (string)finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        } else {
            // fallback por extensión
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $mime = match($ext) { 'pdf' => 'application/pdf', 'png' => 'image/png', 'webp' => 'image/webp', default => 'image/jpeg' };
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!in_array($mime, $allowed)) {
            $error = 'Tipo de archivo no permitido. Solo JPG, PNG, WEBP o PDF.';
            break;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            $error = 'El archivo no puede superar 5 MB.';
            break;
        }

        // Guardar archivo
        $uploadDir = __DIR__ . '/uploads/subscription-receipts';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
        $extMap   = ['application/pdf' => 'pdf', 'image/png' => 'png', 'image/webp' => 'webp'];
        $ext      = $extMap[$mime] ?? 'jpg';
        $filename = sprintf('sinpe_%d_%d_%s.%s', $userId, $planId, bin2hex(random_bytes(6)), $ext);
        $filepath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $error = 'Error al guardar el archivo. Intenta de nuevo.';
            break;
        }

        $receiptUrl = '/uploads/subscription-receipts/' . $filename;

        try {
            // Cancelar suscripciones previas
            $pdo->prepare("UPDATE entrepreneur_subscriptions SET status='cancelled', updated_at=datetime('now') WHERE user_id=? AND status IN ('active','pending')")
                ->execute([$userId]);

            // Crear suscripción pendiente
            $pdo->prepare("
                INSERT INTO entrepreneur_subscriptions (user_id,plan_id,status,payment_method,start_date,auto_renew)
                VALUES (?,?,'pending','sinpe',datetime('now'),0)
            ")->execute([$userId, $planId]);

            // Guardar comprobante
            $pdo->exec("CREATE TABLE IF NOT EXISTS payment_receipts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                listing_type TEXT, listing_id INTEGER,
                user_id INTEGER NOT NULL, receipt_url TEXT NOT NULL,
                status TEXT DEFAULT 'pending',
                uploaded_at TEXT DEFAULT (datetime('now')),
                reviewed_at TEXT, reviewed_by INTEGER, notes TEXT
            )");
            $pdo->prepare("INSERT INTO payment_receipts (listing_type,listing_id,user_id,receipt_url,status) VALUES ('subscription',?,?,?,'pending')")
                ->execute([$planId, $userId, $receiptUrl]);
        } catch (Exception $e) {
            $error = 'Error al registrar la suscripción. Por favor contacta soporte.';
            @file_put_contents(__DIR__ . '/logs/emprendedoras_subscribe.log',
                '[' . date('Y-m-d H:i:s') . '] DB_ERROR | ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            break;
        }

        // Limpiar sesión
        unset($_SESSION['ent_sub_plan_id'], $_SESSION['ent_sub_billing'], $_SESSION['ent_sub_price']);

        // Emails — siempre en try/catch individual para no bloquear el flujo
        $adminReceiptLink = rtrim(SITE_URL, '/') . $receiptUrl;
        $logFile = __DIR__ . '/logs/emprendedoras_subscribe.log';
        if (!is_dir(dirname($logFile))) @mkdir(dirname($logFile), 0755, true);

        try {
            send_email(
                $userEmail,
                '📋 Comprobante recibido - CompraTica Emprendedoras',
                _email_cliente_sinpe($userName, $plan['name'], $billingPeriod, $price)
            );
        } catch (Throwable $e) {
            @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] EMAIL_CLIENTE_ERR | ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }

        try {
            send_email(
                ADMIN_EMAIL,
                "[Emprendedoras] Nuevo comprobante SINPE - {$userName}",
                _email_admin_sinpe($userName, $userEmail, $plan['name'], $billingPeriod, $price, $adminReceiptLink)
            );
        } catch (Throwable $e) {
            @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] EMAIL_ADMIN_ERR | ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }

        // PRG: redirigir para evitar re-submit y página en blanco
        $_SESSION['ent_sub_success'] = '¡Comprobante enviado! Activaremos tu cuenta en un máximo de 24 horas hábiles.';
        header("Location: emprendedoras-subscribe.php?plan_id={$planId}&step=done");
        exit;

    } while (false);

    if ($error) $step = 'sinpe';
}

// Leer mensaje de éxito guardado en sesión (después del redirect)
if ($step === 'done' && isset($_SESSION['ent_sub_success'])) {
    $success = $_SESSION['ent_sub_success'];
    unset($_SESSION['ent_sub_success']);
}

// ============================================================
// FUNCIONES DE EMAIL
// ============================================================
function _email_cliente_activado(string $nombre, string $plan, string $periodo, float $precio): string {
    $periodoLabel = $periodo === 'annual' ? 'Anual' : 'Mensual';
    $precioLabel  = $precio == 0 ? 'Gratuito' : '₡' . number_format($precio, 0);
    return "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
        <div style='background:linear-gradient(135deg,#667eea,#764ba2);padding:40px;text-align:center;border-radius:16px 16px 0 0;'>
            <h1 style='color:#fff;margin:0;font-size:1.8rem;'>🌸 CompraTica Emprendedoras</h1>
        </div>
        <div style='background:#fff;padding:40px;border:1px solid #e0e0e0;'>
            <h2 style='color:#27ae60;'>✅ ¡Tu plan está activo!</h2>
            <p>Hola <strong>" . htmlspecialchars($nombre) . "</strong>,</p>
            <p>Tu suscripción al <strong>" . htmlspecialchars($plan) . "</strong> ha sido activada exitosamente.</p>
            <div style='background:#f8f9ff;padding:20px;border-radius:12px;margin:20px 0;border-left:4px solid #667eea;'>
                <p style='margin:5px 0;'><strong>Plan:</strong> " . htmlspecialchars($plan) . "</p>
                <p style='margin:5px 0;'><strong>Período:</strong> $periodoLabel</p>
                <p style='margin:5px 0;'><strong>Monto:</strong> $precioLabel</p>
            </div>
            <p>Ya puedes acceder a tu dashboard y comenzar a publicar tus productos.</p>
            <div style='text-align:center;margin:30px 0;'>
                <a href='" . SITE_URL . "/emprendedoras-dashboard' style='background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:14px 30px;border-radius:50px;text-decoration:none;font-weight:bold;font-size:1rem;'>Ir a mi Dashboard</a>
            </div>
        </div>
        <div style='background:#f9fafb;padding:20px;text-align:center;border-radius:0 0 16px 16px;color:#666;font-size:0.85rem;'>
            CompraTica — El marketplace costarricense
        </div>
    </div>";
}

function _email_cliente_sinpe(string $nombre, string $plan, string $periodo, float $precio): string {
    $periodoLabel = $periodo === 'annual' ? 'Anual' : 'Mensual';
    return "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
        <div style='background:linear-gradient(135deg,#667eea,#764ba2);padding:40px;text-align:center;border-radius:16px 16px 0 0;'>
            <h1 style='color:#fff;margin:0;font-size:1.8rem;'>🌸 CompraTica Emprendedoras</h1>
        </div>
        <div style='background:#fff;padding:40px;border:1px solid #e0e0e0;'>
            <h2 style='color:#2c3e50;'>📋 Comprobante recibido</h2>
            <p>Hola <strong>" . htmlspecialchars($nombre) . "</strong>,</p>
            <p>Recibimos tu comprobante de pago SINPE Móvil para el plan <strong>" . htmlspecialchars($plan) . "</strong>.</p>
            <div style='background:#fff3cd;padding:20px;border-radius:12px;margin:20px 0;border-left:4px solid #ffc107;'>
                <p style='margin:5px 0;'><strong>Plan:</strong> " . htmlspecialchars($plan) . "</p>
                <p style='margin:5px 0;'><strong>Período:</strong> $periodoLabel</p>
                <p style='margin:5px 0;'><strong>Monto:</strong> ₡" . number_format($precio, 0) . "</p>
                <p style='margin:5px 0;'><strong>Estado:</strong> <span style='color:#856404;'>⏳ Pendiente de verificación</span></p>
            </div>
            <p>Nuestro equipo verificará tu pago en un máximo de <strong>24 horas hábiles</strong> y recibirás un correo de confirmación cuando tu cuenta esté activa.</p>
            <p>Si tienes alguna consulta, responde este correo o escríbenos por WhatsApp.</p>
        </div>
        <div style='background:#f9fafb;padding:20px;text-align:center;border-radius:0 0 16px 16px;color:#666;font-size:0.85rem;'>
            CompraTica — El marketplace costarricense
        </div>
    </div>";
}

function _email_admin_nueva_sub(string $nombre, string $email, string $plan, string $periodo, float $precio, string $metodo): string {
    return "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;'>
        <h2 style='color:#27ae60;'>✅ Nueva Suscripción Emprendedora</h2>
        <table style='width:100%;border-collapse:collapse;'>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Emprendedora:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($nombre) . "</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Email:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($email) . "</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Plan:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($plan) . "</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Período:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . ($periodo === 'annual' ? 'Anual' : 'Mensual') . "</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Monto:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>₡" . number_format($precio, 0) . "</td></tr>
            <tr><td style='padding:8px;'><strong>Método:</strong></td><td style='padding:8px;'>" . htmlspecialchars($metodo) . "</td></tr>
        </table>
    </div>";
}

function _email_admin_sinpe(string $nombre, string $email, string $plan, string $periodo, float $precio, string $receiptLink): string {
    return "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;'>
        <h2 style='color:#e67e22;'>🧾 Nuevo Comprobante SINPE - Verificación Requerida</h2>
        <table style='width:100%;border-collapse:collapse;'>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Emprendedora:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($nombre) . "</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Email:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($email) . "</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Plan:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($plan) . "</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Período:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>" . ($periodo === 'annual' ? 'Anual' : 'Mensual') . "</td></tr>
            <tr><td style='padding:8px;border-bottom:1px solid #eee;'><strong>Monto:</strong></td><td style='padding:8px;border-bottom:1px solid #eee;'>₡" . number_format($precio, 0) . "</td></tr>
        </table>
        <div style='margin-top:20px;padding:15px;background:#fff3cd;border-radius:8px;'>
            <p style='margin:0;'><strong>🔗 Ver comprobante:</strong><br>
            <a href='" . htmlspecialchars($receiptLink) . "' style='color:#667eea;'>" . htmlspecialchars($receiptLink) . "</a></p>
        </div>
        <p style='margin-top:20px;color:#666;'>Verifica el pago en SINPE y activa la suscripción desde el panel de admin.</p>
    </div>";
}

// ============================================================
// PASO STRIPE: crear PaymentIntent server-side
// ============================================================
$stripeClientSecret = '';
$stripeError        = '';
if ($step === 'stripe') {
    $stripeSecretKey = defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : '';
    $stripeSubPlanId = (int)($_SESSION['ent_sub_plan_id'] ?? $planId);
    $stripeSubPeriod = $_SESSION['ent_sub_billing'] ?? 'monthly';
    $stripeSubPrice  = (float)($_SESSION['ent_sub_price'] ?? ($stripeSubPeriod === 'annual' ? $plan['price_annual'] : $plan['price_monthly']));
    $stripeAmountCents = (int)round(($stripeSubPrice / 650) * 100); // CRC → USD → centavos

    if (!$stripeSecretKey) {
        $stripeError = 'Stripe no está configurado en el servidor.';
    } else {
        $ch = curl_init('https://api.stripe.com/v1/payment_intents');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'amount'                        => $stripeAmountCents,
                'currency'                      => 'usd',
                'description'                   => $plan['name'] . ' - ' . ($stripeSubPeriod === 'annual' ? 'Anual' : 'Mensual'),
                'metadata[user_id]'             => $userId,
                'metadata[plan_id]'             => $stripeSubPlanId,
                'metadata[billing_period]'      => $stripeSubPeriod,
                'automatic_payment_methods[enabled]' => 'true',
            ]),
            CURLOPT_USERPWD        => $stripeSecretKey . ':',
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $piJson  = curl_exec($ch);
        $piErr   = curl_error($ch);
        curl_close($ch);

        if ($piErr) {
            $stripeError = 'Error conectando con Stripe. Por favor intenta de nuevo.';
        } else {
            $piData = json_decode((string)$piJson, true);
            $stripeClientSecret = $piData['client_secret'] ?? '';
            if (!$stripeClientSecret) {
                $stripeError = $piData['error']['message'] ?? 'Error al iniciar el pago con Stripe.';
            }
        }
    }
}

// Variables para la vista
$sinpePhone   = defined('SINPE_PHONE') ? SINPE_PHONE : '';
$cantidadProductos = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $it) $cantidadProductos += (int)($it['qty'] ?? 0);
}
$isLoggedIn = true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suscribirse a <?= htmlspecialchars($plan['name']) ?> | CompraTica</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/compratica-header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sub-container { max-width: 760px; margin: 40px auto; padding: 0 20px; }
        .plan-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; padding: 40px; border-radius: 20px; margin-bottom: 30px; text-align: center;
        }
        .plan-hero h1 { font-size: 2rem; margin-bottom: 8px; }
        .plan-hero .price { font-size: 2.5rem; font-weight: 800; margin: 15px 0; }
        .plan-hero ul { list-style: none; padding: 0; margin: 15px 0 0; text-align: left; display: inline-block; }
        .plan-hero ul li { padding: 6px 0; }
        .card {
            background: white; padding: 40px; border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1); margin-bottom: 24px;
        }
        .card h2 { font-size: 1.4rem; color: #333; margin-bottom: 24px; }
        .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-info    { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; color: #374151; margin-bottom: 8px; }
        .form-group select, .form-group input[type="file"] {
            width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0;
            border-radius: 12px; font-size: 1rem; background: white;
        }
        .form-group select:focus { outline: none; border-color: #667eea; }
        .payment-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-top: 8px; }
        .payment-option {
            border: 2px solid #e0e0e0; padding: 20px; border-radius: 14px;
            cursor: pointer; transition: all 0.3s; text-align: center; position: relative;
        }
        .payment-option:hover { border-color: #667eea; background: #f8f9ff; }
        .payment-option input[type="radio"] { position: absolute; top: 12px; right: 12px; }
        .payment-option .icon { font-size: 2.2rem; color: #667eea; margin-bottom: 10px; }
        .payment-option.selected { border-color: #667eea; background: #f0f2ff; }
        .btn-submit {
            width: 100%; padding: 16px; background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; border: none; border-radius: 50px; font-size: 1.05rem;
            font-weight: 700; cursor: pointer; transition: all 0.3s; margin-top: 10px;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(102,126,234,0.4); }
        .sinpe-box {
            background: #f0f9ff; border: 2px solid #38bdf8; border-radius: 16px;
            padding: 30px; text-align: center; margin-bottom: 20px;
        }
        .sinpe-box .phone { font-size: 2.5rem; font-weight: 800; color: #0369a1; letter-spacing: 2px; margin: 10px 0; }
        .sinpe-box img { width: 160px; border-radius: 8px; margin-top: 15px; }
        .upload-area {
            border: 2px dashed #667eea; border-radius: 14px; padding: 30px;
            text-align: center; cursor: pointer; transition: all 0.3s; background: #fafbff;
        }
        .upload-area:hover { background: #f0f2ff; }
        .upload-area input { display: none; }
        .upload-area .icon { font-size: 2.5rem; color: #667eea; margin-bottom: 10px; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #667eea; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .done-box { text-align: center; padding: 40px 20px; }
        .done-box .icon { font-size: 5rem; margin-bottom: 20px; }
        .done-box h2 { font-size: 1.8rem; color: #065f46; margin-bottom: 10px; }
        /* Stripe Elements */
        #stripe-payment-element { margin-bottom: 20px; }
        #stripe-submit { width: 100%; padding: 16px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; border-radius: 50px; font-size: 1.05rem; font-weight: 700; cursor: pointer; transition: all 0.3s; margin-top: 10px; }
        #stripe-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(102,126,234,0.4); }
        #stripe-submit:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="sub-container">

        <!-- Hero del plan -->
        <div class="plan-hero">
            <h1><?= htmlspecialchars($plan['name']) ?></h1>
            <p><?= htmlspecialchars($plan['description']) ?></p>
            <?php if (!empty($features)): ?>
            <ul>
                <?php foreach ($features as $f): ?>
                    <li><i class="fas fa-check-circle"></i> <?= htmlspecialchars($f) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($step === 'done'): ?>
        <!-- ======== CONFIRMACIÓN FINAL ======== -->
        <div class="card">
            <div class="done-box">
                <div class="icon">✅</div>
                <h2>¡Comprobante enviado!</h2>
                <p style="color:#374151;font-size:1.1rem;">Hemos recibido tu comprobante de pago SINPE.<br>
                   Revisaremos y activaremos tu cuenta en un máximo de <strong>24 horas hábiles</strong>.</p>
                <p style="color:#666;margin-top:10px;">Recibirás un correo de confirmación a <strong><?= htmlspecialchars($userEmail) ?></strong>.</p>
                <a href="emprendedoras-planes.php" class="btn-submit" style="display:inline-block;width:auto;padding:14px 40px;margin-top:30px;text-decoration:none;">
                    Ver mis planes
                </a>
            </div>
        </div>

        <?php elseif ($step === 'wallet'): ?>
        <!-- ======== PASO WALLET: PayPal JS SDK (PayPal + Apple Pay + Google Pay) ======== -->
        <?php
        $subPlanId = (int)($_SESSION['ent_sub_plan_id'] ?? $planId);
        $subPeriod = $_SESSION['ent_sub_billing'] ?? 'monthly';
        $subPrice  = (float)($_SESSION['ent_sub_price'] ?? ($subPeriod === 'annual' ? $plan['price_annual'] : $plan['price_monthly']));
        $priceUSD  = number_format(round($subPrice / 650, 2), 2, '.', '');
        $itemDesc  = addslashes($plan['name'] . ' - ' . ($subPeriod === 'annual' ? 'Anual' : 'Mensual'));
        $paypalClientId = defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : '';
        $paypalMode     = defined('PAYPAL_MODE') ? PAYPAL_MODE : 'sandbox';
        $sdkUrl = 'https://www.paypal.com/sdk/js?client-id=' . urlencode($paypalClientId)
                . '&currency=USD&enable-funding=applepay,googlepay,venmo&components=buttons';
        if ($paypalMode !== 'live') {
            $sdkUrl .= '&buyer-country=US';
        }
        ?>
        <div class="card">
            <h2><i class="fas fa-wallet" style="color:#667eea;"></i> Selecciona tu método de pago</h2>

            <div class="alert alert-info">
                <strong>Plan:</strong> <?= htmlspecialchars($plan['name']) ?> &mdash;
                <strong>Período:</strong> <?= $subPeriod === 'annual' ? 'Anual' : 'Mensual' ?> &mdash;
                <strong>Total:</strong> ₡<?= number_format($subPrice, 0) ?> (US$<?= $priceUSD ?>)
            </div>

            <p style="color:#555;margin-bottom:20px;font-size:0.95rem;">
                Los botones disponibles dependen de tu dispositivo y navegador.
                En <strong>iPhone/Mac con Safari</strong> verás <strong>Apple Pay</strong>.
                En <strong>Android/Chrome</strong> verás <strong>Google Pay</strong>.
            </p>

            <div id="paypal-button-container" style="min-height:50px;"></div>

            <div id="wallet-error" class="alert alert-error" style="display:none;margin-top:15px;">
                <i class="fas fa-exclamation-circle"></i>
                <span id="wallet-error-msg">Error al procesar el pago. Por favor intenta de nuevo.</span>
            </div>

            <div id="wallet-processing" style="display:none;text-align:center;padding:20px;">
                <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#667eea;"></i>
                <p style="color:#667eea;font-weight:600;margin-top:10px;">Activando tu suscripción...</p>
            </div>

            <a href="emprendedoras-subscribe.php?plan_id=<?= $planId ?>" class="back-link" style="margin-top:24px;">
                <i class="fas fa-arrow-left"></i> Volver a opciones de pago
            </a>
        </div>

        <script src="<?= htmlspecialchars($sdkUrl, ENT_QUOTES) ?>"></script>
        <script>
        (function() {
            var planId       = <?= $subPlanId ?>;
            var billingPeriod = '<?= htmlspecialchars($subPeriod, ENT_QUOTES) ?>';
            var priceUsd     = '<?= $priceUSD ?>';
            var itemDesc     = '<?= $itemDesc ?>';

            paypal.Buttons({
                style: {
                    layout: 'vertical',
                    color:  'gold',
                    shape:  'pill',
                    label:  'pay'
                },
                createOrder: function(data, actions) {
                    return actions.order.create({
                        purchase_units: [{
                            amount: { value: priceUsd },
                            description: itemDesc,
                            custom_id: JSON.stringify({
                                type: 'entrepreneur_subscription',
                                plan_id: planId,
                                billing_period: billingPeriod
                            })
                        }]
                    });
                },
                onApprove: function(data, actions) {
                    return actions.order.capture().then(function(details) {
                        document.getElementById('paypal-button-container').style.display = 'none';
                        document.getElementById('wallet-processing').style.display = 'block';

                        return fetch('/api/activate-subscription-paypal.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                paypal_order_id: data.orderID,
                                plan_id: planId,
                                billing_period: billingPeriod,
                                amount_usd: priceUsd
                            })
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(result) {
                            if (result.ok) {
                                window.location.href = 'emprendedoras-dashboard.php?suscrita=1';
                            } else {
                                document.getElementById('wallet-processing').style.display = 'none';
                                document.getElementById('paypal-button-container').style.display = 'block';
                                var errEl = document.getElementById('wallet-error');
                                document.getElementById('wallet-error-msg').textContent = result.error || 'Error al activar la suscripción.';
                                errEl.style.display = 'block';
                            }
                        });
                    });
                },
                onError: function(err) {
                    console.error('PayPal error:', err);
                    document.getElementById('wallet-processing').style.display = 'none';
                    document.getElementById('wallet-error').style.display = 'block';
                    document.getElementById('wallet-error-msg').textContent = 'Ocurrió un error con el pago. Por favor intenta de nuevo.';
                },
                onCancel: function() {
                    // user cancelled, just stay on page
                }
            }).render('#paypal-button-container');
        })();
        </script>

        <?php elseif ($step === 'stripe'): ?>
        <!-- ======== PASO STRIPE: formulario tarjeta vía Stripe.js ======== -->
        <?php
        $stripePublicKey = defined('STRIPE_PUBLIC_KEY') ? STRIPE_PUBLIC_KEY : '';
        $stripeSubPeriodLabel = isset($stripeSubPeriod) ? ($stripeSubPeriod === 'annual' ? 'Anual' : 'Mensual') : 'Mensual';
        $stripeSubPriceLabel  = isset($stripeSubPrice)  ? number_format($stripeSubPrice, 0) : '0';
        $stripeSubUsdLabel    = isset($stripeAmountCents) ? number_format($stripeAmountCents / 100, 2) : '0.00';
        ?>
        <div class="card">
            <h2><i class="fas fa-credit-card" style="color:#635bff;"></i> Pago con Tarjeta</h2>

            <div class="alert alert-info">
                <strong>Plan:</strong> <?= htmlspecialchars($plan['name']) ?> &mdash;
                <strong>Período:</strong> <?= $stripeSubPeriodLabel ?> &mdash;
                <strong>Total:</strong> ₡<?= $stripeSubPriceLabel ?> (US$<?= $stripeSubUsdLabel ?>)
            </div>

            <?php if ($stripeError): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($stripeError) ?>
                </div>
                <a href="emprendedoras-subscribe.php?plan_id=<?= $planId ?>" class="back-link">
                    <i class="fas fa-arrow-left"></i> Volver a opciones de pago
                </a>
            <?php else: ?>
                <form id="stripe-form">
                    <div id="stripe-payment-element"></div>

                    <div id="stripe-error-msg" class="alert alert-error" style="display:none;margin-bottom:15px;">
                        <i class="fas fa-exclamation-circle"></i> <span id="stripe-error-text"></span>
                    </div>

                    <div id="stripe-processing" style="display:none;text-align:center;padding:20px;">
                        <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#635bff;"></i>
                        <p style="color:#635bff;font-weight:600;margin-top:10px;">Activando tu suscripción...</p>
                    </div>

                    <button id="stripe-submit" type="submit">
                        <i class="fas fa-lock"></i> Pagar US$<?= $stripeSubUsdLabel ?>
                    </button>
                </form>

                <p style="text-align:center;color:#999;font-size:0.82rem;margin-top:12px;">
                    <i class="fas fa-shield-alt"></i> Pago seguro procesado por <strong>Stripe</strong>. Nunca almacenamos datos de tu tarjeta.
                </p>

                <a href="emprendedoras-subscribe.php?plan_id=<?= $planId ?>" class="back-link">
                    <i class="fas fa-arrow-left"></i> Volver a opciones de pago
                </a>

                <script src="https://js.stripe.com/v3/"></script>
                <script>
                (function() {
                    var stripe  = Stripe(<?= json_encode($stripePublicKey) ?>);
                    var planId  = <?= (int)($stripeSubPlanId ?? $planId) ?>;
                    var billing = <?= json_encode($stripeSubPeriod ?? 'monthly') ?>;
                    var amtUsd  = <?= json_encode($stripeSubUsdLabel) ?>;

                    var elements = stripe.elements({
                        clientSecret: <?= json_encode($stripeClientSecret) ?>,
                        appearance: { theme: 'stripe', variables: { colorPrimary: '#667eea' } }
                    });

                    var paymentElement = elements.create('payment');
                    paymentElement.mount('#stripe-payment-element');

                    document.getElementById('stripe-form').addEventListener('submit', async function(e) {
                        e.preventDefault();
                        var submitBtn     = document.getElementById('stripe-submit');
                        var processingDiv = document.getElementById('stripe-processing');
                        var errorDiv      = document.getElementById('stripe-error-msg');
                        var errorText     = document.getElementById('stripe-error-text');

                        submitBtn.disabled = true;
                        errorDiv.style.display = 'none';

                        var result = await stripe.confirmPayment({
                            elements: elements,
                            redirect: 'if_required'
                        });

                        if (result.error) {
                            errorText.textContent = result.error.message || 'Error al procesar el pago.';
                            errorDiv.style.display = 'block';
                            submitBtn.disabled = false;
                            return;
                        }

                        if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                            submitBtn.style.display = 'none';
                            processingDiv.style.display = 'block';

                            fetch('/api/activate-subscription-stripe.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    payment_intent_id: result.paymentIntent.id,
                                    plan_id:           planId,
                                    billing_period:    billing,
                                    amount_usd:        parseFloat(amtUsd)
                                })
                            })
                            .then(r => r.json())
                            .then(function(res) {
                                if (res.ok) {
                                    window.location.href = 'emprendedoras-dashboard.php?suscrita=1';
                                } else {
                                    processingDiv.style.display = 'none';
                                    submitBtn.style.display = 'block';
                                    submitBtn.disabled = false;
                                    errorText.textContent = res.error || 'Error al activar la suscripción.';
                                    errorDiv.style.display = 'block';
                                }
                            })
                            .catch(function() {
                                processingDiv.style.display = 'none';
                                submitBtn.style.display = 'block';
                                submitBtn.disabled = false;
                                errorText.textContent = 'Error de conexión. Contacta soporte con tu comprobante de pago.';
                                errorDiv.style.display = 'block';
                            });
                        }
                    });
                })();
                </script>
            <?php endif; ?>
        </div>

        <?php elseif ($step === 'sinpe'): ?>
        <!-- ======== PASO SINPE: instrucciones + upload ======== -->
        <div class="card">
            <h2><i class="fas fa-mobile-alt" style="color:#667eea;"></i> Pago por SINPE Móvil</h2>

            <?php
            $subPeriod = $_SESSION['ent_sub_billing'] ?? 'monthly';
            $subPrice  = (float)($_SESSION['ent_sub_price'] ?? ($subPeriod === 'annual' ? $plan['price_annual'] : $plan['price_monthly']));
            ?>

            <div class="alert alert-info">
                <strong>Plan:</strong> <?= htmlspecialchars($plan['name']) ?> —
                <strong>Período:</strong> <?= $subPeriod === 'annual' ? 'Anual' : 'Mensual' ?> —
                <strong>Total:</strong> ₡<?= number_format($subPrice, 0) ?>
            </div>

            <div class="sinpe-box">
                <p style="font-size:1.1rem;color:#0369a1;font-weight:600;margin:0;">
                    <i class="fas fa-mobile-alt"></i> Transfiere a este número SINPE Móvil:
                </p>
                <div class="phone"><?= htmlspecialchars($sinpePhone) ?></div>
                <p style="color:#555;margin:0;">A nombre de <strong>CompraTica</strong></p>
                <p style="color:#0369a1;font-size:1.1rem;margin-top:10px;">
                    <strong>Monto exacto: ₡<?= number_format($subPrice, 0) ?></strong>
                </p>
                <?php if (file_exists(__DIR__ . '/assets/sinpe.jpg')): ?>
                    <img src="assets/sinpe.jpg" alt="SINPE Móvil">
                <?php endif; ?>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_sinpe">

                <div class="form-group">
                    <label><i class="fas fa-upload"></i> Sube el comprobante de tu transferencia</label>
                    <div class="upload-area" onclick="document.getElementById('comprobante').click()">
                        <input type="file" id="comprobante" name="comprobante" accept="image/*,.pdf" required
                               onchange="updateUploadLabel(this)">
                        <div class="icon"><i class="fas fa-cloud-upload-alt"></i></div>
                        <p id="upload-label" style="color:#667eea;font-weight:600;">Haz clic para seleccionar imagen o PDF</p>
                        <p style="color:#999;font-size:0.85rem;margin:0;">JPG, PNG, WEBP o PDF — Máximo 5 MB</p>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Enviar Comprobante
                </button>
            </form>

            <a href="emprendedoras-subscribe.php?plan_id=<?= $planId ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> Volver a opciones de pago
            </a>
        </div>

        <?php else: ?>
        <!-- ======== PASO 1: elegir período y método de pago ======== -->
        <div class="card">
            <h2><i class="fas fa-credit-card" style="color:#667eea;"></i> Completa tu suscripción</h2>

            <form method="POST" id="subscribeForm">
                <input type="hidden" name="action" value="select_method">

                <div class="form-group">
                    <label>Período de facturación</label>
                    <select name="billing_period" id="billingPeriod" onchange="updatePrice()">
                        <option value="monthly">Mensual — ₡<?= number_format((float)$plan['price_monthly'], 0) ?></option>
                        <?php if ($plan['price_annual'] > 0): ?>
                        <option value="annual">Anual — ₡<?= number_format((float)$plan['price_annual'], 0) ?> (ahorra 2 meses)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <?php if ($plan['price_monthly'] > 0): ?>
                <div class="form-group">
                    <label>Método de pago</label>
                    <div class="payment-options">
                        <label class="payment-option" onclick="selectPayment(this, 'sinpe')">
                            <input type="radio" name="payment_method" value="sinpe" required>
                            <div class="icon"><i class="fas fa-mobile-alt"></i></div>
                            <div><strong>SINPE Móvil</strong></div>
                            <div style="font-size:0.85rem;color:#666;margin-top:4px;">Transferencia local</div>
                        </label>
                        <label class="payment-option" onclick="selectPayment(this, 'paypal')">
                            <input type="radio" name="payment_method" value="paypal" required>
                            <div class="icon">
                                <i class="fab fa-paypal" style="color:#003087;"></i>
                                <i class="fab fa-apple" style="color:#555;font-size:1.6rem;vertical-align:middle;margin-left:4px;"></i>
                                <i class="fab fa-google" style="color:#4285F4;font-size:1.4rem;vertical-align:middle;margin-left:4px;"></i>
                            </div>
                            <div><strong>PayPal / Apple Pay / Google Pay</strong></div>
                            <div style="font-size:0.85rem;color:#666;margin-top:4px;">Cuenta PayPal o wallet</div>
                        </label>
                        <label class="payment-option" onclick="selectPayment(this, 'stripe')">
                            <input type="radio" name="payment_method" value="stripe" required>
                            <div class="icon"><i class="fas fa-credit-card" style="color:#635bff;"></i></div>
                            <div><strong>Tarjeta de crédito / débito</strong></div>
                            <div style="font-size:0.85rem;color:#666;margin-top:4px;">Visa, Mastercard, Amex</div>
                        </label>
                    </div>
                </div>
                <?php else: ?>
                    <input type="hidden" name="payment_method" value="free">
                <?php endif; ?>

                <button type="submit" class="btn-submit">
                    <?php if ($plan['price_monthly'] == 0): ?>
                        <i class="fas fa-check"></i> Activar Plan Gratuito
                    <?php else: ?>
                        <i class="fas fa-arrow-right"></i> Continuar con el pago
                    <?php endif; ?>
                </button>
            </form>

            <a href="emprendedoras-planes.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Ver todos los planes
            </a>
        </div>
        <?php endif; ?>

    </div>

    <script>
    function selectPayment(el, method) {
        document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
        el.classList.add('selected');
        el.querySelector('input[type="radio"]').checked = true;
    }

    function updateUploadLabel(input) {
        const label = document.getElementById('upload-label');
        if (input.files && input.files[0]) {
            label.textContent = '📎 ' + input.files[0].name;
        }
    }
    </script>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
