<?php
/**
 * Script para diagnosticar y resolver problema de espacios de venta
 * para el afiliado vanecastro@gmail.com
 */

require_once __DIR__ . '/../includes/db.php';

$email = 'vanecastro@gmail.com';

try {
    $pdo = db();

    echo "=== DIAGNÓSTICO DE ESPACIOS DE VENTA ===\n\n";
    echo "Afiliado: $email\n\n";

    // 1. Buscar el usuario y afiliado
    $userStmt = $pdo->prepare("SELECT id, email, name, is_active FROM users WHERE email = ?");
    $userStmt->execute([$email]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "❌ ERROR: No se encontró el usuario con email $email\n";
        exit(1);
    }

    echo "👤 Usuario:\n";
    echo "   - ID: {$user['id']}\n";
    echo "   - Nombre: {$user['name']}\n";
    echo "   - Cuenta activa: " . ($user['is_active'] ? "✅ Sí" : "❌ No") . "\n\n";

    // 2. Usar el ID del usuario como affiliate_id
    // (En este sistema, users.id = affiliate_id en la tabla sales)
    $aff_id = $user['id'];

    // 3. Buscar espacios de venta
    echo "📦 ESPACIOS DE VENTA:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    $spacesStmt = $pdo->prepare("
        SELECT id, title, start_at, end_at, is_active, created_at
        FROM sales
        WHERE affiliate_id = ?
        ORDER BY created_at DESC
    ");
    $spacesStmt->execute([$aff_id]);
    $spaces = $spacesStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($spaces)) {
        echo "❌ No tiene ningún espacio de venta creado\n\n";
        echo "💡 SOLUCIÓN: El afiliado necesita crear un espacio de venta en 'Mis Espacios'\n";
        exit(0);
    }

    $activeCount = 0;
    $inactiveCount = 0;

    foreach ($spaces as $space) {
        $status = $space['is_active'] ? "✅ ACTIVO" : "❌ INACTIVO";
        $activeCount += $space['is_active'] ? 1 : 0;
        $inactiveCount += $space['is_active'] ? 0 : 1;

        echo "\n{$status}\n";
        echo "  ID: {$space['id']}\n";
        echo "  Título: {$space['title']}\n";
        echo "  Fechas: {$space['start_at']} → {$space['end_at']}\n";
        echo "  Creado: {$space['created_at']}\n";

        // Verificar si tiene pagos pendientes
        $feeStmt = $pdo->prepare("
            SELECT id, amount_crc, amount_usd, status, created_at
            FROM sale_fees
            WHERE sale_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $feeStmt->execute([$space['id']]);
        $fee = $feeStmt->fetch(PDO::FETCH_ASSOC);

        if ($fee) {
            echo "  💳 Fee: ₡{$fee['amount_crc']} / \${$fee['amount_usd']} - Estado: {$fee['status']}\n";
        } else {
            echo "  💳 Sin registro de pago (espacio creado directamente por admin)\n";
        }
    }

    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📊 RESUMEN:\n";
    echo "   Total de espacios: " . count($spaces) . "\n";
    echo "   Espacios ACTIVOS: $activeCount\n";
    echo "   Espacios INACTIVOS: $inactiveCount\n\n";

    if ($activeCount > 0) {
        echo "✅ El afiliado tiene espacios activos\n";
        echo "   El problema podría ser de sesión o caché del navegador\n";
    } else {
        echo "❌ PROBLEMA ENCONTRADO: No tiene espacios activos\n\n";
        echo "💡 SOLUCIONES POSIBLES:\n";
        echo "   1. Activar manualmente un espacio existente (requiere aprobación de pago)\n";
        echo "   2. El afiliado debe pagar el fee pendiente si existe\n";
        echo "   3. Admin debe aprobar el pago en el panel de administración\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
