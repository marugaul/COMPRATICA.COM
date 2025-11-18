<?php
/**
 * Panel de Afiliados - Crear Nuevo Servicio
 *
 * Formulario completo para crear un servicio profesional:
 * - Información básica
 * - Precios y duración
 * - Disponibilidad
 * - Métodos de pago
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/affiliate_auth.php';

aff_require_login();
$pdo = db();
$aff_id = (int)$_SESSION['aff_id'];

// Verificar que el afiliado ofrece servicios
$stmt = $pdo->prepare("SELECT offers_services FROM affiliates WHERE id = ?");
$stmt->execute([$aff_id]);
$offersServices = $stmt->fetchColumn();

if (!$offersServices) {
    header('Location: ../business_setup.php');
    exit;
}

// Obtener categorías activas
$stmt = $pdo->query("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY display_order, name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_service') {
    try {
        $pdo->beginTransaction();

        // Validaciones
        $title = trim($_POST['title'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $short_description = trim($_POST['short_description'] ?? '');
        $price_per_hour = (float)($_POST['price_per_hour'] ?? 0);
        $duration_minutes = (int)($_POST['duration_minutes'] ?? 60);
        $requires_address = isset($_POST['requires_address']) ? 1 : 0;
        $max_distance_km = $requires_address ? (int)($_POST['max_distance_km'] ?? null) : null;

        if (empty($title)) throw new Exception('El título es obligatorio');
        if ($category_id <= 0) throw new Exception('Debes seleccionar una categoría');
        if (empty($description)) throw new Exception('La descripción es obligatoria');
        if ($price_per_hour <= 0) throw new Exception('El precio debe ser mayor a 0');

        // Crear slug único
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $baseSlug = $slug;
        $counter = 1;
        while (true) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetchColumn() == 0) break;
            $slug = $baseSlug . '-' . $counter++;
        }

        // Obtener configuración de pago de la categoría
        $stmt = $pdo->prepare("SELECT requires_online_payment FROM service_categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);
        $accepts_online_payment = $cat['requires_online_payment'] ?? 0;

        // Insertar servicio
        $stmt = $pdo->prepare("
            INSERT INTO services
            (affiliate_id, category_id, title, slug, description, short_description, price_per_hour, duration_minutes,
             is_active, accepts_online_payment, requires_address, max_distance_km, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, datetime('now'), datetime('now'))
        ");
        $stmt->execute([
            $aff_id,
            $category_id,
            $title,
            $slug,
            $description,
            $short_description,
            $price_per_hour,
            $duration_minutes,
            $accepts_online_payment,
            $requires_address,
            $max_distance_km
        ]);

        $service_id = $pdo->lastInsertId();

        // Agregar disponibilidad predeterminada (Lun-Vie, 8am-5pm)
        if (isset($_POST['add_default_schedule']) && $_POST['add_default_schedule']) {
            for ($day = 1; $day <= 5; $day++) {
                $stmt = $pdo->prepare("
                    INSERT INTO service_availability
                    (service_id, day_of_week, start_time, end_time, is_active, created_at, updated_at)
                    VALUES (?, ?, '08:00:00', '17:00:00', 1, datetime('now'), datetime('now'))
                ");
                $stmt->execute([$service_id, $day]);
            }
        }

        // Agregar métodos de pago
        if (isset($_POST['payment_methods']) && is_array($_POST['payment_methods'])) {
            foreach ($_POST['payment_methods'] as $method) {
                $details = '';
                if ($method === 'sinpe') {
                    $details = trim($_POST['sinpe_details'] ?? '');
                } elseif ($method === 'transferencia') {
                    $details = trim($_POST['transfer_details'] ?? '');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO service_payment_methods
                    (service_id, method_type, is_active, details, created_at)
                    VALUES (?, ?, 1, ?, datetime('now'))
                ");
                $stmt->execute([$service_id, $method, $details]);
            }
        }

        $pdo->commit();
        $success = '¡Servicio creado exitosamente!';

        // Redirigir después de 2 segundos
        header("Refresh: 2; url=edit.php?id=$service_id");

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Servicio - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a73e8;
            --primary-dark: #1557b0;
            --accent: #10b981;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-900: #111827;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius: 12px;
            --radius-lg: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.6;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: white;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border-radius: var(--radius-lg);
            padding: 2rem;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 0.9375rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .alert {
            padding: 1.25rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
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

        .form-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .form-section-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--gray-700);
        }

        .form-label .required {
            color: #ef4444;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: all 0.3s;
            font-family: inherit;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(26, 115, 232, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-help {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .checkbox-group label {
            cursor: pointer;
            font-weight: 500;
        }

        .payment-method-card {
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .payment-method-card.selected {
            border-color: var(--primary);
            background: rgba(26, 115, 232, 0.05);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid var(--gray-200);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .form-card {
                padding: 1.5rem;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="header-top">
            <h1 class="header-title">
                <i class="fas fa-plus-circle"></i> Crear Nuevo Servicio
            </h1>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle" style="font-size: 1.5rem;"></i>
            <span><?php echo htmlspecialchars($success); ?> Redirigiendo...</span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle" style="font-size: 1.5rem;"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="action" value="create_service">

        <!-- Información Básica -->
        <div class="form-card">
            <h2 class="form-section-title">
                <i class="fas fa-info-circle"></i>
                Información Básica
            </h2>

            <div class="form-group">
                <label class="form-label">
                    Título del Servicio <span class="required">*</span>
                </label>
                <input
                    type="text"
                    name="title"
                    class="form-input"
                    placeholder="Ej: Reparación de Electrodomésticos a Domicilio"
                    required
                    maxlength="200"
                >
                <p class="form-help">Usa un título claro y descriptivo</p>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Categoría <span class="required">*</span>
                </label>
                <select name="category_id" class="form-select" required id="categorySelect">
                    <option value="">Selecciona una categoría...</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" data-requires-payment="<?php echo $cat['requires_online_payment']; ?>">
                            <?php echo htmlspecialchars($cat['name']); ?>
                            <?php if ($cat['requires_online_payment']): ?>
                                (Requiere pago online)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Descripción Corta
                </label>
                <input
                    type="text"
                    name="short_description"
                    class="form-input"
                    placeholder="Resumen breve del servicio"
                    maxlength="150"
                >
                <p class="form-help">Máximo 150 caracteres. Aparece en las tarjetas de vista previa</p>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Descripción Completa <span class="required">*</span>
                </label>
                <textarea
                    name="description"
                    class="form-textarea"
                    placeholder="Describe tu servicio en detalle: qué incluye, tu experiencia, garantías, etc."
                    required
                    rows="6"
                ></textarea>
            </div>
        </div>

        <!-- Precios y Duración -->
        <div class="form-card">
            <h2 class="form-section-title">
                <i class="fas fa-dollar-sign"></i>
                Precios y Duración
            </h2>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        Precio por Hora (₡) <span class="required">*</span>
                    </label>
                    <input
                        type="number"
                        name="price_per_hour"
                        class="form-input"
                        placeholder="15000"
                        required
                        min="1000"
                        step="100"
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Duración (minutos) <span class="required">*</span>
                    </label>
                    <select name="duration_minutes" class="form-select" required>
                        <option value="30">30 minutos</option>
                        <option value="45">45 minutos</option>
                        <option value="60" selected>1 hora</option>
                        <option value="90">1.5 horas</option>
                        <option value="120">2 horas</option>
                        <option value="180">3 horas</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Ubicación y Área de Servicio -->
        <div class="form-card">
            <h2 class="form-section-title">
                <i class="fas fa-map-marker-alt"></i>
                Ubicación y Área de Servicio
            </h2>

            <div class="checkbox-group">
                <input type="checkbox" name="requires_address" id="requiresAddress" value="1">
                <label for="requiresAddress">
                    Este servicio requiere visita a domicilio
                </label>
            </div>

            <div class="form-group" id="distanceGroup" style="display: none;">
                <label class="form-label">
                    Distancia máxima de cobertura (km)
                </label>
                <input
                    type="number"
                    name="max_distance_km"
                    class="form-input"
                    placeholder="20"
                    min="1"
                    max="200"
                >
                <p class="form-help">Deja en blanco si no tienes límite de distancia</p>
            </div>
        </div>

        <!-- Métodos de Pago -->
        <div class="form-card">
            <h2 class="form-section-title">
                <i class="fas fa-credit-card"></i>
                Métodos de Pago Aceptados
            </h2>

            <div class="payment-method-card">
                <div class="checkbox-group">
                    <input type="checkbox" name="payment_methods[]" value="efectivo" id="pmEfectivo" checked>
                    <label for="pmEfectivo">
                        <i class="fas fa-money-bill-wave"></i> Efectivo
                    </label>
                </div>
            </div>

            <div class="payment-method-card">
                <div class="checkbox-group">
                    <input type="checkbox" name="payment_methods[]" value="sinpe" id="pmSinpe">
                    <label for="pmSinpe">
                        <i class="fas fa-mobile-alt"></i> SINPE Móvil
                    </label>
                </div>
                <div id="sinpeDetails" style="display: none; margin-top: 1rem;">
                    <input
                        type="text"
                        name="sinpe_details"
                        class="form-input"
                        placeholder="Número de teléfono SINPE: 8888-8888"
                    >
                </div>
            </div>

            <div class="payment-method-card">
                <div class="checkbox-group">
                    <input type="checkbox" name="payment_methods[]" value="transferencia" id="pmTransfer">
                    <label for="pmTransfer">
                        <i class="fas fa-exchange-alt"></i> Transferencia Bancaria
                    </label>
                </div>
                <div id="transferDetails" style="display: none; margin-top: 1rem;">
                    <textarea
                        name="transfer_details"
                        class="form-textarea"
                        placeholder="Cuenta IBAN: CR12345678901234567890..."
                        rows="3"
                    ></textarea>
                </div>
            </div>
        </div>

        <!-- Disponibilidad Inicial -->
        <div class="form-card">
            <h2 class="form-section-title">
                <i class="fas fa-calendar-alt"></i>
                Disponibilidad Inicial
            </h2>

            <div class="checkbox-group">
                <input type="checkbox" name="add_default_schedule" id="defaultSchedule" value="1" checked>
                <label for="defaultSchedule">
                    Agregar horario predeterminado (Lunes a Viernes, 8:00am - 5:00pm)
                </label>
            </div>
            <p class="form-help">Podrás personalizar tu disponibilidad después de crear el servicio</p>
        </div>

        <!-- Acciones -->
        <div class="form-actions">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancelar
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-check"></i> Crear Servicio
            </button>
        </div>
    </form>
</div>

<script>
// Mostrar/ocultar campo de distancia
document.getElementById('requiresAddress').addEventListener('change', function() {
    document.getElementById('distanceGroup').style.display = this.checked ? 'block' : 'none';
});

// Mostrar/ocultar detalles de pago
document.getElementById('pmSinpe').addEventListener('change', function() {
    document.getElementById('sinpeDetails').style.display = this.checked ? 'block' : 'none';
});

document.getElementById('pmTransfer').addEventListener('change', function() {
    document.getElementById('transferDetails').style.display = this.checked ? 'block' : 'none';
});

// Marcar tarjeta de método de pago seleccionado
document.querySelectorAll('.payment-method-card input[type="checkbox"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const card = this.closest('.payment-method-card');
        if (this.checked) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
    });

    // Inicializar estado
    if (checkbox.checked) {
        checkbox.closest('.payment-method-card').classList.add('selected');
    }
});
</script>

</body>
</html>
