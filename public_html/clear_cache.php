<?php
// Limpiar cache de opcodes
echo "<h2>Limpieza de Cache PHP</h2>\n\n";

// Limpiar OPcache
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "<p>✓ OPcache reseteado exitosamente</p>\n";
    } else {
        echo "<p>❌ No se pudo resetear OPcache</p>\n";
    }
} else {
    echo "<p>⚠️  OPcache no está disponible</p>\n";
}

// Mostrar configuración de OPcache
echo "<h3>Configuración OPcache:</h3>\n";
echo "<pre>";
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    echo "Habilitado: " . ($status['opcache_enabled'] ? 'SI' : 'NO') . "\n";
    echo "Cache lleno: " . ($status['cache_full'] ? 'SI' : 'NO') . "\n";
    echo "Archivos cacheados: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
} else {
    echo "OPcache no disponible\n";
}
echo "</pre>";

// Invalidar archivos específicos
$filesToInvalidate = [
    __DIR__ . '/../includes/user_auth.php',
    __DIR__ . '/../includes/affiliate_auth.php',
    __DIR__ . '/../includes/config.php'
];

echo "<h3>Invalidando archivos específicos:</h3>\n";
foreach ($filesToInvalidate as $file) {
    $realPath = realpath($file);
    if ($realPath && function_exists('opcache_invalidate')) {
        if (opcache_invalidate($realPath, true)) {
            echo "<p>✓ Invalidado: $realPath</p>\n";
        } else {
            echo "<p>❌ No se pudo invalidar: $realPath</p>\n";
        }
    } else {
        echo "<p>⚠️  Archivo no encontrado o OPcache no disponible: $file</p>\n";
    }
}

echo "<hr>\n";
echo "<p><strong>Cache limpiado. Ahora intenta hacer login nuevamente.</strong></p>\n";
echo "<p><a href='../affiliate/login.php'>Ir al login</a></p>\n";
?>
