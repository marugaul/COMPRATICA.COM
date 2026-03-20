<?php
/**
 * Visor de logs de Mooving - Para debugging
 */
session_start();
require_once __DIR__ . '/../includes/db.php';

// Verificar que es admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$logFile = __DIR__ . '/../logs/mooving_debug.log';
$logContent = '';
$fileExists = false;

if (file_exists($logFile)) {
    $fileExists = true;
    $logContent = file_get_contents($logFile);

    // Si está vacío
    if (empty($logContent)) {
        $logContent = "(El archivo de log existe pero está vacío)";
    }
} else {
    $logContent = "(El archivo de log no existe aún. Intenta acceder a mooving-config.php primero)";
}

// Opción para limpiar el log
if (isset($_POST['clear_log'])) {
    @file_put_contents($logFile, '');
    header('Location: view_mooving_log.php?cleared=1');
    exit;
}

// Auto-refresh cada 5 segundos si se pasa ?auto=1
$autoRefresh = isset($_GET['auto']) && $_GET['auto'] == '1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log de Mooving Debug</title>
    <?php if ($autoRefresh): ?>
    <meta http-equiv="refresh" content="5">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: #252526;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            border-bottom: 2px solid #007acc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .header h1 {
            color: #4ec9b0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #007acc;
            color: white;
        }
        .btn-primary:hover {
            background: #005a9e;
        }
        .btn-danger {
            background: #f14c4c;
            color: white;
        }
        .btn-danger:hover {
            background: #d93232;
        }
        .btn-success {
            background: #4ec9b0;
            color: #1e1e1e;
        }
        .btn-success:hover {
            background: #3ba890;
        }
        .btn-secondary {
            background: #3e3e42;
            color: #d4d4d4;
        }
        .btn-secondary:hover {
            background: #2d2d30;
        }
        .log-container {
            background: #252526;
            padding: 20px;
            border-radius: 0 0 8px 8px;
            min-height: 400px;
        }
        .log-content {
            background: #1e1e1e;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #3e3e42;
            font-size: 0.9rem;
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 600px;
            overflow-y: auto;
        }
        .log-content::-webkit-scrollbar {
            width: 10px;
        }
        .log-content::-webkit-scrollbar-track {
            background: #1e1e1e;
        }
        .log-content::-webkit-scrollbar-thumb {
            background: #3e3e42;
            border-radius: 5px;
        }
        .log-content::-webkit-scrollbar-thumb:hover {
            background: #505050;
        }
        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .status-success {
            background: rgba(78, 201, 176, 0.2);
            color: #4ec9b0;
        }
        .status-error {
            background: rgba(241, 76, 76, 0.2);
            color: #f14c4c;
        }
        .status-warning {
            background: rgba(206, 145, 120, 0.2);
            color: #ce9178;
        }
        .info-box {
            background: rgba(0, 122, 204, 0.1);
            border-left: 3px solid #007acc;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .info-box p {
            margin: 0;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-file-alt"></i>
                Log de Debug: Mooving
            </h1>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
                <a href="mooving-config.php" class="btn btn-primary" target="_blank">
                    <i class="fas fa-motorcycle"></i> Abrir Mooving Config
                </a>
                <a href="?auto=<?= $autoRefresh ? '0' : '1' ?>" class="btn btn-success">
                    <i class="fas fa-sync-alt"></i>
                    <?= $autoRefresh ? 'Desactivar' : 'Activar' ?> Auto-refresh
                </a>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="clear_log" class="btn btn-danger" onclick="return confirm('¿Limpiar el log?')">
                        <i class="fas fa-trash"></i> Limpiar Log
                    </button>
                </form>
            </div>
        </div>

        <div class="log-container">
            <?php if (isset($_GET['cleared'])): ?>
            <div class="status status-success">
                <i class="fas fa-check-circle"></i>
                Log limpiado exitosamente
            </div>
            <?php endif; ?>

            <?php if ($fileExists && !empty($logContent) && strpos($logContent, 'ERROR') !== false): ?>
            <div class="status status-error">
                <i class="fas fa-exclamation-triangle"></i>
                Se detectaron ERRORES en el log
            </div>
            <?php elseif ($fileExists && !empty($logContent)): ?>
            <div class="status status-success">
                <i class="fas fa-check-circle"></i>
                Log cargado correctamente
            </div>
            <?php else: ?>
            <div class="status status-warning">
                <i class="fas fa-info-circle"></i>
                El archivo de log no existe o está vacío
            </div>
            <?php endif; ?>

            <div class="info-box">
                <p>
                    <strong>Ubicación:</strong> <?= htmlspecialchars($logFile) ?><br>
                    <strong>Última actualización:</strong> <?= $fileExists ? date('Y-m-d H:i:s', filemtime($logFile)) : 'N/A' ?><br>
                    <strong>Tamaño:</strong> <?= $fileExists ? number_format(filesize($logFile)) . ' bytes' : 'N/A' ?><br>
                    <?php if ($autoRefresh): ?>
                    <strong style="color: #4ec9b0;"><i class="fas fa-sync-alt fa-spin"></i> Auto-refresh activado (cada 5 segundos)</strong>
                    <?php endif; ?>
                </p>
            </div>

            <h3 style="color: #4ec9b0; margin-bottom: 10px;">
                <i class="fas fa-terminal"></i> Contenido del Log:
            </h3>
            <div class="log-content"><?= htmlspecialchars($logContent) ?></div>
        </div>
    </div>
</body>
</html>
