<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

// Verificar si ya está logueado
$isLoggedIn = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
$userName = $_SESSION['name'] ?? 'Usuario';

if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
ini_set('default_charset', 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Seleccionar Tipo de Publicación — <?php echo APP_NAME; ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">

  <style>
    :root {
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
      --success: #27ae60;
      --warning: #f39c12;
      --info: #3498db;
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
      background: linear-gradient(135deg, var(--cr-azul) 0%, var(--cr-azul-claro) 100%);
      color: var(--dark);
      line-height: 1.6;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
    }

    .container {
      max-width: 1200px;
      width: 100%;
      margin: 0 auto;
    }

    .header {
      text-align: center;
      margin-bottom: 3rem;
      color: var(--cr-blanco);
    }

    .header h1 {
      font-family: 'Playfair Display', serif;
      font-size: 3rem;
      font-weight: 900;
      margin-bottom: 1rem;
      text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
    }

    .header p {
      font-size: 1.25rem;
      opacity: 0.95;
    }

    .cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 2rem;
      margin-top: 2rem;
    }

    .publication-card {
      background: var(--cr-blanco);
      border-radius: var(--radius);
      padding: 2.5rem;
      box-shadow: var(--shadow-xl);
      transition: var(--transition);
      cursor: pointer;
      text-decoration: none;
      color: var(--dark);
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .publication-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 6px;
      background: linear-gradient(90deg, var(--cr-azul), var(--cr-rojo));
      transform: scaleX(0);
      transition: transform 0.3s ease;
    }

    .publication-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 25px 35px -5px rgba(0, 43, 127, 0.3);
    }

    .publication-card:hover::before {
      transform: scaleX(1);
    }

    .card-icon {
      width: 100px;
      height: 100px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      margin-bottom: 1.5rem;
      font-size: 3rem;
      transition: var(--transition);
    }

    .publication-card:hover .card-icon {
      transform: scale(1.1) rotate(5deg);
    }

    .card-icon.garaje {
      background: linear-gradient(135deg, #f39c12, #f1c40f);
      color: var(--cr-blanco);
    }

    .card-icon.empleos {
      background: linear-gradient(135deg, #27ae60, #2ecc71);
      color: var(--cr-blanco);
    }

    .card-icon.bienes {
      background: linear-gradient(135deg, var(--cr-azul), var(--cr-azul-claro));
      color: var(--cr-blanco);
    }

    .card-title {
      font-size: 1.75rem;
      font-weight: 700;
      margin-bottom: 1rem;
      color: var(--dark);
    }

    .card-description {
      font-size: 1rem;
      color: var(--gray-700);
      margin-bottom: 1.5rem;
      line-height: 1.7;
    }

    .card-features {
      list-style: none;
      margin-bottom: 1.5rem;
      text-align: left;
      width: 100%;
    }

    .card-features li {
      padding: 0.5rem 0;
      color: var(--gray-700);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .card-features li i {
      color: var(--success);
      font-size: 0.9rem;
    }

    .card-button {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 1rem 2rem;
      background: linear-gradient(135deg, var(--cr-azul), var(--cr-azul-claro));
      color: var(--cr-blanco);
      border: none;
      border-radius: var(--radius-sm);
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: var(--transition);
      text-decoration: none;
      margin-top: auto;
    }

    .card-button:hover {
      transform: scale(1.05);
      box-shadow: var(--shadow-lg);
    }

    .back-link {
      text-align: center;
      margin-top: 2rem;
    }

    .back-link a {
      color: var(--cr-blanco);
      text-decoration: none;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.75rem 1.5rem;
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border-radius: var(--radius-sm);
      transition: var(--transition);
    }

    .back-link a:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: translateX(-5px);
    }

    @media (max-width: 768px) {
      .header h1 {
        font-size: 2rem;
      }

      .header p {
        font-size: 1rem;
      }

      .cards-grid {
        grid-template-columns: 1fr;
      }

      .publication-card {
        padding: 2rem;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1><i class="fas fa-store"></i> ¿Qué querés publicar?</h1>
      <p>Seleccioná el tipo de publicación que mejor se adapte a lo que querés ofrecer</p>
    </div>

    <div class="cards-grid">
      <!-- VENTA DE GARAJE -->
      <a href="/affiliate/register.php" class="publication-card">
        <div class="card-icon garaje">
          <i class="fas fa-tags"></i>
        </div>
        <h2 class="card-title">Venta de Garaje</h2>
        <p class="card-description">
          Vendé artículos usados, colecciones, y productos de segunda mano.
        </p>
        <ul class="card-features">
          <li><i class="fas fa-check-circle"></i> Ideal para artículos usados</li>
          <li><i class="fas fa-check-circle"></i> Sistema de gestión de inventario</li>
          <li><i class="fas fa-check-circle"></i> Punto de retiro personalizado</li>
          <li><i class="fas fa-check-circle"></i> Fotos ilimitadas por producto</li>
        </ul>
        <span class="card-button">
          Empezar a Vender
          <i class="fas fa-arrow-right"></i>
        </span>
      </a>

      <!-- EMPLEOS Y SERVICIOS -->
      <a href="/jobs/register.php" class="publication-card">
        <div class="card-icon empleos">
          <i class="fas fa-briefcase"></i>
        </div>
        <h2 class="card-title">Empleos y Servicios</h2>
        <p class="card-description">
          Publicá ofertas de trabajo o servicios profesionales que ofrecés.
        </p>
        <ul class="card-features">
          <li><i class="fas fa-check-circle"></i> Publicaciones de empleo</li>
          <li><i class="fas fa-check-circle"></i> Servicios profesionales</li>
          <li><i class="fas fa-check-circle"></i> Categorización detallada</li>
          <li><i class="fas fa-check-circle"></i> Información de contacto</li>
        </ul>
        <span class="card-button">
          Publicar Oferta
          <i class="fas fa-arrow-right"></i>
        </span>
      </a>

      <!-- BIENES RAÍCES -->
      <a href="/real-estate/register.php" class="publication-card">
        <div class="card-icon bienes">
          <i class="fas fa-home"></i>
        </div>
        <h2 class="card-title">Bienes Raíces</h2>
        <p class="card-description">
          Publicá propiedades en venta o alquiler con información detallada.
        </p>
        <ul class="card-features">
          <li><i class="fas fa-check-circle"></i> Propiedades en venta/alquiler</li>
          <li><i class="fas fa-check-circle"></i> Detalles de metros cuadrados</li>
          <li><i class="fas fa-check-circle"></i> Ubicación en mapa</li>
          <li><i class="fas fa-check-circle"></i> Galería de fotos completa</li>
        </ul>
        <span class="card-button">
          Publicar Propiedad
          <i class="fas fa-arrow-right"></i>
        </span>
      </a>
    </div>

    <div class="back-link">
      <a href="/index.php">
        <i class="fas fa-arrow-left"></i>
        Volver al Inicio
      </a>
    </div>
  </div>
</body>
</html>
