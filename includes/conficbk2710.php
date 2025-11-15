<?php
declare(strict_types=1);

// =========================
// Configuración general
// =========================
define('APP_NAME', 'VentaGaraje online');
define('BASE_URL', 'https://www.compratica.com');
define('ADMIN_EMAIL', 'marco.ulate@crv-soft.com');

// Zona horaria
if (!defined('APP_TZ')) define('APP_TZ', 'America/Costa_Rica');
date_default_timezone_set(APP_TZ);

// =========================
// Acceso Admin
// =========================
define('ADMIN_USER', 'marugaul');
define('ADMIN_PASS_HASH', '');
define('ADMIN_PASS_PLAIN', 'marden7i');

// Ruta del dashboard real
define('ADMIN_DASHBOARD_PATH', '/admin/dashboard.php');

// =========================
// Pagos
// =========================
define('SINPE_PHONE', '88902814');
define('PAYPAL_EMAIL', 'marco.ulate@crv-soft.com');

// =========================
// Sesión (segura en HTTPS)
// =========================
if (session_status() !== PHP_SESSION_ACTIVE) {
  @ini_set('session.use_strict_mode', '1');
  @ini_set('session.cookie_httponly', '1');
  @ini_set('session.cookie_samesite', 'Lax');
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    @ini_set('session.cookie_secure', '1');
  }

  $SESSION_NAME = 'PHPSESSID';
  if (session_name() !== $SESSION_NAME) {
    session_name($SESSION_NAME);
  }

  $isHttps = (stripos(BASE_URL, 'https://') === 0)
             || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

  $cookieDomain = '.compratica.com';
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $cookieDomain,
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);

  session_start([
    'use_strict_mode' => 1,
    'cookie_secure'   => $isHttps ? 1 : 0,
    'cookie_httponly' => 1,
    'cookie_samesite' => 'Lax',
    'name'            => $SESSION_NAME,
  ]);
}

// CSRF base
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =========================
// Helpers
// =========================
if (!defined('APP_URL')) define('APP_URL', rtrim(BASE_URL, '/'));
function app_base_url() { return APP_URL; }