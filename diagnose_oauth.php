<?php
/**
 * Script de diagnóstico para Google OAuth
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "===========================================\n";
echo "DIAGNÓSTICO DE GOOGLE OAUTH\n";
echo "===========================================\n\n";

// 1. Verificar credenciales de Google
echo "1. CREDENCIALES DE GOOGLE\n";
echo "-------------------------------------------\n";
echo "GOOGLE_CLIENT_ID: " . (defined('GOOGLE_CLIENT_ID') && !empty(GOOGLE_CLIENT_ID) ? 'Configurado ✓' : 'NO CONFIGURADO ✗') . "\n";
echo "GOOGLE_CLIENT_SECRET: " . (defined('GOOGLE_CLIENT_SECRET') && !empty(GOOGLE_CLIENT_SECRET) ? 'Configurado ✓' : 'NO CONFIGURADO ✗') . "\n";
if (defined('GOOGLE_CLIENT_ID') && !empty(GOOGLE_CLIENT_ID)) {
    echo "Client ID: " . substr(GOOGLE_CLIENT_ID, 0, 30) . "...\n";
}
echo "\n";

// 2. Verificar sesión
echo "2. CONFIGURACIÓN DE SESIÓN\n";
echo "-------------------------------------------\n";
echo "session_status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVA ✓' : 'NO ACTIVA ✗') . "\n";
echo "session_save_path: " . session_save_path() . "\n";
echo "session_name: " . session_name() . "\n";
echo "session_id: " . (session_id() ?: 'NO HAY SESSION ID') . "\n";

// Probar escribir en sesión
$_SESSION['test_oauth_session'] = 'test_value_' . time();
echo "Test write to session: " . ($_SESSION['test_oauth_session'] ?? 'FAILED ✗') . "\n";
echo "\n";

// 3. Verificar base de datos
echo "3. BASE DE DATOS\n";
echo "-------------------------------------------\n";
try {
    $pdo = db();
    echo "Conexión a base de datos: OK ✓\n";

    // Verificar tabla users
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('users', $tables)) {
        echo "Tabla 'users': EXISTE ✓\n";

        // Obtener schema de la tabla
        $schema = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
        echo "\nColumnas de la tabla 'users':\n";
        $hasOAuthProvider = false;
        $hasOAuthId = false;
        foreach ($schema as $column) {
            echo "  - " . $column['name'] . " (" . $column['type'] . ")" . ($column['notnull'] ? ' NOT NULL' : '') . "\n";
            if ($column['name'] === 'oauth_provider') $hasOAuthProvider = true;
            if ($column['name'] === 'oauth_id') $hasOAuthId = true;
        }

        echo "\nColumnas OAuth:\n";
        echo "  oauth_provider: " . ($hasOAuthProvider ? 'EXISTE ✓' : 'NO EXISTE ✗') . "\n";
        echo "  oauth_id: " . ($hasOAuthId ? 'EXISTE ✓' : 'NO EXISTE ✗') . "\n";

        // Contar usuarios
        $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "\nTotal de usuarios: $userCount\n";

    } else {
        echo "Tabla 'users': NO EXISTE ✗\n";
        echo "\nTablas existentes:\n";
        $allTables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($allTables as $table) {
            echo "  - $table\n";
        }
    }

} catch (Exception $e) {
    echo "ERROR en base de datos ✗\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Verificar archivos OAuth
echo "4. ARCHIVOS OAUTH\n";
echo "-------------------------------------------\n";
$oauthFiles = [
    '/real-estate/login.php' => 'Real Estate Login',
    '/real-estate/oauth-start.php' => 'Real Estate OAuth Start',
    '/real-estate/oauth-callback.php' => 'Real Estate OAuth Callback',
    '/services/login.php' => 'Services Login',
    '/services/oauth-start.php' => 'Services OAuth Start',
    '/services/oauth-callback.php' => 'Services OAuth Callback',
];

foreach ($oauthFiles as $file => $description) {
    $fullPath = __DIR__ . $file;
    echo "$description: " . (file_exists($fullPath) ? 'EXISTE ✓' : 'NO EXISTE ✗') . "\n";
}
echo "\n";

// 5. Verificar directorio de logs
echo "5. DIRECTORIO DE LOGS\n";
echo "-------------------------------------------\n";
$logDir = __DIR__ . '/logs';
echo "Directorio: $logDir\n";
echo "Existe: " . (is_dir($logDir) ? 'SÍ ✓' : 'NO ✗') . "\n";
if (is_dir($logDir)) {
    echo "Escribible: " . (is_writable($logDir) ? 'SÍ ✓' : 'NO ✗') . "\n";

    // Listar archivos de log
    $logFiles = glob($logDir . '/*.log');
    if (count($logFiles) > 0) {
        echo "\nArchivos de log:\n";
        foreach ($logFiles as $logFile) {
            $size = filesize($logFile);
            echo "  - " . basename($logFile) . " ($size bytes)\n";
        }
    } else {
        echo "\nNo hay archivos de log.\n";
    }
} else {
    echo "El directorio de logs no existe. Se creará automáticamente.\n";
}
echo "\n";

// 6. Test de función user_auth
echo "6. FUNCIONES DE AUTENTICACIÓN\n";
echo "-------------------------------------------\n";
if (function_exists('get_or_create_oauth_user')) {
    echo "get_or_create_oauth_user(): EXISTE ✓\n";
} else {
    echo "get_or_create_oauth_user(): NO EXISTE ✗\n";
}
if (function_exists('login_user')) {
    echo "login_user(): EXISTE ✓\n";
} else {
    echo "login_user(): NO EXISTE ✗\n";
}
if (function_exists('is_user_logged_in')) {
    echo "is_user_logged_in(): EXISTE ✓\n";
} else {
    echo "is_user_logged_in(): NO EXISTE ✗\n";
}
echo "\n";

// 7. Verificar URLs de redirección
echo "7. URLS DE REDIRECCIÓN\n";
echo "-------------------------------------------\n";
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
           (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
           (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') ||
           (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false) ||
           stripos(BASE_URL, 'https://') === 0;

$baseUrl = ($isHttps ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
echo "Base URL: $baseUrl\n";
echo "HTTPS: " . ($isHttps ? 'SÍ ✓' : 'NO ✗') . "\n";
echo "\nURLs de callback que deberían estar configuradas en Google Cloud Console:\n";
echo "  - $baseUrl/real-estate/oauth-callback.php\n";
echo "  - $baseUrl/services/oauth-callback.php\n";
echo "  - $baseUrl/jobs/oauth-callback.php\n";
echo "\n";

echo "===========================================\n";
echo "FIN DEL DIAGNÓSTICO\n";
echo "===========================================\n";
