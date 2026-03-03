<?php
// Test simple para verificar que el logging funciona
$logFile = __DIR__ . '/public_html/logs/auth_debug.log';

echo "Intentando escribir en: $logFile\n";

$logMsg = '[' . date('Y-m-d H:i:s') . '] TEST: Verificando escritura de log' . PHP_EOL;
$result = @file_put_contents($logFile, $logMsg, FILE_APPEND);

if ($result !== false) {
    echo "✓ Log escrito exitosamente ($result bytes)\n";
    echo "Contenido:\n";
    echo file_get_contents($logFile);
} else {
    echo "❌ Error al escribir log\n";
    echo "Error: " . error_get_last()['message'] ?? 'Desconocido' . "\n";
}

// Verificar permisos
echo "\nPermisos del directorio:\n";
echo "Dir: " . dirname($logFile) . "\n";
if (is_dir(dirname($logFile))) {
    echo "✓ Directorio existe\n";
    echo "Writable: " . (is_writable(dirname($logFile)) ? 'SI' : 'NO') . "\n";
} else {
    echo "❌ Directorio NO existe\n";
}
?>
