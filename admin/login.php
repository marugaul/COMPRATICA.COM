<?php
require_once __DIR__ . '/../includes/config.php';

// ---------- Encabezados ----------
if (!headers_sent()) {
  header('Content-Type: text/html; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');
}

// ---------- Bitácora ----------
function admin_log(string $msg): void {
  $logDir = __DIR__ . '/../logs';
  if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
  $line = sprintf("[%s] ADMIN %s | IP:%s | SID:%s%s",
    date('Y-m-d H:i:s'), $msg, $_SERVER['REMOTE_ADDR'] ?? 'N/A', session_id() ?: '-', PHP_EOL
  );
  @file_put_contents($logDir . '/admin.log', $line, FILE_APPEND | LOCK_EX);
}

// ---------- CSRF helpers ----------
function admin_set_csrf(): string {
  $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
  $cookieDomain = '.compratica.com';
  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  setcookie('ADMIN-XSRF', $_SESSION['admin_csrf'], [
    'expires'  => time() + 1800,
    'path'     => '/',
    'domain'   => $cookieDomain,
    'secure'   => $isHttps,
    'httponly' => false,
    'samesite' => 'Lax',
  ]);
  return $_SESSION['admin_csrf'];
}
function admin_get_csrf(): string {
  $tok = (string)($_POST['csrf_token'] ?? '');
  if ($tok === '' && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) $tok = (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
  if ($tok === '' && !empty($_COOKIE['ADMIN-XSRF'])) $tok = (string)$_COOKIE['ADMIN-XSRF'];
  return $tok;
}
function admin_csrf_valid(string $token): bool {
  $sessTok   = (string)($_SESSION['admin_csrf'] ?? '');
  $cookieTok = (string)($_COOKIE['ADMIN-XSRF'] ?? '');
  if ($token === '') return false;
  if ($sessTok && hash_equals($sessTok, $token)) return true;
  if ($cookieTok && hash_equals($cookieTok, $token)) return true;
  return false;
}

// ---------- Login ----------
$dashboard = ADMIN_DASHBOARD_PATH;
if (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
  header('Location: ' . $dashboard);
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  session_regenerate_id(true);
  $csrf = admin_set_csrf();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = admin_get_csrf();
  if (!admin_csrf_valid($token)) {
    admin_log('CSRF inválido en login');
    $csrf = admin_set_csrf();
    $error = 'Token inválido. Refresca la página e intenta de nuevo.';
  } else {
    $user = trim($_POST['username'] ?? '');
    $pass = (string)($_POST['password'] ?? '');
    $okUser = hash_equals(ADMIN_USER, $user);
    $okPass = (ADMIN_PASS_HASH !== '')
                ? password_verify($pass, ADMIN_PASS_HASH)
                : hash_equals(ADMIN_PASS_PLAIN, $pass);

    if ($okUser && $okPass) {
      $_SESSION['is_admin'] = true;
      $_SESSION['admin_user'] = ADMIN_USER;
      session_regenerate_id(true);
      admin_log("Login OK de $user");
      header('Location: ' . $dashboard);
      exit;
    } else {
      admin_log("Login FAIL user=$user");
      $error = 'Usuario o contraseña incorrectos.';
      $csrf = admin_set_csrf();
    }
  }
}
if (!isset($csrf)) $csrf = admin_set_csrf();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin - Ingresar · <?= APP_NAME ?></title>
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
  <link rel="stylesheet" href="../assets/style.css?v=36">
  <style>.login-wrapper{max-width:420px;margin:48px auto}</style>
</head>
<body>
<div class="container login-wrapper">
  <div class="card">
    <h2>Panel Admin</h2>
    <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" class="form" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
      <label>Usuario
        <input class="input" type="text" name="username" required autofocus>
      </label>
      <label>Contraseña
        <input class="input" type="password" name="password" required>
      </label>
      <button class="btn primary" type="submit">Ingresar</button>
    </form>
    <div class="small" style="margin-top:8px;">
      <a href="../index.php">Volver al sitio</a>
    </div>
  </div>
</div>
</body>
</html>
