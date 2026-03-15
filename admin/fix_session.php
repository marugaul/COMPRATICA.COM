<?php
/**
 * FIX DE SESIÓN - Ejecutar UNA vez para limpiar y configurar sesión correctamente
 */

// Iniciar sesión limpia
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Configurar parámetros de cookie seguros
session_set_cookie_params([
    'lifetime' => 0,  // Hasta cerrar navegador
    'path'     => '/',
    'domain'   => '',  // Dominio actual (sin punto inicial)
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// Cargar config
require_once __DIR__ . '/../includes/config.php';

echo "<h1>Fix de Sesión Admin</h1>";
echo "<pre>";

// Limpiar sesión anterior
$_SESSION = [];
session_regenerate_id(true);

echo "✓ Sesión limpiada\n";
echo "✓ Nuevo session_id: " . session_id() . "\n\n";

// Verificar credenciales
$user = trim($_POST['username'] ?? '');
$pass = $_POST['password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user && $pass) {
    echo "Intentando login...\n";
    echo "Usuario recibido: {$user}\n";
    echo "Usuario esperado: " . ADMIN_USER . "\n";

    $okUser = hash_equals(ADMIN_USER, $user);
    $okPass = hash_equals(ADMIN_PASS_PLAIN, $pass);

    echo "Usuario correcto: " . ($okUser ? 'SÍ' : 'NO') . "\n";
    echo "Password correcto: " . ($okPass ? 'SÍ' : 'NO') . "\n\n";

    if ($okUser && $okPass) {
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_user'] = ADMIN_USER;
        $_SESSION['login_time'] = time();

        echo "✅ LOGIN EXITOSO\n";
        echo "✓ is_admin = true\n";
        echo "✓ admin_user = " . $_SESSION['admin_user'] . "\n";
        echo "✓ login_time = " . date('Y-m-d H:i:s', $_SESSION['login_time']) . "\n\n";

        echo "Sesión configurada correctamente.\n\n";
        echo "<a href='import_jobs.php' style='display:inline-block;padding:10px 20px;background:#27ae60;color:white;text-decoration:none;border-radius:5px;'>Ir a Import Jobs</a> ";
        echo "<a href='dashboard.php' style='display:inline-block;padding:10px 20px;background:#3498db;color:white;text-decoration:none;border-radius:5px;'>Ir a Dashboard</a>";
    } else {
        echo "❌ CREDENCIALES INCORRECTAS\n";
    }
} else {
    echo "Estado actual de sesión:\n";
    echo "is_admin: " . (isset($_SESSION['is_admin']) ? ($_SESSION['is_admin'] ? 'true' : 'false') : 'NO DEFINIDO') . "\n";
    echo "admin_user: " . ($_SESSION['admin_user'] ?? 'NO DEFINIDO') . "\n\n";

    ?>
    <h2>Login</h2>
    <form method="POST">
        <p><input type="text" name="username" placeholder="Usuario" required style="padding:8px;font-size:14px;"></p>
        <p><input type="password" name="password" placeholder="Contraseña" required style="padding:8px;font-size:14px;"></p>
        <p><button type="submit" style="padding:10px 20px;background:#27ae60;color:white;border:none;border-radius:5px;cursor:pointer;font-size:14px;">Login</button></p>
    </form>
    <p><small>Usuario: <code>marugaul</code></small></p>
    <?php
}

echo "</pre>";
