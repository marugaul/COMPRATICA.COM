<?php
// Migration: Add pricing_plan_id to job_listings
// Date: 2026-02-27
// Description: Adds pricing plan reference to job and service listings

require_once __DIR__ . '/../includes/db.php';

$pdo = db();

try {
    echo "Iniciando migración para job_listings...\n\n";

    // Verificar si job_listings existe
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='job_listings'")->fetchAll();

    if (empty($tables)) {
        echo "❌ ERROR: La tabla job_listings no existe.\n";
        echo "   Por favor, ejecuta primero instalar-empleos-servicios.php\n\n";
        exit(1);
    }

    // Verificar qué columnas ya existen
    $columns = $pdo->query("PRAGMA table_info(job_listings)")->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'name');

    echo "Columnas existentes: " . implode(', ', $existingColumns) . "\n\n";

    // Agregar columna pricing_plan_id si no existe
    if (!in_array('pricing_plan_id', $existingColumns)) {
        echo "➕ Agregando columna pricing_plan_id...\n";

        // Primero, agregar la columna como nullable
        $pdo->exec("ALTER TABLE job_listings ADD COLUMN pricing_plan_id INTEGER");

        // Obtener el ID del plan gratuito para cada tipo
        $jobPlanId = null;
        $servicePlanId = null;

        // Buscar plan gratuito de empleos
        $stmt = $pdo->query("SELECT id FROM job_pricing WHERE is_active = 1 ORDER BY price_usd ASC, price_crc ASC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $jobPlanId = $result['id'];
            echo "  ✓ Plan de empleos encontrado: ID {$jobPlanId}\n";
        }

        // Buscar plan gratuito de servicios
        $stmt = $pdo->query("SELECT id FROM service_pricing WHERE is_active = 1 ORDER BY price_usd ASC, price_crc ASC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $servicePlanId = $result['id'];
            echo "  ✓ Plan de servicios encontrado: ID {$servicePlanId}\n";
        }

        // Actualizar registros existentes
        if ($jobPlanId) {
            $stmt = $pdo->prepare("UPDATE job_listings SET pricing_plan_id = ? WHERE listing_type = 'job' AND pricing_plan_id IS NULL");
            $stmt->execute([$jobPlanId]);
            $count = $stmt->rowCount();
            echo "  ✓ Actualizados {$count} empleos con plan ID {$jobPlanId}\n";
        }

        if ($servicePlanId) {
            $stmt = $pdo->prepare("UPDATE job_listings SET pricing_plan_id = ? WHERE listing_type = 'service' AND pricing_plan_id IS NULL");
            $stmt->execute([$servicePlanId]);
            $count = $stmt->rowCount();
            echo "  ✓ Actualizados {$count} servicios con plan ID {$servicePlanId}\n";
        }

        echo "✅ Columna pricing_plan_id agregada\n\n";
    } else {
        echo "ℹ️  La columna pricing_plan_id ya existe\n\n";
    }

    echo "📊 Estado de job_listings:\n";
    $stats = $pdo->query("
        SELECT
            listing_type,
            COUNT(*) as total,
            COUNT(pricing_plan_id) as with_plan,
            COUNT(*) - COUNT(pricing_plan_id) as without_plan
        FROM job_listings
        GROUP BY listing_type
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo "┌─────────────┬────────┬───────────┬──────────────┐\n";
    echo "│ Tipo        │ Total  │ Con Plan  │ Sin Plan     │\n";
    echo "├─────────────┼────────┼───────────┼──────────────┤\n";
    foreach ($stats as $row) {
        printf("│ %-11s │ %-6d │ %-9d │ %-12d │\n",
            $row['listing_type'],
            $row['total'],
            $row['with_plan'],
            $row['without_plan']
        );
    }
    echo "└─────────────┴────────┴───────────┴──────────────┘\n\n";

    echo "✅ Migración completada exitosamente\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
