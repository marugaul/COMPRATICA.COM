<?php
// Agregar columna updated_at a tabla affiliates
require_once __DIR__ . '/includes/db.php';

echo "<h2>Actualizando estructura de tabla affiliates</h2>\n\n";

try {
    $pdo = db();

    // Ver estructura actual
    echo "<h3>Estructura actual:</h3>\n";
    $stmt = $pdo->query("PRAGMA table_info(affiliates)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    foreach ($columns as $col) {
        echo $col['name'] . " - " . $col['type'] . "\n";
    }
    echo "</pre>\n";

    // Verificar si updated_at existe
    $hasUpdatedAt = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'updated_at') {
            $hasUpdatedAt = true;
            break;
        }
    }

    if (!$hasUpdatedAt) {
        echo "<h3>Agregando columna updated_at...</h3>\n";
        $pdo->exec("ALTER TABLE affiliates ADD COLUMN updated_at TEXT");

        // Inicializar con created_at
        $pdo->exec("UPDATE affiliates SET updated_at = created_at WHERE updated_at IS NULL");

        echo "<p style='color:green;'>✓ Columna updated_at agregada exitosamente</p>\n";
    } else {
        echo "<p style='color:blue;'>✓ Columna updated_at ya existe</p>\n";
    }

    // Verificar estructura final
    echo "<h3>Estructura final:</h3>\n";
    $stmt = $pdo->query("PRAGMA table_info(affiliates)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    foreach ($columns as $col) {
        echo $col['name'] . " - " . $col['type'] . "\n";
    }
    echo "</pre>\n";

    echo "<hr>\n";
    echo "<p><strong>✓ Tabla actualizada correctamente</strong></p>\n";
    echo "<p><a href='admin/affiliates.php'>← Volver a gestión de afiliados</a></p>\n";

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
?>
