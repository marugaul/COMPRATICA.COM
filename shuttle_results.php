<?php
/**
 * Resultados de búsqueda de Shuttle
 * Muestra afiliados que ofrecen el servicio según los criterios de búsqueda
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();

// Obtener parámetros de búsqueda
$origin = $_GET['origin'] ?? '';
$originType = $_GET['origin_type'] ?? '';
$originId = $_GET['origin_id'] ?? '';
$destination = $_GET['destination'] ?? '';
$destinationType = $_GET['destination_type'] ?? '';
$destinationId = $_GET['destination_id'] ?? '';
$date = $_GET['date'] ?? '';
$time = $_GET['time'] ?? '';
$passengers = (int)($_GET['passengers'] ?? 2);
$luggage = (int)($_GET['luggage'] ?? 2);

if (!$origin || !$destination || !$date || !$time) {
    header('Location: shuttle_search.php');
    exit;
}

// Buscar servicios de shuttle disponibles
$stmt = $pdo->prepare("
    SELECT
        s.*,
        a.name as affiliate_name,
        a.email as affiliate_email,
        a.phone as affiliate_phone,
        sc.name as category_name,
        COALESCE(AVG(sr.rating), 0) as avg_rating,
        COUNT(DISTINCT sr.id) as review_count
    FROM services s
    INNER JOIN affiliates a ON a.id = s.affiliate_id
    INNER JOIN service_categories sc ON sc.id = s.category_id
    LEFT JOIN service_reviews sr ON sr.service_id = s.id
    WHERE sc.slug = 'shuttle-aeropuerto'
        AND s.is_active = 1
    GROUP BY s.id
    ORDER BY avg_rating DESC, s.price_per_hour ASC
");
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular distancia y precio estimado (simplificado)
function calculateEstimatedPrice($basePrice) {
    // Precio base + estimación
    return $basePrice + rand(5000, 15000);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados de Búsqueda - Shuttle Aeropuerto</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #1a73e8;
            --primary-dark: #1557b0;
            --success: #10b981;
            --warning: #f59e0b;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-500: #6b7280;
            --gray-700: #374151;
            --gray-900: #111827;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius: 12px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-50);
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            color: var(--white);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--white);
            text-decoration: none;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .search-summary {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .summary-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .summary-item i {
            font-size: 1.2rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .results-header {
            margin-bottom: 2rem;
        }

        .results-count {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        .service-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            display: grid;
            grid-template-columns: 200px 1fr auto;
            gap: 1.5rem;
            align-items: center;
            transition: var(--transition);
        }

        .service-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .service-image {
            width: 200px;
            height: 140px;
            object-fit: cover;
            border-radius: var(--radius);
        }

        .service-info h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .service-provider {
            color: var(--gray-500);
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }

        .service-features {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-bottom: 0.75rem;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.9rem;
            color: var(--gray-700);
        }

        .feature i {
            color: var(--primary);
        }

        .service-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stars {
            color: #fbbf24;
        }

        .service-price-block {
            text-align: right;
        }

        .price-label {
            font-size: 0.85rem;
            color: var(--gray-500);
            margin-bottom: 0.25rem;
        }

        .price {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .btn-book {
            padding: 0.75rem 2rem;
            background: linear-gradient(135deg, var(--success), #059669);
            color: var(--white);
            border: none;
            border-radius: var(--radius);
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
        }

        .btn-book:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .no-results {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--white);
            border-radius: var(--radius);
        }

        .no-results i {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: 1rem;
        }

        .no-results h3 {
            font-size: 1.5rem;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .service-card {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .service-image {
                width: 100%;
                margin: 0 auto;
            }

            .service-price-block {
                text-align: center;
            }

            .search-summary {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <a href="shuttle_search.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Modificar búsqueda
        </a>

        <div class="search-summary">
            <div class="summary-item">
                <i class="fas fa-location-dot"></i>
                <span><?php echo htmlspecialchars($origin); ?></span>
            </div>
            <div class="summary-item">
                <i class="fas fa-arrow-right"></i>
            </div>
            <div class="summary-item">
                <i class="fas fa-flag-checkered"></i>
                <span><?php echo htmlspecialchars($destination); ?></span>
            </div>
            <div class="summary-item">
                <i class="fas fa-calendar"></i>
                <span><?php echo date('d M Y', strtotime($date)); ?></span>
            </div>
            <div class="summary-item">
                <i class="fas fa-clock"></i>
                <span><?php echo date('g:i A', strtotime($time)); ?></span>
            </div>
            <div class="summary-item">
                <i class="fas fa-users"></i>
                <span><?php echo $passengers; ?> pasajeros</span>
            </div>
            <div class="summary-item">
                <i class="fas fa-suitcase"></i>
                <span><?php echo $luggage; ?> maletas</span>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="results-header">
        <h2 class="results-count">
            <?php echo count($services); ?> shuttles disponibles
        </h2>
    </div>

    <?php if (empty($services)): ?>
        <div class="no-results">
            <i class="fas fa-inbox"></i>
            <h3>No se encontraron shuttles disponibles</h3>
            <p>Intenta modificar tu búsqueda o contacta directamente con los proveedores</p>
        </div>
    <?php else: ?>
        <?php foreach ($services as $service): ?>
            <div class="service-card">
                <img
                    src="<?php echo htmlspecialchars($service['cover_image'] ?: '/assets/placeholder-shuttle.jpg'); ?>"
                    alt="<?php echo htmlspecialchars($service['title']); ?>"
                    class="service-image"
                >

                <div class="service-info">
                    <h3><?php echo htmlspecialchars($service['affiliate_name']); ?></h3>
                    <p class="service-provider">
                        <i class="fas fa-shuttle-van"></i>
                        <?php echo htmlspecialchars($service['title']); ?>
                    </p>

                    <div class="service-features">
                        <div class="feature">
                            <i class="fas fa-users"></i>
                            <span>Hasta 4 pasajeros</span>
                        </div>
                        <div class="feature">
                            <i class="fas fa-suitcase"></i>
                            <span>Hasta 4 maletas</span>
                        </div>
                        <div class="feature">
                            <i class="fas fa-shield-check"></i>
                            <span>Vehículo asegurado</span>
                        </div>
                        <div class="feature">
                            <i class="fas fa-star"></i>
                            <span>Conductor profesional</span>
                        </div>
                    </div>

                    <div class="service-rating">
                        <div class="stars">
                            <?php
                            $rating = round($service['avg_rating']);
                            for ($i = 0; $i < 5; $i++) {
                                echo $i < $rating ? '★' : '☆';
                            }
                            ?>
                        </div>
                        <span><?php echo number_format($service['avg_rating'], 1); ?></span>
                        <span class="service-provider">(<?php echo $service['review_count']; ?> reviews)</span>
                    </div>
                </div>

                <div class="service-price-block">
                    <div class="price-label">Precio estimado</div>
                    <div class="price">₡<?php echo number_format(calculateEstimatedPrice($service['price_per_hour']), 0, ',', '.'); ?></div>
                    <a href="booking.php?service_id=<?php echo $service['id']; ?>&origin=<?php echo urlencode($origin); ?>&destination=<?php echo urlencode($destination); ?>&date=<?php echo urlencode($date); ?>&time=<?php echo urlencode($time); ?>&passengers=<?php echo $passengers; ?>&luggage=<?php echo $luggage; ?>" class="btn-book">
                        <i class="fas fa-calendar-check"></i> Reservar ahora
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
