<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$logFile = $logDir . '/login_debug.log';

function logDebug($msg, $data = null) {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($data !== null) {
        $enc = @json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $line .= ' | ' . ($enc !== false ? $enc : print_r($data, true));
    }
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

logDebug("LOGIN_START", ['uri' => $_SERVER['REQUEST_URI'] ?? '']);

$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
} else {
    // Fallback a /tmp si no se puede escribir en sessions
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
$GOOGLE_CLIENT_ID = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
$GOOGLE_CLIENT_SECRET = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : '';
$GOOGLE_REDIRECT_URI = ($__isHttps ? 'https://' : 'http://') . $host . '/login.php?oauth=google';

$FACEBOOK_APP_ID = defined('FACEBOOK_APP_ID') ? FACEBOOK_APP_ID : '';
$FACEBOOK_APP_SECRET = defined('FACEBOOK_APP_SECRET') ? FACEBOOK_APP_SECRET : '';
$FACEBOOK_REDIRECT_URI = ($__isHttps ? 'https://' : 'http://') . $host . '/login.php?oauth=facebook';

$error = '';
$success = '';
$redirect = $_GET['redirect'] ?? 'index.php';

if (isset($_SESSION['uid']) && $_SESSION['uid'] > 0 && empty($_GET['reset']) && empty($_GET['oauth'])) {
    header('Location: ' . $redirect);
    exit;
}

// OAuth Handlers
function handleGoogleOAuth($code, $clientId, $clientSecret, $redirectUri, $pdo) {
    logDebug("GOOGLE_OAUTH_START");
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
        $ins = $pdo->prepare("INSERT INTO users (name, email, password_hash, oauth_provider, oauth_id, created_at) VALUES (?, ?, ?, 'google', ?, datetime('now'))");
        $ins->execute([$name, $userData['email'], $randomPass, $userData['id']]);
        $userId = (int)$pdo->lastInsertId();
    } else {
        $userId = (int)$user['id'];
    }
    
    return ['success' => true, 'user_id' => $userId, 'email' => $userData['email'], 'name' => $userData['name'] ?? $userData['email']];
}

function handleFacebookOAuth($code, $appId, $appSecret, $redirectUri, $pdo) {
    logDebug("FACEBOOK_OAUTH_START");
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
        $ins = $pdo->prepare("INSERT INTO users (name, email, password_hash, oauth_provider, oauth_id, created_at) VALUES (?, ?, ?, 'facebook', ?, datetime('now'))");
        $ins->execute([$name, $email, $randomPass, $userData['id']]);
        $userId = (int)$pdo->lastInsertId();
    } else {
        $userId = (int)$user['id'];
    }
    
    return ['success' => true, 'user_id' => $userId, 'email' => $email, 'name' => $userData['name'] ?? 'Usuario Facebook'];
}

// OAuth Callback
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
            $old_sid = session_id();
            try {
                $stmt = $pdo->prepare("UPDATE carts SET user_id = ? WHERE guest_sid = ?");
                $stmt->execute([$result['user_id'], $old_sid]);
            } catch (Exception $e) {}
            
            $_SESSION['uid'] = $result['user_id'];
            $_SESSION['email'] = $result['email'];
            $_SESSION['name'] = $result['name'];
            $_SESSION['role'] = 'active';
            
            session_regenerate_id(false);
            logDebug("OAUTH_LOGIN_OK", ['provider' => $_GET['oauth'], 'uid' => $result['user_id']]);
            
            header('Location: ' . $redirect);
            exit;
        } elseif ($result && isset($result['error'])) {
            $error = $result['error'];
        }
    } catch (Exception $e) {
        $error = 'Error en autenticación';
    }
}

