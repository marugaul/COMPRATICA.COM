<?php
// Test para verificar max_photos
require_once __DIR__ . '/../includes/db.php';

echo "=== DIAGNÓSTICO MAX_PHOTOS ===\n\n";

try {
    // Obtener nueva instancia de PDO
    $pdo = db();

    echo "1. Base de datos en uso:\n";
    $dbPath = __DIR__ . '/../data.sqlite';
    echo "   Archivo: $dbPath\n";
    echo "   Existe: " . (file_exists($dbPath) ? "✅ Sí" : "❌ No") . "\n\n";

    echo "2. Verificando tabla listing_pricing:\n";
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='listing_pricing'")->fetchAll();

    if (empty($tables)) {
        echo "   ❌ La tabla NO existe\n\n";
        exit(1);
    }

    echo "   ✅ La tabla existe\n\n";

    echo "3. Columnas de listing_pricing:\n";
    $columns = $pdo->query("PRAGMA table_info(listing_pricing)")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        $marker = ($col['name'] === 'max_photos' || $col['name'] === 'payment_methods') ? '✅' : '  ';
        echo "   $marker {$col['name']} ({$col['type']})\n";
    }

    $columnNames = array_column($columns, 'name');

    echo "\n4. Verificación específica:\n";
    echo "   max_photos: " . (in_array('max_photos', $columnNames) ? "✅ EXISTE" : "❌ NO EXISTE") . "\n";
    echo "   payment_methods: " . (in_array('payment_methods', $columnNames) ? "✅ EXISTE" : "❌ NO EXISTE") . "\n\n";

    echo "5. Intentando SELECT con max_photos:\n";
    $plans = $pdo->query("SELECT id, name, max_photos, payment_methods FROM listing_pricing")->fetchAll(PDO::FETCH_ASSOC);

    echo "   ✅ SELECT exitoso\n";
    foreach ($plans as $plan) {
        echo "   - Plan {$plan['id']}: {$plan['name']} - max_photos={$plan['max_photos']}, payment_methods={$plan['payment_methods']}\n";
    }

    echo "\n6. Intentando UPDATE de prueba:\n";
    $testId = 1;
    $stmt = $pdo->prepare("
        UPDATE listing_pricing
        SET max_photos = ?,
            payment_methods = ?,
            updated_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([3, 'sinpe,paypal', $testId]);
    echo "   ✅ UPDATE exitoso\n\n";

    echo "=== TODO FUNCIONA CORRECTAMENTE ===\n";

} catch (Exception $e) {
    echo "\n❌ ERROR:\n";
    echo "   Mensaje: " . $e->getMessage() . "\n";
    echo "   Archivo: " . $e->getFile() . "\n";
    echo "   Línea: " . $e->getLine() . "\n\n";
    exit(1);
}
?>
