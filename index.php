<?php
// AUTO-DEPLOY TEST - Last update: 2025-11-16 (Git auto-sync enabled)
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

// ============= LOG DE DEBUG =============
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$logFile = $logDir . '/index_debug.log';

function logDebug($msg, $data = null) {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($data) $line .= ' | ' . json_encode($data);
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

logDebug("INDEX_START", ['uri' => $_SERVER['REQUEST_URI'] ?? '']);

// ============= CONFIGURACIÓN DE SESIONES =============
$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
} else {
    // Fallback a /tmp si no se puede escribir en sessions
    ini_set('session.save_path', '/tmp');
}

logDebug("SESSION_PATH_SET", ['path' => $__sessPath]);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

// Detectar HTTPS
$__isHttps = false;
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') $__isHttps = true;
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') $__isHttps = true;
if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') $__isHttps = true;
if (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"') !== false) $__isHttps = true;

// Dominio de cookie
$host = $_SERVER['HTTP_HOST'] ?? parse_url(defined('BASE_URL') ? BASE_URL : '', PHP_URL_HOST) ?? '';
$cookieDomain = '';
if ($host && strpos($host, 'localhost') === false && !filter_var($host, FILTER_VALIDATE_IP)) {
    $clean = preg_replace('/^www\./i', '', $host);
    if (filter_var($clean, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        $cookieDomain = $clean;
    }
}

logDebug("BEFORE_SESSION_START", [
    'session_status' => session_status(),
    'cookies' => $_COOKIE,
    'cookie_domain' => $cookieDomain,
    'host' => $host
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PHPSESSID');
    
    if (PHP_VERSION_ID < 70300) {
        ini_set('session.cookie_lifetime', '0');
        ini_set('session.cookie_path', '/');
        ini_set('session.cookie_domain', $cookieDomain);
        ini_set('session.cookie_secure', $__isHttps ? '1' : '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
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
    
    ini_set('session.use_strict_mode', '0');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '86400');
    session_start();
}

logDebug("AFTER_SESSION_START", [
    'sid' => session_id(),
    'session_data' => $_SESSION,
    'cookie_domain' => $cookieDomain
]);

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cantidadProductos = 0;
foreach ($_SESSION['cart'] as $it) { $cantidadProductos += (int)($it['qty'] ?? 0); }

// Verificar si el usuario está logueado
$isLoggedIn = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
$userName = $_SESSION['name'] ?? 'Usuario';

logDebug("USER_CHECK", [
    'isLoggedIn' => $isLoggedIn,
    'uid' => $_SESSION['uid'] ?? null,
    'userName' => $userName
]);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMESITE');
}
ini_set('default_charset', 'UTF-8');

// CSRF por cookie
$token = $_COOKIE['vg_csrf'] ?? bin2hex(random_bytes(32));
$isHttps = $__isHttps;
if (PHP_VERSION_ID < 70300) {
    setcookie('vg_csrf', $token, time()+7200, '/', $cookieDomain, $isHttps, false);
} else {
    setcookie('vg_csrf', $token, [
        'expires'  => time()+7200,
        'path'     => '/',
        'domain'   => $cookieDomain,
        'secure'   => $isHttps,
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
}

logDebug("RENDERING_PAGE");

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="google-site-verification" content="AdeaSmtzSb9lvmOfwaFt9QyFq5VzvkR8RdLaG_KGM5s" />
  <meta name="description" content="CompraTica es el marketplace de Costa Rica. Venta de garaje, empleos, bienes raíces, servicios profesionales y emprendedoras ticas. Compra y vende con pago seguro por SINPE.">
  <meta name="keywords" content="compratica, marketplace costa rica, venta de garaje costa rica, empleos costa rica, bienes raices costa rica, servicios profesionales costa rica, emprendedoras costa rica, compra venta usados cr, sinpe costa rica">
  <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
  <meta name="author" content="CompraTica">
  <link rel="canonical" href="https://compratica.com/">
  <title>CompraTica — Venta de Garaje, Empleos, Servicios y Bienes Raíces en Costa Rica</title>

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://compratica.com/">
  <meta property="og:title" content="CompraTica — Marketplace de Costa Rica">
  <meta property="og:description" content="Venta de garaje, empleos, bienes raíces, servicios profesionales y emprendedoras ticas. ¡El marketplace #1 de Costa Rica!">
  <meta property="og:image" content="https://compratica.com/logo.png">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:locale" content="es_CR">
  <meta property="og:site_name" content="CompraTica">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:url" content="https://compratica.com/">
  <meta name="twitter:title" content="CompraTica — Marketplace de Costa Rica">
  <meta name="twitter:description" content="Venta de garaje, empleos, bienes raíces y servicios profesionales en Costa Rica. ¡Compra y vende fácil!">
  <meta name="twitter:image" content="https://compratica.com/logo.png">

  <!-- Geo Tags -->
  <meta name="geo.region" content="CR">
  <meta name="geo.placename" content="Costa Rica">
  <meta name="geo.position" content="9.7489;-83.7534">
  <meta name="ICBM" content="9.7489, -83.7534">

  <!-- CSS crítico primero -->
  <link rel="stylesheet" href="/assets/css/main.css">

  <!-- Fuentes optimizadas con display=swap (no bloqueantes) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">

  <!-- Font Awesome cargado de forma asíncrona -->
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css" media="print" onload="this.media='all'">

  <!-- Fallback para navegadores sin JS -->
  <noscript>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  </noscript>

  <!-- Favicon - Bandera de Costa Rica 🇨🇷 -->
  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link rel="shortcut icon" href="/favicon.ico">

  <!-- Schema.org JSON-LD para SEO -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "Compratica",
    "alternateName": "CompraTica Costa Rica",
    "url": "https://compratica.com",
    "description": "Marketplace de Costa Rica. Compra y vende productos online con SINPE QR.",
    "potentialAction": {
      "@type": "SearchAction",
      "target": "https://compratica.com/search?q={search_term_string}",
      "query-input": "required name=search_term_string"
    }
  }
  </script>
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "Compratica",
    "url": "https://compratica.com",
    "logo": "https://compratica.com/logo.png",
    "sameAs": [],
    "contactPoint": {
      "@type": "ContactPoint",
      "contactType": "customer service",
      "availableLanguage": ["Spanish"]
    },
    "areaServed": {
      "@type": "Country",
      "name": "Costa Rica"
    }
  }
  </script>

</head>
<body>

<!-- HEADER -->
<header class="header">
  <a href="index" class="logo">
    <span class="flag emoji">🇨🇷</span>
    <div class="text">
      <span class="main">CompraTica</span>
      <span class="sub">100% COSTARRICENSE</span>
    </div>
  </a>
  <nav class="header-nav">
    <button id="cartButton" class="btn-icon" title="Carrito" aria-label="Ver carrito">
      <i class="fas fa-shopping-cart"></i>
      <span id="cartBadge" class="cart-badge" style="display:none">0</span>
    </button>

    <button id="menuButton" class="btn-icon" title="Menú" aria-label="Abrir menú">
      <i class="fas fa-bars"></i>
    </button>
  </nav>

  <!-- Popover del carrito -->
  <div id="cart-popover">
    <div class="cart-popover-header">
      <i class="fas fa-shopping-cart"></i> Tu Carrito
    </div>
    
    <div class="cart-popover-body">
      <div id="cart-empty" style="display:none">
        <p>Tu carrito está vacío</p>
      </div>
      <div id="cart-items"></div>
    </div>
    
    <div class="cart-popover-footer">
      <div class="cart-popover-total">
        <span>Total:</span>
        <span id="cart-total">₡0</span>
      </div>
      <div class="cart-popover-actions">
        <a href="cart" class="cart-popover-btn secondary">
          Ver carrito
        </a>
        <a href="checkout" id="checkoutBtn" class="cart-popover-btn primary">
          Pagar
        </a>
      </div>
    </div>
  </div>
</header>

<!-- Overlay del menú -->
<div id="menu-overlay"></div>

<!-- Menú hamburguesa -->
<aside id="hamburger-menu">
  <button class="menu-close" id="menu-close" aria-label="Cerrar menú">
    <i class="fas fa-times"></i>
  </button>

  <div class="menu-header">
    <?php if ($isLoggedIn): ?>
      <div class="menu-user">
        <div class="menu-user-avatar">
          <?php echo strtoupper(substr($userName, 0, 1)); ?>
        </div>
        <div class="menu-user-info">
          <h3><?php echo htmlspecialchars($userName); ?></h3>
          <p>Bienvenido de nuevo</p>
        </div>
      </div>
    <?php else: ?>
      <div class="menu-user">
        <div class="menu-user-avatar">
          <i class="fas fa-user"></i>
        </div>
        <div class="menu-user-info">
          <h3>Hola, Invitado</h3>
          <p>Inicia sesión para más opciones</p>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="menu-body">
    <?php if ($isLoggedIn): ?>
      <a href="my_orders" class="menu-item">
        <i class="fas fa-box"></i>
        <span>Mis Órdenes</span>
      </a>
      <a href="cart" class="menu-item">
        <i class="fas fa-shopping-cart"></i>
        <span>Mi Carrito</span>
      </a>
    <?php else: ?>
      <a href="login" class="menu-item primary">
        <i class="fas fa-sign-in-alt"></i>
        <span>Iniciar Sesión</span>
      </a>
    <?php endif; ?>

    <!-- ── CLIENTES ─────────────────────────── -->
    <div style="display:flex;align-items:center;gap:8px;margin:14px 0 6px;padding:0 4px;">
      <div style="flex:1;height:1px;background:#e5e7eb;"></div>
      <span style="font-size:.8rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;white-space:nowrap;">
        <i class="fas fa-shopping-bag" style="color:#3b82f6;margin-right:4px;"></i>Para Clientes
      </span>
      <div style="flex:1;height:1px;background:#e5e7eb;"></div>
    </div>

    <a href="index" class="menu-item">
      <i class="fas fa-home"></i>
      <span>Inicio</span>
    </a>

    <a href="/servicios" class="menu-item">
      <i class="fas fa-concierge-bell"></i>
      <span>Empleos y Servicios</span>
    </a>

    <a href="venta-garaje" class="menu-item">
      <i class="fas fa-tags"></i>
      <span>Venta de Garaje</span>
    </a>

    <a href="bienes-raices" class="menu-item">
      <i class="fas fa-building"></i>
      <span>Bienes Raíces</span>
    </a>

    <a href="emprendedores-catalogo" class="menu-item">
      <i class="fas fa-store"></i>
      <span>Emprendedoras y Emprendedores</span>
    </a>

    <!-- ── VENDEDORES ────────────────────────── -->
    <div style="display:flex;align-items:center;gap:8px;margin:14px 0 6px;padding:0 4px;">
      <div style="flex:1;height:1px;background:#e5e7eb;"></div>
      <span style="font-size:.8rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#6b7280;white-space:nowrap;">
        <i class="fas fa-store-alt" style="color:#10b981;margin-right:4px;"></i>Para Vendedores
      </span>
      <div style="flex:1;height:1px;background:#e5e7eb;"></div>
    </div>

    <a href="select-publication-type.php" class="menu-item">
      <i class="fas fa-bullhorn"></i>
      <span>Publicar mi venta</span>
    </a>

    <a href="affiliate/login.php" class="menu-item">
      <i class="fas fa-tags"></i>
      <span>Portal Venta Garaje</span>
    </a>

    <a href="/jobs_service/login.php" class="menu-item">
      <i class="fas fa-briefcase"></i>
      <span>Portal Empleos y Servicios</span>
    </a>

    <a href="/real-estate/login.php" class="menu-item">
      <i class="fas fa-home"></i>
      <span>Portal Bienes Raíces</span>
    </a>

    <a href="emprendedores-dashboard" class="menu-item">
      <i class="fas fa-store"></i>
      <span>Portal Emprendedores</span>
    </a>

    <a href="admin/login.php" class="menu-item">
      <i class="fas fa-user-shield"></i>
      <span>Administrador</span>
    </a>

    <?php if ($isLoggedIn): ?>
      <div class="menu-divider"></div>
      <a href="logout" class="menu-item danger">
        <i class="fas fa-sign-out-alt"></i>
        <span>Cerrar Sesión</span>
      </a>
    <?php endif; ?>
  </div>
</aside>


<!-- HERO SECTION -->
<section class="hero">
  <div class="hero-content">
    <div class="hero-badge">
      <span class="flag-emoji emoji">🇨🇷</span> ORGULLO COSTARRICENSE
    </div>
    <h1>
      Hecho en Costa Rica, Para Ticos
      <span class="cr-flag emoji">🇨🇷</span>
    </h1>
    <p>El primer marketplace 100% costarricense que conecta emprendedores ticos con compradores nacionales. Apoyemos lo nuestro y fortalezcamos nuestra economía local.</p>
    <div class="hero-buttons">
      <a href="#categorias" class="btn-hero btn-hero-primary">
        <i class="fas fa-compass"></i>
        Explorar Ahora
      </a>
      <a href="emprendedores-catalogo" class="btn-hero btn-hero-secondary">
        <i class="fas fa-store"></i>
        Únete como Emprendedor/a
      </a>
    </div>
  </div>
</section>

<!-- CATEGORÍAS -->
<section class="categories-section" id="categorias">
  <div class="section-header">
    <h2 class="section-title">Descubrí Nuestro Mercado Tico</h2>
    <p class="section-subtitle">Todo lo que necesitás, hecho por ticos para ticos. Productos y servicios 100% costarricenses.</p>
  </div>

  <div class="categories-grid">
    <!-- SERVICIOS -->
    <a href="servicios" class="category-card category-servicios">
      <div class="category-content">
        <div class="category-icon">
          <i class="fas fa-briefcase"></i>
        </div>
        <h3 class="category-title">Servicios</h3>
        <p class="category-description">Encontrá profesionales ticos de primer nivel: diseño, fotografía, consultoría, reparaciones y mucho más</p>
      </div>
    </a>

    <!-- VENTA DE GARAJE -->
    <a href="venta-garaje" class="category-card category-garaje">
      <div class="category-content">
        <div class="category-icon">
          <i class="fas fa-tags"></i>
        </div>
        <h3 class="category-title">Venta de Garaje</h3>
        <p class="category-description">Descubrí tesoros únicos y productos de segunda mano en perfecto estado a precios que te van a encantar, mae</p>
      </div>
    </a>

    <!-- BIENES RAÍCES -->
    <a href="bienes-raices" class="category-card category-bienes-raices">
      <div class="category-content">
        <div class="category-icon">
          <i class="fas fa-home"></i>
        </div>
        <h3 class="category-title">Bienes Raíces</h3>
        <p class="category-description">Encontrá tu casa ideal, apartamento, local comercial o terreno. Alquilá o comprá propiedades en todo Costa Rica</p>
      </div>
    </a>

    <!-- EMPLEOS -->
    <a href="empleos" class="category-card category-empleos">
      <div class="category-content">
        <div class="category-icon">
          <i class="fas fa-briefcase"></i>
        </div>
        <h3 class="category-title">Empleos</h3>
        <p class="category-description">Encontrá oportunidades laborales en empresas costarricenses. Publicá tu CV o buscá el trabajo ideal para vos</p>
      </div>
    </a>

    <!-- EMPRENDEDORAS Y EMPRENDEDORES -->
    <a href="emprendedores-catalogo" class="category-card category-emprendedoras" style="
      position: relative;
      overflow: hidden;
      background: #0f172a;
      min-height: 200px;
    ">
      <!-- Fondo fotográfico difuminado -->
      <div style="
        position: absolute; inset: 0;
        background-image: url('https://images.unsplash.com/photo-1533900298318-6b8da08a523e?w=800&h=500&fit=crop&auto=format');
        background-size: cover; background-position: center;
        opacity: 0.80;
      "></div>
      <!-- Overlay degradado profesional -->
      <div style="
        position: absolute; inset: 0;
        background: linear-gradient(135deg,
          rgba(15,23,42,0.40) 0%,
          rgba(30,41,59,0.28) 50%,
          rgba(51,65,85,0.20) 100%);
      "></div>
      <!-- Acento de color lateral -->
      <div style="
        position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
        background: linear-gradient(to bottom, #f472b6, #60a5fa);
        z-index: 2;
      "></div>
      <div class="category-content" style="position: relative; z-index: 3;">
        <div class="category-icon" style="
          background: rgba(255,255,255,0.08);
          border: 1.5px solid rgba(255,255,255,0.15);
          backdrop-filter: blur(6px);
        ">
          <i class="fas fa-store" style="color: #fff;"></i>
        </div>
        <h3 class="category-title" style="color: #fff;">Emprendedoras y Emprendedores</h3>
        <p class="category-description" style="color: rgba(255,255,255,0.90);">Mercadito tico: comprá directo a quienes venden. Apoyá el talento costarricense.</p>
      </div>
    </a>
  </div>
</section>


<!-- ESTADÍSTICAS -->
<section class="stats-section">
  <span class="stats-flag-bg emoji">🇨🇷</span>
  <div class="stats-container">
    <div class="stat-item">
      <div class="stat-number">500+</div>
      <div class="stat-label">Emprendedores Ticos</div>
    </div>
    <div class="stat-item">
      <div class="stat-number">10K+</div>
      <div class="stat-label">Productos Costarricenses</div>
    </div>
    <div class="stat-item">
      <div class="stat-number">5K+</div>
      <div class="stat-label">Ticos Satisfechos</div>
    </div>
    <div class="stat-item">
      <div class="stat-number">100%</div>
      <div class="stat-label">Orgullo Nacional <span class="stat-flag emoji">🇨🇷</span></div>
    </div>
  </div>
</section>

<!-- SECCIÓN PURA VIDA -->
<section class="pura-vida-section">
  <div class="pura-vida-content">
    <h2 class="pura-vida-title">¡Pura Vida, Mae! <span class="pura-vida-flag emoji">🇨🇷</span></h2>
    <p class="pura-vida-text">
      Somos más que un marketplace. Somos una comunidad de ticos apoyando ticos.
      Cada compra que hacés fortalece nuestra economía local y ayuda a que emprendedores
      costarricenses cumplan sus sueños. Juntos construimos un Costa Rica más próspero.
    </p>
    <div class="pura-vida-icons">
      <div class="pv-icon">
        <img
          src="https://cdn.getyourguide.com/image/format=auto,fit=contain,gravity=auto,quality=60,width=1440,height=650,dpr=1/tour_img/f75f22af67b8873946d5bb70e701aa3ae65305c9198890fca5ee43ae567d7093.jpg"
          alt="Volcán Arenal"
          class="pv-icon-img"
          id="pv-arenal-img"
          loading="lazy"
          title="Hacé clic una vez para activar los sonidos y luego pasá el mouse">
        <span class="pv-icon-text">Volcán Arenal</span>
      </div>
      <div class="pv-icon">
        <img
          src="https://images.unsplash.com/photo-1559056199-641a0ac8b55e?w=400&h=300&fit=crop"
          alt="Café Costarricense"
          class="pv-icon-img"
          id="pv-cafe-img"
          loading="lazy"
          title="Hacé clic una vez para activar los sonidos y luego pasá el mouse">
        <span class="pv-icon-text">Café de Altura</span>
      </div>
      <div class="pv-icon">
        <img
          src="https://costarica.org/wp-content/uploads/2017/05/Caribbean.jpg"
          alt="Playas de Costa Rica"
          class="pv-icon-img"
          id="pv-caribe-img"
          loading="lazy"
          title="Hacé clic una vez para activar los sonidos y luego pasá el mouse">
        <span class="pv-icon-text">Playas del Caribe</span>
      </div>
      <div class="pv-icon">
        <img
          src="/imagenes/yiguirro.jpg"
          alt="Yigüirro"
          class="pv-icon-img"
          id="pv-yiguirro-img"
          title="Hacé clic una vez para activar los sonidos y luego pasá el mouse">
        <span class="pv-icon-text">Yigüirro Nacional</span>
      </div>
    </div>
  </div>
</section>

<!-- Audios de la sección Pura Vida -->
<audio id="audioArenal" src="/sonidos/arenal.mp3" preload="none"></audio>
<audio id="audioCafe" src="/sonidos/cafe.mp3" preload="none"></audio>
<audio id="audioCaribe" src="/sonidos/caribe.mp3" preload="none"></audio>
<audio id="audioYiguirro" src="/sonidos/yiguirro.mp3" preload="none"></audio>

<!-- FOOTER -->
<footer class="site-footer">
  <div class="footer-content">
    <div class="footer-section">
      <h3><span class="footer-section-flag emoji">🇨🇷</span> CompraTica</h3>
      <p>El marketplace costarricense que une a emprendedores ticos con compradores nacionales. Hecho en Costa Rica, con orgullo y dedicación.</p>
    </div>
    <div class="footer-section">
      <h3>Enlaces Rápidos</h3>
      <a href="servicios">Servicios</a>
      <a href="venta-garaje">Venta de Garaje</a>
      <a href="bienes-raices">Bienes Raíces</a>
      <a href="emprendedores-catalogo">Emprendedoras y Emprendedores</a>
    </div>
    <div class="footer-section">
      <h3>Para Emprendedores</h3>
      <a href="affiliate/login.php">Portal de Afiliados</a>
      <a href="register.php">Registrarse</a>
      <a href="admin/login.php">Administración</a>
    </div>
    <div class="footer-section">
      <h3>Contacto</h3>
      <a href="mailto:info@compratica.com">
        <i class="fas fa-envelope"></i> info@compratica.com
      </a>
      <a href="tel:+50688902814">
        <i class="fas fa-phone"></i> +506 8890-2814
      </a>
    </div>
  </div>
  <div class="footer-bottom">
    <p>
      © <?php echo date('Y'); ?> CompraTica — Hecho con <span class="footer-heart emoji">❤️</span> en Costa Rica
      <span class="footer-flag emoji">🇨🇷</span>
    </p>
    <p style="margin-top: 0.5rem; font-size: 0.9rem; opacity: 0.8;">
      Apoyando el talento costarricense desde el corazón de Centroamérica
    </p>
  </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Carrito badge
  const cartBadge = document.getElementById('cartBadge');
  const cantidadInicial = <?php echo $cantidadProductos; ?>;

  if (cantidadInicial > 0) {
    cartBadge.style.display = 'inline-block';
  } else {
    cartBadge.style.display = 'none';
  }

  // Smooth scroll
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    });
  });

  // Sonidos Pura Vida (Arenal, Café, Caribe, Yigüirro)
  const soundMap = [
    { imgId: 'pv-arenal-img',  audioId: 'audioArenal'  },
    { imgId: 'pv-cafe-img',    audioId: 'audioCafe'    },
    { imgId: 'pv-caribe-img',  audioId: 'audioCaribe'  },
    { imgId: 'pv-yiguirro-img',audioId: 'audioYiguirro'}
  ];

  let audioDesbloqueado = false;

  function attachHoverSound(imgId, audioId) {
    const img = document.getElementById(imgId);
    const audio = document.getElementById(audioId);
    if (!img || !audio) return;

    function reproducirDesdeInicio() {
      try {
        audio.currentTime = 0;
        const p = audio.play();
        if (p && p.catch) {
          p.catch(err => console.warn('Error al reproducir audio (' + audioId + '):', err));
        }
      } catch (e) {
        console.warn('No se pudo reproducir audio (' + audioId + '):', e);
      }
    }

    // Primer clic en cualquier imagen: desbloquea audio para todas
    img.addEventListener('click', function () {
      if (!audioDesbloqueado) {
        audioDesbloqueado = true;
        reproducirDesdeInicio();
        setTimeout(function () {
          audio.pause();
          audio.currentTime = 0;
        }, 200);
      }
    });

    img.addEventListener('mouseenter', function () {
      if (!audioDesbloqueado) return;
      reproducirDesdeInicio();
    });

    img.addEventListener('mouseleave', function () {
      audio.pause();
      audio.currentTime = 0;
    });
  }

  soundMap.forEach(cfg => attachHoverSound(cfg.imgId, cfg.audioId));
});
</script>


