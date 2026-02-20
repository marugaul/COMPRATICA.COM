<?php
// real-estate/login.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/user_auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Si ya tiene sesión, ir al dashboard
if (is_user_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');

    if ($email === '' || $pass === '') throw new RuntimeException('Por favor ingresa tu correo y contraseña.');

    $user = authenticate_user($email, $pass);

    if (!$user) {
      throw new RuntimeException('Credenciales inválidas.');
    }

    login_user($user);

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
  <title>Login Bienes Raíces — <?php echo APP_NAME; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
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
      <h1>Iniciar Sesión</h1>
      <p>Bienes Raíces</p>
    </div>

    <?php if ($msg): ?>
      <div class="alert"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <!-- Botón de Google OAuth -->
    <a href="/real-estate/oauth-start.php" class="btn-google" id="googleBtn" onclick="showLoading(event)">
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" id="googleIcon">
        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
      </svg>
      <span id="googleText">Continuar con Google</span>
      <span id="googleLoading" style="display:none;">Redirigiendo a Google...</span>
    </a>

    <div id="debugInfo" style="display:none; margin: 1rem 0; padding: 1rem; background: #f0f0f0; border-radius: 8px; font-size: 0.875rem;">
      <strong>Debug:</strong> <span id="debugMsg"></span>
    </div>

    <script>
    function showLoading(e) {
      console.log('🔍 Botón de Google clickeado');
      var btn = document.getElementById('googleBtn');
      var text = document.getElementById('googleText');
      var loading = document.getElementById('googleLoading');
      var debug = document.getElementById('debugInfo');
      var debugMsg = document.getElementById('debugMsg');

      // Mostrar mensaje de carga
      text.style.display = 'none';
      loading.style.display = 'inline';
      btn.style.opacity = '0.7';
      btn.style.pointerEvents = 'none';

      // Mostrar debug info
      debug.style.display = 'block';
      debugMsg.textContent = 'Iniciando flujo OAuth... Si no te redirige a Google en 3 segundos, hay un problema.';

      console.log('🔍 Redirigiendo a: ' + btn.href);

      // Dejar que el navegador siga el enlace normalmente
      return true;
    }

    // Verificar si venimos de un error
    window.addEventListener('DOMContentLoaded', function() {
      var hash = window.location.hash;
      if (hash === '#oauth-test') {
        var debug = document.getElementById('debugInfo');
        var debugMsg = document.getElementById('debugMsg');
        debug.style.display = 'block';
        debugMsg.textContent = 'Listo para probar OAuth. Haz clic en el botón de Google.';
      }
    });
    </script>

    <div class="divider"><span>o usa tu email</span></div>

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
