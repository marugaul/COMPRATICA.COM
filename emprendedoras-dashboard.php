<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);

$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/logger.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['uid']) || $_SESSION['uid'] <= 0) {
    header('Location: emprendedoras-login.php');
    exit;
}

$userId = $_SESSION['uid'];
$userName = $_SESSION['name'] ?? 'Usuario';
$userEmail = $_SESSION['email'] ?? '';

$pdo = db();

// Obtener suscripción más reciente (activa o pendiente)
try {
    $stmt = $pdo->prepare("
        SELECT s.*, p.name as plan_name, p.max_products, p.commission_rate
        FROM entrepreneur_subscriptions s
        JOIN entrepreneur_plans p ON s.plan_id = p.id
        WHERE s.user_id = ? AND s.status IN ('active', 'pending')
        ORDER BY s.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    header('Location: emprendedoras-planes.php');
    exit;
}

// Sin suscripción → ir a planes
if (!$subscription) {
    header('Location: emprendedoras-planes.php');
    exit;
}

$isPending = $subscription['status'] === 'pending';

// Obtener estadísticas de productos
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_products,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_products,
        SUM(views_count) as total_views,
        SUM(sales_count) as total_sales
    FROM entrepreneur_products
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener productos
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM entrepreneur_products p
    LEFT JOIN entrepreneur_categories c ON p.category_id = c.id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$userId]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener pedidos recientes
$stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name
    FROM entrepreneur_orders o
    JOIN entrepreneur_products p ON o.product_id = p.id
    WHERE o.seller_user_id = ?
    ORDER BY o.created_at DESC
    LIMIT 10
");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cantidadProductos = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $it) {
        $cantidadProductos += (int)($it['qty'] ?? 0);
    }
}
$isLoggedIn = true;

// ── Manejar toggle EN VIVO ────────────────────────────────────────────────
$liveMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_live') {
    $goLive    = (int)($_POST['go_live'] ?? 0);
    $liveTitle = trim($_POST['live_title'] ?? '');
    $liveLink  = trim($_POST['live_link'] ?? '');
    if ($goLive) {
        $pdo->prepare("UPDATE users SET is_live=1, live_title=?, live_link=?, live_started_at=datetime('now') WHERE id=?")
            ->execute([$liveTitle ?: 'EN VIVO', $liveLink ?: null, $userId]);
        $liveMsg = ['ok', '🔴 ¡Estás EN VIVO! Tu puesto aparece primero en el mercadito.'];
    } else {
        $pdo->prepare("UPDATE users SET is_live=0, live_title=NULL, live_link=NULL, live_started_at=NULL WHERE id=?")
            ->execute([$userId]);
        $liveMsg = ['info', '⚫ Transmisión finalizada. Tu puesto volvió al orden normal.'];
    }
    header('Location: emprendedoras-dashboard.php#live-section');
    exit;
}

// Cargar datos de live actuales
try {
    $liveData = $pdo->prepare("SELECT COALESCE(is_live,0) AS is_live, live_title, live_link FROM users WHERE id=?");
    $liveData->execute([$userId]);
    $liveData = $liveData->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $_e) {
    $liveData = ['is_live' => 0, 'live_title' => '', 'live_link' => ''];
}

