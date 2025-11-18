<?php
/**
 * VER COMMIT - Muestra qué commit tiene el servidor
 */

$secret = 'COMMIT2024';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    die('Acceso denegado');
}

echo "<pre>";
echo "==============================================\n";
echo "ESTADO DEL REPOSITORIO\n";
echo "==============================================\n\n";

$repo_path = '/home/comprati/compratica_repo';

// 1. Leer archivo .git/HEAD
echo "[1] HEAD del repositorio:\n";
$head_file = $repo_path . '/.git/HEAD';
if (file_exists($head_file)) {
    $head = file_get_contents($head_file);
    echo "  " . trim($head) . "\n\n";
} else {
    echo "  ❌ No existe\n\n";
}

// 2. Leer último commit del log
echo "[2] Último commit en el log:\n";
$log_file = $repo_path . '/.git/logs/HEAD';
if (file_exists($log_file)) {
    $logs = file($log_file);
    $last_log = end($logs);

    // Extraer el commit hash
    preg_match('/^(\w+)\s+(\w+)/', $last_log, $matches);
    if (isset($matches[2])) {
        $commit_hash = $matches[2];
        echo "  Commit: " . substr($commit_hash, 0, 7) . "\n";
        echo "  Full:   " . $commit_hash . "\n";
    }
    echo "  Log completo: " . trim($last_log) . "\n\n";
} else {
    echo "  ❌ No existe\n\n";
}

// 3. Leer refs/heads/main
echo "[3] Commit en refs/heads/main:\n";
$main_ref = $repo_path . '/.git/refs/heads/main';
if (file_exists($main_ref)) {
    $commit = trim(file_get_contents($main_ref));
    echo "  " . substr($commit, 0, 7) . " (full: " . $commit . ")\n\n";
} else {
    echo "  ❌ No existe\n\n";
}

// 4. Leer refs/remotes/origin/main
echo "[4] Commit en refs/remotes/origin/main:\n";
$origin_main = $repo_path . '/.git/refs/remotes/origin/main';
if (file_exists($origin_main)) {
    $commit = trim(file_get_contents($origin_main));
    echo "  " . substr($commit, 0, 7) . " (full: " . $commit . ")\n\n";
} else {
    echo "  ❌ No existe\n\n";
}

// 5. Tamaño del archivo migrate
echo "[5] Tamaño de migrate_uber_integration.php:\n";
$migrate_repo = $repo_path . '/uber/migrate_uber_integration.php';
$migrate_public = '/home/comprati/public_html/uber/migrate_uber_integration.php';

if (file_exists($migrate_repo)) {
    $size = filesize($migrate_repo);
    echo "  Repo:   " . number_format($size) . " bytes\n";
} else {
    echo "  Repo:   ❌ No existe\n";
}

if (file_exists($migrate_public)) {
    $size = filesize($migrate_public);
    echo "  Public: " . number_format($size) . " bytes\n";
} else {
    echo "  Public: ❌ No existe\n";
}

echo "\n==============================================\n";
echo "COMMITS ESPERADOS:\n";
echo "==============================================\n";
echo "4479d9e = Commit NUEVO (fix migration script) ✅\n";
echo "ecee259 = Commit VIEJO (file verification)   ❌\n";
echo "\n";
echo "Si ves 4479d9e → Todo OK\n";
echo "Si ves ecee259 → Cron no funcionó\n";
echo "</pre>";
?>
