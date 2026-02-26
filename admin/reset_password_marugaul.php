<?php
/**
 * Script para resetear contraseña y verificar estado de cuenta
 */

require_once __DIR__ . '/../includes/db.php';

$email = 'marugaul@gmail.com';
$nuevaPassword = 'TempPassword123!'; // Contraseña temporal

try {
    $pdo = db();

    echo "=== DIAGNÓSTICO Y RESET DE CUENTA ===\n\n";
    echo "Email: $email\n\n";

    // 1. Verificar estado actual
    $stmt = $pdo->prepare("SELECT id, email, name, is_active, password_hash, created_at FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "❌ ERROR: No se encontró la cuenta\n";
        exit(1);
    }

    echo "📊 Estado actual:\n";
    echo "   - ID: {$user['id']}\n";
    echo "   - Nombre: {$user['name']}\n";
    echo "   - is_active: {$user['is_active']} (tipo: " . gettype($user['is_active']) . ")\n";

    // Verificar el valor real de is_active
    $isActiveInt = (int)($user['is_active'] ?? 0);
    echo "   - is_active convertido a int: $isActiveInt\n";
    echo "   - ¿Pasa validación? " . ($isActiveInt === 1 ? "✅ SÍ" : "❌ NO") . "\n";
    echo "   - Hash de contraseña: " . (empty($user['password_hash']) ? "❌ VACÍO" : "✅ Existe") . "\n\n";

    // 2. Asegurar que esté activo
    if ($isActiveInt !== 1) {
        echo "🔧 Activando cuenta...\n";
        $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?")->execute([$user['id']]);
        echo "✅ Cuenta activada\n\n";
    } else {
        echo "✅ Cuenta ya está activa\n\n";
    }

    // 3. Resetear contraseña
    echo "🔑 Reseteando contraseña...\n";
    $newHash = password_hash($nuevaPassword, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $user['id']]);
    echo "✅ Contraseña actualizada\n\n";

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🎉 LISTO - Usa estas credenciales:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Email: $email\n";
    echo "Contraseña temporal: $nuevaPassword\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "⚠️  IMPORTANTE: Cambia esta contraseña después de iniciar sesión\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
