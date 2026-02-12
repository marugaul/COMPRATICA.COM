<?php
// real-estate/oauth-start.php
// Inicia el flujo de OAuth para agentes de bienes raíces

session_start();
require_once __DIR__ . '/../includes/config.php';

// Cargar credenciales de Google
$GOOGLE_CLIENT_ID = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';

if (empty($GOOGLE_CLIENT_ID)) {
    die('Error: Credenciales de Google no configuradas');
}

// Generar estado de seguridad
$_SESSION['re_oauth_state'] = bin2hex(random_bytes(16));

// Construir URL de redirección
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://')
           . $_SERVER['HTTP_HOST'];
$redirectUri = $baseUrl . '/real-estate/oauth-callback.php';

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

// Redirigir a Google
header('Location: ' . $googleAuthUrl);
exit;
