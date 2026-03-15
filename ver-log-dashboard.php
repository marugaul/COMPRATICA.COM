<?php
/**
 * Visor de log del dashboard
 * URL: compratica.com/ver-log-dashboard.php
 */

$logFile = __DIR__ . '/logs/dashboard_debug.log';

header('Content-Type: text/plain; charset=utf-8');

echo "=== LOG DEL DASHBOARD DE EMPRENDEDORAS ===\n";
echo "Archivo: /logs/dashboard_debug.log\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 80) . "\n\n";

if (file_exists($logFile)) {
    $lines = file($logFile);
    $totalLines = count($lines);

    // Mostrar últimas 200 líneas
    $linesToShow = 200;
    $start = max(0, $totalLines - $linesToShow);

    echo "Mostrando últimas $linesToShow líneas (total: $totalLines)\n";
    echo str_repeat("-", 80) . "\n\n";

    for ($i = $start; $i < $totalLines; $i++) {
        echo $lines[$i];
    }

} else {
    echo "❌ El archivo de log no existe todavía.\n";
    echo "Se creará automáticamente cuando accedas a emprendedoras-dashboard.php\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "Para limpiar el log: elimina el archivo /logs/dashboard_debug.log\n";
?>
