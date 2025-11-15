<?php
// includes/auth.php — utilidades de autenticación para Admin

if (session_status() !== PHP_SESSION_ACTIVE) {
  @session_start();
}

/**
 * Redirige siempre a la pantalla de login si el admin no está autenticado.
 * Usa la misma sesión y rutas definidas en config.php.
 */
function require_login(): void {
  if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    // Regenera el ID para evitar fijación y limpia restos
    @session_regenerate_id(true);
    header('Location: /admin/login.php');
    exit;
  }
}

/**
 * Cierra sesión de forma segura y redirige al login.
 */
function admin_logout_and_redirect(): void {
  $_SESSION['is_admin'] = false;
  unset($_SESSION['is_admin'], $_SESSION['admin_user']);
  @session_regenerate_id(true);
  header('Location: /admin/login.php');
  exit;
}
