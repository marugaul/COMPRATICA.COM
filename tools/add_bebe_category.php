<?php
/**
 * Script para habilitar/agregar la categoría "Bebé" en Emprendedoras
 */

require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();

    echo "🔍 Verificando categoría 'Bebé'...\n\n";

    // Verificar si la categoría ya existe
    $stmt = $pdo->prepare("SELECT * FROM entrepreneur_categories WHERE slug = ?");
    $stmt->execute(['bebe']);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo "✅ La categoría 'Bebé' ya existe!\n";
        echo "   ID: {$existing['id']}\n";
        echo "   Nombre: {$existing['name']}\n";
        echo "   Estado actual: " . ($existing['is_active'] ? 'Activa' : 'Inactiva') . "\n\n";

        // Si está inactiva, activarla
        if (!$existing['is_active']) {
            $pdo->prepare("UPDATE entrepreneur_categories SET is_active = 1 WHERE id = ?")
                ->execute([$existing['id']]);
            echo "✅ ¡Categoría 'Bebé' HABILITADA exitosamente!\n";
        } else {
            echo "✅ La categoría 'Bebé' ya está habilitada.\n";
        }
    } else {
        echo "➕ La categoría 'Bebé' no existe, creándola...\n\n";

        // Obtener el último display_order
        $maxOrder = $pdo->query("SELECT MAX(display_order) as max_order FROM entrepreneur_categories")->fetch();
        $newOrder = ($maxOrder['max_order'] ?? 0) + 1;

        // Insertar la nueva categoría
        $stmt = $pdo->prepare("
            INSERT INTO entrepreneur_categories (name, slug, description, icon, display_order, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
        ");

        $stmt->execute([
            'Bebé',
            'bebe',
            'Productos para bebés y mamás',
            'fa-baby',
            $newOrder
        ]);

        $newId = $pdo->lastInsertId();

        echo "✅ ¡Categoría 'Bebé' CREADA Y HABILITADA exitosamente!\n";
        echo "   ID: {$newId}\n";
        echo "   Nombre: Bebé\n";
        echo "   Slug: bebe\n";
        echo "   Descripción: Productos para bebés y mamás\n";
        echo "   Icono: fa-baby\n";
        echo "   Orden: {$newOrder}\n";
    }

    echo "\n" . str_repeat("=", 50) . "\n";
    echo "📊 CATEGORÍAS ACTUALES:\n";
    echo str_repeat("=", 50) . "\n\n";

    // Mostrar todas las categorías activas
    $categories = $pdo->query("
        SELECT id, name, slug, is_active, display_order
        FROM entrepreneur_categories
        ORDER BY display_order
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($categories as $cat) {
        $status = $cat['is_active'] ? '✅ Activa' : '❌ Inactiva';
        echo sprintf("  [%2d] %-30s (%-20s) %s\n",
            $cat['id'],
            $cat['name'],
            $cat['slug'],
            $status
        );
    }

    echo "\n✨ ¡Proceso completado exitosamente!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
