<?php
/**
 * Panel de Afiliados - Configurar Disponibilidad del Servicio
 *
 * Permite configurar:
 * - Días de la semana disponibles
 * - Horarios por día
 * - Excepciones (días específicos bloqueados)
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/affiliate_auth.php';

aff_require_login();
$pdo = db();
$aff_id = (int)$_SESSION['aff_id'];

// Obtener servicio
$service_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND affiliate_id = ?");
$stmt->execute([$service_id, $aff_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    header('Location: index.php');
    exit;
}

// Obtener disponibilidad actual
$stmt = $pdo->prepare("
    SELECT * FROM service_availability
    WHERE service_id = ?
    ORDER BY day_of_week, start_time
");
$stmt->execute([$service_id]);
$availability = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organizar por día
$scheduleByDay = [];
foreach ($availability as $slot) {
    $scheduleByDay[$slot['day_of_week']][] = $slot;
}

$success = '';
$error = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'save_schedule') {
            $pdo->beginTransaction();

            // Eliminar disponibilidad existente
            $stmt = $pdo->prepare("DELETE FROM service_availability WHERE service_id = ?");
            $stmt->execute([$service_id]);

            // Agregar nueva disponibilidad
            for ($day = 0; $day <= 6; $day++) {
                if (isset($_POST['enabled'][$day]) && $_POST['enabled'][$day]) {
                    $start = $_POST['start_time'][$day] ?? '08:00';
                    $end = $_POST['end_time'][$day] ?? '17:00';

                    $stmt = $pdo->prepare("
                        INSERT INTO service_availability
                        (service_id, day_of_week, start_time, end_time, is_active, created_at, updated_at)
                        VALUES (?, ?, ?, ?, 1, datetime('now'), datetime('now'))
                    ");
                    $stmt->execute([$service_id, $day, $start.':00', $end.':00']);
                }
            }

            $pdo->commit();
            $success = 'Disponibilidad actualizada exitosamente';

            // Recargar datos
            $stmt = $pdo->prepare("SELECT * FROM service_availability WHERE service_id = ? ORDER BY day_of_week");
            $stmt->execute([$service_id]);
            $availability = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $scheduleByDay = [];
            foreach ($availability as $slot) {
                $scheduleByDay[$slot['day_of_week']][] = $slot;
            }

        } elseif ($_POST['action'] === 'add_exception') {
            $date = $_POST['exception_date'] ?? '';
            $reason = trim($_POST['exception_reason'] ?? '');

            if (empty($date)) throw new Exception('Debes seleccionar una fecha');

            $stmt = $pdo->prepare("
                INSERT INTO service_availability_exceptions
                (service_id, exception_date, is_available, reason, created_at)
                VALUES (?, ?, 0, ?, datetime('now'))
            ");
            $stmt->execute([$service_id, $date, $reason]);
            $success = 'Excepción agregada';
        }

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Obtener excepciones
$stmt = $pdo->prepare("
    SELECT * FROM service_availability_exceptions
    WHERE service_id = ? AND exception_date >= date('now')
    ORDER BY exception_date
");
$stmt->execute([$service_id]);
$exceptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$daysOfWeek = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Disponibilidad - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
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
            max-width: 1000px;
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
            font-size: 1.75rem;
            font-weight: 700;
        }

        .service-name {
            color: var(--gray-500);
            font-size: 1rem;
            font-weight: 400;
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

        .card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .day-row {
            display: grid;
            grid-template-columns: 30px 150px 1fr;
            gap: 1rem;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .day-row:last-child {
            border-bottom: none;
        }

        .day-name {
            font-weight: 600;
            color: var(--gray-700);
        }

        input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .time-inputs {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        input[type="time"] {
            padding: 0.5rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 0.9375rem;
        }

        input[type="time"]:disabled {
            background: var(--gray-100);
            cursor: not-allowed;
        }

        .exception-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: var(--radius);
            margin-bottom: 0.75rem;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid var(--gray-200);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .day-row {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            .time-inputs {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="header-top">
            <div>
                <h1 class="header-title">
                    <i class="fas fa-calendar-alt"></i> Configurar Disponibilidad
                </h1>
                <p class="service-name"><?php echo htmlspecialchars($service['title']); ?></p>
            </div>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($success); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <!-- Horario Semanal -->
    <form method="POST" action="">
        <input type="hidden" name="action" value="save_schedule">

        <div class="card">
            <h2 class="card-title">
                <i class="fas fa-clock"></i>
                Horario Semanal
            </h2>

            <?php for ($day = 0; $day <= 6; $day++):
                $hasSchedule = isset($scheduleByDay[$day]);
                $startTime = $hasSchedule ? substr($scheduleByDay[$day][0]['start_time'], 0, 5) : '08:00';
                $endTime = $hasSchedule ? substr($scheduleByDay[$day][0]['end_time'], 0, 5) : '17:00';
            ?>
                <div class="day-row">
                    <input
                        type="checkbox"
                        name="enabled[<?php echo $day; ?>]"
                        value="1"
                        <?php echo $hasSchedule ? 'checked' : ''; ?>
                        onchange="toggleTimeInputs(this, <?php echo $day; ?>)"
                    >
                    <div class="day-name"><?php echo $daysOfWeek[$day]; ?></div>
                    <div class="time-inputs">
                        <span>De:</span>
                        <input
                            type="time"
                            name="start_time[<?php echo $day; ?>]"
                            value="<?php echo $startTime; ?>"
                            id="start_<?php echo $day; ?>"
                            <?php echo !$hasSchedule ? 'disabled' : ''; ?>
                        >
                        <span>hasta:</span>
                        <input
                            type="time"
                            name="end_time[<?php echo $day; ?>]"
                            value="<?php echo $endTime; ?>"
                            id="end_<?php echo $day; ?>"
                            <?php echo !$hasSchedule ? 'disabled' : ''; ?>
                        >
                    </div>
                </div>
            <?php endfor; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar Horario
                </button>
            </div>
        </div>
    </form>

    <!-- Excepciones (Días Bloqueados) -->
    <div class="card">
        <h2 class="card-title">
            <i class="fas fa-ban"></i>
            Días Bloqueados
        </h2>

        <?php if (!empty($exceptions)): ?>
            <?php foreach ($exceptions as $exc): ?>
                <div class="exception-item">
                    <div>
                        <strong><?php echo date('d/m/Y', strtotime($exc['exception_date'])); ?></strong>
                        <?php if ($exc['reason']): ?>
                            <br><small><?php echo htmlspecialchars($exc['reason']); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: var(--gray-500); text-align: center; padding: 2rem 0;">
                No tienes días bloqueados
            </p>
        <?php endif; ?>

        <form method="POST" action="" style="margin-top: 2rem; padding-top: 2rem; border-top: 2px solid var(--gray-200);">
            <input type="hidden" name="action" value="add_exception">
            <h3 style="margin-bottom: 1rem;">Bloquear un Día Específico</h3>

            <div class="form-group">
                <label class="form-label">Fecha</label>
                <input
                    type="date"
                    name="exception_date"
                    class="form-input"
                    min="<?php echo date('Y-m-d'); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label class="form-label">Motivo (opcional)</label>
                <input
                    type="text"
                    name="exception_reason"
                    class="form-input"
                    placeholder="Ej: Vacaciones, Feriado, etc."
                >
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus"></i> Agregar Día Bloqueado
            </button>
        </form>
    </div>
</div>

<script>
function toggleTimeInputs(checkbox, day) {
    document.getElementById('start_' + day).disabled = !checkbox.checked;
    document.getElementById('end_' + day).disabled = !checkbox.checked;
}
</script>

</body>
</html>
