<?php
// affiliate/login.php – UTF-8 (sin BOM)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// IMPORTANTE: Configurar sesiones ANTES de cargar affiliate_auth.php (que hace session_start)
$__sessPath = __DIR__ . '/../sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
} else {
    // Fallback a /tmp si no se puede escribir en sessions
    ini_set('session.save_path', '/tmp');
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';
require_once __DIR__ . '/../includes/user_auth.php';

$pdo = db();
$msg = '';

// Si ya tiene sesión, redirigir al dashboard
if (is_user_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

// ============================================
// OAUTH GOOGLE - Callback Handler
// ============================================
if (isset($_GET['code']) && !empty($_GET['code'])) {
  try {
    $code = $_GET['code'];

    // Verificar que tenemos las credenciales
    if (empty(GOOGLE_CLIENT_ID) || empty(GOOGLE_CLIENT_SECRET)) {
      throw new RuntimeException('Configuración OAuth incompleta');
    }

    // Exchange code for access token
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $redirectUri = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
                 . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                 . '/affiliate/login.php';

    $postData = [
      'code' => $code,
      'client_id' => GOOGLE_CLIENT_ID,
      'client_secret' => GOOGLE_CLIENT_SECRET,
      'redirect_uri' => $redirectUri,
      'grant_type' => 'authorization_code'
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
      throw new RuntimeException('Error al obtener token de Google');
    }

    $tokenData = json_decode($response, true);
    if (!isset($tokenData['access_token'])) {
      throw new RuntimeException('Token no recibido');
    }

    // Get user info from Google
    $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
    $ch = curl_init($userInfoUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Authorization: Bearer ' . $tokenData['access_token']
    ]);
    $userInfoResponse = curl_exec($ch);
    curl_close($ch);

    $userInfo = json_decode($userInfoResponse, true);

    if (!isset($userInfo['email'])) {
      throw new RuntimeException('No se pudo obtener información del usuario');
    }

    // Obtener información del usuario
    $email = $userInfo['email'];
    $name = $userInfo['name'] ?? $userInfo['email'];
    $oauthId = $userInfo['id'] ?? '';

    // Usar la función unificada para obtener o crear usuario
    $user = get_or_create_oauth_user($email, $name, 'google', $oauthId);

    // Iniciar sesión usando la función unificada
    login_user($user);

    header('Location: dashboard.php');
    exit;

  } catch (Throwable $e) {
    error_log("[affiliate/login.php] OAuth error: " . $e->getMessage());
    $msg = "Error al iniciar sesión con Google: " . $e->getMessage();
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');

    if ($email === '' || $pass === '') {
      throw new RuntimeException('Por favor ingresa tu correo y contraseña.');
    }

    $user = authenticate_user($email, $pass);

    if (!$user) {
      throw new RuntimeException('Credenciales inválidas.');
    }

    login_user($user);

    header('Location: dashboard.php');
    exit;

  } catch (Throwable $e) {
    $msg = $e->getMessage();
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Iniciar Sesión — <?php echo APP_NAME; ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    :root {
      --primary: #2c3e50;
      --primary-light: #34495e;
      --accent: #3498db;
      --success: #27ae60;
      --danger: #c0392b;
      --dark: #1a1a1a;
      --gray-900: #2d3748;
      --gray-700: #4a5568;
      --gray-500: #718096;
      --gray-300: #cbd5e0;
      --gray-100: #f7fafc;
      --white: #ffffff;
      --bg-primary: #f8f9fa;
      --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
      --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
      --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
      --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      --radius: 8px;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: var(--bg-primary);
      color: var(--dark);
      line-height: 1.6;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* HEADER */
    .header {
      background: var(--white);
      border-bottom: 1px solid var(--gray-300);
      padding: 1rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: var(--shadow-sm);
    }

    .logo {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--primary);
      display: flex;
      align-items: center;
      gap: 0.75rem;
      letter-spacing: -0.02em;
      text-decoration: none;
    }

    .header-nav {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.625rem 1.25rem;
      border-radius: var(--radius);
      font-weight: 500;
      font-size: 0.9375rem;
      cursor: pointer;
      transition: var(--transition);
      text-decoration: none;
      border: 1.5px solid var(--gray-300);
      background: var(--white);
      color: var(--gray-700);
    }

    .btn:hover {
      background: var(--gray-100);
      border-color: var(--gray-500);
    }

    .btn-primary {
      background: var(--accent);
      color: var(--white);
      border-color: var(--accent);
      width: 100%;
      justify-content: center;
    }

    .btn-primary:hover {
      background: #2980b9;
      border-color: #2980b9;
    }

    /* CONTAINER */
    .container {
      max-width: 460px;
      margin: 3rem auto;
      padding: 0 1.5rem;
      flex: 1;
    }

    /* CARD */
    .card {
      background: var(--white);
      border-radius: var(--radius);
      padding: 2.5rem;
      box-shadow: var(--shadow-md);
      border: 1px solid var(--gray-300);
    }

    .card h3 {
      font-size: 1.75rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 0.5rem;
      letter-spacing: -0.02em;
    }

    .card p {
      color: var(--gray-700);
      margin-bottom: 1.5rem;
    }

    /* ALERT */
    .alert {
      padding: 1rem 1.25rem;
      border-radius: var(--radius);
      margin-bottom: 1.5rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      background: #fee;
      color: var(--danger);
      border: 1px solid #fcc;
    }

    .alert::before {
      content: "⚠️";
      font-size: 1.25rem;
    }

    /* FORM */
    .form {
      display: flex;
      flex-direction: column;
      gap: 1.25rem;
    }

    .form label {
      display: block;
      font-weight: 500;
      color: var(--gray-900);
      margin-bottom: 0.5rem;
      font-size: 0.9375rem;
    }

    .input {
      width: 100%;
      padding: 0.75rem 1rem;
      border: 1.5px solid var(--gray-300);
      border-radius: var(--radius);
      font-size: 1rem;
      font-family: inherit;
      transition: var(--transition);
      background: var(--white);
    }

    .input:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    }

    .small {
      font-size: 0.875rem;
      color: var(--gray-500);
      text-align: center;
      margin-top: 1rem;
    }

    .link {
      color: var(--accent);
      text-decoration: none;
      font-weight: 500;
      transition: var(--transition);
    }

    .link:hover {
      color: #2980b9;
      text-decoration: underline;
    }

    /* OAUTH BUTTONS */
    .btn-google {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      padding: 0.875rem 1.5rem;
      background: white;
      border: 2px solid #dadce0;
      border-radius: var(--radius);
      font-size: 1rem;
      font-weight: 500;
      color: #3c4043;
      text-decoration: none;
      transition: var(--transition);
      cursor: pointer;
      margin-bottom: 1rem;
    }

    .btn-google:hover {
      background: #f8f9fa;
      border-color: #d2d3d4;
      box-shadow: 0 1px 2px rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
    }

    .divider {
      display: flex;
      align-items: center;
      text-align: center;
      margin: 1.5rem 0;
      color: var(--gray-500);
      font-size: 0.875rem;
    }

    .divider::before,
    .divider::after {
      content: '';
      flex: 1;
      border-bottom: 1px solid var(--gray-300);
    }

    .divider span {
      padding: 0 1rem;
    }

    /* FOOTER */
    .site-footer {
      background: var(--white);
      border-top: 1px solid var(--gray-300);
      padding: 1.5rem 2rem;
      margin-top: 3rem;
    }

    .site-footer .inner {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
      font-size: 0.875rem;
      color: var(--gray-500);
    }

    .footer-links {
      display: flex;
      gap: 1.5rem;
    }

    .footer-links a {
      color: var(--gray-500);
      text-decoration: none;
      transition: var(--transition);
    }

    .footer-links a:hover {
      color: var(--accent);
    }

    /* RESPONSIVE */
    @media (max-width: 640px) {
      .header {
        padding: 1rem;
      }

      .logo {
        font-size: 1.25rem;
      }

      .container {
        margin: 2rem auto;
        padding: 0 1rem;
      }

      .card {
        padding: 1.5rem;
      }

      .card h3 {
        font-size: 1.5rem;
      }

      .site-footer .inner {
        flex-direction: column;
        text-align: center;
      }

      .footer-links {
        flex-direction: column;
        gap: 0.75rem;
      }
    }
  </style>
</head>
<body>

<header class="header">
  <a href="../index" class="logo">
    <i class="fas fa-store"></i>
    <?php echo APP_NAME; ?>
  </a>
  <nav class="header-nav">
    <a class="btn" href="../index">
      <i class="fas fa-home"></i> Inicio
    </a>
  </nav>
</header>

<div class="container">
  <?php if ($msg): ?>
    <div class="alert"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="card">
    <h3>Iniciar Sesión</h3>
    <p>Accede a tu cuenta de afiliado</p>

    <?php
    // OAuth Google - Generar URL
    $googleAuthUrl = '';
    if (!empty(GOOGLE_CLIENT_ID)) {
      $redirectUri = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
                   . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                   . '/affiliate/login.php';

      $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'email profile',
        'access_type' => 'online'
      ];

      $googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    ?>

    <?php if ($googleAuthUrl): ?>
    <!-- Botón de Google -->
    <a href="<?= htmlspecialchars($googleAuthUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn-google">
      <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 12px;">
        <path d="M17.64 9.20443C17.64 8.56625 17.5827 7.95262 17.4764 7.36353H9V10.8449H13.8436C13.635 11.9699 13.0009 12.9231 12.0477 13.5613V15.8194H14.9564C16.6582 14.2526 17.64 11.9453 17.64 9.20443Z" fill="#4285F4"/>
        <path d="M8.99976 18C11.4298 18 13.467 17.1941 14.9561 15.8195L12.0475 13.5613C11.2416 14.1013 10.2107 14.4204 8.99976 14.4204C6.65567 14.4204 4.67158 12.8372 3.96385 10.71H0.957031V13.0418C2.43794 15.9831 5.48158 18 8.99976 18Z" fill="#34A853"/>
        <path d="M3.96409 10.71C3.78409 10.17 3.68182 9.59318 3.68182 9C3.68182 8.40682 3.78409 7.82999 3.96409 7.28999V4.95819H0.957273C0.347727 6.17318 0 7.54772 0 9C0 10.4523 0.347727 11.8268 0.957273 13.0418L3.96409 10.71Z" fill="#FBBC05"/>
        <path d="M8.99976 3.57955C10.3211 3.57955 11.5075 4.03364 12.4402 4.92545L15.0216 2.34409C13.4629 0.891818 11.4257 0 8.99976 0C5.48158 0 2.43794 2.01682 0.957031 4.95818L3.96385 7.29C4.67158 5.16273 6.65567 3.57955 8.99976 3.57955Z" fill="#EA4335"/>
      </svg>
      Continuar con Google
    </a>

    <div class="divider">
      <span>o usa tu correo</span>
    </div>
    <?php endif; ?>

    <form class="form" method="post" autocomplete="on">
      <div>
        <label>Correo electrónico</label>
        <input class="input" type="email" name="email" required autofocus>
      </div>

      <div>
        <label>Contraseña</label>
        <input class="input" type="password" name="password" required>
      </div>

      <button class="btn btn-primary" type="submit">
        <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
      </button>
    </form>

    <p class="small">
      ¿No tienes cuenta?
      <a href="register.php" class="link">Regístrate aquí</a>
    </p>
  </div>
</div>

<footer class="site-footer">
  <div class="inner">
    <div>© <?php echo date('Y'); ?> <?php echo APP_NAME; ?> — Todos los derechos reservados.</div>
    <div class="footer-links">
      <a href="mailto:<?php echo ADMIN_EMAIL; ?>">Contacto</a>
      <a href="register.php">Registro</a>
      <a href="../admin/login.php">Administrador</a>
    </div>
  </div>
</footer>

</body>
</html>
