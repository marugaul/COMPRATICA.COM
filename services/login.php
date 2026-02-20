<?php
// services/login.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Si ya tiene sesión, ir al dashboard
if (!empty($_SESSION['agent_id'])) {
    header('Location: dashboard.php');
    exit;
}

$pdo = db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');

        if ($email === '' || $pass === '') {
            throw new RuntimeException('Por favor ingresá tu correo y contraseña.');
        }

        $st = $pdo->prepare("SELECT * FROM real_estate_agents WHERE email = ? LIMIT 1");
        $st->execute([$email]);
        $agent = $st->fetch(PDO::FETCH_ASSOC);

        if (!$agent || !password_verify($pass, $agent['password_hash'])) {
            throw new RuntimeException('Credenciales inválidas.');
        }
        if ((int)($agent['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('Tu cuenta no está activa.');
        }

        session_regenerate_id(true);
        $_SESSION['agent_id']   = (int)$agent['id'];
        $_SESSION['agent_name'] = (string)($agent['name'] ?? '');
        $_SESSION['uid']        = (int)$agent['id'];
        $_SESSION['name']       = (string)($agent['name'] ?? '');

        header('Location: dashboard.php?login=success');
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
  <title>Login Servicios — <?php echo APP_NAME; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    :root { --primary: #1a6b3a; --primary-dark: #104d28; --white: #fff; --dark: #1a1a1a; --gray-700: #4a5568; --gray-300: #cbd5e0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, var(--primary), #2e9e5b); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
    .container { max-width: 450px; width: 100%; background: var(--white); border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.15); padding: 3rem; }
    .header { text-align: center; margin-bottom: 2rem; }
    .header i { font-size: 3rem; color: var(--primary); margin-bottom: 1rem; display: block; }
    .header h1 { font-size: 1.875rem; font-weight: 700; color: var(--dark); }
    .header p { color: var(--gray-700); margin-top: 0.5rem; }
    .alert { padding: 1rem; border-radius: 8px; background: #f8d7da; color: #721c24; margin-bottom: 1.5rem; border: 1px solid #f5c6cb; }
    .form-group { margin-bottom: 1.5rem; }
    label { display: block; margin-bottom: 0.5rem; color: var(--gray-700); font-weight: 500; }
    input { width: 100%; padding: 0.875rem 1rem; border: 2px solid var(--gray-300); border-radius: 8px; font-size: 1rem; font-family: 'Inter', sans-serif; transition: border-color 0.2s; }
    input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,58,0.1); }
    .btn { width: 100%; padding: 1rem; background: var(--primary); color: var(--white); border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 1rem; font-family: 'Inter', sans-serif; transition: background 0.2s; }
    .btn:hover { background: var(--primary-dark); }
    .divider { display: flex; align-items: center; text-align: center; margin: 1.5rem 0; }
    .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid var(--gray-300); }
    .divider span { padding: 0 1rem; color: var(--gray-700); font-size: 0.875rem; font-weight: 500; }
    .btn-google { width: 100%; padding: 0.875rem 1rem; background: white; color: #333; border: 2px solid var(--gray-300); border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.75rem; transition: all 0.2s; text-decoration: none; margin-bottom: 1.5rem; font-family: 'Inter', sans-serif; }
    .btn-google:hover { background: #f8f9fa; border-color: var(--primary); }
    .btn-google svg { width: 20px; height: 20px; flex-shrink: 0; }
    .links { text-align: center; margin-top: 1.5rem; }
    .links p { margin-bottom: 0.5rem; color: var(--gray-700); }
    .links a { color: var(--primary); text-decoration: none; font-weight: 500; }
    .links a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <i class="fas fa-concierge-bell"></i>
      <h1>Iniciar Sesión</h1>
      <p>Servicios Profesionales</p>
    </div>

    <?php if ($msg): ?>
      <div class="alert"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <a href="/services/oauth-start.php" class="btn-google" id="googleBtn" onclick="showLoading(event)">
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
      </svg>
      <span id="googleText">Continuar con Google</span>
      <span id="googleLoading" style="display:none;">Redirigiendo a Google...</span>
    </a>

    <script>
    function showLoading(e) {
      var btn = document.getElementById('googleBtn');
      document.getElementById('googleText').style.display = 'none';
      document.getElementById('googleLoading').style.display = 'inline';
      btn.style.opacity = '0.7';
      btn.style.pointerEvents = 'none';
      return true;
    }
    </script>

    <div class="divider"><span>o usá tu email</span></div>

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
      <p><a href="/select-publication-type.php">Ver otras opciones de publicación</a></p>
    </div>
  </div>
</body>
</html>
