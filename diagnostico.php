<?php
/**
 * DIAGNÓSTICO - Ver qué puede hacer PHP en el servidor
 */

$secret = 'DIAG2024';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    die('Acceso denegado');
}

echo "<pre>";
echo "==============================================\n";
echo "DIAGNÓSTICO DEL SERVIDOR\n";
echo "==============================================\n\n";

// 1. Verificar funciones deshabilitadas
echo "[1] Funciones PHP disponibles:\n";
$disabled = ini_get('disable_functions');
echo "Funciones deshabilitadas: " . ($disabled ?: 'Ninguna') . "\n\n";

$functions = ['shell_exec', 'exec', 'system', 'passthru', 'proc_open', 'popen'];
foreach ($functions as $func) {
    $available = function_exists($func) && !in_array($func, explode(',', $disabled));
    echo "  " . ($available ? '✓' : '❌') . " $func\n";
}

// 2. Probar shell_exec simple
echo "\n[2] Prueba de shell_exec:\n";
if (function_exists('shell_exec')) {
    $test = shell_exec('echo "test" 2>&1');
    echo "  Resultado: " . var_export($test, true) . "\n";
} else {
    echo "  ❌ shell_exec no disponible\n";
}

// 3. Verificar permisos
echo "\n[3] Verificar permisos de directorios:\n";
$dirs = [
    '/home/comprati',
    '/home/comprati/compratica_repo',
    '/home/comprati/public_html'
];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        $writable = is_writable($dir);
        echo "  " . ($writable ? '✓' : '❌') . " $dir - Permisos: $perms " . ($writable ? '(escribible)' : '(solo lectura)') . "\n";
    } else {
        echo "  ⚠️  $dir - No existe\n";
    }
}

// 4. Ver si existe el repo
echo "\n[4] Estado del repositorio:\n";
$repo_path = '/home/comprati/compratica_repo';
if (is_dir($repo_path . '/.git')) {
    echo "  ✓ Repositorio existe\n";

    // Intentar leer el HEAD
    $head_file = $repo_path . '/.git/HEAD';
    if (file_exists($head_file)) {
        $head = file_get_contents($head_file);
        echo "  HEAD: " . trim($head) . "\n";
    }

    // Intentar leer último commit
    $log_file = $repo_path . '/.git/logs/HEAD';
    if (file_exists($log_file)) {
        $logs = file($log_file);
        $last_log = end($logs);
        echo "  Último log: " . substr($last_log, 0, 100) . "...\n";
    }
} else {
    echo "  ❌ Repositorio no existe\n";
}

// 5. Probar exec con git
echo "\n[5] Probar git status:\n";
if (function_exists('exec')) {
    $output = [];
    $return = 0;
    exec("cd $repo_path && git status 2>&1", $output, $return);
    echo "  Return code: $return\n";
    echo "  Output: " . implode("\n  ", array_slice($output, 0, 5)) . "\n";
} else {
    echo "  ❌ exec no disponible\n";
}

// 6. Ver archivos en public_html/uber
echo "\n[6] Archivos en public_html/uber:\n";
$uber_dir = '/home/comprati/public_html/uber';
if (is_dir($uber_dir)) {
    $files = scandir($uber_dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $size = filesize("$uber_dir/$file");
            $mtime = filemtime("$uber_dir/$file");
            echo "  - $file (" . number_format($size) . " bytes, " . date('Y-m-d H:i:s', $mtime) . ")\n";
        }
    }
} else {
    echo "  ❌ Directorio uber no existe\n";
}

echo "\n==============================================\n";
echo "</pre>";
?>
