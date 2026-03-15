<?php
// includes/auth.php — utilidades de autenticación para Admin

// Función de debug para troubleshooting
function auth_debug_log($msg, $data = []) {
    $logFile = __DIR__ . '/../logs/auth_debug.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) @mkdir($logDir, 0775, true);

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if (!empty($data)) {
        $line .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    $line .= PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// NOTA: NO iniciamos sesión aquí porque config.php ya lo hace.
// Si intentamos iniciar la sesión nuevamente, podemos perder las variables de sesión.

// Verificar que la sesión esté activa (config.php debería haberla iniciado)
if (session_status() !== PHP_SESSION_ACTIVE) {
    auth_debug_log('ERROR: Sesión no activa - config.php no fue cargado', [
        'session_status' => session_status(),
        'cookies' => array_keys($_COOKIE)
    ]);
    // En este caso, cargar config.php para iniciar la sesión correctamente
    require_once __DIR__ . '/config.php';
}

// Verificar estado de la sesión
auth_debug_log('Sesión verificada', [
    'session_id' => session_id(),
    'is_admin' => $_SESSION['is_admin'] ?? 'NO DEFINIDO',
    'admin_user' => $_SESSION['admin_user'] ?? 'NO DEFINIDO',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A'
]);

// Admin nunca debe cachearse — ni browser ni proxies/CDN
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

/**
 * Redirige siempre a la pantalla de login si el admin no está autenticado.
 * Usa la misma sesión y rutas definidas en config.php.
 */
function require_login(): void {
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

    auth_debug_log('require_login() llamado', [
        'is_admin_isset' => isset($_SESSION['is_admin']),
        'is_admin_value' => $_SESSION['is_admin'] ?? 'NO DEFINIDO',
        'is_admin_bool' => $isAdmin,
        'session_id' => session_id(),
        'current_url' => $_SERVER['REQUEST_URI'] ?? 'N/A'
    ]);

    if (!$isAdmin) {
        auth_debug_log('Acceso denegado - redirigiendo a login');
        // NO regenerar session_id aquí para no perder la sesión
        header('Location: /admin/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
        exit;
    }

    auth_debug_log('Acceso permitido', ['admin_user' => $_SESSION['admin_user'] ?? 'N/A']);
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
