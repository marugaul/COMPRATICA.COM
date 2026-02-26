<?php
/**
 * Script para crear un espacio de venta activo para vanecastro@gmail.com
 */

require_once __DIR__ . '/../includes/db.php';

$email = 'vanecastro@gmail.com';

try {
    $pdo = db();

    echo "=== CREANDO ESPACIO DE VENTA ===\n\n";

    // 1. Buscar el usuario
    $userStmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $userStmt->execute([$email]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "❌ ERROR: Usuario no encontrado\n";
        exit(1);
    }

    echo "👤 Usuario: {$user['name']} (ID: {$user['id']})\n\n";

    // 2. Verificar si ya tiene espacios
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE affiliate_id = ?");
    $checkStmt->execute([$user['id']]);
    $existingCount = $checkStmt->fetchColumn();

    echo "📊 Espacios existentes: $existingCount\n\n";

    // 3. Crear un nuevo espacio de venta ACTIVO
    $title = "Espacio de Venta - " . $user['name'];
    $startDate = date('Y-m-d H:i:s'); // Hoy
    $endDate = date('Y-m-d H:i:s', strtotime('+90 days')); // 90 días desde hoy
    $now = date('Y-m-d H:i:s');

    echo "📦 Creando espacio de venta:\n";
    echo "   Título: $title\n";
    echo "   Inicio: $startDate\n";
    echo "   Fin: $endDate (90 días)\n";
    echo "   Estado: ACTIVO (is_active = 1)\n\n";

    $insertStmt = $pdo->prepare("
        INSERT INTO sales (affiliate_id, title, start_at, end_at, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, 1, ?, ?)
    ");

    $insertStmt->execute([
        $user['id'],
        $title,
        $startDate,
        $endDate,
        $now,
        $now
    ]);

    $newSpaceId = $pdo->lastInsertId();

    echo "✅ Espacio creado exitosamente\n";
    echo "   ID del espacio: $newSpaceId\n\n";

    // 4. Verificar espacios activos
    $activeStmt = $pdo->prepare("
        SELECT id, title, start_at, end_at, is_active
        FROM sales
        WHERE affiliate_id = ? AND is_active = 1
    ");
    $activeStmt->execute([$user['id']]);
    $activeSpaces = $activeStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ ESPACIOS ACTIVOS: " . count($activeSpaces) . "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    foreach ($activeSpaces as $space) {
        echo "  📦 ID {$space['id']}: {$space['title']}\n";
        echo "     {$space['start_at']} → {$space['end_at']}\n\n";
    }

    echo "🎉 El afiliado ahora puede crear productos en /affiliate/products.php\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
