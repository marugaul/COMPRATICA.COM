<?php
/**
 * Script para reorganizar categorías de servicios:
 * - Crear categoría "Shuttle Aeropuerto"
 * - Mover shuttles de Turismo a Shuttle Aeropuerto
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

$pdo = db();
$now = date('Y-m-d H:i:s');

echo "==============================================\n";
echo "REORGANIZACIÓN DE CATEGORÍAS - SHUTTLE\n";
echo "==============================================\n\n";

try {
    // 1. Crear categoría "Shuttle Aeropuerto"
    $stmt = $pdo->prepare("SELECT id FROM service_categories WHERE slug = ?");
    $stmt->execute(['shuttle-aeropuerto']);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $shuttleCategoryId = $existing['id'];
        echo "✅ Categoría 'Shuttle Aeropuerto' ya existe (ID: $shuttleCategoryId)\n";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO service_categories
            (name, slug, description, icon, is_active, requires_online_payment, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, 1, ?, ?)
        ");

        $stmt->execute([
            'Shuttle Aeropuerto',
            'shuttle-aeropuerto',
            'Transporte privado a aeropuertos de Costa Rica. Cotización automática según origen y destino. Puntualidad garantizada, seguimiento de vuelos.',
            'fas fa-shuttle-van',
            $now,
            $now
        ]);

        $shuttleCategoryId = $pdo->lastInsertId();
        echo "✅ Categoría 'Shuttle Aeropuerto' creada (ID: $shuttleCategoryId)\n";
    }

    // 2. Mover servicios de shuttle de Turismo a Shuttle Aeropuerto
    $shuttleSlugs = [
        'shuttle-aeropuerto-sjo',
        'shuttle-aeropuerto-lir'
    ];

    $movedCount = 0;
    foreach ($shuttleSlugs as $slug) {
        $stmt = $pdo->prepare("
            UPDATE services
            SET category_id = ?, updated_at = ?
            WHERE slug = ?
        ");
        $stmt->execute([$shuttleCategoryId, $now, $slug]);

        if ($stmt->rowCount() > 0) {
            echo "✅ Servicio '$slug' movido a Shuttle Aeropuerto\n";
            $movedCount++;
        } else {
            echo "⚠️  Servicio '$slug' no encontrado\n";
        }
    }

    echo "\n==============================================\n";
    echo "RESUMEN:\n";
    echo "   • Categoría Shuttle Aeropuerto: ✅\n";
    echo "   • $movedCount servicios movidos\n";
    echo "==============================================\n";
    echo "\n📍 Estructura actual:\n";
    echo "   • Turismo: Tours de playa y excursiones\n";
    echo "   • Shuttle Aeropuerto: Transporte a aeropuertos\n";
    echo "==============================================\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
