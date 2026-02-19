<?php
// affiliate/register.php – UTF-8 (sin BOM)
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
if (file_exists(__DIR__ . '/../includes/mailer.php')) {
  require_once __DIR__ . '/../includes/mailer.php';
}

if (session_status() === PHP_SESSION_NONE) session_start();
$pdo = db();

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
                 . '/affiliate/register.php';

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

    // Check if affiliate already exists
    $email = $userInfo['email'];
    $name = $userInfo['name'] ?? $userInfo['email'];

    $stmt = $pdo->prepare("SELECT id FROM affiliates WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $existingAff = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingAff) {
      // Ya existe - iniciar sesión directamente
      // Log existing user OAuth login
      $logFile = __DIR__ . '/../logs/affiliate_oauth.log';
      $timestamp = date('Y-m-d H:i:s');
      $logMsg = "[{$timestamp}] OAuth login: Existing affiliate | ID: {$existingAff['id']} | Email: {$email}";
      @file_put_contents($logFile, $logMsg . PHP_EOL, FILE_APPEND);

      $_SESSION['aff_id'] = (int)$existingAff['id'];
      $_SESSION['aff_name'] = $name;
      header('Location: dashboard.php');
      exit;
    }

    // Create new affiliate
    $phone = ''; // Google no proporciona teléfono, lo dejaremos vacío
    $password_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT); // Password aleatorio

    $ins = $pdo->prepare("
      INSERT INTO affiliates (name, email, phone, password_hash, is_active, created_at)
      VALUES (?, ?, ?, ?, 1, datetime('now'))
    ");
    $ins->execute([$name, $email, $phone, $password_hash]);

    $aff_id = (int)$pdo->lastInsertId();

    // Iniciar sesión automáticamente
    $_SESSION['aff_id'] = $aff_id;
    $_SESSION['aff_name'] = $name;

    // Log successful OAuth registration
    $logFile = __DIR__ . '/../logs/affiliate_oauth.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMsg = "[{$timestamp}] OAuth success: New affiliate registered | ID: {$aff_id} | Email: {$email} | Name: {$name}";
    @file_put_contents($logFile, $logMsg . PHP_EOL, FILE_APPEND);

    // Enviar email de bienvenida
    try {
      $loginUrl = (defined('APP_URL') ? APP_URL : (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http').'://'.($_SERVER['HTTP_HOST'] ?? 'localhost'))) . "/affiliate/dashboard.php";
      $subject = "✅ Bienvenido a " . APP_NAME;
      $body = "
        <p>Hola <strong>".htmlspecialchars($name)."</strong>,</p>
        <p>¡Bienvenido a <strong>".APP_NAME."</strong>! Tu cuenta de afiliado ha sido creada exitosamente usando Google.</p>
        <p>Ya podés crear tus espacios y publicar productos:</p>
        <p><a href='".htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8')."' style='background:#3498db;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block'>Ir al panel</a></p>
        <p>Si necesitás ayuda, escribinos a <a href='mailto:".htmlspecialchars(ADMIN_EMAIL)."'>".htmlspecialchars(ADMIN_EMAIL)."</a>.</p>
        <br><p>— El equipo de ".APP_NAME."</p>";
      safe_send($email, $subject, $body);
    } catch (Throwable $e) {
      error_log("[register.php] Email bienvenida OAuth falló: ".$e->getMessage());
    }

    // Notificar admin
    try {
      $subject = "[Afiliados] Nuevo registro con Google";
      $body = "Nuevo afiliado registrado con Google:<br><br>"
            . "Nombre: <strong>".htmlspecialchars($name)."</strong><br>"
            . "Email: <strong>".htmlspecialchars($email)."</strong><br>"
            . "ID: <strong>".$aff_id."</strong>";
      if (defined('ADMIN_EMAIL') && ADMIN_EMAIL !== '') {
        safe_send(ADMIN_EMAIL, $subject, $body);
      }
    } catch (Throwable $e) {
      error_log("[register.php] Email admin OAuth falló: ".$e->getMessage());
    }

    header('Location: dashboard.php');
    exit;

  } catch (Throwable $e) {
    // Log OAuth error to application log
    $logFile = __DIR__ . '/../logs/affiliate_oauth.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMsg = "[{$timestamp}] OAuth error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString();
    @file_put_contents($logFile, $logMsg . PHP_EOL, FILE_APPEND);

    error_log("[register.php] OAuth error: " . $e->getMessage());
    $msg = "Error al registrarse con Google: " . $e->getMessage();
  }
}

