<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
} else {
    ini_set('session.save_path', '/tmp');
}

$__isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
    || (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false);

$host = $_SERVER['HTTP_HOST'] ?? '';
$cookieDomain = '';
if ($host && strpos($host, 'localhost') === false && !filter_var($host, FILTER_VALIDATE_IP)) {
    $clean = preg_replace('/^www\./i', '', $host);
    if (filter_var($clean, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) $cookieDomain = $clean;
}

date_default_timezone_set('America/Costa_Rica');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PHPSESSID');
    if (PHP_VERSION_ID < 70300) {
        session_set_cookie_params(0, '/', $cookieDomain ?: '', $__isHttps, true);
    } else {
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/', 'domain' => $cookieDomain,
            'secure' => $__isHttps, 'httponly' => true, 'samesite' => 'Lax',
        ]);
    }
    ini_set('session.use_strict_mode', '0');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '86400');
    session_start();
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// OAuth Config
$GOOGLE_CLIENT_ID     = defined('GOOGLE_CLIENT_ID')     ? GOOGLE_CLIENT_ID     : '';
$GOOGLE_CLIENT_SECRET = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '';
$GOOGLE_REDIRECT_URI  = ($__isHttps ? 'https://' : 'http://') . $host . '/emprendedores-login.php?oauth=google';

$FACEBOOK_APP_ID      = defined('FACEBOOK_APP_ID')      ? FACEBOOK_APP_ID      : '';
$FACEBOOK_APP_SECRET  = defined('FACEBOOK_APP_SECRET')  ? FACEBOOK_APP_SECRET  : '';
$FACEBOOK_REDIRECT_URI = ($__isHttps ? 'https://' : 'http://') . $host . '/emprendedores-login.php?oauth=facebook';

$error   = '';
$success = '';

// --- Log de login ---
$__logDir = __DIR__ . '/logs';
if (!is_dir($__logDir)) @mkdir($__logDir, 0755, true);
$__logFile = $__logDir . '/emprendedoras-login.log';
function empLog(string $level, string $msg, array $ctx = []): void {
    global $__logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] [' . $level . '] ' . $msg;
    if ($ctx) $line .= ' | ' . json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    @file_put_contents($__logFile, $line . PHP_EOL, FILE_APPEND);
}

// Obtiene o crea el entrepreneur_id para un user_id dado.
// Garantiza que quien pasa por emprendedoras-login siempre tenga identidad de emprendedora.
function get_or_create_entrepreneur_id(PDO $pdo, int $uid): int {
    $stmt = $pdo->prepare("SELECT id FROM entrepreneurs WHERE user_id = ?");
    $stmt->execute([$uid]);
    $eid = (int)($stmt->fetchColumn() ?: 0);
    if ($eid <= 0) {
        $pdo->prepare("INSERT OR IGNORE INTO entrepreneurs (user_id, status) VALUES (?, 'active')")->execute([$uid]);
        $stmt->execute([$uid]);
        $eid = (int)($stmt->fetchColumn() ?: 0);
    }
    return $eid;
}

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['uid']) && $_SESSION['uid'] > 0 && empty($_GET['oauth'])) {
    header('Location: emprendedores-dashboard.php');
    exit;
}

