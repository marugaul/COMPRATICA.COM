<?php
/**
 * Script para crear la tabla affiliate_shipping_options
 * Ejecuta este archivo desde el navegador: https://compratica.com/create_shipping_table.php
 */

require_once __DIR__ . '/includes/db.php';

echo "<h1>Crear Tabla de Opciones de Envío</h1>";

try {
    $pdo = db();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h2>Proceso de Creación</h2>";

    // Verificar si ya existe
    $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='affiliate_shipping_options'")->fetch();
    if ($check) {
        echo "<p style='color:orange;'>⚠️ La tabla ya existe, eliminándola primero...</p>";
        $pdo->exec("DROP TABLE IF EXISTS affiliate_shipping_options");
        echo "<p style='color:green;'>✅ Tabla anterior eliminada</p>";
    }

    // Crear tabla SIN foreign key para evitar problemas
    $sql = "CREATE TABLE affiliate_shipping_options (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        affiliate_id INTEGER NOT NULL,
        enable_pickup INTEGER DEFAULT 1,
        enable_free_shipping INTEGER DEFAULT 0,
        enable_uber INTEGER DEFAULT 0,
        pickup_instructions TEXT DEFAULT NULL,
        free_shipping_min_amount REAL DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now','localtime')),
        updated_at TEXT DEFAULT (datetime('now','localtime'))
    )";

    $pdo->exec($sql);
    echo "<p style='color:green;'>✅ Tabla 'affiliate_shipping_options' creada exitosamente</p>";

    // Crear índice
    $indexSql = "CREATE INDEX IF NOT EXISTS idx_aff_shipping_options ON affiliate_shipping_options(affiliate_id)";
    $pdo->exec($indexSql);
    echo "<p style='color:green;'>✅ Índice 'idx_aff_shipping_options' creado exitosamente</p>";

    // Verificar que existe
    $check2 = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='affiliate_shipping_options'")->fetch();
    if ($check2) {
        echo "<p style='color:green;font-weight:bold;font-size:18px;'>✅ ¡ÉXITO! La tabla está lista para usar</p>";

        // Mostrar estructura
        echo "<h3>Estructura de la tabla:</h3>";
        $columns = $pdo->query("PRAGMA table_info(affiliate_shipping_options)")->fetchAll(PDO::FETCH_ASSOC);
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
        echo "<tr><th>Columna</th><th>Tipo</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($col['name']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($col['type']) . "</td>";
            echo "<td>" . htmlspecialchars($col['dflt_value'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<p style='color:red;font-weight:bold;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr>";
echo "<p><a href='affiliate/dashboard.php'>← Volver al Dashboard</a></p>";
