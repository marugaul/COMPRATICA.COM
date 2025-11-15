<?php
require_once __DIR__ . '/../inc/security.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Crear cuenta / Conectarse</title>
  <link rel="stylesheet" href="/assets/style.css?v=17">
</head>
<body>
<header class="header">
  <div class="logo">Compratica</div>
  <nav><a class="btn" href="/">Inicio</a></nav>
</header>

<div class="container">
  <div class="card">
    <h2>Crear cuenta o conectarse</h2>
    <?php $partial = __DIR__ . '/../views/partial_social_buttons.php';
      if (file_exists($partial)) include $partial; ?>
    <hr>
    <form method="post" action="/auth/register.php" style="margin-top:1rem;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(),ENT_QUOTES) ?>">
      <label>Nombre<br><input class="input" type="text" name="name" required></label><br>
      <label>Correo<br><input class="input" type="email" name="email" required></label><br>
      <label>Contraseña (mín. 8)<br><input class="input" type="password" name="password" minlength="8" required></label><br>
      <button class="btn primary" type="submit">Crear cuenta</button>
    </form>
    <p class="small" style="margin-top:1rem;">¿Ya tienes cuenta? continúa por <a href="/checkout.php">checkout</a>.</p>
  </div>
</div>
</body>
</html>
