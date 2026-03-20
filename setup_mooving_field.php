<?php
/**
 * Script para agregar el campo enable_mooving a la tabla entrepreneur_shipping
 * Ejecutar una sola vez para actualizar la estructura de la base de datos
 */

require_once __DIR__ . '/includes/db.php';

try {
    $pdo = db();

    echo "🚀 Agregando campo enable_mooving a entrepreneur_shipping...\n\n";

    // Verificar si la columna ya existe
    $stmt = $pdo->query("PRAGMA table_info(entrepreneur_shipping)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnExists = false;

    foreach ($columns as $col) {
        if ($col['name'] === 'enable_mooving') {
            $columnExists = true;
            break;
        }
    }

    if ($columnExists) {
        echo "ℹ️  El campo 'enable_mooving' ya existe en la tabla.\n";
    } else {
        // Agregar la columna
        $pdo->exec("ALTER TABLE entrepreneur_shipping ADD COLUMN enable_mooving INTEGER NOT NULL DEFAULT 0");
        echo "✅ Campo 'enable_mooving' agregado exitosamente.\n";
    }

    // Mostrar la estructura actualizada
    echo "\n📋 Estructura actualizada de entrepreneur_shipping:\n";
    echo "Columna                    | Tipo    | Not Null | Default\n";
    echo "---------------------------|---------|----------|----------\n";

    $stmt = $pdo->query("PRAGMA table_info(entrepreneur_shipping)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        printf("%-26s | %-7s | %-8s | %s\n",
            $col['name'],
            $col['type'],
            $col['notnull'] ? 'YES' : 'NO',
            $col['dflt_value'] ?? 'NULL'
        );
    }

    echo "\n✅ ¡Migración completada exitosamente!\n";
    echo "\nOpciones de envío disponibles ahora:\n";
    echo "  - enable_free_shipping: Envío gratis\n";
    echo "  - enable_pickup: Retiro en local\n";
    echo "  - enable_express: Envío express (zonas configurables)\n";
    echo "  - enable_mooving: Envío con Mooving (NUEVO) ⭐\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