<script>
// MENÚ HAMBURGUESA
const menuButton = document.getElementById('menuButton');
const menuOverlay = document.getElementById('menu-overlay');
const hamburgerMenu = document.getElementById('hamburger-menu');
const menuClose = document.getElementById('menu-close');

function openMenu() {
  menuOverlay.classList.add('show');
  hamburgerMenu.classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeMenu() {
  menuOverlay.classList.remove('show');
  hamburgerMenu.classList.remove('show');
  document.body.style.overflow = '';
}

if (menuButton) {
  menuButton.addEventListener('click', openMenu);
}

if (menuClose) {
  menuClose.addEventListener('click', closeMenu);
}

if (menuOverlay) {
  menuOverlay.addEventListener('click', closeMenu);
}

// Cerrar con ESC
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && hamburgerMenu.classList.contains('show')) {
    closeMenu();
  }
});

// ============= CARRITO =============
const API = '/api/cart.php';

function groupCartItems(groups) {
  const productMap = new Map();
  
  groups.forEach(group => {
    group.items.forEach(item => {
      const key = `${item.product_id}_${item.unit_price}`;
      
      if (productMap.has(key)) {
        const existing = productMap.get(key);
        existing.qty += item.qty;
        existing.line_total += item.line_total;
      } else {
        productMap.set(key, {
          ...item,
          sale_id: group.sale_id,
          sale_title: group.sale_title,
          currency: group.currency
        });
      }
    });
  });
  
  return Array.from(productMap.values());
}

