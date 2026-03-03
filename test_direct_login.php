<?php
// Test de login directo
session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/user_auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    echo "<h3>Intentando login...</h3>\n";
    echo "<p>Email: " . htmlspecialchars($email) . "</p>\n";
    echo "<p>Password length: " . strlen($password) . "</p>\n";

    try {
        $user = authenticate_user($email, $password);

        if ($user) {
            $success = "✓ Login exitoso para: " . $user['name'];
            echo "<div style='background:#d4edda;padding:15px;border:1px solid #c3e6cb;color:#155724;margin:10px 0;'>";
            echo "<h3>✓ LOGIN EXITOSO</h3>";
            echo "<p>Usuario: {$user['name']} ({$user['email']})</p>";
            echo "<p>ID: {$user['id']}</p>";
            echo "<p>is_active: {$user['is_active']}</p>";
            echo "</div>";

            // Configurar sesión
            login_user($user);

            echo "<p><strong>Sesión configurada:</strong></p>";
            echo "<pre>";
            print_r($_SESSION);
            echo "</pre>";

        } else {
            $error = "❌ Credenciales inválidas (authenticate_user retornó false)";
        }
    } catch (Throwable $e) {
        $error = "❌ Error: " . $e->getMessage();
        echo "<div style='background:#f8d7da;padding:15px;border:1px solid #f5c6cb;color:#721c24;margin:10px 0;'>";
        echo "<h3>❌ ERROR</h3>";
        echo "<p>{$e->getMessage()}</p>";
        echo "<p>Archivo: {$e->getFile()}:{$e->getLine()}</p>";
        echo "</div>";
    }

    // Mostrar logs
    echo "<h3>Logs de autenticación:</h3>";
    $logFile = __DIR__ . '/logs/auth_debug.log';
    if (file_exists($logFile)) {
        echo "<pre style='background:#f5f5f5;padding:15px;border:1px solid #ddd;overflow:auto;max-height:400px;'>";
        echo htmlspecialchars(file_get_contents($logFile));
        echo "</pre>";
    } else {
        echo "<p>No hay logs</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Test Login Directo</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
        .error { background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; color: #721c24; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; color: #155724; margin: 10px 0; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>🔐 Test de Login Directo</h1>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Email:</label>
            <input type="email" name="email" value="vanecastro@gmail.com" required>
        </div>

        <div class="form-group">
            <label>Password:</label>
            <input type="password" name="password" value="Compratica2024!" required>
        </div>

        <button type="submit">Probar Login</button>
    </form>

    <hr>
    <p><a href="affiliate/login.php">← Volver a login normal</a> | <a href="ver_logs.php">Ver logs completos</a></p>
</body>
</html>
