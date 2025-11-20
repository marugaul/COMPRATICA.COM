<?php
/**
 * Diagnóstico de Email Marketing System
 * Versión que muestra todos los errores
 */

// Mostrar TODOS los errores
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h1>Diagnóstico de Email Marketing</h1>";
echo "<style>
body{font-family:Arial;padding:20px;background:#f5f5f5}
.ok{color:green;font-weight:bold}
.error{color:red;font-weight:bold}
.warn{color:orange;font-weight:bold}
pre{background:#1e293b;color:#cbd5e1;padding:15px;border-radius:8px;overflow-x:auto}
.step{background:white;padding:20px;margin:15px 0;border-radius:8px;border-left:4px solid #0891b2}
</style>";

// PASO 1: Sesión
echo "<div class='step'>";
echo "<h2>1. Verificando Sesión</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "✓ Sesión iniciada<br>";
} else {
    echo "✓ Sesión ya estaba activa<br>";
}
echo "Session ID: " . session_id() . "<br>";
echo "is_admin: " . (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true ? '<span class="ok">✓ TRUE</span>' : '<span class="error">✗ FALSE</span>') . "<br>";
echo "</div>";

// PASO 2: Config de base de datos
echo "<div class='step'>";
echo "<h2>2. Verificando config/database.php</h2>";
$configFile = __DIR__ . '/../config/database.php';
if (!file_exists($configFile)) {
    echo '<span class="error">✗ Archivo NO existe: ' . $configFile . '</span><br>';
    exit;
}
echo "✓ Archivo existe<br>";

try {
    $config = require $configFile;
    echo "✓ Archivo cargado correctamente<br>";
    echo "<pre>";
    print_r([
        'host' => $config['host'],
        'database' => $config['database'],
        'username' => $config['username'],
        'password' => '***'
    ]);
    echo "</pre>";
} catch (Exception $e) {
    echo '<span class="error">✗ Error al cargar config: ' . $e->getMessage() . '</span><br>';
    exit;
}
echo "</div>";

// PASO 3: Conexión a BD
echo "<div class='step'>";
echo "<h2>3. Conectando a MySQL</h2>";
try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo '<span class="ok">✓ Conexión exitosa a MySQL</span><br>';
} catch (PDOException $e) {
    echo '<span class="error">✗ Error de conexión: ' . htmlspecialchars($e->getMessage()) . '</span><br>';
    exit;
}
echo "</div>";

// PASO 4: Verificar tablas
echo "<div class='step'>";
echo "<h2>4. Verificando Tablas de Email Marketing</h2>";
$tables = [
    'email_smtp_configs',
    'email_templates',
    'email_campaigns',
    'email_recipients',
    'email_send_logs'
];

$tablesExist = true;
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo '<span class="ok">✓ ' . $table . '</span> (' . $count . ' registros)<br>';
        } else {
            echo '<span class="error">✗ ' . $table . ' NO EXISTE</span><br>';
            $tablesExist = false;
        }
    } catch (Exception $e) {
        echo '<span class="error">✗ Error al verificar ' . $table . ': ' . $e->getMessage() . '</span><br>';
        $tablesExist = false;
    }
}

if (!$tablesExist) {
    echo "<div style='background:#fee2e2;padding:15px;margin-top:15px;border-radius:8px;border-left:4px solid #dc2626'>";
    echo "<h3>⚠️ FALTAN TABLAS</h3>";
    echo "<p>Las tablas de email marketing no existen en la base de datos.</p>";
    echo "<p><strong>Solución:</strong> Ejecutar el archivo SQL de instalación:</p>";
    echo "<pre>mysql -u comprati_places_user -p comprati_marketplace < admin/setup_email_marketing.sql</pre>";
    echo "<p><a href='install_email_system.php' style='display:inline-block;padding:10px 20px;background:#dc2626;color:white;text-decoration:none;border-radius:6px;'>Ejecutar Instalador Automático</a></p>";
    echo "</div>";
    echo "</div>";
} else {
    echo "</div>";
}

// PASO 5: Verificar archivos del sistema
echo "<div class='step'>";
echo "<h2>5. Verificando Archivos del Sistema</h2>";
$files = [
    'email_marketing.php' => 'Panel principal',
    'email_marketing_api.php' => 'API backend',
    'email_sender.php' => 'Motor de envío',
    'email_track.php' => 'Tracking',
    'email_marketing/dashboard.php' => 'Módulo Dashboard',
    'email_marketing/new_campaign.php' => 'Módulo Nueva Campaña',
    'email_marketing/campaigns.php' => 'Módulo Campañas',
    'email_marketing/templates.php' => 'Módulo Plantillas',
    'email_marketing/smtp_config.php' => 'Módulo Config SMTP',
    'email_marketing/reports.php' => 'Módulo Reportes',
];

$filesOk = true;
foreach ($files as $file => $desc) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo '<span class="ok">✓ ' . $file . '</span> - ' . $desc . '<br>';
    } else {
        echo '<span class="error">✗ ' . $file . ' NO EXISTE</span><br>';
        $filesOk = false;
    }
}
echo "</div>";

// PASO 6: Intentar cargar dashboard.php
echo "<div class='step'>";
echo "<h2>6. Probando Carga de Dashboard</h2>";
try {
    ob_start();
    $dashboardFile = __DIR__ . '/email_marketing/dashboard.php';
    if (file_exists($dashboardFile)) {
        include $dashboardFile;
        $dashboardContent = ob_get_clean();
        echo '<span class="ok">✓ Dashboard cargado sin errores</span><br>';
        echo "<details><summary>Ver contenido del dashboard (primeras 500 caracteres)</summary>";
        echo "<pre>" . htmlspecialchars(substr($dashboardContent, 0, 500)) . "...</pre>";
        echo "</details>";
    } else {
        ob_end_clean();
        echo '<span class="error">✗ Archivo dashboard.php no existe</span><br>';
    }
} catch (Exception $e) {
    ob_end_clean();
    echo '<span class="error">✗ Error al cargar dashboard: ' . htmlspecialchars($e->getMessage()) . '</span><br>';
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
echo "</div>";

// RESUMEN
echo "<div class='step'>";
echo "<h2>🎯 Resumen</h2>";
if ($tablesExist && $filesOk) {
    echo "<div style='background:#d1fae5;padding:15px;border-radius:8px;border-left:4px solid #16a34a'>";
    echo "<h3>✓ Sistema Funcionando</h3>";
    echo "<p>Todos los componentes están en orden. Deberías poder acceder al panel.</p>";
    echo "<p><a href='email_marketing.php' style='display:inline-block;padding:12px 24px;background:#16a34a;color:white;text-decoration:none;border-radius:6px;font-weight:bold;'>Ir a Email Marketing</a></p>";
    echo "</div>";
} else {
    echo "<div style='background:#fee2e2;padding:15px;border-radius:8px;border-left:4px solid #dc2626'>";
    echo "<h3>✗ Hay Problemas</h3>";
    echo "<p>Revisa los errores arriba y ejecuta las soluciones sugeridas.</p>";
    echo "</div>";
}
echo "</div>";

echo "<hr style='margin:30px 0'>";
echo "<p><small>Diagnóstico completado - " . date('Y-m-d H:i:s') . "</small></p>";
?>
