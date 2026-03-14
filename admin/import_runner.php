<?php
/**
 * admin/import_runner.php
 * Endpoint de streaming para importación de empleos en tiempo real.
 * Llamado vía fetch() desde import_jobs.php — devuelve texto plano línea a línea.
 */
require_once __DIR__ . '/../includes/auth.php';
require_login();

// ── Deshabilitar todo buffer para streaming real ─────────────────────────────
while (ob_get_level()) ob_end_clean();

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Accel-Buffering: no'); // Deshabilitar buffer de nginx

if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
    @apache_setenv('dont-vary', '1');
}

ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);
ini_set('implicit_flush', true);

set_time_limit(300);

// ── Validar fuente ────────────────────────────────────────────────────────────
$source = in_array($_POST['source'] ?? '', ['indeed', 'remote', 'all'])
    ? $_POST['source'] : 'remote';

// ── Bootstrap ─────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/db.php';
$pdo = db();

// Verificar bot
$botCheck = $pdo->query("SELECT id FROM users WHERE email='bot@compratica.com' LIMIT 1")->fetchColumn();
if (!$botCheck) {
    echo '[' . date('Y-m-d H:i:s') . '] [ERROR] Usuario bot no encontrado. Visita la portada de la app una vez para inicializarlo.' . "\n";
    flush();
    exit;
}

// ── Simular argv y ejecutar el script inline ──────────────────────────────────
$argv = ['import_jobs.php'];
if ($source !== 'all') $argv[] = '--source=' . $source;

echo '[' . date('Y-m-d H:i:s') . '] [ADMIN] === Iniciando importación. source=' . $source . ' ===' . "\n";
echo '[' . date('Y-m-d H:i:s') . '] [ADMIN] PHP=' . phpversion() . ' | cURL=' . (function_exists('curl_init') ? 'disponible' : 'NO disponible') . "\n";
flush();

// Capturar errores PHP y mostrarlos en el stream
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo '[' . date('Y-m-d H:i:s') . '] [PHP-WARN] ' . $errstr . ' (' . basename($errfile) . ':' . $errline . ')' . "\n";
    flush();
    return true;
});

try {
    include dirname(__DIR__) . '/scripts/import_jobs.php';
} catch (\Throwable $e) {
    echo '[' . date('Y-m-d H:i:s') . '] [FATAL] ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' . "\n";
    flush();
}

restore_error_handler();

echo '[' . date('Y-m-d H:i:s') . '] [ADMIN] === FIN ===' . "\n";
flush();
