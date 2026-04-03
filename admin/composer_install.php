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
        $token = htmlspecialchars($_GET['token']);
        echo "<a href='?token={$token}&action=download'>Descargar composer.phar</a>";
        echo "</pre>"; exit;
    }
    @unlink($logFile);
    // Escribir script shell temporal para lanzar en background real
    $shScript = $repoDir . '/composer_run.sh';
    file_put_contents($shScript, "#!/bin/sh\ncd {$repoDir}\nphp composer.phar install --no-dev --no-interaction --no-progress --prefer-dist > {$logFile} 2>&1\necho 'DONE' >> {$logFile}\n");
    chmod($shScript, 0755);
    // Intentar nohup primero, luego fallback a exec con &
    $launched = false;
    if (shell_exec('which nohup 2>/dev/null')) {
        exec("nohup {$shScript} > /dev/null 2>&1 &");
        $launched = true;
    } else {
        exec("{$shScript} > /dev/null 2>&1 &");
        $launched = true;
    }
    sleep(2); // dar 2 seg para que arranque
    $token = htmlspecialchars($_GET['token']);
    if (file_exists($logFile) && filesize($logFile) > 0) {
        echo "✓ Composer corriendo. Log inicial:\n";
        echo htmlspecialchars(file_get_contents($logFile)) . "\n";
    } else {
        echo "Proceso lanzado. Si el log está vacío en 10 seg, intenta ?action=sync\n";
    }
    echo "\n<a href='?token={$token}&action=log'>Ver log</a> | ";
    echo "<a href='?token={$token}&action=sync'>Instalar sincrónico (lento)</a> | ";
    echo "<a href='?token={$token}&action=check'>Verificar resultado</a>";
    echo "</pre>"; exit;
}

// --- ACCIÓN: instalar sincrónico (sin background) ---
if ($action === 'sync') {
    if (!file_exists($composerPhar)) {
        echo "ERROR: composer.phar no encontrado\n"; echo "</pre>"; exit;
    }
    set_time_limit(300);
    echo "Corriendo composer install (puede tardar 2-3 min)...\n";
    flush(); ob_flush();
    $output = [];
    exec("cd {$repoDir} && php composer.phar install --no-dev --no-interaction --no-progress --prefer-dist 2>&1", $output, $code);
    echo htmlspecialchars(implode("\n", $output)) . "\n";
    echo "\nCódigo de salida: {$code}\n";
    $token = htmlspecialchars($_GET['token']);
    echo "\n<a href='?token={$token}&action=check'>Verificar resultado</a>";
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