$msg = '';
$ok  = false;

/** ---- helpers ---- */
function valid_email($e){
  return filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
}

function clean_phone($p){
  $p = trim((string)$p);
  if (!preg_match('/^[0-9 \-\+\(\)]{7,20}$/', $p)) return false;
  return $p;
}

function safe_send(string $to, string $subject, string $html): bool {
  $fromEmail = defined('FROM_EMAIL') ? FROM_EMAIL : ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
  $fromName  = defined('FROM_NAME')  ? FROM_NAME  : (defined('APP_NAME') ? APP_NAME : 'Marketplace');

  if (function_exists('send_email')) {
    try {
      $ok = send_email($to, $subject, $html);
      error_log("[register.php] send_email to={$to} result=" . ($ok?'OK':'FAIL'));
      return (bool)$ok;
    } catch (Throwable $e) {
      error_log("[register.php] send_email exception: ".$e->getMessage());
    }
  }

  $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
  $fromHeader = sprintf('%s <%s>', $fromName, $fromEmail);
  $headers  = "MIME-Version: 1.0\r\n";
  $headers .= "Content-type: text/html; charset=utf-8\r\n";
  $headers .= "From: {$fromHeader}\r\n";
  $headers .= "Reply-To: {$fromHeader}\r\n";
  $headers .= "X-Mailer: PHP/" . phpversion();

  $ok = @mail($to, $encodedSubject, $html, $headers, "-f{$fromEmail}");
  error_log("[register.php] mail() to={$to} result=" . ($ok?'OK':'FAIL') . " from={$fromEmail}");
  return (bool)$ok;
}

function ensure_affiliates_phone(PDO $pdo): void {
  try {
    $cols = $pdo->query("PRAGMA table_info(affiliates)")->fetchAll(PDO::FETCH_ASSOC);
    $hasPhone = false;
    foreach ($cols as $c) {
      if (isset($c['name']) && strtolower($c['name']) === 'phone') { $hasPhone = true; break; }
    }
    if (!$hasPhone) {
      $pdo->exec("ALTER TABLE affiliates ADD COLUMN phone TEXT");
      error_log("[register.php] ALTER TABLE affiliates ADD COLUMN phone TEXT ejecutado");
    }
  } catch (Throwable $e) {
    error_log("[register.php] ensure_affiliates_phone error: ".$e->getMessage());
  }
}
ensure_affiliates_phone($pdo);

