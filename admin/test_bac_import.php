<?php
/**
 * Página de prueba para importación del BAC (SIN autenticación)
 * TEMPORAL - Usar solo para testing
 */
set_time_limit(300);

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Test Importación BAC</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e293b; color: #e2e8f0; }
        .log { background: #0f172a; padding: 20px; border-radius: 8px; white-space: pre-wrap; }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
        h1 { color: #f59e0b; }
        .btn { background: #f59e0b; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #d97706; }
    </style>
</head>
<body>
<h1>🏦 Test Importación BAC (Experimental)</h1>
";

if (isset($_POST['run'])) {
    echo "<div class='log'>";

    // Ejecutar el script con timeout
    $scriptPath = __DIR__ . '/../scripts/import_bac_jobs.php';
    $logPath = __DIR__ . '/../logs/import_bac.log';

    echo "<div class='warning'>⏳ Ejecutando importación BAC (máximo 60 segundos)...</div>\n";
    echo "<div class='warning'>Script: php {$scriptPath}</div>\n\n";

    // Flush para mostrar inmediatamente
    if (ob_get_level()) ob_flush();
    flush();

    // Ejecutar con timeout de 60 segundos
    $cmd = "timeout 60 php " . escapeshellarg($scriptPath) . " 2>&1";

    // Ejecutar y capturar output línea por línea
    $handle = popen($cmd, 'r');
    $output = '';

    if ($handle) {
        echo "<pre style='background:#0f172a;padding:10px;border-radius:4px;'>";
        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line === false) break;

            $output .= $line;

            // Colorear y mostrar línea
            $displayLine = htmlspecialchars($line);
            $displayLine = preg_replace('/✓(.+)/', '<span class="success">✓$1</span>', $displayLine);
            $displayLine = preg_replace('/✗(.+)/', '<span class="error">✗$1</span>', $displayLine);
            $displayLine = preg_replace('/ERROR:(.+)/', '<span class="error">ERROR:$1</span>', $displayLine);
            $displayLine = preg_replace('/⚠(.+)/', '<span class="warning">⚠$1</span>', $displayLine);

            echo $displayLine;

            // Flush para mostrar en tiempo real
            if (ob_get_level()) ob_flush();
            flush();
        }
        echo "</pre>";

        $returnCode = pclose($handle);
    } else {
        echo "<div class='error'>✗ No se pudo ejecutar el script</div>";
        $returnCode = 1;
    }

    echo "\n\n";
    if ($returnCode === 0) {
        echo "<div class='success'>✓ Script completado exitosamente</div>";
    } elseif ($returnCode === 124) {
        echo "<div class='warning'>⚠ Script excedió el tiempo límite (60s) - revisar logs</div>";
    } else {
        echo "<div class='error'>✗ Script terminó con errores (código: {$returnCode})</div>";
    }

    echo "</div>";

    echo "<br><a href='?' class='btn'>Volver</a>";
    echo " <a href='../empleos.php' class='btn' style='background:#10b981;'>Ver Empleos</a>";

    if (file_exists($logPath)) {
        echo " <a href='?view_log=1' class='btn' style='background:#f59e0b;'>Ver Log Completo</a>";
    }

} elseif (isset($_GET['view_log'])) {
    // Mostrar log completo
    $logPath = __DIR__ . '/../logs/import_bac.log';
    echo "<h2>📄 Log Completo de Importación BAC</h2>";
    echo "<div class='log'><pre>";
    if (file_exists($logPath)) {
        echo htmlspecialchars(file_get_contents($logPath));
    } else {
        echo "No hay log disponible";
    }
    echo "</pre></div>";
    echo "<br><a href='?' class='btn'>Volver</a>";

} else {
    echo "<p>Esta página ejecuta el importador del BAC directamente.</p>";
    echo "<p><strong style='color:#f59e0b;'>⚠ EXPERIMENTAL:</strong> Este script puede funcionar o no, dependiendo de las protecciones del sitio del BAC.</p>";
    echo "<form method='POST'>";
    echo "<button type='submit' name='run' class='btn'>▶ Ejecutar Importación BAC</button>";
    echo "</form>";

    echo "<br><br>";
    echo "<p><strong>Nota:</strong> Página de prueba SIN autenticación. Elimínala después de verificar.</p>";

    // Mostrar estado actual
    require_once __DIR__ . '/../includes/db.php';
    $pdo = db();
    $count = $pdo->query("SELECT COUNT(*) FROM job_listings WHERE import_source LIKE 'BAC_%'")->fetchColumn();
    echo "<p>Empleos del BAC en DB actualmente: <strong>{$count}</strong></p>";
}

echo "</body></html>";
