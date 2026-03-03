<?php
// Test de autenticación desde entorno web
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';

$email = 'vanecastro@gmail.com';
$password = 'Compratica2024!';

echo "<h2>Test de Autenticación Web</h2>\n";
echo "<p>Probando: <strong>$email</strong></p>\n\n";

echo "<h3>1. Entorno PHP:</h3>\n";
echo "<pre>";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "SAPI: " . php_sapi_name() . "\n";
echo "PDO disponible: " . (extension_loaded('pdo') ? 'SI' : 'NO') . "\n";
echo "PDO SQLite: " . (extension_loaded('pdo_sqlite') ? 'SI' : 'NO') . "\n";
echo "</pre>\n";

echo "<h3>2. Conexión a base de datos:</h3>\n";
try {
    $pdo = db();
    echo "<p>✓ Conexión exitosa</p>\n";
} catch (Exception $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    exit;
}

echo "<h3>3. Buscando usuario:</h3>\n";
$stmt = $pdo->prepare("SELECT id, name, email, password_hash, is_active FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "<p>❌ Usuario NO encontrado</p>\n";
    exit;
}

echo "<pre>";
echo "ID: " . $user['id'] . "\n";
echo "Nombre: " . $user['name'] . "\n";
echo "Email: " . $user['email'] . "\n";
echo "Password hash length: " . strlen($user['password_hash']) . " chars\n";
echo "is_active: " . $user['is_active'] . "\n";
echo "</pre>\n";

echo "<h3>4. Verificando contraseña:</h3>\n";
echo "<p>Contraseña a verificar: <code>" . htmlspecialchars($password) . "</code></p>\n";
echo "<p>Longitud: " . strlen($password) . " caracteres</p>\n";

$result = password_verify($password, $user['password_hash']);
echo "<p>password_verify() = <strong>" . ($result ? '✓ TRUE' : '❌ FALSE') . "</strong></p>\n";

if (!$result) {
    echo "<div style='background:#fff3cd;padding:15px;border:1px solid #ffc107;margin:10px 0;'>\n";
    echo "<h4>⚠️ La contraseña NO coincide</h4>\n";
    echo "<p>Esto es exactamente el problema. Vamos a investigar más:</p>\n";

    // Probar variaciones
    echo "<h4>Probando variaciones:</h4>\n";
    $variations = [
        'Compratica2024!',
        'Compratica2024! ',  // con espacio al final
        ' Compratica2024!',  // con espacio al inicio
        'compratica2024!',   // minúsculas
        'COMPRATICA2024!',   // mayúsculas
    ];

    foreach ($variations as $var) {
        $testResult = password_verify($var, $user['password_hash']);
        echo "<p>" . ($testResult ? '✓' : '✗') . " '" . htmlspecialchars($var) . "'</p>\n";
    }

    echo "</div>\n";
} else {
    echo "<div style='background:#d4edda;padding:15px;border:1px solid #c3e6cb;margin:10px 0;'>\n";
    echo "<h4>✓ Contraseña correcta</h4>\n";
    echo "</div>\n";
}

echo "<h3>5. Verificando is_active:</h3>\n";
$isActiveInt = (int)($user['is_active'] ?? 0);
echo "<p>is_active = $isActiveInt " . ($isActiveInt === 1 ? '✓' : '❌') . "</p>\n";

echo "<h3>6. Llamando a authenticate_user():</h3>\n";
try {
    $authenticatedUser = authenticate_user($email, $password);

    if ($authenticatedUser) {
        echo "<div style='background:#d4edda;padding:15px;border:1px solid #c3e6cb;margin:10px 0;'>\n";
        echo "<h4>✓ authenticate_user() EXITOSO</h4>\n";
        echo "<p>Usuario autenticado: " . $authenticatedUser['name'] . "</p>\n";
        echo "</div>\n";
    } else {
        echo "<div style='background:#f8d7da;padding:15px;border:1px solid #f5c6cb;margin:10px 0;'>\n";
        echo "<h4>❌ authenticate_user() retornó FALSE</h4>\n";
        echo "<p>La función de autenticación está fallando.</p>\n";
        echo "</div>\n";
    }
} catch (Exception $e) {
    echo "<div style='background:#f8d7da;padding:15px;border:1px solid #f5c6cb;margin:10px 0;'>\n";
    echo "<h4>❌ authenticate_user() lanzó excepción</h4>\n";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

echo "<h3>7. Logs de autenticación:</h3>\n";
$logFile = __DIR__ . '/logs/auth_debug.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    $last20 = array_slice($lines, -20);
    echo "<pre style='background:#f5f5f5;padding:10px;border:1px solid #ddd;max-height:300px;overflow:auto;'>";
    echo htmlspecialchars(implode("\n", $last20));
    echo "</pre>\n";
} else {
    echo "<p>No hay logs (archivo no existe)</p>\n";
}

echo "<hr>\n";
echo "<p><a href='../affiliate/login.php'>← Volver al login</a></p>\n";
?>
