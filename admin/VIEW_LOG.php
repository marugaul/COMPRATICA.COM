<?php
/**
 * Visor del Log de Campañas Programadas
 * Solo lee y muestra el log, no ejecuta nada
 */

$logFile = '/home/comprati/CampanasProgramadas';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Log Campañas Programadas</title>";
echo "<style>
body{font-family:Arial;padding:40px;background:#f5f5f5;max-width:1200px;margin:0 auto;}
pre{background:#1e293b;color:#f1f5f9;padding:20px;border-radius:5px;overflow:auto;font-size:13px;line-height:1.6;max-height:80vh;}
.card{background:white;padding:25px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);margin:20px 0;}
h1{color:#dc2626;margin:0 0 10px 0;}
.btn{display:inline-block;padding:12px 24px;background:#0891b2;color:white;text-decoration:none;border-radius:5px;margin:10px 5px;}
.btn:hover{background:#0e7490;}
.btn-danger{background:#dc2626;}
.btn-danger:hover{background:#b91c1c;}
.btn-success{background:#16a34a;}
.btn-success:hover{background:#15803d;}
.info{background:#eff6ff;border-left:4px solid #0891b2;padding:15px;margin:15px 0;border-radius:4px;}
.success{background:#f0fdf4;border-left:4px solid #16a34a;padding:15px;margin:15px 0;border-radius:4px;}
.warning{background:#fef3c7;border-left:4px solid #f59e0b;padding:15px;margin:15px 0;border-radius:4px;}
.error{background:#fee;border-left:4px solid #dc2626;padding:15px;margin:15px 0;border-radius:4px;}
</style>
<meta http-equiv='refresh' content='30'>
</head><body>";

echo "<div class='card'>";
echo "<h1>📄 Log de Campañas Programadas</h1>";
echo "<p><strong>Archivo:</strong> <code>{$logFile}</code></p>";
echo "<p><strong>Hora actual (UTC):</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Hora Costa Rica (UTC-6):</strong> " . date('Y-m-d H:i:s', strtotime('-6 hours')) . "</p>";

if (file_exists($logFile)) {
    echo "<div class='success'>✓ El archivo de log existe y se está actualizando</div>";
} else {
    echo "<div class='error'>⚠️ El archivo de log NO existe. El cron aún no ha ejecutado el procesador.</div>";
}

echo "<div class='info'>";
echo "📌 <strong>Esta página se recarga automáticamente cada 30 segundos</strong><br>";
echo "🔄 Última actualización: " . date('H:i:s');
echo "</div>";
echo "</div>";

// Botones de acción
echo "<div class='card'>";
echo "<p style='text-align:center;'>";
echo "<a href='SEND_SCHEDULED_NOW.php' class='btn btn-success'>▶️ Ejecutar Procesador AHORA</a> ";
echo "<a href='VIEW_LOG.php' class='btn'>🔄 Recargar Log</a> ";
echo "<a href='email_marketing.php?page=campaigns' class='btn'>📧 Ver Campañas</a>";
echo "</p>";
echo "</div>";

// Mostrar contenido del log
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $logLines = explode("\n", $logContent);

    echo "<div class='card'>";
    echo "<h2>📋 Contenido del Log</h2>";

    // Mostrar últimas 300 líneas
    $lastLines = array_slice($logLines, -300);

    echo "<p><em>Mostrando últimas 300 líneas (de " . count($logLines) . " totales)</em></p>";
    echo "<p><strong>Tamaño del archivo:</strong> " . number_format(filesize($logFile)) . " bytes</p>";

    echo "<pre>" . htmlspecialchars(implode("\n", $lastLines)) . "</pre>";
    echo "</div>";

    // Botón para limpiar log
    if (isset($_GET['clear_log']) && $_GET['clear_log'] === 'confirm') {
        file_put_contents($logFile, '');
        echo "<div class='success'>✓ Log limpiado exitosamente. <a href='VIEW_LOG.php'>Recargar</a></div>";
    } else {
        echo "<div class='card'>";
        echo "<p style='text-align:center;'>";
        echo "<a href='VIEW_LOG.php?clear_log=confirm' class='btn btn-danger' onclick='return confirm(\"¿Está seguro de limpiar el log?\")'>🗑️ Limpiar Log</a>";
        echo "</p>";
        echo "</div>";
    }
} else {
    echo "<div class='card'>";
    echo "<div class='warning'>";
    echo "<h3>⚠️ El log aún no existe</h3>";
    echo "<p>Esto significa que el cron job aún no ha ejecutado el procesador de campañas programadas.</p>";
    echo "<p><strong>Opciones:</strong></p>";
    echo "<ol>";
    echo "<li><strong>Ejecutar manualmente:</strong> Haz clic en \"Ejecutar Procesador AHORA\" arriba</li>";
    echo "<li><strong>Verificar cron:</strong> Asegúrate de haber configurado el cron job según las instrucciones</li>";
    echo "<li><strong>Esperar:</strong> El cron se ejecuta cada minuto, espera 1-2 minutos y recarga esta página</li>";
    echo "</ol>";
    echo "</div>";
    echo "</div>";

    echo "<div class='card'>";
    echo "<h3>📖 Instrucciones para Configurar Cron</h3>";
    echo "<p>Agrega esta línea a tu crontab:</p>";
    echo "<pre style='background:#1e293b;color:#10b981;'>* * * * * cd /home/comprati/public_html/admin && php process_scheduled_campaigns.php</pre>";
    echo "<p><a href='SETUP_CRON.php' class='btn'>Ver Guía Completa de Configuración</a></p>";
    echo "</div>";
}

echo "</body></html>";
