<?php
declare(strict_types=1);

/* =========================
   Sesión endurecida (unifica nombre con config.php)
   ========================= */
$SESSION_NAME = 'vg_session'; // <-- igual que en includes/config.php

if (session_status() === PHP_SESSION_NONE) {
  // Configuración segura de la sesión
  ini_set('session.use_strict_mode', '1');
  ini_set('session.cookie_httponly', '1');
  ini_set('session.cookie_samesite', 'Lax');
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
  }

  if (session_name() !== $SESSION_NAME) {
    session_name($SESSION_NAME);
  }

  // Cookie params coherentes en todo el sitio
  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '.compratica.com', // comparte entre www y sin www
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);

  session_start();
}

/* =========================
   Security Headers
   ========================= */
if (!headers_sent()) {
  header('X-Frame-Options: SAMEORIGIN');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: strict-origin-when-cross-origin');
}

/* =========================
   CSRF: token en sesión + cookie vg_csrf sincronizada
   ========================= */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function security_set_csrf_cookie(): void {
  $token = (string)($_SESSION['csrf_token'] ?? '');
  if ($token === '') return;
  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  // Exponer cookie legible por JS (no httponly) para que el front la reenvíe por header
  setcookie('vg_csrf', $token, [
    'expires'  => time() + 60*60*24, // 1 día
    'path'     => '/',
    'domain'   => '.compratica.com',
    'secure'   => $isHttps,
    'httponly' => false,            // <-- JS la puede leer
    'samesite' => 'Lax',
  ]);
}
security_set_csrf_cookie();

function csrf_token(): string {
  return (string)($_SESSION['csrf_token'] ?? '');
}

function csrf_check(): void {
  // Acepta header o POST
  $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  $post   = $_POST['csrf_token'] ?? '';
  $given  = (string)($header !== '' ? $header : $post);
  $sess   = (string)($_SESSION['csrf_token'] ?? '');

  if ($sess === '' || $given === '' || !hash_equals($sess, $given)) {
    http_response_code(419);
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'invalid_csrf']);
    exit;
  }
}

/* =========================
   Helpers
   ========================= */
function current_user_id(): ?int {
  // Soporta ambas claves de sesión
  if (isset($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
  if (isset($_SESSION['uid']))     return (int)$_SESSION['uid'];
  return null;
}

function ensure_json(): void {
  if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
}

function safe_redirect(string $url, string $fallback='/'): void {
  $host = parse_url($url, PHP_URL_HOST);
  if ($host && $host !== ($_SERVER['HTTP_HOST'] ?? '')) {
    $url = $fallback;
  }
  header('Location: ' . $url, true, 302);
  exit;
}

