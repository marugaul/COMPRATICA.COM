<?php
/**
 * RESET REPOSITORY - Fuerza re-clone del repositorio
 * Usar SOLO si el git pull no funciona
 */

$secret = 'RESET2024';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    die('Acceso denegado');
}

echo "<pre>";
echo "==============================================\n";
echo "RESET REPOSITORY\n";
echo "==============================================\n\n";

$repo_path = '/home/comprati/compratica_repo';
$backup_path = '/home/comprati/compratica_repo_backup_' . date('Ymd_His');
$public_path = '/home/comprati/public_html';

// Paso 1: Hacer backup del repo actual
echo "[1/4] Haciendo backup del repositorio actual...\n";
if (is_dir($repo_path)) {
    $cmd = "mv $repo_path $backup_path 2>&1";
    $output = shell_exec($cmd);
    echo "  ✓ Backup creado en: $backup_path\n";
    echo "  " . $output . "\n";
} else {
    echo "  ⚠️  No existe repositorio anterior\n";
}

// Paso 2: Clonar repositorio fresco
echo "\n[2/4] Clonando repositorio fresco desde GitHub...\n";
$clone_cmd = "cd /home/comprati && git clone https://github.com/marugaul/COMPRATICA.COM.git compratica_repo 2>&1";
$output = shell_exec($clone_cmd);
echo "  " . $output . "\n";

if (!is_dir($repo_path . '/.git')) {
    echo "  ❌ Error: No se pudo clonar el repositorio\n";
    echo "  Restaurando backup...\n";
    shell_exec("mv $backup_path $repo_path 2>&1");
    die("Abortado");
}

echo "  ✓ Repositorio clonado exitosamente\n";

// Paso 3: Verificar último commit
echo "\n[3/4] Verificando último commit...\n";
$commit = shell_exec("cd $repo_path && git log --oneline -1 2>&1");
echo "  Commit actual: " . $commit . "\n";

// Paso 4: Sincronizar a public_html
echo "\n[4/4] Sincronizando a public_html...\n";
$rsync = "rsync -av --delete " .
    "--exclude='.git' " .
    "--exclude='.gitignore' " .
    "--exclude='data.sqlite' " .
    "--exclude='sessions/' " .
    "--exclude='logs/' " .
    "--exclude='php_error.log' " .
    "--exclude='includes/config.local.php' " .
    "$repo_path/ $public_path/ 2>&1";

$output = shell_exec($rsync);
echo "  " . substr($output, 0, 500) . "\n";
echo "  ✓ Sincronización completada\n";

// Verificar archivos clave
echo "\n[5/5] Verificación de archivos clave...\n";
$files = [
    "$public_path/uber/migrate_uber_integration.php",
    "$public_path/uber/UberDirectAPI.php",
    "$public_path/checkout.php"
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "  ✓ " . basename($file) . " existe\n";
    } else {
        echo "  ❌ " . basename($file) . " NO EXISTE\n";
    }
}

echo "\n==============================================\n";
echo "✅ RESET COMPLETADO\n";
echo "==============================================\n";
echo "\nEl backup del repo viejo está en:\n$backup_path\n";
echo "Puedes borrarlo después de verificar que todo funciona.\n";
echo "</pre>";
?>
