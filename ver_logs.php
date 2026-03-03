<?php
// Script para ver los logs de autenticación
echo "<h2>Logs de Autenticación</h2>\n\n";

$logFiles = [
    '/home/user/COMPRATICA.COM/logs/auth_debug.log' => 'Auth Debug',
    '/home/user/COMPRATICA.COM/logs/affiliate_login_debug.log' => 'Affiliate Login Debug',
    '/home/user/COMPRATICA.COM/logs/login_debug.log' => 'Login Debug'
];

foreach ($logFiles as $file => $name) {
    echo "<h3>$name</h3>\n";

    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (!empty($content)) {
            // Mostrar las últimas 50 líneas
            $lines = explode("\n", $content);
            $last50 = array_slice($lines, -50);

            echo "<pre style='background:#f5f5f5;padding:15px;border:1px solid #ddd;overflow:auto;'>";
            echo htmlspecialchars(implode("\n", $last50));
            echo "</pre>\n";
        } else {
            echo "<p style='color:#999;'>Log vacío</p>\n";
        }
    } else {
        echo "<p style='color:#999;'>Archivo no existe</p>\n";
    }
    echo "\n";
}

echo "<hr>\n";
echo "<p><a href='javascript:location.reload()'>Recargar</a> | <a href='affiliate/login.php'>Ir a Login</a></p>\n";
?>
