<?php
// services/logout.php
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
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #1a6b3a, #2e9e5b); min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem; gap: 1.25rem; }
    .topnav { display: flex; gap: 0.75rem; flex-wrap: wrap; justify-content: center; }
    .topnav a { background: rgba(255,255,255,0.15); color: #fff; text-decoration: none; padding: 0.5rem 1.1rem; border-radius: 999px; font-size: 0.875rem; font-weight: 600; border: 1px solid rgba(255,255,255,0.3); transition: background 0.2s; }
    .topnav a:hover { background: rgba(255,255,255,0.28); }
    .card { background: #fff; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.15); padding: 3rem 2.5rem; max-width: 420px; width: 100%; text-align: center; }
    .icon { font-size: 3.5rem; margin-bottom: 1rem; }
    h1 { font-size: 1.5rem; font-weight: 700; color: #1a1a1a; margin-bottom: 0.5rem; }
    p { color: #4a5568; margin-bottom: 2rem; font-size: 0.95rem; }
    .btn { display: inline-block; padding: 0.875rem 1.75rem; border-radius: 8px; font-weight: 600; font-size: 1rem; text-decoration: none; transition: background 0.2s; }
    .btn-primary { background: #1a6b3a; color: #fff; margin-bottom: 0.75rem; display: block; }
    .btn-primary:hover { background: #104d28; }
    .btn-secondary { background: #f0f7f2; color: #1a6b3a; display: block; }
    .btn-secondary:hover { background: #d4edda; }
  </style>
</head>
<body>
  <nav class="topnav">
    <a href="/">🏠 CompraTica</a>
    <a href="/empleos">💼 Ver Empleos</a>
    <a href="/servicios">🔧 Ver Servicios</a>
  </nav>
  <div class="card">
    <div class="icon">👋</div>
    <h1>Sesión cerrada</h1>
    <p>Tu sesión en el Portal de Servicios Profesionales ha sido cerrada correctamente.</p>
    <a href="/" class="btn btn-primary">🏠 Ir a la página principal</a>
    <a href="/services/login.php" class="btn btn-secondary">Volver a iniciar sesión</a>
  </div>
</body>
</html>
