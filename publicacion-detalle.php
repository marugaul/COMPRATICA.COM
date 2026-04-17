<?php
/**
 * Página de Detalle de Publicación (Empleo o Servicio)
 * Muestra información completa de una publicación de job_listings
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

// Configuración de sesiones
$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
} else {
    ini_set('session.save_path', '/tmp');
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

// Detectar HTTPS
$__isHttps = false;
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') $__isHttps = true;
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') $__isHttps = true;
if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') $__isHttps = true;
if (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false) $__isHttps = true;

$host = $_SERVER['HTTP_HOST'] ?? parse_url(defined('BASE_URL') ? BASE_URL : '', PHP_URL_HOST) ?? '';
$cookieDomain = '';
if ($host && strpos($host, 'localhost') === false && !filter_var($host, FILTER_VALIDATE_IP)) {
    $clean = preg_replace('/^www\./i', '', $host);
    if (filter_var($clean, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        $cookieDomain = $clean;
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PHPSESSID');

    if (PHP_VERSION_ID < 70300) {
        session_set_cookie_params(0, '/', $cookieDomain, $__isHttps, true);
    } else {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => $cookieDomain,
            'secure'   => $__isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_start();
}

$isLoggedIn = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
$userName = $_SESSION['name'] ?? 'Usuario';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMESITE');
}

// Obtener ID de la publicación
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: /');
    exit;
}

/**
 * Decodifica TODAS las entidades HTML (nombradas y numéricas)
 */
function decodeAllEntities($text) {
    // Decodificar entidades nombradas y numéricas
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Decodificar entidades numéricas que quedaron (&#8211;, &#8217;, etc.)
    $text = preg_replace_callback('/&#(\d+);/', function($matches) {
        return mb_chr((int)$matches[1], 'UTF-8');
    }, $text);

    // Decodificar entidades hexadecimales (&#x2013;, etc.)
    $text = preg_replace_callback('/&#x([0-9a-f]+);/i', function($matches) {
        return mb_chr(hexdec($matches[1]), 'UTF-8');
    }, $text);

    return $text;
}

/**
 * Convierte URLs en texto a enlaces clickeables
 */
function makeUrlsClickable($text) {
    // Convertir URLs a enlaces
    $pattern = '/(https?:\/\/[^\s<>"\']+)/i';
    $replacement = '<a href="$1" target="_blank" style="color: var(--primary); text-decoration: underline; font-weight: 600;">$1</a>';
    return preg_replace($pattern, $replacement, $text);
}

/**
 * Extrae URLs de aplicación de un texto
 * Retorna array con 'url' y 'text' (texto sin el URL)
 */
function extractApplicationUrl($text) {
    $url = null;
    $cleanText = $text;

    // Buscar URLs completos (incluyendo parámetros, hash, etc.)
    // El patrón captura hasta encontrar un espacio, salto de línea, o fin de cadena
    if (preg_match('/(https?:\/\/[^\s]+)/i', $text, $matches)) {
        $url = $matches[1];

        // Limpiar posibles puntos o comas al final del URL
        $url = rtrim($url, '.,;:!?)');

        // Remover el URL del texto
        $cleanText = str_replace($matches[1], '', $cleanText);

        // Limpiar espacios múltiples resultantes
        $cleanText = preg_replace('/\s+/', ' ', $cleanText);
        $cleanText = trim($cleanText);
    }

    return [
        'url' => $url,
        'text' => $cleanText
    ];
}

/**
 * Formatea una descripción de empleo de manera profesional
 * Detecta secciones, listas, y formatea con HTML limpio
 * NO convierte URLs en enlaces (se manejan por separado)
 */
function formatJobDescription($text) {
    // Decodificar entidades HTML
    $text = decodeAllEntities($text);

    // Limpiar espacios excesivos
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    // Detectar secciones comunes (Job Description, Responsibilities, etc.)
    $sections = [
        'Job Description Summary' => '<h3><i class="fas fa-file-alt"></i> Resumen</h3>',
        'Job Description' => '<h3><i class="fas fa-briefcase"></i> Descripción del Puesto</h3>',
        'Roles and Responsibilities' => '<h3><i class="fas fa-tasks"></i> Roles y Responsabilidades</h3>',
        'Responsibilities' => '<h3><i class="fas fa-clipboard-list"></i> Responsabilidades</h3>',
        'Requirements' => '<h3><i class="fas fa-check-circle"></i> Requisitos</h3>',
        'Qualifications' => '<h3><i class="fas fa-graduation-cap"></i> Calificaciones</h3>',
        'Skills' => '<h3><i class="fas fa-star"></i> Habilidades</h3>',
        'Benefits' => '<h3><i class="fas fa-gift"></i> Beneficios</h3>',
        'About' => '<h3><i class="fas fa-info-circle"></i> Acerca de</h3>',
        'Essential Responsibilities' => '<h3><i class="fas fa-exclamation-circle"></i> Responsabilidades Esenciales</h3>',
        'Additional Information' => '<h3><i class="fas fa-info"></i> Información Adicional</h3>',
    ];

    foreach ($sections as $section => $html) {
        // Solo reemplazar la primera ocurrencia para evitar duplicados
        $text = preg_replace('/\b' . preg_quote($section, '/') . '\b/i', "\n\n" . $html . "\n", $text, 1);
    }

    // Dividir en párrafos por puntos seguidos o secciones
    $paragraphs = preg_split('/\.(?=\s+[A-Z]|\s*\n)|\n{2,}/', $text);

    $formatted = '';
    foreach ($paragraphs as $para) {
        $para = trim($para);
        if (empty($para)) continue;

        // Si es un título HTML, agregar directamente
        if (strpos($para, '<h3>') !== false) {
            $formatted .= $para;
            continue;
        }

        // Detectar listas (líneas que parecen items)
        $lines = explode('.', $para);
        $isList = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Si la línea empieza con verbo de acción o parece un item de lista
            if (preg_match('/^(Act|Manage|Responsible|Perform|Follow|Keep|Offer|Own|Maintain|Develop|Ensure|Support|Lead|Execute|Deliver|Collaborate)/i', $line)) {
                if (!$isList) {
                    $formatted .= '<ul class="professional-list">';
                    $isList = true;
                }
                // NO hacer URLs clickeables (ya fueron removidos)
                $formatted .= '<li><i class="fas fa-check-circle"></i> ' . htmlspecialchars($line) . '.</li>';
            } else {
                if ($isList) {
                    $formatted .= '</ul>';
                    $isList = false;
                }
                if (strlen($line) > 10) {
                    // NO hacer URLs clickeables (ya fueron removidos)
                    $formatted .= '<p>' . htmlspecialchars($line) . '.</p>';
                }
            }
        }

        if ($isList) {
            $formatted .= '</ul>';
        }
    }

    return $formatted;
}

