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
$isLoggedIn = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
$userName = $_SESSION['name'] ?? 'Usuario';
$userId = $_SESSION['uid'] ?? 0;

// Inicializar carrito para el header
$cantidadProductos = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $it) {
        $cantidadProductos += (int)($it['qty'] ?? 0);
    }
}

// Obtener planes
$pdo = db();
$plans = $pdo->query("
    SELECT * FROM entrepreneur_plans
    WHERE is_active = 1
    ORDER BY display_order, price_monthly
")->fetchAll(PDO::FETCH_ASSOC);

// Verificar si el usuario ya tiene una suscripción activa
$currentSubscription = null;
if ($isLoggedIn) {
    $stmt = $pdo->prepare("
        SELECT s.*, p.name as plan_name
        FROM entrepreneur_subscriptions s
        JOIN entrepreneur_plans p ON s.plan_id = p.id
        WHERE s.user_id = ? AND s.status = 'active'
        ORDER BY s.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $currentSubscription = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planes para Emprendedores | CompraTica</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/compratica-header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .hero-emprendedoras {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 20px;
            text-align: center;
        }
        .hero-emprendedoras h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            font-weight: 700;
        }
        .hero-emprendedoras p {
            font-size: 1.2rem;
            opacity: 0.95;
            max-width: 600px;
            margin: 0 auto;
        }
        .plans-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        .plan-card {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            overflow: hidden;
        }
        .plan-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        .plan-card.featured {
            border: 3px solid #667eea;
            transform: scale(1.05);
        }
        .plan-card.featured::before {
            content: "RECOMENDADO";
            position: absolute;
            top: 20px;
            right: -35px;
            background: #667eea;
            color: white;
            padding: 5px 40px;
            transform: rotate(45deg);
            font-size: 0.75rem;
            font-weight: bold;
        }
        .plan-name {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #333;
        }
        .plan-description {
            color: #666;
            margin-bottom: 25px;
            font-size: 0.95rem;
        }
        .plan-price {
            font-size: 3rem;
            font-weight: 800;
            color: #667eea;
            margin-bottom: 10px;
        }
        .plan-price span {
            font-size: 1.2rem;
            color: #999;
        }
        .plan-features {
            list-style: none;
            padding: 0;
            margin: 30px 0;
        }
        .plan-features li {
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            color: #555;
        }
        .plan-features li i {
            color: #667eea;
            margin-right: 12px;
            font-size: 1.1rem;
        }
        .plan-button {
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
        .plan-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        .plan-button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .current-plan-badge {
            background: #4ade80;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="hero-emprendedoras">
        <h1>✨ Planes para Emprendedores ✨</h1>
        <p>Elige el plan perfecto para hacer crecer tu negocio y llegar a más clientes</p>
    </div>

    <?php if ($currentSubscription): ?>
        <div style="text-align: center; margin: 30px 0;">
            <div class="current-plan-badge">
                <i class="fas fa-crown"></i> Plan Actual: <?php echo htmlspecialchars($currentSubscription['plan_name']); ?>
            </div>
            <p style="color: #666; margin-top: 10px;">
                <a href="emprendedores-dashboard.php" style="color: #667eea;">
                    <i class="fas fa-arrow-right"></i> Ir a mi Dashboard
                </a>
            </p>
        </div>
    <?php endif; ?>

    <div class="plans-container">
        <?php foreach ($plans as $index => $plan):
            $features = json_decode($plan['features'], true) ?? [];
            $isFeatured = $index === 1; // El plan del medio es destacado
        ?>
            <div class="plan-card <?php echo $isFeatured ? 'featured' : ''; ?>">
                <h2 class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></h2>
                <p class="plan-description"><?php echo htmlspecialchars($plan['description']); ?></p>

                <div class="plan-price">
                    ₡<?php echo number_format($plan['price_monthly'], 0); ?>
                    <span>/mes</span>
                </div>

                <?php if ($plan['price_annual'] > 0): ?>
                    <p style="color: #4ade80; font-size: 0.9rem; margin-bottom: 20px;">
                        <i class="fas fa-tag"></i>
                        ₡<?php echo number_format($plan['price_annual'], 0); ?>/año (ahorra 2 meses)
                    </p>
                <?php endif; ?>

                <ul class="plan-features">
                    <?php foreach ($features as $feature): ?>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <?php echo htmlspecialchars($feature); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($isLoggedIn): ?>
                    <?php if ($currentSubscription && $currentSubscription['plan_id'] == $plan['id']): ?>
                        <button class="plan-button" disabled>
                            <i class="fas fa-check"></i> Plan Actual
                        </button>
                    <?php else: ?>
                        <a href="emprendedores-subscribe.php?plan_id=<?php echo $plan['id']; ?>">
                            <button class="plan-button">
                                <?php echo $currentSubscription ? 'Cambiar a este plan' : 'Elegir este plan'; ?>
                            </button>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="login.php?redirect=emprendedores-planes.php">
                        <button class="plan-button">
                            Iniciar sesión para elegir
                        </button>
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div style="text-align: center; padding: 60px 20px; background: #f9fafb; margin-top: 60px;">
        <h2 style="font-size: 2rem; margin-bottom: 20px; color: #333;">
            ¿Por qué vender en CompraTica?
        </h2>
        <div style="max-width: 900px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px; margin-top: 40px;">
            <div style="padding: 30px;">
                <i class="fas fa-users" style="font-size: 3rem; color: #667eea; margin-bottom: 15px;"></i>
                <h3 style="color: #333; margin-bottom: 10px;">Audiencia Local</h3>
                <p style="color: #666;">Conecta con compradores de toda Costa Rica</p>
            </div>
            <div style="padding: 30px;">
                <i class="fas fa-shield-alt" style="font-size: 3rem; color: #667eea; margin-bottom: 15px;"></i>
                <h3 style="color: #333; margin-bottom: 10px;">Pagos Seguros</h3>
                <p style="color: #666;">SINPE y PayPal integrados</p>
            </div>
            <div style="padding: 30px;">
                <i class="fas fa-chart-line" style="font-size: 3rem; color: #667eea; margin-bottom: 15px;"></i>
                <h3 style="color: #333; margin-bottom: 10px;">Haz Crecer tu Negocio</h3>
                <p style="color: #666;">Herramientas para impulsar tus ventas</p>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
