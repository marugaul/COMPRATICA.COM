<?php
/**
 * Visor de Logs de Blacklist
 */
require_once __DIR__ . '/../includes/config.php';

// Verificar que sea admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

$logFile = __DIR__ . '/../logs/error_Blacklist.log';

// Acción para limpiar logs
if (isset($_GET['clear']) && $_GET['clear'] === 'yes') {
    file_put_contents($logFile, '');
    header('Location: view_blacklist_logs.php');
    exit;
}

// Leer logs
$logs = '';
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    $fileSize = filesize($logFile);
    $lastModified = date('Y-m-d H:i:s', filemtime($logFile));
} else {
    $fileSize = 0;
    $lastModified = 'N/A';
}

// Contar entradas
$entryCount = substr_count($logs, str_repeat('-', 80));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Logs de Blacklist</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .log-viewer {
            background: #1e293b;
            color: #f1f5f9;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 600px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .stats-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .highlight-error { color: #ff6b6b; font-weight: bold; }
        .highlight-success { color: #51cf66; font-weight: bold; }
        .highlight-warning { color: #ffd43b; font-weight: bold; }
        .timestamp { color: #94a3b8; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-md-12">
                <h1><i class="fas fa-file-alt"></i> Visor de Logs - Blacklist</h1>
            </div>
        </div>

        <!-- Stats -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card">
                    <h6 class="text-muted">Entradas de Log</h6>
                    <h2><?= $entryCount ?></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6 class="text-muted">Tamaño del Archivo</h6>
                    <h2><?= round($fileSize / 1024, 2) ?> KB</h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h6 class="text-muted">Última Modificación</h6>
                    <h2 style="font-size: 16px;"><?= $lastModified ?></h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card text-end">
                    <a href="?clear=yes" class="btn btn-danger" onclick="return confirm('¿Limpiar todos los logs?')">
                        <i class="fas fa-trash"></i> Limpiar Logs
                    </a>
                    <a href="view_blacklist_logs.php" class="btn btn-primary">
                        <i class="fas fa-sync"></i> Recargar
                    </a>
                </div>
            </div>
        </div>

        <!-- Log Content -->
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-terminal"></i> Contenido del Log</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($logs)): ?>
                    <div class="alert alert-info m-3">
                        <i class="fas fa-info-circle"></i> No hay logs registrados todavía.
                        <br><br>
                        <strong>Para generar logs:</strong>
                        <ol>
                            <li>Intenta acceder a la página de Blacklist</li>
                            <li>Haz clic en cualquier botón relacionado con blacklist</li>
                            <li>Vuelve aquí y recarga la página</li>
                        </ol>
                    </div>
                <?php else: ?>
                    <div class="log-viewer">
<?php
// Colorear el log
$coloredLogs = $logs;
$coloredLogs = preg_replace('/\[(.*?)\]/', '<span class="timestamp">[$1]</span>', $coloredLogs);
$coloredLogs = preg_replace('/(ERROR|EXCEPCIÓN|ACCESO DENEGADO|FAILED)/i', '<span class="highlight-error">$1</span>', $coloredLogs);
$coloredLogs = preg_replace('/(SUCCESS|exitosamente|INICIO|CARGADA)/i', '<span class="highlight-success">$1</span>', $coloredLogs);
$coloredLogs = preg_replace('/(WARNING|ADVERTENCIA|Verificando)/i', '<span class="highlight-warning">$1</span>', $coloredLogs);
echo $coloredLogs;
?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-3 text-center">
            <a href="email_marketing.php?page=blacklist" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Volver a Blacklist
            </a>
            <a href="email_marketing.php?page=dashboard" class="btn btn-secondary">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </div>

        <!-- Auto-refresh -->
        <div class="mt-3 text-center">
            <button onclick="toggleAutoRefresh()" class="btn btn-outline-info" id="autoRefreshBtn">
                <i class="fas fa-sync-alt"></i> Activar Auto-Recarga (5s)
            </button>
        </div>
    </div>

    <script>
    let autoRefreshInterval = null;
    let isAutoRefreshing = false;

    function toggleAutoRefresh() {
        const btn = document.getElementById('autoRefreshBtn');

        if (isAutoRefreshing) {
            clearInterval(autoRefreshInterval);
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Activar Auto-Recarga (5s)';
            btn.className = 'btn btn-outline-info';
            isAutoRefreshing = false;
        } else {
            autoRefreshInterval = setInterval(() => {
                location.reload();
            }, 5000);
            btn.innerHTML = '<i class="fas fa-stop"></i> Detener Auto-Recarga';
            btn.className = 'btn btn-warning';
            isAutoRefreshing = true;
        }
    }

    // Auto-scroll al final
    window.onload = function() {
        const logViewer = document.querySelector('.log-viewer');
        if (logViewer) {
            logViewer.scrollTop = logViewer.scrollHeight;
        }
    };
    </script>
</body>
</html>
