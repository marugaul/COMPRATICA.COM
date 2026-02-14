<?php
/**
 * Wizard de Configuración de Negocio para Afiliados
 *
 * Permite al afiliado seleccionar si ofrece:
 * - Productos
 * - Servicios
 * - Ambos
 *
 * Y configurar qué categorías de servicios puede ofrecer
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';

aff_require_login();
$pdo = db();
$aff_id = (int)$_SESSION['aff_id'];

// Obtener información del afiliado
$stmt = $pdo->prepare("
    SELECT * FROM affiliates WHERE id = ? LIMIT 1
");
$stmt->execute([$aff_id]);
$affiliate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$affiliate) {
    header('Location: login.php');
    exit;
}

// Procesar formulario
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_business_type') {
    try {
        $offers_products = isset($_POST['offers_products']) ? 1 : 0;
        $offers_services = isset($_POST['offers_services']) ? 1 : 0;
        $business_description = trim($_POST['business_description'] ?? '');

        if (!$offers_products && !$offers_services) {
            throw new Exception('Debes seleccionar al menos una opción (productos o servicios)');
        }

        $stmt = $pdo->prepare("
            UPDATE affiliates
            SET offers_products = ?,
                offers_services = ?,
                business_description = ?,
                updated_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([$offers_products, $offers_services, $business_description, $aff_id]);

        $success = '¡Configuración guardada exitosamente!';

        // Recargar datos del afiliado
        $stmt = $pdo->prepare("SELECT * FROM affiliates WHERE id = ? LIMIT 1");
        $stmt->execute([$aff_id]);
        $affiliate = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener categorías de servicios disponibles
$categories = [];
try {
    $stmt = $pdo->query("
        SELECT * FROM service_categories
        WHERE is_active = 1
        ORDER BY display_order ASC, name ASC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Tabla no existe aún
}

$offers_products = $affiliate['offers_products'] ?? 1;
$offers_services = $affiliate['offers_services'] ?? 0;
$business_description = $affiliate['business_description'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Negocio - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link rel="stylesheet" href="../assets/style.css?v=24">
    <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        }

        .wizard-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }

        .wizard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2.5rem;
            text-align: center;
        }

        .wizard-header h1 {
            margin: 0 0 0.5rem 0;
            font-size: 2rem;
            font-weight: 700;
        }

        .wizard-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .wizard-body {
            padding: 3rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: #667eea;
        }

        .option-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .option-card {
            border: 3px solid #e0e0e0;
            border-radius: 16px;
            padding: 2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .option-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        }

        .option-card input[type="checkbox"] {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 24px;
            height: 24px;
            cursor: pointer;
        }

        .option-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
        }

        .option-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #667eea;
        }

        .option-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .option-description {
            font-size: 0.95rem;
            color: #666;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 2rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .categories-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .category-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            background: white;
            border-radius: 8px;
            margin-bottom: 0.75rem;
        }

        .category-item:last-child {
            margin-bottom: 0;
        }

        .category-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .category-info {
            flex: 1;
        }

        .category-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .category-desc {
            font-size: 0.85rem;
            color: #666;
        }

        .payment-badge {
            background: #ffc107;
            color: #000;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .payment-badge.no-payment {
            background: #6c757d;
            color: white;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 2.5rem;
        }

        .btn {
            flex: 1;
            padding: 1.25rem;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }

        @media (max-width: 768px) {
            .wizard-body {
                padding: 2rem 1.5rem;
            }

            .option-cards {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="wizard-container">
        <div class="wizard-header">
            <h1><i class="fas fa-magic"></i> Configuración de Negocio</h1>
            <p>Personaliza tu perfil de afiliado</p>
        </div>

        <div class="wizard-body">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle" style="font-size: 1.5rem;"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle" style="font-size: 1.5rem;"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="action" value="update_business_type">

                <div class="section-title">
                    <i class="fas fa-store"></i>
                    ¿Qué ofreces en tu negocio?
                </div>

                <div class="option-cards">
                    <label class="option-card <?php echo $offers_products ? 'selected' : ''; ?>" data-type="products">
                        <input type="checkbox" name="offers_products" <?php echo $offers_products ? 'checked' : ''; ?>>
                        <div class="option-icon">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <div class="option-title">Productos</div>
                        <div class="option-description">
                            Vendo productos físicos o digitales a través de la plataforma
                        </div>
                    </label>

                    <label class="option-card <?php echo $offers_services ? 'selected' : ''; ?>" data-type="services">
                        <input type="checkbox" name="offers_services" <?php echo $offers_services ? 'checked' : ''; ?>>
                        <div class="option-icon">
                            <i class="fas fa-hands-helping"></i>
                        </div>
                        <div class="option-title">Servicios</div>
                        <div class="option-description">
                            Ofrezco servicios profesionales con sistema de reservas
                        </div>
                    </label>
                </div>

                <div class="form-group">
                    <label class="form-label" for="business_description">
                        <i class="fas fa-align-left"></i> Descripción de tu negocio
                    </label>
                    <textarea
                        class="form-control"
                        id="business_description"
                        name="business_description"
                        placeholder="Cuéntale a tus clientes sobre tu negocio, qué lo hace especial y por qué deberían elegirte..."
                    ><?php echo htmlspecialchars($business_description); ?></textarea>
                </div>

                <?php if (!empty($categories)): ?>
                    <div class="categories-info">
                        <div class="section-title" style="margin-bottom: 1rem; font-size: 1.1rem;">
                            <i class="fas fa-list"></i>
                            Categorías de Servicios Disponibles
                        </div>
                        <p style="color: #666; margin-bottom: 1.5rem; font-size: 0.95rem;">
                            Una vez que actives "Servicios", podrás crear servicios en estas categorías:
                        </p>

                        <?php foreach ($categories as $cat): ?>
                            <div class="category-item">
                                <div class="category-icon">
                                    <i class="<?php echo htmlspecialchars($cat['icon']); ?>"></i>
                                </div>
                                <div class="category-info">
                                    <div class="category-name"><?php echo htmlspecialchars($cat['name']); ?></div>
                                    <div class="category-desc"><?php echo htmlspecialchars($cat['description']); ?></div>
                                </div>
                                <?php if ($cat['requires_online_payment']): ?>
                                    <span class="payment-badge">
                                        <i class="fas fa-credit-card"></i> Pago online
                                    </span>
                                <?php else: ?>
                                    <span class="payment-badge no-payment">
                                        <i class="fas fa-handshake"></i> Pago directo
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="btn-group">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Volver al Dashboard
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Guardar Configuración
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Manejar la selección visual de las tarjetas
        document.querySelectorAll('.option-card').forEach(card => {
            const checkbox = card.querySelector('input[type="checkbox"]');

            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });

            // Hacer que toda la tarjeta sea clickeable
            card.addEventListener('click', function(e) {
                if (e.target !== checkbox) {
                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change'));
                }
            });
        });
    </script>
</body>
</html>
