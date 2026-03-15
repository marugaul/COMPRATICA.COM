#!/usr/bin/env php
<?php
/**
 * Script para actualizar empleos de Telegram existentes
 * Extrae URLs de las descripciones y los guarda en application_url
 */

require_once __DIR__ . '/../includes/db.php';

echo "=== Actualizando empleos de Telegram ===\n\n";

$pdo = db();

try {
    // Obtener todos los empleos importados de Telegram
    $stmt = $pdo->query("
        SELECT id, title, description, application_url
        FROM job_listings
        WHERE import_source LIKE 'Telegram_%'
          AND is_active = 1
    ");

    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = count($jobs);
    $updated = 0;
    $skipped = 0;

    echo "Encontrados {$total} empleos de Telegram\n\n";

    foreach ($jobs as $job) {
        // Si ya tiene application_url, saltar
        if (!empty($job['application_url'])) {
            $skipped++;
            continue;
        }

        // Buscar URL en la descripción
        if (preg_match('/(https?:\/\/[^\s<>"\']+)/i', $job['description'], $urlMatch)) {
            $applicationUrl = $urlMatch[1];

            // Actualizar el empleo
            $updateStmt = $pdo->prepare("
                UPDATE job_listings
                SET application_url = ?
                WHERE id = ?
            ");

            $updateStmt->execute([$applicationUrl, $job['id']]);
            $updated++;

            echo "✓ {$job['title']}\n";
            echo "  URL: {$applicationUrl}\n\n";
        } else {
            $skipped++;
            echo "⚠ {$job['title']} - No se encontró URL\n\n";
        }
    }

    echo "\n=== Resumen ===\n";
    echo "Total procesados: {$total}\n";
    echo "Actualizados: {$updated}\n";
    echo "Sin cambios: {$skipped}\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