/* ---------- CAPTCHA SENCILLO (suma) + honeypot ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $a = random_int(1, 9);
  $b = random_int(1, 9);
  $_SESSION['reg_captcha_ans'] = $a + $b;
  $_SESSION['reg_captcha_a']   = $a;
  $_SESSION['reg_captcha_b']   = $b;
}

/** ---- POST ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $name   = trim($_POST['name']   ?? '');
    $email  = trim($_POST['email']  ?? '');
    $phone  = trim($_POST['phone']  ?? '');
    $pass   = (string)($_POST['password']  ?? '');
    $pass2  = (string)($_POST['password2'] ?? '');

    $hp = (string)($_POST['company'] ?? '');
    if ($hp !== '') {
      throw new RuntimeException('Detección anti-bot activada.');
    }
    $captcha = trim((string)($_POST['captcha'] ?? ''));
    $expect  = (string)($_SESSION['reg_captcha_ans'] ?? '');
    if ($expect === '' || $captcha === '' || (string)$captcha !== (string)$expect) {
      throw new RuntimeException('Verificación humana incorrecta. Intentá de nuevo.');
    }

    if ($name === '' || $email === '' || $phone === '' || $pass === '' || $pass2 === '') {
      throw new RuntimeException('Todos los campos son requeridos.');
    }
    if (!valid_email($email)) {
      throw new RuntimeException('El correo no es válido.');
    }
    $phone_ok = clean_phone($phone);
    if ($phone_ok === false) {
      throw new RuntimeException('El teléfono no es válido. Usa solo dígitos, espacios, +, -, ().');
    }
    if ($pass !== $pass2) {
      throw new RuntimeException('Las contraseñas no coinciden.');
    }
    if (strlen($pass) < 6) {
      throw new RuntimeException('La contraseña debe tener al menos 6 caracteres.');
    }

    $st = $pdo->prepare("SELECT id FROM affiliates WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    if ($st->fetchColumn()) {
      throw new RuntimeException('Ya existe una cuenta con este correo.');
    }

    $hash = password_hash($pass, PASSWORD_BCRYPT);

    $ins = $pdo->prepare("
      INSERT INTO affiliates (name, email, phone, password_hash, is_active, created_at)
      VALUES (?, ?, ?, ?, 1, datetime('now'))
    ");
    $ins->execute([$name, $email, $phone_ok, $hash]);

    $aff_id = (int)$pdo->lastInsertId();
    $ok = true;
    $msg = "¡Tu cuenta fue creada y activada! Ya podés iniciar sesión.";

    try {
      $loginUrl = (defined('APP_URL') ? APP_URL : (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http').'://'.($_SERVER['HTTP_HOST'] ?? 'localhost'))) . "/affiliate/login.php";
      $subject = "✅ Tu cuenta de afiliado está activa";
      $body = "
        <p>Hola <strong>".htmlspecialchars($name)."</strong>,</p>
        <p>¡Bienvenido a <strong>".APP_NAME."</strong>! Tu cuenta de afiliado ha sido <strong>activada</strong>.</p>
        <p>Ya podés iniciar sesión para crear tus espacios y publicar productos:</p>
        <p><a href='".htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8')."' style='background:#3498db;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block'>Iniciar sesión</a></p>
        <p>Si necesitás ayuda, escribinos a <a href='mailto:".htmlspecialchars(ADMIN_EMAIL)."'>".htmlspecialchars(ADMIN_EMAIL)."</a>.</p>
        <br><p>— El equipo de ".APP_NAME."</p>";
      safe_send($email, $subject, $body);
    } catch (Throwable $e) {
      error_log("[register.php] Notificación al afiliado falló: ".$e->getMessage());
    }

    try {
      $subject = "[Afiliados] Nuevo afiliado activado";
      $body = "Se registró un nuevo afiliado (activado automáticamente):<br><br>"
            . "Nombre: <strong>".htmlspecialchars($name)."</strong><br>"
            . "Email: <strong>".htmlspecialchars($email)."</strong><br>"
            . "Teléfono: <strong>".htmlspecialchars($phone_ok)."</strong><br>"
            . "ID: <strong>".$aff_id."</strong>";
      $admin = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : '';
      if ($admin !== '') safe_send($admin, $subject, $body);
    } catch (Throwable $e) {
      error_log("[register.php] Notificación admin falló: ".$e->getMessage());
    }

    $_SESSION['reg_captcha_ans'] = null;

  } catch (Throwable $e) {
    $msg = "Error: " . $e->getMessage();

    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $_SESSION['reg_captcha_ans'] = $a + $b;
    $_SESSION['reg_captcha_a']   = $a;
    $_SESSION['reg_captcha_b']   = $b;
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registro de Afiliados — <?php echo APP_NAME; ?></title>
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

    /* ===================================== */
    /* HEADER */
    /* ===================================== */
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
    }

    .btn-primary:hover {
      background: #2980b9;
      border-color: #2980b9;
    }

    /* ===================================== */
    /* CONTAINER */
    /* ===================================== */
    .container {
      max-width: 520px;
      margin: 3rem auto;
      padding: 0 1.5rem;
      flex: 1;
    }

    /* ===================================== */
    /* CARD */
    /* ===================================== */
    .card {
      background: var(--white);
      border-radius: var(--radius);
      padding: 2.5rem;
      box-shadow: var(--shadow-md);
      border: 1px solid var(--gray-300);
    }

    .card h2 {
      font-size: 1.75rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 0.5rem;
      letter-spacing: -0.02em;
    }

    .card h3 {
      font-size: 1.5rem;
      font-weight: 600;
      color: var(--primary);
      margin-bottom: 1rem;
    }

    .card p {
      color: var(--gray-700);
      margin-bottom: 1.5rem;
    }

    /* ===================================== */
    /* MENSAJES */
    /* ===================================== */
    .alert, .success {
      padding: 1rem 1.25rem;
      border-radius: var(--radius);
      margin-bottom: 1.5rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .alert {
      background: #fee;
      color: var(--danger);
      border: 1px solid #fcc;
    }

    .success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .alert::before {
      content: "⚠️";
      font-size: 1.25rem;
    }

    .success::before {
      content: "✅";
      font-size: 1.25rem;
    }

    /* ===================================== */
    /* FORM */
    /* ===================================== */
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
      margin-top: -0.5rem;
    }

    .actions {
      display: flex;
      gap: 0.75rem;
      margin-top: 1rem;
    }

    .actions .btn {
      flex: 1;
      justify-content: center;
    }

    /* ===================================== */
    /* OAUTH BUTTONS */
    /* ===================================== */
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

    /* ===================================== */
    /* HONEYPOT */
    /* ===================================== */
    .hpwrap {
      position: absolute;
      left: -5000px;
      top: -5000px;
      height: 0;
      overflow: hidden;
    }

    /* ===================================== */
    /* FOOTER */
    /* ===================================== */
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

    /* ===================================== */
    /* RESPONSIVE */
    /* ===================================== */
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

      .card h2 {
        font-size: 1.5rem;
      }

      .actions {
        flex-direction: column;
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
    <a class="btn" href="login.php">
      <i class="fas fa-sign-in-alt"></i> Ingresar
    </a>
  </nav>
</header>

<div class="container">
  <?php if ($msg): ?>
    <div class="<?php echo $ok ? 'success' : 'alert'; ?>">
      <?php echo htmlspecialchars($msg); ?>
    </div>
  <?php endif; ?>

  <?php if (!$ok): ?>
  <div class="card">
    <h2>Crear cuenta de afiliado</h2>
    <p>Registrate para crear tus espacios de venta y publicar productos.</p>

    <?php
    // OAuth Google - Generar URL
    $googleAuthUrl = '';
    if (!empty(GOOGLE_CLIENT_ID)) {
      $redirectUri = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
                   . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                   . '/affiliate/register.php';

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
    <div style="margin-bottom: 1.5rem;">
      <a href="<?= htmlspecialchars($googleAuthUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn-google">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 12px;">
          <path d="M17.64 9.20443C17.64 8.56625 17.5827 7.95262 17.4764 7.36353H9V10.8449H13.8436C13.635 11.9699 13.0009 12.9231 12.0477 13.5613V15.8194H14.9564C16.6582 14.2526 17.64 11.9453 17.64 9.20443Z" fill="#4285F4"/>
          <path d="M8.99976 18C11.4298 18 13.467 17.1941 14.9561 15.8195L12.0475 13.5613C11.2416 14.1013 10.2107 14.4204 8.99976 14.4204C6.65567 14.4204 4.67158 12.8372 3.96385 10.71H0.957031V13.0418C2.43794 15.9831 5.48158 18 8.99976 18Z" fill="#34A853"/>
          <path d="M3.96409 10.71C3.78409 10.17 3.68182 9.59318 3.68182 9C3.68182 8.40682 3.78409 7.82999 3.96409 7.28999V4.95819H0.957273C0.347727 6.17318 0 7.54772 0 9C0 10.4523 0.347727 11.8268 0.957273 13.0418L3.96409 10.71Z" fill="#FBBC05"/>
          <path d="M8.99976 3.57955C10.3211 3.57955 11.5075 4.03364 12.4402 4.92545L15.0216 2.34409C13.4629 0.891818 11.4257 0 8.99976 0C5.48158 0 2.43794 2.01682 0.957031 4.95818L3.96385 7.29C4.67158 5.16273 6.65567 3.57955 8.99976 3.57955Z" fill="#EA4335"/>
        </svg>
        Registrarse con Google
      </a>
    </div>

    <div class="divider">
      <span>o registrate con email</span>
    </div>
    <?php endif; ?>

    <form class="form" method="post" autocomplete="on" novalidate>
      <div class="hpwrap" aria-hidden="true">
        <label>Empresa
          <input type="text" name="company" value="">
        </label>
      </div>

      <div>
        <label>Nombre completo</label>
        <input class="input" type="text" name="name" required>
      </div>

      <div>
        <label>Correo electrónico</label>
        <input class="input" type="email" name="email" required>
      </div>

      <div>
        <label>Teléfono</label>
        <input class="input" type="tel" name="phone" placeholder="+506 8888-8888" required>
      </div>

      <div>
        <label>Contraseña</label>
        <input class="input" type="password" name="password" minlength="6" required>
        <div class="small">Mínimo 6 caracteres</div>
      </div>

      <div>
        <label>Confirmar contraseña</label>
        <input class="input" type="password" name="password2" minlength="6" required>
      </div>

      <div>
        <label>
          Verificación humana: ¿Cuánto es 
          <strong><?php echo (int)($_SESSION['reg_captcha_a'] ?? 0); ?></strong> + 
          <strong><?php echo (int)($_SESSION['reg_captcha_b'] ?? 0); ?></strong>?
        </label>
        <input class="input" type="number" name="captcha" inputmode="numeric" required>
      </div>

      <div class="small">
        <i class="fas fa-check-circle"></i>
        Tu cuenta se activará <strong>automáticamente</strong> al registrarte.
      </div>

      <div class="actions">
        <button class="btn btn-primary" type="submit">
          <i class="fas fa-user-plus"></i> Crear cuenta
        </button>
        <a class="btn" href="login.php">
          <i class="fas fa-times"></i> Cancelar
        </a>
      </div>
    </form>
  </div>
  <?php else: ?>
  <div class="card">
    <h3>¡Cuenta creada exitosamente!</h3>
    <p>Tu cuenta ha sido activada. Ya podés iniciar sesión para crear tu espacio y publicar productos.</p>
    <div class="actions">
      <a class="btn btn-primary" href="login.php">
        <i class="fas fa-sign-in-alt"></i> Iniciar sesión
      </a>
      <a class="btn" href="../index">
        <i class="fas fa-home"></i> Volver al inicio
      </a>
    </div>
  </div>
  <?php endif; ?>
</div>

<footer class="site-footer">
  <div class="inner">
    <div>© <?php echo date('Y'); ?> <?php echo APP_NAME; ?> — Todos los derechos reservados.</div>
    <div class="footer-links">
      <a href="mailto:<?php echo ADMIN_EMAIL; ?>">Contacto</a>
      <a href="login.php">Afiliados</a>
      <a href="../admin/login.php">Administrador</a>
    </div>
  </div>
</footer>

</body>
</html>
