<?php
// Actualizar contraseña de vanecastro@gmail.com en la base de datos correcta
require_once __DIR__ . '/includes/db.php';

$email = 'vanecastro@gmail.com';
$newPassword = 'Compratica2024!';

echo "<h2>Actualizar Contraseña</h2>\n";
echo "<p>Email: <strong>$email</strong></p>\n";
echo "<p>Nueva contraseña: <strong>$newPassword</strong></p>\n\n";

try {
    $pdo = db();

    // Verificar usuario actual
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "<p style='color:red;'>❌ Usuario no encontrado</p>\n";
        exit;
    }

    echo "<h3>Usuario encontrado:</h3>\n";
    echo "<pre>";
    echo "ID: {$user['id']}\n";
    echo "Nombre: {$user['name']}\n";
    echo "Email: {$user['email']}\n";
    echo "</pre>\n";

    // Generar nuevo hash
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);

    echo "<h3>Actualizando contraseña...</h3>\n";

    // Actualizar
    $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $result = $updateStmt->execute([$newHash, $email]);

    if ($result) {
        echo "<div style='background:#d4edda;padding:15px;border:1px solid #c3e6cb;color:#155724;margin:10px 0;'>\n";
        echo "<h3>✓ Contraseña actualizada exitosamente</h3>\n";
        echo "<p>Ahora puedes iniciar sesión con:</p>\n";
        echo "<ul>\n";
        echo "<li>Email: <strong>$email</strong></li>\n";
        echo "<li>Contraseña: <strong>$newPassword</strong></li>\n";
        echo "</ul>\n";
        echo "</div>\n";

        // Verificar que funciona
        echo "<h3>Verificación:</h3>\n";
        $verify = password_verify($newPassword, $newHash);
        echo "<p>password_verify(): " . ($verify ? '✓ TRUE' : '❌ FALSE') . "</p>\n";

    } else {
        echo "<p style='color:red;'>❌ Error al actualizar</p>\n";
    }

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

echo "<hr>\n";
echo "<p><a href='affiliate/login.php'>← Ir al login</a> | <a href='public_html/test_web_auth.php'>Verificar autenticación</a></p>\n";
?>
