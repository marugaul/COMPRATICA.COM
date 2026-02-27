<?php
/**
 * FIX PAYMENT_METHODS - Agregar columna payment_methods a listing_pricing
 * Fecha: 2026-02-27
 * Ejecutar este archivo una vez para solucionar el error: "no such column: payment_methods"
 */

// Usar las mismas configuraciones que el resto del sistema
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔧 Fix Payment Methods</h1>";
echo "<hr>";

$pdo = db();

try {
    echo "<h2>1. Verificando estado actual...</h2>";

    // Verificar si la columna ya existe
    $columns = $pdo->query("PRAGMA table_info(listing_pricing)")->fetchAll(PDO::FETCH_ASSOC);
    $hasPaymentMethods = false;

    foreach ($columns as $col) {
        if ($col['name'] === 'payment_methods') {
            $hasPaymentMethods = true;
            break;
        }
    }

    if ($hasPaymentMethods) {
        echo "<div style='background:#d4edda;color:#155724;padding:15px;border-left:4px solid #c3e6cb;margin:10px 0'>";
        echo "✅ La columna <code>payment_methods</code> ya existe. No se necesita hacer nada.";
        echo "</div>";
        exit;
    }

    echo "<p>⚠️ La columna <code>payment_methods</code> NO existe. Procediendo a agregarla...</p>";

    // Comenzar transacción
    $pdo->beginTransaction();

    echo "<h2>2. Agregando columna payment_methods...</h2>";

    // Agregar la columna
    $pdo->exec("ALTER TABLE listing_pricing ADD COLUMN payment_methods TEXT DEFAULT 'sinpe,paypal'");
    echo "<p>✅ Columna agregada correctamente</p>";

    echo "<h2>3. Actualizando valores por defecto...</h2>";

    // Actualizar planes existentes con valores por defecto
    $updates = [
        1 => ['name' => 'Plan Gratis', 'payment' => 'sinpe,paypal'],
        2 => ['name' => 'Plan 30 días', 'payment' => 'sinpe,paypal'],
        3 => ['name' => 'Plan 90 días', 'payment' => 'sinpe,paypal']
    ];

    foreach ($updates as $id => $data) {
        $pdo->exec("UPDATE listing_pricing SET payment_methods = '{$data['payment']}' WHERE id = $id");
        echo "<p>✅ Actualizado {$data['name']}: payment_methods = '{$data['payment']}'</p>";
    }

    // Asegurarse de que todos los demás planes tengan valores
    $pdo->exec("UPDATE listing_pricing SET payment_methods = 'sinpe,paypal' WHERE payment_methods IS NULL OR payment_methods = ''");

    // Confirmar transacción
    $pdo->commit();

    echo "<h2>4. Verificando resultado...</h2>";

    // Mostrar planes actualizados
    $plans = $pdo->query("
        SELECT id, name, duration_days, price_usd, price_crc, max_photos, payment_methods, is_active
        FROM listing_pricing
        ORDER BY display_order
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' cellpadding='8' style='border-collapse:collapse; width:100%; margin:10px 0'>";
    echo "<tr style='background:#f0f0f0'>";
    echo "<th>ID</th><th>Nombre</th><th>Días</th><th>Precio USD</th><th>Max Fotos</th><th>Métodos Pago</th><th>Activo</th>";
    echo "</tr>";

    foreach ($plans as $plan) {
        echo "<tr>";
        echo "<td>{$plan['id']}</td>";
        echo "<td>" . htmlspecialchars($plan['name']) . "</td>";
        echo "<td>{$plan['duration_days']}</td>";
        echo "<td>\${$plan['price_usd']}</td>";
        echo "<td>{$plan['max_photos']}</td>";
        echo "<td><strong style='color:green'>" . htmlspecialchars($plan['payment_methods']) . "</strong></td>";
        echo "<td>" . ($plan['is_active'] ? '✅' : '❌') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<div style='background:#d4edda;color:#155724;padding:20px;border-left:4px solid #c3e6cb;margin:20px 0'>";
    echo "<h3>✅ COMPLETADO EXITOSAMENTE</h3>";
    echo "<p>La columna <code>payment_methods</code> se ha agregado correctamente a la tabla <code>listing_pricing</code>.</p>";
    echo "<p><strong>El error debería estar resuelto.</strong></p>";
    echo "</div>";

    echo "<h3>Siguiente paso:</h3>";
    echo "<ol>";
    echo "<li>Regresa a <a href='bienes_raices_config.php'>Configuración de Bienes Raíces</a></li>";
    echo "<li>Prueba actualizar un plan para confirmar que funciona</li>";
    echo "</ol>";

} catch (Exception $e) {
    // Revertir en caso de error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "<div style='background:#f8d7da;color:#721c24;padding:20px;border-left:4px solid #f5c6cb;margin:20px 0'>";
    echo "<h3>❌ ERROR</h3>";
    echo "<p><strong>Mensaje de error:</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p><strong>Archivo:</strong> {$e->getFile()}</p>";
    echo "<p><strong>Línea:</strong> {$e->getLine()}</p>";
    echo "</div>";

    echo "<h3>Solución alternativa:</h3>";
    echo "<p>Ejecuta este SQL directamente en tu base de datos:</p>";
    echo "<textarea style='width:100%;height:150px;font-family:monospace;padding:10px'>";
    echo "ALTER TABLE listing_pricing ADD COLUMN payment_methods TEXT DEFAULT 'sinpe,paypal';\n\n";
    echo "UPDATE listing_pricing SET payment_methods = 'sinpe,paypal' WHERE id = 1;\n";
    echo "UPDATE listing_pricing SET payment_methods = 'sinpe,paypal' WHERE id = 2;\n";
    echo "UPDATE listing_pricing SET payment_methods = 'sinpe,paypal' WHERE id = 3;\n";
    echo "</textarea>";
}

echo "<hr>";
echo "<p><a href='diagnose_bienes_raices.php'>← Volver al Diagnóstico</a></p>";
?>
