<?php
declare(strict_types=1);

// =========================
// Configuración general
// =========================
define('APP_NAME', 'VentaGaraje online');
define('BASE_URL', 'https://compratica.com');
define('SITE_URL', 'https://compratica.com');
define('SITE_EMAIL', 'info@compratica.com');
define('ADMIN_EMAIL', 'marco.ulate@crv-soft.com');

// Zona horaria
if (!defined('APP_TZ')) define('APP_TZ', 'America/Costa_Rica');
date_default_timezone_set(APP_TZ);

// =========================
// Tipos de Publicación
// =========================
define('PUBLICATION_TYPE_GARAGE_SALE', 'garage_sale');
define('PUBLICATION_TYPE_REAL_ESTATE', 'real_estate');
define('PUBLICATION_TYPE_SERVICES', 'services');
define('PUBLICATION_TYPE_JOBS', 'jobs');

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

// PayPal API Configuration
// NOTA: Las credenciales se configuran en config.local.php (archivo seguro no versionado)
if (!defined('PAYPAL_MODE')) define('PAYPAL_MODE', 'sandbox'); // 'sandbox' para pruebas, 'live' para producción
if (!defined('PAYPAL_CLIENT_ID')) define('PAYPAL_CLIENT_ID', ''); // Se define en config.local.php
if (!defined('PAYPAL_SECRET')) define('PAYPAL_SECRET', ''); // Se define en config.local.php

// URLs de API de PayPal (se configuran automáticamente según PAYPAL_MODE)
if (!defined('PAYPAL_API_URL')) {
    define('PAYPAL_API_URL', PAYPAL_MODE === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com');
}

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
// OAuth Configuration (Google & Facebook Login)
// =========================
// INSTRUCCIONES EN: OAUTH_SETUP.md

// Cargar configuración local PRIMERO (credenciales sensibles)
// NOTA: Este archivo NO debe subirse a git (está en .gitignore)
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// Cargar configuración de Sightengine (credenciales API)
// NOTA: Este archivo NO debe subirse a git (está en .gitignore)
if (file_exists(__DIR__ . '/config.sightengine.php')) {
    require_once __DIR__ . '/config.sightengine.php';
}

// Definir valores por defecto solo si no fueron definidos en config.local.php
if (!defined('GOOGLE_CLIENT_ID')) define('GOOGLE_CLIENT_ID', '');
if (!defined('GOOGLE_CLIENT_SECRET')) define('GOOGLE_CLIENT_SECRET', '');
if (!defined('FACEBOOK_APP_ID')) define('FACEBOOK_APP_ID', '');
if (!defined('FACEBOOK_APP_SECRET')) define('FACEBOOK_APP_SECRET', '');

// =========================
// Sightengine API (Moderación de Imágenes)
// =========================
// Obtén credenciales gratis en: https://sightengine.com/
// Plan gratuito: 2000 operaciones/mes sin tarjeta de crédito
// Detecta: pornografía, violencia, gore, contenido ofensivo
if (!defined('SIGHTENGINE_API_USER')) define('SIGHTENGINE_API_USER', '');
if (!defined('SIGHTENGINE_API_SECRET')) define('SIGHTENGINE_API_SECRET', '');

// =========================
// Helpers generales
// =========================
if (!defined('APP_URL')) define('APP_URL', rtrim(BASE_URL, '/'));
function app_base_url() { return APP_URL; }

/**
 * Convierte URLs de Google Drive al formato de visualización directa
 * De: https://drive.google.com/file/d/FILE_ID/view?usp=sharing
 * A: https://drive.google.com/uc?export=view&id=FILE_ID
 */
function convert_google_drive_url(string $url): string {
    // Patrón para URLs de Google Drive
    $pattern = '/https:\/\/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/';

    if (preg_match($pattern, $url, $matches)) {
        $fileId = $matches[1];
        return "https://drive.google.com/uc?export=view&id={$fileId}";
    }

    // Si no es una URL de Google Drive o ya está en formato correcto, devolver sin cambios
    return $url;
}