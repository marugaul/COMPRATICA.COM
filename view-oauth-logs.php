<?php
// view-oauth-logs.php
// Visualizador de logs de OAuth para depuración

ini_set('display_errors', 1);
error_reporting(E_ALL);

$logFile = __DIR__ . '/logs/real_estate_oauth.log';

// Función para parsear el log
function parseLogFile($logFile) {
    if (!file_exists($logFile)) {
        return [];
    }

    $content = file_get_contents($logFile);
    $entries = explode(str_repeat('-', 80) . "\n", $content);
    $parsed = [];

    foreach ($entries as $entry) {
        $entry = trim($entry);
        if (empty($entry)) continue;

        // Extraer timestamp
        preg_match('/\[([\d\-\s:]+)\]/', $entry, $matches);
        $timestamp = $matches[1] ?? '';

        // Extraer mensaje
        $lines = explode("\n", $entry);
        $firstLine = $lines[0] ?? '';
        $message = preg_replace('/\[[\d\-\s:]+\]\s*/', '', $firstLine);

        // Extraer contexto JSON si existe
        $contextJson = '';
        if (strpos($entry, 'Context:') !== false) {
            $parts = explode('Context:', $entry, 2);
            $contextJson = trim($parts[1] ?? '');
        }

        $parsed[] = [
            'timestamp' => $timestamp,
            'message' => $message,
            'context' => $contextJson,
            'raw' => $entry
        ];
    }

    return array_reverse($parsed); // Más reciente primero
}

$entries = parseLogFile($logFile);
$logExists = file_exists($logFile);
$logSize = $logExists ? filesize($logFile) : 0;

// Acción de limpiar logs
if (isset($_GET['action']) && $_GET['action'] === 'clear' && $logExists) {
    file_put_contents($logFile, '');
    header('Location: view-oauth-logs.php?cleared=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de OAuth - Bienes Raíces</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; background: #1e1e1e; color: #d4d4d4; padding: 1rem; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: #252526; padding: 1.5rem; border-radius: 8px 8px 0 0; border-bottom: 2px solid #007acc; }
        .header h1 { color: #4ec9b0; font-size: 1.5rem; margin-bottom: 0.5rem; }
        .header .info { color: #858585; font-size: 0.9rem; }
        .controls { background: #2d2d30; padding: 1rem; display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        .btn { padding: 0.5rem 1rem; background: #007acc; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.9rem; }
        .btn:hover { background: #005a9e; }
        .btn-danger { background: #f48771; }
        .btn-danger:hover { background: #d16969; }
        .btn-success { background: #4ec9b0; }
        .btn-success:hover { background: #3ea88a; }
        .content { background: #1e1e1e; padding: 1.5rem; border-radius: 0 0 8px 8px; }
        .log-entry { background: #252526; margin-bottom: 1rem; border-radius: 4px; border-left: 4px solid #007acc; overflow: hidden; }
        .log-entry.error { border-left-color: #f48771; }
        .log-entry.success { border-left-color: #4ec9b0; }
        .log-header { padding: 1rem; background: #2d2d30; display: flex; justify-content: space-between; align-items: center; }
        .log-timestamp { color: #858585; font-size: 0.85rem; }
        .log-message { color: #4fc1ff; font-weight: bold; }
        .log-context { padding: 1rem; background: #1e1e1e; border-top: 1px solid #3e3e42; }
        .log-context pre { color: #ce9178; font-size: 0.85rem; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
        .empty-state { text-align: center; padding: 3rem; color: #858585; }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; display: block; }
        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.75rem; margin-left: 0.5rem; }
        .badge-error { background: #5a1d1d; color: #f48771; }
        .badge-success { background: #1d4d3d; color: #4ec9b0; }
        .badge-info { background: #1d3d5a; color: #4fc1ff; }
        .stats { display: flex; gap: 2rem; font-size: 0.9rem; }
        .stat-item { color: #858585; }
        .stat-item strong { color: #d4d4d4; }
        .alert { padding: 1rem; background: #1d4d3d; border: 1px solid #4ec9b0; border-radius: 4px; margin-bottom: 1rem; color: #4ec9b0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Logs de OAuth - Bienes Raíces</h1>
            <div class="info">
                <div class="stats">
                    <div class="stat-item"><strong>Archivo:</strong> /logs/real_estate_oauth.log</div>
                    <div class="stat-item"><strong>Tamaño:</strong> <?php echo number_format($logSize / 1024, 2); ?> KB</div>
                    <div class="stat-item"><strong>Entradas:</strong> <?php echo count($entries); ?></div>
                </div>
            </div>
        </div>

        <div class="controls">
            <a href="view-oauth-logs.php" class="btn btn-success">🔄 Recargar</a>
            <a href="view-oauth-logs.php?action=clear" class="btn btn-danger" onclick="return confirm('¿Estás seguro de que quieres borrar todos los logs?');">🗑️ Limpiar Logs</a>
            <a href="/real-estate/login.php" class="btn">🔑 Ir a Login</a>
            <a href="/test-real-estate-oauth.php" class="btn">🔍 Diagnóstico</a>
        </div>

        <div class="content">
            <?php if (isset($_GET['cleared'])): ?>
                <div class="alert">✅ Logs limpiados exitosamente</div>
            <?php endif; ?>

            <?php if (!$logExists || empty($entries)): ?>
                <div class="empty-state">
                    <i>📝</i>
                    <h2>No hay logs disponibles</h2>
                    <p style="margin-top: 1rem;">Los logs aparecerán aquí cuando ocurra una autenticación OAuth.</p>
                    <p style="margin-top: 0.5rem;"><a href="/real-estate/login.php" class="btn" style="margin-top: 1rem;">Ir a Login para generar logs</a></p>
                </div>
            <?php else: ?>
                <?php foreach ($entries as $entry): ?>
                    <?php
                    $isError = stripos($entry['message'], 'error') !== false;
                    $isSuccess = stripos($entry['message'], 'exitoso') !== false || stripos($entry['message'], 'success') !== false;
                    $class = $isError ? 'error' : ($isSuccess ? 'success' : '');
                    ?>
                    <div class="log-entry <?php echo $class; ?>">
                        <div class="log-header">
                            <div>
                                <span class="log-timestamp"><?php echo htmlspecialchars($entry['timestamp']); ?></span>
                                <span class="log-message"><?php echo htmlspecialchars($entry['message']); ?></span>
                                <?php if ($isError): ?>
                                    <span class="badge badge-error">ERROR</span>
                                <?php elseif ($isSuccess): ?>
                                    <span class="badge badge-success">SUCCESS</span>
                                <?php else: ?>
                                    <span class="badge badge-info">INFO</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($entry['context'])): ?>
                            <div class="log-context">
                                <pre><?php echo htmlspecialchars($entry['context']); ?></pre>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
