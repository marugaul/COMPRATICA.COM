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
