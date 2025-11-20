<?php
/**
 * Visor de Logs de Email API
 */

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Email API Logs</title>";
echo "<style>
body{font-family:monospace;padding:20px;background:#1e293b;color:#cbd5e1}
h1{color:#fbbf24}
pre{background:#0f172a;padding:20px;border-radius:8px;overflow-x:auto;border:1px solid #334155;white-space:pre-wrap;word-wrap:break-word}
.btn{display:inline-block;padding:10px 20px;background:#dc2626;color:white;text-decoration:none;border-radius:6px;margin:10px 5px 10px 0}
.btn:hover{background:#b91c1c}
.error-log{background:#7f1d1d;color:#fecaca;padding:15px;border-radius:8px;margin:15px 0}
</style></head><body>";

echo "<h1>📋 Email Marketing API Debug Logs</h1>";

$logFile = __DIR__ . '/../logs/email_api_debug.log';
$errorLogFile = __DIR__ . '/../logs/php_email_api_errors.log';

echo "<p><a href='email_marketing.php?page=smtp-config' class='btn'>← Volver a Config SMTP</a> ";
echo "<a href='?clear=1' class='btn' style='background:#0891b2'>Limpiar Logs</a></p>";

if (isset($_GET['clear'])) {
    @unlink($logFile);
    @unlink($errorLogFile);
    echo "<p style='color:#16a34a'>✓ Logs limpiados</p>";
    echo "<script>setTimeout(function(){ window.location='view_api_logs.php'; }, 1500);</script>";
} else {

    // Mostrar logs de API
    echo "<h2>API Debug Log:</h2>";
    if (!file_exists($logFile)) {
        echo "<p style='color:#fbbf24'>⚠️ No hay archivo de log todavía. Intenta guardar la configuración SMTP primero.</p>";
    } else {
        $logContent = file_get_contents($logFile);
        $lines = explode("\n", $logContent);
        $recentLines = array_slice(array_filter($lines), -100); // Últimas 100 líneas

        echo "<p>Mostrando las últimas " . count($recentLines) . " líneas:</p>";
        echo "<pre>" . htmlspecialchars(implode("\n", $recentLines)) . "</pre>";
        echo "<p><small>Archivo: " . $logFile . "</small></p>";
    }

    // Mostrar errores PHP
    echo "<h2 style='margin-top:40px'>PHP Errors Log:</h2>";
    if (!file_exists($errorLogFile)) {
        echo "<p style='color:#16a34a'>✓ No hay errores de PHP registrados</p>";
    } else {
        $errorContent = file_get_contents($errorLogFile);
        $errorLines = explode("\n", $errorContent);
        $recentErrors = array_slice(array_filter($errorLines), -50);

        echo "<div class='error-log'>";
        echo "<strong>Últimos " . count($recentErrors) . " errores PHP:</strong><br><br>";
        echo "<pre>" . htmlspecialchars(implode("\n", $recentErrors)) . "</pre>";
        echo "</div>";
        echo "<p><small>Archivo: " . $errorLogFile . "</small></p>";
    }
}

echo "</body></html>";
?>
