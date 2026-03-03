<?php
// Verificar usuario vanecastro@gmail.com
require_once __DIR__ . '/includes/db.php';

$pdo = db();
$email = 'vanecastro@gmail.com';

echo "=== VERIFICACIÓN DE USUARIO: $email ===\n\n";

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "✓ USUARIO ENCONTRADO\n\n";
    echo "ID: " . $user['id'] . "\n";
    echo "Nombre: " . $user['name'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Password Hash: " . (empty($user['password_hash']) ? 'VACÍO ❌' : 'Presente ✓ (' . strlen($user['password_hash']) . ' chars)') . "\n";
    echo "is_active: " . $user['is_active'] . " " . ($user['is_active'] == 1 ? '✓' : '❌') . "\n";
    echo "OAuth Provider: " . ($user['oauth_provider'] ?? 'NULL') . "\n";
    echo "Created: " . $user['created_at'] . "\n\n";

    // Probar la contraseña
    $testPassword = 'Compratica2024!';
    echo "=== PROBANDO CONTRASEÑA: $testPassword ===\n\n";

    if (!empty($user['password_hash'])) {
        $result = password_verify($testPassword, $user['password_hash']);
        echo "Resultado: " . ($result ? "✓ CONTRASEÑA CORRECTA" : "❌ CONTRASEÑA INCORRECTA") . "\n\n";

        if (!$result) {
            echo "La contraseña '$testPassword' NO coincide con el hash almacenado.\n";
            echo "Necesitas resetear la contraseña.\n\n";

            // Crear el hash correcto
            $correctHash = password_hash($testPassword, PASSWORD_BCRYPT);
            echo "Hash correcto para '$testPassword':\n";
            echo "$correctHash\n\n";

            echo "Para corregir, ejecuta:\n";
            echo "UPDATE users SET password_hash = '$correctHash' WHERE email = '$email';\n";
        }
    } else {
        echo "❌ El usuario no tiene password_hash almacenado.\n";
    }

    // Verificar is_active
    if ((int)$user['is_active'] !== 1) {
        echo "\n❌ PROBLEMA: El usuario NO está activo (is_active = " . $user['is_active'] . ")\n";
        echo "Para activar, ejecuta:\n";
        echo "UPDATE users SET is_active = 1 WHERE email = '$email';\n";
    }

} else {
    echo "❌ USUARIO NO ENCONTRADO\n\n";
    echo "El email '$email' no existe en la base de datos.\n";
    echo "Necesitas crear el usuario primero.\n";
}
?>
