<?php
/**
 * SCRIPT DE EMERGENCIA: Enviar campañas programadas manualmente
 * Ejecuta el proceso de campañas programadas inmediatamente
 */

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Enviar Campañas Programadas</title>";
echo "<style>
body{font-family:Arial;padding:40px;background:#f5f5f5;max-width:1200px;margin:0 auto;}
.ok{color:green;font-weight:bold;}
.error{color:red;font-weight:bold;}
pre{background:#1e293b;color:#f1f5f9;padding:20px;border-radius:5px;overflow:auto;font-size:13px;line-height:1.6;}
.card{background:white;padding:25px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);margin:20px 0;}
h1{color:#dc2626;margin:0 0 10px 0;}
.btn{display:inline-block;padding:12px 24px;background:#0891b2;color:white;text-decoration:none;border-radius:5px;margin:10px 5px;}
.btn:hover{background:#0e7490;}
.btn-secondary{background:#64748b;}
.log-viewer{margin-top:30px;}
</style></head><body>";

echo "<div class='card'>";
echo "<h1>🚀 Procesador de Campañas Programadas</h1>";
echo "<p><strong>Hora del servidor (UTC):</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Hora Costa Rica (UTC-6):</strong> " . date('Y-m-d H:i:s', strtotime('-6 hours')) . "</p>";
echo "</div>";

echo "<div class='card'>";
echo "<h2>📋 Ejecutando Procesador...</h2>";

// Cargar proceso
ob_start();
include __DIR__ . '/process_scheduled_campaigns.php';
$output = ob_get_clean();

echo "<pre>" . htmlspecialchars($output) . "</pre>";
echo "</div>";

// Mostrar contenido del log
$logFile = '/home/comprati/CampanasProgramadas';
echo "<div class='card log-viewer'>";
echo "<h2>📄 Contenido Completo del Log</h2>";
echo "<p><strong>Archivo:</strong> <code>{$logFile}</code></p>";

if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $logLines = explode("\n", $logContent);

    // Mostrar últimas 200 líneas
    $lastLines = array_slice($logLines, -200);

    echo "<p><em>Mostrando últimas 200 líneas del log</em></p>";
    echo "<pre>" . htmlspecialchars(implode("\n", $lastLines)) . "</pre>";

    echo "<p><strong>Tamaño del archivo:</strong> " . number_format(filesize($logFile)) . " bytes</p>";
    echo "<p><strong>Total de líneas:</strong> " . count($logLines) . "</p>";
} else {
    echo "<p class='error'>⚠️ El archivo de log aún no existe. El procesador creará el archivo en la primera ejecución.</p>";
}
echo "</div>";

echo "<div class='card'>";
echo "<p style='text-align:center;'>";
echo "<a href='email_marketing.php?page=campaigns' class='btn'>📧 Ver Campañas</a> ";
echo "<a href='SEND_SCHEDULED_NOW.php' class='btn btn-secondary'>🔄 Recargar</a>";
echo "</p>";
echo "</div>";

echo "</body></html>";
