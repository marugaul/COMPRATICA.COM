<?php
/**
 * Panel de Afiliados - Mis Servicios
 *
 * Lista todos los servicios del afiliado con opciones para:
 * - Crear nuevo servicio
 * - Editar servicio existente
 * - Activar/desactivar
 * - Configurar disponibilidad
 * - Ver reservas
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/affiliate_auth.php';

aff_require_login();
$pdo = db();
$aff_id = (int)$_SESSION['aff_id'];

// Obtener servicios del afiliado
$stmt = $pdo->prepare("
    SELECT
        s.*,
        sc.name as category_name,
        sc.icon as category_icon,
        COALESCE(AVG(sr.rating), 0) as avg_rating,
        COUNT(DISTINCT sr.id) as review_count,
        COUNT(DISTINCT sb.id) as booking_count
    FROM services s
    INNER JOIN service_categories sc ON sc.id = s.category_id
    LEFT JOIN service_reviews sr ON sr.service_id = s.id AND sr.is_approved = 1
    LEFT JOIN service_bookings sb ON sb.service_id = s.id
    WHERE s.affiliate_id = ?
    GROUP BY s.id
    ORDER BY s.created_at DESC
");
$stmt->execute([$aff_id]);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar si el afiliado ofrece servicios
$stmt = $pdo->prepare("SELECT offers_services FROM affiliates WHERE id = ?");
$stmt->execute([$aff_id]);
$offersServices = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Servicios - <?php echo APP_NAME; ?></title>
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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.6;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: white;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border-radius: var(--radius-lg);
            padding: 2rem;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .header-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 0.9375rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1.5rem;
            border-radius: var(--radius);
        }

        .stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
        }

        .alert {
            padding: 1.25rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .services-grid {
            display: grid;
            gap: 1.5rem;
        }

        .service-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: all 0.3s;
        }

        .service-card:hover {
            box-shadow: var(--shadow-lg);
        }

        .service-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            padding: 1.5rem;
            border-bottom: 2px solid var(--gray-100);
        }

        .service-info {
            flex: 1;
        }

        .service-category {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            background: var(--gray-100);
            border-radius: 20px;
            font-size: 0.875rem;
            color: var(--gray-700);
            margin-bottom: 0.75rem;
        }

        .service-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--gray-900);
        }

        .service-description {
            color: var(--gray-600);
            font-size: 0.9375rem;
            margin-bottom: 1rem;
        }

        .service-meta {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .meta-item i {
            color: var(--primary);
        }

        .service-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .service-body {
            padding: 1.5rem;
        }

        .service-footer {
            padding: 1rem 1.5rem;
            background: var(--gray-50);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .service-price {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
        }

        .price-label {
            font-size: 0.75rem;
            color: var(--gray-500);
            display: block;
        }

        .service-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--gray-700);
        }

        .empty-state p {
            color: var(--gray-500);
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header-top {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }

            .service-header {
                flex-direction: column;
                gap: 1rem;
            }

            .service-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .service-actions {
                width: 100%;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="header-top">
            <h1 class="header-title">
                <i class="fas fa-hands-helping"></i> Mis Servicios
            </h1>
            <div style="display: flex; gap: 0.75rem;">
                <a href="../dashboard.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
                <?php if ($offersServices): ?>
                    <a href="create.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Nuevo Servicio
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Servicios Activos</div>
                <div class="stat-value">
                    <?php echo count(array_filter($services, fn($s) => $s['is_active'])); ?>
                </div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, var(--accent), #0d9668);">
                <div class="stat-label">Total Reservas</div>
                <div class="stat-value">
                    <?php echo array_sum(array_column($services, 'booking_count')); ?>
                </div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, var(--warning), #d97706);">
                <div class="stat-label">Calificación Promedio</div>
                <div class="stat-value">
                    <?php
                    $avgRatings = array_column($services, 'avg_rating');
                    echo count($avgRatings) > 0 ? number_format(array_sum($avgRatings) / count($avgRatings), 1) : '0.0';
                    ?> ⭐
                </div>
            </div>
        </div>
    </div>

    <?php if (!$offersServices): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle" style="font-size: 1.5rem;"></i>
            <div>
                <strong>¡Atención!</strong> No tienes habilitada la opción de ofrecer servicios.
                <a href="../business_setup.php">Configura tu negocio aquí</a> para poder crear servicios.
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($services)): ?>
        <div class="empty-state">
            <i class="fas fa-hands-helping"></i>
            <h3>Aún no tienes servicios</h3>
            <p>Comienza a ofrecer tus servicios profesionales a través de nuestra plataforma</p>
            <?php if ($offersServices): ?>
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Crear Mi Primer Servicio
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="services-grid">
            <?php foreach ($services as $service): ?>
                <div class="service-card">
                    <div class="service-header">
                        <div class="service-info">
                            <div class="service-category">
                                <i class="<?php echo htmlspecialchars($service['category_icon']); ?>"></i>
                                <?php echo htmlspecialchars($service['category_name']); ?>
                            </div>
                            <h2 class="service-title"><?php echo htmlspecialchars($service['title']); ?></h2>
                            <p class="service-description">
                                <?php echo htmlspecialchars($service['short_description']); ?>
                            </p>
                            <div class="service-meta">
                                <div class="meta-item">
                                    <i class="fas fa-clock"></i>
                                    <?php echo $service['duration_minutes']; ?> min
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-star"></i>
                                    <?php echo number_format($service['avg_rating'], 1); ?> (<?php echo $service['review_count']; ?> reviews)
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar-check"></i>
                                    <?php echo $service['booking_count']; ?> reservas
                                </div>
                            </div>
                        </div>
                        <div class="service-status <?php echo $service['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                            <?php echo $service['is_active'] ? 'Activo' : 'Inactivo'; ?>
                        </div>
                    </div>

                    <div class="service-footer">
                        <div>
                            <span class="price-label">Precio por hora</span>
                            <div class="service-price">₡<?php echo number_format($service['price_per_hour'], 0, ',', '.'); ?></div>
                        </div>
                        <div class="service-actions">
                            <a href="edit.php?id=<?php echo $service['id']; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                            <a href="availability.php?id=<?php echo $service['id']; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-calendar-alt"></i> Disponibilidad
                            </a>
                            <a href="/service_detail.php?id=<?php echo $service['id']; ?>" class="btn btn-secondary btn-sm" target="_blank">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
