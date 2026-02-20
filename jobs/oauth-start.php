<?php
// jobs/oauth-start.php
// Inicia el flujo de Google OAuth para el módulo de Empleos

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$GOOGLE_CLIENT_ID = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';

if (empty($GOOGLE_CLIENT_ID)) {
    header('Location: /jobs/register.php?error=' . urlencode('Las credenciales de Google OAuth no están configuradas. Registrate con email.'));
    exit;
}

$_SESSION['jobs_oauth_state'] = bin2hex(random_bytes(16));

$baseUrl     = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
$redirectUri = $baseUrl . '/jobs/oauth-callback.php';

$googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => $GOOGLE_CLIENT_ID,
    'redirect_uri'  => $redirectUri,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $_SESSION['jobs_oauth_state'],
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]);

header('Location: ' . $googleAuthUrl);
exit;
