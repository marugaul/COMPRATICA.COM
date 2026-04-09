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

// PayPal y Stripe: Se configuran después de cargar config.local.php

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

// PayPal API Configuration
if (!defined('PAYPAL_MODE')) define('PAYPAL_MODE', 'live'); // 'sandbox' para pruebas, 'live' para producción
if (!defined('PAYPAL_CLIENT_ID')) define('PAYPAL_CLIENT_ID', '');
if (!defined('PAYPAL_SECRET')) define('PAYPAL_SECRET', '');

// URLs de API de PayPal (se configuran automáticamente según PAYPAL_MODE)
if (!defined('PAYPAL_API_URL')) {
    define('PAYPAL_API_URL', PAYPAL_MODE === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com');
}

// Stripe API Configuration
if (!defined('STRIPE_PUBLIC_KEY')) define('STRIPE_PUBLIC_KEY', '');
if (!defined('STRIPE_SECRET_KEY')) define('STRIPE_SECRET_KEY', '');

// ══════════════════════════════════════════════════════════════════════
// SwiftPay — Gateway de Tarjetas (Visa / Mastercard / Amex)
// ══════════════════════════════════════════════════════════════════════
//
// SANDBOX  → para pruebas (no cobra tarjetas reales)
// LIVE     → producción (cobra tarjetas reales)
//
// SWIFTPAY_SANDBOX = true  → rutas /api/card/qa/...  (mismo dominio, path diferente)
// SWIFTPAY_SANDBOX = false → rutas /api/card/...
//
// Base URL: https://swiftportals.com  (igual para sandbox y live)
// JWT: token proporcionado por SwiftPay para cada ambiente
// ══════════════════════════════════════════════════════════════════════

if (!defined('SWIFTPAY_SANDBOX')) define('SWIFTPAY_SANDBOX', false); // producción activa

// ── Sandbox (QA) — misma URL base, rutas /api/card/qa/ ────────────────
if (!defined('SWIFTPAY_URL_SANDBOX')) define('SWIFTPAY_URL_SANDBOX', 'https://swiftportals.com');
if (!defined('SWIFTPAY_JWT_SANDBOX')) define('SWIFTPAY_JWT_SANDBOX', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJtZXJjaGFudF9pZCI6MTUsImlhdCI6MTc3MjA3NTE3OH0.O29R_kJ0T5xZWI8_9QiUU7eR6edouBYkwuXqYe6AZSM');

// ── Producción (Live) — misma URL base, rutas /api/card/ ──────────────
if (!defined('SWIFTPAY_URL_LIVE')) define('SWIFTPAY_URL_LIVE', 'https://swiftportals.com');
if (!defined('SWIFTPAY_JWT_LIVE')) define('SWIFTPAY_JWT_LIVE', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJtZXJjaGFudF9pZCI6NDgsImlhdCI6MTc3NTc2NzI4NH0.JboUI9XuULmMGSemgl4ZvabRZCcaKoDMD2Iv-MzV4gY');

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

/**
 * Genera un slug SEO-friendly a partir de un texto.
 * Uso: url_slug('Desarrollador React - San José') → 'desarrollador-react-san-jose'
 */
function url_slug(string $text, int $maxLen = 60): string {
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $t = preg_replace('/[^a-z0-9]+/', '-', strtolower($t));
    return substr(trim($t, '-'), 0, $maxLen);
}

/**
 * Devuelve la URL limpia de una publicación (empleo/servicio).
 * Uso: clean_url_publicacion($id, $title)
 */
function clean_url_publicacion(int $id, string $title): string {
    $s = url_slug($title);
    return '/publicacion/' . $id . ($s ? '-' . $s : '');
}

function clean_url_propiedad(int $id, string $title): string {
    $s = url_slug($title);
    return '/propiedad/' . $id . ($s ? '-' . $s : '');
}

function clean_url_tienda(int $id, string $title): string {
    $s = url_slug($title);
    return '/tienda/' . $id . ($s ? '-' . $s : '');
}

function clean_url_producto(int $id, string $name): string {
    $s = url_slug($name);
    return '/producto/' . $id . ($s ? '-' . $s : '');
}