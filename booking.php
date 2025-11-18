<?php
/**
 * Sistema de Reservas para Clientes
 *
 * Permite a los usuarios:
 * - Seleccionar fecha y hora disponible
 * - Ingresar datos de contacto
 * - Elegir método de pago
 * - Confirmar reserva
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

// Sesiones
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
$userName = $_SESSION['name'] ?? '';
$userEmail = $_SESSION['email'] ?? '';

$pdo = db();

// Obtener servicio
$service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;

$stmt = $pdo->prepare("
    SELECT
        s.*,
        a.name as affiliate_name,
        a.email as affiliate_email,
        a.phone as affiliate_phone,
        sc.name as category_name,
        sc.icon as category_icon,
        sc.requires_online_payment
    FROM services s
    INNER JOIN affiliates a ON a.id = s.affiliate_id
    INNER JOIN service_categories sc ON sc.id = s.category_id
    WHERE s.id = ? AND s.is_active = 1
    LIMIT 1
");
$stmt->execute([$service_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    header('Location: servicios.php');
    exit;
}

// Obtener disponibilidad
$stmt = $pdo->prepare("
    SELECT * FROM service_availability
    WHERE service_id = ? AND is_active = 1
    ORDER BY day_of_week, start_time
");
$stmt->execute([$service_id]);
$availability = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener métodos de pago
$stmt = $pdo->prepare("
    SELECT * FROM service_payment_methods
    WHERE service_id = ? AND is_active = 1
");
$stmt->execute([$service_id]);
$paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Procesar reserva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_booking') {
    try {
        // Validaciones
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_email = trim($_POST['customer_email'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $booking_date = $_POST['booking_date'] ?? '';
        $booking_time = $_POST['booking_time'] ?? '';
        $payment_method = $_POST['payment_method'] ?? '';
        $address = trim($_POST['address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (empty($customer_name)) throw new Exception('El nombre es obligatorio');
        if (empty($customer_email)) throw new Exception('El email es obligatorio');
        if (empty($customer_phone)) throw new Exception('El teléfono es obligatorio');
        if (empty($booking_date)) throw new Exception('Debes seleccionar una fecha');
        if (empty($booking_time)) throw new Exception('Debes seleccionar una hora');
        if (empty($payment_method)) throw new Exception('Debes seleccionar un método de pago');

        if ($service['requires_address'] && empty($address)) {
            throw new Exception('La dirección es obligatoria para este servicio');
        }

        // Calcular monto total
        $duration = $service['duration_minutes'];
        $hours = $duration / 60;
        $total_amount = $service['price_per_hour'] * $hours;

        // Insertar reserva
        $stmt = $pdo->prepare("
            INSERT INTO service_bookings
            (service_id, user_id, affiliate_id, customer_name, customer_email, customer_phone,
             booking_date, booking_time, duration_minutes, address, notes, status, total_amount,
             currency, payment_status, payment_method, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', ?, 'CRC', 'Pendiente', ?, datetime('now'), datetime('now'))
        ");
        $stmt->execute([
            $service_id,
            $isLoggedIn ? $_SESSION['uid'] : null,
            $service['affiliate_id'],
            $customer_name,
            $customer_email,
            $customer_phone,
            $booking_date,
            $booking_time,
            $duration,
            $address,
            $notes,
            $total_amount,
            $payment_method
        ]);

        $booking_id = $pdo->lastInsertId();
        $success = '¡Reserva creada exitosamente! Te contactaremos pronto para confirmar.';

        // Limpiar formulario
        $_POST = [];

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$daysOfWeek = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar: <?php echo htmlspecialchars($service['title']); ?> - <?php echo APP_NAME; ?></title>
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
            --gray-500: #6b7280;
            --gray-700: #374151;
            --gray-900: #111827;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
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

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            text-decoration: none;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .service-header {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .service-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .service-meta {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            color: var(--gray-500);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .price-display {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1.5rem;
            border-radius: var(--radius);
            text-align: center;
            margin-top: 1.5rem;
        }

        .price-amount {
            font-size: 2.5rem;
            font-weight: 700;
        }

        .alert {
            padding: 1.25rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
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
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .required {
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
            font-family: inherit;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .radio-group {
            display: grid;
            gap: 0.75rem;
        }

        .radio-option {
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .radio-option:hover {
            border-color: var(--primary);
        }

        .radio-option input:checked + label {
            color: var(--primary);
            font-weight: 600;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
            width: 100%;
            justify-content: center;
            font-size: 1.125rem;
        }

        .btn-primary:hover {
            background: #0d9668;
            transform: translateY(-2px);
        }

        .availability-info {
            background: var(--gray-50);
            padding: 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
        }

        .availability-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .availability-item:last-child {
            border-bottom: none;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .service-title {
                font-size: 1.5rem;
            }

            .price-amount {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="service_detail.php?id=<?php echo $service_id; ?>" class="back-link">
        <i class="fas fa-arrow-left"></i>
        Volver al servicio
    </a>

    <div class="service-header">
        <h1 class="service-title">
            <i class="<?php echo htmlspecialchars($service['category_icon']); ?>"></i>
            <?php echo htmlspecialchars($service['title']); ?>
        </h1>
        <div class="service-meta">
            <div class="meta-item">
                <i class="fas fa-user"></i>
                <?php echo htmlspecialchars($service['affiliate_name']); ?>
            </div>
            <div class="meta-item">
                <i class="fas fa-clock"></i>
                <?php echo $service['duration_minutes']; ?> minutos
            </div>
        </div>

        <div class="price-display">
            <div>Precio total del servicio</div>
            <div class="price-amount">
                ₡<?php echo number_format(($service['price_per_hour'] * $service['duration_minutes'] / 60), 0, ',', '.'); ?>
            </div>
        </div>
    </div>

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

    <?php if (!$success): ?>
    <form method="POST" action="">
        <input type="hidden" name="action" value="create_booking">

        <!-- Datos de Contacto -->
        <div class="form-card">
            <h2 class="form-section-title">
                <i class="fas fa-user"></i> Tus Datos
            </h2>

            <div class="form-group">
                <label class="form-label">
                    Nombre Completo <span class="required">*</span>
                </label>
                <input
                    type="text"
                    name="customer_name"
                    class="form-input"
                    value="<?php echo htmlspecialchars($userName); ?>"
                    required
                >
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        Email <span class="required">*</span>
                    </label>
                    <input
                        type="email"
                        name="customer_email"
                        class="form-input"
                        value="<?php echo htmlspecialchars($userEmail); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Teléfono <span class="required">*</span>
                    </label>
                    <input
                        type="tel"
                        name="customer_phone"
                        class="form-input"
                        placeholder="8888-8888"
                        required
                    >
                </div>
            </div>
        </div>

        <!-- Fecha y Hora -->
        <div class="form-card">
            <h2 class="form-section-title">
                <i class="fas fa-calendar"></i> Fecha y Hora
            </h2>

            <?php if (!empty($availability)): ?>
                <div class="availability-info">
                    <strong>Horarios disponibles:</strong>
                    <?php
                    $scheduleByDay = [];
                    foreach ($availability as $slot) {
                        $scheduleByDay[$slot['day_of_week']][] = $slot;
                    }
                    foreach ($scheduleByDay as $day => $slots):
                    ?>
                        <div class="availability-item">
                            <span><?php echo $daysOfWeek[$day]; ?></span>
                            <span>
                                <?php foreach ($slots as $slot): ?>
                                    <?php echo substr($slot['start_time'], 0, 5); ?> - <?php echo substr($slot['end_time'], 0, 5); ?>
                                <?php endforeach; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        Fecha <span class="required">*</span>
                    </label>
                    <input
                        type="date"
                        name="booking_date"
                        class="form-input"
                        min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Hora <span class="required">*</span>
                    </label>
                    <input
                        type="time"
                        name="booking_time"
                        class="form-input"
                        required
                    >
                </div>
            </div>
        </div>

        <!-- Dirección -->
        <?php if ($service['requires_address']): ?>
        <div class="form-card">
            <h2 class="form-section-title">
                <i class="fas fa-map-marker-alt"></i> Dirección
            </h2>

            <div class="form-group">
                <label class="form-label">
                    Dirección Completa <span class="required">*</span>
                </label>
                <textarea
                    name="address"
                    class="form-textarea"
                    rows="3"
                    placeholder="Incluye señas específicas para facilitar la ubicación"
                    required
                ></textarea>
            </div>
        </div>
        <?php endif; ?>

        <!-- Método de Pago -->
        <div class="form-card">
            <h2 class="form-section-title">
                <i class="fas fa-credit-card"></i> Método de Pago
            </h2>

            <div class="radio-group">
                <?php foreach ($paymentMethods as $pm): ?>
                    <div class="radio-option">
                        <input
                            type="radio"
                            name="payment_method"
                            value="<?php echo htmlspecialchars($pm['method_type']); ?>"
                            id="pm_<?php echo $pm['id']; ?>"
                            required
                        >
                        <label for="pm_<?php echo $pm['id']; ?>">
                            <?php
                            $icons = [
                                'efectivo' => 'fa-money-bill-wave',
                                'sinpe' => 'fa-mobile-alt',
                                'transferencia' => 'fa-exchange-alt'
                            ];
                            $icon = $icons[$pm['method_type']] ?? 'fa-credit-card';
                            ?>
                            <i class="fas <?php echo $icon; ?>"></i>
                            <?php echo ucfirst($pm['method_type']); ?>
                            <?php if ($pm['details']): ?>
                                <br><small style="color: var(--gray-500);"><?php echo htmlspecialchars($pm['details']); ?></small>
                            <?php endif; ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Notas Adicionales -->
        <div class="form-card">
            <h2 class="form-section-title">
                <i class="fas fa-comment"></i> Notas Adicionales (opcional)
            </h2>

            <div class="form-group">
                <textarea
                    name="notes"
                    class="form-textarea"
                    rows="4"
                    placeholder="Información adicional que el proveedor deba saber..."
                ></textarea>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-check"></i>
                Confirmar Reserva
            </button>
        </div>
    </form>
    <?php else: ?>
        <div class="form-card" style="text-align: center;">
            <a href="servicios.php" class="btn btn-primary">
                <i class="fas fa-search"></i> Ver Más Servicios
            </a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
