<?php
/**
 * Login Simple para Admin - Sin CSRF para diagnóstico
 */
require_once __DIR__ . '/../includes/config.php';

// Si ya está logueado, redirigir
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    // Validar credenciales
    $okUser = hash_equals(ADMIN_USER, $user);
    $okPass = (ADMIN_PASS_HASH !== '')
                ? password_verify($pass, ADMIN_PASS_HASH)
                : hash_equals(ADMIN_PASS_PLAIN, $pass);

    if ($okUser && $okPass) {
        // Login exitoso
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_user'] = ADMIN_USER;
        session_regenerate_id(true);

        $success = "Login exitoso! Redirigiendo...";

        // Redirigir después de 1 segundo
        header("Refresh: 1; url=dashboard.php");
    } else {
        $error = "Usuario o contraseña incorrectos";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - CompraTica</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            max-width: 420px;
            width: 100%;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            color: #dc2626;
            font-size: 28px;
            margin-bottom: 5px;
        }
        .logo p {
            color: #64748b;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            color: #334155;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover {
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.3);
        }
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #16a34a;
        }
        .credentials {
            background: #fef3c7;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #f59e0b;
        }
        .credentials h3 {
            color: #92400e;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .credentials p {
            color: #78350f;
            font-size: 13px;
            margin: 5px 0;
            font-family: monospace;
        }
        .links {
            text-align: center;
            margin-top: 20px;
        }
        .links a {
            color: #0891b2;
            text-decoration: none;
            font-size: 14px;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>🇨🇷 CompraTica</h1>
            <p>Panel de Administración</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                ✗ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ✓ <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" required autofocus placeholder="Ingresa tu usuario">
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required placeholder="Ingresa tu contraseña">
            </div>

            <button type="submit" class="btn">Iniciar Sesión</button>
        </form>

        <div class="credentials">
            <h3>📋 Credenciales Configuradas:</h3>
            <p><strong>Usuario:</strong> <?= ADMIN_USER ?></p>
            <p><strong>Contraseña:</strong> <?= ADMIN_PASS_PLAIN ?></p>
        </div>

        <div class="links">
            <a href="../index.php">← Volver al sitio</a> |
            <a href="test_login.php">🔧 Diagnóstico completo</a>
        </div>
    </div>
</body>
</html>
