<?php
// Script de prueba de autenticación
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/user_auth.php';

echo "<h2>Test de Autenticación</h2>\n\n";

// Probar con el usuario principal
$testEmail = 'marugaul@gmail.com';

echo "<h3>Probando autenticación para: $testEmail</h3>\n";

// Obtener usuario
$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$testEmail]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "<p>✓ Usuario encontrado</p>\n";
    echo "<pre>";
    echo "ID: " . $user['id'] . "\n";
    echo "Nombre: " . $user['name'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Password Hash: " . (empty($user['password_hash']) ? '<span style="color:red">VACÍO</span>' : '<span style="color:green">Presente (' . strlen($user['password_hash']) . ' chars)</span>') . "\n";
    echo "is_active: " . $user['is_active'] . " (" . ($user['is_active'] == 1 ? 'Activo' : 'Inactivo') . ")\n";
    echo "oauth_provider: " . ($user['oauth_provider'] ?? 'NULL') . "\n";
    echo "</pre>\n";

    // Probar algunas contraseñas comunes
    $testPasswords = ['password', '123456', 'admin', 'marugaul'];

    echo "<h4>Probando contraseñas comunes:</h4>\n";
    foreach ($testPasswords as $testPass) {
        $result = password_verify($testPass, $user['password_hash']);
        echo "<p>Contraseña '$testPass': " . ($result ? '<span style="color:green">✓ VÁLIDA</span>' : '<span style="color:red">✗ Inválida</span>') . "</p>\n";
    }

    echo "\n<h4>Para resetear la contraseña:</h4>\n";
    echo "<form method='post'>\n";
    echo "<input type='hidden' name='email' value='" . htmlspecialchars($testEmail) . "'>\n";
    echo "<label>Nueva contraseña:</label>\n";
    echo "<input type='text' name='new_password' value='password123'>\n";
    echo "<button type='submit' name='reset'>Resetear Contraseña</button>\n";
    echo "</form>\n";

} else {
    echo "<p style='color:red'>✗ Usuario NO encontrado</p>\n";
}

// Procesar reset de contraseña
if (isset($_POST['reset'])) {
    $email = $_POST['email'];
    $newPass = $_POST['new_password'];

    $newHash = password_hash($newPass, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $result = $stmt->execute([$newHash, $email]);

    if ($result) {
        echo "<div style='background:#d4edda;padding:10px;border:1px solid #c3e6cb;color:#155724;margin:10px 0;'>";
        echo "✓ Contraseña reseteada exitosamente para $email<br>";
        echo "Nueva contraseña: <strong>$newPass</strong>";
        echo "</div>";

        // Verificar que funciona
        $verifyResult = password_verify($newPass, $newHash);
        echo "<p>Verificación: " . ($verifyResult ? '<span style="color:green">✓ Contraseña válida</span>' : '<span style="color:red">✗ Error</span>') . "</p>\n";
    } else {
        echo "<div style='background:#f8d7da;padding:10px;border:1px solid #f5c6cb;color:#721c24;margin:10px 0;'>";
        echo "✗ Error al resetear contraseña";
        echo "</div>";
    }
}
?>
