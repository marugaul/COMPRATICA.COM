<?php
/**
 * DEPLOY MANUAL - Sincroniza desde GitHub a producción
 * Acceder: https://compratica.com/deploy_manual.php?key=DEPLOY123
 */

$secret_key = 'DEPLOY123'; // Cambia esto por seguridad

if (!isset($_GET['key']) || $_GET['key'] !== $secret_key) {
    die('❌ Acceso denegado');
}

echo "<pre>";
echo "==============================================\n";
echo "DEPLOY MANUAL - COMPRATICA.COM\n";
echo "==============================================\n\n";

$repo_path = '/home/comprati/compratica_repo';
$public_path = '/home/comprati/public_html';

// Paso 1: Verificar que existe el repositorio
echo "[1/3] Verificando repositorio...\n";
if (!is_dir($repo_path . '/.git')) {
    echo "  ❌ Repositorio no existe en $repo_path\n";
    echo "  Por favor, configura el cron para que clone el repositorio primero.\n";
    exit(1);
} else {
    echo "  ✓ Repositorio existe\n";
}

// Paso 2: Git pull
echo "\n[2/3] Ejecutando git pull...\n";
$pull_cmd = "cd $repo_path && git pull origin main 2>&1";
$output = shell_exec($pull_cmd);
echo "  " . $output . "\n";

if (strpos($output, 'Already up to date') !== false) {
    echo "  ℹ️  Ya está actualizado\n";
} elseif (strpos($output, 'error') !== false || strpos($output, 'fatal') !== false) {
    echo "  ❌ Error en git pull\n";
    exit(1);
} else {
    echo "  ✓ Pull exitoso\n";
}

// Paso 3: Rsync
echo "\n[3/3] Sincronizando archivos a public_html...\n";
$rsync_cmd = "rsync -av --delete " .
    "--exclude='.git' " .
    "--exclude='.gitignore' " .
    "--exclude='data.sqlite' " .
    "--exclude='sessions/' " .
    "--exclude='logs/' " .
    "--exclude='php_error.log' " .
    "--exclude='includes/config.local.php' " .
    "--exclude='test_oauth.php' " .
    "--exclude='debug_oauth.php' " .
    "--exclude='fix_oauth_complete.sh' " .
    "$repo_path/ $public_path/ 2>&1";

$output = shell_exec($rsync_cmd);
echo "  " . substr($output, 0, 1000) . "\n"; // Mostrar primeras 1000 caracteres
echo "  ✓ Rsync completado\n";

// Paso 4: Verificar archivos actualizados
echo "\n[4/4] Verificación...\n";
$git_log = shell_exec("cd $repo_path && git log --oneline -3 2>&1");
echo "  Últimos commits:\n";
echo "  " . $git_log . "\n";

// Verificar que existen los archivos nuevos
$files_to_check = [
    "$public_path/uber/migrate_uber_integration.php",
    "$public_path/uber/test_uber_api.php",
    "$public_path/checkout.php"
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $mtime = filemtime($file);
        echo "  ✓ " . basename($file) . " - Modificado: " . date('Y-m-d H:i:s', $mtime) . "\n";
    } else {
        echo "  ❌ " . basename($file) . " - NO EXISTE\n";
    }
}

echo "\n==============================================\n";
echo "✅ DEPLOY COMPLETADO\n";
echo "==============================================\n";
echo "</pre>";
?>
