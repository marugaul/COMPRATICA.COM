<?php
// Test de diagnóstico para import_jobs
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

echo "<h1>Diagnóstico de Import Jobs</h1>";
echo "<pre>";

// 1. Verificar sesión
echo "=== SESIÓN ===\n";
echo "is_admin: " . (isset($_SESSION['is_admin']) ? ($_SESSION['is_admin'] ? 'true' : 'false') : 'NO DEFINIDO') . "\n";
echo "admin_user: " . (isset($_SESSION['admin_user']) ? $_SESSION['admin_user'] : 'NO DEFINIDO') . "\n";
echo "\n";

// 2. Verificar base de datos
echo "=== BASE DE DATOS ===\n";
try {
    require_once __DIR__ . '/../includes/db.php';
    $pdo = db();
    echo "✓ Conexión a BD: OK\n";

    // Verificar usuario bot
    $bot = $pdo->query("SELECT id, email, name FROM users WHERE email='bot@compratica.com' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($bot) {
        echo "✓ Usuario bot existe: ID={$bot['id']}, email={$bot['email']}, nombre={$bot['name']}\n";
    } else {
        echo "✗ Usuario bot NO existe\n";
    }

    // Verificar tabla job_import_log
    $logCheck = $pdo->query("SELECT COUNT(*) FROM job_import_log")->fetchColumn();
    echo "✓ Tabla job_import_log existe: {$logCheck} registros\n";

    // Verificar columnas en job_listings
    $cols = $pdo->query("PRAGMA table_info(job_listings)")->fetchAll(PDO::FETCH_ASSOC);
    $hasImportSource = false;
    $hasSourceUrl = false;
    foreach ($cols as $col) {
        if ($col['name'] === 'import_source') $hasImportSource = true;
        if ($col['name'] === 'source_url') $hasSourceUrl = true;
    }
    echo ($hasImportSource ? "✓" : "✗") . " Columna import_source\n";
    echo ($hasSourceUrl ? "✓" : "✗") . " Columna source_url\n";

} catch (Exception $e) {
    echo "✗ Error de BD: " . $e->getMessage() . "\n";
}
echo "\n";

// 3. Verificar archivos
echo "=== ARCHIVOS ===\n";
$files = [
    '/scripts/import_jobs.php',
    '/admin/import_jobs.php',
    '/admin/import_runner.php',
    '/logs/import_jobs.log',
];
foreach ($files as $file) {
    $path = __DIR__ . '/..' . $file;
    if (file_exists($path)) {
        echo "✓ {$file}: " . filesize($path) . " bytes\n";
    } else {
        echo "✗ {$file}: NO EXISTE\n";
    }
}
echo "\n";

// 4. Verificar permisos de logs
echo "=== PERMISOS ===\n";
$logDir = __DIR__ . '/../logs';
if (is_dir($logDir)) {
    echo "✓ Directorio logs existe\n";
    echo "  Permisos: " . substr(sprintf('%o', fileperms($logDir)), -4) . "\n";
    echo "  Escribible: " . (is_writable($logDir) ? 'SÍ' : 'NO') . "\n";
} else {
    echo "✗ Directorio logs NO existe\n";
}
echo "\n";

// 5. Verificar cURL
echo "=== EXTENSIONES PHP ===\n";
echo "cURL: " . (function_exists('curl_init') ? '✓ Disponible' : '✗ NO disponible') . "\n";
echo "PDO SQLite: " . (class_exists('PDO') ? '✓ Disponible' : '✗ NO disponible') . "\n";
echo "Versión PHP: " . phpversion() . "\n";
echo "\n";

echo "</pre>";

echo "<h2>Acciones</h2>";
echo "<p><a href='import_jobs.php'>Ir a Import Jobs</a></p>";
echo "<p><a href='login.php'>Ir a Login</a></p>";
echo "<p><a href='dashboard.php'>Ir a Dashboard</a></p>";
