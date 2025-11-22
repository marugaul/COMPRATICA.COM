<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Verificación OAuth - CompraTica</title>
    <style>
        body { font-family: Arial; padding: 40px; background: #f5f5f5; max-width: 800px; margin: 0 auto; }
        .success { background: #f0fdf4; border-left: 4px solid #16a34a; padding: 20px; margin: 15px 0; border-radius: 4px; }
        .error { background: #fee; border-left: 4px solid #dc2626; padding: 20px; margin: 15px 0; border-radius: 4px; }
        .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 20px; margin: 15px 0; border-radius: 4px; }
        .info { background: #eff6ff; border-left: 4px solid #0891b2; padding: 20px; margin: 15px 0; border-radius: 4px; }
        h1 { color: #3b82f6; }
        code { background: #1f2937; color: #10b981; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        pre { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 8px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔐 Verificación de Credenciales OAuth</h1>

    <?php
    require_once __DIR__ . '/includes/config.php';

    $configLocalExists = file_exists(__DIR__ . '/includes/config.local.php');
    $googleConfigured = defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== '';
    $facebookConfigured = defined('FACEBOOK_APP_ID') && FACEBOOK_APP_ID !== '';

    echo "<div class='info'>";
    echo "<h3>📄 Archivo config.local.php</h3>";
    if ($configLocalExists) {
        echo "<p class='success'>✓ <strong>Existe:</strong> /includes/config.local.php</p>";
    } else {
        echo "<p class='error'>✗ <strong>NO existe:</strong> /includes/config.local.php</p>";
        echo "<p>Debes crearlo con tus credenciales OAuth.</p>";
    }
    echo "</div>";

    echo "<div class='info'>";
    echo "<h3>🔑 Google OAuth</h3>";
    if ($googleConfigured) {
        $clientId = GOOGLE_CLIENT_ID;
        $maskedId = substr($clientId, 0, 20) . '...' . substr($clientId, -10);
        echo "<p class='success'>✓ <strong>Configurado</strong></p>";
        echo "<p><code>GOOGLE_CLIENT_ID</code>: $maskedId</p>";
        echo "<p><code>GOOGLE_CLIENT_SECRET</code>: " . (defined('GOOGLE_CLIENT_SECRET') && GOOGLE_CLIENT_SECRET !== '' ? '✓ Configurado' : '✗ Falta') . "</p>";
    } else {
        echo "<p class='error'>✗ <strong>NO configurado</strong></p>";
        echo "<p>Agrega tus credenciales de Google a <code>config.local.php</code></p>";
    }
    echo "</div>";

    echo "<div class='info'>";
    echo "<h3>📘 Facebook OAuth</h3>";
    if ($facebookConfigured) {
        $appId = FACEBOOK_APP_ID;
        echo "<p class='success'>✓ <strong>Configurado</strong></p>";
        echo "<p><code>FACEBOOK_APP_ID</code>: $appId</p>";
        echo "<p><code>FACEBOOK_APP_SECRET</code>: " . (defined('FACEBOOK_APP_SECRET') && FACEBOOK_APP_SECRET !== '' ? '✓ Configurado' : '✗ Falta') . "</p>";
    } else {
        echo "<p class='error'>✗ <strong>NO configurado</strong></p>";
        echo "<p>Agrega tus credenciales de Facebook a <code>config.local.php</code></p>";
    }
    echo "</div>";

    echo "<div class='info'>";
    echo "<h3>🌐 URIs de Redirección</h3>";
    $host = $_SERVER['HTTP_HOST'] ?? 'compratica.com';
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    echo "<p><strong>Google Redirect URI:</strong><br><code>{$protocol}://{$host}/login.php?oauth=google</code></p>";
    echo "<p><strong>Facebook Redirect URI:</strong><br><code>{$protocol}://{$host}/login.php?oauth=facebook</code></p>";
    echo "</div>";

    echo "<div class='" . ($googleConfigured && $facebookConfigured ? 'success' : 'warning') . "'>";
    echo "<h3>📋 Resumen</h3>";
    if ($googleConfigured && $facebookConfigured) {
        echo "<p><strong>✅ OAuth completamente configurado</strong></p>";
        echo "<p>Los botones de 'Continuar con Google' y 'Continuar con Facebook' deberían aparecer en <a href='/login.php'>/login.php</a></p>";
    } elseif ($googleConfigured || $facebookConfigured) {
        echo "<p><strong>⚠️ OAuth parcialmente configurado</strong></p>";
        echo "<p>Solo " . ($googleConfigured ? 'Google' : 'Facebook') . " está configurado.</p>";
    } else {
        echo "<p><strong>❌ OAuth NO configurado</strong></p>";
        echo "<p>Los botones de OAuth no aparecerán hasta que configures las credenciales.</p>";
    }
    echo "</div>";

    if (!$googleConfigured || !$facebookConfigured) {
        echo "<div class='warning'>";
        echo "<h3>🔧 Cómo Recuperar las Credenciales</h3>";
        echo "<p><strong>Opción 1:</strong> Si tienes acceso al servidor de producción:</p>";
        echo "<pre>cat /home/comprati/public_html/includes/config.local.php</pre>";
        echo "<p>Copia las credenciales de ahí.</p>";

        echo "<p><strong>Opción 2:</strong> Si no existen en producción, créalas desde cero:</p>";
        echo "<ul>";
        echo "<li><strong>Google:</strong> <a href='https://console.cloud.google.com/apis/credentials' target='_blank'>console.cloud.google.com</a></li>";
        echo "<li><strong>Facebook:</strong> <a href='https://developers.facebook.com/apps' target='_blank'>developers.facebook.com</a></li>";
        echo "</ul>";
        echo "</div>";
    }
    ?>

    <p style="text-align:center;margin-top:30px;">
        <a href="/login.php" style="display:inline-block;padding:12px 24px;background:#3b82f6;color:white;text-decoration:none;border-radius:6px;">Ver Login</a>
        <a href="/index.php" style="display:inline-block;padding:12px 24px;background:#dc2626;color:white;text-decoration:none;border-radius:6px;margin-left:10px;">← Inicio</a>
    </p>
</body>
</html>
