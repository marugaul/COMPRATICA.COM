<?php
/**
 * Script para verificar y agregar columnas faltantes en las tablas de email marketing
 */

require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Acceso denegado');
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Reparar Base de Datos</title>";
echo "<style>
body{font-family:Arial;padding:20px;background:#f5f5f5}
.ok{color:green;font-weight:bold}
.error{color:red;font-weight:bold}
.warning{color:orange;font-weight:bold}
.step{background:white;padding:20px;margin:15px 0;border-radius:8px;border-left:4px solid #0891b2}
pre{background:#f0f0f0;padding:10px;border-radius:4px;overflow:auto}
</style></head><body>";

echo "<h1>🔧 Reparar Estructura de Base de Datos - Email Marketing</h1>";

try {
    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<div class='step'><h3>1. Verificando tabla email_campaigns</h3>";

    // Obtener columnas actuales
    $stmt = $pdo->query("SHOW COLUMNS FROM email_campaigns");
    $existing_columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[] = $row['Field'];
    }

    echo "<p>Columnas existentes: <code>" . implode(', ', $existing_columns) . "</code></p>";

    // Definir columnas requeridas
    $required_columns = [
        'scheduled_at' => "ADD COLUMN scheduled_at DATETIME NULL AFTER status",
        'started_at' => "ADD COLUMN started_at DATETIME NULL AFTER scheduled_at",
        'completed_at' => "ADD COLUMN completed_at DATETIME NULL AFTER started_at"
    ];

    foreach ($required_columns as $column => $sql) {
        if (!in_array($column, $existing_columns)) {
            echo "<p><span class='warning'>⚠ Columna faltante: $column</span> - Agregando...</p>";
            try {
                $pdo->exec("ALTER TABLE email_campaigns $sql");
                echo "<p><span class='ok'>✓ Columna '$column' agregada exitosamente</span></p>";
            } catch (PDOException $e) {
                echo "<p><span class='error'>✗ Error al agregar '$column': " . $e->getMessage() . "</span></p>";
            }
        } else {
            echo "<p><span class='ok'>✓ Columna '$column' ya existe</span></p>";
        }
    }
    echo "</div>";

    // Verificar email_templates
    echo "<div class='step'><h3>2. Verificando tabla email_templates</h3>";

    $stmt = $pdo->query("SHOW COLUMNS FROM email_templates");
    $existing_template_columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_template_columns[] = $row['Field'];
    }

    echo "<p>Columnas existentes: <code>" . implode(', ', $existing_template_columns) . "</code></p>";

    // Verificar que subject_default existe
    if (!in_array('subject_default', $existing_template_columns)) {
        echo "<p><span class='warning'>⚠ Columna 'subject_default' faltante</span> - Agregando...</p>";
        try {
            $pdo->exec("ALTER TABLE email_templates ADD COLUMN subject_default VARCHAR(255) NOT NULL AFTER company");
            echo "<p><span class='ok'>✓ Columna 'subject_default' agregada</span></p>";
        } catch (PDOException $e) {
            echo "<p><span class='error'>✗ Error: " . $e->getMessage() . "</span></p>";
        }
    } else {
        echo "<p><span class='ok'>✓ Columna 'subject_default' existe</span></p>";
    }
    echo "</div>";

    // Verificar estructura final
    echo "<div class='step'><h3>3. Estructura Final</h3>";
    echo "<h4>email_campaigns:</h4>";
    $stmt = $pdo->query("SHOW COLUMNS FROM email_campaigns");
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
    echo "<tr style='background:#e0f2fe'><th>Columna</th><th>Tipo</th><th>Null</th><th>Default</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td><strong>{$row['Field']}</strong></td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<h4 style='margin-top:20px'>email_templates:</h4>";
    $stmt = $pdo->query("SHOW COLUMNS FROM email_templates");
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
    echo "<tr style='background:#e0f2fe'><th>Columna</th><th>Tipo</th><th>Null</th><th>Default</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td><strong>{$row['Field']}</strong></td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

    echo "<div class='step' style='background:#d1fae5;border-left-color:#16a34a'>";
    echo "<h3 style='color:#065f46'>✓ Verificación Completada</h3>";
    echo "<p>La base de datos ha sido revisada y reparada.</p>";
    echo "<p><a href='email_marketing.php' class='btn' style='display:inline-block;background:#dc2626;color:white;padding:10px 20px;text-decoration:none;border-radius:6px'>Volver a Email Marketing</a></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='step' style='background:#fee2e2;border-left-color:#dc2626'>";
    echo "<h3 style='color:#991b1b'>✗ Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</body></html>";
?>
