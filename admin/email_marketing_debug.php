<?php
/**
 * Email Marketing - Versión DEBUG
 * Muestra paso a paso qué está pasando
 */

// Mostrar TODOS los errores
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Email Marketing DEBUG</title>";
echo "<style>
body{font-family:Arial;padding:20px;background:#f5f5f5}
.ok{color:green;font-weight:bold}
.error{color:red;font-weight:bold}
.step{background:white;padding:15px;margin:10px 0;border-radius:8px;border-left:4px solid #0891b2}
pre{background:#1e293b;color:#cbd5e1;padding:10px;border-radius:4px;font-size:12px;overflow-x:auto}
</style></head><body>";

echo "<h1>🔍 Email Marketing - Modo DEBUG</h1>";

// PASO 1: Sesión
echo "<div class='step'>";
echo "<h3>PASO 1: Iniciando sesión</h3>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "✓ session_start() ejecutado<br>";
} else {
    echo "✓ Sesión ya estaba activa<br>";
}
echo "Session ID: " . session_id() . "<br>";
echo "is_admin: " . (isset($_SESSION['is_admin']) ? ($_SESSION['is_admin'] ? '<span class="ok">TRUE</span>' : '<span class="error">FALSE</span>') : '<span class="error">NO DEFINIDO</span>') . "<br>";
echo "</div>";

// PASO 2: Verificar admin
echo "<div class='step'>";
echo "<h3>PASO 2: Verificando autenticación</h3>";
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo '<span class="error">✗ NO ESTÁS LOGUEADO COMO ADMIN</span><br>';
    echo "<p>Redirigiendo a login en 3 segundos...</p>";
    echo "<p><a href='login_simple.php' style='padding:10px 20px;background:#dc2626;color:white;text-decoration:none;border-radius:6px;'>Ir a Login Ahora</a></p>";
    echo "<script>setTimeout(function(){ window.location='login_simple.php'; }, 3000);</script>";
    echo "</div></body></html>";
    exit;
}
echo '<span class="ok">✓ Autenticación correcta</span><br>';
echo "Usuario admin: " . ($_SESSION['admin_user'] ?? 'N/A') . "<br>";
echo "</div>";

// PASO 3: Cargar config
echo "<div class='step'>";
echo "<h3>PASO 3: Cargando configuración de BD</h3>";
try {
    $config = require __DIR__ . '/../config/database.php';
    echo "✓ Config cargado<br>";
    echo "Host: {$config['host']}<br>";
    echo "Database: {$config['database']}<br>";
} catch (Exception $e) {
    echo '<span class="error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</span>';
    die('</div></body></html>');
}
echo "</div>";

// PASO 4: Conectar BD
echo "<div class='step'>";
echo "<h3>PASO 4: Conectando a MySQL</h3>";
try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo '<span class="ok">✓ Conexión exitosa</span><br>';
} catch (PDOException $e) {
    echo '<span class="error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</span>';
    die('</div></body></html>');
}
echo "</div>";

// PASO 5: Cargar página
echo "<div class='step'>";
echo "<h3>PASO 5: Procesando página solicitada</h3>";
$page = $_GET['page'] ?? 'dashboard';
echo "Página solicitada: <strong>" . htmlspecialchars($page) . "</strong><br>";

$validPages = ['dashboard', 'new-campaign', 'campaigns', 'templates', 'smtp-config', 'reports'];
if (!in_array($page, $validPages)) {
    $page = 'dashboard';
    echo "Página inválida, usando dashboard por defecto<br>";
}

$pageFile = __DIR__ . '/email_marketing/' . str_replace('-', '_', $page) . '.php';
echo "Archivo a cargar: " . basename($pageFile) . "<br>";

if (!file_exists($pageFile)) {
    echo '<span class="error">✗ Archivo no existe: ' . htmlspecialchars($pageFile) . '</span>';
    die('</div></body></html>');
}
echo '<span class="ok">✓ Archivo existe</span><br>';
echo "</div>";

// PASO 6: Renderizar
echo "<div class='step'>";
echo "<h3>PASO 6: Cargando interfaz completa</h3>";
echo '<span class="ok">✓ Todo OK - Mostrando panel</span>';
echo "</div>";

