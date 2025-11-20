<?php
/**
 * Login Admin - CompraTica
 * Con logging detallado para diagnóstico
 */

// Función de logging
function debugLog($message, $data = null) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
    $logFile = $logDir . '/login_debug.log';

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($data !== null) {
        $line .= ' | Data: ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    $line .= ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . PHP_EOL;

    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

debugLog('LOGIN.PHP INICIADO', [
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'N/A',
    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'N/A'
]);

require_once __DIR__ . '/../includes/config.php';

debugLog('CONFIG CARGADO', [
    'session_status' => session_status(),
    'session_id' => session_id(),
    'is_admin_set' => isset($_SESSION['is_admin']),
    'is_admin_value' => $_SESSION['is_admin'] ?? 'not set'
]);

// Si ya está logueado, redirigir
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    debugLog('YA LOGUEADO - REDIRIGIENDO', ['admin_user' => $_SESSION['admin_user'] ?? 'N/A']);
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    debugLog('POST RECIBIDO', [
        'username_presente' => isset($_POST['username']),
        'password_presente' => isset($_POST['password'])
    ]);

    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    debugLog('CREDENCIALES RECIBIDAS', [
        'username_recibido' => $user,
        'password_length' => strlen($pass),
        'ADMIN_USER_esperado' => ADMIN_USER,
        'ADMIN_PASS_PLAIN_esperado' => ADMIN_PASS_PLAIN
    ]);

    // Validar credenciales
    $okUser = hash_equals(ADMIN_USER, $user);
    $okPass = (ADMIN_PASS_HASH !== '')
                ? password_verify($pass, ADMIN_PASS_HASH)
                : hash_equals(ADMIN_PASS_PLAIN, $pass);

    debugLog('VALIDACION', [
        'usuario_correcto' => $okUser,
        'password_correcto' => $okPass,
        'usando_hash' => (ADMIN_PASS_HASH !== '')
    ]);

    if ($okUser && $okPass) {
        debugLog('LOGIN EXITOSO - CONFIGURANDO SESION');

        // Login exitoso
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_user'] = ADMIN_USER;

        debugLog('SESION CONFIGURADA', [
            'is_admin' => $_SESSION['is_admin'],
            'admin_user' => $_SESSION['admin_user'],
            'session_id_antes_regenerate' => session_id()
        ]);

        session_regenerate_id(true);

        debugLog('SESSION REGENERADO', [
            'nuevo_session_id' => session_id()
        ]);

        // Redirigir
        $redirect = $_GET['redirect'] ?? 'dashboard.php';
        // Sanitizar redirect para evitar open redirect
        if (strpos($redirect, 'http') === 0 || strpos($redirect, '//') === 0) {
            $redirect = 'dashboard.php';
        }

        debugLog('REDIRIGIENDO A', ['redirect' => $redirect]);

        header('Location: ' . $redirect);
        exit;
    } else {
        debugLog('LOGIN FALLIDO', [
            'user_ok' => $okUser,
            'pass_ok' => $okPass
        ]);

        $error = "Usuario o contraseña incorrectos";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - CompraTica</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 50%, #fbbf24 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 420px;
            width: 100%;
            border: 3px solid #fbbf24;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo .flag {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .logo h1 {
            background: linear-gradient(135deg, #dc2626 0%, #fbbf24 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 32px;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .logo p {
            color: #64748b;
            font-size: 14px;
            font-weight: 600;
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
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #dc2626;
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.1);
        }
        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
        }
        .btn:hover {
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
        }
        .btn:active {
            transform: translateY(0);
        }
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
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
        .links {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .links a {
            color: #dc2626;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: color 0.3s;
        }
        .links a:hover {
            color: #b91c1c;
            text-decoration: underline;
        }
        .costa-rica-bar {
            height: 4px;
            background: linear-gradient(90deg, #dc2626 33%, white 33%, white 66%, #dc2626 66%);
            margin-bottom: 20px;
            border-radius: 2px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="costa-rica-bar"></div>

        <div class="logo">
            <div class="flag">🇨🇷</div>
            <h1>CompraTica</h1>
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
                <label for="username">👤 Usuario</label>
                <input type="text" id="username" name="username" required autofocus placeholder="Ingresa tu usuario">
            </div>

            <div class="form-group">
                <label for="password">🔒 Contraseña</label>
                <input type="password" id="password" name="password" required placeholder="Ingresa tu contraseña">
            </div>

            <button type="submit" class="btn">Iniciar Sesión</button>
        </form>

        <div class="links">
            <a href="../index.php">← Volver al sitio</a>
        </div>
    </div>
</body>
</html>
