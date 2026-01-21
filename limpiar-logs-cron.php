#!/usr/bin/php
<?php
/**
 * ============================================
 * LIMPIADOR DE LOGS MYSQL - Versión CRON
 * ============================================
 * Este script elimina todos los logs antiguos
 * generados por mysql-auto-executor.sh
 *
 * USO EN CRON (ejecutar UNA sola vez):
 * /usr/bin/php /home/comprati/public_html/limpiar-logs-cron.php
 * ============================================
 */

$logsDir = __DIR__ . '/mysql-logs';

echo "========================================\n";
echo "🧹 Limpiador de Logs MySQL\n";
echo "========================================\n\n";

// Verificar que el directorio existe
if (!is_dir($logsDir)) {
    echo "❌ Error: El directorio mysql-logs no existe.\n";
    exit(1);
}

// Buscar todos los archivos .log excepto ultimo-ejecutado.log
$archivos = glob($logsDir . '/*.log');

if ($archivos === false) {
    echo "❌ Error: No se pudo leer el directorio.\n";
    exit(1);
}

// Filtrar para excluir ultimo-ejecutado.log
$archivosParaEliminar = array_filter($archivos, function($archivo) {
    return basename($archivo) !== 'ultimo-ejecutado.log';
});

$totalLogs = count($archivosParaEliminar);

if ($totalLogs === 0) {
    echo "✅ No hay logs antiguos para eliminar\n\n";
    exit(0);
}

echo "📊 Logs encontrados: $totalLogs\n\n";
echo "🗑️  Eliminando logs antiguos...\n\n";

$logsEliminados = 0;
$errores = 0;

foreach ($archivosParaEliminar as $archivo) {
    $nombreArchivo = basename($archivo);

    if (unlink($archivo)) {
        $logsEliminados++;
        echo "   ✓ Eliminado: $nombreArchivo\n";
    } else {
        $errores++;
        echo "   ✗ Error al eliminar: $nombreArchivo\n";
    }
}

echo "\n========================================\n";
echo "✅ Limpieza completada\n";
echo "========================================\n";
echo "Logs eliminados: $logsEliminados\n";
echo "Errores: $errores\n";

// Calcular espacio del directorio
$espacioBytes = 0;
$archivosRestantes = glob($logsDir . '/*');
foreach ($archivosRestantes as $archivo) {
    if (is_file($archivo)) {
        $espacioBytes += filesize($archivo);
    }
}

$espacioKB = round($espacioBytes / 1024, 2);
$espacioMB = round($espacioBytes / (1024 * 1024), 2);
$espacioFormateado = $espacioMB > 1 ? $espacioMB . ' MB' : $espacioKB . ' KB';

echo "Espacio actual en mysql-logs/: $espacioFormateado\n";
echo "========================================\n\n";

exit(0);
