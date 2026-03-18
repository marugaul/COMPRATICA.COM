<?php
/**
 * public_html/cron_import.php
 * ============================================================================
 * Endpoint web para ejecutar importación desde cron de cPanel
 * ============================================================================
 *
 * Si tu cron muestra "Content-type: text/html", usa este archivo.
 *
 * Configuración en cPanel → Cron Jobs:
 *   wget -q -O /dev/null https://tudominio.com/cron_import.php?key=CLAVE_SECRETA
 *
 * O con curl:
 *   curl -s https://tudominio.com/cron_import.php?key=CLAVE_SECRETA
 */

// ============================================================================
// SEGURIDAD: Cambiar esta clave por una aleatoria
// ============================================================================
define('CRON_SECRET_KEY', 'compratica_cron_2024_' . md5('change_this_secret'));

// Verificar clave de seguridad
$providedKey = $_GET['key'] ?? '';
if ($providedKey !== CRON_SECRET_KEY) {
    http_response_code(403);
    die("Access denied. Invalid key.\n");
}

// Solo permitir GET/POST
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    http_response_code(405);
    die("Method not allowed\n");
}

// Deshabilitar límites de tiempo
set_time_limit(600); // 10 minutos max
ini_set('memory_limit', '256M');

// Headers para texto plano
header('Content-Type: text/plain; charset=utf-8');

// Cambiar al directorio raíz del proyecto
chdir(dirname(__DIR__));

// Incluir el script principal
$scriptPath = __DIR__ . '/../scripts/cron_import_all.php';

if (!file_exists($scriptPath)) {
    http_response_code(500);
    die("ERROR: Script not found: $scriptPath\n");
}

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║        COMPRATICA - IMPORTACIÓN VÍA WEB CRON              ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "Script: $scriptPath\n";
echo "\n";

// Capturar output del script
ob_start();
$startTime = microtime(true);

try {
    // Ejecutar el script
    include $scriptPath;
    $exitCode = 0;
} catch (Exception $e) {
    echo "\n";
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    $exitCode = 1;
}

$duration = round(microtime(true) - $startTime, 2);
$output = ob_get_clean();

// Mostrar output
echo $output;

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "⏱️  Duración: {$duration}s\n";
echo "🏁 Código de salida: $exitCode\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Guardar en log adicional
$logFile = __DIR__ . '/../logs/web_cron.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$logEntry = sprintf(
    "[%s] Ejecutado vía web cron | Duración: %ss | Exit: %d\n",
    date('Y-m-d H:i:s'),
    $duration,
    $exitCode
);
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

exit($exitCode);
