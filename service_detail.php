<?php
/**
 * Detalle de Servicio
 *
 * Muestra información completa del servicio:
 * - Descripción detallada
 * - Galería de imágenes
 * - Calificaciones y reviews
 * - Información del proveedor
 * - Disponibilidad
 * - Botón de reserva
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

// Sesiones
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
$userName = $_SESSION['name'] ?? 'Usuario';

$pdo = db();

// Obtener servicio por ID
$serviceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$serviceId) {
    header('Location: servicios.php');
    exit;
}

// Obtener servicio con información del afiliado y categoría
$stmt = $pdo->prepare("
    SELECT
        s.*,
        a.name as affiliate_name,
        a.email as affiliate_email,
        a.phone as affiliate_phone,
        a.avatar as affiliate_avatar,
        a.business_description,
        sc.name as category_name,
        sc.slug as category_slug,
        sc.icon as category_icon,
        sc.requires_online_payment
    FROM services s
    INNER JOIN affiliates a ON a.id = s.affiliate_id
    INNER JOIN service_categories sc ON sc.id = s.category_id
    WHERE s.id = ? AND s.is_active = 1
    LIMIT 1
");
$stmt->execute([$serviceId]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    header('Location: servicios.php');
    exit;
}

// Obtener calificación promedio y reseñas
$stmt = $pdo->prepare("
    SELECT
        AVG(rating) as avg_rating,
        COUNT(*) as review_count
    FROM service_reviews
    WHERE service_id = ? AND is_approved = 1
");
$stmt->execute([$serviceId]);
$ratings = $stmt->fetch(PDO::FETCH_ASSOC);
$avgRating = round($ratings['avg_rating'] ?? 0, 1);
$reviewCount = $ratings['review_count'] ?? 0;

// Obtener reviews
$stmt = $pdo->prepare("
    SELECT *
    FROM service_reviews
    WHERE service_id = ? AND is_approved = 1
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$serviceId]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener disponibilidad
$stmt = $pdo->prepare("
    SELECT *
    FROM service_availability
    WHERE service_id = ? AND is_active = 1
    ORDER BY day_of_week ASC
");
$stmt->execute([$serviceId]);
$availability = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Métodos de pago aceptados
$stmt = $pdo->prepare("
    SELECT *
    FROM service_payment_methods
    WHERE service_id = ? AND is_active = 1
");
$stmt->execute([$serviceId]);
$paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

$daysOfWeek = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($service['title']); ?> - <?php echo APP_NAME; ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #1a73e8;
            --primary-dark: #1557b0;
            --accent: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", -apple-system, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.6;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 2rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            text-decoration: none;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .back-link:hover {
            color: var(--primary-dark);
        }

        .service-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .main-content {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .service-header {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid var(--gray-100);
        }

        .service-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--gray-900);
        }

        .service-meta {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-600);
        }

        .rating-large {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.25rem;
        }

        .star {
            color: var(--warning);
        }

        .rating-number {
            font-weight: 700;
            color: var(--gray-900);
        }

        .service-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            background: linear-gradient(135deg, var(--gray-200), var(--gray-300));
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            margin-top: 2rem;
        }

        .service-description {
            font-size: 1.0625rem;
            line-height: 1.8;
            color: var(--gray-700);
            margin-bottom: 2rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-card {
            padding: 1.25rem;
            background: var(--gray-50);
            border-radius: var(--radius);
            border: 2px solid var(--gray-200);
        }

        .info-label {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        /* Reviews */
        .review-item {
            padding: 1.5rem;
            border: 2px solid var(--gray-100);
            border-radius: var(--radius);
            margin-bottom: 1rem;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .reviewer-name {
            font-weight: 600;
            color: var(--gray-900);
        }

        .review-date {
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        .review-rating {
            display: flex;
            gap: 0.25rem;
            margin-bottom: 0.5rem;
        }

        .review-text {
            color: var(--gray-700);
            line-height: 1.6;
        }

        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .booking-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 2rem;
        }

        .price-display {
            text-align: center;
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
        }

        .price-amount {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .price-label {
            font-size: 0.9375rem;
            opacity: 0.9;
        }

        .btn-book {
            width: 100%;
            padding: 1.25rem;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 1.125rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 1rem;
        }

        .btn-book:hover {
            background: #0d9668;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .provider-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }

        .provider-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .provider-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            font-weight: 700;
        }

        .provider-info h3 {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }

        .provider-badge {
            font-size: 0.875rem;
            color: var(--accent);
            font-weight: 600;
        }

        .availability-list {
            list-style: none;
            padding: 0;
        }

        .availability-item {
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: var(--radius);
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
        }

        .day-name {
            font-weight: 600;
            color: var(--gray-900);
        }

        .time-range {
            color: var(--gray-600);
        }

        @media (max-width: 1024px) {
            .service-layout {
                grid-template-columns: 1fr;
            }

            .booking-card {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .main-content {
                padding: 1.5rem;
            }

            .service-title {
                font-size: 2rem;
            }

            .service-image {
                height: 250px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="services_list.php?category=<?php echo urlencode($service['category_slug']); ?>" class="back-link">
        <i class="fas fa-arrow-left"></i>
        Volver a <?php echo htmlspecialchars($service['category_name']); ?>
    </a>

    <div class="service-layout">
        <!-- Contenido principal -->
        <div class="main-content">
            <div class="service-header">
                <h1 class="service-title"><?php echo htmlspecialchars($service['title']); ?></h1>

                <div class="service-meta">
                    <div class="meta-item">
                        <i class="<?php echo htmlspecialchars($service['category_icon']); ?>"></i>
                        <?php echo htmlspecialchars($service['category_name']); ?>
                    </div>
                    <div class="rating-large">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star star" style="<?php echo $i > $avgRating ? 'opacity: 0.3;' : ''; ?>"></i>
                        <?php endfor; ?>
                        <span class="rating-number"><?php echo $avgRating; ?></span>
                        <span class="meta-item">(<?php echo $reviewCount; ?> reviews)</span>
                    </div>
                </div>
            </div>

            <img
                src="<?php echo $service['cover_image'] ? htmlspecialchars($service['cover_image']) : '/assets/placeholder-service.jpg'; ?>"
                alt="<?php echo htmlspecialchars($service['title']); ?>"
                class="service-image"
            >

            <h2 class="section-title">Descripción del Servicio</h2>
            <div class="service-description">
                <?php echo nl2br(htmlspecialchars($service['description'])); ?>
            </div>

            <div class="info-grid">
                <div class="info-card">
                    <div class="info-label">Duración</div>
                    <div class="info-value"><?php echo $service['duration_minutes']; ?> minutos</div>
                </div>
                <div class="info-card">
                    <div class="info-label">Modalidad</div>
                    <div class="info-value">
                        <?php echo $service['requires_address'] ? 'A domicilio' : 'Presencial/Virtual'; ?>
                    </div>
                </div>
                <?php if ($service['max_distance_km']): ?>
                <div class="info-card">
                    <div class="info-label">Distancia máxima</div>
                    <div class="info-value"><?php echo $service['max_distance_km']; ?> km</div>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($reviews)): ?>
                <h2 class="section-title">Opiniones de Clientes</h2>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <div class="reviewer-name"><?php echo htmlspecialchars($review['customer_name']); ?></div>
                            <div class="review-date">
                                <?php echo date('d/m/Y', strtotime($review['created_at'])); ?>
                            </div>
                        </div>
                        <div class="review-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star star" style="<?php echo $i > $review['rating'] ? 'opacity: 0.3;' : ''; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <div class="review-text"><?php echo htmlspecialchars($review['comment']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Card de reserva -->
            <div class="booking-card">
                <div class="price-display">
                    <div class="price-amount">₡<?php echo number_format($service['price_per_hour'], 0, ',', '.'); ?></div>
                    <div class="price-label">por hora</div>
                </div>

                <button class="btn-book" onclick="handleBooking()">
                    <i class="fas fa-calendar-check"></i>
                    Reservar Ahora
                </button>

                <?php if (!empty($paymentMethods)): ?>
                    <div style="text-align: center; font-size: 0.875rem; color: var(--gray-600); margin-top: 1rem;">
                        <i class="fas fa-info-circle"></i>
                        <?php echo $service['requires_online_payment'] ? 'Pago online requerido' : 'Pago acordado con el proveedor'; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Card del proveedor -->
            <div class="provider-card">
                <div class="provider-header">
                    <div class="provider-avatar">
                        <?php echo strtoupper(substr($service['affiliate_name'], 0, 1)); ?>
                    </div>
                    <div class="provider-info">
                        <h3><?php echo htmlspecialchars($service['affiliate_name']); ?></h3>
                        <div class="provider-badge">
                            <i class="fas fa-check-circle"></i> Verificado
                        </div>
                    </div>
                </div>
                <?php if ($service['business_description']): ?>
                    <p style="color: var(--gray-600); font-size: 0.9375rem; margin-bottom: 1rem;">
                        <?php echo htmlspecialchars($service['business_description']); ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Disponibilidad -->
            <?php if (!empty($availability)): ?>
                <div class="provider-card">
                    <h3 style="margin-bottom: 1rem;">Horarios Disponibles</h3>
                    <ul class="availability-list">
                        <?php foreach ($availability as $avail): ?>
                            <li class="availability-item">
                                <span class="day-name"><?php echo $daysOfWeek[$avail['day_of_week']]; ?></span>
                                <span class="time-range">
                                    <?php echo substr($avail['start_time'], 0, 5); ?> -
                                    <?php echo substr($avail['end_time'], 0, 5); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function handleBooking() {
    <?php if ($isLoggedIn): ?>
        // Redirigir a página de reserva (próximamente)
        alert('Sistema de reservas en desarrollo.\n\n' +
              'Próximamente podrás:\n' +
              '• Seleccionar fecha y hora\n' +
              '• Confirmar detalles\n' +
              '• Realizar el pago\n\n' +
              'Por ahora, contacta directamente al proveedor.');
    <?php else: ?>
        if (confirm('Necesitas iniciar sesión para hacer una reserva.\n¿Quieres ir a la página de login?')) {
            window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
        }
    <?php endif; ?>
}
</script>

</body>
</html>
