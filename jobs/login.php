<?php
// jobs/login.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$pdo = db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');

    if ($email === '' || $pass === '') {
      throw new RuntimeException('Por favor ingresa tu correo y contraseña.');
    }

    $st = $pdo->prepare("SELECT * FROM jobs_employers WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    $emp = $st->fetch(PDO::FETCH_ASSOC);

    if (!$emp || !password_verify($pass, $emp['password_hash'])) {
      throw new RuntimeException('Credenciales inválidas.');
    }

    if ((int)($emp['is_active'] ?? 0) !== 1) {
      throw new RuntimeException('Tu cuenta aún no ha sido aprobada.');
    }

    session_regenerate_id(true);
    $_SESSION['employer_id'] = (int)$emp['id'];
    $_SESSION['employer_name'] = (string)($emp['name'] ?? '');

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
  <title>Iniciar Sesión Empleos — <?php echo APP_NAME; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    :root {
      --primary: #27ae60;
      --primary-dark: #229954;
      --white: #ffffff;
      --dark: #1a1a1a;
      --gray-700: #4a5568;
      --gray-300: #cbd5e0;
      --gray-100: #f7fafc;
      --radius: 8px;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, var(--primary) 0%, #2ecc71 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
    }

    .container {
      max-width: 450px;
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

    .alert {
      padding: 1rem;
      border-radius: var(--radius);
      background: #f8d7da;
      color: #721c24;
      margin-bottom: 1.5rem;
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    label {
      display: block;
      margin-bottom: 0.5rem;
      color: var(--gray-700);
      font-weight: 500;
    }

    input {
      width: 100%;
      padding: 0.875rem 1rem;
      border: 2px solid var(--gray-300);
      border-radius: var(--radius);
      font-size: 1rem;
      transition: all 0.25s;
    }

    input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.1);
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
      transition: all 0.25s;
    }

    .btn:hover {
      background: var(--primary-dark);
      transform: translateY(-2px);
    }

    .links {
      text-align: center;
      margin-top: 1.5rem;
    }

    .links a {
      color: var(--primary);
      text-decoration: none;
      font-weight: 500;
    }

    .links a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <i class="fas fa-briefcase"></i>
      <h1>Iniciar Sesión</h1>
      <p>Empleos y Servicios</p>
    </div>

    <?php if ($msg): ?>
      <div class="alert"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-group">
        <label><i class="fas fa-envelope"></i> Correo electrónico</label>
        <input type="email" name="email" required autofocus>
      </div>

      <div class="form-group">
        <label><i class="fas fa-lock"></i> Contraseña</label>
        <input type="password" name="password" required>
      </div>

      <button type="submit" class="btn">
        <i class="fas fa-sign-in-alt"></i>
        Iniciar Sesión
      </button>
    </form>

    <div class="links">
      <p>¿No tenés cuenta? <a href="register.php">Registrate aquí</a></p>
      <p><a href="/select-publication-type.php"><i class="fas fa-arrow-left"></i> Ver otras opciones</a></p>
    </div>
  </div>
</body>
</html>
