<?php
/**
 * Diagnóstico de Login Admin - COMPRATICA.COM
 */
require_once __DIR__ . '/../includes/config.php';

echo "<h1>Diagnóstico de Login Admin</h1>";
echo "<style>
body{font-family:Arial;padding:20px;background:#f5f5f5}
.ok{color:green;font-weight:bold}
.error{color:red;font-weight:bold}
.warn{color:orange;font-weight:bold}
.info{background:white;padding:15px;margin:10px 0;border-left:4px solid #0891b2;border-radius:4px}
.success{background:#d1fae5;padding:15px;margin:10px 0;border-left:4px solid #16a34a;border-radius:4px}
.danger{background:#fee2e2;padding:15px;margin:10px 0;border-left:4px solid #ef4444;border-radius:4px}
table{border-collapse:collapse;width:100%;max-width:600px;background:white;margin:10px 0}
table td{padding:10px;border:1px solid #ddd}
table td:first-child{font-weight:bold;width:200px;background:#f9fafb}
button,input[type=submit]{padding:12px 24px;background:#dc2626;color:white;border:none;border-radius:6px;cursor:pointer;font-size:16px;font-weight:bold}
button:hover,input[type=submit]:hover{background:#b91c1c}
input[type=text],input[type=password]{padding:10px;border:1px solid #ddd;border-radius:4px;width:100%;max-width:300px;font-size:14px}
.form-group{margin:15px 0}
label{display:block;margin-bottom:5px;font-weight:bold}
</style>";

// 1. Info de configuración
echo "<h2>1. Configuración de Admin</h2>";
echo "<table>";
echo "<tr><td>Usuario Admin</td><td>" . ADMIN_USER . "</td></tr>";
echo "<tr><td>Contraseña (Plain)</td><td>" . (ADMIN_PASS_PLAIN ? "✓ Configurada: <code>" . ADMIN_PASS_PLAIN . "</code>" : '<span class="error">✗ NO CONFIGURADA</span>') . "</td></tr>";
echo "<tr><td>Contraseña (Hash)</td><td>" . (ADMIN_PASS_HASH ? "✓ Configurada" : '<span class="warn">⚠ No configurada (usará plain)</span>') . "</td></tr>";
echo "<tr><td>Dashboard Path</td><td>" . ADMIN_DASHBOARD_PATH . "</td></tr>";
echo "</table>";

// 2. Sesión actual
echo "<h2>2. Estado de Sesión</h2>";
echo "<table>";
echo "<tr><td>Session ID</td><td>" . session_id() . "</td></tr>";
echo "<tr><td>Session Status</td><td>" . (session_status() === PHP_SESSION_ACTIVE ? '<span class="ok">✓ ACTIVE</span>' : '<span class="error">✗ NOT ACTIVE</span>') . "</td></tr>";
echo "<tr><td>Session Save Path</td><td>" . session_save_path() . "</td></tr>";
echo "<tr><td>¿Es Admin?</td><td>" . (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true ? '<span class="ok">✓ SÍ - LOGUEADO</span>' : '<span class="warn">✗ NO - NO LOGUEADO</span>') . "</td></tr>";

if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    echo "<tr><td>Admin User</td><td>" . ($_SESSION['admin_user'] ?? 'N/A') . "</td></tr>";
}
echo "</table>";

// 3. Cookies
echo "<h2>3. Cookies</h2>";
echo "<table>";
echo "<tr><td>PHPSESSID</td><td>" . ($_COOKIE['PHPSESSID'] ?? '<span class="warn">No definida</span>') . "</td></tr>";
echo "<tr><td>ADMIN-XSRF</td><td>" . ($_COOKIE['ADMIN-XSRF'] ?? '<span class="warn">No definida</span>') . "</td></tr>";
echo "<tr><td>admin_csrf (session)</td><td>" . ($_SESSION['admin_csrf'] ?? '<span class="warn">No definida</span>') . "</td></tr>";
echo "</table>";

// 4. Directorios
echo "<h2>4. Directorios y Permisos</h2>";
$sessionPath = __DIR__ . '/../sessions';
$logsPath = __DIR__ . '/../logs';

echo "<table>";
echo "<tr><td>Sessions dir</td><td>";
if (is_dir($sessionPath)) {
    echo '<span class="ok">✓ Existe</span> - ';
    echo is_writable($sessionPath) ? '<span class="ok">✓ Escribible</span>' : '<span class="error">✗ NO escribible</span>';
} else {
    echo '<span class="error">✗ NO existe</span>';
}
echo "</td></tr>";

echo "<tr><td>Logs dir</td><td>";
if (is_dir($logsPath)) {
    echo '<span class="ok">✓ Existe</span> - ';
    echo is_writable($logsPath) ? '<span class="ok">✓ Escribible</span>' : '<span class="error">✗ NO escribible</span>';
} else {
    echo '<span class="warn">⚠ NO existe (se creará automáticamente)</span>';
}
echo "</td></tr>";
echo "</table>";

// 5. Simulador de Login
echo "<h2>5. Probar Login</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_login'])) {
    $testUser = trim($_POST['test_username'] ?? '');
    $testPass = $_POST['test_password'] ?? '';

    echo "<div class='info'>";
    echo "<h3>Resultado del Test:</h3>";

    echo "<p><strong>Usuario ingresado:</strong> <code>" . htmlspecialchars($testUser) . "</code></p>";
    echo "<p><strong>Contraseña ingresada:</strong> <code>" . htmlspecialchars($testPass) . "</code></p>";
    echo "<hr>";

    echo "<p><strong>Usuario esperado:</strong> <code>" . ADMIN_USER . "</code></p>";
    echo "<p><strong>Contraseña esperada:</strong> <code>" . ADMIN_PASS_PLAIN . "</code></p>";
    echo "<hr>";

    $okUser = hash_equals(ADMIN_USER, $testUser);
    $okPass = (ADMIN_PASS_HASH !== '')
                ? password_verify($testPass, ADMIN_PASS_HASH)
                : hash_equals(ADMIN_PASS_PLAIN, $testPass);

    echo "<p><strong>Usuario coincide:</strong> " . ($okUser ? '<span class="ok">✓ SÍ</span>' : '<span class="error">✗ NO</span>') . "</p>";
    echo "<p><strong>Contraseña coincide:</strong> " . ($okPass ? '<span class="ok">✓ SÍ</span>' : '<span class="error">✗ NO</span>') . "</p>";

    if ($okUser && $okPass) {
        echo "</div>";
        echo "<div class='success'>";
        echo "<h3>✓ LOGIN EXITOSO</h3>";
        echo "<p>Las credenciales son correctas. Simulando login...</p>";

        // Simular el login
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_user'] = ADMIN_USER;
        session_regenerate_id(true);

        echo "<p><strong>Sesión actualizada:</strong></p>";
        echo "<ul>";
        echo "<li>is_admin: <span class='ok'>" . ($_SESSION['is_admin'] ? 'true' : 'false') . "</span></li>";
        echo "<li>admin_user: <span class='ok'>" . $_SESSION['admin_user'] . "</span></li>";
        echo "<li>Session ID: " . session_id() . "</li>";
        echo "</ul>";

        echo "<p><a href='email_marketing.php' style='display:inline-block;margin-top:10px;padding:12px 24px;background:#16a34a;color:white;text-decoration:none;border-radius:6px;font-weight:bold;'>Ir a Email Marketing</a></p>";
        echo "<p><a href='dashboard.php' style='display:inline-block;margin-top:10px;padding:12px 24px;background:#0891b2;color:white;text-decoration:none;border-radius:6px;font-weight:bold;'>Ir a Dashboard</a></p>";
        echo "</div>";
    } else {
        echo "</div>";
        echo "<div class='danger'>";
        echo "<h3>✗ LOGIN FALLIDO</h3>";
        echo "<p>Las credenciales no coinciden. Verifica:</p>";
        echo "<ul>";
        if (!$okUser) echo "<li>El nombre de usuario es incorrecto</li>";
        if (!$okPass) echo "<li>La contraseña es incorrecta</li>";
        echo "</ul>";
        echo "</div>";
    }
}

echo "<div class='info'>";
echo "<h3>Formulario de Prueba:</h3>";
echo "<form method='post'>";
echo "<div class='form-group'><label>Usuario:</label><input type='text' name='test_username' placeholder='marugaul' required></div>";
echo "<div class='form-group'><label>Contraseña:</label><input type='password' name='test_password' placeholder='marden7i' required></div>";
echo "<input type='submit' name='test_login' value='Probar Login'>";
echo "</form>";
echo "</div>";

// 6. Acciones rápidas
echo "<h2>6. Acciones Rápidas</h2>";
echo "<div class='info'>";
echo "<p><strong>Credenciales configuradas actualmente:</strong></p>";
echo "<ul style='background:#fef3c7;padding:15px;border-radius:4px;font-family:monospace'>";
echo "<li><strong>Usuario:</strong> " . ADMIN_USER . "</li>";
echo "<li><strong>Contraseña:</strong> " . ADMIN_PASS_PLAIN . "</li>";
echo "</ul>";

if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    echo "<p style='margin-top:20px'><a href='email_marketing.php' style='display:inline-block;padding:12px 24px;background:#16a34a;color:white;text-decoration:none;border-radius:6px;font-weight:bold;'>✓ Ir a Email Marketing</a></p>";
    echo "<p><a href='dashboard.php' style='display:inline-block;padding:12px 24px;background:#0891b2;color:white;text-decoration:none;border-radius:6px;font-weight:bold;'>Ir a Dashboard Admin</a></p>";
} else {
    echo "<p style='margin-top:20px'><a href='login.php' style='display:inline-block;padding:12px 24px;background:#dc2626;color:white;text-decoration:none;border-radius:6px;font-weight:bold;'>Ir a Login Admin</a></p>";
}
echo "</div>";

echo "<hr style='margin:30px 0'>";
echo "<p><small>Diagnóstico completado - " . date('Y-m-d H:i:s') . "</small></p>";
?>
