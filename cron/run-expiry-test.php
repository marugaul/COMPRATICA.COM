<?php
// Script de prueba — eliminar después de confirmar que los emails funcionan
if (($_GET['key'] ?? '') !== 'compratica2026') {
    http_response_code(403);
    die('Acceso denegado');
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== Ejecutando cron de expiración ===\n\n";

// Capturar output del cron
ob_start();
require __DIR__ . '/real-estate-expiry-notify.php';
$output = ob_get_clean();

echo $output;
echo "\n=== Listo ===\n";
