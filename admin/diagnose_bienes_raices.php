<?php
/**
 * DIAGNÓSTICO ESPECÍFICO PARA BIENES RAÍCES
 * Muestra exactamente qué BD está usando bienes_raices_config.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔍 Diagnóstico Bienes Raíces</h1>";
echo "<hr>";

// Usar exactamente el mismo código que bienes_raices_config.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

echo "<h2>1. Conexión de Base de Datos</h2>";

$pdo = db();

// Ver qué archivo de BD está usando
$result = $pdo->query("PRAGMA database_list")->fetchAll(PDO::FETCH_ASSOC);

foreach ($result as $db) {
    if ($db['name'] == 'main') {
        $dbPath = $db['file'];
        echo "<strong>Archivo BD en uso:</strong> <code>" . htmlspecialchars($dbPath) . "</code><br>";

        if (file_exists($dbPath)) {
            echo "<strong>Existe:</strong> ✅ Sí<br>";
            echo "<strong>Tamaño:</strong> " . number_format(filesize($dbPath)) . " bytes<br>";
            echo "<strong>Última modificación:</strong> " . date('Y-m-d H:i:s', filemtime($dbPath)) . "<br>";
            echo "<strong>Permisos:</strong> " . substr(sprintf('%o', fileperms($dbPath)), -4) . "<br>";
        } else {
            echo "<strong>Existe:</strong> ❌ No<br>";
        }
    }
}

echo "<br><h2>2. Verificar tabla listing_pricing</h2>";

// Verificar si existe la tabla
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='listing_pricing'")->fetchAll();

if (empty($tables)) {
    echo "<p style='color:red;font-size:18px'>❌ ERROR: La tabla listing_pricing NO EXISTE en esta base de datos</p>";
    echo "<p>Esta es la base de datos INCORRECTA.</p>";
    exit;
}

echo "<p style='color:green'>✅ Tabla listing_pricing existe</p><br>";

echo "<h2>3. Columnas de listing_pricing</h2>";

$columns = $pdo->query("PRAGMA table_info(listing_pricing)")->fetchAll(PDO::FETCH_ASSOC);

$hasMaxPhotos = false;
$hasPaymentMethods = false;

echo "<table border='1' cellpadding='8' style='border-collapse:collapse; width:100%; max-width:600px'>";
echo "<tr style='background:#f0f0f0'><th>Columna</th><th>Tipo</th><th>Estado</th></tr>";

foreach ($columns as $col) {
    if ($col['name'] === 'max_photos') $hasMaxPhotos = true;
    if ($col['name'] === 'payment_methods') $hasPaymentMethods = true;

    $rowStyle = "";
    $status = "";

    if ($col['name'] === 'max_photos') {
        $rowStyle = "style='background:#d4edda'";
        $status = "✅ <strong>NECESARIA</strong>";
    } else if ($col['name'] === 'payment_methods') {
        $rowStyle = "style='background:#d4edda'";
        $status = "✅ <strong>NECESARIA</strong>";
    }

    echo "<tr $rowStyle>";
    echo "<td><strong>{$col['name']}</strong></td>";
    echo "<td>{$col['type']}</td>";
    echo "<td>$status</td>";
    echo "</tr>";
}
echo "</table><br>";

// Resultado
echo "<h2>4. Resultado del Diagnóstico</h2>";

if (!$hasMaxPhotos && !$hasPaymentMethods) {
    echo "<div style='background:#f8d7da;color:#721c24;padding:20px;border-left:4px solid #f5c6cb'>";
    echo "<h3>❌ FALTAN AMBAS COLUMNAS</h3>";
    echo "<p><strong>Columnas faltantes:</strong></p>";
    echo "<ul>";
    echo "<li>max_photos</li>";
    echo "<li>payment_methods</li>";
    echo "</ul>";
    echo "<p><strong>SOLUCIÓN:</strong></p>";
    echo "<ol>";
    echo "<li>Ejecuta el SQL abajo en esta base de datos</li>";
    echo "<li>O ejecuta: <a href='fix_max_photos_emergency.php'>fix_max_photos_emergency.php</a></li>";
    echo "</ol>";
    echo "</div>";

    echo "<h3>SQL para agregar las columnas:</h3>";
    echo "<textarea style='width:100%;height:200px;font-family:monospace;padding:10px'>";
    echo "ALTER TABLE listing_pricing ADD COLUMN max_photos INTEGER DEFAULT 3;\n";
    echo "ALTER TABLE listing_pricing ADD COLUMN payment_methods TEXT DEFAULT 'sinpe,paypal';\n\n";
    echo "-- Actualizar planes existentes\n";
    echo "UPDATE listing_pricing SET max_photos = 3, payment_methods = 'sinpe,paypal' WHERE id = 1;\n";
    echo "UPDATE listing_pricing SET max_photos = 5, payment_methods = 'sinpe,paypal' WHERE id = 2;\n";
    echo "UPDATE listing_pricing SET max_photos = 8, payment_methods = 'sinpe,paypal' WHERE id = 3;\n";
    echo "</textarea>";

} else if (!$hasMaxPhotos) {
    echo "<div style='background:#fff3cd;color:#856404;padding:20px;border-left:4px solid #ffeeba'>";
    echo "<h3>⚠️ FALTA max_photos</h3>";
    echo "<p>La columna <code>payment_methods</code> existe, pero falta <code>max_photos</code></p>";
    echo "</div>";

} else if (!$hasPaymentMethods) {
    echo "<div style='background:#fff3cd;color:#856404;padding:20px;border-left:4px solid #ffeeba'>";
    echo "<h3>⚠️ FALTA payment_methods</h3>";
    echo "<p>La columna <code>max_photos</code> existe, pero falta <code>payment_methods</code></p>";
    echo "</div>";

} else {
    echo "<div style='background:#d4edda;color:#155724;padding:20px;border-left:4px solid #c3e6cb'>";
    echo "<h3>✅ AMBAS COLUMNAS EXISTEN</h3>";
    echo "<p>Las columnas están correctamente configuradas en la base de datos.</p>";
    echo "</div>";

    // Probar el query exacto que usa bienes_raices_config.php
    echo "<br><h2>5. Prueba del Query de Actualización</h2>";

    try {
        $stmt = $pdo->prepare("
            UPDATE listing_pricing
            SET name = ?,
                duration_days = ?,
                price_usd = ?,
                price_crc = ?,
                max_photos = ?,
                payment_methods = ?,
                is_active = ?,
                is_featured = ?,
                description = ?,
                updated_at = datetime('now')
            WHERE id = ?
        ");

        // Ejecutar con datos de prueba (sin realmente actualizar)
        $pdo->beginTransaction();
        $stmt->execute(['Test', 30, 1.0, 540.0, 5, 'sinpe,paypal', 1, 0, 'Test', 999]);
        $pdo->rollBack(); // Revertir para no modificar datos

        echo "<p style='color:green;font-size:18px'>✅ El query UPDATE funciona correctamente</p>";
        echo "<p><strong>Conclusión:</strong> Todo está bien en la base de datos. El error puede ser de caché.</p>";
        echo "<p><strong>Solución:</strong> Presiona <kbd>Ctrl+Shift+R</kbd> en tu navegador para limpiar caché.</p>";

    } catch (Exception $e) {
        echo "<p style='color:red;font-size:18px'>❌ El query UPDATE falló</p>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    // Mostrar planes actuales
    echo "<br><h2>6. Planes Actuales</h2>";

    $plans = $pdo->query("SELECT id, name, duration_days, price_usd, max_photos, payment_methods, is_active FROM listing_pricing ORDER BY display_order")->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' cellpadding='8' style='border-collapse:collapse; width:100%'>";
    echo "<tr style='background:#f0f0f0'>";
    echo "<th>ID</th><th>Nombre</th><th>Días</th><th>Precio USD</th><th>Max Fotos</th><th>Métodos Pago</th><th>Activo</th>";
    echo "</tr>";

    foreach ($plans as $plan) {
        echo "<tr>";
        echo "<td>{$plan['id']}</td>";
        echo "<td>" . htmlspecialchars($plan['name']) . "</td>";
        echo "<td>{$plan['duration_days']}</td>";
        echo "<td>\${$plan['price_usd']}</td>";
        echo "<td><strong>{$plan['max_photos']}</strong></td>";
        echo "<td>" . htmlspecialchars($plan['payment_methods']) . "</td>";
        echo "<td>" . ($plan['is_active'] ? '✅' : '❌') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<p><a href='bienes_raices_config.php'>← Volver a Configuración de Bienes Raíces</a></p>";
?>
