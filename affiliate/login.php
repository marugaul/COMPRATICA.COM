<?php
// affiliate/login.php – UTF-8 (sin BOM)
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$pdo = db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');

    if ($email === '' || $pass === '') {
      throw new RuntimeException('Por favor ingresa tu correo y contraseña.');
    }

    $st = $pdo->prepare("SELECT * FROM affiliates WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    $a = $st->fetch(PDO::FETCH_ASSOC);

    if (!$a) {
      throw new RuntimeException('Credenciales inválidas.');
    }

    $hash = null;
    if (!empty($a['password_hash'])) {
      $hash = $a['password_hash'];
    } elseif (!empty($a['pass_hash'])) {
      $hash = $a['pass_hash'];
    }

    if (!$hash || !password_verify($pass, $hash)) {
      throw new RuntimeException('Credenciales inválidas.');
    }

    if ((int)($a['is_active'] ?? 0) !== 1) {
      throw new RuntimeException('Tu cuenta aún no ha sido aprobada por el administrador.');
    }

    session_regenerate_id(true);
    $_SESSION['aff_id']   = (int)$a['id'];
    $_SESSION['aff_name'] = (string)($a['name'] ?? '');

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
  <a href="../index.php" class="logo">
    <i class="fas fa-store"></i>
    <?php echo APP_NAME; ?>
  </a>
  <nav class="header-nav">
    <a class="btn" href="../index.php">
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
