<?php
// real-estate/login.php
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

    if ($email === '' || $pass === '') throw new RuntimeException('Por favor ingresa tu correo y contraseña.');

    $st = $pdo->prepare("SELECT * FROM real_estate_agents WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    $agent = $st->fetch(PDO::FETCH_ASSOC);

    if (!$agent || !password_verify($pass, $agent['password_hash'])) throw new RuntimeException('Credenciales inválidas.');
    if ((int)($agent['is_active'] ?? 0) !== 1) throw new RuntimeException('Tu cuenta aún no ha sido aprobada.');

    session_regenerate_id(true);
    $_SESSION['agent_id'] = (int)$agent['id'];
    $_SESSION['agent_name'] = (string)($agent['name'] ?? '');

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
  <title>Login Bienes Raíces — <?php echo APP_NAME; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root { --primary: #002b7f; --white: #fff; --dark: #1a1a1a; --gray-700: #4a5568; --gray-300: #cbd5e0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, var(--primary), #0041b8); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
    .container { max-width: 450px; width: 100%; background: var(--white); border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); padding: 3rem; }
    .header { text-align: center; margin-bottom: 2rem; }
    .header i { font-size: 3rem; color: var(--primary); margin-bottom: 1rem; }
    .header h1 { font-size: 2rem; font-weight: 700; color: var(--dark); }
    .alert { padding: 1rem; border-radius: 8px; background: #f8d7da; color: #721c24; margin-bottom: 1.5rem; }
    .form-group { margin-bottom: 1.5rem; }
    label { display: block; margin-bottom: 0.5rem; color: var(--gray-700); font-weight: 500; }
    input { width: 100%; padding: 0.875rem 1rem; border: 2px solid var(--gray-300); border-radius: 8px; font-size: 1rem; }
    input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0,43,127,0.1); }
    .btn { width: 100%; padding: 1rem; background: var(--primary); color: var(--white); border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
    .btn:hover { background: #001d5c; }
    .links { text-align: center; margin-top: 1.5rem; }
    .links a { color: var(--primary); text-decoration: none; font-weight: 500; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <i class="fas fa-home"></i>
      <h1>Iniciar Sesión</h1>
      <p>Bienes Raíces</p>
    </div>

    <?php if ($msg): ?>
      <div class="alert"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-group">
        <label>Correo electrónico</label>
        <input type="email" name="email" required autofocus>
      </div>

      <div class="form-group">
        <label>Contraseña</label>
        <input type="password" name="password" required>
      </div>

      <button type="submit" class="btn">Iniciar Sesión</button>
    </form>

    <div class="links">
      <p>¿No tenés cuenta? <a href="register.php">Registrate aquí</a></p>
      <p><a href="/select-publication-type.php">Ver otras opciones</a></p>
    </div>
  </div>
</body>
</html>
