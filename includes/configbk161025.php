<?php

// Zona horaria global del sistema (Costa Rica)
if (!defined('APP_TZ')) define('APP_TZ', 'America/Costa_Rica');
date_default_timezone_set(APP_TZ);

// Helper consistente para timestamps
if (!function_exists('now_cr')) {
  function now_cr(): string {
    return (new DateTime('now', new DateTimeZone(APP_TZ)))->format('Y-m-d H:i:s');
  }
}

define('APP_NAME', 'VentaGaraje online');
define('BASE_URL', 'http://www.compratica.com'); // http hasta tener SSL

// Admin
define('ADMIN_USER', 'marugaul');
define('ADMIN_PASS_HASH', password_hash('Misha2025', PASSWORD_DEFAULT));

// Pagos
define('SINPE_PHONE', '88902814');
define('PAYPAL_EMAIL', 'marco.ulate@crv-soft.com');

// Notificaciones
define('ADMIN_EMAIL', 'info@compratica.com');
define('NOTIFY_FROM_EMAIL', 'no-reply@compratica.com');

// SMTP opcional
define('SMTP_ENABLED', false);
define('SMTP_HOST', 'smtp-relay.brevo.com');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_SECURE', 'tls');

// Sesi¨®n simple (sin espacios antes de este archivo)
session_name('vg_session');
session_start();

function app_base_url(){ return rtrim(BASE_URL, '/'); }