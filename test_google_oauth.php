<?php
/**
 * Script de diagnóstico para Google OAuth
 */

// Simular el entorno del servidor
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'compratica.com';
$_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? 'on';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/test_google_oauth.php';

require_once __DIR__ . '/includes/config.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Diagnóstico Google OAuth</title>";
echo "<style>body{font-family:Arial;padding:40px;background:#f5f5f5;}";
echo ".box{background:white;padding:20px;margin:15px 0;border-radius:8px;border-left:4px solid #4285f4;}";
echo ".success{border-left-color:#16a34a;background:#f0fdf4;}";
echo ".error{border-left-color:#dc2626;background:#fee;}";
echo ".warning{border-left-color:#f59e0b;background:#fef3c7;}";
echo "code{background:#1f2937;color:#10b981;padding:2px 6px;border-radius:3px;}";
echo "pre{background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:8px;overflow-x:auto;}";
echo "</style></head><body>";

echo "<h1>🔍 Diagnóstico de Google OAuth</h1>";

// 1. Verificar que las credenciales están definidas
echo "<div class='box'>";
echo "<h2>1. Verificación de Credenciales</h2>";
$clientIdDefined = defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== '';
$clientSecretDefined = defined('GOOGLE_CLIENT_SECRET') && GOOGLE_CLIENT_SECRET !== '';

if ($clientIdDefined) {
    echo "<p class='success'>✅ <strong>GOOGLE_CLIENT_ID</strong> está configurado</p>";
    echo "<p><code>" . htmlspecialchars(GOOGLE_CLIENT_ID) . "</code></p>";
} else {
    echo "<p class='error'>❌ <strong>GOOGLE_CLIENT_ID</strong> NO está configurado</p>";
}

if ($clientSecretDefined) {
    echo "<p class='success'>✅ <strong>GOOGLE_CLIENT_SECRET</strong> está configurado</p>";
    echo "<p><code>" . substr(GOOGLE_CLIENT_SECRET, 0, 10) . "...</code></p>";
} else {
    echo "<p class='error'>❌ <strong>GOOGLE_CLIENT_SECRET</strong> NO está configurado</p>";
}
echo "</div>";

// 2. Generar URI de redirección
echo "<div class='box'>";
echo "<h2>2. URI de Redirección</h2>";

$host = $_SERVER['HTTP_HOST'] ?? '';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
    || (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false);

$redirectUri = ($isHttps ? 'https://' : 'http://') . $host . '/login.php?oauth=google';

echo "<p><strong>Host detectado:</strong> <code>" . htmlspecialchars($host) . "</code></p>";
echo "<p><strong>HTTPS:</strong> " . ($isHttps ? "✅ Sí" : "❌ No") . "</p>";
echo "<p><strong>Redirect URI generada:</strong></p>";
echo "<pre>" . htmlspecialchars($redirectUri) . "</pre>";
echo "<p class='warning'><strong>⚠️ IMPORTANTE:</strong> Esta URI debe estar configurada EXACTAMENTE así en Google Cloud Console</p>";
echo "</div>";

// 3. Generar URL de login
echo "<div class='box'>";
echo "<h2>3. URL de Autorización de Google</h2>";

if ($clientIdDefined) {
    $googleLoginUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'email profile',
    ]);

    echo "<p class='success'>✅ URL de autorización generada correctamente</p>";
    echo "<pre>" . htmlspecialchars($googleLoginUrl) . "</pre>";
    echo "<p><a href='" . htmlspecialchars($googleLoginUrl) . "' style='display:inline-block;padding:12px 24px;background:#4285f4;color:white;text-decoration:none;border-radius:6px;font-weight:bold;'>🔗 Probar Login con Google</a></p>";
} else {
    echo "<p class='error'>❌ No se puede generar la URL de autorización (falta Client ID)</p>";
}
echo "</div>";

// 4. Verificar configuración en Google Cloud Console
echo "<div class='box warning'>";
echo "<h2>4. Checklist de Google Cloud Console</h2>";
echo "<p>Verifica que en <a href='https://console.cloud.google.com/apis/credentials' target='_blank'>Google Cloud Console</a> tengas:</p>";
echo "<ol>";
echo "<li>El <strong>Client ID</strong> correcto: <code>" . htmlspecialchars(GOOGLE_CLIENT_ID ?? 'N/A') . "</code></li>";
echo "<li>En <strong>\"URIs de redirección autorizadas\"</strong> debe estar configurada EXACTAMENTE:<br><code>" . htmlspecialchars($redirectUri) . "</code></li>";
echo "<li>La API de <strong>Google+ API</strong> debe estar habilitada</li>";
echo "<li>El <strong>OAuth consent screen</strong> debe estar configurado</li>";
echo "</ol>";
echo "</div>";

// 5. Probar conexión a Google APIs
echo "<div class='box'>";
echo "<h2>5. Test de Conectividad</h2>";
echo "<p>Probando conexión a Google APIs...</p>";

$ch = curl_init('https://www.googleapis.com/oauth2/v2/tokeninfo?access_token=test');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode > 0) {
    echo "<p class='success'>✅ Conexión a Google APIs exitosa (HTTP $httpCode)</p>";
} else {
    echo "<p class='error'>❌ No se puede conectar a Google APIs</p>";
}
echo "</div>";

// 6. Verificar tabla de usuarios
echo "<div class='box'>";
echo "<h2>6. Verificación de Base de Datos</h2>";
try {
    require_once __DIR__ . '/includes/db.php';
    $pdo = db();

    // Verificar si existe la columna oauth_provider
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $hasOauthProvider = in_array('oauth_provider', $columns);
    $hasOauthId = in_array('oauth_id', $columns);

    if ($hasOauthProvider && $hasOauthId) {
        echo "<p class='success'>✅ Tabla <code>users</code> tiene columnas OAuth configuradas</p>";
    } else {
        echo "<p class='error'>❌ Faltan columnas OAuth en la tabla users:</p>";
        if (!$hasOauthProvider) echo "<p>- Falta: <code>oauth_provider</code></p>";
        if (!$hasOauthId) echo "<p>- Falta: <code>oauth_id</code></p>";
    }

    // Mostrar estructura de la tabla
    echo "<details><summary>Ver estructura de tabla users</summary>";
    echo "<pre>";
    $stmt = $pdo->query("DESCRIBE users");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
    echo "</pre></details>";

} catch (Exception $e) {
    echo "<p class='error'>❌ Error de base de datos: " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo "</div>";

echo "<hr>";
echo "<p style='text-align:center;'>";
echo "<a href='/login.php' style='display:inline-block;padding:12px 24px;background:#667eea;color:white;text-decoration:none;border-radius:6px;margin:5px;'>Ver Login Real</a>";
echo "<a href='/check_oauth.php' style='display:inline-block;padding:12px 24px;background:#059669;color:white;text-decoration:none;border-radius:6px;margin:5px;'>Verificar OAuth</a>";
echo "</p>";

echo "</body></html>";