// Obtener publicación
$pdo = db();
$publicacion = null;

try {
    $stmt = $pdo->prepare("
        SELECT
            jl.*,
            u.company_name,
            u.company_logo,
            u.name as provider_name,
            u.email as provider_email,
            u.phone as provider_phone,
            u.website as provider_website
        FROM job_listings jl
        INNER JOIN users u ON u.id = jl.employer_id
        WHERE jl.id = ?
          AND jl.is_active = 1
          AND u.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $publicacion = $stmt->fetch(PDO::FETCH_ASSOC);

    // Incrementar vistas
    if ($publicacion) {
        $pdo->exec("UPDATE job_listings SET views_count = views_count + 1 WHERE id = $id");
    }
} catch (Exception $e) {
    error_log("Error loading listing: " . $e->getMessage());
}

if (!$publicacion) {
    header('Location: /');
    exit;
}

// Redirigir URL antigua (?id=) a URL limpia (/publicacion/id-slug)
$cleanUrl = 'https://compratica.com' . clean_url_publicacion($id, $publicacion['title'] ?? '');
if (!str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/publicacion/')) {
    header('Location: ' . $cleanUrl, true, 301);
    exit;
}

// Extraer URL de aplicación de la descripción si existe
$extractedData = extractApplicationUrl($publicacion['description']);
$extractedUrl = $extractedData['url'];
$cleanDescription = $extractedData['text'];

// Si encontramos un URL en la descripción y no hay application_url, usarlo
if ($extractedUrl && empty($publicacion['application_url'])) {
    $publicacion['application_url'] = $extractedUrl;
}

// Usar la descripción limpia (sin URL) para mostrar
$publicacion['description'] = $cleanDescription;

$isJob = $publicacion['listing_type'] === 'job';
$backUrl = $isJob ? '/empleos' : '/ofertas-servicios';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($publicacion['title']); ?> en <?php echo htmlspecialchars($publicacion['company_name'] ?? 'Costa Rica'); ?> | CompraTica</title>

  <!-- SEO Meta Tags -->
  <meta name="description" content="<?php echo htmlspecialchars(substr(strip_tags($publicacion['description']), 0, 160)); ?>... Aplica ahora en CompraTica 🇨🇷">
  <meta name="keywords" content="<?php echo htmlspecialchars($publicacion['title']); ?>, empleo <?php echo htmlspecialchars($publicacion['category']); ?>, trabajo <?php echo htmlspecialchars($publicacion['location']); ?>, <?php echo htmlspecialchars($publicacion['company_name'] ?? ''); ?>">
  <meta name="robots" content="index, follow, max-image-preview:large">
  <meta name="author" content="<?php echo htmlspecialchars($publicacion['company_name'] ?? 'CompraTica'); ?>">
  <link rel="canonical" href="<?php echo htmlspecialchars($cleanUrl, ENT_QUOTES) ?>">

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="article">
  <meta property="og:url" content="<?php echo htmlspecialchars($cleanUrl, ENT_QUOTES) ?>">
  <meta property="og:title" content="<?php echo htmlspecialchars($publicacion['title']); ?> - <?php echo htmlspecialchars($publicacion['company_name'] ?? 'CompraTica'); ?>">
  <meta property="og:description" content="<?php echo htmlspecialchars(substr(strip_tags($publicacion['description']), 0, 200)); ?>">
  <?php if ($publicacion['company_logo']): ?>
  <meta property="og:image" content="<?php echo htmlspecialchars($publicacion['company_logo']); ?>">
  <?php else: ?>
  <meta property="og:image" content="https://compratica.com/assets/img/og-empleos.jpg">
  <?php endif; ?>
  <meta property="og:locale" content="es_CR">
  <meta property="og:site_name" content="CompraTica">
  <meta property="article:published_time" content="<?php echo date('c', strtotime($publicacion['created_at'])); ?>">
  <meta property="article:modified_time" content="<?php echo date('c', strtotime($publicacion['updated_at'] ?? $publicacion['created_at'])); ?>">
  <meta property="article:section" content="<?php echo htmlspecialchars($publicacion['category']); ?>">
  <meta property="article:tag" content="<?php echo $isJob ? 'Empleo' : 'Servicio'; ?>">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:url" content="<?php echo htmlspecialchars($cleanUrl, ENT_QUOTES) ?>">
  <meta name="twitter:title" content="<?php echo htmlspecialchars($publicacion['title']); ?>">
  <meta name="twitter:description" content="<?php echo htmlspecialchars(substr(strip_tags($publicacion['description']), 0, 200)); ?>">
  <?php if ($publicacion['company_logo']): ?>
  <meta name="twitter:image" content="<?php echo htmlspecialchars($publicacion['company_logo']); ?>">
  <?php else: ?>
  <meta name="twitter:image" content="https://compratica.com/assets/img/og-empleos.jpg">
  <?php endif; ?>

  <!-- Geo Tags -->
  <meta name="geo.region" content="CR">
  <meta name="geo.placename" content="<?php echo htmlspecialchars($publicacion['location'] ?? 'Costa Rica'); ?>">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Color+Emoji&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/main.css">

  <style>
    :root {
      --cr-azul: #002b7f;
      --primary: #1a73e8;
      --primary-dark: #1557b0;
      --accent: #10b981;
      --warning: #f59e0b;
      --white: #ffffff;
      --gray-50: #f9fafb;
      --gray-100: #f3f4f6;
      --gray-200: #e5e7eb;
      --gray-300: #d1d5db;
      --gray-500: #6b7280;
      --gray-600: #4b5563;
      --gray-700: #374151;
      --gray-900: #111827;
      --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
      --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
      --radius: 12px;
      --radius-lg: 16px;
      --radius-xl: 24px;
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: "Inter", sans-serif;
      background: var(--gray-50);
      color: var(--gray-900);
      line-height: 1.6;
    }

    a {
      text-decoration: none;
      color: inherit;
      transition: var(--transition);
    }

    .header {
      background: var(--white);
      padding: 1.25rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 3px solid var(--cr-azul);
      box-shadow: 0 4px 12px rgba(0, 43, 127, 0.1);
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .logo .flag {
      font-size: 2rem;
    }

    .logo .text .main {
      color: var(--cr-azul);
      font-weight: 800;
      font-size: 1.5rem;
    }

    .logo .text .sub {
      color: var(--primary);
      font-size: 0.7rem;
      font-weight: 600;
      letter-spacing: 0.15em;
      text-transform: uppercase;
    }

    .back-button {
      padding: 0.75rem 1.5rem;
      background: var(--gray-100);
      color: var(--gray-700);
      border-radius: var(--radius);
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      transition: var(--transition);
    }

    .back-button:hover {
      background: var(--gray-200);
    }

    .container {
      max-width: 1000px;
      margin: 0 auto;
      padding: 2rem;
    }

    .detail-card {
      background: var(--white);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-lg);
      overflow: hidden;
    }

    .detail-header {
      padding: 2rem;
      border-bottom: 2px solid var(--gray-100);
    }

    .detail-title {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 1rem;
      color: var(--gray-900);
    }

    .detail-company {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .company-logo {
      width: 60px;
      height: 60px;
      border-radius: var(--radius);
      object-fit: cover;
      background: var(--gray-100);
    }

    .company-info h3 {
      font-size: 1.25rem;
      color: var(--gray-900);
    }

    .company-info p {
      color: var(--gray-600);
      font-size: 0.95rem;
    }

    .detail-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 1.5rem;
      font-size: 1rem;
      color: var(--gray-700);
    }

    .meta-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .meta-item i {
      color: var(--primary);
    }

    .detail-body {
      padding: 2rem;
    }

    .detail-body-layout {
      display: grid;
      grid-template-columns: 1fr 280px;
      gap: 2rem;
      align-items: start;
    }

    .detail-flyer-sidebar {
      position: sticky;
      top: 1rem;
    }

    .detail-flyer-sidebar img {
      width: 100%;
      border-radius: var(--radius);
      box-shadow: var(--shadow-md);
    }

    .detail-flyer-label {
      text-align: center;
      font-size: 0.8rem;
      color: var(--gray-500);
      margin-top: 0.5rem;
    }

    @media (max-width: 768px) {
      .detail-body-layout {
        grid-template-columns: 1fr;
      }
      .detail-flyer-sidebar {
        order: -1;
        position: static;
      }
    }

    /* Descripción formateada profesionalmente */
    .formatted-description {
      line-height: 1.8;
      color: var(--gray-800);
    }

    .formatted-description h3 {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--primary);
      margin: 2rem 0 1rem 0;
      padding-bottom: 0.5rem;
      border-bottom: 2px solid var(--gray-100);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .formatted-description h3 i {
      font-size: 1.1rem;
    }

    .formatted-description p {
      margin-bottom: 1rem;
      text-align: justify;
    }

    .formatted-description ul.professional-list {
      list-style: none;
      padding: 0;
      margin: 1rem 0 1.5rem 0;
    }

    .formatted-description ul.professional-list li {
      padding: 0.75rem 0;
      padding-left: 2rem;
      position: relative;
      border-bottom: 1px solid var(--gray-100);
      transition: var(--transition);
    }

    .formatted-description ul.professional-list li:hover {
      background: var(--gray-50);
      padding-left: 2.25rem;
    }

    .formatted-description ul.professional-list li:last-child {
      border-bottom: none;
    }

    .formatted-description ul.professional-list li i {
      position: absolute;
      left: 0;
      top: 0.85rem;
      color: var(--accent);
      font-size: 0.9rem;
    }

    .btn-translate-detail {
      padding: 0.625rem 1.25rem;
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: var(--white);
      border: none;
      border-radius: var(--radius);
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.9rem;
    }

    .btn-translate-detail:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .btn-translate-detail.translating {
      opacity: 0.6;
      pointer-events: none;
    }

    .detail-section {
      margin-bottom: 2rem;
    }

    .detail-section h2 {
      font-size: 1.5rem;
      margin-bottom: 1rem;
      color: var(--gray-900);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .detail-section h2 i {
      color: var(--primary);
    }

    .detail-section p {
      color: var(--gray-700);
      line-height: 1.8;
      white-space: pre-wrap;
    }

    .detail-section ul {
      list-style: none;
      padding: 0;
    }

    .detail-section li {
      padding: 0.75rem;
      margin-bottom: 0.5rem;
      background: var(--gray-50);
      border-radius: var(--radius);
      display: flex;
      align-items: start;
      gap: 0.75rem;
    }

    .detail-section li i {
      color: var(--accent);
      margin-top: 0.25rem;
    }

    .image-gallery {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 1rem;
      margin-top: 1rem;
    }

    .gallery-image {
      width: 100%;
      height: 200px;
      object-fit: cover;
      border-radius: var(--radius);
      cursor: pointer;
      transition: var(--transition);
    }

    .gallery-image:hover {
      transform: scale(1.05);
      box-shadow: var(--shadow-md);
    }

    .contact-section {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      padding: 2rem;
      border-radius: var(--radius-lg);
      color: var(--white);
      margin-top: 2rem;
    }

    .contact-section h2 {
      font-size: 1.75rem;
      margin-bottom: 1.5rem;
    }

    .contact-buttons {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .btn-contact {
      padding: 1rem 2rem;
      background: var(--white);
      color: var(--primary);
      border-radius: var(--radius);
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 0.75rem;
      transition: var(--transition);
      border: none;
      cursor: pointer;
    }

    .btn-contact:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .btn-contact.whatsapp {
      background: #25d366;
      color: var(--white);
    }

    .btn-contact.email {
      background: var(--white);
      color: var(--gray-900);
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      border-radius: 50px;
      font-size: 0.9rem;
      font-weight: 600;
    }

    .badge.featured {
      background: var(--warning);
      color: var(--white);
    }

    .price-tag {
      font-size: 2rem;
      font-weight: 700;
      color: var(--accent);
      margin: 1rem 0;
    }

    @media (max-width: 768px) {
      .header {
        padding: 1rem;
        flex-wrap: wrap;
      }

      .logo .flag {
        font-size: 1.5rem;
      }

      .logo .text .main {
        font-size: 1.25rem;
      }

      .logo .text .sub {
        font-size: 0.75rem;
      }

      .back-button {
        padding: 0.625rem 1rem;
        font-size: 0.875rem;
      }

      .container {
        padding: 1rem;
        max-width: 100%;
      }

      .detail-card {
        padding: 1.5rem;
      }

      .detail-title {
        font-size: 1.5rem;
        line-height: 1.3;
      }

      .detail-meta {
        flex-direction: column;
        gap: 0.75rem;
        font-size: 0.875rem;
      }

      .detail-description {
        font-size: 0.9375rem;
        padding: 1rem;
      }

      .contact-buttons {
        flex-direction: column;
        gap: 0.75rem;
      }

      .btn-contact {
        width: 100%;
        justify-content: center;
        padding: 0.875rem;
      }

      .translate-btn {
        width: 100%;
        padding: 0.875rem;
      }

      .professional-list li {
        font-size: 0.9375rem;
        padding: 0.625rem 0;
      }

      h3 {
        font-size: 1.125rem;
      }
    }

    @media (max-width: 480px) {
      .header {
        padding: 0.75rem;
      }

      .logo .flag {
        font-size: 1.25rem;
      }

      .logo .text .main {
        font-size: 1.125rem;
      }

      .back-button {
        padding: 0.5rem 0.875rem;
        font-size: 0.8125rem;
      }

      .container {
        padding: 0.75rem;
      }

      .detail-card {
        padding: 1rem;
      }

      .detail-title {
        font-size: 1.25rem;
      }

      .detail-meta {
        font-size: 0.8125rem;
      }

      .detail-description {
        font-size: 0.875rem;
        padding: 0.875rem;
      }

      .btn-contact, .translate-btn {
        padding: 0.75rem;
        font-size: 0.9375rem;
      }

      .professional-list li {
        font-size: 0.875rem;
      }

      h3 {
        font-size: 1rem;
      }
    }
  </style>

  <!-- Schema.org JSON-LD para JobPosting (Google for Jobs) -->
  <?php if ($isJob): ?>
  <script type="application/ld+json">
  {
    "@context": "https://schema.org/",
    "@type": "JobPosting",
    "title": "<?php echo addslashes(htmlspecialchars_decode($publicacion['title'])); ?>",
    "description": "<?php echo addslashes(strip_tags(htmlspecialchars_decode($publicacion['description']))); ?>",
    "identifier": {
      "@type": "PropertyValue",
      "name": "<?php echo htmlspecialchars($publicacion['company_name'] ?? 'CompraTica'); ?>",
      "value": "<?php echo $id; ?>"
    },
    "datePosted": "<?php echo date('c', strtotime($publicacion['created_at'])); ?>",
    <?php if ($publicacion['end_date']): ?>
    "validThrough": "<?php echo date('c', strtotime($publicacion['end_date'])); ?>",
    <?php endif; ?>
    "employmentType": "<?php
      $typeMap = [
        'full-time' => 'FULL_TIME',
        'part-time' => 'PART_TIME',
        'contract' => 'CONTRACTOR',
        'freelance' => 'CONTRACTOR',
        'internship' => 'INTERN'
      ];
      echo $typeMap[$publicacion['job_type']] ?? 'FULL_TIME';
    ?>",
    "hiringOrganization": {
      "@type": "Organization",
      "name": "<?php echo addslashes(htmlspecialchars_decode($publicacion['company_name'] ?? 'CompraTica')); ?>",
      <?php if ($publicacion['company_logo']): ?>
      "logo": "<?php echo htmlspecialchars($publicacion['company_logo']); ?>",
      <?php endif; ?>
      <?php if ($publicacion['provider_website']): ?>
      "sameAs": "<?php echo htmlspecialchars($publicacion['provider_website']); ?>",
      <?php endif; ?>
      "url": "https://compratica.com"
    },
    "jobLocation": {
      "@type": "Place",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "<?php echo addslashes(htmlspecialchars_decode($publicacion['location'] ?? 'Costa Rica')); ?>",
        "addressLocality": "<?php echo addslashes(htmlspecialchars_decode($publicacion['location'] ?? 'San José')); ?>",
        "addressRegion": "<?php echo htmlspecialchars($publicacion['province'] ?? 'San José'); ?>",
        "addressCountry": "CR"
      }
    },
    <?php if ($publicacion['salary_min'] && $publicacion['salary_max']): ?>
    "baseSalary": {
      "@type": "MonetaryAmount",
      "currency": "<?php echo $publicacion['salary_currency'] === 'USD' ? 'USD' : 'CRC'; ?>",
      "value": {
        "@type": "QuantitativeValue",
        "minValue": <?php echo $publicacion['salary_min']; ?>,
        "maxValue": <?php echo $publicacion['salary_max']; ?>,
        "unitText": "<?php
          $periodMap = [
            'hour' => 'HOUR',
            'day' => 'DAY',
            'week' => 'WEEK',
            'month' => 'MONTH',
            'year' => 'YEAR'
          ];
          echo $periodMap[$publicacion['salary_period']] ?? 'MONTH';
        ?>"
      }
    },
    <?php endif; ?>
    <?php if ($publicacion['remote_allowed']): ?>
    "jobLocationType": "TELECOMMUTE",
    <?php endif; ?>
    <?php if ($publicacion['application_url']): ?>
    "directApply": true,
    "applicationContact": {
      "@type": "ContactPoint",
      "url": "<?php echo htmlspecialchars($publicacion['application_url']); ?>"
    },
    <?php endif; ?>
    "url": "https://compratica.com/publicacion-detalle.php?id=<?php echo $id; ?>"
  }
  </script>
  <?php endif; ?>
</head>
<body>

<header class="header">
  <a href="index" class="logo">
    <span class="flag">🇨🇷</span>
    <div class="text">
      <span class="main">CompraTica</span>
      <span class="sub"><?php echo $isJob ? 'Empleos' : 'Servicios'; ?></span>
    </div>
  </a>
  <a href="<?php echo $backUrl; ?>" class="back-button">
    <i class="fas fa-arrow-left"></i>
    Volver
  </a>
</header>

<div class="container">
  <div class="detail-card">
    <div class="detail-header">
      <?php if ($publicacion['is_featured']): ?>
        <div style="margin-bottom: 1rem;">
          <span class="badge featured">
            <i class="fas fa-star"></i>
            Destacado
          </span>
        </div>
      <?php endif; ?>

      <h1 class="detail-title"><?php echo htmlspecialchars(decodeAllEntities($publicacion['title'])); ?></h1>

      <div class="detail-company">
        <?php if ($publicacion['company_logo']): ?>
          <img src="<?php echo htmlspecialchars($publicacion['company_logo']); ?>" alt="Logo" class="company-logo">
        <?php else: ?>
          <div class="company-logo" style="display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--gray-400);">
            <i class="fas fa-building"></i>
          </div>
        <?php endif; ?>

        <div class="company-info">
          <h3><?php echo htmlspecialchars(decodeAllEntities($publicacion['company_name'] ?? $publicacion['provider_name'] ?? 'Empresa')); ?></h3>
          <?php if ($publicacion['provider_website']): ?>
            <p><a href="<?php echo htmlspecialchars($publicacion['provider_website']); ?>" target="_blank" style="color: var(--primary);">
              <i class="fas fa-globe"></i> <?php echo htmlspecialchars($publicacion['provider_website']); ?>
            </a></p>
          <?php endif; ?>
        </div>
      </div>

      <div class="detail-meta">
        <?php if ($publicacion['category']): ?>
          <div class="meta-item">
            <i class="fas fa-tag"></i>
            <strong><?php echo htmlspecialchars($publicacion['category']); ?></strong>
          </div>
        <?php endif; ?>

        <?php if ($publicacion['location']): ?>
          <div class="meta-item">
            <i class="fas fa-map-marker-alt"></i>
            <?php echo htmlspecialchars($publicacion['location']); ?>
          </div>
        <?php endif; ?>

        <?php if ($isJob && $publicacion['job_type']): ?>
          <div class="meta-item">
            <i class="fas fa-clock"></i>
            <?php
            $jobTypes = [
              'full-time' => 'Tiempo Completo',
              'part-time' => 'Medio Tiempo',
              'freelance' => 'Freelance',
              'contract' => 'Contrato',
              'internship' => 'Pasantía'
            ];
            echo $jobTypes[$publicacion['job_type']] ?? $publicacion['job_type'];
            ?>
          </div>
        <?php endif; ?>

        <?php if ($publicacion['remote_allowed']): ?>
          <div class="meta-item">
            <i class="fas fa-laptop-house"></i>
            Remoto
          </div>
        <?php endif; ?>

        <div class="meta-item">
          <i class="fas fa-eye"></i>
          <?php echo $publicacion['views_count'] ?? 0; ?> vistas
        </div>
      </div>

      <?php if ($isJob && $publicacion['salary_min'] && $publicacion['salary_max']): ?>
        <div class="price-tag">
          <?php
          $currency = $publicacion['salary_currency'] === 'USD' ? '$' : '₡';
          echo $currency . number_format($publicacion['salary_min']) . ' - ' . $currency . number_format($publicacion['salary_max']);
          if ($publicacion['salary_period']) {
            $periods = [
              'hour' => '/hora',
              'day' => '/día',
              'week' => '/sem',
              'month' => '/mes',
              'year' => '/año'
            ];
            echo $periods[$publicacion['salary_period']] ?? '';
          }
          ?>
        </div>
      <?php elseif (!$isJob && $publicacion['service_price']): ?>
        <div class="price-tag">
          <?php
          $currency = $publicacion['salary_currency'] === 'USD' ? '$' : '₡';
          echo $currency . number_format($publicacion['service_price']);
          if ($publicacion['service_price_type']) {
            $types = [
              'fixed' => '',
              'hourly' => '/hora',
              'daily' => '/día',
              'negotiable' => ' (negociable)'
            ];
            echo $types[$publicacion['service_price_type']] ?? '';
          }
          ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="detail-body">
    <?php $hasFlyerSidebar = !empty($publicacion['flyer_image']); ?>
    <?php if ($hasFlyerSidebar): ?>
    <div class="detail-body-layout">
    <div class="detail-main">
    <?php endif; ?>
      <?php if ($publicacion['description']): ?>
        <div class="detail-section">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="margin: 0;">
              <i class="fas fa-align-left"></i>
              Descripción
            </h2>
            <?php if ($publicacion['import_source']): ?>
              <button class="btn-translate-detail" onclick="translateDescription()">
                <i class="fas fa-language"></i>
                <span id="translate-text">Traducir a Español</span>
              </button>
            <?php endif; ?>
          </div>
          <div id="job-description" class="formatted-description">
            <?php echo formatJobDescription($publicacion['description']); ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($publicacion['requirements']): ?>
        <div class="detail-section">
          <h2>
            <i class="fas fa-check-circle"></i>
            <?php echo $isJob ? 'Requisitos' : 'Qué incluye'; ?>
          </h2>
          <ul>
            <?php
            $requirements = explode("\n", $publicacion['requirements']);
            foreach ($requirements as $req) {
              $req = trim($req);
              if ($req) {
                echo '<li><i class="fas fa-check"></i> ' . htmlspecialchars($req) . '</li>';
              }
            }
            ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($publicacion['benefits']): ?>
        <div class="detail-section">
          <h2>
            <i class="fas fa-star"></i>
            <?php echo $isJob ? 'Beneficios' : 'Ventajas'; ?>
          </h2>
          <ul>
            <?php
            $benefits = explode("\n", $publicacion['benefits']);
            foreach ($benefits as $benefit) {
              $benefit = trim($benefit);
              if ($benefit) {
                echo '<li><i class="fas fa-gift"></i> ' . htmlspecialchars($benefit) . '</li>';
              }
            }
            ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php
      $images = array_filter([
        $publicacion['image_1'],
        $publicacion['image_2'],
        $publicacion['image_3'],
        $publicacion['image_4'],
        $publicacion['image_5']
      ]);

      if (!empty($images)):
      ?>
        <div class="detail-section">
          <h2>
            <i class="fas fa-images"></i>
            Imágenes
          </h2>
          <div class="image-gallery">
            <?php foreach ($images as $image): ?>
              <img src="<?php echo htmlspecialchars($image); ?>" alt="Imagen" class="gallery-image" onclick="window.open(this.src, '_blank')">
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

    <?php if ($hasFlyerSidebar): ?>
    </div><!-- .detail-main -->
    <aside class="detail-flyer-sidebar">
      <img src="<?php echo htmlspecialchars($publicacion['flyer_image']); ?>" alt="Flyer promocional de <?php echo htmlspecialchars($publicacion['title']); ?>">
      <p class="detail-flyer-label"><i class="fas fa-image"></i> Flyer Promocional</p>
    </aside>
    </div><!-- .detail-body-layout -->
    <?php endif; ?>

      <div class="contact-section">
        <h2>
          <i class="fas fa-envelope"></i>
          <?php echo $isJob ? '¿Te interesa? Aplicá ahora' : '¿Te interesa? Contactá directamente'; ?>
        </h2>

        <div class="contact-buttons">
          <?php if ($isJob): ?>
            <?php
            // Para empleos, botón principal de "Aplicar"
            if ($publicacion['application_url']) {
              // Si hay URL de aplicación externa
              $applyUrl = htmlspecialchars($publicacion['application_url']);
              $applyTarget = '_blank';
            } else {
              // Si no hay URL, usar email para aplicar
              $applyEmail = $publicacion['contact_email'] ?? $publicacion['provider_email'];
              $applyUrl = 'mailto:' . htmlspecialchars($applyEmail) . '?subject=Aplicación: ' . urlencode($publicacion['title']) . '&body=' . urlencode("Estimados,\n\nMe interesa aplicar para el puesto de " . $publicacion['title'] . ".\n\nAdjunto mi información de contacto para coordinar una entrevista.\n\nSaludos.");
              $applyTarget = '_self';
            }
            ?>
            <a href="<?php echo $applyUrl; ?>" target="<?php echo $applyTarget; ?>" class="btn-contact" style="background: var(--accent); color: var(--white); font-size: 1.1rem; padding: 1.25rem 2.5rem; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
              <i class="fas fa-paper-plane"></i>
              Aplicar Ahora
            </a>
          <?php endif; ?>

          <?php if ($publicacion['contact_whatsapp'] ?? $publicacion['provider_phone']): ?>
            <?php
            $whatsapp = $publicacion['contact_whatsapp'] ?? $publicacion['provider_phone'];
            $whatsapp = preg_replace('/[^0-9]/', '', $whatsapp);
            $message = urlencode("Hola! Me interesa: " . $publicacion['title']);
            ?>
            <a href="https://wa.me/506<?php echo $whatsapp; ?>?text=<?php echo $message; ?>" target="_blank" class="btn-contact whatsapp">
              <i class="fab fa-whatsapp"></i>
              WhatsApp
            </a>
          <?php endif; ?>

          <?php if ($publicacion['contact_email']): ?>
            <a href="mailto:<?php echo htmlspecialchars($publicacion['contact_email']); ?>?subject=Consulta: <?php echo urlencode($publicacion['title']); ?>" class="btn-contact email">
              <i class="fas fa-envelope"></i>
              <?php echo $isJob ? 'Consultar' : 'Email'; ?>
            </a>
          <?php endif; ?>

          <?php if ($publicacion['contact_phone']): ?>
            <a href="tel:<?php echo htmlspecialchars($publicacion['contact_phone']); ?>" class="btn-contact">
              <i class="fas fa-phone"></i>
              <?php echo htmlspecialchars($publicacion['contact_phone']); ?>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Traducción de la descripción completa
let isTranslated = false;
let originalDescription = '';

async function translateDescription() {
  const button = document.querySelector('.btn-translate-detail');
  const textSpan = document.getElementById('translate-text');
  const descDiv = document.getElementById('job-description');

  if (!button || !descDiv) return;

  // Si ya está traducido, volver al original
  if (isTranslated) {
    descDiv.innerHTML = originalDescription;
    textSpan.textContent = 'Traducir a Español';
    isTranslated = false;
    return;
  }

  // Guardar original
  originalDescription = descDiv.innerHTML;

  // Mostrar estado de carga
  button.classList.add('translating');
  textSpan.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Traduciendo...';

  try {
    // Crear una copia temporal del DOM para trabajar
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = originalDescription;

    // Marcar los títulos (h3) para NO traducirlos porque ya están en español
    const headers = tempDiv.querySelectorAll('h3');
    headers.forEach(h3 => {
      h3.setAttribute('data-no-translate', 'true');
    });

    // Obtener solo el texto que NO está en títulos
    const textParts = [];
    const walker = document.createTreeWalker(tempDiv, NodeFilter.SHOW_TEXT, null, false);

    while (walker.nextNode()) {
      const node = walker.currentNode;
      // Solo incluir texto que NO está dentro de un h3
      let isInHeader = false;
      let parent = node.parentElement;
      while (parent) {
        if (parent.tagName === 'H3') {
          isInHeader = true;
          break;
        }
        parent = parent.parentElement;
      }

      if (!isInHeader && node.textContent.trim()) {
        textParts.push({
          node: node,
          text: node.textContent
        });
      }
    }

    // Concatenar todo el texto a traducir (sin títulos)
    const textToTranslate = textParts.map(p => p.text).join(' ');

    // Traducir en chunks de 5000 caracteres
    const chunkSize = 5000;
    const chunks = [];
    for (let i = 0; i < textToTranslate.length; i += chunkSize) {
      chunks.push(textToTranslate.substring(i, i + chunkSize));
    }

    let translatedText = '';
    for (const chunk of chunks) {
      const res = await fetch('/api/translate.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `text=${encodeURIComponent(chunk)}&from=auto&to=es`
      });
      const data = await res.json();

      if (data.error) {
        throw new Error(data.error);
      }

      translatedText += data.translated || chunk;
    }

    // Normalizar el texto traducido completo primero (eliminar saltos de línea innecesarios)
    translatedText = translatedText.replace(/\n+/g, ' ').replace(/\s+/g, ' ').trim();

    // Distribuir el texto traducido de vuelta a los nodos originales (sin los títulos)
    let translated = translatedText;
    textParts.forEach(part => {
      const originalLength = part.text.trim().length;
      if (originalLength > 0 && translated.length > 0) {
        const portion = translated.substring(0, Math.min(originalLength * 1.5, translated.length));
        part.node.textContent = portion;
        translated = translated.substring(portion.length).trim();
      }
    });

    descDiv.innerHTML = tempDiv.innerHTML;

    // Cambiar botón
    textSpan.textContent = 'Ver Original';
    button.classList.remove('translating');
    isTranslated = true;

  } catch (error) {
    console.error('Error traduciendo:', error);
    alert('Error al traducir. Por favor intenta de nuevo.');
    button.classList.remove('translating');
    textSpan.textContent = 'Traducir a Español';
  }
}

// Animación para los iconos de check en las listas
document.addEventListener('DOMContentLoaded', function() {
  const listItems = document.querySelectorAll('.professional-list li');
  listItems.forEach((item, index) => {
    item.style.opacity = '0';
    item.style.transform = 'translateX(-20px)';
    setTimeout(() => {
      item.style.transition = 'all 0.3s ease';
      item.style.opacity = '1';
      item.style.transform = 'translateX(0)';
    }, index * 50);
  });
});
</script>

</body>
</html>
