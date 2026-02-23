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
    header('Location: index.php');
    exit;
}

// Obtener publicación
$pdo = db();
$publicacion = null;

try {
    $stmt = $pdo->prepare("
        SELECT
            jl.*,
            je.company_name,
            je.company_logo,
            je.name as provider_name,
            je.email as provider_email,
            je.phone as provider_phone,
            je.website as provider_website
        FROM job_listings jl
        INNER JOIN jobs_employers je ON je.id = jl.employer_id
        WHERE jl.id = ?
          AND jl.is_active = 1
          AND je.is_active = 1
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
    header('Location: index.php');
    exit;
}

$isJob = $publicacion['listing_type'] === 'job';
$backUrl = $isJob ? 'empleos.php' : 'ofertas-servicios.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($publicacion['title']); ?> | CompraTica</title>

  <meta name="description" content="<?php echo htmlspecialchars(substr($publicacion['description'], 0, 160)); ?>">
  <meta name="robots" content="index, follow">

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
      .container {
        padding: 1rem;
      }

      .detail-title {
        font-size: 1.75rem;
      }

      .detail-meta {
        flex-direction: column;
        gap: 0.75rem;
      }

      .contact-buttons {
        flex-direction: column;
      }

      .btn-contact {
        width: 100%;
        justify-content: center;
      }
    }
  </style>
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

      <h1 class="detail-title"><?php echo htmlspecialchars($publicacion['title']); ?></h1>

      <div class="detail-company">
        <?php if ($publicacion['company_logo']): ?>
          <img src="<?php echo htmlspecialchars($publicacion['company_logo']); ?>" alt="Logo" class="company-logo">
        <?php else: ?>
          <div class="company-logo" style="display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--gray-400);">
            <i class="fas fa-building"></i>
          </div>
        <?php endif; ?>

        <div class="company-info">
          <h3><?php echo htmlspecialchars($publicacion['company_name'] ?? $publicacion['provider_name'] ?? 'Empresa'); ?></h3>
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
      <?php if ($publicacion['description']): ?>
        <div class="detail-section">
          <h2>
            <i class="fas fa-align-left"></i>
            Descripción
          </h2>
          <p><?php echo nl2br(htmlspecialchars($publicacion['description'])); ?></p>
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

      <div class="contact-section">
        <h2>
          <i class="fas fa-envelope"></i>
          ¿Te interesa? Contactá directamente
        </h2>

        <div class="contact-buttons">
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

          <?php if ($publicacion['contact_email'] ?? $publicacion['provider_email']): ?>
            <a href="mailto:<?php echo htmlspecialchars($publicacion['contact_email'] ?? $publicacion['provider_email']); ?>?subject=Consulta: <?php echo urlencode($publicacion['title']); ?>" class="btn-contact email">
              <i class="fas fa-envelope"></i>
              Email
            </a>
          <?php endif; ?>

          <?php if ($publicacion['contact_phone']): ?>
            <a href="tel:<?php echo htmlspecialchars($publicacion['contact_phone']); ?>" class="btn-contact">
              <i class="fas fa-phone"></i>
              <?php echo htmlspecialchars($publicacion['contact_phone']); ?>
            </a>
          <?php endif; ?>

          <?php if ($publicacion['application_url']): ?>
            <a href="<?php echo htmlspecialchars($publicacion['application_url']); ?>" target="_blank" class="btn-contact">
              <i class="fas fa-external-link-alt"></i>
              Aplicar Aquí
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

</body>
</html>
