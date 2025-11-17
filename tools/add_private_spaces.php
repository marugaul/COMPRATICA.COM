<?php
/**
 * Script para agregar funcionalidad de espacios privados
 * Ejecutar una sola vez desde el navegador o CLI
 */

// Solo permitir ejecución desde localhost o admin
$isLocalhost = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']);
$isAdmin = ($_SERVER['PHP_AUTH_USER'] ?? '') === 'marugaul' && ($_SERVER['PHP_AUTH_PW'] ?? '') === 'marden7i';

if (!$isLocalhost && !$isAdmin) {
    die('Acceso denegado. Solo admin o localhost.');
}

require_once __DIR__ . '/../includes/db.php';

$pdo = db();

try {
    echo "Iniciando migración para espacios privados...\n\n";

    // Verificar si las columnas ya existen
    $cols = $pdo->query("PRAGMA table_info(sales)")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($cols, 'name');

    $hasIsPrivate = in_array('is_private', $columnNames);
    $hasAccessCode = in_array('access_code', $columnNames);

    if ($hasIsPrivate && $hasAccessCode) {
        echo "✓ Las columnas ya existen. No se requiere migración.\n";
        echo "\nEstructura actual de la tabla sales:\n";
        foreach ($cols as $col) {
            echo "  - {$col['name']} ({$col['type']})\n";
        }
        exit;
    }

    // Agregar is_private si no existe
    if (!$hasIsPrivate) {
        echo "Agregando columna is_private...\n";
        $pdo->exec("ALTER TABLE sales ADD COLUMN is_private INTEGER NOT NULL DEFAULT 0");
        echo "✓ Columna is_private agregada\n\n";
    }

    // Agregar access_code si no existe
    if (!$hasAccessCode) {
        echo "Agregando columna access_code...\n";
        $pdo->exec("ALTER TABLE sales ADD COLUMN access_code TEXT");
        echo "✓ Columna access_code agregada\n\n";
    }

    echo "Migración completada exitosamente!\n\n";
    echo "Nueva estructura de la tabla sales:\n";
    $cols = $pdo->query("PRAGMA table_info(sales)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo "  - {$col['name']} ({$col['type']})\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
