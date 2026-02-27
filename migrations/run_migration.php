<?php
// Script para ejecutar la migración de campos configurables en planes
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

try {
    echo "Iniciando migración...\n\n";

    // Verificar si listing_pricing existe
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='listing_pricing'")->fetchAll();

    if (empty($tables)) {
        echo "❌ ERROR: La tabla listing_pricing no existe.\n";
        echo "   Por favor, ejecuta primero instalar-bienes-raices.php\n\n";
        exit(1);
    }

    // Verificar qué columnas ya existen
    $columns = $pdo->query("PRAGMA table_info(listing_pricing)")->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'name');

    echo "Columnas existentes: " . implode(', ', $existingColumns) . "\n\n";

    // Agregar columna max_photos si no existe
    if (!in_array('max_photos', $existingColumns)) {
        echo "➕ Agregando columna max_photos...\n";
        $pdo->exec("ALTER TABLE listing_pricing ADD COLUMN max_photos INTEGER DEFAULT 3");
        echo "✅ Columna max_photos agregada\n\n";
    } else {
        echo "ℹ️  La columna max_photos ya existe\n\n";
    }

    // Agregar columna payment_methods si no existe
    if (!in_array('payment_methods', $existingColumns)) {
        echo "➕ Agregando columna payment_methods...\n";
        $pdo->exec("ALTER TABLE listing_pricing ADD COLUMN payment_methods TEXT DEFAULT 'sinpe,paypal'");
        echo "✅ Columna payment_methods agregada\n\n";
    } else {
        echo "ℹ️  La columna payment_methods ya existe\n\n";
    }

    // Actualizar planes existentes con valores por defecto
    echo "🔄 Actualizando planes existentes...\n";

    $plans = $pdo->query("SELECT id, name, max_photos, payment_methods FROM listing_pricing")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($plans as $plan) {
        $needsUpdate = false;
        $updates = [];
        $params = [];

        // Asignar max_photos según el plan
        if ($plan['max_photos'] === null || $plan['max_photos'] == 0) {
            if (stripos($plan['name'], 'gratis') !== false || stripos($plan['name'], '7') !== false) {
                $updates[] = "max_photos = ?";
                $params[] = 3;
            } elseif (stripos($plan['name'], '30') !== false) {
                $updates[] = "max_photos = ?";
                $params[] = 5;
            } elseif (stripos($plan['name'], '90') !== false) {
                $updates[] = "max_photos = ?";
                $params[] = 8;
            } else {
                $updates[] = "max_photos = ?";
                $params[] = 3;
            }
            $needsUpdate = true;
        }

        // Asignar payment_methods si está vacío
        if (empty($plan['payment_methods'])) {
            $updates[] = "payment_methods = ?";
            $params[] = 'sinpe,paypal';
            $needsUpdate = true;
        }

        if ($needsUpdate) {
            $params[] = $plan['id'];
            $sql = "UPDATE listing_pricing SET " . implode(', ', $updates) . " WHERE id = ?";
            $pdo->prepare($sql)->execute($params);
            echo "  ✅ Plan '{$plan['name']}' (ID: {$plan['id']}) actualizado\n";
        } else {
            echo "  ℹ️  Plan '{$plan['name']}' (ID: {$plan['id']}) ya configurado\n";
        }
    }

    echo "\n📊 Estado final de los planes:\n";
    echo "┌─────┬─────────────────────────┬──────────────┬──────────┬─────────────────────┐\n";
    echo "│ ID  │ Nombre                  │ Días         │ Fotos    │ Métodos de pago     │\n";
    echo "├─────┼─────────────────────────┼──────────────┼──────────┼─────────────────────┤\n";

    $finalPlans = $pdo->query("SELECT id, name, duration_days, max_photos, payment_methods FROM listing_pricing ORDER BY display_order")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($finalPlans as $plan) {
        printf("│ %-3d │ %-23s │ %-12d │ %-8d │ %-19s │\n",
            $plan['id'],
            substr($plan['name'], 0, 23),
            $plan['duration_days'],
            $plan['max_photos'],
            substr($plan['payment_methods'], 0, 19)
        );
    }

    echo "└─────┴─────────────────────────┴──────────────┴──────────┴─────────────────────┘\n\n";

    echo "✅ Migración completada exitosamente\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
