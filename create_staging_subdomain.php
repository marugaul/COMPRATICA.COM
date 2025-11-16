<?php
/**
 * Script para crear subdominio staging.compratica.com
 * Ejecutar UNA VEZ desde el navegador o cron
 */

// Configuración
$cpanel_user = 'comprati';  // Tu usuario de cPanel
$domain = 'compratica.com'; // Tu dominio principal
$subdomain = 'staging';     // El subdominio a crear
$rootdomain = 'compratica.com';
$dir = '/home/comprati/staging'; // Directorio destino

// Intentar crear el subdominio usando uapi
$command = "uapi --user={$cpanel_user} SubDomain add_subdomain domain={$subdomain} rootdomain={$rootdomain} dir={$dir}";

echo "<h1>Creando subdominio staging.compratica.com</h1>\n";
echo "<p>Comando: " . htmlspecialchars($command) . "</p>\n";
echo "<pre>\n";

$output = [];
$return_var = 0;
exec($command . " 2>&1", $output, $return_var);

echo implode("\n", $output);
echo "</pre>\n";

if ($return_var === 0) {
    echo "<h2 style='color: green;'>✅ Subdominio creado exitosamente!</h2>\n";
    echo "<p>Accede a: <a href='https://staging.compratica.com'>https://staging.compratica.com</a></p>\n";
    echo "<p><strong>IMPORTANTE:</strong> Elimina este archivo inmediatamente por seguridad.</p>\n";
} else {
    echo "<h2 style='color: red;'>❌ Error al crear subdominio</h2>\n";
    echo "<p>Error code: {$return_var}</p>\n";
    echo "<p>Necesitas crear el subdominio manualmente desde cPanel.</p>\n";
}
?>
