<?php
/**
 * Script de migración simple para espacios privados
 * Ejecutar UNA SOLA VEZ desde navegador o CLI
 * BORRAR después de ejecutar
 */

require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=================================================\n";
echo "MIGRACIÓN: Espacios Privados\n";
echo "=================================================\n\n";

$pdo = db();

// Agregar is_private
try {
    echo "1. Agregando columna is_private...\n";
    $pdo->exec("ALTER TABLE sales ADD COLUMN is_private INTEGER NOT NULL DEFAULT 0");
    echo "   ✓ Columna is_private agregada correctamente\n\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'duplicate column') !== false) {
        echo "   ⚠ La columna is_private ya existe (OK)\n\n";
    } else {
        echo "   ✗ Error: " . $e->getMessage() . "\n\n";
    }
}

// Agregar access_code
try {
    echo "2. Agregando columna access_code...\n";
    $pdo->exec("ALTER TABLE sales ADD COLUMN access_code TEXT");
    echo "   ✓ Columna access_code agregada correctamente\n\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'duplicate column') !== false) {
        echo "   ⚠ La columna access_code ya existe (OK)\n\n";
    } else {
        echo "   ✗ Error: " . $e->getMessage() . "\n\n";
    }
}

// Verificar estructura
echo "3. Verificando estructura de tabla sales:\n";
echo "=================================================\n";

$cols = $pdo->query("PRAGMA table_info(sales)")->fetchAll(PDO::FETCH_ASSOC);

$foundPrivate = false;
$foundCode = false;

foreach ($cols as $col) {
    $line = sprintf("   %-20s %-15s", $col['name'], $col['type']);

    if ($col['name'] === 'is_private') {
        $line .= " ← NUEVA";
        $foundPrivate = true;
    }
    if ($col['name'] === 'access_code') {
        $line .= " ← NUEVA";
        $foundCode = true;
    }

    echo $line . "\n";
}

echo "\n=================================================\n";
echo "RESULTADO:\n";
echo "=================================================\n";

if ($foundPrivate && $foundCode) {
    echo "✓ ¡Migración exitosa!\n";
    echo "✓ Ambas columnas están presentes\n";
    echo "✓ Ya puedes crear espacios privados\n\n";
    echo "IMPORTANTE: Borra este archivo (migrate_now.php) por seguridad\n";
} else {
    echo "✗ Faltan columnas:\n";
    if (!$foundPrivate) echo "  - is_private\n";
    if (!$foundCode) echo "  - access_code\n";
    echo "\nContacta al administrador\n";
}

echo "\n=================================================\n";
