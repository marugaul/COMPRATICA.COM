<?php
/**
 * Script para limpiar caché de PHP y verificar max_photos
 * Ejecuta esto desde el navegador
 */

// Limpiar OPcache si está habilitado
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache limpiado<br>";
} else {
    echo "ℹ️ OPcache no está habilitado<br>";
}

// Limpiar caché de realpath
clearstatcache(true);
echo "✅ Cache de archivos limpiado<br><br>";

// Reiniciar la conexión PDO
require_once __DIR__ . '/../includes/db.php';

echo "<h2>Diagnóstico de Base de Datos</h2>";

try {
    // Forzar nueva conexión
    $pdo = db();

    echo "<p><strong>Base de datos:</strong> ";
    $result = $pdo->query("PRAGMA database_list")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as $db) {
        if ($db['name'] == 'main') {
            echo htmlspecialchars($db['file']);
        }
    }
    echo "</p>";

    // Verificar tabla
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='listing_pricing'")->fetchAll();

    if (empty($tables)) {
        echo "<p style='color:red'>❌ ERROR: La tabla listing_pricing NO existe</p>";
        exit;
    }

    echo "<p style='color:green'>✅ Tabla listing_pricing existe</p>";

    // Verificar columnas
    echo "<h3>Columnas de la tabla:</h3><ul>";
    $columns = $pdo->query("PRAGMA table_info(listing_pricing)")->fetchAll(PDO::FETCH_ASSOC);

    $hasMaxPhotos = false;
    $hasPaymentMethods = false;

    foreach ($columns as $col) {
        $style = "";
        if ($col['name'] === 'max_photos') {
            $hasMaxPhotos = true;
            $style = "style='color:green;font-weight:bold'";
        }
        if ($col['name'] === 'payment_methods') {
            $hasPaymentMethods = true;
            $style = "style='color:green;font-weight:bold'";
        }
        echo "<li $style>{$col['name']} ({$col['type']})</li>";
    }
    echo "</ul>";

    if (!$hasMaxPhotos) {
        echo "<p style='color:red;font-size:20px'>❌ ERROR: Falta columna max_photos</p>";
        echo "<p><strong>Solución:</strong> Ejecuta el archivo migrations/run_migration.php desde CLI</p>";
        exit;
    }

    if (!$hasPaymentMethods) {
        echo "<p style='color:red;font-size:20px'>❌ ERROR: Falta columna payment_methods</p>";
        echo "<p><strong>Solución:</strong> Ejecuta el archivo migrations/run_migration.php desde CLI</p>";
        exit;
    }

    echo "<p style='color:green;font-size:18px'>✅ Ambas columnas existen correctamente</p>";

    // Intentar SELECT
    echo "<h3>Planes actuales:</h3>";
    $plans = $pdo->query("SELECT id, name, max_photos, payment_methods FROM listing_pricing ORDER BY display_order")->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' cellpadding='5' style='border-collapse:collapse'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Max Fotos</th><th>Métodos de Pago</th></tr>";
    foreach ($plans as $plan) {
        echo "<tr>";
        echo "<td>{$plan['id']}</td>";
        echo "<td>" . htmlspecialchars($plan['name']) . "</td>";
        echo "<td>{$plan['max_photos']}</td>";
        echo "<td>" . htmlspecialchars($plan['payment_methods']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Intentar UPDATE de prueba
    echo "<h3>Prueba de UPDATE:</h3>";
    $testStmt = $pdo->prepare("UPDATE listing_pricing SET max_photos = ?, payment_methods = ? WHERE id = 1");
    $testStmt->execute([3, 'sinpe,paypal']);
    echo "<p style='color:green;font-size:18px'>✅ UPDATE ejecutado exitosamente</p>";

    echo "<hr>";
    echo "<h2 style='color:green'>✅ TODO FUNCIONA CORRECTAMENTE</h2>";
    echo "<p>La base de datos está lista. Ahora puedes usar admin/bienes_raices_config.php sin problemas.</p>";
    echo "<p><a href='bienes_raices_config.php'>Ir a Configuración de Bienes Raíces</a></p>";

} catch (Exception $e) {
    echo "<h2 style='color:red'>❌ ERROR</h2>";
    echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Archivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>
