<?php
/**
 * Panel de Afiliados - Gestión de Reservas
 *
 * Permite al proveedor:
 * - Ver todas las reservas de sus servicios
 * - Filtrar por estado y fecha
 * - Aprobar/rechazar reservas
 * - Marcar como completadas
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';

aff_require_login();
$pdo = db();
$aff_id = (int)$_SESSION['aff_id'];

// Filtros
$status_filter = $_GET['status'] ?? 'all';
$service_filter = $_GET['service'] ?? 'all';

// Obtener reservas
$sql = "
    SELECT
        sb.*,
        s.title as service_title,
        s.price_per_hour
    FROM service_bookings sb
    INNER JOIN services s ON s.id = sb.service_id
    WHERE sb.affiliate_id = ?
";

$params = [$aff_id];

if ($status_filter !== 'all') {
    $sql .= " AND sb.status = ?";
    $params[] = $status_filter;
}

if ($service_filter !== 'all' && is_numeric($service_filter)) {
    $sql .= " AND sb.service_id = ?";
    $params[] = (int)$service_filter;
}

$sql .= " ORDER BY sb.booking_date DESC, sb.booking_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener servicios para el filtro
$stmt = $pdo->prepare("SELECT id, title FROM services WHERE affiliate_id = ? ORDER BY title");
$stmt->execute([$aff_id]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pendiente' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Confirmada' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'Completada' THEN 1 ELSE 0 END) as completed
    FROM service_bookings
    WHERE affiliate_id = ?
");
$stmt->execute([$aff_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Actualizar estado de reserva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $booking_id = (int)($_POST['booking_id'] ?? 0);

        // Verificar que la reserva pertenece al afiliado
        $stmt = $pdo->prepare("SELECT id FROM service_bookings WHERE id = ? AND affiliate_id = ?");
        $stmt->execute([$booking_id, $aff_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Reserva no encontrada');
        }

        if ($_POST['action'] === 'confirm') {
            $stmt = $pdo->prepare("UPDATE service_bookings SET status = 'Confirmada', updated_at = datetime('now') WHERE id = ?");
            $stmt->execute([$booking_id]);
            $success = 'Reserva confirmada exitosamente';
        } elseif ($_POST['action'] === 'reject') {
            $stmt = $pdo->prepare("UPDATE service_bookings SET status = 'Rechazada', updated_at = datetime('now') WHERE id = ?");
            $stmt->execute([$booking_id]);
            $success = 'Reserva rechazada';
        } elseif ($_POST['action'] === 'complete') {
            $stmt = $pdo->prepare("UPDATE service_bookings SET status = 'Completada', payment_status = 'Pagado', updated_at = datetime('now') WHERE id = ?");
            $stmt->execute([$booking_id]);
            $success = 'Reserva marcada como completada';
        }

        // Recargar página
        header("Location: bookings.php?status=$status_filter&service=$service_filter");
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Reservas - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
    <style>
        :root {
            --primary: #1a73e8;
            --accent: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
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
            max-width: 1280px;
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
            margin-bottom: 1.5rem;
        }

        .header-title {
            font-size: 2rem;
            font-weight: 700;
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

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .btn-success {
            background: var(--accent);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--primary), #1557b0);
            color: white;
            padding: 1.5rem;
            border-radius: var(--radius);
        }

        .stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-top: 0.5rem;
        }

        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .filter-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .filter-select {
            padding: 0.625rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 0.9375rem;
        }

        .booking-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .booking-header {
            padding: 1.5rem;
            background: var(--gray-50);
            border-bottom: 2px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: start;
        }

        .booking-service {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .booking-date {
            color: var(--gray-500);
            font-size: 0.9375rem;
        }

        .booking-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .status-pendiente {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmada {
            background: #d4edda;
            color: #155724;
        }

        .status-completada {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-rechazada {
            background: #f8d7da;
            color: #721c24;
        }

        .booking-body {
            padding: 1.5rem;
        }

        .booking-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .info-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--gray-500);
            font-weight: 600;
        }

        .info-value {
            font-size: 1rem;
            color: var(--gray-900);
        }

        .booking-actions {
            display: flex;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            background: var(--gray-50);
            border-top: 2px solid var(--gray-200);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--gray-500);
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .booking-header,
            .booking-actions {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="header-top">
            <h1 class="header-title">
                <i class="fas fa-calendar-check"></i> Mis Reservas
            </h1>
            <a href="dashboard.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Reservas</div>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, var(--warning), #d97706);">
                <div class="stat-label">Pendientes</div>
                <div class="stat-value"><?php echo $stats['pending']; ?></div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, var(--accent), #0d9668);">
                <div class="stat-label">Confirmadas</div>
                <div class="stat-value"><?php echo $stats['confirmed']; ?></div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #0dcaf0, #0a9fcf);">
                <div class="stat-label">Completadas</div>
                <div class="stat-value"><?php echo $stats['completed']; ?></div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" class="filters">
        <div class="filter-group">
            <label class="filter-label">Estado</label>
            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Todos</option>
                <option value="Pendiente" <?php echo $status_filter === 'Pendiente' ? 'selected' : ''; ?>>Pendientes</option>
                <option value="Confirmada" <?php echo $status_filter === 'Confirmada' ? 'selected' : ''; ?>>Confirmadas</option>
                <option value="Completada" <?php echo $status_filter === 'Completada' ? 'selected' : ''; ?>>Completadas</option>
                <option value="Rechazada" <?php echo $status_filter === 'Rechazada' ? 'selected' : ''; ?>>Rechazadas</option>
            </select>
        </div>

        <div class="filter-group">
            <label class="filter-label">Servicio</label>
            <select name="service" class="filter-select" onchange="this.form.submit()">
                <option value="all">Todos los servicios</option>
                <?php foreach ($services as $svc): ?>
                    <option value="<?php echo $svc['id']; ?>" <?php echo $service_filter == $svc['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($svc['title']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <!-- Lista de Reservas -->
    <?php if (empty($bookings)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h3>No tienes reservas</h3>
            <p style="color: var(--gray-500);">Las reservas de tus servicios aparecerán aquí</p>
        </div>
    <?php else: ?>
        <?php foreach ($bookings as $booking): ?>
            <div class="booking-card">
                <div class="booking-header">
                    <div>
                        <div class="booking-service"><?php echo htmlspecialchars($booking['service_title']); ?></div>
                        <div class="booking-date">
                            <i class="fas fa-calendar"></i>
                            <?php echo date('d/m/Y', strtotime($booking['booking_date'])); ?> a las
                            <?php echo substr($booking['booking_time'], 0, 5); ?>
                        </div>
                    </div>
                    <div class="booking-status status-<?php echo strtolower($booking['status']); ?>">
                        <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                        <?php echo $booking['status']; ?>
                    </div>
                </div>

                <div class="booking-body">
                    <div class="booking-info-grid">
                        <div class="info-item">
                            <span class="info-label">Cliente</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['customer_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Teléfono</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['customer_phone']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['customer_email']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Duración</span>
                            <span class="info-value"><?php echo $booking['duration_minutes']; ?> minutos</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Monto</span>
                            <span class="info-value" style="color: var(--primary); font-weight: 700;">
                                ₡<?php echo number_format($booking['total_amount'], 0, ',', '.'); ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Método de Pago</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['payment_method'] ?? 'No especificado'); ?></span>
                        </div>
                    </div>

                    <?php if ($booking['address']): ?>
                        <div class="info-item" style="margin-bottom: 1rem;">
                            <span class="info-label">Dirección</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['address']); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($booking['notes']): ?>
                        <div class="info-item">
                            <span class="info-label">Notas del Cliente</span>
                            <span class="info-value"><?php echo nl2br(htmlspecialchars($booking['notes'])); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($booking['status'] === 'Pendiente' || $booking['status'] === 'Confirmada'): ?>
                    <div class="booking-actions">
                        <?php if ($booking['status'] === 'Pendiente'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                <input type="hidden" name="action" value="confirm">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="fas fa-check"></i> Confirmar
                                </button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="fas fa-times"></i> Rechazar
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($booking['status'] === 'Confirmada'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                <input type="hidden" name="action" value="complete">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-check-double"></i> Marcar como Completada
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
