<?php
/**
 * VERIFICAR ARCHIVOS - Ver qué archivos de Uber existen
 */

$secret = 'CHECK2024';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    die('Acceso denegado');
}

echo "<pre>";
echo "==============================================\n";
echo "VERIFICACIÓN DE ARCHIVOS UBER\n";
echo "==============================================\n\n";

// Directorios a verificar
$repo_uber = '/home/comprati/compratica_repo/uber';
$public_uber = '/home/comprati/public_html/uber';

// Archivos que DEBEN existir
$required_files = [
    'migrate_uber_integration.php',
    'UberDirectAPI.php',
    'ajax_uber_quote.php',
    'test_uber_api.php',
    'test_both_environments.php',
    'test_scopes.php',
    'test_uber_correct.php'
];

// Verificar en repositorio
echo "[1] Archivos en REPOSITORIO ($repo_uber):\n";
if (is_dir($repo_uber)) {
    foreach ($required_files as $file) {
        $path = "$repo_uber/$file";
        if (file_exists($path)) {
            $size = filesize($path);
            $mtime = filemtime($path);
            echo "  ✓ $file (" . number_format($size) . " bytes, " . date('Y-m-d H:i:s', $mtime) . ")\n";
        } else {
            echo "  ❌ $file - NO EXISTE\n";
        }
    }
} else {
    echo "  ❌ Directorio no existe\n";
}

// Verificar en producción
echo "\n[2] Archivos en PRODUCCIÓN ($public_uber):\n";
if (is_dir($public_uber)) {
    foreach ($required_files as $file) {
        $path = "$public_uber/$file";
        if (file_exists($path)) {
            $size = filesize($path);
            $mtime = filemtime($path);
            echo "  ✓ $file (" . number_format($size) . " bytes, " . date('Y-m-d H:i:s', $mtime) . ")\n";
        } else {
            echo "  ❌ $file - NO EXISTE\n";
        }
    }

    // Listar TODOS los archivos que hay
    echo "\n  Archivos adicionales en uber:\n";
    $files = scandir($public_uber);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && !in_array($file, $required_files)) {
            $path = "$public_uber/$file";
            if (is_file($path)) {
                $size = filesize($path);
                echo "  - $file (" . number_format($size) . " bytes)\n";
            }
        }
    }
} else {
    echo "  ❌ Directorio no existe\n";
}

// Verificar checkout.php
echo "\n[3] Archivo checkout.php:\n";
$checkout_repo = '/home/comprati/compratica_repo/checkout.php';
$checkout_public = '/home/comprati/public_html/checkout.php';

if (file_exists($checkout_repo)) {
    $mtime = filemtime($checkout_repo);
    echo "  Repositorio: " . date('Y-m-d H:i:s', $mtime) . " (" . number_format(filesize($checkout_repo)) . " bytes)\n";
} else {
    echo "  ❌ No existe en repositorio\n";
}

if (file_exists($checkout_public)) {
    $mtime = filemtime($checkout_public);
    echo "  Producción:  " . date('Y-m-d H:i:s', $mtime) . " (" . number_format(filesize($checkout_public)) . " bytes)\n";

    // Buscar si tiene el código de geolocalización
    $content = file_get_contents($checkout_public);
    if (strpos($content, 'geolocate-btn') !== false) {
        echo "  ✓ Contiene código de geolocalización\n";
    } else {
        echo "  ❌ NO contiene código de geolocalización\n";
    }
} else {
    echo "  ❌ No existe en producción\n";
}

// Estado del rsync
echo "\n[4] Comparación de fechas:\n";
$repo_mtime = is_dir($repo_uber) ? filemtime($repo_uber) : 0;
$public_mtime = is_dir($public_uber) ? filemtime($public_uber) : 0;

echo "  Última modificación repo:  " . date('Y-m-d H:i:s', $repo_mtime) . "\n";
echo "  Última modificación public: " . date('Y-m-d H:i:s', $public_mtime) . "\n";

if ($public_mtime < $repo_mtime) {
    $diff = $repo_mtime - $public_mtime;
    echo "  ⚠️  Public está " . round($diff / 60) . " minutos desactualizado\n";
    echo "  👉 El cron NECESITA ejecutarse para sincronizar\n";
} elseif ($public_mtime >= $repo_mtime) {
    echo "  ✓ Public está actualizado\n";
} else {
    echo "  ❓ No se puede determinar\n";
}

echo "\n==============================================\n";
echo "SIGUIENTE PASO:\n";
echo "==============================================\n";

if (file_exists('/home/comprati/public_html/uber/migrate_uber_integration.php')) {
    echo "✅ Los archivos YA están en producción\n";
    echo "👉 Puedes ejecutar la migración:\n";
    echo "   https://compratica.com/uber/migrate_uber_integration.php\n";
} else {
    echo "⚠️  Los archivos NO están sincronizados\n";
    echo "👉 Espera 1 minuto para que el cron sincronice\n";
    echo "👉 Luego vuelve a ejecutar este script\n";
}

echo "</pre>";
?>
