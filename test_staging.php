<?php
// Archivo de diagnóstico temporal
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h1>🔍 Diagnóstico de Staging</h1>";
echo "<hr>";

echo "<h2>1. Variables del servidor:</h2>";
echo "<pre>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'No definido') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'No definido') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'No definido') . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'No definido') . "\n";
echo "</pre>";

echo "<h2>2. Detección de Staging:</h2>";
echo "<pre>";
$isStaging = false;
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/staging/') === 0) {
    $isStaging = true;
}
echo "¿Es Staging? " . ($isStaging ? 'SÍ ✅' : 'NO ❌') . "\n";
echo "</pre>";

echo "<h2>3. Cargando config.php:</h2>";
require_once __DIR__ . '/includes/config.php';
echo "<pre>";
echo "BASE_URL: " . (defined('BASE_URL') ? BASE_URL : 'No definido') . "\n";
echo "SITE_URL: " . (defined('SITE_URL') ? SITE_URL : 'No definido') . "\n";
echo "ADMIN_DASHBOARD_PATH: " . (defined('ADMIN_DASHBOARD_PATH') ? ADMIN_DASHBOARD_PATH : 'No definido') . "\n";
echo "</pre>";

echo "<h2>4. Archivos importantes:</h2>";
echo "<pre>";
echo "config.php existe: " . (file_exists(__DIR__ . '/includes/config.php') ? 'SÍ ✅' : 'NO ❌') . "\n";
echo ".htaccess existe: " . (file_exists(__DIR__ . '/.htaccess') ? 'SÍ ✅' : 'NO ❌') . "\n";
echo ".htaccess_staging existe: " . (file_exists(__DIR__ . '/.htaccess_staging') ? 'SÍ ✅' : 'NO ❌') . "\n";
echo "</pre>";

echo "<h2>5. Test de enlaces:</h2>";
echo "<ul>";
echo "<li><a href='/staging/index.php'>index.php</a></li>";
echo "<li><a href='/staging/emprendedores.php'>emprendedores.php</a></li>";
echo "<li><a href='/staging/store.php'>store.php</a></li>";
echo "</ul>";

echo "<hr>";
echo "<p><strong>Si BASE_URL y SITE_URL no muestran '/staging', hay un problema con el config.php</strong></p>";
?>
