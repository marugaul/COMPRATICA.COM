<?php
/**
 * VERSIÓN TEMPORAL SIN AUTENTICACIÓN
 * Para probar la importación sin necesidad de login
 */

// NO require_login - versión de prueba

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';

// AJAX: devolver las últimas líneas del log
if (($_GET['ajax'] ?? '') === 'log') {
    $logFile = dirname(__DIR__) . '/logs/import_jobs.log';
    if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        echo implode("\n", array_slice($lines, -60));
    } else {
        echo '(log vacío)';
    }
    exit;
}

$pdo = db();
$msg = '';

// ── Acciones POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'run_import') {
        $source  = in_array($_POST['source'] ?? '', ['indeed', 'remote', 'all'])
                   ? $_POST['source'] : 'remote';
        $logFile = dirname(__DIR__) . '/logs/import_jobs.log';
        $logDir  = dirname($logFile);
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

        // Verificar bot antes de ejecutar
        $botCheck = $pdo->query("SELECT id FROM users WHERE email='bot@compratica.com' LIMIT 1")->fetchColumn();
        if (!$botCheck) {
            $msg = ['err', '<i class="fas fa-exclamation-triangle"></i> El usuario bot no existe.'];
        } else {
            set_time_limit(300);

            $argv = ['import_jobs.php'];
            if ($source !== 'all') $argv[] = '--source=' . $source;

            $logLine = fn(string $m) => file_put_contents($logFile,
                '[' . date('Y-m-d H:i:s') . '] ' . $m . "\n", FILE_APPEND | LOCK_EX);

            $logLine('[ADMIN] === Importación iniciada inline. source=' . $source . ' ===');
            $logLine('[ADMIN] PHP=' . phpversion() . ' | SAPI=' . PHP_SAPI . ' | cURL=' . (function_exists('curl_init') ? 'sí' : 'no'));

            // Capturar cualquier output y errores PHP del script incluido
            ob_start();
            $prevError = set_error_handler(function($errno, $errstr, $errfile, $errline) use ($logLine) {
                $logLine('[PHP-ERROR] ' . $errstr . ' en ' . $errfile . ':' . $errline);
                return false;
            });
            try {
                ob_start();
                include dirname(__DIR__) . '/scripts/import_jobs.php';
                ob_end_clean();
                $logLine('[ADMIN] === Include completado ===');
            } catch (\Throwable $e) {
                $logLine('[FATAL] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
            }
            set_error_handler($prevError);
            $capturedOutput = ob_get_clean();
            if (trim($capturedOutput) !== '') {
                $logLine('[OUTPUT] ' . str_replace("\n", ' | ', trim($capturedOutput)));
            }

            $msg = ['ok',
                '<i class="fas fa-check-circle"></i> <strong>Importación completada.</strong> '
                . 'Ver el log de abajo para el resultado detallado.'
            ];
        }
    }
}

// ── Datos para mostrar ───────────────────────────────────────────────────────
$logs = $pdo->query("
    SELECT * FROM job_import_log
    ORDER BY started_at DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

$bySource = $pdo->query("
    SELECT import_source,
           COUNT(*) as total,
           SUM(is_active) as active,
           MAX(created_at) as last_import
    FROM job_listings
    WHERE import_source IS NOT NULL
    GROUP BY import_source
    ORDER BY last_import DESC
")->fetchAll(PDO::FETCH_ASSOC);

$botUser = $pdo->query("SELECT id, name, email FROM users WHERE email='bot@compratica.com' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Importación de Empleos (TEST)</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body{font-family:sans-serif;max-width:1200px;margin:20px auto;padding:20px;background:#f5f5f5}
.card{background:white;padding:20px;margin:15px 0;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
h1{color:#2c3e50}h2{color:#34495e;font-size:1.2rem;border-bottom:2px solid #eee;padding-bottom:10px}
.btn{padding:10px 20px;background:#27ae60;color:white;border:none;border-radius:5px;cursor:pointer;font-size:16px}
.btn:hover{background:#229954}
.alert{padding:15px;border-radius:5px;margin:15px 0}
.alert.ok{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
.alert.err{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
pre{background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:5px;overflow-x:auto;font-size:13px}
table{width:100%;border-collapse:collapse;margin-top:15px}
th,td{padding:10px;text-align:left;border-bottom:1px solid #eee}
th{background:#f8f9fa;font-weight:600}
select{padding:8px;border:1px solid #ddd;border-radius:4px;font-size:14px}
</style>
</head>
<body>
<h1>🤖 Importación de Empleos (SIN AUTH - TEST)</h1>

<?php if ($msg): ?>
<div class="alert <?= $msg[0] ?>">
    <?= $msg[1] ?>
</div>
<?php endif; ?>

<div class="card">
    <h2>Usuario Bot</h2>
    <?php if ($botUser): ?>
    <p>✓ Bot activo: <strong><?= htmlspecialchars($botUser['name']) ?></strong> (ID: <?= $botUser['id'] ?>)</p>
    <?php else: ?>
    <p style="color:red;">✗ Bot no existe</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Ejecutar Importación</h2>
    <form method="POST">
        <input type="hidden" name="action" value="run_import">
        <select name="source">
            <option value="remote">Empleos Remotos (Arbeitnow, Remotive, Jobicy)</option>
            <option value="all">Todas las fuentes</option>
        </select>
        <button type="submit" class="btn"><i class="fas fa-download"></i> Importar Ahora</button>
    </form>
</div>

<?php if ($bySource): ?>
<div class="card">
    <h2>Empleos por Fuente</h2>
    <table>
        <tr><th>Fuente</th><th>Total</th><th>Activos</th><th>Última Importación</th></tr>
        <?php foreach ($bySource as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['import_source']) ?></td>
            <td><?= (int)$row['total'] ?></td>
            <td><?= (int)$row['active'] ?></td>
            <td><?= substr($row['last_import'], 0, 16) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<div class="card">
    <h2>Log (últimas 100 líneas)</h2>
    <pre><?php
    $logFile = dirname(__DIR__) . '/logs/import_jobs.log';
    if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        echo htmlspecialchars(implode("\n", array_slice($lines, -100)));
    } else {
        echo '(log vacío - ejecuta una importación primero)';
    }
    ?></pre>
</div>

</body>
</html>
