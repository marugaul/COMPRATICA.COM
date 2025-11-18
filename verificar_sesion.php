<?php
/**
 * VERIFICAR SESIÓN - Diagnóstico de sesiones
 */

// Iniciar sesión igual que checkout.php
$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) {
    mkdir($__sessPath, 0700, true);
}
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}

$__isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PHPSESSID');
    if (PHP_VERSION_ID >= 70300) {
        ini_set('session.cookie_lifetime', '0');
        ini_set('session.cookie_path', '/');
        ini_set('session.cookie_domain', '');
        ini_set('session.cookie_secure', $__isHttps ? '1' : '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
    }
    session_start();
}

header('Content-Type: text/plain; charset=utf-8');

echo "==========================================\n";
echo "DIAGNÓSTICO DE SESIÓN\n";
echo "==========================================\n\n";

echo "[1] Información del servidor:\n";
echo "  Protocolo: " . ($__isHttps ? 'HTTPS' : 'HTTP') . "\n";
echo "  Host: " . ($_SERVER['HTTP_HOST'] ?? 'No definido') . "\n";
echo "  Path actual: " . __DIR__ . "\n\n";

echo "[2] Configuración de sesión:\n";
echo "  Session name: " . session_name() . "\n";
echo "  Session ID: " . session_id() . "\n";
echo "  Session status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVA' : 'INACTIVA') . "\n";
echo "  Save path: " . ini_get('session.save_path') . "\n";
echo "  Cookie lifetime: " . ini_get('session.cookie_lifetime') . " segundos\n";
echo "  Cookie path: " . ini_get('session.cookie_path') . "\n";
echo "  Cookie domain: " . (ini_get('session.cookie_domain') ?: '(vacío)') . "\n";
echo "  Cookie secure: " . (ini_get('session.cookie_secure') ? 'SÍ' : 'NO') . "\n";
echo "  Cookie httponly: " . (ini_get('session.cookie_httponly') ? 'SÍ' : 'NO') . "\n";
echo "  Cookie samesite: " . ini_get('session.cookie_samesite') . "\n\n";

echo "[3] Directorio de sesiones:\n";
echo "  Existe: " . (is_dir($__sessPath) ? 'SÍ' : 'NO') . "\n";
echo "  Escribible: " . (is_writable($__sessPath) ? 'SÍ' : 'NO') . "\n";
echo "  Permisos: " . substr(sprintf('%o', fileperms($__sessPath)), -4) . "\n";

if (is_dir($__sessPath)) {
    $files = scandir($__sessPath);
    $sessionFiles = array_filter($files, function($f) { return $f !== '.' && $f !== '..'; });
    echo "  Archivos de sesión: " . count($sessionFiles) . "\n";
}
echo "\n";

echo "[4] Variables de sesión actuales:\n";
if (empty($_SESSION)) {
    echo "  ⚠️  La sesión está VACÍA\n";
} else {
    foreach ($_SESSION as $key => $value) {
        if ($key === 'csrf_token') {
            echo "  ✓ $key: " . substr($value, 0, 16) . "...\n";
        } else {
            echo "  ✓ $key: " . (is_array($value) ? json_encode($value) : $value) . "\n";
        }
    }
}
echo "\n";

echo "[5] Cookies recibidas:\n";
if (empty($_COOKIE)) {
    echo "  ⚠️  No hay cookies\n";
} else {
    foreach ($_COOKIE as $key => $value) {
        if ($key === 'PHPSESSID') {
            echo "  ✓ $key: " . $value . " (Session cookie)\n";
        } else {
            echo "  - $key: " . substr($value, 0, 30) . "...\n";
        }
    }
}
echo "\n";

echo "[6] Estado de autenticación:\n";
$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id > 0) {
    echo "  ✅ LOGUEADO - user_id: $user_id\n";

    // Verificar en BD
    require_once __DIR__ . '/includes/db.php';
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "  ✅ Usuario existe en BD:\n";
        echo "     Nombre: " . $user['name'] . "\n";
        echo "     Email: " . $user['email'] . "\n";
    } else {
        echo "  ❌ Usuario NO existe en BD\n";
    }
} else {
    echo "  ❌ NO LOGUEADO - user_id no definido en sesión\n";
}

echo "\n==========================================\n";
echo "PRUEBA CRUZADA\n";
echo "==========================================\n\n";

echo "Para probar si la sesión se mantiene:\n";
echo "1. Visita: https://compratica.com/login.php\n";
echo "2. Inicia sesión\n";
echo "3. Vuelve a este script: https://compratica.com/verificar_sesion.php\n";
echo "4. Deberías ver 'LOGUEADO' en la sección [6]\n";
?>
