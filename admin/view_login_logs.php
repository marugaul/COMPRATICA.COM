<?php
/**
 * Visor de Logs de Login
 */

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Login Logs</title>";
echo "<style>
body{font-family:monospace;padding:20px;background:#1e293b;color:#cbd5e1}
h1{color:#fbbf24}
pre{background:#0f172a;padding:20px;border-radius:8px;overflow-x:auto;border:1px solid #334155}
.btn{display:inline-block;padding:10px 20px;background:#dc2626;color:white;text-decoration:none;border-radius:6px;margin:10px 5px 10px 0}
.btn:hover{background:#b91c1c}
</style></head><body>";

echo "<h1>📋 Login Debug Logs</h1>";

$logFile = __DIR__ . '/../logs/login_debug.log';

if (!file_exists($logFile)) {
    echo "<p style='color:#fbbf24'>⚠️ No hay archivo de log todavía. Intenta hacer login primero.</p>";
    echo "<p><a href='login.php' class='btn'>Ir a Login</a></p>";
} else {
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    $recentLines = array_slice(array_filter($lines), -50); // Últimas 50 líneas

    echo "<p>Mostrando las últimas " . count($recentLines) . " líneas:</p>";
    echo "<p><a href='login.php' class='btn'>Ir a Login</a> <a href='?clear=1' class='btn' style='background:#0891b2'>Limpiar Logs</a></p>";

    if (isset($_GET['clear'])) {
        @unlink($logFile);
        echo "<p style='color:#16a34a'>✓ Logs limpiados</p>";
        echo "<script>setTimeout(function(){ window.location='view_login_logs.php'; }, 2000);</script>";
    } else {
        echo "<pre>" . htmlspecialchars(implode("\n", $recentLines)) . "</pre>";
    }

    echo "<p><small>Archivo: " . $logFile . "</small></p>";
}

echo "<p><a href='dashboard.php' class='btn' style='background:#0891b2'>Volver al Dashboard</a></p>";
echo "</body></html>";
?>
