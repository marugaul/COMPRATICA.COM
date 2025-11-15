<?php
// =========================
// Configuración general
// =========================
define('APP_NAME', 'VentaGaraje online');

// Usa siempre HTTPS ahora que tienes SSL activo
define('BASE_URL', 'https://www.compratica.com');

// Zona horaria de Costa Rica para todo el sistema
if (!defined('APP_TZ')) {
  define('APP_TZ', 'America/Costa_Rica');
}
date_default_timezone_set(APP_TZ);

// =========================
// Acceso Admin
// =========================
define('ADMIN_USER', 'marugaul');
// Nota: se calcula en cada carga; se usa con password_verify('admin123', ADMIN_PASS_HASH)
define('ADMIN_PASS_HASH', password_hash('marden7i', PASSWORD_DEFAULT));

// =========================
// Pagos
// =========================
define('SINPE_PHONE', '88902814');
define('PAYPAL_EMAIL', 'marco.ulate@crv-soft.com');

// =========================
// Notificaciones / Email
// =========================
define('ADMIN_EMAIL', 'marco.ulate@crv-soft.com');
define('NOTIFY_FROM_EMAIL', 'no-reply@compratica.com');

// =========================
// SMTP opcional
// =========================
define('SMTP_ENABLED', false);
define('SMTP_HOST', 'smtp-relay.brevo.com');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_SECURE', 'tls');

// =========================
// Sesión (cookies seguras en HTTPS)
// =========================


session_name('vg_session');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('compratica');
    session_start();
}

if (session_status() === PHP_SESSION_NONE) {
  // Como BASE_URL es https, marcamos secure=true
  $isHttps = true;
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}



// =========================
# Helpers de URL
// =========================
if (!defined('APP_URL')) {
  define('APP_URL', rtrim(BASE_URL, '/'));
}

function app_base_url(){
  return APP_URL;
}
