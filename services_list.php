<?php
/**
 * Listado de Servicios por Categoría
 *
 * Muestra servicios filtrados por categoría con:
 * - Filtros por precio y calificación
 * - Ordenamiento (popularidad, precio)
 * - Tarjetas de servicios con info básica
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

// Sesiones
$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0700, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['uid']) && $_SESSION['uid'] > 0;
$userName = $_SESSION['name'] ?? 'Usuario';

$pdo = db();

// Obtener categoría por slug
$categorySlug = $_GET['category'] ?? '';
$category = null;

if ($categorySlug) {
    $stmt = $pdo->prepare("SELECT * FROM service_categories WHERE slug = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$categorySlug]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$category) {
    header('Location: servicios.php');
    exit;
}

// Parámetros de filtrado y ordenamiento
$orderBy = $_GET['order'] ?? 'rating'; // rating, price_asc, price_desc
$minPrice = isset($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 999999;
$minRating = isset($_GET['min_rating']) ? (int)$_GET['min_rating'] : 0;

// Construir query
$sql = "
    SELECT
        s.*,
        a.name as affiliate_name,
        a.avatar as affiliate_avatar,
        COALESCE(AVG(sr.rating), 0) as avg_rating,
        COUNT(DISTINCT sr.id) as review_count
    FROM services s
    INNER JOIN affiliates a ON a.id = s.affiliate_id
    LEFT JOIN service_reviews sr ON sr.service_id = s.id AND sr.is_approved = 1
    WHERE s.category_id = ?
      AND s.is_active = 1
      AND s.price_per_hour >= ?
      AND s.price_per_hour <= ?
    GROUP BY s.id
";

// Filtro de rating
if ($minRating > 0) {
    $sql .= " HAVING avg_rating >= " . (int)$minRating;
}

// Ordenamiento
switch ($orderBy) {
    case 'price_asc':
        $sql .= " ORDER BY s.price_per_hour ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY s.price_per_hour DESC";
        break;
    case 'rating':
    default:
        $sql .= " ORDER BY avg_rating DESC, review_count DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$category['id'], $minPrice, $maxPrice]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cantidadProductos = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $it) {
        $cantidadProductos += (int)($it['qty'] ?? 0);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category['name']); ?> - Servicios | <?php echo APP_NAME; ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">

    <style>
        :root {
            --primary: #1a73e8;
            --primary-dark: #1557b0;
            --secondary: #7c3aed;
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

        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 3rem 2rem;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            font-size: 1.125rem;
            opacity: 0.9;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
            text-decoration: none;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .back-link:hover {
            opacity: 1;
        }

        .filters-bar {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .filter-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .filter-select, .filter-input {
            padding: 0.625rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 0.9375rem;
            transition: all 0.3s;
        }

        .filter-select:focus, .filter-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .filter-btn {
            padding: 0.75rem 1.5rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            margin-top: auto;
        }

        .filter-btn:hover {
            background: var(--primary-dark);
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .results-count {
            font-size: 1.125rem;
            color: var(--gray-700);
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .service-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .service-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .service-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(135deg, var(--gray-200), var(--gray-300));
        }

        .service-content {
            padding: 1.5rem;
        }

        .service-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--gray-900);
        }

        .service-description {
            font-size: 0.9375rem;
            color: var(--gray-600);
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .service-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .rating {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .star {
            color: var(--warning);
        }

        .service-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }

        .service-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .price-label {
            font-size: 0.75rem;
            color: var(--gray-500);
            display: block;
        }

        .service-provider {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .provider-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--gray-300);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .provider-name {
            font-size: 0.875rem;
            color: var(--gray-700);
        }

        .no-results {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-500);
        }

        .no-results i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: var(--gray-300);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .page-header {
                padding: 2rem 1rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .filters-bar {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="page-header">
    <div class="container">
        <a href="servicios.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Volver a categorías
        </a>
        <h1 class="page-title">
            <i class="<?php echo htmlspecialchars($category['icon']); ?>"></i>
            <?php echo htmlspecialchars($category['name']); ?>
        </h1>
        <p class="page-subtitle"><?php echo htmlspecialchars($category['description']); ?></p>
    </div>
</div>

<div class="container">
    <!-- Filtros -->
    <form class="filters-bar" method="GET">
        <input type="hidden" name="category" value="<?php echo htmlspecialchars($categorySlug); ?>">

        <div class="filter-group">
            <label class="filter-label">Ordenar por</label>
            <select name="order" class="filter-select">
                <option value="rating" <?php echo $orderBy === 'rating' ? 'selected' : ''; ?>>Mejor calificados</option>
                <option value="price_asc" <?php echo $orderBy === 'price_asc' ? 'selected' : ''; ?>>Precio: Menor a Mayor</option>
                <option value="price_desc" <?php echo $orderBy === 'price_desc' ? 'selected' : ''; ?>>Precio: Mayor a Menor</option>
            </select>
        </div>

        <div class="filter-group">
            <label class="filter-label">Precio mínimo</label>
            <input type="number" name="min_price" class="filter-input" placeholder="₡0" value="<?php echo $minPrice > 0 ? $minPrice : ''; ?>" style="width: 120px;">
        </div>

        <div class="filter-group">
            <label class="filter-label">Precio máximo</label>
            <input type="number" name="max_price" class="filter-input" placeholder="₡99999" value="<?php echo $maxPrice < 999999 ? $maxPrice : ''; ?>" style="width: 120px;">
        </div>

        <div class="filter-group">
            <label class="filter-label">Calificación mínima</label>
            <select name="min_rating" class="filter-select">
                <option value="0">Todas</option>
                <option value="4" <?php echo $minRating === 4 ? 'selected' : ''; ?>>4+ ⭐</option>
                <option value="3" <?php echo $minRating === 3 ? 'selected' : ''; ?>>3+ ⭐</option>
            </select>
        </div>

        <button type="submit" class="filter-btn">
            <i class="fas fa-filter"></i> Aplicar filtros
        </button>
    </form>

    <!-- Resultados -->
    <div class="results-header">
        <div class="results-count">
            <strong><?php echo count($services); ?></strong> servicios encontrados
        </div>
    </div>

    <!-- Grid de servicios -->
    <?php if (empty($services)): ?>
        <div class="no-results">
            <i class="fas fa-inbox"></i>
            <h3>No se encontraron servicios</h3>
            <p>Intenta ajustar los filtros o vuelve más tarde</p>
        </div>
    <?php else: ?>
        <div class="services-grid">
            <?php foreach ($services as $service): ?>
                <a href="service_detail.php?id=<?php echo $service['id']; ?>" class="service-card">
                    <img
                        src="<?php echo $service['cover_image'] ? htmlspecialchars($service['cover_image']) : '/assets/placeholder-service.jpg'; ?>"
                        alt="<?php echo htmlspecialchars($service['title']); ?>"
                        class="service-image"
                    >

                    <div class="service-content">
                        <h3 class="service-title"><?php echo htmlspecialchars($service['title']); ?></h3>

                        <p class="service-description">
                            <?php echo htmlspecialchars($service['short_description'] ?: $service['description']); ?>
                        </p>

                        <div class="service-meta">
                            <div class="meta-item">
                                <div class="rating">
                                    <?php
                                    $rating = round($service['avg_rating'], 1);
                                    for ($i = 1; $i <= 5; $i++):
                                    ?>
                                        <i class="fas fa-star star" style="<?php echo $i > $rating ? 'opacity: 0.3;' : ''; ?>"></i>
                                    <?php endfor; ?>
                                    <span><?php echo $rating; ?></span>
                                </div>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-comment"></i>
                                <?php echo $service['review_count']; ?> reviews
                            </div>
                        </div>

                        <div class="service-footer">
                            <div>
                                <span class="price-label">Desde</span>
                                <div class="service-price">
                                    ₡<?php echo number_format($service['price_per_hour'], 0, ',', '.'); ?>
                                </div>
                                <span class="price-label">por hora</span>
                            </div>

                            <div class="service-provider">
                                <div class="provider-avatar">
                                    <?php echo strtoupper(substr($service['affiliate_name'], 0, 1)); ?>
                                </div>
                                <div class="provider-name"><?php echo htmlspecialchars($service['affiliate_name']); ?></div>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
