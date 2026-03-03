<?php
// Test para ver cómo se resuelven las rutas
require_once __DIR__ . '/includes/user_auth.php';

echo "=== RESOLUCIÓN DE RUTAS ===\n\n";

echo "__DIR__ en test: " . __DIR__ . "\n";
echo "__FILE__ en test: " . __FILE__ . "\n\n";

// Simular lo que hace user_auth.php
$includesDir = __DIR__ . '/includes';
echo "Directorio includes: $includesDir\n";

$logFromIncludes = $includesDir . '/../public_html/logs/auth_debug.log';
echo "Log path desde includes: $logFromIncludes\n";
echo "Realpath: " . realpath(dirname($logFromIncludes)) . "\n\n";

// Probar autenticación para ver la ruta real
echo "=== PROBANDO AUTENTICACIÓN ===\n";
try {
    $user = authenticate_user('vanecastro@gmail.com', 'Compratica2024!');
    if ($user) {
        echo "✓ Autenticación exitosa!\n";
        echo "Usuario: " . $user['name'] . "\n";
    } else {
        echo "❌ Autenticación falló (retornó false)\n";
    }
} catch (Exception $e) {
    echo "❌ Excepción: " . $e->getMessage() . "\n";
}

echo "\n=== VERIFICANDO LOGS ===\n";
$possibleLogs = [
    __DIR__ . '/public_html/logs/auth_debug.log',
    __DIR__ . '/logs/auth_debug.log',
    '/home/user/COMPRATICA.COM/public_html/logs/auth_debug.log',
    '/home/user/COMPRATICA.COM/logs/auth_debug.log'
];

foreach ($possibleLogs as $logPath) {
    if (file_exists($logPath)) {
        echo "\n✓ ENCONTRADO: $logPath\n";
        echo "Últimas líneas:\n";
        $lines = file($logPath);
        echo implode('', array_slice($lines, -5));
    } else {
        echo "\n✗ No existe: $logPath\n";
    }
}
?>
