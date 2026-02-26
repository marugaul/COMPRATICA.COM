<?php
/**
 * Activar el espacio ID 15 para vanecastro@gmail.com
 */

require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();

    echo "=== ACTIVACIÓN DEL ESPACIO ID 15 ===\n\n";

    // 1. Verificar el espacio
    $space = $pdo->query("SELECT * FROM sales WHERE id = 15")->fetch(PDO::FETCH_ASSOC);

    if (!$space) {
        echo "❌ ERROR: Espacio ID 15 no encontrado\n";
        exit(1);
    }

    echo "📦 Espacio actual:\n";
    echo "   ID: {$space['id']}\n";
    echo "   Título: {$space['title']}\n";
    echo "   affiliate_id: {$space['affiliate_id']}\n";
    echo "   is_active: {$space['is_active']} (0 = inactivo, 1 = activo)\n";
    echo "   Fechas: {$space['start_at']} → {$space['end_at']}\n\n";

    // 2. Verificar el dueño
    $owner = $pdo->query("SELECT email, name FROM users WHERE id = {$space['affiliate_id']}")->fetch(PDO::FETCH_ASSOC);

    if ($owner) {
        echo "👤 Dueño: {$owner['name']} ({$owner['email']})\n\n";
    }

    // 3. Activar
    if ((int)$space['is_active'] === 1) {
        echo "✅ El espacio YA está activo\n";
    } else {
        echo "🔧 Activando espacio...\n";
        $pdo->prepare("UPDATE sales SET is_active = 1, updated_at = datetime('now') WHERE id = 15")->execute();
        echo "✅ Espacio activado exitosamente\n\n";

        // Verificar
        $updated = $pdo->query("SELECT is_active FROM sales WHERE id = 15")->fetch(PDO::FETCH_ASSOC);
        echo "📊 Estado final: is_active = {$updated['is_active']}\n";
    }

    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ El afiliado puede crear productos ahora\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
