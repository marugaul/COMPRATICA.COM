<?php
/**
 * admin/import_jobs.php
 * Panel de administración para importación automática de empleos.
 */

// ══════════════════════════════════════════════════════════════════════════
// LOGGING PARA DEBUG - Registra todos los errores
// ══════════════════════════════════════════════════════════════════════════
$ADMIN_LOG_FILE = __DIR__ . '/../logs/admin_import_debug.log';
if (!is_dir(__DIR__ . '/../logs')) @mkdir(__DIR__ . '/../logs', 0777, true);

function admin_debug_log($msg) {
    global $ADMIN_LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$msg}\n";
    @file_put_contents($ADMIN_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

// Capturar todos los errores
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    admin_debug_log("PHP ERROR [$errno]: $errstr en $errfile:$errline");
    return false;
});

set_exception_handler(function($e) {
    admin_debug_log("EXCEPTION: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
    admin_debug_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo "Error 500 - Ver /logs/admin_import_debug.log para detalles";
    exit;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        admin_debug_log("FATAL ERROR: {$error['message']} en {$error['file']}:{$error['line']}");
    }
});

admin_debug_log("========== INICIO PETICIÓN ADMIN ==========");
admin_debug_log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
admin_debug_log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
// ══════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../includes/config.php';  // Cargar config primero para iniciar sesión
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();
admin_debug_log("Login OK - Usuario: " . ($_SESSION['uid'] ?? 'N/A'));

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
            $msg = ['err', '<i class="fas fa-exclamation-triangle"></i> El usuario bot no existe. Visita la portada de la app una vez para inicializarlo.'];
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

    if ($action === 'delete_imported') {
        $source = trim($_POST['source_name'] ?? '');
        if ($source) {
            $n = $pdo->prepare("DELETE FROM job_listings WHERE import_source = ?");
            $n->execute([$source]);
            $msg = ['ok', "Se eliminaron {$n->rowCount()} empleos importados de «{$source}»."];
        }
    }

    if ($action === 'toggle_imported') {
        $active = (int)($_POST['active'] ?? 1);
        $source = trim($_POST['source_name'] ?? '');
        $pdo->prepare("UPDATE job_listings SET is_active=? WHERE import_source=?")
            ->execute([$active, $source]);
        $msg = ['ok', 'Estado actualizado.'];
    }

    if ($action === 'expire_old') {
        $n = $pdo->exec("UPDATE job_listings SET is_active=0 WHERE import_source IS NOT NULL AND end_date < date('now') AND is_active=1");
        $msg = ['ok', "Se marcaron {$n} empleos expirados como inactivos."];
    }

    if ($action === 'delete_old_auto') {
        $quick     = $_POST['quick']     ?? '';
        $date_from = trim($_POST['date_from'] ?? '');
        $date_to   = trim($_POST['date_to']   ?? '');

        if ($quick === '2weeks') {
            $n = $pdo->exec("DELETE FROM job_listings WHERE import_source IS NOT NULL AND created_at < datetime('now', '-14 days')");
            $msg = ['ok', "Se eliminaron <strong>{$n}</strong> empleos automáticos con más de 2 semanas."];
        } elseif ($date_from && $date_to) {
            if ($date_from > $date_to) { [$date_from, $date_to] = [$date_to, $date_from]; }
            $stmt = $pdo->prepare("DELETE FROM job_listings WHERE import_source IS NOT NULL AND date(created_at) BETWEEN ? AND ?");
            $stmt->execute([$date_from, $date_to]);
            $n = $stmt->rowCount();
            $msg = ['ok', "Se eliminaron <strong>{$n}</strong> empleos automáticos publicados entre {$date_from} y {$date_to}."];
        } else {
            $msg = ['error', 'Seleccioná un rango de fechas o usá el botón rápido.'];
        }
    }

    if ($action === 'run_bac_import') {
        admin_debug_log("========== ACCIÓN: run_bac_import ==========");
        $logFile = dirname(__DIR__) . '/logs/import_bac.log';
        $logDir  = dirname($logFile);
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

        // Verificar bot antes de ejecutar
        $botCheck = $pdo->query("SELECT id FROM users WHERE email='bot@compratica.com' LIMIT 1")->fetchColumn();
        if (!$botCheck) {
            admin_debug_log("ERROR: Usuario bot no existe");
            $msg = ['err', '<i class="fas fa-exclamation-triangle"></i> El usuario bot no existe.'];
        } else {
            admin_debug_log("Bot verificado OK, iniciando importación BAC");
            set_time_limit(300);

            $scriptPath = dirname(__DIR__) . '/scripts/import_bac_jobs.php';
            admin_debug_log("Script path: $scriptPath");

            $logLine = fn(string $m) => file_put_contents($logFile,
                '[' . date('Y-m-d H:i:s') . '] ' . $m . "\n", FILE_APPEND | LOCK_EX);

            $logLine('[ADMIN] === Importación BAC iniciada inline ===');

            // Capturar cualquier output y errores PHP del script incluido
            ob_start();
            $prevError = set_error_handler(function($errno, $errstr, $errfile, $errline) use ($logLine) {
                $logLine('[PHP-ERROR] ' . $errstr . ' en ' . $errfile . ':' . $errline);
                return false;
            });
            try {
                ob_start();
                include $scriptPath;
                ob_end_clean();
                $logLine('[ADMIN] === Include completado ===');
                admin_debug_log("Script BAC completado exitosamente");
                $msg = ['ok',
                    '<i class="fas fa-check-circle"></i> <strong>Importación BAC completada.</strong> '
                    . 'Ver el log de abajo para el resultado.'
                ];
            } catch (Throwable $e) {
                $logLine('[FATAL] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
                admin_debug_log("EXCEPTION en run_bac_import: " . $e->getMessage());
                admin_debug_log("Trace: " . $e->getTraceAsString());
                $msg = ['err', '<i class="fas fa-times-circle"></i> Error: ' . $e->getMessage()];
            }
            set_error_handler($prevError);
            $capturedOutput = ob_get_clean();
            if (trim($capturedOutput) !== '') {
                $logLine('[OUTPUT] ' . str_replace("\n", ' | ', trim($capturedOutput)));
            }
        }
    }

    if ($action === 'run_telegram_import') {
        admin_debug_log("========== ACCIÓN: run_telegram_import ==========");
        $logFile = dirname(__DIR__) . '/logs/import_telegram.log';
        $logDir  = dirname($logFile);
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

        // Verificar configuración de Telegram
        $configFile = dirname(__DIR__) . '/includes/telegram_config.php';
        if (!file_exists($configFile)) {
            admin_debug_log("ERROR: Archivo de configuración no existe: $configFile");
            $msg = ['err',
                '<i class="fas fa-exclamation-triangle"></i> <strong>Falta configuración.</strong> '
                . 'Debes crear el archivo <code>includes/telegram_config.php</code> con tu bot token. '
                . 'Ver <code>includes/telegram_config.php.example</code> para instrucciones.'
            ];
        } else {
            admin_debug_log("Config de Telegram encontrado OK");
            // Verificar bot
            $botCheck = $pdo->query("SELECT id FROM users WHERE email='bot@compratica.com' LIMIT 1")->fetchColumn();
            if (!$botCheck) {
                admin_debug_log("ERROR: Usuario bot no existe");
                $msg = ['err', '<i class="fas fa-exclamation-triangle"></i> El usuario bot no existe.'];
            } else {
                admin_debug_log("Bot verificado OK, iniciando importación Telegram");
                set_time_limit(300);

                $scriptPath = dirname(__DIR__) . '/scripts/import_telegram_jobs.php';
                admin_debug_log("Script path: $scriptPath");

                $logLine = fn(string $m) => file_put_contents($logFile,
                    '[' . date('Y-m-d H:i:s') . '] ' . $m . "\n", FILE_APPEND | LOCK_EX);

                $logLine('[ADMIN] === Importación Telegram iniciada inline ===');

                // Capturar cualquier output y errores PHP del script incluido
                ob_start();
                $prevError = set_error_handler(function($errno, $errstr, $errfile, $errline) use ($logLine) {
                    $logLine('[PHP-ERROR] ' . $errstr . ' en ' . $errfile . ':' . $errline);
                    return false;
                });
                try {
                    ob_start();
                    include $scriptPath;
                    ob_end_clean();
                    $logLine('[ADMIN] === Include completado ===');
                    admin_debug_log("Script Telegram completado exitosamente");
                    $msg = ['ok',
                        '<i class="fas fa-check-circle"></i> <strong>Importación Telegram completada.</strong> '
                        . 'Ver el log de abajo para el resultado.'
                    ];
                } catch (Throwable $e) {
                    $logLine('[FATAL] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
                    admin_debug_log("EXCEPTION en run_telegram_import: " . $e->getMessage());
                    admin_debug_log("Trace: " . $e->getTraceAsString());
                    $msg = ['err', '<i class="fas fa-times-circle"></i> Error: ' . $e->getMessage()];
                }
                set_error_handler($prevError);
                $capturedOutput = ob_get_clean();
                if (trim($capturedOutput) !== '') {
                    $logLine('[OUTPUT] ' . str_replace("\n", ' | ', trim($capturedOutput)));
                }
            }
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
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Importación de Empleos | Admin CompraTica</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; }
body { font-family: system-ui, sans-serif; background: #f4f6fa; margin: 0; color: #1f2937; }
.topbar { background: linear-gradient(135deg,#1e3a5f,#2d5a8e); color: white; padding: 14px 28px;
           display:flex; align-items:center; gap:14px; }
.topbar h1 { margin:0; font-size:1.2rem; }
.topbar a { color:rgba(255,255,255,.75); text-decoration:none; font-size:.88rem; }
.topbar a:hover { color:white; }
.container { max-width: 1100px; margin: 32px auto; padding: 0 20px; }
.card { background:white; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,.07); padding:24px; margin-bottom:24px; }
.card h2 { margin:0 0 18px; font-size:1.05rem; color:#374151; display:flex; align-items:center; gap:8px; }
.alert { border-radius:10px; padding:14px 18px; margin-bottom:18px; font-size:.9rem; }
.alert.ok   { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
.alert.err  { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
.grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
@media(max-width:700px){ .grid-2{ grid-template-columns:1fr; } }
.btn { display:inline-flex; align-items:center; gap:7px; padding:9px 18px; border-radius:8px;
       font-weight:700; font-size:.88rem; cursor:pointer; border:none; text-decoration:none; }
.btn-primary { background:#2563eb; color:white; }
.btn-green   { background:#10b981; color:white; }
.btn-red     { background:#ef4444; color:white; }
.btn-gray    { background:#e5e7eb; color:#374151; }
.btn:hover   { opacity:.88; }
select, input[type=text] {
    padding:9px 13px; border:2px solid #e5e7eb; border-radius:8px; font-size:.9rem;
    width:100%; box-sizing:border-box;
}
select:focus, input:focus { border-color:#2563eb; outline:none; }
table { width:100%; border-collapse:collapse; font-size:.85rem; }
th { background:#f1f5f9; padding:9px 12px; text-align:left; color:#6b7280; font-weight:700; }
td { padding:9px 12px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
tr:last-child td { border-bottom:none; }
.badge { display:inline-block; padding:2px 9px; border-radius:20px; font-size:.78rem; font-weight:700; }
.badge-green  { background:#dcfce7; color:#166534; }
.badge-gray   { background:#f1f5f9; color:#6b7280; }
.badge-yellow { background:#fef3c7; color:#92400e; }
.source-tag { font-family:monospace; background:#f1f5f9; padding:2px 7px; border-radius:5px; font-size:.82rem; }
.cron-box { background:#1e293b; color:#e2e8f0; border-radius:10px; padding:16px 18px; font-family:monospace; font-size:.88rem; overflow-x:auto; }
</style>
</head>
<body>
<div class="topbar">
    <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Admin</a>
    <h1><i class="fas fa-robot"></i> Importación Automática de Empleos</h1>
</div>

<div class="container">

<?php if ($msg): ?>
<div class="alert <?= $msg[0] ?>">
    <?= is_array($msg) ? $msg[1] : htmlspecialchars($msg[1]) ?>
</div>
<?php endif; ?>

<!-- INFO DEL BOT -->
<div class="card">
    <h2><i class="fas fa-user-robot" style="color:#2563eb;"></i> Usuario Bot</h2>
    <?php if ($botUser): ?>
    <p style="margin:0;color:#6b7280;font-size:.9rem;">
        Los empleos importados se publican bajo el usuario <strong><?= htmlspecialchars($botUser['name']) ?></strong>
        (ID: <?= $botUser['id'] ?>, email: <code><?= htmlspecialchars($botUser['email']) ?></code>).
        Los compradores ven el nombre de la empresa de cada oferta individual.
    </p>
    <?php else: ?>
    <p style="color:#ef4444;">El usuario bot no existe aún. Se creará automáticamente en la próxima carga de la app.</p>
    <?php endif; ?>
</div>

<!-- EJECUTAR IMPORTACIÓN MANUAL -->
<div class="card">
    <h2><i class="fas fa-play-circle" style="color:#10b981;"></i> Ejecutar Importación</h2>
    <div class="grid-2" style="align-items:end;">
        <div>
            <label style="display:block;margin-bottom:6px;font-weight:600;font-size:.88rem;">Fuente</label>
            <select id="import-source">
                <option value="remote">Empleos Remotos (Arbeitnow, Remotive, Jobicy)</option>
                <option value="all">Todas las fuentes</option>
                <option value="indeed" disabled>Indeed CR (bloqueado por Cloudflare)</option>
            </select>
        </div>
        <div>
            <button type="button" id="import-btn" onclick="startImport()" class="btn btn-green" style="width:100%;justify-content:center;">
                <i class="fas fa-download" id="import-icon"></i> Importar ahora
            </button>
        </div>
    </div>
    <p style="margin:14px 0 0;font-size:.82rem;color:#9ca3af;">
        <i class="fas fa-info-circle"></i>
        La importación puede tardar 1-3 minutos. Verás el progreso en tiempo real aquí abajo.
    </p>
</div>

<!-- IMPORTACIÓN EXPERIMENTAL: BAC CREDOMATIC -->
<div class="card" style="background:linear-gradient(135deg,#fff5f5 0%,#ffffff 100%);border-left:4px solid #f59e0b;">
    <h2 style="color:#f59e0b;">
        <i class="fas fa-flask"></i> Importación Experimental: BAC Credomatic
    </h2>
    <p style="margin:0 0 14px;font-size:.88rem;color:#6b7280;line-height:1.6;">
        <i class="fas fa-exclamation-triangle" style="color:#f59e0b;"></i>
        <strong>Script experimental</strong> que intenta importar empleos del portal de Talento360 del BAC.
        Puede funcionar o no dependiendo de las protecciones del sitio.
    </p>
    <form method="POST" style="display:flex;gap:12px;align-items:center;">
        <input type="hidden" name="action" value="run_bac_import">
        <button type="submit" class="btn" style="background:#f59e0b;flex:1;">
            <i class="fas fa-university"></i> Probar importación BAC
        </button>
        <a href="?view_log=bac" class="btn btn-gray" style="text-decoration:none;">
            <i class="fas fa-file-alt"></i> Ver log BAC
        </a>
    </form>
</div>

<!-- IMPORTACIÓN TELEGRAM: STEMJobsCR -->
<div class="card" style="background:linear-gradient(135deg,#f0f9ff 0%,#ffffff 100%);border-left:4px solid #0088cc;">
    <h2 style="color:#0088cc;">
        <i class="fab fa-telegram"></i> Importación desde Telegram
    </h2>
    <p style="margin:0 0 14px;font-size:.88rem;color:#6b7280;line-height:1.6;">
        <i class="fas fa-info-circle" style="color:#0088cc;"></i>
        Importa empleos desde los canales <strong>@STEMJobsCR</strong> y <strong>@STEMJobsLATAM</strong>.
        <?php
        $configExists = file_exists(dirname(__DIR__) . '/includes/telegram_config.php');
        if (!$configExists):
        ?>
        <br><span style="color:#dc2626;"><i class="fas fa-exclamation-circle"></i> <strong>Configuración pendiente:</strong>
        Debes crear <code>includes/telegram_config.php</code> con tu bot token de @BotFather</span>
        <?php endif; ?>
    </p>
    <form method="POST" style="display:flex;gap:12px;align-items:center;">
        <input type="hidden" name="action" value="run_telegram_import">
        <button type="submit" class="btn" style="background:#0088cc;flex:1;" <?= $configExists ? '' : 'disabled' ?>>
            <i class="fab fa-telegram-plane"></i> <?= $configExists ? 'Importar desde Telegram' : 'Configuración requerida' ?>
        </button>
        <a href="?view_log=telegram" class="btn btn-gray" style="text-decoration:none;">
            <i class="fas fa-file-alt"></i> Ver log
        </a>
        <?php if (!$configExists): ?>
        <a href="https://t.me/BotFather" target="_blank" class="btn" style="background:#10b981;text-decoration:none;">
            <i class="fas fa-robot"></i> Crear Bot
        </a>
        <?php endif; ?>
    </form>
    <?php if (!$configExists): ?>
    <details style="margin-top:14px;padding:12px;background:#f9fafb;border-radius:8px;">
        <summary style="cursor:pointer;font-weight:600;color:#374151;">
            <i class="fas fa-question-circle"></i> ¿Cómo configurar?
        </summary>
        <ol style="margin:10px 0 0;padding-left:20px;font-size:.85rem;line-height:1.8;color:#4b5563;">
            <li>Ve a Telegram y habla con <a href="https://t.me/BotFather" target="_blank" style="color:#0088cc;">@BotFather</a></li>
            <li>Envía el comando: <code>/newbot</code></li>
            <li>Sigue las instrucciones para crear tu bot</li>
            <li>Copia el <strong>TOKEN</strong> que te da</li>
            <li>Copia el archivo <code>includes/telegram_config.php.example</code> a <code>includes/telegram_config.php</code></li>
            <li>Edita el archivo y pega tu TOKEN</li>
            <li>¡Listo! Vuelve aquí y ejecuta la importación</li>
        </ol>
    </details>
    <?php endif; ?>
</div>

<!-- PANEL DE PROGRESO EN TIEMPO REAL -->
<div id="import-progress" class="card" style="display:none;">
    <h2 style="justify-content:space-between;align-items:center;">
        <span><i class="fas fa-terminal" style="color:#10b981;"></i> Progreso de importación</span>
        <span id="import-status" style="font-size:.85rem;font-weight:600;"></span>
    </h2>
    <pre id="import-live-log" class="cron-box" style="min-height:180px;max-height:480px;overflow-y:auto;white-space:pre-wrap;word-break:break-word;margin:0;font-size:.8rem;line-height:1.6;"></pre>
    <div id="import-summary" style="display:none;margin-top:14px;display:none;gap:12px;flex-wrap:wrap;"></div>
</div>

<!-- EMPLEOS IMPORTADOS POR FUENTE -->
<?php if ($bySource): ?>
<div class="card">
    <h2><i class="fas fa-database" style="color:#667eea;"></i> Empleos importados por fuente</h2>
    <table>
        <thead>
            <tr>
                <th>Fuente</th>
                <th>Total</th>
                <th>Activos</th>
                <th>Última importación</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($bySource as $row): ?>
        <tr>
            <td><span class="source-tag"><?= htmlspecialchars($row['import_source']) ?></span></td>
            <td><?= (int)$row['total'] ?></td>
            <td>
                <span class="badge badge-<?= $row['active'] > 0 ? 'green' : 'gray' ?>">
                    <?= (int)$row['active'] ?> activos
                </span>
            </td>
            <td style="color:#6b7280;"><?= substr($row['last_import'], 0, 16) ?></td>
            <td style="display:flex;gap:6px;flex-wrap:wrap;">
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="action" value="toggle_imported">
                    <input type="hidden" name="source_name" value="<?= htmlspecialchars($row['import_source']) ?>">
                    <input type="hidden" name="active" value="<?= $row['active'] > 0 ? 0 : 1 ?>">
                    <button type="submit" class="btn btn-gray" style="padding:5px 10px;font-size:.78rem;">
                        <i class="fas fa-<?= $row['active'] > 0 ? 'eye-slash' : 'eye' ?>"></i>
                        <?= $row['active'] > 0 ? 'Ocultar' : 'Mostrar' ?>
                    </button>
                </form>
                <form method="POST" onsubmit="return confirm('¿Eliminar todos de «<?= htmlspecialchars($row['import_source']) ?>»?')" style="margin:0;">
                    <input type="hidden" name="action" value="delete_imported">
                    <input type="hidden" name="source_name" value="<?= htmlspecialchars($row['import_source']) ?>">
                    <button type="submit" class="btn btn-red" style="padding:5px 10px;font-size:.78rem;">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <form method="POST" style="margin-top:14px;">
        <input type="hidden" name="action" value="expire_old">
        <button type="submit" class="btn btn-gray">
            <i class="fas fa-clock"></i> Marcar expirados como inactivos
        </button>
    </form>
</div>
<?php endif; ?>

<!-- LIMPIEZA DE AUTOMÁTICOS -->
<div class="card" style="border-left:4px solid #ef4444;">
    <h2><i class="fas fa-trash-alt" style="color:#ef4444;"></i> Limpieza de empleos automáticos</h2>
    <p style="color:#6b7280;font-size:.9rem;margin:0 0 16px;">
        Elimina empleos importados automáticamente (<code>import_source</code> no nulo).
        Los empleos publicados por clientes <strong>no se tocan</strong>: se rigen por su plan.
    </p>

    <!-- Botón rápido: 2 semanas -->
    <form method="POST" onsubmit="return confirm('¿Eliminar TODOS los empleos automáticos con más de 2 semanas? Esta acción no se puede deshacer.')" style="margin-bottom:20px;">
        <input type="hidden" name="action" value="delete_old_auto">
        <input type="hidden" name="quick" value="2weeks">
        <button type="submit" class="btn btn-red">
            <i class="fas fa-trash"></i> Eliminar automáticos &gt; 2 semanas
        </button>
        <span style="font-size:.82rem;color:#9ca3af;margin-left:10px;">Borra todo lo que tenga más de 14 días de publicación inicial.</span>
    </form>

    <hr style="border:none;border-top:1px solid #f3f4f6;margin:0 0 16px;">

    <!-- Filtro por rango de fechas -->
    <form method="POST" onsubmit="return confirm('¿Eliminar empleos automáticos en el rango seleccionado?')">
        <input type="hidden" name="action" value="delete_old_auto">
        <p style="font-weight:600;font-size:.9rem;margin:0 0 10px;"><i class="fas fa-calendar-alt"></i> Eliminar por rango de fecha de publicación inicial</p>
        <div style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:14px;">
            <div>
                <label style="font-size:.82rem;color:#6b7280;display:block;margin-bottom:4px;">Desde</label>
                <input type="date" name="date_from" required
                    max="<?= date('Y-m-d') ?>"
                    style="padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:.9rem;">
            </div>
            <div>
                <label style="font-size:.82rem;color:#6b7280;display:block;margin-bottom:4px;">Hasta</label>
                <input type="date" name="date_to" required
                    max="<?= date('Y-m-d') ?>"
                    style="padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:.9rem;">
            </div>
            <div>
                <button type="submit" class="btn btn-red">
                    <i class="fas fa-trash"></i> Eliminar rango
                </button>
            </div>
        </div>
    </form>
</div>

<!-- LOG DE IMPORTACIONES -->
<?php if ($logs): ?>
<div class="card">
    <h2><i class="fas fa-list-alt" style="color:#6b7280;"></i> Historial de importaciones</h2>
    <table>
        <thead>
            <tr>
                <th>Fuente</th>
                <th>Inicio</th>
                <th>Nuevos</th>
                <th>Duplicados</th>
                <th>Errores</th>
                <th>Mensaje</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
        <tr>
            <td style="max-width:220px;word-break:break-all;"><?= htmlspecialchars($log['source']) ?></td>
            <td style="color:#6b7280;white-space:nowrap;"><?= substr($log['started_at'], 0, 16) ?></td>
            <td><span class="badge badge-green">+<?= (int)$log['inserted'] ?></span></td>
            <td><span class="badge badge-gray"><?= (int)$log['skipped'] ?></span></td>
            <td><span class="badge <?= $log['errors'] > 0 ? 'badge-yellow' : 'badge-gray' ?>"><?= (int)$log['errors'] ?></span></td>
            <td style="color:#9ca3af;font-size:.8rem;"><?= htmlspecialchars(substr($log['message'] ?? '', 0, 80)) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- LOG DE ARCHIVO EN TIEMPO REAL -->
<div class="card">
    <?php
    $viewLog = $_GET['view_log'] ?? 'default';
    $logTitle = 'Log en tiempo real';
    $logFile = dirname(__DIR__) . '/logs/import_jobs.log';
    $logDesc = 'logs/import_jobs.log';

    if ($viewLog === 'bac') {
        $logTitle = 'Log BAC Credomatic (Experimental)';
        $logFile = dirname(__DIR__) . '/logs/import_bac.log';
        $logDesc = 'logs/import_bac.log';
    } elseif ($viewLog === 'telegram') {
        $logTitle = 'Log Telegram (@STEMJobsCR)';
        $logFile = dirname(__DIR__) . '/logs/import_telegram.log';
        $logDesc = 'logs/import_telegram.log';
    }
    ?>
    <h2 style="justify-content:space-between;">
        <span><i class="fas fa-terminal" style="color:#1e293b;"></i> <?= htmlspecialchars($logTitle) ?></span>
        <div style="display:flex;gap:8px;">
            <?php if ($viewLog !== 'default'): ?>
                <a href="?" class="btn btn-gray" style="font-size:.8rem;padding:5px 12px;text-decoration:none;">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            <?php endif; ?>
            <button onclick="refreshLog()" class="btn btn-gray" style="font-size:.8rem;padding:5px 12px;">
                <i class="fas fa-sync-alt" id="refresh-icon"></i> Actualizar
            </button>
        </div>
    </h2>
    <div id="log-box" class="cron-box" style="min-height:120px;max-height:340px;overflow-y:auto;font-size:.8rem;line-height:1.5;">
        <?php
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $last  = array_slice($lines, -60);
            echo htmlspecialchars(implode("\n", $last));
        } else {
            echo '(aún no hay log — inicia una importación primero)';
        }
        ?>
    </div>
    <p style="margin:8px 0 0;font-size:.78rem;color:#6b7280;">
        Últimas 60 líneas de <code><?= htmlspecialchars($logDesc) ?></code>
    </p>
</div>

<!-- CONFIGURACIÓN CRON -->
<div class="card">
    <h2><i class="fas fa-clock" style="color:#f59e0b;"></i> Configurar Cron Job (cPanel)</h2>
    <p style="color:#6b7280;font-size:.9rem;margin:0 0 14px;">
        Para importar empleos automáticamente cada día, crea un Cron Job en cPanel con el siguiente comando:
    </p>

    <p style="font-weight:700;font-size:.88rem;margin:0 0 6px;">Cada día a las 6 AM:</p>
    <div class="cron-box">0 6 * * *  php <?= dirname(dirname(__DIR__)) ?>/scripts/import_jobs.php &gt;&gt; <?= dirname(dirname(__DIR__)) ?>/logs/import_jobs.log 2&gt;&amp;1</div>

    <p style="font-weight:700;font-size:.88rem;margin:14px 0 6px;">Cada 12 horas (más empleos frescos):</p>
    <div class="cron-box">0 6,18 * * *  php <?= dirname(dirname(__DIR__)) ?>/scripts/import_jobs.php &gt;&gt; <?= dirname(dirname(__DIR__)) ?>/logs/import_jobs.log 2&gt;&amp;1</div>

    <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:12px 16px;margin-top:16px;font-size:.85rem;color:#92400e;">
        <i class="fas fa-lightbulb"></i>
        <strong>Nota:</strong> En cPanel → <em>Cron Jobs</em>, elige "Custom" e ingresa el comando anterior.
        Reemplaza la ruta si tu servidor usa una ruta diferente. El archivo de log se crea automáticamente en <code>logs/import_jobs.log</code>.
    </div>
</div>

<!-- CÓMO FUNCIONA -->
<div class="card">
    <h2><i class="fas fa-question-circle" style="color:#667eea;"></i> ¿Cómo funciona?</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
        <div style="text-align:center;padding:16px;background:#f8f9ff;border-radius:12px;">
            <i class="fas fa-rss" style="font-size:2rem;color:#f59e0b;display:block;margin-bottom:8px;"></i>
            <strong>Indeed CR RSS</strong>
            <p style="font-size:.82rem;color:#6b7280;margin:6px 0 0;">Descarga empleos del RSS gratuito de Indeed Costa Rica por categoría. Sin API key.</p>
        </div>
        <div style="text-align:center;padding:16px;background:#f8f9ff;border-radius:12px;">
            <i class="fas fa-robot" style="font-size:2rem;color:#2563eb;display:block;margin-bottom:8px;"></i>
            <strong>Usuario Bot</strong>
            <p style="font-size:.82rem;color:#6b7280;margin:6px 0 0;">Se postean bajo el usuario «CompraTica Empleos» creado automáticamente.</p>
        </div>
        <div style="text-align:center;padding:16px;background:#f8f9ff;border-radius:12px;">
            <i class="fas fa-clone" style="font-size:2rem;color:#10b981;display:block;margin-bottom:8px;"></i>
            <strong>Sin duplicados</strong>
            <p style="font-size:.82rem;color:#6b7280;margin:6px 0 0;">Cada empleo tiene URL única. Si ya existe, se omite.</p>
        </div>
        <div style="text-align:center;padding:16px;background:#f8f9ff;border-radius:12px;">
            <i class="fas fa-calendar-times" style="font-size:2rem;color:#ef4444;display:block;margin-bottom:8px;"></i>
            <strong>Expiración automática</strong>
            <p style="font-size:.82rem;color:#6b7280;margin:6px 0 0;">Los empleos importados se desactivan a los 30 días automáticamente.</p>
        </div>
    </div>
</div>

</div><!-- /container -->

<script>
function refreshLog() {
    const icon = document.getElementById('refresh-icon');
    const box  = document.getElementById('log-box');
    icon.classList.add('fa-spin');
    fetch('?ajax=log')
        .then(r => r.text())
        .then(t => {
            box.textContent = t;
            box.scrollTop   = box.scrollHeight;
            icon.classList.remove('fa-spin');
        })
        .catch(() => icon.classList.remove('fa-spin'));
}

// ── Importación en tiempo real ────────────────────────────────────────────────
async function startImport() {
    const source  = document.getElementById('import-source').value;
    const panel   = document.getElementById('import-progress');
    const logEl   = document.getElementById('import-live-log');
    const statusEl= document.getElementById('import-status');
    const btn     = document.getElementById('import-btn');
    const icon    = document.getElementById('import-icon');

    console.log('[startImport] Iniciando importación', {source});

    panel.style.display = 'block';
    logEl.textContent   = '';
    statusEl.innerHTML  = '<span style="color:#f59e0b"><i class="fas fa-circle-notch fa-spin"></i> Importando…</span>';
    btn.disabled = true;
    icon.className = 'fas fa-circle-notch fa-spin';
    panel.scrollIntoView({behavior: 'smooth', block: 'start'});

    const fd = new FormData();
    fd.append('source', source);
    console.log('[startImport] FormData creado', {source});

    let hasError = false;
    let inserted = 0, skipped = 0, errors = 0;

    try {
        console.log('[startImport] Enviando fetch a /admin/import_runner.php');
        const response = await fetch('/admin/import_runner.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'  // Incluir cookies de sesión
        });

        console.log('[startImport] Respuesta recibida', {
            status: response.status,
            ok: response.ok,
            headers: Object.fromEntries(response.headers.entries())
        });

        // Verificar si la respuesta es válida
        if (!response.ok) {
            const errorText = await response.text();
            console.error('[startImport] Error HTTP', {status: response.status, body: errorText});
            throw new Error(`HTTP ${response.status}: ${response.statusText}\n${errorText}`);
        }

        if (!response.body) {
            // Fallback: no streaming (servidor sin soporte)
            const text = await response.text();
            logEl.textContent = text;
            hasError = text.includes('[ERROR]') || text.includes('[FATAL]');
        } else {
            const reader  = response.body.getReader();
            const decoder = new TextDecoder();
            while (true) {
                const {done, value} = await reader.read();
                if (done) break;
                const chunk = decoder.decode(value, {stream: true});
                logEl.textContent += chunk;
                logEl.scrollTop    = logEl.scrollHeight;
                if (chunk.includes('[ERROR]') || chunk.includes('[FATAL]') || chunk.includes('[PHP-WARN]')) hasError = true;
                // Extraer totales de la línea === TOTAL ===
                const m = chunk.match(/\+(\d+) insertados.*?(\d+) duplicados.*?(\d+) errores/);
                if (m) { inserted += +m[1]; skipped += +m[2]; errors += +m[3]; }
            }
        }

        // Resumen final
        const summaryHtml = `
            <span class="badge badge-green" style="font-size:.88rem;padding:5px 12px;">+${inserted} nuevos</span>
            <span class="badge badge-gray"  style="font-size:.88rem;padding:5px 12px;">${skipped} duplicados</span>
            ${errors > 0 ? `<span class="badge badge-yellow" style="font-size:.88rem;padding:5px 12px;">${errors} errores</span>` : ''}
        `;
        const summary = document.getElementById('import-summary');
        summary.innerHTML = summaryHtml;
        summary.style.display = 'flex';

        statusEl.innerHTML = hasError
            ? '<span style="color:#f59e0b"><i class="fas fa-exclamation-triangle"></i> Completado con advertencias</span>'
            : '<span style="color:#10b981"><i class="fas fa-check-circle"></i> ¡Importación completada!</span>';

        // Refrescar historial después de 3s
        setTimeout(refreshLog, 3000);

    } catch(e) {
        console.error('[startImport] Error capturado', e);
        logEl.textContent += '\n[ERROR-JS] ' + e.message + '\n' + (e.stack || '');
        statusEl.innerHTML = '<span style="color:#ef4444"><i class="fas fa-times-circle"></i> Error: ' + e.message + '</span>';
    }

    console.log('[startImport] Finalizando');
    btn.disabled = false;
    icon.className = 'fas fa-download';
}
</script>
</body>
</html>
