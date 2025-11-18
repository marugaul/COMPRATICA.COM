<?php
/**
 * Script para agregar la categoría "Turismo" a la base de datos
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

$pdo = db();

try {
    // Verificar si ya existe
    $stmt = $pdo->prepare("SELECT id FROM service_categories WHERE slug = ?");
    $stmt->execute(['turismo']);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo "✅ La categoría Turismo ya existe (ID: {$existing['id']})\n";
        exit(0);
    }

    // Crear categoría Turismo
    $stmt = $pdo->prepare("
        INSERT INTO service_categories
        (name, slug, description, icon, is_active, requires_online_payment, created_at, updated_at)
        VALUES (?, ?, ?, ?, 1, 1, ?, ?)
    ");

    $now = date('Y-m-d H:i:s');

    $stmt->execute([
        'Turismo',
        'turismo',
        'Transporte turístico: shuttles al aeropuerto, tours a playa, excursiones personalizadas. Reservá tu traslado con cotización automática.',
        'fas fa-plane-departure',
        $now,
        $now
    ]);

    $categoryId = $pdo->lastInsertId();

    echo "✅ Categoría Turismo creada exitosamente (ID: $categoryId)\n";
    echo "   • Nombre: Turismo\n";
    echo "   • Slug: turismo\n";
    echo "   • Requiere pago online: Sí\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
