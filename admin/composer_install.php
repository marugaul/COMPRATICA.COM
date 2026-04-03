<?php
// Instalar dependencias Composer en producción - BORRAR después de usar
$secret = 'deploy_' . md5('compratica_deploy_2024');
if (($_GET['token'] ?? '') !== $secret) { http_response_code(403); die('Acceso denegado'); }

set_time_limit(300);
$repoDir = '/home/comprati/public_html';

echo "<pre>\n";
echo "=== Composer Install - " . date('Y-m-d H:i:s') . " ===\n\n";

$composerPhar = $repoDir . '/composer.phar';

// Paso 1: Descargar composer.phar si no existe
if (!file_exists($composerPhar)) {
    echo "Descargando composer.phar...\n";
    $composerData = file_get_contents('https://getcomposer.org/composer-stable.phar');
    if (!$composerData) {
        // Intentar con curl
        $ch = curl_init('https://getcomposer.org/composer-stable.phar');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $composerData = curl_exec($ch);
        curl_close($ch);
    }
    if ($composerData) {
        file_put_contents($composerPhar, $composerData);
        echo "composer.phar descargado (" . round(strlen($composerData)/1024) . " KB)\n\n";
    } else {
        echo "ERROR: No se pudo descargar composer.phar\n";
        echo "</pre>";
        exit;
    }
} else {
    echo "composer.phar ya existe\n\n";
}

// Paso 2: Verificar PHP CLI
echo "PHP CLI: " . shell_exec('which php 2>&1') . "\n";
echo "PHP version: " . shell_exec('php -r "echo PHP_VERSION;" 2>&1') . "\n\n";

// Paso 3: Correr composer install
echo "--- composer install --no-dev --no-interaction ---\n";
$cmd = "cd {$repoDir} && php composer.phar install --no-dev --no-interaction --no-progress --prefer-dist 2>&1";
$output = shell_exec($cmd);
echo htmlspecialchars($output ?? 'Sin output') . "\n";

// Paso 4: Verificar resultado
echo "\n--- Verificación ---\n";
$autoload = $repoDir . '/vendor/autoload.php';
echo "vendor/autoload.php: " . (file_exists($autoload) ? "SÍ ✓" : "NO ✗") . "\n";
if (file_exists($autoload)) {
    require $autoload;
    echo "PhpSpreadsheet: " . (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet') ? "SÍ ✓" : "NO ✗") . "\n";
    // Limpiar composer.phar
    @unlink($composerPhar);
    echo "\ncomposer.phar eliminado.\n";
    echo "\n✓ Todo listo. Puedes borrar este archivo.\n";
}

echo "</pre>";
