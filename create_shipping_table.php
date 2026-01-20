<?php
/**
 * Script para crear la tabla affiliate_shipping_options
 * Ejecuta este archivo desde el navegador: https://compratica.com/create_shipping_table.php
 */

require_once __DIR__ . '/includes/db.php';

echo "<h1>Crear Tabla de Opciones de Envío</h1>";

try {
    $pdo = db();

    // Crear tabla
    $sql = "CREATE TABLE IF NOT EXISTS affiliate_shipping_options (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        affiliate_id INTEGER NOT NULL,
        enable_pickup INTEGER DEFAULT 1,
        enable_free_shipping INTEGER DEFAULT 0,
        enable_uber INTEGER DEFAULT 0,
        pickup_instructions TEXT DEFAULT NULL,
        free_shipping_min_amount REAL DEFAULT 0,
        created_at TEXT DEFAULT (datetime('now','localtime')),
        updated_at TEXT DEFAULT (datetime('now','localtime')),
        FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
    )";

    $pdo->exec($sql);
    echo "<p style='color:green;'>✅ Tabla 'affiliate_shipping_options' creada exitosamente</p>";

    // Crear índice
    $indexSql = "CREATE INDEX IF NOT EXISTS idx_aff_shipping_options ON affiliate_shipping_options(affiliate_id)";
    $pdo->exec($indexSql);
    echo "<p style='color:green;'>✅ Índice 'idx_aff_shipping_options' creado exitosamente</p>";

    // Verificar que existe
    $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='affiliate_shipping_options'")->fetch();
    if ($check) {
        echo "<p style='color:green;font-weight:bold;'>✅ ÉXITO: La tabla está lista para usar</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='affiliate/dashboard.php'>← Volver al Dashboard</a></p>";
