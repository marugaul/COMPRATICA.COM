<?php
/**
 * Buscar el espacio ID 218 y todos los espacios de vanecastro
 */

require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = db();

    echo "=== BÚSQUEDA COMPLETA DE ESPACIOS ===\n\n";

    // 1. Buscar espacio 218
    echo "1) Buscando espacio ID 218:\n";
    $sp218 = $pdo->query("SELECT * FROM sales WHERE id = 218")->fetch(PDO::FETCH_ASSOC);

    if ($sp218) {
        echo "   ✅ ENCONTRADO:\n";
        foreach ($sp218 as $key => $value) {
            echo "   - $key: $value\n";
        }
    } else {
        echo "   ❌ NO EXISTE en la base de datos actual\n";
    }
    echo "\n";

    // 2. Buscar todos los espacios con affiliate_id = 8 (ID en tabla affiliates)
    echo "2) Espacios con affiliate_id = 8 (ID de affiliates):\n";
    $spaces8 = $pdo->query("SELECT * FROM sales WHERE affiliate_id = 8")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($spaces8)) {
        echo "   ❌ No hay espacios\n";
    } else {
        foreach ($spaces8 as $sp) {
            $status = $sp['is_active'] ? "✅ ACTIVO" : "❌ INACTIVO";
            echo "   $status - ID {$sp['id']}: {$sp['title']}\n";
            echo "      Fechas: {$sp['start_at']} → {$sp['end_at']}\n";
        }
    }
    echo "\n";

    // 3. Buscar todos los espacios con affiliate_id = 416 (ID en tabla users)
    echo "3) Espacios con affiliate_id = 416 (ID de users):\n";
    $spaces416 = $pdo->query("SELECT * FROM sales WHERE affiliate_id = 416")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($spaces416)) {
        echo "   ❌ No hay espacios\n";
    } else {
        foreach ($spaces416 as $sp) {
            $status = $sp['is_active'] ? "✅ ACTIVO" : "❌ INACTIVO";
            echo "   $status - ID {$sp['id']}: {$sp['title']}\n";
            echo "      Fechas: {$sp['start_at']} → {$sp['end_at']}\n";
        }
    }
    echo "\n";

    // 4. Mostrar todos los IDs de sales
    echo "4) Todos los IDs en la tabla sales:\n";
    $allIds = $pdo->query("SELECT id, affiliate_id, title, is_active FROM sales ORDER BY id DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allIds as $sp) {
        $active = $sp['is_active'] ? "✅" : "❌";
        echo "   $active ID {$sp['id']}: affiliate_id={$sp['affiliate_id']}, {$sp['title']}\n";
    }
    echo "\n";

    // 5. Verificar email del afiliado ID 8
    echo "5) Verificación del afiliado ID 8 en tabla affiliates:\n";
    $aff8 = $pdo->query("SELECT id, email, name FROM affiliates WHERE id = 8")->fetch(PDO::FETCH_ASSOC);

    if ($aff8) {
        echo "   Email: {$aff8['email']}\n";
        echo "   Nombre: {$aff8['name']}\n";
    } else {
        echo "   ❌ No existe\n";
    }
    echo "\n";

    // 6. Verificar qué espacios están realmente ACTIVOS para vanecastro
    echo "6) SOLUCIÓN: Activar espacio correcto\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    if (!empty($spaces8)) {
        echo "   El afiliado tiene espacios con affiliate_id=8:\n";
        foreach ($spaces8 as $sp) {
            if ($sp['is_active'] == 0) {
                echo "   🔧 Espacio ID {$sp['id']} está INACTIVO - ¿Activar? (Sí/No)\n";
            }
        }
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
