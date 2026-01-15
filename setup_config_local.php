<?php
/**
 * Script para verificar y crear config.local.php si no existe
 * Ejecutar después del primer deploy
 */

$configLocalPath = __DIR__ . '/includes/config.local.php';
$configExamplePath = __DIR__ . '/includes/config.local.php.example';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Setup config.local.php</title>";
echo "<style>body{font-family:Arial;padding:40px;background:#f5f5f5;max-width:800px;margin:0 auto;}";
echo ".box{background:white;padding:20px;margin:15px 0;border-radius:8px;}";
echo ".success{background:#f0fdf4;border-left:4px solid #16a34a;}";
echo ".error{background:#fee;border-left:4px solid #dc2626;}";
echo ".warning{background:#fef3c7;border-left:4px solid #f59e0b;}";
echo "code{background:#1f2937;color:#10b981;padding:2px 6px;border-radius:3px;font-family:monospace;}";
echo "pre{background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:8px;overflow-x:auto;}";
echo "</style></head><body>";

echo "<h1>🔧 Configuración de config.local.php</h1>";

// Verificar si existe
if (file_exists($configLocalPath)) {
    echo "<div class='box success'>";
    echo "<h2>✅ Archivo ya existe</h2>";
    echo "<p>El archivo <code>includes/config.local.php</code> ya está configurado.</p>";

    // Verificar que tenga las credenciales
    require_once $configLocalPath;

    if (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== '') {
        echo "<p class='success'>✅ Google OAuth configurado</p>";
        echo "<p><code>GOOGLE_CLIENT_ID</code>: " . substr(GOOGLE_CLIENT_ID, 0, 30) . "...</p>";
    } else {
        echo "<p class='error'>❌ Google OAuth NO configurado (credenciales vacías)</p>";
    }

    if (defined('FACEBOOK_APP_ID') && FACEBOOK_APP_ID !== '') {
        echo "<p class='success'>✅ Facebook OAuth configurado</p>";
    } else {
        echo "<p class='warning'>⚠️ Facebook OAuth NO configurado</p>";
    }

    echo "</div>";
} else {
    echo "<div class='box error'>";
    echo "<h2>❌ Archivo NO existe</h2>";
    echo "<p>El archivo <code>includes/config.local.php</code> no existe.</p>";

    // Intentar crearlo desde el ejemplo
    if (file_exists($configExamplePath)) {
        echo "<p>Intentando crear desde <code>config.local.php.example</code>...</p>";

        if (copy($configExamplePath, $configLocalPath)) {
            echo "<p class='success'>✅ Archivo creado exitosamente</p>";
            echo "<p class='warning'>⚠️ Debes editar el archivo y agregar tus credenciales OAuth reales:</p>";
            echo "<pre>vim /home/comprati/public_html/includes/config.local.php</pre>";
            echo "<p>O usa el panel de cPanel File Manager para editarlo.</p>";
        } else {
            echo "<p class='error'>❌ No se pudo crear el archivo (permisos?)</p>";
            echo "<p>Crea manualmente el archivo con este contenido:</p>";
            echo "<pre>" . htmlspecialchars(file_get_contents($configExamplePath)) . "</pre>";
        }
    } else {
        echo "<p class='error'>❌ Tampoco existe el archivo de ejemplo</p>";
        echo "<p>Crea manualmente <code>/home/comprati/public_html/includes/config.local.php</code> copiando tus credenciales OAuth.</p>";
        echo "<p class='warning'>Usa el archivo <code>config.local.php.example</code> como plantilla y agrega tus credenciales de Google Cloud Console.</p>";
    }

    echo "</div>";
}

echo "<div class='box'>";
echo "<h2>📋 Próximos pasos</h2>";
echo "<ol>";
echo "<li>Asegurarte de que <code>config.local.php</code> existe y tiene las credenciales correctas</li>";
echo "<li>Ejecutar: <a href='/setup_oauth_db.php'>setup_oauth_db.php</a> (configurar base de datos)</li>";
echo "<li>Probar: <a href='/test_google_oauth.php'>test_google_oauth.php</a> (diagnóstico completo)</li>";
echo "<li>Configurar Google Cloud Console con la URI de redirección correcta</li>";
echo "</ol>";
echo "</div>";

echo "<hr>";
echo "<p style='text-align:center;'>";
echo "<a href='/setup_oauth_db.php' style='display:inline-block;padding:12px 24px;background:#4285f4;color:white;text-decoration:none;border-radius:6px;margin:5px;'>➡️ Siguiente: Configurar DB</a>";
echo "<a href='/test_google_oauth.php' style='display:inline-block;padding:12px 24px;background:#667eea;color:white;text-decoration:none;border-radius:6px;margin:5px;'>🔍 Diagnóstico OAuth</a>";
echo "</p>";

echo "</body></html>";
