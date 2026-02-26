<?php
/**
 * Buscar específicamente el espacio ID 21
 */

require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();

    echo "=== BÚSQUEDA DEL ESPACIO ID 21 ===\n\n";

    // Buscar espacio 21
    $space = $pdo->query("SELECT * FROM sales WHERE id = 21")->fetch(PDO::FETCH_ASSOC);

    if (!$space) {
        echo "❌ NO SE ENCONTRÓ el espacio ID 21 en la base de datos\n\n";

        // Mostrar todos los espacios
        echo "📦 Todos los espacios en la tabla 'sales':\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $allSpaces = $pdo->query("SELECT id, affiliate_id, title, is_active FROM sales ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allSpaces as $sp) {
            $status = $sp['is_active'] ? "✅" : "❌";
            echo "   $status ID {$sp['id']}: affiliate_id={$sp['affiliate_id']}, {$sp['title']}\n";
        }

    } else {
        echo "✅ ENCONTRADO - Espacio ID 21:\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        foreach ($space as $key => $value) {
            echo "   $key: $value\n";
        }

        echo "\n🔧 ¿Actualizar este espacio?\n";
        echo "   affiliate_id actual: {$space['affiliate_id']}\n";
        echo "   affiliate_id correcto: 416 (para que funcione con el login)\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
