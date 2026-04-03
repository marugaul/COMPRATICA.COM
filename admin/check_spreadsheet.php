<?php
// Diagnóstico rápido - BORRAR después de usar
$secret = 'deploy_' . md5('compratica_deploy_2024');
if (($_GET['token'] ?? '') !== $secret) { http_response_code(403); die('Acceso denegado'); }

echo "<pre>\n";
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
echo "vendor/autoload.php existe: " . (file_exists($vendorAutoload) ? "SÍ" : "NO") . "\n";

if (file_exists($vendorAutoload)) {
    require $vendorAutoload;
    echo "PhpSpreadsheet instalado: " . (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet') ? "SÍ ✓" : "NO ✗") . "\n";
} else {
    echo "vendor/ no existe. Intentando composer install...\n";
    $repoDir = '/home/comprati/public_html';
    $output = shell_exec("cd {$repoDir} && composer install --no-dev --no-interaction 2>&1");
    echo $output . "\n";
}

// Intentar correr composer si falta
echo "\nPHP version: " . PHP_VERSION . "\n";
echo "composer disponible: " . (shell_exec('which composer 2>&1') ?: 'NO') . "\n";
echo "</pre>";
