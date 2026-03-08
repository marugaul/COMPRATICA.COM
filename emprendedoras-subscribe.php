<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);

$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['uid']) || $_SESSION['uid'] <= 0) {
    header('Location: emprendedoras-login.php');
    exit;
}

$userId = $_SESSION['uid'];
$userName = $_SESSION['name'] ?? 'Usuario';
$userEmail = $_SESSION['email'] ?? '';

// Obtener plan_id
$planId = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;

$pdo = db();

// Obtener información del plan
$stmt = $pdo->prepare("SELECT * FROM entrepreneur_plans WHERE id = ? AND is_active = 1");
$stmt->execute([$planId]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plan) {
    header('Location: emprendedoras-planes.php');
    exit;
}

// Obtener métodos de pago del usuario (si ya los tiene configurados)
$stmt = $pdo->prepare("SELECT * FROM affiliate_payment_methods WHERE affiliate_id = ?");
$stmt->execute([$userId]);
$userPaymentMethods = $stmt->fetch(PDO::FETCH_ASSOC);

// Procesar formulario
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $billingPeriod = $_POST['billing_period'] ?? 'monthly';
    $paymentMethod = $_POST['payment_method'] ?? '';
    $paymentProof = $_POST['payment_proof'] ?? '';

    if (empty($paymentMethod)) {
        $error = 'Por favor selecciona un método de pago';
    } else {
        // Calcular precio según el período
        $price = $billingPeriod === 'annual' ? $plan['price_annual'] : $plan['price_monthly'];

        // Para el plan gratuito, activar inmediatamente
        if ($price == 0) {
            $paymentStatus = 'confirmed';
            $startDate = date('Y-m-d H:i:s');
            $endDate = $billingPeriod === 'annual'
                ? date('Y-m-d H:i:s', strtotime('+1 year'))
                : date('Y-m-d H:i:s', strtotime('+1 month'));
        } else {
            $paymentStatus = 'pending';
            $startDate = date('Y-m-d H:i:s');
            $endDate = null;
        }

        try {
            // Desactivar suscripciones anteriores
            $stmt = $pdo->prepare("
                UPDATE entrepreneur_subscriptions
                SET status = 'cancelled', updated_at = datetime('now')
                WHERE user_id = ? AND status = 'active'
            ");
            $stmt->execute([$userId]);

            // Crear nueva suscripción
            $stmt = $pdo->prepare("
                INSERT INTO entrepreneur_subscriptions
                (user_id, plan_id, status, payment_method, payment_date, start_date, end_date, auto_renew)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0)
            ");

            $stmt->execute([
                $userId,
                $planId,
                $paymentStatus === 'confirmed' ? 'active' : 'pending',
                $paymentMethod,
                $paymentStatus === 'confirmed' ? date('Y-m-d H:i:s') : null,
                $startDate,
                $endDate
            ]);

            if ($paymentStatus === 'confirmed') {
                $success = '¡Felicidades! Tu suscripción ha sido activada. Ya puedes empezar a vender.';
                header('refresh:2;url=emprendedoras-dashboard.php');
            } else {
                $success = 'Suscripción creada. Por favor realiza el pago y sube el comprobante para activar tu cuenta.';
            }

        } catch (Exception $e) {
            $error = 'Error al procesar la suscripción: ' . $e->getMessage();
        }
    }
}

$cantidadProductos = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $it) {
        $cantidadProductos += (int)($it['qty'] ?? 0);
    }
}
$isLoggedIn = true;

$features = json_decode($plan['features'], true) ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suscribirse a <?php echo htmlspecialchars($plan['name']); ?> | CompraTica</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/compratica-header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .subscribe-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .plan-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
        }
        .plan-summary h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .plan-summary .price {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 20px 0;
        }
        .subscribe-form {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .payment-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .payment-option {
            border: 2px solid #e0e0e0;
            padding: 20px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        .payment-option:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .payment-option input[type="radio"] {
            margin-right: 10px;
        }
        .payment-option.selected {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="subscribe-container">
        <div class="plan-summary">
            <h1><?php echo htmlspecialchars($plan['name']); ?></h1>
            <p><?php echo htmlspecialchars($plan['description']); ?></p>

            <ul style="list-style: none; padding: 0; margin: 20px 0;">
                <?php foreach ($features as $feature): ?>
                    <li style="padding: 8px 0;">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($feature); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="subscribe-form">
            <h2 style="margin-bottom: 30px; color: #333;">Completa tu suscripción</h2>

            <div class="form-group">
                <label>Período de facturación</label>
                <select name="billing_period" id="billingPeriod" required>
                    <option value="monthly">Mensual - ₡<?php echo number_format($plan['price_monthly'], 0); ?></option>
                    <?php if ($plan['price_annual'] > 0): ?>
                        <option value="annual">Anual - ₡<?php echo number_format($plan['price_annual'], 0); ?> (ahorra 2 meses)</option>
                    <?php endif; ?>
                </select>
            </div>

            <?php if ($plan['price_monthly'] > 0 || $plan['price_annual'] > 0): ?>
                <div class="form-group">
                    <label>Método de pago</label>
                    <div class="payment-options">
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="sinpe" required>
                            <div>
                                <i class="fas fa-mobile-alt" style="font-size: 2rem; color: #667eea; margin-bottom: 10px;"></i>
                                <div><strong>SINPE Móvil</strong></div>
                            </div>
                        </label>
                        <label class="payment-option">
                            <input type="radio" name="payment_method" value="paypal" required>
                            <div>
                                <i class="fab fa-paypal" style="font-size: 2rem; color: #667eea; margin-bottom: 10px;"></i>
                                <div><strong>PayPal</strong></div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="alert" style="background: #fff3cd; border: 1px solid #ffc107; color: #856404;">
                    <i class="fas fa-info-circle"></i>
                    <strong>Importante:</strong> Una vez realizado el pago, por favor envía el comprobante a través de WhatsApp o correo para activar tu cuenta.
                </div>
            <?php endif; ?>

            <button type="submit" class="btn-submit">
                <?php if ($plan['price_monthly'] == 0 && $plan['price_annual'] == 0): ?>
                    <i class="fas fa-check"></i> Activar Plan Gratuito
                <?php else: ?>
                    <i class="fas fa-credit-card"></i> Continuar con el pago
                <?php endif; ?>
            </button>

            <p style="text-align: center; margin-top: 20px; color: #666; font-size: 0.9rem;">
                <a href="emprendedoras-planes.php" style="color: #667eea;">
                    <i class="fas fa-arrow-left"></i> Volver a ver los planes
                </a>
            </p>
        </form>
    </div>

    <script>
        // Agregar clase 'selected' a la opción de pago seleccionada
        document.querySelectorAll('.payment-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.payment-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });
    </script>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
