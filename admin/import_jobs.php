<?php
/**
 * Página de Administración: Importación de Empleos
 *
 * Muestra el log de importación y permite ejecutar manualmente la importación
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

// Solo permitir acceso a administradores (agregar validación de sesión si es necesario)
// session_start();
// if (!isset($_SESSION['admin_logged_in'])) {
//     header('Location: login.php');
//     exit;
// }

$pdo = db();

// Obtener ruta del log
$logFile = __DIR__ . '/../logs/import_jobs.log';
$scriptFile = __DIR__ . '/../scripts/import_jobs.php';

// Variables para mensajes
$message = '';
$messageType = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'run_import':
                // Ejecutar importación manualmente
                $output = [];
                $returnVar = 0;
                exec("php " . escapeshellarg($scriptFile) . " 2>&1", $output, $returnVar);

                if ($returnVar === 0) {
                    $message = "Importación ejecutada exitosamente";
                    $messageType = "success";
                } else {
                    $message = "Error al ejecutar la importación. Código: $returnVar";
                    $messageType = "error";
                }
                break;

            case 'clear_log':
                // Limpiar log
                if (file_exists($logFile)) {
                    file_put_contents($logFile, '');
                    $message = "Log limpiado exitosamente";
                    $messageType = "success";
                } else {
                    $message = "El archivo de log no existe";
                    $messageType = "warning";
                }
                break;
        }
    }
}

// Leer el log (últimas 500 líneas)
$logContent = '';
$logLines = [];
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $allLines = explode("\n", $logContent);
    $logLines = array_slice($allLines, -500); // Últimas 500 líneas
    $logContent = implode("\n", $logLines);
} else {
    $logContent = "El archivo de log no existe aún.\nSe creará cuando se ejecute la primera importación.";
}

// Obtener estadísticas de empleos importados
$statsQuery = $pdo->query("
    SELECT
        COUNT(*) as total_jobs,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_jobs,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_jobs,
        SUM(CASE WHEN employer_id = (SELECT id FROM jobs_employers WHERE email = 'importador@compratica.com' LIMIT 1) THEN 1 ELSE 0 END) as imported_jobs
    FROM job_listings
");
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC);

// Obtener últimos empleos importados
$recentJobsQuery = $pdo->query("
    SELECT
        jl.id, jl.title, jl.category, jl.location,
        jl.is_active, jl.created_at, je.company_name
    FROM job_listings jl
    LEFT JOIN jobs_employers je ON jl.employer_id = je.id
    WHERE je.email = 'importador@compratica.com'
    ORDER BY jl.created_at DESC
    LIMIT 20
");
$recentJobs = $recentJobsQuery->fetchAll(PDO::FETCH_ASSOC);

// Verificar si el archivo de script existe
$scriptExists = file_exists($scriptFile);
$scriptExecutable = $scriptExists && is_executable($scriptFile);

// Información del cron
$cronInfo = "0 6,18 * * * php " . $scriptFile . " >> " . $logFile . " 2>&1";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importación de Empleos - Admin</title>
    <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --primary-light: #34495e;
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #3498db;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-600: #6c757d;
            --gray-800: #343a40;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--gray-100);
            color: var(--gray-800);
        }

        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 1.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
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

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .card-header {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .stat-box {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }

        .stat-box h3 {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-box p {
            color: var(--gray-600);
            font-size: 0.95rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-light);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #229954;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-secondary {
            background: var(--gray-300);
            color: var(--gray-800);
        }

        .btn-secondary:hover {
            background: var(--gray-600);
            color: white;
        }

        .log-container {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 1.5rem;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            overflow-x: auto;
            max-height: 600px;
            overflow-y: auto;
        }

        .log-container pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .info-box {
            background: #e8f4f8;
            border-left: 4px solid var(--info);
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .info-box code {
            background: rgba(0,0,0,0.1);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-family: monospace;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-ok {
            background: #d4edda;
            color: #155724;
        }

        .status-error {
            background: #f8d7da;
            color: #721c24;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        table th {
            background: var(--gray-100);
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-800);
            border-bottom: 2px solid var(--gray-300);
        }

        table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--gray-200);
        }

        table tbody tr:hover {
            background: var(--gray-100);
        }

        .actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            <i class="fas fa-file-import"></i>
            Importación de Empleos
        </h1>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'times-circle' : 'exclamation-triangle'); ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-box">
                <h3><?php echo $stats['total_jobs']; ?></h3>
                <p><i class="fas fa-briefcase"></i> Total Empleos</p>
            </div>
            <div class="stat-box">
                <h3><?php echo $stats['active_jobs']; ?></h3>
                <p><i class="fas fa-check-circle"></i> Empleos Activos</p>
            </div>
            <div class="stat-box">
                <h3><?php echo $stats['imported_jobs']; ?></h3>
                <p><i class="fas fa-download"></i> Importados</p>
            </div>
            <div class="stat-box">
                <h3><?php echo $stats['inactive_jobs']; ?></h3>
                <p><i class="fas fa-times-circle"></i> Inactivos</p>
            </div>
        </div>

        <!-- Información del Sistema -->
        <div class="card">
            <div class="card-header">
                <span><i class="fas fa-info-circle"></i> Estado del Sistema</span>
            </div>

            <div class="info-box">
                <strong><i class="fas fa-clock"></i> Configuración del Cron:</strong><br>
                <code><?php echo htmlspecialchars($cronInfo); ?></code><br>
                <small>Se ejecuta todos los días a las 6:00 AM y 6:00 PM</small>
            </div>

            <p>
                <strong>Script de importación:</strong>
                <?php if ($scriptExists): ?>
                    <span class="status-badge status-ok"><i class="fas fa-check"></i> Existe</span>
                <?php else: ?>
                    <span class="status-badge status-error"><i class="fas fa-times"></i> No encontrado</span>
                <?php endif; ?>
                <code><?php echo htmlspecialchars($scriptFile); ?></code>
            </p>

            <p>
                <strong>Archivo de log:</strong>
                <?php if (file_exists($logFile)): ?>
                    <span class="status-badge status-ok"><i class="fas fa-check"></i> Existe</span>
                <?php else: ?>
                    <span class="status-badge status-error"><i class="fas fa-times"></i> No existe</span>
                <?php endif; ?>
                <code><?php echo htmlspecialchars($logFile); ?></code>
            </p>
        </div>

        <!-- Acciones -->
        <div class="card">
            <div class="card-header">
                <span><i class="fas fa-cog"></i> Acciones</span>
            </div>

            <div class="actions">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="run_import">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-play"></i>
                        Ejecutar Importación Ahora
                    </button>
                </form>

                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de limpiar el log?');">
                    <input type="hidden" name="action" value="clear_log">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i>
                        Limpiar Log
                    </button>
                </form>

                <a href="dashboard_ext.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Volver al Dashboard
                </a>
            </div>
        </div>

        <!-- Últimos Empleos Importados -->
        <?php if (count($recentJobs) > 0): ?>
        <div class="card">
            <div class="card-header">
                <span><i class="fas fa-list"></i> Últimos Empleos Importados (20)</span>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Categoría</th>
                        <th>Ubicación</th>
                        <th>Estado</th>
                        <th>Fecha Creación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentJobs as $job): ?>
                    <tr>
                        <td><strong>#<?php echo $job['id']; ?></strong></td>
                        <td><?php echo htmlspecialchars($job['title']); ?></td>
                        <td><?php echo htmlspecialchars($job['category']); ?></td>
                        <td><?php echo htmlspecialchars($job['location']); ?></td>
                        <td>
                            <?php if ($job['is_active']): ?>
                                <span class="status-badge status-ok">Activo</span>
                            <?php else: ?>
                                <span class="status-badge status-error">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($job['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Log de Importación -->
        <div class="card">
            <div class="card-header">
                <span><i class="fas fa-file-alt"></i> Log de Importación (Últimas 500 líneas)</span>
                <a href="javascript:location.reload();" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                    <i class="fas fa-sync"></i> Recargar
                </a>
            </div>

            <div class="log-container">
                <pre><?php echo htmlspecialchars($logContent); ?></pre>
            </div>
        </div>
    </div>
</body>
</html>
