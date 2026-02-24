<?php
/**
 * Fix: Habilitar offers_services para afiliados con servicios activos
 *
 * Este script actualiza la columna offers_services = 1 para todos los afiliados
 * que tienen al menos un servicio activo en la tabla services.
 */

require_once __DIR__ . '/../includes/db.php';

echo "=== FIX: Habilitar offers_services para afiliados con servicios ===\n\n";

try {
    $pdo = db();

    // Verificar si la columna offers_services existe
    $columns = $pdo->query("PRAGMA table_info(affiliates)")->fetchAll(PDO::FETCH_ASSOC);
    $hasColumn = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'offers_services') {
            $hasColumn = true;
            break;
        }
    }

    if (!$hasColumn) {
        echo "ERROR: La columna offers_services no existe en la tabla affiliates.\n";
        echo "Ejecute primero tools/migrate_services.php\n";
        exit(1);
    }

    echo "✓ Columna offers_services existe\n\n";

    // Obtener afiliados con servicios
    $stmt = $pdo->query("
        SELECT DISTINCT a.id, a.name, COUNT(s.id) as service_count
        FROM affiliates a
        INNER JOIN services s ON s.affiliate_id = a.id
        WHERE s.is_active = 1
        GROUP BY a.id
    ");
    $affiliatesWithServices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($affiliatesWithServices)) {
        echo "No se encontraron afiliados con servicios activos.\n";
        exit(0);
    }

    echo "Afiliados con servicios activos:\n";
    foreach ($affiliatesWithServices as $aff) {
        echo "  - {$aff['name']} ({$aff['service_count']} servicios)\n";
    }
    echo "\n";

    // Actualizar offers_services = 1 para estos afiliados
    $updateStmt = $pdo->prepare("UPDATE affiliates SET offers_services = 1 WHERE id = ?");
    $updated = 0;

    foreach ($affiliatesWithServices as $aff) {
        $updateStmt->execute([$aff['id']]);
        if ($updateStmt->rowCount() > 0) {
            $updated++;
            echo "✓ Actualizado: {$aff['name']}\n";
        }
    }

    echo "\n=== RESULTADO ===\n";
    echo "Total de afiliados actualizados: $updated\n";

    // Verificar el resultado final
    $stmt = $pdo->query("
        SELECT COUNT(*) as total
        FROM services s
        INNER JOIN affiliates a ON a.id = s.affiliate_id
        WHERE s.is_active = 1
          AND a.is_active = 1
          AND a.offers_services = 1
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Servicios que ahora se mostrarán en /servicios: {$result['total']}\n";

    echo "\n✓ Fix completado exitosamente\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