echo "<hr style='margin:30px 0'>";

// Helper function
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);
?>

<!-- INTERFAZ COMPLETA -->
<style>
:root {
    --primary: #dc2626;
    --secondary: #0891b2;
    --success: #16a34a;
    --warning: #f59e0b;
    --danger: #ef4444;
}

body {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    min-height: 100vh;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.sidebar {
    background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
    min-height: 100vh;
    color: #cbd5e1;
    padding: 0;
    width: 250px;
    position: fixed;
    left: 0;
    top: 0;
}

.sidebar-header {
    background: linear-gradient(135deg, var(--primary) 0%, #b91c1c 100%);
    padding: 25px 20px;
    color: white;
    text-align: center;
}

.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 20px 0;
}

.sidebar-menu li a {
    display: block;
    padding: 15px 25px;
    color: #cbd5e1;
    text-decoration: none;
    transition: all 0.3s;
    border-left: 3px solid transparent;
}

.sidebar-menu li a:hover,
.sidebar-menu li a.active {
    background-color: rgba(220, 38, 38, 0.1);
    border-left-color: var(--primary);
    color: #fbbf24;
}

.main-content {
    margin-left: 250px;
    padding: 30px;
}

.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    margin-bottom: 25px;
    background: white;
    padding: 20px;
}

.card-header {
    background: linear-gradient(135deg, var(--primary) 0%, #b91c1c 100%);
    color: white;
    border-radius: 12px 12px 0 0 !important;
    padding: 20px;
    font-weight: bold;
    margin: -20px -20px 20px -20px;
}

.stat-card {
    text-align: center;
    padding: 25px;
    border-radius: 12px;
    color: white;
    margin-bottom: 20px;
}

.stat-card.primary { background: linear-gradient(135deg, var(--primary) 0%, #b91c1c 100%); }
.stat-card.success { background: linear-gradient(135deg, var(--success) 0%, #15803d 100%); }
.stat-card.warning { background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%); }
.stat-card.info { background: linear-gradient(135deg, var(--secondary) 0%, #0e7490 100%); }

.stat-card h3 {
    font-size: 36px;
    margin: 10px 0;
    font-weight: bold;
}

.stat-card p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}
</style>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h3 style="margin: 0;">🇨🇷 CompraTica</h3>
        <small>Email Marketing</small>
    </div>

    <ul class="sidebar-menu">
        <li><a href="?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">
            📈 Dashboard
        </a></li>
        <li><a href="?page=new-campaign" class="<?= $page === 'new-campaign' ? 'active' : '' ?>">
            ➕ Nueva Campaña
        </a></li>
        <li><a href="?page=campaigns" class="<?= $page === 'campaigns' ? 'active' : '' ?>">
            📧 Campañas
        </a></li>
        <li><a href="?page=templates" class="<?= $page === 'templates' ? 'active' : '' ?>">
            🎨 Plantillas
        </a></li>
        <li><a href="?page=smtp-config" class="<?= $page === 'smtp-config' ? 'active' : '' ?>">
            ⚙️ Config. SMTP
        </a></li>
        <li><a href="?page=reports" class="<?= $page === 'reports' ? 'active' : '' ?>">
            📊 Reportes
        </a></li>
        <li><hr style="border-color: #334155; margin: 20px 15px;"></li>
        <li><a href="dashboard.php">
            ← Volver al Admin
        </a></li>
    </ul>
</div>

<!-- Main Content -->
<div class="main-content">
    <?php if ($message): ?>
        <div class="alert alert-<?= $message['type'] ?? 'info' ?>" style="padding:15px;border-radius:8px;margin-bottom:20px;background:#d1fae5;color:#065f46">
            <?= h($message['text']) ?>
        </div>
    <?php endif; ?>

    <?php
    // Cargar el módulo de la página
    try {
        include $pageFile;
    } catch (Exception $e) {
        echo '<div class="card">';
        echo '<div class="card-header">Error</div>';
        echo '<p style="color:red">Error al cargar el módulo: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</div>';
    }
    ?>
</div>

</body>
</html>
