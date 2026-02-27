<?php
// Script para eliminar planes de precios duplicados
// Este script debe ejecutarse una sola vez

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

echo "<h2>Verificando planes duplicados...</h2>";

try {
    // Obtener todos los planes
    $stmt = $pdo->query("SELECT * FROM listing_pricing ORDER BY id ASC");
    $allPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Total de planes encontrados: " . count($allPlans) . "</p>";

    // Agrupar por nombre para encontrar duplicados
    $plansByName = [];
    foreach ($allPlans as $plan) {
        $name = $plan['name'];
        if (!isset($plansByName[$name])) {
            $plansByName[$name] = [];
        }
        $plansByName[$name][] = $plan;
    }

    echo "<h3>Planes agrupados por nombre:</h3><ul>";
    foreach ($plansByName as $name => $plans) {
        echo "<li><strong>$name</strong>: " . count($plans) . " registro(s)";
        if (count($plans) > 1) {
            echo " <span style='color: red;'>(DUPLICADO)</span>";
        }
        echo "</li>";
    }
    echo "</ul>";

    // Eliminar duplicados (mantener solo el primero de cada grupo)
    $deletedCount = 0;
    foreach ($plansByName as $name => $plans) {
        if (count($plans) > 1) {
            // Mantener el primero, eliminar los demás
            $keepId = $plans[0]['id'];
            echo "<p><strong>Manteniendo plan '$name' con ID: $keepId</strong></p>";

            for ($i = 1; $i < count($plans); $i++) {
                $deleteId = $plans[$i]['id'];

                // Verificar si hay propiedades usando este plan
                $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM real_estate_listings WHERE pricing_plan_id = ?");
                $checkStmt->execute([$deleteId]);
                $usageCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];

                if ($usageCount > 0) {
                    // Actualizar las propiedades para usar el plan que vamos a mantener
                    echo "<p>- Actualizando $usageCount propiedad(es) del plan ID $deleteId al ID $keepId...</p>";
                    $updateStmt = $pdo->prepare("UPDATE real_estate_listings SET pricing_plan_id = ? WHERE pricing_plan_id = ?");
                    $updateStmt->execute([$keepId, $deleteId]);
                }

                // Eliminar el plan duplicado
                echo "<p>- Eliminando plan duplicado con ID: $deleteId...</p>";
                $deleteStmt = $pdo->prepare("DELETE FROM listing_pricing WHERE id = ?");
                $deleteStmt->execute([$deleteId]);
                $deletedCount++;
            }
        }
    }

    if ($deletedCount > 0) {
        echo "<h3 style='color: green;'>✓ Se eliminaron $deletedCount plan(es) duplicado(s) exitosamente.</h3>";
    } else {
        echo "<h3 style='color: green;'>✓ No se encontraron planes duplicados.</h3>";
    }

    // Mostrar planes finales
    echo "<h3>Planes finales en la base de datos:</h3><ul>";
    $finalStmt = $pdo->query("SELECT * FROM listing_pricing ORDER BY display_order ASC");
    $finalPlans = $finalStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($finalPlans as $plan) {
        echo "<li>ID: {$plan['id']} - {$plan['name']} ({$plan['duration_days']} días) - \${$plan['price_usd']} / ₡{$plan['price_crc']}</li>";
    }
    echo "</ul>";

    echo "<p><a href='dashboard.php'>← Volver al Dashboard</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
