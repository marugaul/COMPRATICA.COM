<?php
// affiliate/register.php — UTF-8 (sin BOM)
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
// mailer.php puede no tener send_mail() según tu instalación; lo incluimos por si define send_email()
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

// Teléfono: dígitos, espacios, guiones, paréntesis, +, longitud 7-20
function clean_phone($p){
  $p = trim((string)$p);
  if (!preg_match('/^[0-9 \-\+\(\)]{7,20}$/', $p)) return false;
  return $p;
}

/** Envío seguro de email con logs */
function safe_send(string $to, string $subject, string $html): bool {
  $fromEmail = defined('FROM_EMAIL') ? FROM_EMAIL : ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
  $fromName  = defined('FROM_NAME')  ? FROM_NAME  : (defined('APP_NAME') ? APP_NAME : 'Marketplace');

  // Si existe send_email() de tu proyecto, úsalo
  if (function_exists('send_email')) {
    try {
      $ok = send_email($to, $subject, $html);
      error_log("[register.php] send_email to={$to} result=" . ($ok?'OK':'FAIL'));
      return (bool)$ok;
    } catch (Throwable $e) {
      error_log("[register.php] send_email exception: ".$e->getMessage());
      // seguimos con fallback
    }
  }

  // Fallback a mail()
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

/** Verifica que affiliates tenga columna phone (evita errores si falta) */
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
// En GET generamos un captcha simple y lo guardamos en sesión
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

    // Verificación humana:
    // 1) Honeypot (campo oculto debe venir vacío)
    $hp = (string)($_POST['company'] ?? '');
    if ($hp !== '') {
      throw new RuntimeException('Detección anti-bot activada.');
    }
    // 2) Captcha aritmético sencillo
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

    // ¿Correo ya existe?
    $st = $pdo->prepare("SELECT id FROM affiliates WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    if ($st->fetchColumn()) {
      throw new RuntimeException('Ya existe una cuenta con este correo.');
    }

    $hash = password_hash($pass, PASSWORD_BCRYPT);

    // Guardar afiliado — AHORA activo automáticamente (is_active=1)
    $ins = $pdo->prepare("
      INSERT INTO affiliates (name, email, phone, password_hash, is_active, created_at)
      VALUES (?, ?, ?, ?, 1, datetime('now'))
    ");
    $ins->execute([$name, $email, $phone_ok, $hash]);

    $aff_id = (int)$pdo->lastInsertId();
    $ok = true;
    $msg = "¡Tu cuenta fue creada y activada! Ya podés iniciar sesión.";

    // Enviar correo al afiliado (bienvenida/activación)
    try {
      $loginUrl = (defined('APP_URL') ? APP_URL : (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http').'://'.($_SERVER['HTTP_HOST'] ?? 'localhost'))) . "/affiliate/login.php";
      $subject = "✅ Tu cuenta de afiliado está activa";
      $body = "
        <p>Hola <strong>".htmlspecialchars($name)."</strong>,</p>
        <p>¡Bienvenido a <strong>".APP_NAME."</strong>! Tu cuenta de afiliado ha sido <strong>activada</strong>.</p>
        <p>Ya podés iniciar sesión para crear tus espacios y publicar productos:</p>
        <p><a href='".htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8')."' style='background:#2563eb;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;display:inline-block'>Iniciar sesión</a></p>
        <p>Si necesitás ayuda, escribinos a <a href='mailto:".htmlspecialchars(ADMIN_EMAIL)."'>".htmlspecialchars(ADMIN_EMAIL)."</a>.</p>
        <br><p>— El equipo de ".APP_NAME."</p>";
      safe_send($email, $subject, $body);
    } catch (Throwable $e) {
      error_log("[register.php] Notificación al afiliado falló: ".$e->getMessage());
    }

    // (Opcional) Notificar al admin del nuevo registro
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

    // Regeneramos captcha para evitar reenvíos con la misma respuesta
    $_SESSION['reg_captcha_ans'] = null;

  } catch (Throwable $e) {
    $msg = "Error: " . $e->getMessage();

    // Si falla, regeneramos un nuevo captcha para la UI
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
  <title>Afiliados — Registro</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/style.css?v=20251012b">
  <style>.auth-wrap{max-width:520px;margin:auto}
  .hpwrap{position:absolute;left:-5000px;top:-5000px;height:0;overflow:hidden}
  </style>
</head>
<body>
<header class="header">
  <div class="logo">🛒 Afiliados — Registrar cuenta</div>
  <nav>
    <a class="btn" href="../index.php">Inicio</a>
    <a class="btn" href="login.php">Ya tengo cuenta</a>
  </nav>
</header>

<div class="container">
  <?php if ($msg): ?>
    <div class="<?php echo $ok ? 'success' : 'alert'; ?>"><?php echo htmlspecialchars($msg); ?></div>
  <?php endif; ?>

  <?php if (!$ok): ?>
  <div class="card auth-wrap">
    <h2>Crear mi cuenta de afiliado</h2>
    <form class="form" method="post" autocomplete="on" novalidate>
      <!-- Honeypot (debe permanecer vacío) -->
      <div class="hpwrap" aria-hidden="true">
        <label>Empresa
          <input type="text" name="company" value="">
        </label>
      </div>

      <label>Nombre completo
        <input class="input" type="text" name="name" required>
      </label>
      <label>Correo
        <input class="input" type="email" name="email" required>
      </label>
      <label>Teléfono
        <input class="input" type="tel" name="phone" placeholder="+506 8888-8888" required>
      </label>
      <label>Contraseña
        <input class="input" type="password" name="password" minlength="6" required>
      </label>
      <label>Confirmar contraseña
        <input class="input" type="password" name="password2" minlength="6" required>
      </label>

      <!-- Captcha aritmético simple -->
      <label>Verificación humana: ¿Cuánto es
        <strong><?php echo (int)($_SESSION['reg_captcha_a'] ?? 0); ?></strong> +
        <strong><?php echo (int)($_SESSION['reg_captcha_b'] ?? 0); ?></strong>?
        <input class="input" type="number" name="captcha" inputmode="numeric" required>
      </label>

      <div class="small">Tu cuenta se activará <strong>automáticamente</strong> al registrarte.</div>
      <div class="actions">
        <button class="btn primary" type="submit">Crear cuenta</button>
        <a class="btn" href="login.php">Cancelar</a>
      </div>
    </form>
  </div>
  <?php else: ?>
  <div class="card auth-wrap">
    <h3>¡Cuenta creada y activa!</h3>
    <p class="small">Ya podés iniciar sesión para crear tu espacio y publicar productos.</p>
    <div class="actions">
      <a class="btn" href="../index.php">Volver al inicio</a>
      <a class="btn" href="login.php">Ir al login</a>
    </div>
  </div>
  <?php endif; ?>
</div>

<footer class="site-footer">
  <div class="inner">
    <div>© <?php echo date('Y'); ?> <?php echo APP_NAME; ?></div>
    <div class="footer-links">
      <a href="mailto:<?php echo ADMIN_EMAIL; ?>">Contacto</a>
      <a href="login.php">Afiliados</a>
      <a href="../admin/login.php">Administrador</a>
    </div>
  </div>
</footer>
</body>
</html>
