<?php
// affiliate/reset-password.php – Resetear contraseña con token
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = db();
$msg = '';
$msgType = '';
$validToken = false;
$email = '';

// Verificar token
if (isset($_GET['token']) && !empty($_GET['token'])) {
  $token = $_GET['token'];

  try {
    // Verificar si existe la tabla
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='password_resets'")->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($tables)) {
      $stmt = $pdo->prepare("
        SELECT email, expires_at, used
        FROM password_resets
        WHERE token = ?
        LIMIT 1
      ");
      $stmt->execute([$token]);
      $reset = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($reset) {
        // Verificar si ya fue usado
        if ($reset['used'] == 1) {
          $msg = 'Este enlace ya fue utilizado.';
          $msgType = 'error';
        }
        // Verificar si expiró
        elseif (strtotime($reset['expires_at']) < time()) {
          $msg = 'Este enlace ha expirado. Por favor solicita uno nuevo.';
          $msgType = 'error';
        }
        else {
          $validToken = true;
          $email = $reset['email'];
        }
      } else {
        $msg = 'Enlace de recuperación inválido.';
        $msgType = 'error';
      }
    } else {
      $msg = 'Enlace de recuperación inválido.';
      $msgType = 'error';
    }
  } catch (Exception $e) {
    $msg = 'Error al verificar el token.';
    $msgType = 'error';
  }
} else {
  $msg = 'Token no proporcionado.';
  $msgType = 'error';
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
  try {
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    if ($password === '' || $passwordConfirm === '') {
      throw new RuntimeException('Por favor completa todos los campos.');
    }

    if (strlen($password) < 6) {
      throw new RuntimeException('La contraseña debe tener al menos 6 caracteres.');
    }

    if ($password !== $passwordConfirm) {
      throw new RuntimeException('Las contraseñas no coinciden.');
    }

    // Actualizar contraseña
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $stmt->execute([$passwordHash, $email]);

    // Marcar token como usado
    $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
    $stmt->execute([$_GET['token']]);

    $msg = '¡Contraseña actualizada exitosamente! Ya puedes <a href="login.php" class="link">iniciar sesión</a>.';
    $msgType = 'success';
    $validToken = false; // Para ocultar el formulario

  } catch (Throwable $e) {
    $msg = $e->getMessage();
    $msgType = 'error';
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Restablecer Contraseña — <?php echo APP_NAME; ?></title>
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
      align-items: flex-start;
      gap: 0.75rem;
    }

    .alert.error {
      background: #fee;
      color: var(--danger);
      border: 1px solid #fcc;
    }

    .alert.error::before {
      content: "⚠️";
      font-size: 1.25rem;
    }

    .alert.success {
      background: #efe;
      color: #0a6;
      border: 1px solid #cfc;
    }

    .alert.success::before {
      content: "✓";
      font-size: 1.25rem;
      font-weight: bold;
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

    .password-requirements {
      font-size: 0.875rem;
      color: var(--gray-500);
      margin-top: 0.5rem;
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
    <a class="btn" href="login.php">
      <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
    </a>
  </nav>
</header>

<div class="container">
  <?php if ($msg): ?>
    <div class="alert <?= $msgType ?>">
      <div><?= $msg ?></div>
    </div>
  <?php endif; ?>

  <?php if ($validToken): ?>
  <div class="card">
    <h3>Nueva Contraseña</h3>
    <p>Ingresa tu nueva contraseña para <strong><?= htmlspecialchars($email) ?></strong></p>

    <form class="form" method="post">
      <div>
        <label>Nueva contraseña</label>
        <input class="input" type="password" name="password" required autofocus minlength="6">
        <div class="password-requirements">Mínimo 6 caracteres</div>
      </div>

      <div>
        <label>Confirmar contraseña</label>
        <input class="input" type="password" name="password_confirm" required minlength="6">
      </div>

      <button class="btn btn-primary" type="submit">
        <i class="fas fa-check"></i> Cambiar Contraseña
      </button>
    </form>

    <p class="small">
      <a href="login.php" class="link">Volver a iniciar sesión</a>
    </p>
  </div>
  <?php else: ?>
  <div class="card">
    <h3>Enlace Inválido</h3>
    <p>El enlace de recuperación no es válido o ha expirado.</p>
    <a href="forgot-password.php" class="btn btn-primary">
      <i class="fas fa-redo"></i> Solicitar nuevo enlace
    </a>
  </div>
  <?php endif; ?>
</div>

</body>
</html>
