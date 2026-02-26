<?php
/**
 * Actualizar el espacio ID 21 para usar el affiliate_id correcto
 */

require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();

    echo "=== CORRECCIÓN DEL ESPACIO ID 21 ===\n\n";

    // 1. Verificar el espacio actual
    $space = $pdo->query("SELECT * FROM sales WHERE id = 21")->fetch(PDO::FETCH_ASSOC);

    if (!$space) {
        echo "❌ ERROR: Espacio ID 21 no encontrado\n";
        exit(1);
    }

    echo "📦 Estado actual del espacio:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "   ID: {$space['id']}\n";
    echo "   Título: {$space['title']}\n";
    echo "   affiliate_id: {$space['affiliate_id']} ← INCORRECTO (tabla affiliates)\n";
    echo "   is_active: {$space['is_active']}\n";
    echo "   Fechas: {$space['start_at']} → {$space['end_at']}\n\n";

    // 2. Verificar quién es affiliate_id = 8
    $aff8 = $pdo->query("SELECT email, name FROM affiliates WHERE id = 8")->fetch(PDO::FETCH_ASSOC);
    if ($aff8) {
        echo "👤 Afiliado ID 8 (tabla affiliates):\n";
        echo "   Email: {$aff8['email']}\n";
        echo "   Nombre: {$aff8['name']}\n\n";
    }

    // 3. Verificar el ID correcto en users
    $user = $pdo->query("SELECT id, email, name FROM users WHERE email = 'vanecastro@gmail.com'")->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        echo "👤 Usuario en tabla 'users':\n";
        echo "   ID: {$user['id']} ← CORRECTO (usado por el sistema de login)\n";
        echo "   Email: {$user['email']}\n";
        echo "   Nombre: {$user['name']}\n\n";
    }

    // 4. Actualizar el affiliate_id
    echo "🔧 ACTUALIZANDO...\n";
    echo "   affiliate_id: 8 → 416\n\n";

    $pdo->beginTransaction();

    $updateStmt = $pdo->prepare("UPDATE sales SET affiliate_id = ?, updated_at = datetime('now') WHERE id = 21");
    $updateStmt->execute([416]);

    $pdo->commit();

    echo "✅ ACTUALIZACIÓN COMPLETADA\n\n";

    // 5. Verificar el resultado
    $updated = $pdo->query("SELECT id, affiliate_id, title, is_active FROM sales WHERE id = 21")->fetch(PDO::FETCH_ASSOC);

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📊 Estado final del espacio:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "   ID: {$updated['id']}\n";
    echo "   Título: {$updated['title']}\n";
    echo "   affiliate_id: {$updated['affiliate_id']} ✅ CORRECTO\n";
    echo "   is_active: {$updated['is_active']} ✅ ACTIVO\n\n";

    // 6. Verificar todos los espacios activos de vanecastro
    echo "📦 Todos los espacios activos de vanecastro (affiliate_id=416):\n";
    $allSpaces = $pdo->query("SELECT id, title, is_active FROM sales WHERE affiliate_id = 416 AND is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allSpaces as $sp) {
        echo "   ✅ ID {$sp['id']}: {$sp['title']}\n";
    }

    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🎉 LISTO - vanecastro puede crear productos en el espacio ID 21\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
