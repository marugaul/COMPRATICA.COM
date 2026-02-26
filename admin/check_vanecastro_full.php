<?php
/**
 * Script para verificar completamente la relación entre users, affiliates y sales
 */

require_once __DIR__ . '/../includes/db.php';

$email = 'vanecastro@gmail.com';

try {
    $pdo = db();

    echo "=== VERIFICACIÓN COMPLETA ===\n\n";

    // 1. Usuario en tabla users
    $userStmt = $pdo->prepare("SELECT id, name, email, is_active FROM users WHERE email = ?");
    $userStmt->execute([$email]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "❌ Usuario no encontrado en tabla 'users'\n";
        exit(1);
    }

    echo "👤 Tabla USERS:\n";
    echo "   ID: {$user['id']}\n";
    echo "   Email: {$user['email']}\n";
    echo "   Nombre: {$user['name']}\n";
    echo "   Activo: " . ($user['is_active'] ? "Sí" : "No") . "\n\n";

    // 2. Verificar si existe tabla affiliates y buscar el registro
    echo "🏢 Tabla AFFILIATES:\n";

    // Primero verificar si la tabla existe
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='affiliates'")->fetchAll();

    if (empty($tables)) {
        echo "   ⚠️  La tabla 'affiliates' NO EXISTE\n";
        echo "   Se usa directamente users.id como affiliate_id\n\n";
        $affiliate_id = $user['id'];
    } else {
        // Buscar por email en affiliates
        $affStmt = $pdo->prepare("SELECT * FROM affiliates WHERE email = ?");
        $affStmt->execute([$email]);
        $affiliate = $affStmt->fetch(PDO::FETCH_ASSOC);

        if ($affiliate) {
            echo "   ✅ Encontrado:\n";
            foreach ($affiliate as $key => $value) {
                echo "   - $key: $value\n";
            }
            $affiliate_id = $affiliate['id'];
        } else {
            echo "   ❌ No encontrado con email: $email\n";
            echo "   Buscando con user_id...\n\n";

            // Buscar todas las columnas de la tabla affiliates
            $cols = $pdo->query("PRAGMA table_info(affiliates)")->fetchAll();
            echo "   Columnas disponibles en 'affiliates':\n";
            foreach ($cols as $col) {
                echo "   - {$col['name']} ({$col['type']})\n";
            }

            $affiliate_id = $user['id'];
        }
        echo "\n";
    }

    echo "🔍 Usando affiliate_id: $affiliate_id\n\n";

    // 3. Buscar espacios con diferentes IDs
    echo "📦 BÚSQUEDA DE ESPACIOS:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    // Buscar con user_id
    echo "A) Buscando con affiliate_id = {$user['id']} (user_id):\n";
    $s1 = $pdo->prepare("SELECT id, title, is_active, affiliate_id FROM sales WHERE affiliate_id = ?");
    $s1->execute([$user['id']]);
    $spaces1 = $s1->fetchAll(PDO::FETCH_ASSOC);

    if (empty($spaces1)) {
        echo "   ❌ No se encontraron espacios\n\n";
    } else {
        foreach ($spaces1 as $sp) {
            echo "   ✅ ID {$sp['id']}: {$sp['title']} (activo: {$sp['is_active']})\n";
        }
        echo "\n";
    }

    // Buscar con el email directamente en todas las sales
    echo "B) Buscando con email '$email' en toda la tabla:\n";
    $allSpaces = $pdo->query("SELECT * FROM sales ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

    echo "   Últimos 10 espacios en la tabla:\n";
    foreach ($allSpaces as $sp) {
        echo "   - ID {$sp['id']}: affiliate_id={$sp['affiliate_id']}, title={$sp['title']}, activo={$sp['is_active']}\n";
    }
    echo "\n";

    // Buscar específicamente el espacio ID 218 que mencionó el usuario
    echo "C) Verificando espacio ID 218 (mencionado por el usuario):\n";
    $sp218 = $pdo->query("SELECT * FROM sales WHERE id = 218")->fetch(PDO::FETCH_ASSOC);

    if ($sp218) {
        echo "   ✅ Encontrado:\n";
        foreach ($sp218 as $key => $value) {
            echo "   - $key: $value\n";
        }
        echo "\n";

        // Verificar a quién pertenece ese affiliate_id
        echo "   🔍 Buscando dueño de affiliate_id {$sp218['affiliate_id']}:\n";

        // En users
        $owner = $pdo->prepare("SELECT id, email, name FROM users WHERE id = ?");
        $owner->execute([$sp218['affiliate_id']]);
        $ownerUser = $owner->fetch(PDO::FETCH_ASSOC);

        if ($ownerUser) {
            echo "   📧 En tabla USERS:\n";
            echo "      Email: {$ownerUser['email']}\n";
            echo "      Nombre: {$ownerUser['name']}\n";
        } else {
            echo "   ❌ No encontrado en tabla users con ID {$sp218['affiliate_id']}\n";
        }
    } else {
        echo "   ❌ No se encontró el espacio ID 218\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
