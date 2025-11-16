<?php
declare(strict_types=1);

// =========================
// DETECCIÓN AUTOMÁTICA DE STAGING (TEMPORAL - Quitar cuando exista subdominio)
// =========================
$isStaging = false;
$stagingPath = '';
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/staging/') === 0) {
    $isStaging = true;
    $stagingPath = '/staging';
}

// =========================
// Configuración general
// =========================
define('APP_NAME', 'VentaGaraje online');
define('BASE_URL', $isStaging ? 'https://www.compratica.com/staging' : 'https://www.compratica.com');
define('SITE_URL', $isStaging ? 'https://compratica.com/staging' : 'https://compratica.com');
define('SITE_EMAIL', 'info@compratica.com');
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
define('ADMIN_DASHBOARD_PATH', $stagingPath . '/admin/dashboard.php');

// =========================
// Pagos
// =========================
define('SINPE_PHONE', '88902814');
define('PAYPAL_EMAIL', 'marco.ulate@crv-soft.com');

// =========================
// Sesión (UNIFICADA - Compatible con todo el sitio)
// =========================
if (session_status() === PHP_SESSION_NONE) {
    $savePath = __DIR__ . '/../sessions';
    if (!is_dir($savePath)) {
        @mkdir($savePath, 0755, true);
    }
    if (is_dir($savePath) && is_writable($savePath)) {
        session_save_path($savePath);
    }

    // Detectar HTTPS de forma robusta
    $isHttps = false;
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') $isHttps = true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') $isHttps = true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') $isHttps = true;
    if (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false) $isHttps = true;
    if (stripos(BASE_URL, 'https://') === 0) $isHttps = true;

    // Detectar dominio automáticamente (IGUAL que login.php)
    $host = $_SERVER['HTTP_HOST'] ?? parse_url(BASE_URL, PHP_URL_HOST) ?? '';
    $cookieDomain = '';
    
    if ($host && strpos($host, 'localhost') === false && !filter_var($host, FILTER_VALIDATE_IP)) {
        // Remover www. si existe
        $clean = preg_replace('/^www\./i', '', $host);
        
        // Validar que sea un dominio válido
        if (filter_var($clean, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            // IMPORTANTE: Agregar punto inicial para que funcione en subdominios
            $cookieDomain = '.' . $clean;
        }
    }

    // Establecer parámetros de cookie ANTES de session_start()
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => $cookieDomain,
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/', $cookieDomain, $isHttps, true);
    }

    // Nombre de la sesión (consistente en todo el sitio)
    session_name('PHPSESSID');

    // Iniciar sesión
    session_start();
}

// CSRF base
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =========================
// Helpers de sesión
// =========================
function is_logged_in(): bool {
    return isset($_SESSION['uid']) && (int)$_SESSION['uid'] > 0;
}

function get_user_id(): int {
    return (int)($_SESSION['uid'] ?? 0);
}

function get_session_id_for_cart(): string {
    // Si está logueado, usar el user_id
    // Si no, usar el session_id
    if (is_logged_in()) {
        return 'user_' . get_user_id();
    }
    return session_id();
}

// =========================
// Helpers generales
// =========================
if (!defined('APP_URL')) define('APP_URL', rtrim(BASE_URL, '/'));
function app_base_url() { return APP_URL; }