// OAuth handlers
function handleGoogleOAuth($code, $clientId, $clientSecret, $redirectUri, $pdo) {
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'code' => $code, 'client_id' => $clientId, 'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri, 'grant_type' => 'authorization_code'
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $tokenInfo = json_decode($response, true);
    if (!isset($tokenInfo['access_token'])) return ['error' => 'Error con Google'];

    $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $tokenInfo['access_token']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $userData = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (!isset($userData['email'])) return ['error' => 'No se pudo obtener email'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$userData['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $name = $userData['name'] ?? explode('@', $userData['email'])[0];
        $randomPass = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
        $ins = $pdo->prepare("INSERT INTO users (name, email, password_hash, oauth_provider, oauth_id, created_at) VALUES (?, ?, ?, 'google', ?, NOW())");
        $ins->execute([$name, $userData['email'], $randomPass, $userData['id']]);
        $userId = (int)$pdo->lastInsertId();
    } else {
        $userId = (int)$user['id'];
    }

    return ['success' => true, 'user_id' => $userId, 'email' => $userData['email'], 'name' => $userData['name'] ?? $userData['email']];
}

function handleFacebookOAuth($code, $appId, $appSecret, $redirectUri, $pdo) {
    $tokenUrl = 'https://graph.facebook.com/v18.0/oauth/access_token?'
        . 'client_id=' . urlencode($appId)
        . '&client_secret=' . urlencode($appSecret)
        . '&redirect_uri=' . urlencode($redirectUri)
        . '&code=' . urlencode($code);

    $tokenInfo = json_decode(file_get_contents($tokenUrl), true);
    if (!isset($tokenInfo['access_token'])) return ['error' => 'Error con Facebook'];

    $userUrl = 'https://graph.facebook.com/v18.0/me?fields=id,name,email&access_token=' . $tokenInfo['access_token'];
    $userData = json_decode(file_get_contents($userUrl), true);

    if (!isset($userData['id'])) return ['error' => 'No se pudo obtener datos'];

    $email = $userData['email'] ?? ('fb_' . $userData['id'] . '@facebook.user');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE oauth_provider = 'facebook' AND oauth_id = ? LIMIT 1");
    $stmt->execute([$userData['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $name = $userData['name'] ?? 'Usuario Facebook';
        $randomPass = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
        $ins = $pdo->prepare("INSERT INTO users (name, email, password_hash, oauth_provider, oauth_id, created_at) VALUES (?, ?, ?, 'facebook', ?, NOW())");
        $ins->execute([$name, $email, $randomPass, $userData['id']]);
        $userId = (int)$pdo->lastInsertId();
    } else {
        $userId = (int)$user['id'];
    }

    return ['success' => true, 'user_id' => $userId, 'email' => $email, 'name' => $userData['name'] ?? 'Usuario Facebook'];
}

// Callback OAuth
if (isset($_GET['oauth']) && isset($_GET['code'])) {
    try {
        $pdo = db();
        $result = null;

        if ($_GET['oauth'] === 'google' && $GOOGLE_CLIENT_ID && $GOOGLE_CLIENT_SECRET) {
            $result = handleGoogleOAuth($_GET['code'], $GOOGLE_CLIENT_ID, $GOOGLE_CLIENT_SECRET, $GOOGLE_REDIRECT_URI, $pdo);
        } elseif ($_GET['oauth'] === 'facebook' && $FACEBOOK_APP_ID && $FACEBOOK_APP_SECRET) {
            $result = handleFacebookOAuth($_GET['code'], $FACEBOOK_APP_ID, $FACEBOOK_APP_SECRET, $FACEBOOK_REDIRECT_URI, $pdo);
        }

        if ($result && isset($result['success'])) {
            $oauthUid = (int)$result['user_id'];
            $_SESSION['uid']             = $oauthUid;
            $_SESSION['user_id']         = $oauthUid;
            $_SESSION['email']           = $result['email'];
            $_SESSION['user_email']      = $result['email'];
            $_SESSION['name']            = $result['name'];
            $_SESSION['user_name']       = $result['name'];
            $_SESSION['role']            = 'active';
            $_SESSION['entrepreneur_id'] = get_or_create_entrepreneur_id($pdo, $oauthUid);

            header('Location: emprendedores-dashboard.php');
            exit;
        } elseif ($result && isset($result['error'])) {
            $error = $result['error'];
        }
    } catch (Exception $e) {
        $error = 'Error en autenticación';
    }
}

// Formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo = db();

        if ($_POST['action'] === 'login') {
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($email && $password) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                $storedHash = $user ? ($user['password_hash'] ?? $user['password'] ?? null) : null;
                $ok = $storedHash ? password_verify($password, $storedHash) : false;

                if ($user && $ok) {
                    $loginUid = (int)$user['id'];
                    empLog('INFO', 'LOGIN_OK', ['email' => $email, 'user_id' => $loginUid, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
                    $_SESSION['uid']             = $loginUid;
                    $_SESSION['user_id']         = $loginUid;
                    $_SESSION['email']           = $user['email'];
                    $_SESSION['user_email']      = $user['email'];
                    $_SESSION['name']            = $user['name'] ?? '';
                    $_SESSION['user_name']       = $user['name'] ?? '';
                    $_SESSION['role']            = $user['status'] ?? 'active';
                    $_SESSION['entrepreneur_id'] = get_or_create_entrepreneur_id($pdo, $loginUid);

                    header('Location: emprendedores-dashboard.php');
                    exit;
                } else {
                    $reason = !$user ? 'usuario_no_encontrado' : (!$storedHash ? 'sin_hash' : 'password_incorrecta');
                    empLog('ERROR', 'LOGIN_FAIL', [
                        'email'   => $email,
                        'reason'  => $reason,
                        'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
                        'ua'      => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 80),
                    ]);
                    $error = 'Email o contraseña incorrectos';
                }
            } else {
                $error = 'Completa todos los campos';
            }
        }

        if ($_POST['action'] === 'register') {
            $name      = trim($_POST['name'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $phone     = trim($_POST['phone'] ?? '');
            $password  = $_POST['password'] ?? '';
            $password2 = $_POST['password2'] ?? '';
            if ($name && $email && $password) {
                if ($password !== $password2) {
                    $error = 'Las contraseñas no coinciden';
                } elseif (strlen($password) < 6) {
                    $error = 'La contraseña debe tener al menos 6 caracteres';
                } elseif ((string)($_POST['accept_terms'] ?? '') !== '1') {
                    $error = 'Debés aceptar los Términos y Condiciones para continuar';
                } else {
                    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $check->execute([$email]);
                    if ($check->fetch()) {
                        $error = 'Ya existe una cuenta con este email';
                    } else {
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $ins  = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $ins->execute([$name, $email, $phone, $hash]);
                        $newUid = (int)$pdo->lastInsertId();

                        // Registrar aceptación de T&C
                        try {
                            $tcStmt = $pdo->prepare("SELECT version FROM terms_conditions WHERE type='emprendedor' AND is_active=1 LIMIT 1");
                            $tcStmt->execute();
                            $tcRow = $tcStmt->fetch(PDO::FETCH_ASSOC);
                            $tcVersion = $tcRow['version'] ?? '1.0';
                            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                            $pdo->prepare("INSERT INTO terms_acceptances (user_table, user_id, terms_type, version, ip_address) VALUES ('users', ?, 'emprendedor', ?, ?)")
                                ->execute([$newUid, $tcVersion, $ip]);
                        } catch (Throwable $e) { error_log('[emprendedoras-login.php] T&C record failed: '.$e->getMessage()); }

                        $_SESSION['uid']             = $newUid;
                        $_SESSION['user_id']         = $newUid;
                        $_SESSION['email']           = $email;
                        $_SESSION['user_email']      = $email;
                        $_SESSION['name']            = $name;
                        $_SESSION['user_name']       = $name;
                        $_SESSION['role']            = 'active';
                        $_SESSION['entrepreneur_id'] = get_or_create_entrepreneur_id($pdo, $newUid);

                        header('Location: emprendedores-dashboard.php');
                        exit;
                    }
                }
            } else {
                $error = 'Completa todos los campos obligatorios';
            }
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

$googleLoginUrl = $GOOGLE_CLIENT_ID ? 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => $GOOGLE_CLIENT_ID,
    'redirect_uri'  => $GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'email profile',
]) : '';

$facebookLoginUrl = $FACEBOOK_APP_ID ? 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query([
    'client_id'     => $FACEBOOK_APP_ID,
    'redirect_uri'  => $FACEBOOK_REDIRECT_URI,
    'scope'         => 'email',
    'response_type' => 'code'
]) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Acceso Emprendedores | CompraTica</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: system-ui, -apple-system, sans-serif;
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.container {
    background: #fff;
    border-radius: 24px;
    box-shadow: 0 25px 70px rgba(0,0,0,0.25);
    max-width: 560px;
    width: 100%;
    overflow: hidden;
}
.header {
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
    color: #fff;
    padding: 40px 30px 30px;
    text-align: center;
}
.header .logo {
    font-size: 3rem;
    margin-bottom: 10px;
}
.header h1 { font-size: 1.7rem; margin-bottom: 6px; }
.header p { opacity: 0.85; font-size: 0.95rem; }
.tabs {
    display: flex;
    border-bottom: 2px solid #e5e7eb;
}
.tab {
    flex: 1;
    padding: 14px;
    text-align: center;
    cursor: pointer;
    background: #f9fafb;
    border: none;
    font-size: 1rem;
    font-weight: 600;
    color: #6b7280;
    transition: all 0.3s;
}
.tab.active {
    background: #fff;
    color: #2563eb;
    border-bottom: 3px solid #2563eb;
}
.tab:hover { background: #f3f4f6; }
.content { padding: 28px; }
.tab-content { display: none; }
.tab-content.active { display: block; }
.form-group { margin-bottom: 18px; }
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 0.9rem;
}
.form-group input {
    width: 100%;
    padding: 13px 15px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s;
}
.form-group input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
}
.btn {
    width: 100%;
    padding: 13px;
    border: none;
    border-radius: 50px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 6px;
}
.btn-primary {
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
    color: #fff;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102,126,234,0.4);
}
.btn-oauth {
    width: 100%;
    padding: 12px;
    border: 2px solid #e5e7eb;
    border-radius: 50px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 8px;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    text-decoration: none;
    color: #374151;
}
.btn-oauth:hover { border-color: #2563eb; background: #f9fafb; transform: translateY(-2px); }
.btn-oauth.google:hover { background: #4285f4; color: #fff; border-color: #4285f4; }
.btn-oauth.facebook:hover { background: #1877f2; color: #fff; border-color: #1877f2; }
.divider {
    display: flex;
    align-items: center;
    margin: 20px 0;
    color: #9ca3af;
    font-size: 0.9rem;
}
.divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e5e7eb; }
.divider span { padding: 0 12px; }
.alert {
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 16px;
    font-size: 0.9rem;
}
.alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
.footer {
    text-align: center;
    padding: 18px;
    background: #f9fafb;
    color: #6b7280;
    font-size: 0.9rem;
    border-top: 1px solid #e5e7eb;
}
.footer a { color: #2563eb; text-decoration: none; font-weight: 600; }
.footer a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo">🚀</div>
        <h1>Área de Emprendedores</h1>
        <p>Inicia sesión para acceder a tu dashboard</p>
    </div>

    <?php if ($error): ?>
        <div class="content" style="padding-bottom:0">
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="content" style="padding-bottom:0">
            <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
        </div>
    <?php endif; ?>

    <div class="tabs">
        <button class="tab active" id="tab-login" onclick="switchTab('login')">Iniciar Sesión</button>
        <button class="tab" id="tab-register" onclick="switchTab('register')">Crear Cuenta</button>
    </div>

    <div class="content">
        <!-- TAB LOGIN -->
        <div id="login-tab" class="tab-content active">
            <?php if ($googleLoginUrl || $facebookLoginUrl): ?>
            <div style="margin-bottom:20px">
                <?php if ($googleLoginUrl): ?>
                <a href="<?= htmlspecialchars($googleLoginUrl) ?>" class="btn-oauth google">
                    <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.258c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332C2.438 15.983 5.482 18 9 18z"/><path fill="#FBBC05" d="M3.964 10.707c-.18-.54-.282-1.117-.282-1.707 0-.593.102-1.17.282-1.709V4.958H.957C.347 6.173 0 7.548 0 9c0 1.452.348 2.827.957 4.042l3.007-2.335z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0 5.482 0 2.438 2.017.957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z"/></svg>
                    Continuar con Google
                </a>
                <?php endif; ?>
                <?php if ($facebookLoginUrl): ?>
                <a href="<?= htmlspecialchars($facebookLoginUrl) ?>" class="btn-oauth facebook">
                    <svg width="18" height="18" fill="#1877f2" viewBox="0 0 24 24"><path d="M24 12c0-6.627-5.373-12-12-12S0 5.373 0 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 22.954 24 17.99 24 12z"/></svg>
                    Continuar con Facebook
                </a>
                <?php endif; ?>
            </div>
            <div class="divider"><span>O con email</span></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="tu@email.com" required>
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Entrar al Dashboard
                </button>
            </form>
        </div>

        <!-- TAB REGISTRO -->
        <div id="register-tab" class="tab-content">
            <?php if ($googleLoginUrl || $facebookLoginUrl): ?>
            <div style="margin-bottom:20px">
                <?php if ($googleLoginUrl): ?>
                <a href="<?= htmlspecialchars($googleLoginUrl) ?>" class="btn-oauth google">
                    <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.258c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332C2.438 15.983 5.482 18 9 18z"/><path fill="#FBBC05" d="M3.964 10.707c-.18-.54-.282-1.117-.282-1.707 0-.593.102-1.17.282-1.709V4.958H.957C.347 6.173 0 7.548 0 9c0 1.452.348 2.827.957 4.042l3.007-2.335z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0 5.482 0 2.438 2.017.957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z"/></svg>
                    Registrarse con Google
                </a>
                <?php endif; ?>
                <?php if ($facebookLoginUrl): ?>
                <a href="<?= htmlspecialchars($facebookLoginUrl) ?>" class="btn-oauth facebook">
                    <svg width="18" height="18" fill="#1877f2" viewBox="0 0 24 24"><path d="M24 12c0-6.627-5.373-12-12-12S0 5.373 0 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 22.954 24 17.99 24 12z"/></svg>
                    Registrarse con Facebook
                </a>
                <?php endif; ?>
            </div>
            <div class="divider"><span>O con email</span></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label>Nombre completo *</label>
                    <input type="text" name="name" placeholder="Tu nombre" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" placeholder="tu@email.com" required>
                </div>
                <div class="form-group">
                    <label>Teléfono (opcional)</label>
                    <input type="tel" name="phone" placeholder="+506 8888-8888">
                </div>
                <div class="form-group">
                    <label>Contraseña *</label>
                    <input type="password" name="password" placeholder="Mínimo 6 caracteres" required>
                </div>
                <div class="form-group">
                    <label>Confirmar contraseña *</label>
                    <input type="password" name="password2" placeholder="Repite tu contraseña" required>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-start;gap:0.5rem;">
                    <input type="checkbox" id="accept_terms_reg" name="accept_terms" value="1" required style="margin-top:3px;flex-shrink:0;">
                    <label for="accept_terms_reg" style="cursor:pointer;font-size:0.875rem;">
                        Acepto los <a href="/terminos-condiciones.php?type=emprendedor" target="_blank" style="color:#2563eb">Términos y Condiciones</a> para emprendedores de CompraTica.
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">
                    Crear mi cuenta de Emprendedor/a
                </button>
            </form>
        </div>
    </div>

    <div class="footer">
        <a href="emprendedoras">← Volver a Emprendedores CompraTica</a>
        &nbsp;|&nbsp;
        <a href="index">Inicio</a>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    if (tab === 'login') {
        document.getElementById('tab-login').classList.add('active');
        document.getElementById('login-tab').classList.add('active');
    } else {
        document.getElementById('tab-register').classList.add('active');
        document.getElementById('register-tab').classList.add('active');
    }
}
</script>
</body>
</html>