$showResetForm = false;
$resetTokenFromGet = trim((string)($_GET['token'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo = db();

        if ($_POST['action'] === 'login') {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($email && $password) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                $storedHash = $user ? ($user['password_hash'] ?? $user['password'] ?? null) : null;
                $ok = $storedHash ? password_verify($password, $storedHash) : false;

                if ($user && $ok) {
                    $old_sid = session_id();
                    try {
                        $stmt = $pdo->prepare("UPDATE carts SET user_id = ? WHERE guest_sid = ?");
                        $stmt->execute([$user['id'], $old_sid]);
                    } catch (Exception $e) {}

                    $_SESSION['uid'] = (int)$user['id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['name'] = $user['name'] ?? '';
                    $_SESSION['role'] = $user['status'] ?? 'active';

                    session_regenerate_id(false);
                    header('Location: ' . $redirect);
                    exit;
                } else {
                    $error = 'Email o contraseña incorrectos';
                }
            } else {
                $error = 'Completa todos los campos';
            }
        }

        if ($_POST['action'] === 'register') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $password2 = $_POST['password2'] ?? '';

            if ($name && $email && $password) {
                if ($password !== $password2) {
                    $error = 'Las contraseñas no coinciden';
                } elseif (strlen($password) < 6) {
                    $error = 'La contraseña debe tener al menos 6 caracteres';
                } else {
                    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $check->execute([$email]);
                    if ($check->fetch()) {
                        $error = 'Ya existe una cuenta con este email';
                    } else {
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $ins = $pdo->prepare("INSERT INTO users (name, email, phone, password_hash, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $ins->execute([$name, $email, $phone, $hash]);
                        $newUid = (int)$pdo->lastInsertId();

                        $old_sid = session_id();
                        try {
                            $stmt = $pdo->prepare("UPDATE carts SET user_id = ? WHERE guest_sid = ?");
                            $stmt->execute([$newUid, $old_sid]);
                        } catch (Exception $e) {}

                        $_SESSION['uid'] = $newUid;
                        $_SESSION['email'] = $email;
                        $_SESSION['name'] = $name;
                        $_SESSION['role'] = 'active';

                        session_regenerate_id(true);
                        header('Location: ' . $redirect);
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
} else {
    if (!empty($_GET['reset']) && !empty($resetTokenFromGet)) {
        $showResetForm = true;
    }
}

$APP_NAME = defined('APP_NAME') ? APP_NAME : 'Compratica';

$googleLoginUrl = $GOOGLE_CLIENT_ID ? 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => $GOOGLE_CLIENT_ID,
    'redirect_uri' => $GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'email profile',
]) : '';

$facebookLoginUrl = $FACEBOOK_APP_ID ? 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query([
    'client_id' => $FACEBOOK_APP_ID,
    'redirect_uri' => $FACEBOOK_REDIRECT_URI,
    'scope' => 'email',
    'response_type' => 'code'
]) : '';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - <?= htmlspecialchars($APP_NAME) ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:system-ui,-apple-system,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.container{background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,0.3);max-width:560px;width:100%;overflow:hidden}
.header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:30px;text-align:center}
.header h1{font-size:1.8rem;margin-bottom:8px}
.header p{opacity:0.9;font-size:0.95rem}
.tabs{display:flex;border-bottom:2px solid #e5e7eb}
.tab{flex:1;padding:14px;text-align:center;cursor:pointer;background:#f9fafb;border:none;font-size:1rem;font-weight:600;color:#6b7280;transition:all 0.3s}
.tab.active{background:#fff;color:#667eea;border-bottom:3px solid #667eea}
.tab:hover{background:#f3f4f6}
.content{padding:24px}
.tab-content{display:none}
.tab-content.active{display:block}
.form-group{margin-bottom:16px}
.form-group label{display:block;margin-bottom:8px;font-weight:600;color:#374151;font-size:0.9rem}
.form-group input{width:100%;padding:12px;border:2px solid #e5e7eb;border-radius:10px;font-size:1rem;transition:all 0.3s}
.form-group input:focus{outline:none;border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,0.08)}
.btn{width:100%;padding:12px;border:none;border-radius:10px;font-size:1rem;font-weight:600;cursor:pointer;transition:all 0.2s;margin-top:6px}
.btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff}
.btn-oauth{width:100%;padding:12px;border:2px solid #e5e7eb;border-radius:10px;font-size:0.95rem;font-weight:600;cursor:pointer;transition:all 0.2s;margin-top:8px;background:#fff;display:flex;align-items:center;justify-content:center;gap:10px;text-decoration:none;color:#374151}
.btn-oauth:hover{border-color:#667eea;background:#f9fafb;transform:translateY(-2px)}
.btn-oauth.google:hover{background:#4285f4;color:#fff;border-color:#4285f4}
.btn-oauth.facebook:hover{background:#1877f2;color:#fff;border-color:#1877f2}
.divider{display:flex;align-items:center;margin:18px 0;color:#9ca3af;font-size:0.9rem}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:#e5e7eb}
.divider span{padding:0 12px}
.alert{padding:10px 12px;border-radius:10px;margin-bottom:14px;font-size:0.9rem}
.alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
.alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
.footer{text-align:center;padding:16px;background:#f9fafb;color:#6b7280;font-size:0.9rem}
.footer a{color:#667eea;text-decoration:none;font-weight:600}
.small-link{display:block;margin-top:8px;text-align:right;font-size:0.9rem}
.small-link a{color:#667eea;text-decoration:none}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>🛍️ <?= htmlspecialchars($APP_NAME) ?></h1>
    <p>Inicia sesión o crea tu cuenta</p>
  </div>

  <?php if ($error): ?>
    <div class="content"><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div></div>
  <?php endif; ?>

  <div class="tabs">
    <button class="tab active" id="tab-login" onclick="switchTab('login')">Iniciar Sesión</button>
    <button class="tab" id="tab-register" onclick="switchTab('register')">Crear Cuenta</button>
  </div>

  <div class="content">
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
          <input type="email" name="email" required>
        </div>
        <div class="form-group">
          <label>Contraseña</label>
          <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
      </form>
    </div>

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
          <label>Nombre *</label>
          <input type="text" name="name" required>
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input type="email" name="email" required>
        </div>
        <div class="form-group">
          <label>Teléfono</label>
          <input type="tel" name="phone">
        </div>
        <div class="form-group">
          <label>Contraseña *</label>
          <input type="password" name="password" required>
        </div>
        <div class="form-group">
          <label>Confirmar *</label>
          <input type="password" name="password2" required>
        </div>
        <button type="submit" class="btn btn-primary">Crear Cuenta</button>
      </form>
    </div>
  </div>
  <div class="footer">
    <a href="index">← Volver al inicio</a>
  </div>
</div>

<script>
function switchTab(tab) {
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
  if (tab === 'login') {
    document.getElementById('tab-login').classList.add('active');
    document.getElementById('login-tab').classList.add('active');
  } else if (tab === 'register') {
    document.getElementById('tab-register').classList.add('active');
    document.getElementById('register-tab').classList.add('active');
  }
}
</script>
</body>
</html>