<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/security.php';

// Carga llaves OAuth (archivo tuyo)
$OAUTH = ['google'=>['id'=>'','secret'=>''],'facebook'=>['id'=>'','secret'=>''],'apple'=>['client_id'=>'']];
$path = __DIR__ . '/../includes/config.oauth.php';
if (file_exists($path)) { /** @noinspection PhpIncludeInspection */ $OAUTH = include $path; }

$provider = strtolower((string)($_GET['provider'] ?? ''));
if (!in_array($provider, ['google','facebook','apple'], true)) {
  http_response_code(400); echo "Proveedor inválido"; exit;
}
$_SESSION['oauth_state'] = bin2hex(random_bytes(16));

$baseUrl  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
$redirect = $baseUrl . '/auth/callback.php?provider=' . urlencode($provider);

switch ($provider) {
  case 'google':
    $id = $OAUTH['google']['id'] ?? '';
    $url = 'https://accounts.google.com/o/oauth2/v2/auth?response_type=code'
         . '&client_id=' . urlencode($id)
         . '&redirect_uri=' . urlencode($redirect)
         . '&scope=' . urlencode('openid email profile')
         . '&state=' . urlencode($_SESSION['oauth_state']);
    safe_redirect($url, '/');
  case 'facebook':
    $id = $OAUTH['facebook']['id'] ?? '';
    $url = 'https://www.facebook.com/v19.0/dialog/oauth?response_type=code'
         . '&client_id=' . urlencode($id)
         . '&redirect_uri=' . urlencode($redirect)
         . '&scope=' . urlencode('email,public_profile')
         . '&state=' . urlencode($_SESSION['oauth_state']);
    safe_redirect($url, '/');
  case 'apple':
    $id = $OAUTH['apple']['client_id'] ?? '';
    $url = 'https://appleid.apple.com/auth/authorize?response_type=code%20id_token&response_mode=form_post'
         . '&client_id=' . urlencode($id)
         . '&redirect_uri=' . urlencode($redirect)
         . '&scope=' . urlencode('name email')
         . '&state=' . urlencode($_SESSION['oauth_state']);
    safe_redirect($url, '/');
}
