<?php
// real-estate/oauth-callback.php
// Maneja el callback de Google OAuth y crea/vincula agentes de bienes raíces

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';

$pdo = db();

// Función para logging detallado
function logOAuthError($message, $context = []) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/real_estate_oauth.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";

    if (!empty($context)) {
        $logEntry .= "Context: " . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    $logEntry .= str_repeat('-', 80) . "\n";

    @file_put_contents($logFile, $logEntry, FILE_APPEND);
    error_log($message); // También log al error_log de PHP
}

// Log inicio del callback
logOAuthError('OAuth Callback iniciado', [
    'GET' => $_GET,
    'SESSION' => [
        'oauth_state' => $_SESSION['re_oauth_state'] ?? 'NO SET',
    ],
    'SERVER' => [
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? '',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '',
        'HTTPS' => $_SERVER['HTTPS'] ?? 'off',
    ]
]);

// Verificar estado de seguridad
$state = $_GET['state'] ?? '';
$expectedState = $_SESSION['re_oauth_state'] ?? '';

if (empty($state) || empty($expectedState) || !hash_equals($expectedState, $state)) {
    logOAuthError('Error: Estado de seguridad inválido', [
        'state_received' => $state,
        'state_expected' => $expectedState,
        'state_empty' => empty($state),
        'expected_empty' => empty($expectedState),
    ]);
    header('Location: /real-estate/register.php?error=' . urlencode('Estado de seguridad inválido'));
    exit;
}

// Verificar que tenemos un código de autorización
$code = $_GET['code'] ?? '';
if (empty($code)) {
    logOAuthError('Error: Código de autorización no recibido', [
        'GET_params' => $_GET,
        'error' => $_GET['error'] ?? 'none',
        'error_description' => $_GET['error_description'] ?? 'none',
    ]);
    header('Location: /real-estate/register.php?error=' . urlencode('Código de autorización no recibido'));
    exit;
}

// Cargar credenciales de Google
$GOOGLE_CLIENT_ID = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
$GOOGLE_CLIENT_SECRET = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '';

if (empty($GOOGLE_CLIENT_ID) || empty($GOOGLE_CLIENT_SECRET)) {
    header('Location: /real-estate/register.php?error=' . urlencode('Credenciales de Google no configuradas'));
    exit;
}

try {
    // Construir URL de redirección (debe coincidir con la configuración de Google Cloud Console)
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://')
               . $_SERVER['HTTP_HOST'];
    $redirectUri = $baseUrl . '/real-estate/oauth-callback.php';

    // Log para debug
    error_log('[OAuth Debug] Redirect URI: ' . $redirectUri);

    // Intercambiar código por tokens
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $tokenData = [
        'code' => $code,
        'client_id' => $GOOGLE_CLIENT_ID,
        'client_secret' => $GOOGLE_CLIENT_SECRET,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        logOAuthError('Error al intercambiar código por token', [
            'http_code' => $httpCode,
            'response' => $response,
            'curl_error' => $curlError,
            'token_url' => $tokenUrl,
            'redirect_uri' => $redirectUri,
        ]);
        throw new Exception('Error al intercambiar código por token: HTTP ' . $httpCode . ' - ' . $response);
    }

    $tokens = json_decode($response, true);
    if (empty($tokens['id_token'])) {
        logOAuthError('No id_token en la respuesta de Google', [
            'response' => $response,
            'tokens' => $tokens,
        ]);
        throw new Exception('ID token no recibido de Google');
    }

    // Obtener información del usuario desde el ID token usando cURL (más confiable que file_get_contents)
    $userInfoUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($tokens['id_token']);

    $ch = curl_init($userInfoUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $userInfoResponse = curl_exec($ch);
    $userInfoHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($userInfoHttpCode !== 200) {
        logOAuthError('Error al obtener información del usuario', [
            'http_code' => $userInfoHttpCode,
            'response' => $userInfoResponse,
            'url' => $userInfoUrl,
        ]);
        throw new Exception('Error al obtener información del usuario');
    }

    $userInfo = json_decode($userInfoResponse, true);

    if (empty($userInfo['sub']) || empty($userInfo['email'])) {
        throw new Exception('Información del usuario incompleta');
    }

    $googleId = $userInfo['sub'];
    $email = $userInfo['email'];
    $name = $userInfo['name'] ?? '';
    $emailVerified = ($userInfo['email_verified'] ?? 'false') === 'true';

    // Usar la función unificada para obtener o crear usuario
    $user = get_or_create_oauth_user($email, $name, 'google', $googleId);

    logOAuthError('Login exitoso vía OAuth', [
        'user_id' => $user['id'],
        'email' => $email,
        'name' => $name
    ]);

    // Iniciar sesión usando la función unificada
    login_user($user);

    unset($_SESSION['re_oauth_state']);

    // Asegurar que la sesión se persista antes de redirigir
    session_write_close();

    header('Location: /real-estate/dashboard.php?login=success');
    exit;

} catch (Exception $e) {
    $errorMsg = $e->getMessage();

    logOAuthError('Excepción capturada en OAuth callback', [
        'message' => $errorMsg,
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    // Proporcionar mensajes de error más útiles
    if (strpos($errorMsg, 'no such table: users') !== false) {
        $userError = 'Error de configuración: La tabla de usuarios no existe. Contactá al administrador.';
    } elseif (strpos($errorMsg, 'redirect_uri_mismatch') !== false || strpos($errorMsg, 'HTTP 400') !== false) {
        $userError = 'Error de configuración OAuth. El redirect URI no coincide. Contactá al administrador.';
    } elseif (strpos($errorMsg, 'invalid_client') !== false) {
        $userError = 'Credenciales de Google inválidas. Contactá al administrador.';
    } elseif (strpos($errorMsg, 'access_denied') !== false) {
        $userError = 'Acceso denegado. No autorizaste el acceso a tu cuenta de Google.';
    } else {
        $userError = 'Error al autenticar con Google: ' . $errorMsg;
    }

    header('Location: /real-estate/register.php?error=' . urlencode($userError));
    exit;
}
