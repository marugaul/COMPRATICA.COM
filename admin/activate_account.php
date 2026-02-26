<?php
/**
 * Script para verificar y activar cuenta de usuario
 */

require_once __DIR__ . '/../includes/db.php';

$email = 'marugaul@gmail.com';

try {
    $pdo = db();

    // Verificar estado actual
    echo "=== Verificando cuenta: $email ===\n\n";

    $stmt = $pdo->prepare("SELECT id, email, name, is_active, created_at FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "ERROR: No se encontró ninguna cuenta con el email $email\n";
        exit(1);
    }

    echo "Información de la cuenta:\n";
    echo "- ID: {$user['id']}\n";
    echo "- Email: {$user['email']}\n";
    echo "- Nombre: {$user['name']}\n";
    echo "- Estado actual (is_active): {$user['is_active']}\n";
    echo "- Creada el: {$user['created_at']}\n\n";

    if ((int)$user['is_active'] === 1) {
        echo "✓ La cuenta YA está activa (is_active = 1)\n";
        echo "El problema podría ser otro. Verifica:\n";
        echo "  1. Que la contraseña sea correcta\n";
        echo "  2. Que no haya problemas de sesión/cookies\n";
    } else {
        echo "✗ La cuenta NO está activa (is_active = {$user['is_active']})\n";
        echo "\nActivando cuenta...\n";

        $updateStmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
        $updateStmt->execute([$user['id']]);

        echo "✓ Cuenta activada exitosamente\n";
        echo "\nAhora puedes iniciar sesión con tu cuenta.\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
