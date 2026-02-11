<?php
// jobs/register.php
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
  $_SESSION['job_reg_captcha_ans'] = $a + $b;
  $_SESSION['job_reg_captcha_a']   = $a;
  $_SESSION['job_reg_captcha_b']   = $b;
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
    $expect  = (string)($_SESSION['job_reg_captcha_ans'] ?? '');
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

    $st = $pdo->prepare("SELECT id FROM jobs_employers WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    if ($st->fetchColumn()) {
      throw new RuntimeException('Ya existe una cuenta con este correo.');
    }

    $hash = password_hash($pass, PASSWORD_BCRYPT);

    $ins = $pdo->prepare("
      INSERT INTO jobs_employers (name, email, phone, company_name, password_hash, is_active, created_at)
      VALUES (?, ?, ?, ?, ?, 1, datetime('now'))
    ");
    $ins->execute([$name, $email, $phone_ok, $company_name, $hash]);

    $employer_id = (int)$pdo->lastInsertId();
    $ok = true;
    $msg = "¡Tu cuenta fue creada y activada! Ya podés iniciar sesión.";

    $_SESSION['job_reg_captcha_ans'] = null;

  } catch (Throwable $e) {
    $msg = "Error: " . $e->getMessage();

    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $_SESSION['job_reg_captcha_ans'] = $a + $b;
    $_SESSION['job_reg_captcha_a']   = $a;
    $_SESSION['job_reg_captcha_b']   = $b;
  }
}

$captcha_a = $_SESSION['job_reg_captcha_a'] ?? 0;
$captcha_b = $_SESSION['job_reg_captcha_b'] ?? 0;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registro Empleos y Servicios — <?php echo APP_NAME; ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary: #27ae60;
      --primary-dark: #229954;
      --accent: #2ecc71;
      --success: #27ae60;
      --danger: #c0392b;
      --dark: #1a1a1a;
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
      background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
    }

    .container {
      max-width: 500px;
      width: 100%;
      background: var(--white);
      border-radius: 16px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
      padding: 3rem;
    }

    .header {
      text-align: center;
      margin-bottom: 2rem;
    }

    .header i {
      font-size: 3rem;
      color: var(--primary);
      margin-bottom: 1rem;
    }

    .header h1 {
      font-size: 2rem;
      font-weight: 700;
      color: var(--dark);
      margin-bottom: 0.5rem;
    }

    .header p {
      color: var(--gray-700);
      font-size: 1rem;
    }

    .alert {
      padding: 1rem;
      border-radius: var(--radius);
      margin-bottom: 1.5rem;
      font-weight: 500;
    }

    .alert.success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .alert.error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    label {
      display: block;
      margin-bottom: 0.5rem;
      color: var(--gray-700);
      font-weight: 500;
      font-size: 0.95rem;
    }

    input[type="text"],
    input[type="email"],
    input[type="tel"],
    input[type="password"] {
      width: 100%;
      padding: 0.875rem 1rem;
      border: 2px solid var(--gray-300);
      border-radius: var(--radius);
      font-size: 1rem;
      transition: var(--transition);
      font-family: inherit;
    }

    input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.1);
    }

    input.honeypot {
      position: absolute;
      left: -9999px;
      width: 1px;
      height: 1px;
    }

    .btn {
      width: 100%;
      padding: 1rem;
      background: var(--primary);
      color: var(--white);
      border: none;
      border-radius: var(--radius);
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .btn:hover {
      background: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: var(--shadow-lg);
    }

    .divider {
      text-align: center;
      margin: 1.5rem 0;
      color: var(--gray-500);
      position: relative;
    }

    .divider::before,
    .divider::after {
      content: '';
      position: absolute;
      top: 50%;
      width: 45%;
      height: 1px;
      background: var(--gray-300);
    }

    .divider::before { left: 0; }
    .divider::after { right: 0; }

    .links {
      text-align: center;
      margin-top: 1.5rem;
    }

    .links a {
      color: var(--primary);
      text-decoration: none;
      font-weight: 500;
      transition: var(--transition);
    }

    .links a:hover {
      color: var(--primary-dark);
      text-decoration: underline;
    }

    .captcha-question {
      background: var(--gray-100);
      padding: 1rem;
      border-radius: var(--radius);
      margin-bottom: 1rem;
      text-align: center;
      font-weight: 600;
      color: var(--dark);
    }

    .captcha-question span {
      color: var(--primary);
      font-size: 1.25rem;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <i class="fas fa-briefcase"></i>
      <h1>Registro Empleos y Servicios</h1>
      <p>Creá tu cuenta para publicar ofertas</p>
    </div>

    <?php if ($msg): ?>
      <div class="alert <?php echo $ok ? 'success' : 'error'; ?>">
        <?php echo htmlspecialchars($msg); ?>
      </div>
    <?php endif; ?>

    <?php if ($ok): ?>
      <div class="links">
        <a href="login.php"><i class="fas fa-sign-in-alt"></i> Ir a iniciar sesión</a>
      </div>
    <?php else: ?>
      <form method="post">
        <div class="form-group">
          <label><i class="fas fa-user"></i> Nombre completo</label>
          <input type="text" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
        </div>

        <div class="form-group">
          <label><i class="fas fa-building"></i> Nombre de empresa (opcional)</label>
          <input type="text" name="company_name" value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
        </div>

        <div class="form-group">
          <label><i class="fas fa-envelope"></i> Correo electrónico</label>
          <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="form-group">
          <label><i class="fas fa-phone"></i> Teléfono</label>
          <input type="tel" name="phone" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
        </div>

        <div class="form-group">
          <label><i class="fas fa-lock"></i> Contraseña</label>
          <input type="password" name="password" required>
        </div>

        <div class="form-group">
          <label><i class="fas fa-lock"></i> Confirmar contraseña</label>
          <input type="password" name="password2" required>
        </div>

        <!-- Honeypot anti-bot -->
        <input type="text" name="company_field" class="honeypot" tabindex="-1" autocomplete="off">

        <!-- Captcha sencillo -->
        <div class="form-group">
          <div class="captcha-question">
            ¿Cuánto es <span><?php echo $captcha_a; ?></span> + <span><?php echo $captcha_b; ?></span>?
          </div>
          <input type="text" name="captcha" required placeholder="Tu respuesta">
        </div>

        <button type="submit" class="btn">
          <i class="fas fa-user-plus"></i>
          Crear Cuenta
        </button>
      </form>

      <div class="divider">o</div>

      <div class="links">
        <p>¿Ya tenés cuenta? <a href="login.php">Iniciar sesión</a></p>
        <p><a href="/select-publication-type.php"><i class="fas fa-arrow-left"></i> Ver otras opciones</a></p>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
