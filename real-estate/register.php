<?php
// real-estate/register.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$pdo = db();

$msg = '';
$ok  = false;

// Verificar si hay un error de OAuth en la URL
if (isset($_GET['error']) && !empty($_GET['error'])) {
    $msg = $_GET['error'];
    $ok = false;
}

function valid_email($e){
  return filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
}

function clean_phone($p){
  $p = trim((string)$p);
  if (!preg_match('/^[0-9 \-\+\(\)]{7,20}$/', $p)) return false;
  return $p;
}

/* ---------- CAPTCHA SENCILLO (suma) + honeypot ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $a = random_int(1, 9);
  $b = random_int(1, 9);
  $_SESSION['re_reg_captcha_ans'] = $a + $b;
  $_SESSION['re_reg_captcha_a']   = $a;
  $_SESSION['re_reg_captcha_b']   = $b;
}

/** ---- POST ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $name         = trim($_POST['name']   ?? '');
    $email        = trim($_POST['email']  ?? '');
    $phone        = trim($_POST['phone']  ?? '');
    $pass         = (string)($_POST['password']  ?? '');
    $pass2        = (string)($_POST['password2'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');

    $hp = (string)($_POST['company_field'] ?? '');
    if ($hp !== '') {
      throw new RuntimeException('Detección anti-bot activada.');
    }

    $captcha = trim((string)($_POST['captcha'] ?? ''));
    $expect  = (string)($_SESSION['re_reg_captcha_ans'] ?? '');
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

    $st = $pdo->prepare("SELECT id FROM real_estate_agents WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    if ($st->fetchColumn()) {
      throw new RuntimeException('Ya existe una cuenta con este correo.');
    }

    $hash = password_hash($pass, PASSWORD_BCRYPT);

    $ins = $pdo->prepare("
      INSERT INTO real_estate_agents (name, email, phone, company_name, password_hash, is_active, created_at)
      VALUES (?, ?, ?, ?, ?, 1, datetime('now'))
    ");
    $ins->execute([$name, $email, $phone_ok, $company_name, $hash]);

    $agent_id = (int)$pdo->lastInsertId();
    $ok = true;
    $msg = "¡Tu cuenta fue creada y activada! Ya podés iniciar sesión.";

    $_SESSION['re_reg_captcha_ans'] = null;

  } catch (Throwable $e) {
    $msg = "Error: " . $e->getMessage();

    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $_SESSION['re_reg_captcha_ans'] = $a + $b;
    $_SESSION['re_reg_captcha_a']   = $a;
    $_SESSION['re_reg_captcha_b']   = $b;
  }
}

$captcha_a = $_SESSION['re_reg_captcha_a'] ?? 0;
$captcha_b = $_SESSION['re_reg_captcha_b'] ?? 0;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registro Bienes Raíces — <?php echo APP_NAME; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root { --primary: #002b7f; --white: #fff; --dark: #1a1a1a; --gray-700: #4a5568; --gray-300: #cbd5e0; --gray-100: #f7fafc; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, var(--primary), #0041b8); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
    .container { max-width: 500px; width: 100%; background: var(--white); border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); padding: 3rem; }
    .header { text-align: center; margin-bottom: 2rem; }
    .header i { font-size: 3rem; color: var(--primary); margin-bottom: 1rem; }
    .header h1 { font-size: 2rem; font-weight: 700; color: var(--dark); margin-bottom: 0.5rem; }
    .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 500; }
    .alert.success { background: #d4edda; color: #155724; }
    .alert.error { background: #f8d7da; color: #721c24; }
    .form-group { margin-bottom: 1.5rem; }
    label { display: block; margin-bottom: 0.5rem; color: var(--gray-700); font-weight: 500; }
    input { width: 100%; padding: 0.875rem 1rem; border: 2px solid var(--gray-300); border-radius: 8px; font-size: 1rem; }
    input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,43,127,0.1); }
    input.honeypot { position: absolute; left: -9999px; }
    .btn { width: 100%; padding: 1rem; background: var(--primary); color: var(--white); border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
    .btn:hover { background: #001d5c; }
    .links { text-align: center; margin-top: 1.5rem; }
    .links a { color: var(--primary); text-decoration: none; font-weight: 500; }
    .captcha-question { background: var(--gray-100); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center; font-weight: 600; }
    .divider { display: flex; align-items: center; text-align: center; margin: 1.5rem 0; }
    .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid var(--gray-300); }
    .divider span { padding: 0 1rem; color: var(--gray-700); font-size: 0.875rem; font-weight: 500; }
    .btn-google { width: 100%; padding: 0.875rem 1rem; background: white; color: #333; border: 2px solid var(--gray-300); border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.75rem; transition: all 0.2s; text-decoration: none; margin-bottom: 1.5rem; }
    .btn-google:hover { background: #f8f9fa; border-color: var(--primary); }
    .btn-google svg { width: 20px; height: 20px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <i class="fas fa-home"></i>
      <h1>Registro Bienes Raíces</h1>
      <p>Creá tu cuenta de agente inmobiliario</p>
    </div>

    <?php if ($msg): ?>
      <div class="alert <?php echo $ok ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <?php if ($ok): ?>
      <div class="links"><a href="login.php">Ir a iniciar sesión</a></div>
    <?php else: ?>
      <!-- Botón de Google OAuth -->
      <a href="/real-estate/oauth-start.php" class="btn-google">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
          <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
          <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
          <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
        </svg>
        Continuar con Google
      </a>

      <div class="divider"><span>o registrate con email</span></div>

      <form method="post">
        <div class="form-group">
          <label>Nombre completo</label>
          <input type="text" name="name" required>
        </div>

        <div class="form-group">
          <label>Nombre de inmobiliaria (opcional)</label>
          <input type="text" name="company_name">
        </div>

        <div class="form-group">
          <label>Correo electrónico</label>
          <input type="email" name="email" required>
        </div>

        <div class="form-group">
          <label>Teléfono</label>
          <input type="tel" name="phone" required>
        </div>

        <div class="form-group">
          <label>Contraseña</label>
          <input type="password" name="password" required>
        </div>

        <div class="form-group">
          <label>Confirmar contraseña</label>
          <input type="password" name="password2" required>
        </div>

        <input type="text" name="company_field" class="honeypot" tabindex="-1">

        <div class="form-group">
          <div class="captcha-question">¿Cuánto es <?php echo $captcha_a; ?> + <?php echo $captcha_b; ?>?</div>
          <input type="text" name="captcha" required placeholder="Tu respuesta">
        </div>

        <button type="submit" class="btn">Crear Cuenta</button>
      </form>

      <div class="links">
        <p>¿Ya tenés cuenta? <a href="login.php">Iniciar sesión</a></p>
        <p><a href="/select-publication-type.php">Ver otras opciones</a></p>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
