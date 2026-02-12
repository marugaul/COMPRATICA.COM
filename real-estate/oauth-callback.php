<?php
// real-estate/oauth-callback.php
// Maneja el callback de Google OAuth y crea/vincula agentes de bienes raíces

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

// Verificar estado de seguridad
$state = $_GET['state'] ?? '';
$expectedState = $_SESSION['re_oauth_state'] ?? '';

if (empty($state) || empty($expectedState) || !hash_equals($expectedState, $state)) {
    header('Location: /real-estate/register.php?error=' . urlencode('Estado de seguridad inválido'));
    exit;
}

// Verificar que tenemos un código de autorización
$code = $_GET['code'] ?? '';
if (empty($code)) {
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
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Error al intercambiar código por token: HTTP ' . $httpCode);
    }

    $tokens = json_decode($response, true);
    if (empty($tokens['id_token'])) {
        throw new Exception('ID token no recibido de Google');
    }

    // Obtener información del usuario desde el ID token
    $userInfoUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($tokens['id_token']);
    $userInfoResponse = file_get_contents($userInfoUrl);
    $userInfo = json_decode($userInfoResponse, true);

    if (empty($userInfo['sub']) || empty($userInfo['email'])) {
        throw new Exception('Información del usuario incompleta');
    }

    $googleId = $userInfo['sub'];
    $email = $userInfo['email'];
    $name = $userInfo['name'] ?? '';
    $emailVerified = ($userInfo['email_verified'] ?? 'false') === 'true';

    // Verificar si ya existe un agente con este email
    $stmt = $pdo->prepare("SELECT id, name FROM real_estate_agents WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $existingAgent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingAgent) {
        // El agente ya existe, iniciar sesión
        $agentId = (int)$existingAgent['id'];
        $agentName = $existingAgent['name'];

        session_regenerate_id(true);
        $_SESSION['agent_id'] = $agentId;
        $_SESSION['agent_name'] = $agentName;
        unset($_SESSION['re_oauth_state']);

        header('Location: /real-estate/dashboard.php?login=success');
        exit;
    }

    // El agente no existe, crear uno nuevo
    // Para Google OAuth, no necesitamos contraseña, usaremos un hash vacío especial
    $stmt = $pdo->prepare("
        INSERT INTO real_estate_agents (name, email, phone, password_hash, is_active, created_at)
        VALUES (?, ?, '', '', 1, datetime('now'))
    ");
    $stmt->execute([$name, $email]);

    $agentId = (int)$pdo->lastInsertId();

    // Iniciar sesión automáticamente
    session_regenerate_id(true);
    $_SESSION['agent_id'] = $agentId;
    $_SESSION['agent_name'] = $name;
    unset($_SESSION['re_oauth_state']);

    // Redirigir al dashboard con mensaje de bienvenida
    header('Location: /real-estate/dashboard.php?welcome=1');
    exit;

} catch (Exception $e) {
    error_log('[OAuth Callback Error] ' . $e->getMessage());
    header('Location: /real-estate/register.php?error=' . urlencode('Error al autenticar con Google. Por favor, intentá de nuevo.'));
    exit;
}
