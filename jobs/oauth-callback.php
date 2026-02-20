<?php
// jobs/oauth-callback.php
// Maneja el callback de Google OAuth y crea/vincula empleadores

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = db();

function jobsLogOAuth($message, $context = []) {
    $logDir  = __DIR__ . '/../logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $logFile = $logDir . '/jobs_oauth.log';
    $line    = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if (!empty($context)) $line .= ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES);
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

jobsLogOAuth('OAuth callback iniciado', ['GET' => $_GET]);

// Verificar estado de seguridad
$state         = $_GET['state'] ?? '';
$expectedState = $_SESSION['jobs_oauth_state'] ?? '';

if (empty($state) || empty($expectedState) || !hash_equals($expectedState, $state)) {
    jobsLogOAuth('Estado de seguridad inválido', ['received' => $state, 'expected' => $expectedState]);
    header('Location: /jobs/register.php?error=' . urlencode('Estado de seguridad inválido.'));
    exit;
}

$code = $_GET['code'] ?? '';
if (empty($code)) {
    header('Location: /jobs/register.php?error=' . urlencode('Código de autorización no recibido.'));
    exit;
}

$GOOGLE_CLIENT_ID     = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
$GOOGLE_CLIENT_SECRET = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '';

if (empty($GOOGLE_CLIENT_ID) || empty($GOOGLE_CLIENT_SECRET)) {
    header('Location: /jobs/register.php?error=' . urlencode('Credenciales de Google no configuradas.'));
    exit;
}

try {
    $baseUrl     = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
    $redirectUri = $baseUrl . '/jobs/oauth-callback.php';

    // Intercambiar código por tokens
    $tokenData = [
        'code'          => $code,
        'client_id'     => $GOOGLE_CLIENT_ID,
        'client_secret' => $GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => $redirectUri,
        'grant_type'    => 'authorization_code',
    ];

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Error al obtener tokens de Google: HTTP ' . $httpCode);
    }

    $tokens = json_decode($response, true);
    if (empty($tokens['id_token'])) {
        throw new Exception('ID token no recibido de Google.');
    }

    // Obtener información del usuario
    $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($tokens['id_token']));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $userInfoResponse = curl_exec($ch);
    $userInfoHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($userInfoHttpCode !== 200) {
        throw new Exception('Error al obtener información del usuario de Google.');
    }

    $userInfo = json_decode($userInfoResponse, true);
    if (empty($userInfo['sub']) || empty($userInfo['email'])) {
        throw new Exception('Información del usuario incompleta.');
    }

    $email    = $userInfo['email'];
    $name     = $userInfo['name'] ?? $email;
    $oauthId  = $userInfo['sub'];

    // Usar la función unificada para obtener o crear usuario
    $user = get_or_create_oauth_user($email, $name, 'google', $oauthId);

    jobsLogOAuth('Login exitoso vía OAuth', ['user_id' => $user['id'], 'email' => $email]);

    // Iniciar sesión usando la función unificada
    login_user($user);

    unset($_SESSION['jobs_oauth_state']);
    session_write_close();

    header('Location: /jobs/dashboard.php?login=success');
    exit;

} catch (Exception $e) {
    jobsLogOAuth('Error en OAuth', ['message' => $e->getMessage()]);
    $userError = 'Error al autenticar con Google: ' . $e->getMessage();

    if (strpos($e->getMessage(), 'redirect_uri_mismatch') !== false) {
        $userError = 'Error de configuración OAuth. El redirect URI no coincide.';
    } elseif (strpos($e->getMessage(), 'invalid_client') !== false) {
        $userError = 'Credenciales de Google inválidas. Contactá al administrador.';
    }

    header('Location: /jobs/register.php?error=' . urlencode($userError));
    exit;
}
