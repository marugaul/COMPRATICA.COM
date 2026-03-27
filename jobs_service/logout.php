<?php
// jobs_service/logout.php
require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sesión cerrada — CompraTica</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #1a3a5c, #2e6da4); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
    .card { background: #fff; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.15); padding: 3rem 2.5rem; max-width: 420px; width: 100%; text-align: center; }
    .icon { font-size: 3.5rem; margin-bottom: 1rem; }
    h1 { font-size: 1.5rem; font-weight: 700; color: #1a1a1a; margin-bottom: 0.5rem; }
    p { color: #4a5568; margin-bottom: 2rem; font-size: 0.95rem; }
    .btn { display: inline-block; padding: 0.875rem 1.75rem; border-radius: 8px; font-weight: 600; font-size: 1rem; text-decoration: none; transition: background 0.2s; cursor: pointer; }
    .btn-primary { background: #1a3a5c; color: #fff; margin-bottom: 0.75rem; display: block; }
    .btn-primary:hover { background: #122a44; }
    .btn-secondary { background: #f0f4f8; color: #1a3a5c; display: block; }
    .btn-secondary:hover { background: #dce6f0; }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">👋</div>
    <h1>Sesión cerrada</h1>
    <p>Tu sesión en el Portal de Empleos y Servicios ha sido cerrada correctamente.</p>
    <a href="/" class="btn btn-primary">🏠 Ir a la página principal</a>
    <a href="/jobs_service/login.php" class="btn btn-secondary">Volver a iniciar sesión</a>
  </div>
</body>
</html>
