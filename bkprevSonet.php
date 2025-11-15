<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Configurar sesión ANTES de cualquier include
if (session_status() === PHP_SESSION_NONE) {
    $__sessPath = __DIR__ . '/sessions';
    if (!is_dir($__sessPath)) @mkdir($__sessPath, 0700, true);
    if (is_dir($__sessPath) && is_writable($__sessPath)) {
        ini_set('session.save_path', $__sessPath);
    }
    session_name('PHPSESSID');
    session_start();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cantidadProductos = 0;
foreach ($_SESSION['cart'] as $it) {
    $cantidadProductos += (int)($it['qty'] ?? 0);
}

$isLoggedIn = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
$userName = $_SESSION['name'] ?? 'Usuario';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo APP_NAME; ?> — Marketplace de Emprendedores</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      /* Colores de la Bandera de Costa Rica */
      --cr-azul: #002b7f;
      --cr-azul-claro: #0041b8;
      --cr-rojo: #ce1126;
      --cr-rojo-claro: #e63946;
      --cr-blanco: #ffffff;
      --cr-gris: #f8f9fa;
      --dark: #1a1a1a;
      --gray-700: #4a5568;
      --gray-500: #718096;
      --gray-300: #e2e8f0;
      --gray-100: #f7fafc;

      /* Gradientes con colores de CR */
      --gradient-cr: linear-gradient(135deg, var(--cr-azul) 0%, var(--cr-rojo) 100%);
      --gradient-azul: linear-gradient(135deg, #002b7f 0%, #0041b8 100%);
      --gradient-rojo: linear-gradient(135deg, #ce1126 0%, #e63946 100%);

      --shadow-sm: 0 1px 3px 0 rgba(0, 43, 127, 0.1);
      --shadow-md: 0 4px 6px -1px rgba(0, 43, 127, 0.15), 0 2px 4px -1px rgba(0, 43, 127, 0.1);
      --shadow-lg: 0 10px 15px -3px rgba(0, 43, 127, 0.2), 0 4px 6px -2px rgba(0, 43, 127, 0.1);
      --shadow-xl: 0 20px 25px -5px rgba(0, 43, 127, 0.25), 0 10px 10px -5px rgba(0, 43, 127, 0.1);
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      --radius: 16px;
      --radius-sm: 8px;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: var(--cr-gris);
      color: var(--dark);
      line-height: 1.6;
      overflow-x: hidden;
    }

    /* HEADER */
    .header {
      background: var(--cr-blanco);
      border-bottom: 3px solid var(--cr-azul);
      padding: 1.25rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 4px 12px rgba(0, 43, 127, 0.1);
    }

    .logo {
      font-size: 1.75rem;
      font-weight: 800;
      color: var(--cr-azul);
      display: flex;
      align-items: center;
      gap: 0.75rem;
      letter-spacing: -0.03em;
    }

    .logo .flag {
      font-size: 2.5rem;
      filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
    }

    .logo .text {
      display: flex;
      flex-direction: column;
      line-height: 1.2;
    }

    .logo .text .main {
      color: var(--cr-azul);
      font-size: 1.5rem;
    }

    .logo .text .sub {
      color: var(--cr-rojo);
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 0.05em;
      text-transform: uppercase;
    }

    .header-nav {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .btn-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 44px;
      height: 44px;
      border-radius: 12px;
      border: 2px solid var(--cr-azul);
      background: var(--cr-blanco);
      color: var(--cr-azul);
      cursor: pointer;
      transition: var(--transition);
      position: relative;
      font-size: 1.125rem;
    }

    .btn-icon:hover {
      background: var(--cr-azul);
      color: var(--cr-blanco);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 43, 127, 0.3);
    }

    .cart-badge {
      position: absolute;
      top: -6px;
      right: -6px;
      background: var(--cr-rojo);
      color: var(--cr-blanco);
      border-radius: 999px;
      padding: 2px 7px;
      font-size: 0.7rem;
      font-weight: 700;
      min-width: 20px;
      text-align: center;
      box-shadow: 0 2px 8px rgba(206, 17, 38, 0.4);
    }

    /* HERO */
    .hero {
      background: var(--gradient-cr);
      padding: 6rem 2rem;
      text-align: center;
      color: var(--cr-blanco);
      position: relative;
      overflow: hidden;
    }

    .hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background:
        linear-gradient(180deg,
          var(--cr-azul) 0%,
          var(--cr-azul) 23%,
          var(--cr-blanco) 23%,
          var(--cr-blanco) 30%,
          var(--cr-rojo) 30%,
          var(--cr-rojo) 70%,
          var(--cr-blanco) 70%,
          var(--cr-blanco) 77%,
          var(--cr-azul) 77%,
          var(--cr-azul) 100%
        );
      opacity: 0.15;
      z-index: 0;
    }

    .hero::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-image:
        radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
      z-index: 0;
    }

    .hero-content {
      max-width: 900px;
      margin: 0 auto;
      position: relative;
      z-index: 1;
    }

    .hero-badge {
      display: inline-block;
      background: rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(10px);
      padding: 0.5rem 1.5rem;
      border-radius: 50px;
      font-size: 0.9rem;
      font-weight: 600;
      margin-bottom: 2rem;
      border: 2px solid rgba(255, 255, 255, 0.3);
    }

    .hero h1 {
      font-family: 'Playfair Display', serif;
      font-size: 3.5rem;
      font-weight: 900;
      margin-bottom: 1.5rem;
      line-height: 1.2;
      text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }

    .hero h1 .cr-flag {
      display: inline-block;
      font-size: 3rem;
      vertical-align: middle;
      margin-left: 0.5rem;
      animation: wave 2s ease-in-out infinite;
    }

    @keyframes wave {
      0%, 100% { transform: rotate(0deg); }
      25% { transform: rotate(10deg); }
      75% { transform: rotate(-10deg); }
    }

    .hero p {
      font-size: 1.35rem;
      margin-bottom: 2.5rem;
      opacity: 0.95;
      font-weight: 400;
      line-height: 1.7;
    }

    .hero-buttons {
      display: flex;
      gap: 1.25rem;
      justify-content: center;
      flex-wrap: wrap;
    }

    .btn-hero {
      display: inline-flex;
      align-items: center;
      gap: 0.75rem;
      padding: 1rem 2.5rem;
      border-radius: 50px;
      font-size: 1.1rem;
      font-weight: 600;
      text-decoration: none;
      transition: var(--transition);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    .btn-hero-primary {
      background: var(--cr-blanco);
      color: var(--cr-azul);
      border: 3px solid var(--cr-blanco);
    }

    .btn-hero-primary:hover {
      background: var(--cr-azul);
      color: var(--cr-blanco);
      transform: translateY(-3px);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
    }

    .btn-hero-secondary {
      background: transparent;
      color: var(--cr-blanco);
      border: 3px solid var(--cr-blanco);
    }

    .btn-hero-secondary:hover {
      background: var(--cr-blanco);
      color: var(--cr-rojo);
      transform: translateY(-3px);
    }

    /* CATEGORÍAS */
    .categories-section {
      padding: 5rem 2rem;
      max-width: 1400px;
      margin: 0 auto;
    }

    .section-header {
      text-align: center;
      margin-bottom: 4rem;
    }

    .section-title {
      font-family: 'Playfair Display', serif;
      font-size: 2.75rem;
      font-weight: 700;
      color: var(--cr-azul);
      margin-bottom: 1rem;
    }

    .section-subtitle {
      font-size: 1.2rem;
      color: var(--gray-700);
      max-width: 700px;
      margin: 0 auto;
    }

    .categories-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2rem;
      margin-top: 3rem;
    }

    .category-card {
      position: relative;
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow-lg);
      transition: var(--transition);
      text-decoration: none;
      display: block;
      height: 400px;
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      border: 3px solid transparent;
      animation: fadeInUp 0.6s ease-out backwards;
    }

    .category-card:nth-child(1) { animation-delay: 0.1s; }
    .category-card:nth-child(2) { animation-delay: 0.2s; }
    .category-card:nth-child(3) { animation-delay: 0.3s; }
    .category-card:nth-child(4) { animation-delay: 0.4s; }

    .category-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      transition: var(--transition);
    }

    .category-card:hover {
      transform: translateY(-8px);
      box-shadow: var(--shadow-xl);
      border-color: var(--cr-azul);
    }

    .category-card:hover::before {
      opacity: 0.7;
    }

    .category-content {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      padding: 2rem;
      color: var(--cr-blanco);
      z-index: 1;
      background: linear-gradient(to top, rgba(0, 0, 0, 0.9) 0%, transparent 100%);
    }

    .category-icon {
      font-size: 3rem;
      margin-bottom: 1rem;
      display: inline-block;
      filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
    }

    .category-title {
      font-size: 1.75rem;
      font-weight: 700;
      margin-bottom: 0.75rem;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .category-description {
      font-size: 1rem;
      opacity: 0.95;
      line-height: 1.6;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }

    .category-servicios {
      background-image: url('https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=800&q=80');
      background-size: cover;
      background-position: center;
    }

    .category-servicios::before {
      background: linear-gradient(135deg, rgba(0, 43, 127, 0.5) 0%, rgba(0, 65, 184, 0.5) 100%);
    }

    .category-garaje {
      background-image: url('https://images.unsplash.com/photo-1556740749-887f6717d7e4?w=800&q=80');
      background-size: cover;
      background-position: center;
    }

    .category-garaje::before {
      background: linear-gradient(135deg, rgba(206, 17, 38, 0.5) 0%, rgba(230, 57, 70, 0.5) 100%);
    }

    .category-emprendedores {
      background-image: url('https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=800&q=80');
      background-size: cover;
      background-position: center;
    }

    .category-emprendedores::before {
      background: linear-gradient(135deg, rgba(0, 43, 127, 0.5) 0%, rgba(206, 17, 38, 0.5) 100%);
    }

    .category-emprendedoras {
      background-image: url('https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=800&q=80');
      background-size: cover;
      background-position: center;
    }

    .category-emprendedoras::before {
      background: linear-gradient(135deg, rgba(206, 17, 38, 0.5) 0%, rgba(0, 43, 127, 0.5) 100%);
    }

    /* ESTADÍSTICAS */
    .stats-section {
      background: var(--gradient-cr);
      padding: 4rem 2rem;
      color: var(--cr-blanco);
      margin: 4rem 0;
      position: relative;
      overflow: hidden;
    }

    .stats-section::before {
      content: '🇨🇷';
      position: absolute;
      font-size: 30rem;
      opacity: 0.05;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
    }

    .stats-container {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 3rem;
      text-align: center;
      position: relative;
      z-index: 1;
    }

    .stat-item {
      padding: 2rem;
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border-radius: var(--radius);
      border: 2px solid rgba(255, 255, 255, 0.2);
      transition: var(--transition);
    }

    .stat-item:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: translateY(-5px);
    }

    .stat-number {
      font-size: 3.5rem;
      font-weight: 800;
      margin-bottom: 0.5rem;
      line-height: 1;
    }

    .stat-label {
      font-size: 1.1rem;
      opacity: 0.9;
      font-weight: 500;
    }

    /* SECCIÓN PURA VIDA */
    .pura-vida-section {
      padding: 5rem 2rem;
      background: var(--cr-blanco);
      text-align: center;
    }

    .pura-vida-content {
      max-width: 800px;
      margin: 0 auto;
    }

    .pura-vida-title {
      font-family: 'Playfair Display', serif;
      font-size: 3rem;
      color: var(--cr-azul);
      margin-bottom: 1.5rem;
    }

    .pura-vida-text {
      font-size: 1.3rem;
      color: var(--gray-700);
      line-height: 1.8;
      margin-bottom: 2rem;
    }

    .pura-vida-icons {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 2rem;
      margin-top: 3rem;
      max-width: 900px;
      margin-left: auto;
      margin-right: auto;
    }

    .pv-icon {
      text-align: center;
    }

    .pv-icon-img {
      width: 100%;
      height: 180px;
      object-fit: cover;
      border-radius: 12px;
      margin-bottom: 1rem;
      display: block;
      box-shadow: 0 4px 12px rgba(0, 43, 127, 0.2);
      transition: var(--transition);
      border: 3px solid transparent;
      cursor: pointer;
    }

    .pv-icon:hover .pv-icon-img {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0, 43, 127, 0.3);
      border-color: var(--cr-azul);
    }

    .pv-icon-text {
      font-size: 1rem;
      color: var(--cr-azul);
      font-weight: 600;
    }

    /* FOOTER */
    .site-footer {
      background: var(--cr-azul);
      color: var(--cr-blanco);
      padding: 4rem 2rem 2rem;
      margin-top: 5rem;
      border-top: 5px solid var(--cr-rojo);
    }

    .footer-content {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 3rem;
      margin-bottom: 3rem;
    }

    .footer-section h3 {
      font-size: 1.25rem;
      margin-bottom: 1.5rem;
      font-weight: 700;
      color: var(--cr-blanco);
    }

    .footer-section p,
    .footer-section a {
      color: rgba(255, 255, 255, 0.8);
      text-decoration: none;
      display: block;
      margin-bottom: 0.75rem;
      transition: var(--transition);
    }

    .footer-section a:hover {
      color: var(--cr-blanco);
      padding-left: 5px;
      text-decoration: underline;
    }

    .footer-bottom {
      border-top: 2px solid rgba(255, 255, 255, 0.2);
      padding-top: 2rem;
      text-align: center;
      color: rgba(255, 255, 255, 0.9);
      font-size: 1rem;
    }

    .footer-flag {
      font-size: 1.5rem;
      display: inline-block;
      animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.1); }
    }

    /* RESPONSIVE */
    @media (max-width: 768px) {
      .hero h1 {
        font-size: 2.25rem;
      }

      .hero h1 .cr-flag {
        font-size: 2rem;
      }

      .hero p {
        font-size: 1.1rem;
      }

      .section-title {
        font-size: 2rem;
      }

      .categories-grid {
        grid-template-columns: 1fr;
      }

      .category-card {
        height: 350px;
      }

      .stats-container {
        grid-template-columns: 1fr;
        gap: 2rem;
      }

      .hero-buttons {
        flex-direction: column;
        align-items: center;
      }

      .btn-hero {
        width: 100%;
        max-width: 300px;
        justify-content: center;
      }

      .logo .text .main {
        font-size: 1.2rem;
      }

      .logo .text .sub {
        font-size: 0.65rem;
      }

      .pura-vida-title {
        font-size: 2rem;
      }

      .pura-vida-text {
        font-size: 1.1rem;
      }

      .pura-vida-icons {
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
      }

      .pv-icon-img {
        height: 140px;
      }
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
  </style>
</head>
<body>

<!-- HEADER -->
<header class="header">
  <div class="logo">
    <span class="flag">🇨🇷</span>
    <div class="text">
      <span class="main">CompraTica</span>
      <span class="sub">100% COSTARRICENSE</span>
    </div>
  </div>
  <nav class="header-nav">
    <button class="btn-icon" id="cartButton" title="Carrito">
      <i class="fas fa-shopping-cart"></i>
      <span class="cart-badge" id="cartBadge"><?php echo $cantidadProductos; ?></span>
    </button>
    <?php if ($isLoggedIn): ?>
      <a href="profile.php" class="btn-icon" title="Mi Perfil">
        <i class="fas fa-user"></i>
      </a>
    <?php else: ?>
      <a href="login.php" class="btn-icon" title="Iniciar Sesión">
        <i class="fas fa-sign-in-alt"></i>
      </a>
    <?php endif; ?>
  </nav>
</header>

<!-- HERO SECTION -->
<section class="hero">
  <div class="hero-content">
    <div class="hero-badge">
      🇨🇷 ORGULLO COSTARRICENSE
    </div>
    <h1>
      Hecho en Costa Rica, Para Ticos
      <span class="cr-flag">🇨🇷</span>
    </h1>
    <p>El primer marketplace 100% costarricense que conecta emprendedores ticos con compradores nacionales. Apoyemos lo nuestro y fortalezcamos nuestra economía local.</p>
    <div class="hero-buttons">
      <a href="#categorias" class="btn-hero btn-hero-primary">
        <i class="fas fa-compass"></i>
        Explorar Ahora
      </a>
      <a href="register.php" class="btn-hero btn-hero-secondary">
        <i class="fas fa-rocket"></i>
        Únete como Emprendedor
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
    <a href="servicios.php" class="category-card category-servicios">
      <div class="category-content">
        <div class="category-icon">
          <i class="fas fa-briefcase"></i>
        </div>
        <h3 class="category-title">Servicios</h3>
        <p class="category-description">Encontrá profesionales ticos de primer nivel: diseño, fotografía, consultoría, reparaciones y mucho más</p>
      </div>
    </a>

    <!-- VENTA DE GARAJE -->
    <a href="venta-garaje.php" class="category-card category-garaje">
      <div class="category-content">
        <div class="category-icon">
          <i class="fas fa-tags"></i>
        </div>
        <h3 class="category-title">Venta de Garaje</h3>
        <p class="category-description">Descubrí tesoros únicos y productos de segunda mano en perfecto estado a precios que te van a encantar, mae</p>
      </div>
    </a>

    <!-- EMPRENDEDORES -->
    <a href="emprendedores.php" class="category-card category-emprendedores">
      <div class="category-content">
        <div class="category-icon">
          <i class="fas fa-rocket"></i>
        </div>
        <h3 class="category-title">Emprendedores</h3>
        <p class="category-description">Productos innovadores hechos por talentosos emprendedores ticos que están revolucionando el mercado nacional</p>
      </div>
    </a>

    <!-- EMPRENDEDORAS -->
    <a href="emprendedoras.php" class="category-card category-emprendedoras">
      <div class="category-content">
        <div class="category-icon">
          <i class="fas fa-crown"></i>
        </div>
        <h3 class="category-title">Emprendedoras</h3>
        <p class="category-description">Apoyá a mujeres ticas emprendedoras con productos únicos, artesanales y de la más alta calidad nacional</p>
      </div>
    </a>
  </div>
</section>

<!-- ESTADÍSTICAS -->
<section class="stats-section">
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
      <div class="stat-number">5K+</</div>
      <div class="stat-label">Ticos Satisfechos</div>
    </div>
    <div class="stat-item">
      <div class="stat-number">100%</div>
      <div class="stat-label">Orgullo Nacional 🇨🇷</div>
    </div>
  </div>
</section>

<!-- SECCIÓN PURA VIDA -->
<section class="pura-vida-section">
  <div class="pura-vida-content">
    <h2 class="pura-vida-title">¡Pura Vida, Mae! 🇨🇷</h2>
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
          title="Hacé clic una vez para activar los sonidos y luego pasá el mouse">
        <span class="pv-icon-text">Volcán Arenal</span>
      </div>
      <div class="pv-icon">
        <img
          src="https://images.unsplash.com/photo-1559056199-641a0ac8b55e?w=400&h=300&fit=crop"
          alt="Café Costarricense"
          class="pv-icon-img"
          id="pv-cafe-img"
          title="Hacé clic una vez para activar los sonidos y luego pasá el mouse">
        <span class="pv-icon-text">Café de Altura</span>
      </div>
      <div class="pv-icon">
        <img
          src="https://costarica.org/wp-content/uploads/2017/05/Caribbean.jpg"
          alt="Playas de Costa Rica"
          class="pv-icon-img"
          id="pv-caribe-img"
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
<audio id="audioArenal" src="/sonidos/arenal.mp3" preload="auto"></audio>
<audio id="audioCafe" src="/sonidos/cafe.mp3" preload="auto"></audio>
<audio id="audioCaribe" src="/sonidos/caribe.mp3" preload="auto"></audio>
<audio id="audioYiguirro" src="/sonidos/yiguirro.mp3" preload="auto"></audio>

<!-- FOOTER -->
<footer class="site-footer">
  <div class="footer-content">
    <div class="footer-section">
      <h3>🇨🇷 CompraTica</h3>
      <p>El marketplace costarricense que une a emprendedores ticos con compradores nacionales. Hecho en Costa Rica, con orgullo y dedicación.</p>
    </div>
    <div class="footer-section">
      <h3>Enlaces Rápidos</h3>
      <a href="servicios.php">Servicios</a>
      <a href="index.php">Venta de Garaje</a>
      <a href="emprendedores.php">Emprendedores</a>
      <a href="emprendedoras.php">Emprendedoras</a>
    </div>
    <div class="footer-section">
      <h3>Para Emprendedores</h3>
      <a href="affiliate/login.php">Portal de Afiliados</a>
      <a href="register.php">Registrarse</a>
      <a href="admin/login.php">Administración</a>
    </div>
    <div class="footer-section">
      <h3>Contacto</h3>
      <a href="mailto:<?php echo defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'info@compratica.com'; ?>">
        <i class="fas fa-envelope"></i> Enviar Email
      </a>
      <a href="tel:+50622222222">
        <i class="fas fa-phone"></i> +506 2222-2222
      </a>
    </div>
  </div>
  <div class="footer-bottom">
    <p>
      © <?php echo date('Y'); ?> CompraTica — Hecho con <span style="color: var(--cr-rojo);">❤️</span> en Costa Rica
      <span class="footer-flag">🇨🇷</span>
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

</body>
</html>
