<?php
require_once __DIR__ . '/../includes/config.php';

// Solo admin
if (($_GET['key'] ?? '') !== ADMIN_PASS_PLAIN) {
    http_response_code(403);
    die('Acceso denegado');
}

header('Content-Type: text/plain; charset=utf-8');

if (!function_exists('opcache_reset')) {
    echo "OPcache no está activo en este servidor.\n";
    exit;
}

if (opcache_reset()) {
    echo "✅ OPcache limpiado correctamente.\n";
    $status = opcache_get_status(false);
    echo "Scripts en caché antes de limpiar: " . ($status['opcache_statistics']['num_cached_scripts'] ?? 'N/A') . "\n";
} else {
    echo "❌ No se pudo limpiar OPcache.\n";
}
