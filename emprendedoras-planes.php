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

    <div style="text-align:center; padding:60px 20px; background:#f9fafb; margin-top:60px;">
        <h2 style="font-size:2rem; margin-bottom:20px; color:#333;">
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
                <p style="color: #666; margin-bottom: 14px;">SINPE, PayPal y tarjeta integrados</p>
                <div style="display:flex; align-items:center; justify-content:center; gap:10px; flex-wrap:wrap;">
                    <!-- Visa -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 30" style="height:26px;border-radius:4px;box-shadow:0 1px 4px rgba(0,0,0,.18);">
                        <rect width="48" height="30" rx="4" fill="#1A1F71"/>
                        <text x="24" y="21" font-family="Arial,sans-serif" font-size="13" font-weight="900" fill="#FFFFFF" text-anchor="middle" letter-spacing="1">VISA</text>
                    </svg>
                    <!-- Mastercard -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 30" style="height:26px;border-radius:4px;box-shadow:0 1px 4px rgba(0,0,0,.18);">
                        <rect width="48" height="30" rx="4" fill="#252525"/>
                        <circle cx="18" cy="15" r="9" fill="#EB001B"/>
                        <circle cx="30" cy="15" r="9" fill="#F79E1B"/>
                        <path d="M24 8.3a9 9 0 0 1 0 13.4A9 9 0 0 1 24 8.3z" fill="#FF5F00"/>
                    </svg>
                    <!-- Amex -->
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 30" style="height:26px;border-radius:4px;box-shadow:0 1px 4px rgba(0,0,0,.18);">
                        <rect width="48" height="30" rx="4" fill="#2E77BC"/>
                        <text x="24" y="20" font-family="Arial,sans-serif" font-size="8.5" font-weight="900" fill="#FFFFFF" text-anchor="middle" letter-spacing=".5">AMERICAN</text>
                        <text x="24" y="27" font-family="Arial,sans-serif" font-size="5.5" font-weight="700" fill="#FFFFFF" text-anchor="middle" letter-spacing="1.5">EXPRESS</text>
                    </svg>
                </div>
            </div>
            <div style="padding: 30px;">
                <i class="fas fa-chart-line" style="font-size: 3rem; color: #667eea; margin-bottom: 15px;"></i>
                <h3 style="color: #333; margin-bottom: 10px;">Haz Crecer tu Negocio</h3>
                <p style="color: #666;">Herramientas para impulsar tus ventas</p>
            </div>
        </div>
    </div>

    <!-- ── SECCIÓN CONTACTO / DUDAS ──────────────────────────────────────────── -->
    <div style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); padding:60px 20px; text-align:center;">
        <h2 style="color:white; font-size:1.8rem; margin-bottom:10px;">¿Tenés dudas sobre los planes?</h2>
        <p style="color:rgba(255,255,255,.85); font-size:1rem; margin-bottom:36px; max-width:520px; margin-left:auto; margin-right:auto;">
            Nuestro equipo te asesora sin compromiso. Escribinos por WhatsApp o envianos un correo y te respondemos a la brevedad.
        </p>
        <div style="display:flex; justify-content:center; gap:16px; flex-wrap:wrap;">
            <!-- WhatsApp -->
            <a href="https://wa.me/50688902814?text=Hola%2C%20quisiera%20saber%20m%C3%A1s%20sobre%20los%20planes%20de%20emprendedores%20en%20CompraTica."
               target="_blank" rel="noopener"
               style="display:inline-flex; align-items:center; gap:10px; background:#25D366; color:white;
                      padding:15px 30px; border-radius:50px; font-size:1rem; font-weight:700;
                      text-decoration:none; box-shadow:0 4px 15px rgba(37,211,102,.4); transition:opacity .2s;"
               onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
                <svg viewBox="0 0 24 24" style="width:22px;height:22px;fill:white;" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
                Escribinos por WhatsApp
            </a>
            <!-- Email -->
            <a href="mailto:info@compratica.com?subject=Consulta%20sobre%20planes%20de%20emprendedores&body=Hola%20equipo%20CompraTica%2C%0A%0ATengo%20las%20siguientes%20dudas%20sobre%20los%20planes%3A%0A%0A"
               style="display:inline-flex; align-items:center; gap:10px; background:white; color:#667eea;
                      padding:15px 30px; border-radius:50px; font-size:1rem; font-weight:700;
                      text-decoration:none; box-shadow:0 4px 15px rgba(0,0,0,.15); transition:opacity .2s;"
               onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
                <i class="fas fa-envelope" style="font-size:1.1rem;"></i>
                Envianos un correo
            </a>
        </div>
        <p style="color:rgba(255,255,255,.6); font-size:.82rem; margin-top:20px;">
            <i class="fas fa-envelope" style="margin-right:4px;"></i> info@compratica.com &nbsp;·&nbsp;
            <i class="fab fa-whatsapp" style="margin-right:4px;"></i> +506 8890-2814
        </p>
    </div>
    <!-- ── FIN CONTACTO ─────────────────────────────────────────────────────── -->

    <!-- ── BOTÓN FLOTANTE WHATSAPP ──────────────────────────────────────────── -->
    <a href="https://wa.me/50688902814?text=Hola%2C%20quisiera%20saber%20m%C3%A1s%20sobre%20los%20planes%20de%20emprendedores%20en%20CompraTica."
       target="_blank" rel="noopener"
       title="Chateá con nosotros por WhatsApp"
       style="position:fixed; bottom:24px; right:24px; z-index:9999;
              width:58px; height:58px; border-radius:50%; background:#25D366;
              display:flex; align-items:center; justify-content:center;
              box-shadow:0 4px 18px rgba(37,211,102,.55); transition:transform .2s, box-shadow .2s;"
       onmouseover="this.style.transform='scale(1.1)';this.style.boxShadow='0 6px 24px rgba(37,211,102,.7)'"
       onmouseout="this.style.transform='scale(1)';this.style.boxShadow='0 4px 18px rgba(37,211,102,.55)'">
        <svg viewBox="0 0 24 24" style="width:30px;height:30px;fill:white;" xmlns="http://www.w3.org/2000/svg">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
        </svg>
    </a>
    <!-- ── FIN BOTÓN FLOTANTE ────────────────────────────────────────────────── -->

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
