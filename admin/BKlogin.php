
<?php
require_once __DIR__ . '/../includes/config.php';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $user=$_POST['user']??''; $pass=$_POST['pass']??'';
  if($user===ADMIN_USER && password_verify($pass, ADMIN_PASS_HASH)){ $_SESSION['admin']=true; header('Location: dashboard.php'); exit; }
  else $error='Credenciales inválidas';
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login</title><link rel="stylesheet" href="../assets/style.css"></head>
<body><div class="container" style="max-width:380px;margin:40px auto">
<h1>Backoffice</h1>
<?php if(!empty($error)): ?><div class="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<form method="post" class="form">
<label>Usuario <input class="input" type="text" name="user" required></label>
<label>Contraseña <input class="input" type="password" name="pass" required></label>
<button class="btn primary" type="submit">Ingresar</button>
<p class="small">admin / admin123 (edita includes/config.php)</p>
</form></div></body></html>
