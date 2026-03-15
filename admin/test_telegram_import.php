<?php
/**
 * Página de prueba para importación de Telegram (SIN autenticación)
 * TEMPORAL - Usar solo para testing
 */
set_time_limit(300);

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Test Importación Telegram</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e293b; color: #e2e8f0; }
        .log { background: #0f172a; padding: 20px; border-radius: 8px; white-space: pre-wrap; }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
        h1 { color: #60a5fa; }
        .btn { background: #0088cc; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #0077b3; }
    </style>
</head>
<body>
<h1>🤖 Test Importación Telegram</h1>
";

if (isset($_POST['run'])) {
    echo "<div class='log'>";

    // Ejecutar el script
    $scriptPath = __DIR__ . '/../scripts/import_telegram_jobs.php';

    echo "<div class='warning'>Ejecutando: php {$scriptPath}</div>\n\n";

    // Capturar output
    ob_start();
    passthru("php " . escapeshellarg($scriptPath) . " 2>&1", $returnCode);
    $output = ob_get_clean();

    // Colorear output
    $output = htmlspecialchars($output);
    $output = preg_replace('/✓(.+)/', '<span class="success">✓$1</span>', $output);
    $output = preg_replace('/✗(.+)/', '<span class="error">✗$1</span>', $output);
    $output = preg_replace('/ERROR:(.+)/', '<span class="error">ERROR:$1</span>', $output);
    $output = preg_replace('/⚠(.+)/', '<span class="warning">⚠$1</span>', $output);

    echo $output;

    echo "\n\n";
    if ($returnCode === 0) {
        echo "<div class='success'>✓ Script completado exitosamente (código: {$returnCode})</div>";
    } else {
        echo "<div class='error'>✗ Script terminó con errores (código: {$returnCode})</div>";
    }

    echo "</div>";

    echo "<br><a href='?' class='btn'>Volver</a>";
    echo " <a href='../empleos.php' class='btn' style='background:#10b981;'>Ver Empleos</a>";

} else {
    echo "<p>Esta página ejecuta el importador de Telegram directamente.</p>";
    echo "<form method='POST'>";
    echo "<button type='submit' name='run' class='btn'>▶ Ejecutar Importación Telegram</button>";
    echo "</form>";

    echo "<br><br>";
    echo "<p><strong>Nota:</strong> Esta es una página de prueba SIN autenticación. Elimínala después de verificar que funciona.</p>";

    // Mostrar estado actual
    require_once __DIR__ . '/../includes/db.php';
    $pdo = db();
    $count = $pdo->query("SELECT COUNT(*) FROM job_listings WHERE import_source LIKE 'Telegram_%'")->fetchColumn();
    echo "<p>Empleos de Telegram en DB actualmente: <strong>{$count}</strong></p>";
}

echo "</body></html>";
