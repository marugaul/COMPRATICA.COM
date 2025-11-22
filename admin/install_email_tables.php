<?php
/**
 * Instalador de Tablas de Email Marketing
 * Versión simplificada que solo crea las tablas
 */

// Mostrar errores
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Acceso denegado. Solo administradores. <a href="/admin/login.php">Login</a>');
}

echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Instalador Email Marketing</title>";
echo "<style>
body{font-family:Arial;padding:20px;background:#f5f5f5}
.ok{color:green;font-weight:bold}
.error{color:red;font-weight:bold}
.warn{color:orange;font-weight:bold}
.step{background:white;padding:20px;margin:15px 0;border-radius:8px;border-left:4px solid #0891b2}
pre{background:#1e293b;color:#cbd5e1;padding:15px;border-radius:8px;overflow-x:auto;font-size:12px}
</style></head><body>";

echo "<h1>🚀 Instalador de Email Marketing</h1>";

// Conectar a BD
echo "<div class='step'><h2>1. Conectando a la Base de Datos</h2>";
try {
    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo '<span class="ok">✓ Conexión exitosa</span><br>';
    echo "Base de datos: <strong>{$config['database']}</strong>";
} catch (PDOException $e) {
    echo '<span class="error">✗ Error de conexión: ' . htmlspecialchars($e->getMessage()) . '</span>';
    die('</div></body></html>');
}
echo "</div>";

// Leer SQL file
echo "<div class='step'><h2>2. Leyendo archivo SQL</h2>";
$sqlFile = __DIR__ . '/setup_email_marketing.sql';
if (!file_exists($sqlFile)) {
    echo '<span class="error">✗ Archivo no encontrado: ' . $sqlFile . '</span>';
    die('</div></body></html>');
}
echo '<span class="ok">✓ Archivo encontrado</span><br>';
$sql = file_get_contents($sqlFile);
echo "Tamaño: " . strlen($sql) . " bytes";
echo "</div>";

// Ejecutar SQL
echo "<div class='step'><h2>3. Ejecutando SQL</h2>";
try {
    // Dividir por ; y ejecutar cada statement
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );

    $count = 0;
    foreach ($statements as $stmt) {
        if (empty($stmt)) continue;
        try {
            $pdo->exec($stmt);
            $count++;
            // Mostrar solo las primeras 100 caracteres de cada statement
            echo '<span class="ok">✓</span> ' . htmlspecialchars(substr($stmt, 0, 100)) . "...<br>";
        } catch (PDOException $e) {
            // Si es error de "tabla ya existe", solo advertencia
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo '<span class="warn">⚠</span> ' . htmlspecialchars(substr($stmt, 0, 100)) . "... (ya existe)<br>";
            } else {
                echo '<span class="error">✗</span> ' . htmlspecialchars($e->getMessage()) . "<br>";
                echo "<pre>" . htmlspecialchars($stmt) . "</pre>";
            }
        }
    }

    echo "<br><strong>Total statements ejecutados: $count</strong>";
} catch (Exception $e) {
    echo '<span class="error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</span>';
}
echo "</div>";

// Verificar tablas creadas
echo "<div class='step'><h2>4. Verificando Tablas Creadas</h2>";
$tables = [
    'email_smtp_configs',
    'email_templates',
    'email_campaigns',
    'email_recipients',
    'email_send_logs'
];

$allOk = true;
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo '<span class="ok">✓ ' . $table . '</span> (' . $count . ' registros)<br>';
        } else {
            echo '<span class="error">✗ ' . $table . ' NO existe</span><br>';
            $allOk = false;
        }
    } catch (Exception $e) {
        echo '<span class="error">✗ Error: ' . $e->getMessage() . '</span><br>';
        $allOk = false;
    }
}
echo "</div>";

// Resultado final
echo "<div class='step'>";
if ($allOk) {
    echo "<h2 style='color:green'>✓ Instalación Completada</h2>";
    echo "<p>Todas las tablas fueron creadas exitosamente.</p>";
    echo "<p><a href='email_marketing.php' style='display:inline-block;padding:12px 24px;background:#16a34a;color:white;text-decoration:none;border-radius:6px;font-weight:bold;'>Ir a Email Marketing</a></p>";
    echo "<p><a href='test_email_system.php' style='display:inline-block;padding:12px 24px;background:#0891b2;color:white;text-decoration:none;border-radius:6px;font-weight:bold;margin-top:10px'>Ver Diagnóstico Completo</a></p>";
} else {
    echo "<h2 style='color:red'>✗ Instalación Incompleta</h2>";
    echo "<p>Algunas tablas no se crearon correctamente. Revisa los errores arriba.</p>";
}
echo "</div>";

echo "<hr><p><small>Instalación ejecutada - " . date('Y-m-d H:i:s') . "</small></p>";
echo "</body></html>";
?>