function fmtPrice(n, currency = 'CRC') {
  currency = currency.toUpperCase();
  if (currency === 'USD') {
    return '$' + n.toFixed(2);
  }
  return '₡' + Math.round(n).toLocaleString('es-CR');
}

function renderCart(data) {
  const cartItemsContainer = document.getElementById('cart-items');
  const cartTotal = document.getElementById('cart-total');
  const cartEmpty = document.getElementById('cart-empty');
  const cartBadge = document.getElementById('cartBadge');
  const checkoutBtn = document.getElementById('checkoutBtn');
  
  if (!data || !data.ok || !data.groups || data.groups.length === 0) {
    cartBadge.textContent = '0';
    cartBadge.style.display = 'none';
    cartEmpty.style.display = 'block';
    cartItemsContainer.innerHTML = '';
    cartTotal.textContent = '₡0';
    if (checkoutBtn) {
      checkoutBtn.href = 'cart.php';
      checkoutBtn.textContent = 'Pagar';
    }
    return;
  }
  
  const groupedItems = groupCartItems(data.groups);
  
  let totalCount = 0;
  let totalAmount = 0;
  let mainCurrency = 'CRC';
  
  groupedItems.forEach(item => {
    totalCount += item.qty;
    totalAmount += item.line_total;
    mainCurrency = item.currency || 'CRC';
  });
  
  cartBadge.textContent = totalCount;
  cartBadge.style.display = totalCount > 0 ? 'inline-block' : 'none';
  cartEmpty.style.display = totalCount === 0 ? 'block' : 'none';
  
  if (checkoutBtn) {
    if (data.groups.length === 1) {
      checkoutBtn.href = `checkout.php?sale_id=${data.groups[0].sale_id}`;
      checkoutBtn.innerHTML = '<i class="fas fa-credit-card"></i> Pagar';
    } else {
      checkoutBtn.href = 'cart.php';
      checkoutBtn.innerHTML = '<i class="fas fa-shopping-cart"></i> Ver carrito';
    }
  }
  
  cartItemsContainer.innerHTML = groupedItems.map(item => `
    <div class="cart-popover-item" data-pid="${item.product_id}" data-sale-id="${item.sale_id}">
      <img 
        src="${item.product_image_url || '/assets/placeholder.jpg'}" 
        alt="${item.product_name}"
        class="cart-popover-item-img"
      >
      <div class="cart-popover-item-info">
        <div class="cart-popover-item-name">${item.product_name}</div>
        <div class="cart-popover-item-price">
          ${fmtPrice(item.unit_price, item.currency)} × ${item.qty}
        </div>
        <div class="cart-popover-item-total">
          ${fmtPrice(item.line_total, item.currency)}
        </div>
      </div>
      <button 
        class="cart-popover-item-remove" 
        data-pid="${item.product_id}"
        data-sale-id="${item.sale_id}"
        title="Eliminar"
      >
        ×
      </button>
    </div>
  `).join('');
  
  cartTotal.textContent = fmtPrice(totalAmount, mainCurrency);
}

