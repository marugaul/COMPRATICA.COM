<?php
/**
 * Diagnóstico de Google OAuth para affiliate/login.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h1>🔍 Diagnóstico de Google OAuth - Afiliados</h1>";

// 1. Verificar config.php
echo "<h2>1. Verificando archivos de configuración</h2>";
if (file_exists(__DIR__ . '/includes/config.php')) {
    echo "✅ includes/config.php existe<br>";
    require_once __DIR__ . '/includes/config.php';
} else {
    echo "❌ includes/config.php NO existe<br>";
}

if (file_exists(__DIR__ . '/includes/config.local.php')) {
    echo "✅ includes/config.local.php existe<br>";
} else {
    echo "⚠️ includes/config.local.php NO existe (puede ser normal si las credenciales están en config.php)<br>";
}

// 2. Verificar credenciales de Google
echo "<h2>2. Verificando credenciales de Google OAuth</h2>";

if (defined('GOOGLE_CLIENT_ID')) {
    $client_id = GOOGLE_CLIENT_ID;
    if (!empty($client_id)) {
        echo "✅ GOOGLE_CLIENT_ID está definido: <strong>" . substr($client_id, 0, 20) . "...</strong><br>";
    } else {
        echo "❌ GOOGLE_CLIENT_ID está definido pero está VACÍO<br>";
    }
} else {
    echo "❌ GOOGLE_CLIENT_ID NO está definido<br>";
}

if (defined('GOOGLE_CLIENT_SECRET')) {
    $client_secret = GOOGLE_CLIENT_SECRET;
    if (!empty($client_secret)) {
        echo "✅ GOOGLE_CLIENT_SECRET está definido: <strong>" . substr($client_secret, 0, 10) . "...</strong><br>";
    } else {
        echo "❌ GOOGLE_CLIENT_SECRET está definido pero está VACÍO<br>";
    }
} else {
    echo "❌ GOOGLE_CLIENT_SECRET NO está definido<br>";
}

// 3. Generar URL de prueba
echo "<h2>3. Generando URL de OAuth</h2>";

if (defined('GOOGLE_CLIENT_ID') && !empty(GOOGLE_CLIENT_ID)) {
    $redirectUri = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
                 . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                 . '/affiliate/login.php';

    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'email profile',
        'access_type' => 'online'
    ];

    $googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

    echo "✅ URL generada correctamente<br>";
    echo "<strong>Redirect URI:</strong> <code>$redirectUri</code><br>";
    echo "<strong>URL de OAuth:</strong><br>";
    echo "<textarea style='width: 100%; height: 100px; margin-top: 10px;'>$googleAuthUrl</textarea><br>";
    echo "<br><a href='$googleAuthUrl' style='display: inline-block; margin-top: 10px; padding: 10px 20px; background: #4285f4; color: white; text-decoration: none; border-radius: 4px;'>🧪 Probar OAuth con Google</a>";
} else {
    echo "❌ No se puede generar la URL porque GOOGLE_CLIENT_ID está vacío o no definido<br>";
}

// 4. Verificar Google Cloud Console
echo "<h2>4. Instrucciones para Google Cloud Console</h2>";
echo "<p>Asegurate de tener configurado en Google Cloud Console:</p>";
echo "<ol>";
echo "<li><strong>Redirect URI autorizado:</strong> <code>https://compratica.com/affiliate/login.php</code></li>";
echo "<li><strong>JavaScript origins:</strong> <code>https://compratica.com</code></li>";
echo "<li><strong>Tipo de aplicación:</strong> Aplicación web</li>";
echo "</ol>";

echo "<h2>5. Solución si no funcionó</h2>";
echo "<p>Si el botón de Google no hace nada, probablemente las credenciales no están configuradas. Necesitas:</p>";
echo "<ol>";
echo "<li>Asegurarte de que <code>includes/config.local.php</code> existe y tiene las credenciales</li>";
echo "<li>O agregar las credenciales directamente en <code>includes/config.php</code></li>";
echo "</ol>";
