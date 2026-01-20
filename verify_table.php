<?php
require_once __DIR__ . '/includes/db.php';

echo "<h2>Diagnóstico de Base de Datos</h2>";
echo "<hr>";

$pdo = db();

// 1. Verificar ruta del archivo SQLite
$dbFile = __DIR__ . '/data.sqlite';
echo "<p><strong>Ruta del archivo SQLite:</strong> " . htmlspecialchars($dbFile) . "</p>";
echo "<p><strong>¿Existe el archivo?:</strong> " . (file_exists($dbFile) ? '✅ SÍ' : '❌ NO') . "</p>";
if (file_exists($dbFile)) {
    echo "<p><strong>Tamaño del archivo:</strong> " . filesize($dbFile) . " bytes</p>";
    echo "<p><strong>Permisos:</strong> " . substr(sprintf('%o', fileperms($dbFile)), -4) . "</p>";
}

echo "<hr>";

// 2. Listar todas las tablas
echo "<h3>Tablas en la base de datos:</h3>";
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
echo "<ul>";
foreach ($tables as $table) {
    echo "<li>" . htmlspecialchars($table);
    if ($table === 'affiliate_shipping_options') {
        echo " <strong style='color:green'>✅ EXISTE</strong>";
    }
    echo "</li>";
}
echo "</ul>";

echo "<hr>";

// 3. Verificar específicamente la tabla affiliate_shipping_options
echo "<h3>Verificación de affiliate_shipping_options:</h3>";
try {
    $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='affiliate_shipping_options'")->fetch();
    if ($check) {
        echo "<p style='color:green;font-weight:bold'>✅ La tabla affiliate_shipping_options EXISTE</p>";

        // Mostrar estructura
        echo "<h4>Estructura de la tabla:</h4>";
        $columns = $pdo->query("PRAGMA table_info(affiliate_shipping_options)")->fetchAll(PDO::FETCH_ASSOC);
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Tipo</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($col['cid']) . "</td>";
            echo "<td>" . htmlspecialchars($col['name']) . "</td>";
            echo "<td>" . htmlspecialchars($col['type']) . "</td>";
            echo "<td>" . htmlspecialchars($col['dflt_value'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Contar registros
        $count = $pdo->query("SELECT COUNT(*) FROM affiliate_shipping_options")->fetchColumn();
        echo "<p><strong>Registros en la tabla:</strong> " . $count . "</p>";

    } else {
        echo "<p style='color:red;font-weight:bold'>❌ La tabla affiliate_shipping_options NO EXISTE</p>";
        echo "<p>Necesitas ejecutar el script CREATE TABLE</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;font-weight:bold'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='tools/sql_exec.php'>→ Ir a SQL Executor</a></p>";
echo "<p><a href='affiliate/shipping_options.php'>→ Ir a Opciones de Envío</a></p>";
?>