async function loadCart() {
  try {
    // Timeout de 5 segundos para móviles lentos
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000);

    const response = await fetch(API + '?action=get', {
      credentials: 'include',
      cache: 'no-store',
      signal: controller.signal
    });
    clearTimeout(timeoutId);

    const data = await response.json();
    renderCart(data);
  } catch (error) {
    // Si falla o timeout, mostrar carrito vacío sin bloquear
    console.error('Error al cargar carrito:', error);
    renderCart({ items: [], total: 0 });
  }
}

// Toggle popover carrito
const cartBtn = document.getElementById('cartButton');
const cartPopover = document.getElementById('cart-popover');

if (cartBtn && cartPopover) {
  cartBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    const isOpen = cartPopover.classList.contains('show');
    
    if (!isOpen) {
      loadCart();
    }
    
    cartPopover.classList.toggle('show');
  });
  
  document.addEventListener('click', (e) => {
    if (!cartPopover.contains(e.target) && !cartBtn.contains(e.target)) {
      cartPopover.classList.remove('show');
    }
  });
}

// Eliminar item del carrito
document.addEventListener('click', async (e) => {
  const removeBtn = e.target.closest('.cart-popover-item-remove');
  if (!removeBtn) return;
  
  const pid = parseInt(removeBtn.dataset.pid);
  const saleId = parseInt(removeBtn.dataset.saleId);
  
  try {
    const token = (document.cookie.match(/(?:^|;\s*)vg_csrf=([^;]+)/) || [])[1] || '';
    const response = await fetch(API + '?action=remove', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': token
      },
      body: JSON.stringify({ product_id: pid, sale_id: saleId }),
      credentials: 'include'
    });
    
    const data = await response.json();
    if (data.ok) {
      loadCart();
    }
  } catch (error) {
    console.error('Error:', error);
  }
});

// Cargar carrito al inicio
loadCart();
</script>

<?php require_once __DIR__ . '/includes/chat-support.php'; ?>
</body>
</html>
