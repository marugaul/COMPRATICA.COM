<?php
/**
 * Test de diagnóstico para Email Marketing System
 */

echo "<h1>Diagnóstico de Email Marketing</h1>";
echo "<style>body{font-family:Arial;padding:20px} .ok{color:green} .error{color:red} .warn{color:orange}</style>";

// 1. Test de sesión
session_start();
echo "<h2>1. Sesión</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "is_admin: " . (isset($_SESSION['is_admin']) ? ($_SESSION['is_admin'] ? '<span class="ok">✓ TRUE</span>' : '<span class="error">✗ FALSE</span>') : '<span class="warn">⚠ NO DEFINIDO</span>') . "<br>";
echo "admin_user: " . ($_SESSION['admin_user'] ?? '<span class="warn">no definido</span>') . "<br>";

// 2. Test de archivo config
echo "<h2>2. Archivo de Configuración</h2>";
if (file_exists(__DIR__ . '/../config/database.php')) {
    echo '<span class="ok">✓ config/database.php existe</span><br>';
    $config = require __DIR__ . '/../config/database.php';
    echo "Host: " . ($config['host'] ?? 'N/A') . "<br>";
    echo "Database: " . ($config['database'] ?? 'N/A') . "<br>";
    echo "Username: " . ($config['username'] ?? 'N/A') . "<br>";
    echo "Password: " . (isset($config['password']) ? '***' : '<span class="error">NO DEFINIDO</span>') . "<br>";
} else {
    echo '<span class="error">✗ config/database.php NO EXISTE</span><br>';
}

// 3. Test de conexión a BD
echo "<h2>3. Conexión a Base de Datos</h2>";
try {
    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo '<span class="ok">✓ Conexión exitosa a MySQL</span><br>';

    // 4. Test de tablas
    echo "<h2>4. Tablas de Email Marketing</h2>";
    $tables = [
        'email_smtp_configs',
        'email_templates',
        'email_campaigns',
        'email_recipients',
        'email_send_logs'
    ];

    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo '<span class="ok">✓ ' . $table . '</span> (' . $count . ' registros)<br>';
        } else {
            echo '<span class="error">✗ ' . $table . ' NO EXISTE</span><br>';
        }
    }

} catch (PDOException $e) {
    echo '<span class="error">✗ Error de conexión: ' . htmlspecialchars($e->getMessage()) . '</span><br>';
}

// 5. Test de archivos del sistema
echo "<h2>5. Archivos del Sistema</h2>";
$files = [
    'email_marketing.php',
    'email_marketing_api.php',
    'email_sender.php',
    'email_track.php',
    'email_marketing/dashboard.php',
    'email_marketing/new_campaign.php',
    'email_marketing/campaigns.php',
    'email_marketing/templates.php',
    'email_marketing/smtp_config.php',
    'email_marketing/reports.php',
];

foreach ($files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo '<span class="ok">✓ ' . $file . '</span><br>';
    } else {
        echo '<span class="error">✗ ' . $file . ' NO EXISTE</span><br>';
    }
}

// 6. Simulador de login
echo "<h2>6. Simulador de Sesión Admin</h2>";
echo '<form method="post" style="background:#f0f0f0;padding:15px;border-radius:8px;max-width:400px">';
echo '<p><strong>Para probar el panel de Email Marketing, haz login como admin primero:</strong></p>';
echo '<button type="button" onclick="location.href=\'login.php\'" style="padding:10px 20px;background:#dc2626;color:white;border:none;border-radius:5px;cursor:pointer;font-size:16px;">Ir a Login Admin</button>';
echo '</form>';

echo "<h2>7. Acceso Directo</h2>";
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    echo '<span class="ok">✓ Estás logueado como admin</span><br>';
    echo '<a href="email_marketing.php" style="display:inline-block;margin-top:10px;padding:10px 20px;background:#16a34a;color:white;text-decoration:none;border-radius:5px;">Acceder a Email Marketing</a>';
} else {
    echo '<span class="warn">⚠ No estás logueado como admin</span><br>';
    echo '<p>Primero debes hacer login como administrador</p>';
}

echo "<hr style='margin:30px 0'>";
echo "<p><small>Diagnóstico completado - " . date('Y-m-d H:i:s') . "</small></p>";
?>
