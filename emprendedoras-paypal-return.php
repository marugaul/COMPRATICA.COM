<?php
/**
 * emprendedoras-paypal-return.php
 * Página de retorno después del pago PayPal.
 * El IPN activa la suscripción; aquí solo mostramos un mensaje de espera.
 */
$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) ini_set('session.save_path', $__sessPath);
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

$isLoggedIn = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
$userName   = $_SESSION['name'] ?? 'Emprendedora';
$userId     = (int)($_SESSION['uid'] ?? 0);

// Verificar si ya se activó la suscripción (el IPN puede llegar antes o después)
$active = false;
if ($userId > 0) {
    try {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT 1 FROM entrepreneur_subscriptions WHERE user_id=? AND status='active' LIMIT 1");
        $stmt->execute([$userId]);
        $active = (bool)$stmt->fetchColumn();
    } catch (Exception $e) {}
}

$cantidadProductos = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $it) $cantidadProductos += (int)($it['qty'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($active): ?>
    <meta http-equiv="refresh" content="3;url=emprendedoras-dashboard.php">
    <?php endif; ?>
    <title>Pago Recibido | CompraTica Emprendedoras</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/compratica-header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .return-box {
            max-width: 600px; margin: 60px auto; padding: 0 20px; text-align: center;
        }
        .card {
            background: white; padding: 50px 40px; border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.12);
        }
        .icon { font-size: 5rem; margin-bottom: 20px; }
        h2 { font-size: 1.8rem; color: #2c3e50; margin-bottom: 12px; }
        p { color: #555; font-size: 1.05rem; line-height: 1.6; }
        .btn {
            display: inline-block; margin-top: 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; padding: 14px 36px; border-radius: 50px;
            text-decoration: none; font-weight: 700; font-size: 1rem;
            transition: all 0.3s;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(102,126,234,0.4); }
        .spinner {
            width: 50px; height: 50px; border: 5px solid #e0e0e0;
            border-top-color: #667eea; border-radius: 50%;
            animation: spin 1s linear infinite; margin: 20px auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="return-box">
        <div class="card">
            <?php if ($active): ?>
                <div class="icon">🎉</div>
                <h2>¡Pago confirmado!</h2>
                <p>Tu suscripción está activa. Redirigiendo a tu dashboard...</p>
                <div class="spinner"></div>
                <a href="emprendedoras-dashboard.php" class="btn">Ir al Dashboard</a>
            <?php else: ?>
                <div class="icon">⏳</div>
                <h2>Pago procesándose</h2>
                <p>PayPal está procesando tu pago. Esto puede tomar unos minutos.</p>
                <p style="margin-top:15px;">Recibirás un <strong>correo de confirmación</strong> cuando tu cuenta esté activa.</p>
                <div class="spinner"></div>
                <p style="color:#999;font-size:0.9rem;margin-top:20px;">
                    Si ya recibiste el correo de PayPal confirmando tu pago, espera unos minutos y recarga esta página.
                </p>
                <a href="emprendedoras-planes.php" class="btn" style="margin-right:10px;">Ver mis planes</a>
                <a href="javascript:location.reload()" class="btn" style="background:linear-gradient(135deg,#27ae60,#2ecc71);">
                    Recargar
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
