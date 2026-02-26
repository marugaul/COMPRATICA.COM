<?php
// affiliate/logout.php — cierra sesión del afiliado y redirige al login (UTF-8)
ini_set('display_errors', 0);
require_once __DIR__ . '/../includes/config.php';

// Asegurar sesión iniciada (usa PHPSESSID configurado globalmente)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vaciar variables de sesión
$_SESSION = [];

// Invalidar cookie de sesión (si aplica)
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'] ?: '/', $p['domain'] ?? '', !empty($p['secure']), !empty($p['httponly']));
}

// Destruir sesión
@session_destroy();

// Redirigir al login de afiliado
header('Location: login.php?out=1');
exit;
