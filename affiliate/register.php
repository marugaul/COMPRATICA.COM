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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
  <a href="../index.php" class="logo">
    <i class="fas fa-store"></i>
    <?php echo APP_NAME; ?>
  </a>
  <nav class="header-nav">
    <a class="btn" href="../index.php">
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
      <a class="btn" href="../index.php">
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
