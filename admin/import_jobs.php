<?php
/**
 * admin/import_jobs.php
 * Panel de administración para importación automática de empleos.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

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
                   ? $_POST['source'] : 'indeed';
        $srcArg  = $source === 'all' ? '' : '--source=' . $source;
        $script  = dirname(__DIR__) . '/scripts/import_jobs.php';
        $logFile = dirname(__DIR__) . '/logs/import_jobs.log';

        // Crear directorio de logs si no existe
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

        // Ejecutar en background para no bloquear la página (el script tarda varios minutos)
        $cmd = 'nohup php ' . escapeshellarg($script) . ' ' . $srcArg
             . ' >> ' . escapeshellarg($logFile) . ' 2>&1 &';
        exec($cmd);

        $msg = ['ok',
            '<i class="fas fa-rocket"></i> <strong>Importación iniciada en segundo plano.</strong><br>'
            . 'Puede tardar 2-5 minutos. Revisa el log al final de esta página para ver el progreso.<br>'
            . '<small style="color:#166534;">Comando: <code>php scripts/import_jobs.php ' . htmlspecialchars($srcArg) . '</code></small>'
        ];
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
    <form method="POST">
        <input type="hidden" name="action" value="run_import">
        <div class="grid-2" style="align-items:end;">
            <div>
                <label style="display:block;margin-bottom:6px;font-weight:600;font-size:.88rem;">Fuente</label>
                <select name="source">
                    <option value="remote">Empleos Remotos (Arbeitnow, Remotive, Jobicy)</option>
                    <option value="all">Todas las fuentes</option>
                    <option value="indeed" disabled>Indeed CR (bloqueado por Cloudflare)</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;">
                    <i class="fas fa-download"></i> Importar ahora
                </button>
            </div>
        </div>
    </form>
    <p style="margin:14px 0 0;font-size:.82rem;color:#9ca3af;">
        <i class="fas fa-info-circle"></i>
        La importación puede tardar 1-3 minutos según la cantidad de fuentes. Los empleos duplicados se omiten automáticamente.
        Cada empleo importado expira a los 30 días.
    </p>
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
    <h2 style="justify-content:space-between;">
        <span><i class="fas fa-terminal" style="color:#1e293b;"></i> Log en tiempo real</span>
        <button onclick="refreshLog()" class="btn btn-gray" style="font-size:.8rem;padding:5px 12px;">
            <i class="fas fa-sync-alt" id="refresh-icon"></i> Actualizar
        </button>
    </h2>
    <div id="log-box" class="cron-box" style="min-height:120px;max-height:340px;overflow-y:auto;font-size:.8rem;line-height:1.5;">
        <?php
        $logFile = dirname(__DIR__) . '/logs/import_jobs.log';
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
        Últimas 60 líneas de <code>logs/import_jobs.log</code>
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
// Auto-refresh cada 5s si hay una importación reciente en el log (últimas 2 líneas no tienen "TOTAL")
(function autoRefresh() {
    const box = document.getElementById('log-box');
    if (box && !box.textContent.includes('=== TOTAL')) {
        setTimeout(() => { refreshLog(); autoRefresh(); }, 5000);
    }
})();
</script>
</body>
</html>
