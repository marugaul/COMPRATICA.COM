<?php
// Instalar dependencias Composer en producción - BORRAR después de usar
$secret = 'deploy_' . md5('compratica_deploy_2024');
if (($_GET['token'] ?? '') !== $secret) { http_response_code(403); die('Acceso denegado'); }

set_time_limit(30);
$repoDir = '/home/comprati/public_html';
$logFile  = $repoDir . '/composer_install.log';
$composerPhar = $repoDir . '/composer.phar';
$action = $_GET['action'] ?? 'status';

echo "<pre>\n";
echo "=== Composer Install - " . date('Y-m-d H:i:s') . " ===\n\n";

// --- ACCIÓN: lanzar install en background ---
if ($action === 'run') {
    if (!file_exists($composerPhar)) {
        echo "ERROR: composer.phar no encontrado en {$composerPhar}\n";
        echo "Descárgalo primero con ?action=download\n";
        echo "</pre>"; exit;
    }
    @unlink($logFile);
    $cmd = "cd {$repoDir} && php composer.phar install --no-dev --no-interaction --no-progress --prefer-dist > {$logFile} 2>&1 &";
    shell_exec($cmd);
    echo "Composer corriendo en background...\n";
    echo "Espera 60-90 segundos y recarga con ?action=log\n\n";
    $token = htmlspecialchars($_GET['token']);
    echo '<a href="?token=' . $token . '&action=log">Ver log</a> | ';
    echo '<a href="?token=' . $token . '&action=check">Verificar resultado</a>';
    echo "</pre>"; exit;
}

// --- ACCIÓN: descargar composer.phar ---
if ($action === 'download') {
    if (file_exists($composerPhar)) {
        echo "composer.phar ya existe (" . round(filesize($composerPhar)/1024) . " KB)\n";
    } else {
        echo "Descargando composer.phar...\n";
        $ch = curl_init('https://getcomposer.org/composer-stable.phar');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $data = curl_exec($ch);
        curl_close($ch);
        if ($data) {
            file_put_contents($composerPhar, $data);
            echo "Descargado (" . round(strlen($data)/1024) . " KB) ✓\n";
        } else {
            echo "ERROR: no se pudo descargar\n";
            echo "</pre>"; exit;
        }
    }
    $token = htmlspecialchars($_GET['token']);
    echo "\n<a href='?token={$token}&action=run'>▶ Correr composer install</a>";
    echo "</pre>"; exit;
}

// --- ACCIÓN: ver log ---
if ($action === 'log') {
    echo "--- {$logFile} ---\n";
    echo htmlspecialchars(file_exists($logFile) ? file_get_contents($logFile) : '(vacío, puede que aún esté corriendo)') . "\n";
    $token = htmlspecialchars($_GET['token']);
    echo "\n<a href='?token={$token}&action=log'>Recargar</a> | ";
    echo "<a href='?token={$token}&action=check'>Verificar resultado</a>";
    echo "</pre>"; exit;
}

// --- ACCIÓN: verificar resultado ---
if ($action === 'check') {
    $autoload = $repoDir . '/vendor/autoload.php';
    echo "vendor/autoload.php: " . (file_exists($autoload) ? "SÍ ✓" : "NO ✗") . "\n";
    if (file_exists($autoload)) {
        require $autoload;
        echo "PhpSpreadsheet: " . (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet') ? "SÍ ✓ LISTO!" : "NO ✗") . "\n";
        @unlink($composerPhar);
        @unlink($logFile);
        echo "\nArchivos temporales eliminados. Puedes borrar composer_install.php\n";
    }
    echo "</pre>"; exit;
}

// --- DEFAULT: mostrar estado y opciones ---
$token = htmlspecialchars($_GET['token']);
echo "composer.phar: " . (file_exists($composerPhar) ? "SÍ (" . round(filesize($composerPhar)/1024) . " KB)" : "NO") . "\n";
echo "vendor/autoload.php: " . (file_exists($repoDir.'/vendor/autoload.php') ? "SÍ ✓" : "NO") . "\n";
echo "log pendiente: " . (file_exists($logFile) ? "SÍ" : "NO") . "\n\n";
echo "Pasos:\n";
echo "1. <a href='?token={$token}&action=download'>Descargar composer.phar</a>\n";
echo "2. <a href='?token={$token}&action=run'>Correr composer install (background)</a>\n";
echo "3. Esperar ~90 segundos\n";
echo "4. <a href='?token={$token}&action=log'>Ver log</a>\n";
echo "5. <a href='?token={$token}&action=check'>Verificar resultado</a>\n";
echo "</pre>";