// Verificar límite de productos
$canAddProducts = true;
if ($subscription['max_products'] > 0 && $stats['total_products'] >= $subscription['max_products']) {
    $canAddProducts = false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Emprendedoras</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/compratica-header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
        }
        .dashboard-header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .plan-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stat-card .icon {
            font-size: 2.5rem;
            color: #667eea;
            margin-bottom: 10px;
        }
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        .stat-card .label {
            color: #666;
            font-size: 0.9rem;
        }
        .section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .section-header h2 {
            font-size: 1.5rem;
            color: #333;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .product-card {
            border: 1px solid #e0e0e0;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s;
        }
        .product-card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transform: translateY(-5px);
        }
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f5f5f5;
        }
        .product-info {
            padding: 15px;
        }
        .product-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .product-price {
            color: #667eea;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .product-stats {
            display: flex;
            justify-content: space-between;
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 10px;
        }
        .product-actions {
            display: flex;
            gap: 10px;
        }
        .btn-small {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-edit {
            background: #667eea;
            color: white;
        }
        .btn-edit:hover {
            background: #5568d3;
        }
        .btn-delete {
            background: #ef4444;
            color: white;
        }
        .btn-delete:hover {
            background: #dc2626;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        .orders-table th,
        .orders-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        .orders-table th {
            background: #f9fafb;
            font-weight: 600;
            color: #333;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-confirmed {
            background: #d1fae5;
            color: #065f46;
        }
        .status-completed {
            background: #dbeafe;
            color: #1e40af;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>👋 Hola, <?php echo htmlspecialchars($userName); ?></h1>
            <p>Bienvenida a tu dashboard de emprendedora</p>
            <div class="plan-badge">
                <i class="fas fa-crown"></i> <?php echo htmlspecialchars($subscription['plan_name']); ?>
                <?php if ($isPending): ?>
                    &nbsp;— <span style="color:#fde68a;">⏳ Pendiente de aprobación</span>
                <?php else: ?>
                    &nbsp;— <span style="color:#bbf7d0;">✅ Activo</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isPending): ?>
        <div style="background:#fffbeb;border:2px solid #f59e0b;border-radius:16px;padding:30px 35px;margin-bottom:30px;display:flex;align-items:flex-start;gap:20px;">
            <div style="font-size:3rem;line-height:1;">⏳</div>
            <div>
                <h3 style="margin:0 0 8px;color:#92400e;font-size:1.25rem;">Tu suscripción está pendiente de aprobación</h3>
                <p style="margin:0 0 12px;color:#78350f;line-height:1.6;">
                    Recibimos tu comprobante de pago. Un administrador lo verificará y activará tu cuenta a la brevedad.<br>
                    Recibirás un correo cuando tu cuenta sea aprobada.
                </p>
                <table style="border-collapse:collapse;font-size:0.9rem;color:#78350f;">
                    <tr>
                        <td style="padding:3px 12px 3px 0;font-weight:600;">Plan:</td>
                        <td><?php echo htmlspecialchars($subscription['plan_name']); ?></td>
                    </tr>
                    <tr>
                        <td style="padding:3px 12px 3px 0;font-weight:600;">Método de pago:</td>
                        <td><?php echo ucfirst(htmlspecialchars($subscription['payment_method'] ?? 'N/A')); ?></td>
                    </tr>
                    <tr>
                        <td style="padding:3px 12px 3px 0;font-weight:600;">Solicitud enviada:</td>
                        <td><?php echo date('d/m/Y H:i', strtotime($subscription['created_at'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon"><i class="fas fa-box"></i></div>
                <div class="value"><?php echo number_format($stats['total_products']); ?></div>
                <div class="label">Productos Totales</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <div class="value"><?php echo number_format($stats['active_products']); ?></div>
                <div class="label">Productos Activos</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-eye"></i></div>
                <div class="value"><?php echo number_format($stats['total_views']); ?></div>
                <div class="label">Vistas Totales</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="value"><?php echo number_format($stats['total_sales']); ?></div>
                <div class="label">Ventas Totales</div>
            </div>
        </div>

        <?php if (!$isPending && !$canAddProducts): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Límite alcanzado:</strong> Has llegado al límite de productos de tu plan (<?php echo $subscription['max_products']; ?> productos).
                <a href="emprendedoras-planes.php" style="color: #667eea; font-weight: 600;">Actualiza tu plan</a> para agregar más productos.
            </div>
        <?php endif; ?>

        <!-- ── SECCIÓN EN VIVO ──────────────────────────────────────── -->
        <div class="section" id="live-section" style="border: 2px solid <?= $liveData['is_live'] ? '#ef4444' : '#e2e8f0' ?>; transition: border-color .3s;">
            <div class="section-header">
                <h2>
                    <?php if ($liveData['is_live']): ?>
                        <span style="display:inline-flex;align-items:center;gap:8px;">
                            <span style="width:12px;height:12px;background:#ef4444;border-radius:50%;display:inline-block;animation:live-pulse 1.2s infinite;"></span>
                            EN VIVO ahora
                        </span>
                    <?php else: ?>
                        <i class="fas fa-broadcast-tower"></i> Transmisión en Vivo
                    <?php endif; ?>
                </h2>
                <?php if ($liveData['is_live']): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="toggle_live">
                        <input type="hidden" name="go_live" value="0">
                        <button type="submit" style="background:#ef4444;color:white;border:none;padding:10px 20px;border-radius:8px;font-weight:700;cursor:pointer;">
                            <i class="fas fa-stop-circle"></i> Terminar Live
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <style>
            @keyframes live-pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
            .live-toggle-card { background: #fafafa; border-radius: 12px; padding: 20px; }
            .live-field { margin-bottom: 14px; }
            .live-field label { display:block; font-weight:600; margin-bottom:6px; color:#555; font-size:.9rem; }
            .live-field input { width:100%; padding:10px 14px; border:2px solid #e0e0e0; border-radius:8px; font-size:.95rem; box-sizing:border-box; }
            .live-field input:focus { border-color:#667eea; outline:none; }
            .btn-go-live { background:linear-gradient(135deg,#ef4444,#dc2626); color:white; border:none; padding:12px 28px; border-radius:10px; font-weight:700; font-size:1rem; cursor:pointer; display:inline-flex;align-items:center;gap:8px; transition:all .2s; }
            .btn-go-live:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(239,68,68,.4); }
            </style>

            <?php if ($liveData['is_live']): ?>
                <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:16px 20px;display:flex;align-items:center;gap:14px;">
                    <span style="font-size:2rem;">🔴</span>
                    <div>
                        <strong style="color:#dc2626;font-size:1.05rem;"><?= htmlspecialchars($liveData['live_title'] ?? 'EN VIVO') ?></strong><br>
                        <?php if ($liveData['live_link']): ?>
                            <a href="<?= htmlspecialchars($liveData['live_link']) ?>" target="_blank" style="color:#667eea;font-size:.88rem;">
                                <i class="fas fa-external-link-alt"></i> <?= htmlspecialchars($liveData['live_link']) ?>
                            </a>
                        <?php endif; ?>
                        <div style="color:#888;font-size:.82rem;margin-top:4px;">Tu puesto aparece primero en el mercadito con el badge EN VIVO.</div>
                    </div>
                </div>
            <?php else: ?>
                <div class="live-toggle-card">
                    <p style="color:#666;margin:0 0 16px;font-size:.93rem;">
                        <i class="fas fa-info-circle" style="color:#667eea;"></i>
                        Inicia tu transmisión en YouTube, Instagram o donde prefieras, pega el link aquí y activa el modo EN VIVO. Tu puesto aparecerá <strong>primero en el mercadito</strong> con un badge rojo pulsante.
                    </p>
                    <form method="POST">
                        <input type="hidden" name="action" value="toggle_live">
                        <input type="hidden" name="go_live" value="1">
                        <div class="live-field">
                            <label><i class="fas fa-tag"></i> Título del live (ej: "Feria de Navidad 🎄")</label>
                            <input type="text" name="live_title" placeholder="Nueva colección de verano..." maxlength="80">
                        </div>
                        <div class="live-field">
                            <label><i class="fab fa-youtube"></i> Link del live (YouTube, Instagram, etc.)</label>
                            <input type="url" name="live_link" placeholder="https://youtube.com/live/...">
                        </div>
                        <button type="submit" class="btn-go-live">
                            <span style="width:10px;height:10px;background:white;border-radius:50%;display:inline-block;"></span>
                            Iniciar EN VIVO
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <!-- ── FIN EN VIVO ────────────────────────────────────────────── -->

        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-box"></i> Mis Productos</h2>
                <?php if ($isPending): ?>
                    <button class="btn-primary" style="opacity: 0.5; cursor: not-allowed;" disabled>
                        <i class="fas fa-lock"></i> Cuenta pendiente
                    </button>
                <?php elseif ($canAddProducts): ?>
                    <a href="emprendedoras-producto-crear.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Agregar Producto
                    </a>
                <?php else: ?>
                    <button class="btn-primary" style="opacity: 0.5; cursor: not-allowed;" disabled>
                        <i class="fas fa-plus"></i> Agregar Producto
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($isPending): ?>
                <div class="empty-state">
                    <i class="fas fa-lock" style="color:#f59e0b;"></i>
                    <h3>Sección bloqueada</h3>
                    <p>Podrás agregar productos una vez que tu suscripción sea aprobada por el administrador.</p>
                </div>
            <?php elseif (empty($products)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No tienes productos aún</h3>
                    <p>Comienza a vender agregando tu primer producto</p>
                    <?php if ($canAddProducts): ?>
                        <a href="emprendedoras-producto-crear.php" class="btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-plus"></i> Agregar mi primer producto
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="product-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <?php if ($product['image_1']): ?>
                                <img src="<?php echo htmlspecialchars($product['image_1']); ?>" class="product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <?php else: ?>
                                <div class="product-image" style="display: flex; align-items: center; justify-content: center; color: #999;">
                                    <i class="fas fa-image" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="product-info">
                                <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="product-price">₡<?php echo number_format($product['price'], 0); ?></div>
                                <div class="product-stats">
                                    <span><i class="fas fa-eye"></i> <?php echo $product['views_count']; ?></span>
                                    <span><i class="fas fa-shopping-cart"></i> <?php echo $product['sales_count']; ?></span>
                                    <span><?php echo $product['is_active'] ? '<i class="fas fa-check-circle" style="color: #4ade80;"></i>' : '<i class="fas fa-times-circle" style="color: #ef4444;"></i>'; ?></span>
                                </div>
                                <div class="product-actions">
                                    <a href="emprendedoras-producto-editar.php?id=<?php echo $product['id']; ?>" class="btn-small btn-edit">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <button class="btn-small btn-delete" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-receipt"></i> Pedidos Recientes</h2>
                <a href="emprendedoras-orders.php" class="btn-primary">Ver todos</a>
            </div>

            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>No hay pedidos aún</h3>
                    <p>Los pedidos de tus productos aparecerán aquí</p>
                </div>
            <?php else: ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Pedido #</th>
                            <th>Producto</th>
                            <th>Cliente</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id']; ?></td>
                                <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($order['buyer_name']); ?></td>
                                <td>₡<?php echo number_format($order['total_price'], 0); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function deleteProduct(productId) {
            if (confirm('¿Estás segura de que quieres eliminar este producto?')) {
                window.location.href = 'emprendedoras-producto-eliminar.php?id=' + productId;
            }
        }
    </script>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
