<?php
/**
 * Renombrar categoría de "Shuttle Aeropuerto" a "Shuttle Pura Vida"
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

$pdo = db();
$now = date('Y-m-d H:i:s');

echo "==============================================\n";
echo "RENOMBRAR CATEGORÍA A SHUTTLE PURA VIDA\n";
echo "==============================================\n\n";

try {
    // Actualizar categoría
    $stmt = $pdo->prepare("
        UPDATE service_categories
        SET name = ?,
            description = ?,
            updated_at = ?
        WHERE slug = 'shuttle-aeropuerto'
    ");

    $stmt->execute([
        'Shuttle Pura Vida',
        'Transporte privado en Costa Rica. Aeropuertos, playas, hoteles y más. ¡Pura Vida!',
        $now
    ]);

    if ($stmt->rowCount() > 0) {
        echo "✅ Categoría actualizada exitosamente\n\n";
        echo "   Nombre nuevo: Shuttle Pura Vida\n";
        echo "   Descripción: Transporte privado en Costa Rica\n";
        echo "   Slug: shuttle-aeropuerto (mantiene el mismo)\n";
    } else {
        echo "⚠️  No se encontró la categoría o ya estaba actualizada\n";
    }

    echo "\n==============================================\n";
    echo "¡Listo! La categoría ahora se llama:\n";
    echo "🚐 Shuttle Pura Vida 🇨🇷\n";
    echo "==============================================\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
