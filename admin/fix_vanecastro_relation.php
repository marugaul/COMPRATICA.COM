<?php
/**
 * Corregir la relación entre users y affiliates para vanecastro@gmail.com
 */

require_once __DIR__ . '/../includes/db.php';

$email = 'vanecastro@gmail.com';

try {
    $pdo = db();

    echo "=== CORRECCIÓN DE RELACIÓN USERS/AFFILIATES ===\n\n";

    // 1. Verificar estado actual
    $user = $pdo->prepare("SELECT id FROM users WHERE email = ?")->execute([$email])
            ? $pdo->query("SELECT id FROM users WHERE email = '$email'")->fetch() : null;

    $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $userStmt->execute([$email]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    $affStmt = $pdo->prepare("SELECT id FROM affiliates WHERE email = ?");
    $affStmt->execute([$email]);
    $affiliate = $affStmt->fetch(PDO::FETCH_ASSOC);

    $user_id = $user['id'];
    $aff_id = $affiliate['id'];

    echo "👤 Usuario en tabla 'users': ID = $user_id\n";
    echo "🏢 Afiliado en tabla 'affiliates': ID = $aff_id\n\n";

    // 2. Verificar espacios con cada ID
    echo "📦 ESPACIOS ACTUALES:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    echo "A) Con affiliate_id = $aff_id (tabla affiliates):\n";
    $spacesAff = $pdo->prepare("SELECT id, title, is_active FROM sales WHERE affiliate_id = ?");
    $spacesAff->execute([$aff_id]);
    $spacesWithAffId = $spacesAff->fetchAll(PDO::FETCH_ASSOC);

    if (empty($spacesWithAffId)) {
        echo "   ❌ No hay espacios\n\n";
    } else {
        foreach ($spacesWithAffId as $sp) {
            $status = $sp['is_active'] ? "✅ ACTIVO" : "❌ INACTIVO";
            echo "   $status ID {$sp['id']}: {$sp['title']}\n";
        }
        echo "\n";
    }

    echo "B) Con affiliate_id = $user_id (tabla users):\n";
    $spacesUser = $pdo->prepare("SELECT id, title, is_active FROM sales WHERE affiliate_id = ?");
    $spacesUser->execute([$user_id]);
    $spacesWithUserId = $spacesUser->fetchAll(PDO::FETCH_ASSOC);

    if (empty($spacesWithUserId)) {
        echo "   ❌ No hay espacios\n\n";
    } else {
        foreach ($spacesWithUserId as $sp) {
            $status = $sp['is_active'] ? "✅ ACTIVO" : "❌ INACTIVO";
            echo "   $status ID {$sp['id']}: {$sp['title']}\n";
        }
        echo "\n";
    }

    // 3. Determinar la solución
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🔧 SOLUCIÓN:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    if (!empty($spacesWithAffId)) {
        echo "El afiliado tiene espacios con affiliate_id=$aff_id (tabla affiliates)\n";
        echo "pero el sistema busca con affiliate_id=$user_id (tabla users)\n\n";

        echo "Voy a actualizar los espacios para usar el ID correcto:\n\n";

        $pdo->beginTransaction();

        foreach ($spacesWithAffId as $sp) {
            echo "   🔄 Actualizando espacio ID {$sp['id']}: {$sp['title']}\n";
            echo "      affiliate_id: $aff_id → $user_id\n";

            $updateStmt = $pdo->prepare("UPDATE sales SET affiliate_id = ? WHERE id = ?");
            $updateStmt->execute([$user_id, $sp['id']]);
        }

        // También eliminar el espacio ID 19 que creé incorrectamente
        $deleteSpace19 = $pdo->prepare("DELETE FROM sales WHERE id = 19 AND affiliate_id = ?");
        $deleteSpace19->execute([$user_id]);
        echo "   🗑️  Eliminando espacio duplicado ID 19 (creado incorrectamente)\n";

        $pdo->commit();

        echo "\n✅ ACTUALIZACIÓN COMPLETADA\n\n";

        // Verificar resultado
        echo "📊 VERIFICACIÓN FINAL:\n";
        $finalCheck = $pdo->prepare("SELECT id, title, is_active FROM sales WHERE affiliate_id = ?");
        $finalCheck->execute([$user_id]);
        $finalSpaces = $finalCheck->fetchAll(PDO::FETCH_ASSOC);

        echo "   Espacios con affiliate_id=$user_id (correcto para el sistema):\n";
        foreach ($finalSpaces as $sp) {
            $status = $sp['is_active'] ? "✅ ACTIVO" : "❌ INACTIVO";
            echo "   $status ID {$sp['id']}: {$sp['title']}\n";
        }

        echo "\n✅ El afiliado ahora puede crear productos correctamente\n";

    } else {
        echo "⚠️  No hay espacios para migrar\n";
        echo "Los espacios ya están correctamente asignados\n";
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
