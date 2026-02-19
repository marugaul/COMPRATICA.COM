<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

// Inicializar carrito si no existe
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = ['groups' => []];
}

// Calcular cantidad de productos en el carrito
$cantidadProductos = 0;
if (isset($_SESSION['cart']['groups']) && is_array($_SESSION['cart']['groups'])) {
    foreach ($_SESSION['cart']['groups'] as $group) {
        if (isset($group['items']) && is_array($group['items'])) {
            foreach ($group['items'] as $item) {
                $cantidadProductos += (int)($item['qty'] ?? 0);
            }
        }
    }
}

// Verificar si el usuario está logueado
$isLoggedIn = is_logged_in();
$userName = $_SESSION['name'] ?? 'Usuario';

// Configurar charset para la respuesta
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
ini_set('default_charset', 'UTF-8');

$pdo = db();

// Obtener ID de la propiedad
$listingId = (int)($_GET['id'] ?? 0);

if ($listingId <= 0) {
  header('Location: bienes-raices.php');
  exit;
}

// Cargar la propiedad
$stmt = $pdo->prepare("
  SELECT l.*,
         c.name AS category_name,
         c.icon AS category_icon,
         a.name AS agent_name,
         a.email AS agent_email,
         a.phone AS agent_phone,
         a.company_name,
         a.company_logo,
         a.profile_image
  FROM real_estate_listings l
  LEFT JOIN categories c ON c.id = l.category_id
  LEFT JOIN real_estate_agents a ON a.id = l.agent_id
  WHERE l.id = ? AND l.is_active = 1
  LIMIT 1
");
$stmt->execute([$listingId]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$listing) {
  header('Location: bienes-raices.php?error=not_found');
  exit;
}

// Decodificar JSON
$images = !empty($listing['images']) ? json_decode($listing['images'], true) : [];
if (!is_array($images)) $images = [];

// Convertir URLs de Google Drive al formato directo
$images = array_map('convert_google_drive_url', $images);

$features = !empty($listing['features']) ? json_decode($listing['features'], true) : [];
if (!is_array($features)) $features = [];

// Formatear precio
$price = $listing['price'];
$currency = $listing['currency'] ?? 'CRC';
$priceFormatted = $currency === 'USD'
  ? '$' . number_format($price, 2)
  : '₡' . number_format($price, 0);

// Tipo de operación
$operationType = $listing['listing_type'] === 'sale' ? 'Venta' : 'Alquiler';

// Nombre de categoría sin prefijo "BR:"
$categoryDisplay = str_replace('BR: ', '', $listing['category_name'] ?? 'Propiedad');

// URL de WhatsApp
$whatsappPhone = $listing['contact_whatsapp'] ?? $listing['contact_phone'] ?? $listing['agent_phone'] ?? '';
$whatsappPhone = preg_replace('/[^0-9]/', '', $whatsappPhone);
$whatsappMessage = urlencode("Hola, me interesa la propiedad: " . $listing['title']);
$whatsappUrl = "https://wa.me/506{$whatsappPhone}?text={$whatsappMessage}";

// Ubicación completa
$locationParts = array_filter([
  $listing['district'] ?? '',
  $listing['canton'] ?? '',
  $listing['province'] ?? ''
]);
$fullLocation = implode(', ', $locationParts);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($listing['title']) ?> — <?php echo APP_NAME; ?></title>
  <meta name="description" content="<?= htmlspecialchars(substr($listing['description'], 0, 160)) ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <link rel="stylesheet" href="/assets/css/main.css">

  <style>
    :root {
      --cr-azul: #002b7f;
      --cr-azul-claro: #0041b8;
      --cr-rojo: #ce1126;
      --cr-blanco: #ffffff;
      --cr-gris: #f8f9fa;
      --dark: #1a1a1a;
      --gray-700: #4a5568;
      --gray-600: #718096;
      --gray-500: #a0aec0;
      --gray-300: #e2e8f0;
      --gray-100: #f7fafc;
      --success: #27ae60;
      --shadow: 0 4px 6px rgba(0,0,0,0.1);
      --radius: 16px;
      --transition: all 0.3s ease;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--cr-gris);
      color: var(--dark);
      line-height: 1.6;
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--cr-azul);
      text-decoration: none;
      font-weight: 600;
      margin-bottom: 2rem;
      transition: var(--transition);
    }

    .back-link:hover {
      gap: 0.75rem;
    }

    .property-header {
      background: var(--cr-blanco);
      border-radius: var(--radius);
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: var(--shadow);
    }

    .property-type-badge {
      display: inline-block;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      background: var(--cr-azul);
      color: var(--cr-blanco);
      margin-bottom: 1rem;
    }

    .property-type-badge.rent {
      background: var(--cr-rojo);
    }

    .property-title {
      font-family: 'Playfair Display', serif;
      font-size: 2.5rem;
      font-weight: 900;
      color: var(--dark);
      margin-bottom: 1rem;
      line-height: 1.2;
    }

    .property-location {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--gray-600);
      font-size: 1.125rem;
      margin-bottom: 1.5rem;
    }

    .property-location i {
      color: var(--cr-rojo);
    }

    .property-price {
      font-size: 3rem;
      font-weight: 800;
      color: var(--success);
      margin-bottom: 0.5rem;
    }

    .property-category {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      background: var(--gray-100);
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--gray-700);
    }

    .main-content {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 2rem;
    }

    @media (max-width: 968px) {
      .main-content {
        grid-template-columns: 1fr;
      }
    }

    .gallery-section {
      background: var(--cr-blanco);
      border-radius: var(--radius);
      padding: 2rem;
      box-shadow: var(--shadow);
      margin-bottom: 2rem;
    }

    .main-image {
      width: 100%;
      height: 500px;
      object-fit: cover;
      border-radius: var(--radius);
      margin-bottom: 1rem;
    }

    .thumbnails {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
      gap: 1rem;
    }

    .thumbnail {
      width: 100%;
      height: 100px;
      object-fit: cover;
      border-radius: 8px;
      cursor: pointer;
      border: 3px solid transparent;
      transition: var(--transition);
    }

    .thumbnail:hover,
    .thumbnail.active {
      border-color: var(--cr-azul);
    }

    .details-section {
      background: var(--cr-blanco);
      border-radius: var(--radius);
      padding: 2rem;
      box-shadow: var(--shadow);
      margin-bottom: 2rem;
    }

    .section-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--cr-azul);
      margin-bottom: 1.5rem;
      padding-bottom: 0.75rem;
      border-bottom: 2px solid var(--gray-100);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .property-specs {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .spec-item {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .spec-icon {
      width: 50px;
      height: 50px;
      background: var(--gray-100);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--cr-azul);
      font-size: 1.5rem;
    }

    .spec-content h4 {
      font-size: 0.875rem;
      color: var(--gray-600);
      margin-bottom: 0.25rem;
    }

    .spec-content p {
      font-size: 1.125rem;
      font-weight: 700;
      color: var(--dark);
    }

    .description {
      font-size: 1.0625rem;
      line-height: 1.75;
      color: var(--gray-700);
      margin-bottom: 2rem;
    }

    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 1rem;
    }

    .feature-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem;
      background: var(--gray-100);
      border-radius: 8px;
    }

    .feature-item i {
      color: var(--success);
      font-size: 1.125rem;
    }

    .sidebar {
      position: sticky;
      top: 2rem;
      height: fit-content;
    }

    .contact-card {
      background: var(--cr-blanco);
      border-radius: var(--radius);
      padding: 2rem;
      box-shadow: var(--shadow);
      margin-bottom: 2rem;
    }

    .agent-info {
      text-align: center;
      margin-bottom: 1.5rem;
    }

    .agent-avatar {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      object-fit: cover;
      margin: 0 auto 1rem;
      border: 3px solid var(--cr-azul);
    }

    .agent-name {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--dark);
      margin-bottom: 0.25rem;
    }

    .agent-role {
      color: var(--gray-600);
      font-size: 0.875rem;
    }

    .contact-methods {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
      padding: 1rem 1.5rem;
      border-radius: 8px;
      font-weight: 700;
      font-size: 1rem;
      text-decoration: none;
      transition: var(--transition);
      cursor: pointer;
      border: none;
    }

    .btn-whatsapp {
      background: linear-gradient(135deg, #25D366, #128C7E);
      color: var(--cr-blanco);
      box-shadow: var(--shadow);
    }

    .btn-whatsapp:hover {
      background: linear-gradient(135deg, #128C7E, #075E54);
      transform: translateY(-2px);
      box-shadow: 0 6px 12px rgba(0,0,0,0.15);
    }

    .btn-phone {
      background: var(--cr-azul);
      color: var(--cr-blanco);
      box-shadow: var(--shadow);
    }

    .btn-phone:hover {
      background: var(--cr-azul-claro);
      transform: translateY(-2px);
      box-shadow: 0 6px 12px rgba(0,0,0,0.15);
    }

    .btn-email {
      background: var(--gray-100);
      color: var(--gray-700);
    }

    .btn-email:hover {
      background: var(--gray-300);
    }

    @media (max-width: 768px) {
      .container {
        padding: 1rem;
      }

      .property-title {
        font-size: 2rem;
      }

      .property-price {
        font-size: 2rem;
      }

      .main-image {
        height: 300px;
      }

      .property-specs {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="container">
  <a href="bienes-raices.php" class="back-link">
    <i class="fas fa-arrow-left"></i> Volver a listado de propiedades
  </a>

  <div class="property-header">
    <div class="property-type-badge <?= $listing['listing_type'] === 'rent' ? 'rent' : '' ?>">
      <?= $operationType ?>
    </div>

    <h1 class="property-title"><?= htmlspecialchars($listing['title']) ?></h1>

    <?php if ($fullLocation): ?>
    <div class="property-location">
      <i class="fas fa-map-marker-alt"></i>
      <span><?= htmlspecialchars($fullLocation) ?></span>
    </div>
    <?php endif; ?>

    <div class="property-price"><?= $priceFormatted ?></div>

    <div class="property-category">
      <i class="<?= htmlspecialchars($listing['category_icon'] ?? 'fa-home') ?>"></i>
      <?= htmlspecialchars($categoryDisplay) ?>
    </div>
  </div>

  <div class="main-content">
    <div>
      <!-- GALERÍA DE IMÁGENES -->
      <?php if (!empty($images)): ?>
      <div class="gallery-section">
        <img src="<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($listing['title']) ?>" class="main-image" id="mainImage">

        <?php if (count($images) > 1): ?>
        <div class="thumbnails">
          <?php foreach ($images as $index => $image): ?>
            <img
              src="<?= htmlspecialchars($image) ?>"
              alt="Imagen <?= $index + 1 ?>"
              class="thumbnail <?= $index === 0 ? 'active' : '' ?>"
              onclick="changeImage(this, <?= $index ?>)"
            >
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- ESPECIFICACIONES -->
      <div class="details-section">
        <h2 class="section-title">
          <i class="fas fa-info-circle"></i>
          Especificaciones
        </h2>

        <div class="property-specs">
          <?php if ($listing['bedrooms'] > 0): ?>
          <div class="spec-item">
            <div class="spec-icon">
              <i class="fas fa-bed"></i>
            </div>
            <div class="spec-content">
              <h4>Habitaciones</h4>
              <p><?= (int)$listing['bedrooms'] ?></p>
            </div>
          </div>
          <?php endif; ?>

          <?php if ($listing['bathrooms'] > 0): ?>
          <div class="spec-item">
            <div class="spec-icon">
              <i class="fas fa-bath"></i>
            </div>
            <div class="spec-content">
              <h4>Baños</h4>
              <p><?= (int)$listing['bathrooms'] ?></p>
            </div>
          </div>
          <?php endif; ?>

          <?php if ($listing['area_m2'] > 0): ?>
          <div class="spec-item">
            <div class="spec-icon">
              <i class="fas fa-ruler-combined"></i>
            </div>
            <div class="spec-content">
              <h4>Área</h4>
              <p><?= number_format($listing['area_m2'], 0) ?> m²</p>
            </div>
          </div>
          <?php endif; ?>

          <?php if ($listing['parking_spaces'] > 0): ?>
          <div class="spec-item">
            <div class="spec-icon">
              <i class="fas fa-car"></i>
            </div>
            <div class="spec-content">
              <h4>Parqueos</h4>
              <p><?= (int)$listing['parking_spaces'] ?></p>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- DESCRIPCIÓN -->
      <div class="details-section">
        <h2 class="section-title">
          <i class="fas fa-align-left"></i>
          Descripción
        </h2>
        <div class="description">
          <?= nl2br(htmlspecialchars($listing['description'])) ?>
        </div>
      </div>

      <!-- CARACTERÍSTICAS -->
      <?php if (!empty($features)): ?>
      <div class="details-section">
        <h2 class="section-title">
          <i class="fas fa-check-circle"></i>
          Características
        </h2>
        <div class="features-grid">
          <?php foreach ($features as $feature): ?>
            <div class="feature-item">
              <i class="fas fa-check"></i>
              <span><?= htmlspecialchars($feature) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- SIDEBAR CON INFORMACIÓN DE CONTACTO -->
    <div class="sidebar">
      <div class="contact-card">
        <h3 class="section-title" style="border-bottom: none; margin-bottom: 1.5rem;">
          <i class="fas fa-phone"></i>
          Contactar
        </h3>

        <div class="agent-info">
          <?php if (!empty($listing['profile_image'])): ?>
            <img src="<?= htmlspecialchars($listing['profile_image']) ?>" alt="<?= htmlspecialchars($listing['agent_name'] ?? $listing['contact_name']) ?>" class="agent-avatar">
          <?php else: ?>
            <div class="agent-avatar" style="background: var(--gray-100); display: flex; align-items: center; justify-content: center;">
              <i class="fas fa-user" style="font-size: 2rem; color: var(--gray-500);"></i>
            </div>
          <?php endif; ?>

          <div class="agent-name"><?= htmlspecialchars($listing['contact_name'] ?? $listing['agent_name'] ?? 'Agente') ?></div>
          <div class="agent-role"><?= htmlspecialchars($listing['company_name'] ?? 'Bienes Raíces') ?></div>
        </div>

        <div class="contact-methods">
          <?php if (!empty($whatsappPhone)): ?>
          <a href="<?= $whatsappUrl ?>" target="_blank" class="btn btn-whatsapp">
            <i class="fab fa-whatsapp"></i>
            WhatsApp
          </a>
          <?php endif; ?>

          <?php if (!empty($listing['contact_phone'])): ?>
          <a href="tel:<?= htmlspecialchars($listing['contact_phone']) ?>" class="btn btn-phone">
            <i class="fas fa-phone"></i>
            <?= htmlspecialchars($listing['contact_phone']) ?>
          </a>
          <?php endif; ?>

          <?php if (!empty($listing['contact_email'])): ?>
          <a href="mailto:<?= htmlspecialchars($listing['contact_email']) ?>" class="btn btn-email">
            <i class="fas fa-envelope"></i>
            Enviar email
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script src="/assets/js/cart.js"></script>
<script>
function changeImage(thumbnail, index) {
  // Cambiar imagen principal
  const mainImage = document.getElementById('mainImage');
  mainImage.src = thumbnail.src;

  // Actualizar thumbnails activos
  document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
  thumbnail.classList.add('active');
}
</script>
</body>
</html>
