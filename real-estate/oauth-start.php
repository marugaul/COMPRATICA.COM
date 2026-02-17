<?php
// real-estate/oauth-start.php
// Inicia el flujo de OAuth para agentes de bienes raíces

// Habilitar manejo de errores visible temporalmente
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';

// Log inicial
error_log('[OAuth Start] Iniciando flujo de Google OAuth para bienes raíces');

// Cargar credenciales de Google
$GOOGLE_CLIENT_ID = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';

if (empty($GOOGLE_CLIENT_ID)) {
    error_log('[OAuth Start] ERROR: GOOGLE_CLIENT_ID no configurado');
    die('
    <!DOCTYPE html>
    <html lang="es">
    <head><meta charset="utf-8"><title>Error OAuth</title>
    <style>body{font-family:Arial,sans-serif;padding:40px;max-width:600px;margin:0 auto;}
    .error{background:#fee;border:2px solid #c00;padding:20px;border-radius:8px;}
    h1{color:#c00;margin-top:0;}</style></head>
    <body><div class="error">
    <h1>⚠️ Error de Configuración</h1>
    <p><strong>Las credenciales de Google OAuth no están configuradas.</strong></p>
    <p>El administrador debe verificar que el archivo <code>includes/config.local.php</code>
    contenga las constantes <code>GOOGLE_CLIENT_ID</code> y <code>GOOGLE_CLIENT_SECRET</code>.</p>
    <p><a href="register.php">← Volver al registro</a></p>
    </div></body></html>
    ');
}

error_log('[OAuth Start] Client ID presente: ' . substr($GOOGLE_CLIENT_ID, 0, 20) . '...');

// Generar estado de seguridad
$_SESSION['re_oauth_state'] = bin2hex(random_bytes(16));
error_log('[OAuth Start] Estado de seguridad generado');

// Construir URL de redirección
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://')
           . $_SERVER['HTTP_HOST'];
$redirectUri = $baseUrl . '/real-estate/oauth-callback.php';

error_log('[OAuth Start] Redirect URI: ' . $redirectUri);

// Construir URL de autorización de Google
$googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => $GOOGLE_CLIENT_ID,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $_SESSION['re_oauth_state'],
    'access_type' => 'online',
    'prompt' => 'select_account'
]);

error_log('[OAuth Start] Redirigiendo a Google...');
error_log('[OAuth Start] URL: ' . substr($googleAuthUrl, 0, 100) . '...');

// Redirigir a Google
header('Location: ' . $googleAuthUrl);
exit;
