<?php
/**
 * Panel simple para importar empleos de Telegram
 */
require_once __DIR__ . '/../includes/config.php';  // Cargar config primero para iniciar sesión
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

$pdo = db();
$msg = '';
$log_content = '';

// Ejecutar importación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'import') {
        $logFile = dirname(__DIR__) . '/logs/import_telegram.log';

        // Verificar bot
        $botCheck = $pdo->query("SELECT id FROM users WHERE email='bot@compratica.com' LIMIT 1")->fetchColumn();
        if (!$botCheck) {
            $msg = ['error', 'El usuario bot no existe. Se creará automáticamente al cargar la app.'];
        } else {
            set_time_limit(300);

            $scriptPath = dirname(__DIR__) . '/scripts/import_telegram_jobs.php';

            // Ejecutar script
            $output = [];
            $returnVar = 0;
            exec("php {$scriptPath} 2>&1", $output, $returnVar);

            $msg = ['success', 'Importación completada. Ver log abajo para detalles.'];
        }
    }
}

// Cargar log
$logFile = dirname(__DIR__) . '/logs/import_telegram.log';
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $log_content = implode("\n", array_slice($lines, -50));
}

// Obtener estadísticas
$stats = $pdo->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as activos
    FROM job_listings
    WHERE import_source LIKE 'Telegram%'
")->fetch(PDO::FETCH_ASSOC);

$recent = $pdo->query("
    SELECT id, title, location, created_at, is_active
    FROM job_listings
    WHERE import_source LIKE 'Telegram%'
    ORDER BY created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Importar Empleos de Telegram | Admin CompraTica</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f3f4f6;
            color: #1f2937;
            line-height: 1.6;
        }
        .header {
            background: linear-gradient(135deg, #0088cc, #005f99);
            color: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header h1 {
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .header a {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            font-size: 0.9rem;
            margin-top: 0.5rem;
            display: inline-block;
        }
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .card h2 {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-box {
            background: linear-gradient(135deg, #0088cc, #00a0e6);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert.success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #0088cc;
            color: white;
        }
        .btn-primary:hover {
            background: #006699;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .log-box {
            background: #1e293b;
            color: #e2e8f0;
            padding: 1.5rem;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            line-height: 1.6;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        th {
            background: #f9fafb;
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            color: #6b7280;
            border-bottom: 2px solid #e5e7eb;
        }
        td {
            padding: 0.75rem;
            border-bottom: 1px solid #f3f4f6;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-gray {
            background: #f3f4f6;
            color: #6b7280;
        }
        .info-box {
            background: #f0f9ff;
            border-left: 4px solid #0088cc;
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .info-box ul {
            margin: 0.5rem 0 0 1.5rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            <i class="fab fa-telegram"></i>
            Importar Empleos de Telegram
        </h1>
        <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
    </div>

    <div class="container">
        <?php if ($msg): ?>
        <div class="alert <?= $msg[0] ?>">
            <i class="fas fa-<?= $msg[0] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <?= $msg[1] ?>
        </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?= $stats['total'] ?? 0 ?></div>
                <div class="stat-label">Total Empleos</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['activos'] ?? 0 ?></div>
                <div class="stat-label">Activos</div>
            </div>
        </div>

        <!-- Acción Principal -->
        <div class="card">
            <h2>
                <i class="fas fa-download"></i>
                Ejecutar Importación
            </h2>

            <div class="info-box">
                <strong><i class="fas fa-info-circle"></i> Canales configurados:</strong>
                <ul>
                    <li><strong>@STEMJobsCR</strong> - Empleos STEM en Costa Rica</li>
                    <li><strong>@STEMJobsLATAM</strong> - Empleos remotos LATAM</li>
                    <li>@empleosti - Empleos TI</li>
                    <li>@empleoscr506 - Empleos generales CR</li>
                    <li>@remoteworkcr - Trabajo remoto CR</li>
                </ul>
            </div>

            <form method="POST" style="margin-top: 1.5rem;">
                <input type="hidden" name="action" value="import">
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; font-size: 1rem; padding: 1rem;">
                    <i class="fas fa-cloud-download-alt"></i>
                    Importar Empleos Ahora
                </button>
            </form>

            <p style="margin-top: 1rem; color: #6b7280; font-size: 0.9rem;">
                <i class="fas fa-clock"></i>
                La importación puede tardar 30-60 segundos. Solo importará empleos nuevos (no duplicados).
            </p>
        </div>

        <!-- Últimos Empleos -->
        <?php if ($recent): ?>
        <div class="card">
            <h2>
                <i class="fas fa-list"></i>
                Últimos 10 Empleos Importados
            </h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Ubicación</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $job): ?>
                    <tr>
                        <td><?= $job['id'] ?></td>
                        <td><?= htmlspecialchars($job['title']) ?></td>
                        <td><?= htmlspecialchars($job['location'] ?? 'N/A') ?></td>
                        <td><?= date('d/m/Y', strtotime($job['created_at'])) ?></td>
                        <td>
                            <?php if ($job['is_active']): ?>
                                <span class="badge badge-success">Activo</span>
                            <?php else: ?>
                                <span class="badge badge-gray">Inactivo</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Log -->
        <div class="card">
            <h2 style="display: flex; justify-content: space-between; align-items: center;">
                <span>
                    <i class="fas fa-terminal"></i>
                    Log de Importación
                </span>
                <button onclick="location.reload()" class="btn btn-secondary" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                    <i class="fas fa-sync-alt"></i>
                    Actualizar
                </button>
            </h2>
            <div class="log-box">
                <?= $log_content ? htmlspecialchars($log_content) : '(Sin log todavía - ejecuta una importación primero)' ?>
            </div>
            <p style="margin-top: 0.75rem; color: #6b7280; font-size: 0.85rem;">
                Últimas 50 líneas de <code>logs/import_telegram.log</code>
            </p>
        </div>

        <!-- Instrucciones -->
        <div class="card">
            <h2>
                <i class="fas fa-question-circle"></i>
                ¿Cómo funciona?
            </h2>
            <div class="info-box">
                <p><strong>El importador hace lo siguiente:</strong></p>
                <ol style="margin: 0.5rem 0 0 1.5rem; line-height: 1.8;">
                    <li>Accede a los canales públicos de Telegram (modo web, sin API)</li>
                    <li>Busca mensajes que contengan información de empleos</li>
                    <li>Extrae título, empresa, ubicación y descripción</li>
                    <li>Verifica si el empleo ya existe en la base de datos</li>
                    <li>Solo importa empleos nuevos (evita duplicados)</li>
                    <li>Los publica bajo el usuario "bot@compratica.com"</li>
                </ol>
                <p style="margin-top: 1rem;"><strong>Nota:</strong> Si dice "0 mensajes nuevos", significa que ya importó todos los empleos disponibles.</p>
            </div>
        </div>
    </div>
</body>
</html>
