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

$pdo = db();

// Obtener servicio primero para tenerlo en la URL de redirect
$service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;

// Requiere login para hacer reservas
$isLoggedIn = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
if (!$isLoggedIn) {
    // Redirigir al login y volver después
    $currentUrl = urlencode($_SERVER['REQUEST_URI']);
    header("Location: login.php?redirect=$currentUrl");
    exit;
}

// Obtener información del usuario logueado
$userId = (int)$_SESSION['uid'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$userName = $user['name'] ?? $_SESSION['name'] ?? '';
$userEmail = $user['email'] ?? $_SESSION['email'] ?? '';
$userPhone = $user['phone'] ?? '';

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

// Detectar si es una reserva de shuttle (viene de shuttle_results.php)
$isShuttle = isset($_GET['origin']) && isset($_GET['destination']);
$shuttleData = [];
$shuttleQuote = null;

if ($isShuttle) {
    $shuttleData = [
        'origin' => $_GET['origin'] ?? '',
        'destination' => $_GET['destination'] ?? '',
        'date' => $_GET['date'] ?? '',
        'time' => $_GET['time'] ?? '',
        'passengers' => (int)($_GET['passengers'] ?? 2),
        'luggage' => (int)($_GET['luggage'] ?? 2)
    ];

    // Calcular cotización automática
    // Usamos origen/destino como ID para la API de cotización
    // (En este caso, usaremos el nombre directamente como dirección)
    $quoteData = [
        'service_id' => $service_id,
        'origin' => $shuttleData['origin'],
        'origin_type' => 'address',
        'destination' => $shuttleData['destination'],
        'destination_type' => 'address'
    ];

    // Simular llamada a la API de cotización
    // En producción, harías un curl o file_get_contents a la API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://compratica.com/api/calculate_quote.php');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($quoteData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $shuttleQuote = json_decode($response, true);
    }
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
        $payment_method = $_POST['payment_method'] ?? '';
        $address = trim($_POST['address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        // Para shuttles, usar fecha/hora de los parámetros del shuttle
        $isShuttleBooking = isset($_POST['shuttle_origin']) && isset($_POST['shuttle_destination']);
        if ($isShuttleBooking && isset($_POST['shuttle_date']) && isset($_POST['shuttle_time'])) {
            $booking_date = $_POST['shuttle_date'];
            $booking_time = $_POST['shuttle_time'];
        } else {
            $booking_date = $_POST['booking_date'] ?? date('Y-m-d', strtotime('+1 day'));
            $booking_time = $_POST['booking_time'] ?? '09:00';
        }

        if (empty($customer_name)) throw new Exception('El nombre es obligatorio');
        if (empty($customer_email)) throw new Exception('El email es obligatorio');
        if (empty($customer_phone)) throw new Exception('El teléfono es obligatorio');
        if (empty($payment_method)) throw new Exception('Debes seleccionar un método de pago');

        if ($service['requires_address'] && empty($address)) {
            throw new Exception('La dirección es obligatoria para este servicio');
        }

        // Calcular monto total
        $duration = $service['duration_minutes'];
        $hours = $duration / 60;
        $total_amount = $service['price_per_hour'] * $hours;

        // Si es shuttle, usar precio de cotización y datos específicos
        $isShuttleBooking = isset($_POST['shuttle_origin']) && isset($_POST['shuttle_destination']);
        if ($isShuttleBooking && isset($_POST['shuttle_total']) && $_POST['shuttle_total'] > 0) {
            $total_amount = (float)$_POST['shuttle_total'];

            // Agregar información del shuttle a las notas
            $shuttleInfo = sprintf(
                "SHUTTLE PURA VIDA 🇨🇷\n" .
                "Origen: %s\n" .
                "Destino: %s\n" .
                "Fecha: %s\n" .
                "Hora de recogida: %s\n" .
                "Pasajeros: %d\n" .
                "Maletas: %d\n" .
                "Distancia: %s km\n" .
                "---\n%s",
                $_POST['shuttle_origin'] ?? '',
                $_POST['shuttle_destination'] ?? '',
                $_POST['shuttle_date'] ?? '',
                $_POST['shuttle_time'] ?? '',
                (int)($_POST['shuttle_passengers'] ?? 0),
                (int)($_POST['shuttle_luggage'] ?? 0),
                $_POST['shuttle_distance'] ?? '0',
                $notes
            );
            $notes = $shuttleInfo;
        }

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

        // Si es pago SINPE, redirigir a página de carga de comprobante
        if ($payment_method === 'sinpe') {
            header("Location: upload-booking-proof.php?booking_id=$booking_id");
            exit;
        }

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

        <?php if ($isShuttle && $shuttleQuote && isset($shuttleQuote['ok']) && $shuttleQuote['ok']): ?>
            <!-- Información del Shuttle -->
            <div style="background: #e8f5e9; padding: 1.5rem; border-radius: 12px; margin-top: 1.5rem; border-left: 4px solid #4caf50;">
                <h3 style="margin: 0 0 1rem 0; color: #2e7d32;">
                    <i class="fas fa-route"></i> Detalles de tu viaje
                </h3>
                <div style="display: grid; gap: 0.75rem; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between;">
                        <span><i class="fas fa-location-dot"></i> <strong>Desde:</strong></span>
                        <span><?php echo htmlspecialchars($shuttleData['origin']); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span><i class="fas fa-flag-checkered"></i> <strong>Hasta:</strong></span>
                        <span><?php echo htmlspecialchars($shuttleData['destination']); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span><i class="fas fa-calendar"></i> <strong>Fecha:</strong></span>
                        <span><?php echo date('d/m/Y', strtotime($shuttleData['date'])); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span><i class="fas fa-clock"></i> <strong>Hora de recogida:</strong></span>
                        <span><?php echo date('g:i A', strtotime($shuttleData['time'])); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span><i class="fas fa-users"></i> <strong>Pasajeros:</strong></span>
                        <span><?php echo $shuttleData['passengers']; ?> personas</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span><i class="fas fa-suitcase"></i> <strong>Maletas:</strong></span>
                        <span><?php echo $shuttleData['luggage']; ?> maletas</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span><i class="fas fa-route"></i> <strong>Distancia:</strong></span>
                        <span><?php echo $shuttleQuote['distance_km']; ?> km</span>
                    </div>
                </div>
                <div style="border-top: 2px solid #4caf50; padding-top: 0.75rem; margin-top: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; font-size: 1.25rem;">
                        <span><strong>Total del servicio:</strong></span>
                        <span style="color: #2e7d32; font-weight: 700; font-size: 1.5rem;">
                            <?php echo $shuttleQuote['formatted_price']; ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="price-display">
                <div>Precio total del servicio</div>
                <div class="price-amount">
                    ₡<?php echo number_format(($service['price_per_hour'] * $service['duration_minutes'] / 60), 0, ',', '.'); ?>
                </div>
            </div>
        <?php endif; ?>
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

        <?php if ($isShuttle): ?>
            <!-- Preserve shuttle data for form submission -->
            <input type="hidden" name="shuttle_origin" value="<?php echo htmlspecialchars($shuttleData['origin']); ?>">
            <input type="hidden" name="shuttle_destination" value="<?php echo htmlspecialchars($shuttleData['destination']); ?>">
            <input type="hidden" name="shuttle_date" value="<?php echo htmlspecialchars($shuttleData['date']); ?>">
            <input type="hidden" name="shuttle_time" value="<?php echo htmlspecialchars($shuttleData['time']); ?>">
            <input type="hidden" name="shuttle_passengers" value="<?php echo htmlspecialchars($shuttleData['passengers']); ?>">
            <input type="hidden" name="shuttle_luggage" value="<?php echo htmlspecialchars($shuttleData['luggage']); ?>">
            <?php if ($shuttleQuote && isset($shuttleQuote['ok']) && $shuttleQuote['ok']): ?>
                <input type="hidden" name="shuttle_distance" value="<?php echo htmlspecialchars($shuttleQuote['distance_km']); ?>">
                <input type="hidden" name="shuttle_total" value="<?php echo htmlspecialchars($shuttleQuote['total_price']); ?>">
            <?php endif; ?>
        <?php endif; ?>

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
                        value="<?php echo htmlspecialchars($userPhone); ?>"
                        placeholder="8888-8888"
                        required
                    >
                </div>
            </div>
        </div>


        <!-- Dirección -->
        <?php if ($service['requires_address'] && !$isShuttle): ?>
        <div class="form-card">
            <h2 class="form-section-title">
                <i class="fas fa-map-marker-alt"></i> Dirección de Recogida
            </h2>

            <div class="form-group">
                <label class="form-label">
                    Dirección Completa <span class="required">*</span>
                </label>
                <textarea
                    name="address"
                    id="pickup_address"
                    class="form-textarea"
                    rows="3"
                    placeholder="Incluye señas específicas para facilitar la ubicación (ej: San José, Escazú, Plaza del Sol)"
                    required
                ></textarea>
            </div>

            <?php
            // Verificar si es un servicio de shuttle
            $isShuttleService = ($service['category_name'] === 'Shuttle Aeropuerto');

            if ($isShuttleService):
            ?>
            <!-- ORIGEN -->
            <div class="form-group">
                <label class="form-label">
                    Tipo de Origen <span class="required">*</span>
                </label>
                <select id="origin-type" class="form-select" required>
                    <option value="address">Dirección</option>
                    <option value="airport">Aeropuerto</option>
                </select>
            </div>

            <div class="form-group" id="origin-address-group">
                <label class="form-label">
                    Dirección de Origen <span class="required">*</span>
                </label>
                <div style="display: flex; gap: 0.5rem;">
                    <textarea
                        id="origin-address"
                        class="form-textarea"
                        rows="2"
                        placeholder="Ej: San José, Escazú, Plaza del Sol"
                        style="flex: 1;"
                    ></textarea>
                    <button type="button" id="geolocation-btn" class="btn" style="background: #4caf50; color: white; min-width: 140px;">
                        <i class="fas fa-map-marker-alt"></i>
                        AQUÍ ESTOY
                    </button>
                </div>
                <small style="color: var(--gray-500); font-size: 0.875rem;">
                    <i class="fas fa-info-circle"></i> Click en "AQUÍ ESTOY" para usar tu ubicación actual
                </small>
            </div>

            <div class="form-group" id="origin-airport-group" style="display: none;">
                <label class="form-label">
                    Aeropuerto de Origen <span class="required">*</span>
                </label>
                <select id="origin-airport" class="form-select">
                    <option value="">Seleccione un aeropuerto</option>
                    <option value="SJO">Aeropuerto Juan Santamaría (SJO) - Alajuela</option>
                    <option value="LIR">Aeropuerto Daniel Oduber (LIR) - Liberia</option>
                    <option value="LIO">Aeropuerto de Limón (LIO)</option>
                    <option value="SYQ">Aeropuerto Tobías Bolaños (SYQ) - Pavas</option>
                    <option value="TOO">Aeropuerto de San Vito (TOO)</option>
                </select>
            </div>

            <!-- DESTINO -->
            <div class="form-group">
                <label class="form-label">
                    Tipo de Destino <span class="required">*</span>
                </label>
                <select id="dest-type" class="form-select" required>
                    <option value="airport">Aeropuerto</option>
                    <option value="address">Dirección</option>
                </select>
            </div>

            <div class="form-group" id="dest-airport-group">
                <label class="form-label">
                    Aeropuerto de Destino <span class="required">*</span>
                </label>
                <select id="dest-airport" class="form-select">
                    <option value="">Seleccione un aeropuerto</option>
                    <option value="SJO">Aeropuerto Juan Santamaría (SJO) - Alajuela</option>
                    <option value="LIR">Aeropuerto Daniel Oduber (LIR) - Liberia</option>
                    <option value="LIO">Aeropuerto de Limón (LIO)</option>
                    <option value="SYQ">Aeropuerto Tobías Bolaños (SYQ) - Pavas</option>
                    <option value="TOO">Aeropuerto de San Vito (TOO)</option>
                </select>
            </div>

            <div class="form-group" id="dest-address-group" style="display: none;">
                <label class="form-label">
                    Dirección de Destino <span class="required">*</span>
                </label>
                <textarea
                    id="dest-address"
                    class="form-textarea"
                    rows="2"
                    placeholder="Ej: Heredia, Centro, frente al Parque Central"
                ></textarea>
            </div>

            <button type="button" id="calculate-quote-btn" class="btn btn-primary" style="margin-top: 1rem;">
                <i class="fas fa-calculator"></i>
                Calcular Cotización
            </button>

            <div id="quote-result" style="display: none; margin-top: 1.5rem; padding: 1.5rem; background: #e8f5e9; border-radius: 8px; border-left: 4px solid #4caf50;">
                <h3 style="margin: 0 0 1rem 0; color: #2e7d32;">
                    <i class="fas fa-check-circle"></i> Cotización Calculada
                </h3>
                <div style="display: grid; gap: 0.75rem;">
                    <div style="display: flex; justify-content: space-between;">
                        <span><strong>Distancia:</strong></span>
                        <span id="quote-distance">-</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span><strong>Precio base:</strong></span>
                        <span id="quote-base">-</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span><strong>Kilómetros adicionales:</strong></span>
                        <span id="quote-additional-km">-</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span><strong>Costo adicional:</strong></span>
                        <span id="quote-additional-cost">-</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding-top: 0.75rem; border-top: 2px solid #4caf50; font-size: 1.25rem;">
                        <span><strong>Total:</strong></span>
                        <span id="quote-total" style="color: #2e7d32; font-weight: 700;">-</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
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

            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <button type="submit" class="btn btn-primary" style="flex: 2; min-width: 200px;">
                    <i class="fas fa-check"></i>
                    Confirmar Reserva
                </button>

                <?php if (!empty($service['affiliate_phone'])): ?>
                <a
                    href="https://wa.me/506<?php echo preg_replace('/[^0-9]/', '', $service['affiliate_phone']); ?>?text=Hola%2C%20estoy%20interesado%20en%20el%20servicio%20<?php echo urlencode($service['title']); ?>"
                    target="_blank"
                    class="btn"
                    style="flex: 1; min-width: 200px; background: #25d366; color: white; text-align: center; justify-content: center;"
                    title="Contactar por WhatsApp"
                >
                    <i class="fab fa-whatsapp"></i>
                    Consultar por WhatsApp
                </a>
                <?php endif; ?>
            </div>
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

<script>
// Sistema de cotización para shuttles
document.addEventListener('DOMContentLoaded', function() {
    // Elementos del formulario
    const originTypeSelect = document.getElementById('origin-type');
    const destTypeSelect = document.getElementById('dest-type');
    const originAddressGroup = document.getElementById('origin-address-group');
    const originAirportGroup = document.getElementById('origin-airport-group');
    const destAddressGroup = document.getElementById('dest-address-group');
    const destAirportGroup = document.getElementById('dest-airport-group');
    const geolocationBtn = document.getElementById('geolocation-btn');
    const calculateBtn = document.getElementById('calculate-quote-btn');

    // Manejar cambio de tipo de origen
    if (originTypeSelect) {
        originTypeSelect.addEventListener('change', function() {
            if (this.value === 'address') {
                originAddressGroup.style.display = 'block';
                originAirportGroup.style.display = 'none';
            } else {
                originAddressGroup.style.display = 'none';
                originAirportGroup.style.display = 'block';
            }
        });
    }

    // Manejar cambio de tipo de destino
    if (destTypeSelect) {
        destTypeSelect.addEventListener('change', function() {
            if (this.value === 'address') {
                destAddressGroup.style.display = 'block';
                destAirportGroup.style.display = 'none';
            } else {
                destAddressGroup.style.display = 'none';
                destAirportGroup.style.display = 'block';
            }
        });
    }

    // Geolocalización - Botón "AQUÍ ESTOY"
    if (geolocationBtn) {
        geolocationBtn.addEventListener('click', function() {
            if (!navigator.geolocation) {
                alert('Tu navegador no soporta geolocalización');
                return;
            }

            // Mostrar loading
            geolocationBtn.disabled = true;
            geolocationBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ubicando...';

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;

                    // Guardar coordenadas en el textarea
                    document.getElementById('origin-address').value = lat + ',' + lon;

                    // Restaurar botón
                    geolocationBtn.disabled = false;
                    geolocationBtn.innerHTML = '<i class="fas fa-check"></i> Ubicado!';

                    setTimeout(function() {
                        geolocationBtn.innerHTML = '<i class="fas fa-map-marker-alt"></i> AQUÍ ESTOY';
                    }, 2000);
                },
                function(error) {
                    console.error('Error de geolocalización:', error);
                    alert('No se pudo obtener tu ubicación. Por favor verifica los permisos del navegador.');
                    geolocationBtn.disabled = false;
                    geolocationBtn.innerHTML = '<i class="fas fa-map-marker-alt"></i> AQUÍ ESTOY';
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        });
    }

    // Calcular cotización
    if (calculateBtn) {
        calculateBtn.addEventListener('click', async function() {
            const serviceId = <?php echo $service_id; ?>;
            const originType = document.getElementById('origin-type').value;
            const destType = document.getElementById('dest-type').value;

            let origin, destination;

            // Obtener origen
            if (originType === 'address') {
                origin = document.getElementById('origin-address').value.trim();
            } else {
                origin = document.getElementById('origin-airport').value;
            }

            // Obtener destino
            if (destType === 'address') {
                destination = document.getElementById('dest-address').value.trim();
            } else {
                destination = document.getElementById('dest-airport').value;
            }

            // Validar
            if (!origin) {
                alert('Por favor ingresa el origen');
                return;
            }

            if (!destination) {
                alert('Por favor ingresa el destino');
                return;
            }

            // Mostrar loading
            calculateBtn.disabled = true;
            calculateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calculando...';

            try {
                const response = await fetch('/api/calculate_quote.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        service_id: serviceId,
                        origin: origin,
                        origin_type: originType,
                        destination: destination,
                        destination_type: destType
                    })
                });

                const data = await response.json();

                if (data.ok) {
                    // Mostrar resultado
                    document.getElementById('quote-distance').textContent = data.breakdown.distance;
                    document.getElementById('quote-base').textContent = data.breakdown.base;
                    document.getElementById('quote-additional-km').textContent = data.breakdown.additional_km;
                    document.getElementById('quote-additional-cost').textContent = data.breakdown.additional_cost;
                    document.getElementById('quote-total').textContent = data.formatted_price;
                    document.getElementById('quote-result').style.display = 'block';

                    // Scroll suave al resultado
                    document.getElementById('quote-result').scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest'
                    });
                } else {
                    alert('Error al calcular cotización: ' + (data.error || 'Error desconocido'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al conectar con el servidor. Por favor intenta nuevamente.');
            } finally {
                calculateBtn.disabled = false;
                calculateBtn.innerHTML = '<i class="fas fa-calculator"></i> Calcular Cotización';
            }
        });
    }
});
</script>

</body>
</html>
