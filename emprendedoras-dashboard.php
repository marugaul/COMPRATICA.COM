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
require_once __DIR__ . '/includes/chat_helpers.php';
require_once __DIR__ . '/includes/live_cam.php';

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

$isPending   = $subscription['status'] === 'pending';
$isPaidPlan  = !$isPending && ($subscription['price_monthly'] ?? 0) > 0;
// Fallback: consultar directamente por si el join no trajo price_monthly
if (!isset($subscription['price_monthly'])) {
    $isPaidPlan = hasPaidPlan($pdo, $userId);
}

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

// Inicializar tablas de cámara live (también agrega columnas si no existen)
initLiveCamTables($pdo);

// Cargar datos de live actuales
try {
    $liveData = $pdo->prepare("SELECT COALESCE(is_live,0) AS is_live, live_title, live_link,
                                      COALESCE(live_type,'link') AS live_type, live_session_id
                               FROM users WHERE id=?");
    $liveData->execute([$userId]);
    $liveData = $liveData->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $_e) {
    $liveData = ['is_live' => 0, 'live_title' => '', 'live_link' => '', 'live_type' => 'link', 'live_session_id' => ''];
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
        <?php if (!$isPaidPlan): ?>
        <div class="section" id="live-section" style="border: 2px solid #e2e8f0; opacity:.6; pointer-events:none;">
            <div class="section-header">
                <h2><i class="fas fa-broadcast-tower"></i> Transmisión en Vivo</h2>
                <span style="background:#f3f4f6;color:#6b7280;padding:6px 14px;border-radius:8px;font-size:.85rem;font-weight:600;">
                    <i class="fas fa-lock"></i> Solo planes de pago
                </span>
            </div>
            <div style="background:#fafafa;border-radius:12px;padding:20px;text-align:center;color:#9ca3af;">
                <i class="fas fa-video-slash" style="font-size:2.5rem;margin-bottom:12px;display:block;"></i>
                Activa un <strong>Plan Emprendedora</strong> o <strong>Plan Premium</strong> para usar el Live.
                <br><br>
                <a href="emprendedoras-planes.php" style="background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:10px 22px;border-radius:10px;text-decoration:none;font-weight:700;display:inline-block;">
                    <i class="fas fa-arrow-up"></i> Ver Planes
                </a>
            </div>
        </div>
        <?php else: ?>
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

            <style>
            .live-mode-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
            @media(max-width:600px){ .live-mode-grid { grid-template-columns:1fr; } }
            .live-mode-card {
                border:2px solid #e2e8f0; border-radius:14px; padding:20px;
                cursor:pointer; transition:all .2s; text-align:center; background:#fafafa;
            }
            .live-mode-card:hover { border-color:#667eea; background:#f5f3ff; }
            .live-mode-card.selected { border-color:#667eea; background:#f5f3ff; box-shadow:0 0 0 3px rgba(102,126,234,.15); }
            .live-mode-card i { font-size:2.2rem; display:block; margin-bottom:10px; }
            .live-mode-card h4 { margin:0 0 6px; font-size:1rem; color:#1f2937; }
            .live-mode-card p { margin:0; font-size:.82rem; color:#6b7280; line-height:1.4; }
            #cam-preview-wrap {
                position:relative; background:#000; border-radius:12px; overflow:hidden;
                margin-bottom:14px; aspect-ratio:16/9; max-width:480px;
            }
            #cam-preview { width:100%; height:100%; object-fit:cover; display:block; transform:scaleX(-1); }
            .cam-live-badge {
                position:absolute; top:10px; left:10px;
                background:rgba(220,38,38,.9); color:white; border-radius:20px;
                padding:3px 10px; font-size:.78rem; font-weight:700;
                display:flex; align-items:center; gap:5px;
            }
            .cam-live-badge .dot { width:8px;height:8px;background:white;border-radius:50%;animation:live-pulse 1s infinite; }
            #cam-timer { position:absolute; top:10px; right:10px; background:rgba(0,0,0,.6);
                color:white; border-radius:8px; padding:3px 9px; font-size:.8rem; font-family:monospace; }
            </style>

            <?php if ($liveData['is_live'] && $liveData['live_type'] === 'camera'): ?>
            <!-- ── CÁMARA: en vivo ── -->
            <div style="display:flex;flex-wrap:wrap;gap:20px;align-items:flex-start;">
                <div style="flex:1;min-width:260px;">
                    <div id="cam-preview-wrap">
                        <video id="cam-preview" autoplay muted playsinline></video>
                        <div class="cam-live-badge"><span class="dot"></span> EN VIVO</div>
                        <div id="cam-timer">00:00</div>
                    </div>
                    <p style="color:#888;font-size:.82rem;margin:6px 0;">
                        Tu cámara está transmitiendo en directo en tu tienda.
                    </p>
                </div>
                <div>
                    <strong style="color:#dc2626;font-size:1rem;display:block;margin-bottom:8px;">
                        <i class="fas fa-circle" style="animation:live-pulse 1s infinite;"></i>
                        <?= htmlspecialchars($liveData['live_title'] ?? 'En Vivo') ?>
                    </strong>
                    <button id="btn-stop-cam" onclick="stopCamLive()"
                            style="background:#ef4444;color:white;border:none;padding:11px 22px;border-radius:10px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-stop-circle"></i> Terminar Transmisión
                    </button>
                    <p style="color:#9ca3af;font-size:.78rem;margin-top:8px;">
                        <i class="fas fa-info-circle"></i> Al terminar, los clientes dejarán de verte.
                    </p>
                </div>
            </div>

            <?php elseif ($liveData['is_live'] && $liveData['live_type'] !== 'camera'): ?>
            <!-- ── LINK EXTERNO: en vivo ── -->
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
            <!-- ── No live: elegir modo ── -->
            <p style="color:#666;margin:0 0 14px;font-size:.9rem;">
                <i class="fas fa-info-circle" style="color:#667eea;"></i>
                Elige cómo quieres transmitir en vivo:
            </p>

            <!-- Selector de modo -->
            <div class="live-mode-grid">
                <div class="live-mode-card selected" id="card-link" onclick="selectLiveMode('link')">
                    <i class="fas fa-link" style="color:#667eea;"></i>
                    <h4>Link Externo</h4>
                    <p>YouTube, Facebook, Instagram, TikTok — pega tu link de stream</p>
                </div>
                <div class="live-mode-card" id="card-cam" onclick="selectLiveMode('cam')">
                    <i class="fas fa-video" style="color:#10b981;"></i>
                    <h4>Mi Cámara</h4>
                    <p>Transmite directo desde tu cámara web sin salir de la página</p>
                </div>
            </div>

            <!-- Formulario modo LINK -->
            <div id="form-link">
                <div class="live-toggle-card">
                    <form method="POST">
                        <input type="hidden" name="action" value="toggle_live">
                        <input type="hidden" name="go_live" value="1">
                        <div class="live-field">
                            <label><i class="fas fa-tag"></i> Título del live</label>
                            <input type="text" name="live_title" placeholder='Ej: "Nueva colección de verano 🌸"' maxlength="80">
                        </div>
                        <div class="live-field">
                            <label><i class="fab fa-youtube"></i> Link del live (YouTube, Facebook, Instagram, TikTok)</label>
                            <input type="url" name="live_link" placeholder="https://youtube.com/live/...">
                        </div>
                        <button type="submit" class="btn-go-live">
                            <span style="width:10px;height:10px;background:white;border-radius:50%;display:inline-block;"></span>
                            Iniciar EN VIVO con Link
                        </button>
                    </form>
                </div>
            </div>

            <!-- Panel modo CÁMARA -->
            <div id="form-cam" style="display:none;">
                <div class="live-toggle-card" style="background:#f0fdf4;">
                    <div class="live-field">
                        <label><i class="fas fa-tag"></i> Título del live</label>
                        <input type="text" id="cam-title-input" placeholder='Ej: "Mostrando nueva colección 📦"' maxlength="80"
                               style="width:100%;padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:.95rem;box-sizing:border-box;">
                    </div>
                    <div id="cam-preview-wrap" style="display:none;">
                        <video id="cam-preview" autoplay muted playsinline></video>
                        <div class="cam-live-badge"><span class="dot"></span> EN VIVO</div>
                        <div id="cam-timer">00:00</div>
                    </div>
                    <div id="cam-controls" style="display:flex;gap:12px;flex-wrap:wrap;margin-top:4px;">
                        <button id="btn-preview-cam" onclick="previewCamera()"
                                style="background:#374151;color:white;border:none;padding:11px 20px;border-radius:10px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;">
                            <i class="fas fa-camera"></i> Probar cámara
                        </button>
                        <button id="btn-start-cam" onclick="startCamLive()" disabled
                                class="btn-go-live" style="background:linear-gradient(135deg,#10b981,#059669);">
                            <span style="width:10px;height:10px;background:white;border-radius:50%;display:inline-block;"></span>
                            Iniciar EN VIVO con Cámara
                        </button>
                    </div>
                    <p id="cam-status-msg" style="color:#6b7280;font-size:.82rem;margin:8px 0 0;"></p>
                </div>
            </div>
            <?php endif; /* is_live modes */?>
        </div>
        </div><!-- /.section live-section (plan pago) -->
        <?php endif; /* isPaidPlan */ ?>
        <!-- ── FIN EN VIVO ────────────────────────────────────────────── -->

        <?php if ($isPaidPlan && $liveData['is_live']): ?>
        <!-- ── PANEL DE CHAT EN VIVO (vendedora) ─────────────────────── -->
        <div class="section" id="chat-seller-section">
            <div class="section-header">
                <h2>
                    <span style="display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-comments" style="color:#667eea;"></i>
                        Chat en Vivo
                        <span id="seller-chat-new-badge" style="display:none;background:#ef4444;color:white;border-radius:20px;padding:2px 8px;font-size:.75rem;font-weight:800;"></span>
                    </span>
                </h2>
                <span style="color:#10b981;font-size:.85rem;font-weight:600;">
                    <i class="fas fa-circle" style="font-size:.6rem;"></i> Actualiza cada 3s
                </span>
            </div>

            <style>
            .seller-chat-wrap { display:grid; grid-template-columns:1fr 340px; gap:20px; }
            @media(max-width:800px){ .seller-chat-wrap { grid-template-columns:1fr; } }
            #seller-chat-log {
                background:#f8f9ff; border-radius:12px; padding:14px;
                height:320px; overflow-y:auto; display:flex; flex-direction:column; gap:8px;
                border:1px solid #e5e7eb;
            }
            .schat-msg { max-width:90%; }
            .schat-msg.from-client { align-self:flex-start; }
            .schat-msg.from-seller { align-self:flex-end; }
            .schat-bubble {
                padding:9px 13px; border-radius:12px; font-size:.88rem; word-break:break-word;
                line-height:1.45;
            }
            .schat-msg.from-client .schat-bubble {
                background:white; color:#1f2937; box-shadow:0 1px 4px rgba(0,0,0,.08);
                border-bottom-left-radius:4px;
            }
            .schat-msg.from-seller .schat-bubble {
                background:#667eea; color:white; border-bottom-right-radius:4px;
            }
            .schat-msg.private .schat-bubble {
                background:#f0fdf4; color:#166534; border:1px dashed #86efac;
            }
            .schat-meta { font-size:.72rem; color:#9ca3af; margin-top:3px; padding:0 4px; }
            .schat-meta.right { text-align:right; }
            .schat-actions { display:flex; gap:6px; margin-top:4px; flex-wrap:wrap; }
            .schat-actions button {
                font-size:.72rem; padding:3px 9px; border-radius:6px;
                border:none; cursor:pointer; font-weight:600; transition:all .15s;
            }
            .btn-reply-pub { background:#dbeafe; color:#1d4ed8; }
            .btn-reply-priv { background:#d1fae5; color:#065f46; }
            .btn-ban { background:#fee2e2; color:#991b1b; }
            .btn-unban { background:#fef3c7; color:#92400e; }
            #seller-reply-area { background:#f9fafb; border-radius:12px; padding:16px; border:1px solid #e5e7eb; }
            #seller-reply-area h4 { margin:0 0 10px; font-size:.9rem; color:#374151; }
            #seller-reply-mode { font-size:.82rem; color:#059669; font-weight:600; margin-bottom:8px; min-height:20px; }
            #seller-reply-input {
                width:100%; box-sizing:border-box; border:2px solid #e5e7eb; border-radius:10px;
                padding:10px 12px; font-size:.9rem; resize:none; font-family:inherit;
                min-height:80px;
            }
            #seller-reply-input:focus { border-color:#667eea; outline:none; }
            .seller-reply-btns { display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
            .seller-reply-btns button {
                padding:9px 18px; border-radius:10px; border:none; cursor:pointer;
                font-weight:700; font-size:.88rem; transition:all .2s;
            }
            #btn-broadcast { background:linear-gradient(135deg,#667eea,#764ba2); color:white; }
            #btn-send-pub  { background:#2563eb; color:white; }
            #btn-send-priv { background:#059669; color:white; }
            #btn-cancel-reply { background:#f3f4f6; color:#374151; }
            .banned-users-list { margin-top:14px; }
            .banned-users-list h5 { font-size:.82rem; color:#6b7280; margin:0 0 8px; }
            .banned-chip {
                display:inline-flex; align-items:center; gap:6px; background:#fef2f2;
                border:1px solid #fecaca; color:#991b1b; border-radius:20px;
                padding:3px 10px; font-size:.78rem; margin:3px;
            }
            .banned-chip button { background:none;border:none;cursor:pointer;color:#dc2626;padding:0;font-size:.9rem; }
            </style>

            <div class="seller-chat-wrap">
                <!-- Columna 1: historial -->
                <div>
                    <div id="seller-chat-log">
                        <div style="text-align:center;color:#9ca3af;font-size:.85rem;padding:20px;" id="seller-chat-empty">
                            <i class="fas fa-comment-slash" style="font-size:1.8rem;display:block;margin-bottom:8px;color:#d1d5db;"></i>
                            Esperando preguntas de tus clientes...
                        </div>
                    </div>
                </div>
                <!-- Columna 2: respuesta + baneados -->
                <div>
                    <div id="seller-reply-area">
                        <h4><i class="fas fa-paper-plane"></i> Enviar mensaje</h4>
                        <div id="seller-reply-mode">Transmisión a todos los clientes</div>
                        <textarea id="seller-reply-input" maxlength="500"
                            placeholder="Escribe tu mensaje..." rows="3"></textarea>
                        <div class="seller-reply-btns">
                            <button id="btn-broadcast" onclick="sellerSend(1,null)">
                                <i class="fas fa-bullhorn"></i> A todos
                            </button>
                            <button id="btn-send-pub" style="display:none" onclick="sellerSendReply(1)">
                                <i class="fas fa-globe"></i> En público
                            </button>
                            <button id="btn-send-priv" style="display:none" onclick="sellerSendReply(0)">
                                <i class="fas fa-lock"></i> Privado
                            </button>
                            <button id="btn-cancel-reply" style="display:none" onclick="cancelReply()">
                                Cancelar
                            </button>
                        </div>
                    </div>
                    <div class="banned-users-list" id="banned-list-wrap" style="display:none;">
                        <h5><i class="fas fa-ban"></i> Usuarios baneados</h5>
                        <div id="banned-chips"></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- ── FIN PANEL CHAT (vendedora) ───────────────────────────── -->
        <?php elseif ($isPaidPlan && !$liveData['is_live']): ?>
        <div class="section" style="background:#f8f9ff;border:2px dashed #c7d2fe;">
            <div style="text-align:center;padding:16px;color:#6b7280;">
                <i class="fas fa-comments" style="font-size:2rem;color:#a5b4fc;display:block;margin-bottom:8px;"></i>
                <strong>Chat en Vivo disponible</strong> — Inicia tu Live para activar el chat con tus clientes.
            </div>
        </div>
        <?php endif; ?>

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

    <?php if ($isPaidPlan && $liveData['is_live']): ?>
    <script>
    (function(){
    const SELLER_ID = <?= (int)$userId ?>;
    let lastId     = 0;
    let replyToUid = null;
    let bansMap    = {};  // uid → name

    const log      = document.getElementById('seller-chat-log');
    const empty    = document.getElementById('seller-chat-empty');
    const replyInp = document.getElementById('seller-reply-input');
    const replyMode= document.getElementById('seller-reply-mode');
    const newBadge = document.getElementById('seller-chat-new-badge');
    const bannedWrap= document.getElementById('banned-list-wrap');
    const bannedChips= document.getElementById('banned-chips');
    const btnBcast = document.getElementById('btn-broadcast');
    const btnPub   = document.getElementById('btn-send-pub');
    const btnPriv  = document.getElementById('btn-send-priv');
    const btnCancel= document.getElementById('btn-cancel-reply');
    let newCount   = 0;

    function poll() {
        fetch('/api/chat-poll.php?seller_id=' + SELLER_ID + '&last_id=' + lastId, {credentials:'same-origin'})
        .then(r => r.json())
        .then(data => {
            if (data.messages && data.messages.length) {
                if (empty) empty.style.display = 'none';
                data.messages.forEach(appendMsg);
                lastId = data.messages[data.messages.length-1].id;
                newCount += data.messages.filter(m=>m.sender_type==='client').length;
                if (newCount > 0 && newBadge) {
                    newBadge.style.display='inline';
                    newBadge.textContent = newCount + ' nuevo' + (newCount>1?'s':'');
                }
            }
            if (data.bans) {
                bansMap = {};
                Object.entries(data.bans).forEach(([uid,name])=>{ bansMap[uid]=name; });
                renderBans();
            }
        })
        .catch(()=>{});
    }

    function appendMsg(m) {
        const isFromSeller = (m.sender_type === 'seller');
        const isPrivate    = (m.is_public == 0);
        const wrap = document.createElement('div');
        wrap.className = 'schat-msg ' + (isFromSeller ? 'from-seller' : 'from-client') + (isPrivate?' private':'');
        wrap.dataset.uid = m.sender_id;
        wrap.dataset.name= m.sender_name;

        let actHtml = '';
        if (!isFromSeller) {
            const bannedLabel = bansMap[m.sender_id] ? ' (baneado)' : '';
            actHtml = `<div class="schat-actions">
                <button class="btn-reply-pub" onclick="prepReply(${m.sender_id}, '${escJs(m.sender_name)}')">
                    <i class="fas fa-globe"></i> Resp. público</button>
                <button class="btn-reply-priv" onclick="prepReply(${m.sender_id}, '${escJs(m.sender_name)}', true)">
                    <i class="fas fa-lock"></i> Resp. privado</button>
                ${bansMap[m.sender_id]
                    ? `<button class="btn-unban" onclick="banUser(${m.sender_id},'unban')"><i class="fas fa-unlock"></i> Desbanear</button>`
                    : `<button class="btn-ban" onclick="banUser(${m.sender_id},'ban')"><i class="fas fa-ban"></i> Banear</button>`
                }
            </div>`;
        }

        wrap.innerHTML = (isPrivate ? '<div style="font-size:.7rem;color:#059669;font-weight:600;margin-bottom:3px;"><i class="fas fa-lock"></i> Privado</div>' : '') +
            (!isFromSeller ? `<div class="schat-meta">${escHtml(m.sender_name)}</div>` : '') +
            `<div class="schat-bubble">${escHtml(m.message)}</div>` +
            `<div class="schat-meta${isFromSeller?' right':''}">${m.time||''}</div>` +
            actHtml;

        if (log) { log.appendChild(wrap); log.scrollTop = log.scrollHeight; }
    }

    window.prepReply = function(uid, name, priv) {
        replyToUid = uid;
        if (replyMode) replyMode.innerHTML = priv
            ? `<i class="fas fa-lock"></i> Respuesta privada a <strong>${escHtml(name)}</strong>`
            : `<i class="fas fa-globe"></i> Respuesta pública a <strong>${escHtml(name)}</strong>`;
        if (btnBcast) btnBcast.style.display = 'none';
        if (btnPub)   btnPub.style.display   = priv ? 'none' : 'inline-flex';
        if (btnPriv)  btnPriv.style.display  = priv ? 'inline-flex' : 'none';
        if (btnCancel)btnCancel.style.display= 'inline-flex';
        if (replyInp) replyInp.focus();
    };

    window.cancelReply = function() {
        replyToUid = null;
        if (replyMode) replyMode.textContent = 'Transmisión a todos los clientes';
        if (btnBcast) btnBcast.style.display = 'inline-flex';
        if (btnPub)   btnPub.style.display   = 'none';
        if (btnPriv)  btnPriv.style.display  = 'none';
        if (btnCancel)btnCancel.style.display= 'none';
    };

    window.sellerSend = function(isPublic, privateTo) {
        const txt = replyInp ? replyInp.value.trim() : '';
        if (!txt) return;
        const body = `seller_id=${SELLER_ID}&message=${encodeURIComponent(txt)}&is_public=${isPublic}` +
            (privateTo ? `&private_to=${privateTo}` : '');
        fetch('/api/chat-send.php', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body
        })
        .then(r => r.json())
        .then(d => {
            if (d.ok) { replyInp.value=''; cancelReply(); poll(); }
            else alert(d.msg || 'Error al enviar');
        })
        .catch(()=>alert('Error de conexión'));
    };

    window.sellerSendReply = function(isPublic) {
        sellerSend(isPublic, isPublic ? null : replyToUid);
    };

    window.banUser = function(uid, action) {
        fetch('/api/chat-ban.php', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:`banned_user_id=${uid}&action=${action}`
        })
        .then(r => r.json())
        .then(d => { if (d.ok) poll(); })
        .catch(()=>{});
    };

    function renderBans() {
        const uids = Object.keys(bansMap);
        if (!bannedWrap) return;
        if (!uids.length) { bannedWrap.style.display='none'; return; }
        bannedWrap.style.display='block';
        if (!bannedChips) return;
        bannedChips.innerHTML = uids.map(uid =>
            `<span class="banned-chip">${escHtml(bansMap[uid])}
             <button onclick="banUser(${uid},'unban')" title="Desbanear"><i class="fas fa-times"></i></button>
             </span>`
        ).join('');
    }

    function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function escJs(s){ return String(s).replace(/'/g,"\\'"); }

    // Iniciar polling
    poll();
    setInterval(poll, 3000);
    })();
    </script>
    <?php endif; ?>

    <?php include __DIR__ . '/includes/footer.php'; ?>

<!-- ── JS CÁMARA LIVE (dashboard vendedora) ──────────────────────────── -->
<script>
(function(){
// ── Selector de modo ─────────────────────────────────────────────────────
window.selectLiveMode = function(mode) {
    const cLink = document.getElementById('card-link');
    const cCam  = document.getElementById('card-cam');
    const fLink = document.getElementById('form-link');
    const fCam  = document.getElementById('form-cam');
    if (!cLink) return;
    if (mode === 'link') {
        cLink.classList.add('selected');   cCam.classList.remove('selected');
        fLink.style.display = 'block';     fCam.style.display = 'none';
        stopCameraPreview();
    } else {
        cCam.classList.add('selected');    cLink.classList.remove('selected');
        fLink.style.display = 'none';      fCam.style.display = 'block';
    }
};

// ── Variables de estado ───────────────────────────────────────────────────
let camStream    = null;
let mediaRec     = null;
let sessionId    = null;
let chunkIndex   = 0;
let timerInterval = null;
let timerSeconds  = 0;
const SELLER_SESSION_ID = <?= json_encode($liveData['live_session_id'] ?? '') ?>;
const IS_CAM_LIVE       = <?= json_encode($liveData['is_live'] && $liveData['live_type'] === 'camera') ?>;

// ── Previsualizar cámara ─────────────────────────────────────────────────
window.previewCamera = async function() {
    const btn    = document.getElementById('btn-preview-cam');
    const btnStart = document.getElementById('btn-start-cam');
    const wrap   = document.getElementById('cam-preview-wrap');
    const status = document.getElementById('cam-status-msg');
    try {
        camStream = await navigator.mediaDevices.getUserMedia({video:true, audio:true});
        const video = document.getElementById('cam-preview');
        if (video) { video.srcObject = camStream; }
        if (wrap) wrap.style.display = 'block';
        if (btn)  btn.style.display  = 'none';
        if (btnStart) btnStart.disabled = false;
        if (status) status.innerHTML = '<i class="fas fa-check-circle" style="color:#10b981;"></i> Cámara lista. Pulsa "Iniciar EN VIVO con Cámara".';
    } catch(e) {
        if (status) status.innerHTML = '<i class="fas fa-exclamation-circle" style="color:#ef4444;"></i> No se pudo acceder a la cámara: ' + e.message;
    }
};

function stopCameraPreview() {
    if (camStream) { camStream.getTracks().forEach(t=>t.stop()); camStream = null; }
    const wrap = document.getElementById('cam-preview-wrap');
    if (wrap) wrap.style.display = 'none';
    const btnPrev = document.getElementById('btn-preview-cam');
    const btnStart = document.getElementById('btn-start-cam');
    if (btnPrev) btnPrev.style.display = 'inline-flex';
    if (btnStart) btnStart.disabled = true;
}

// ── Iniciar cámara live ───────────────────────────────────────────────────
window.startCamLive = async function() {
    if (!camStream) { alert('Primero prueba la cámara.'); return; }
    const title  = (document.getElementById('cam-title-input')?.value.trim()) || 'En Vivo con Cámara';
    const status = document.getElementById('cam-status-msg');
    const btnStart = document.getElementById('btn-start-cam');
    const btnPrev  = document.getElementById('btn-preview-cam');
    if (btnStart) btnStart.disabled = true;
    if (status) status.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Iniciando sesión…';

    const res  = await fetch('/api/live-cam-start.php', {
        method:'POST', credentials:'same-origin',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'title='+encodeURIComponent(title)
    });
    const data = await res.json();
    if (!data.ok) {
        if (status) status.innerHTML = '<span style="color:#ef4444;">Error al iniciar: ' + (data.error||'?') + '</span>';
        if (btnStart) btnStart.disabled = false;
        return;
    }
    sessionId  = data.session_id;
    chunkIndex = 0;

    // Elegir codec disponible
    const mimeType = ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm']
        .find(t => MediaRecorder.isTypeSupported(t)) || 'video/webm';

    mediaRec = new MediaRecorder(camStream, {mimeType, videoBitsPerSecond: 800000});
    mediaRec.ondataavailable = sendChunk;
    mediaRec.start(2000); // chunk cada 2 segundos

    startTimer();
    if (btnStart) btnStart.style.display = 'none';
    if (btnPrev)  btnPrev.style.display  = 'none';
    if (status) status.innerHTML = '<i class="fas fa-circle" style="color:#ef4444;animation:live-pulse 1s infinite;"></i> Transmitiendo…';
};

async function sendChunk(event) {
    if (!event.data || event.data.size === 0) return;
    const form = new FormData();
    form.append('session_id', sessionId);
    form.append('index',      chunkIndex);
    form.append('chunk',      event.data, 'chunk.webm');
    chunkIndex++;
    try {
        await fetch('/api/live-cam-chunk.php', {method:'POST', credentials:'same-origin', body: form});
    } catch(e) { /* silently ignore transient upload errors */ }
}

// ── Detener transmisión ───────────────────────────────────────────────────
window.stopCamLive = async function() {
    if (!confirm('¿Terminar la transmisión en vivo?')) return;
    const btn = document.getElementById('btn-stop-cam');
    if (btn) btn.disabled = true;

    // Detener MediaRecorder y cámara
    if (mediaRec && mediaRec.state !== 'inactive') {
        mediaRec.stop();
        await new Promise(r => setTimeout(r, 2200)); // esperar último chunk
    }
    if (camStream) camStream.getTracks().forEach(t => t.stop());
    clearInterval(timerInterval);

    await fetch('/api/live-cam-stop.php', {
        method:'POST', credentials:'same-origin',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'session_id='+encodeURIComponent(sessionId||SELLER_SESSION_ID)
    });

    location.reload();
};

// ── Temporizador ──────────────────────────────────────────────────────────
function startTimer() {
    timerSeconds = 0;
    timerInterval = setInterval(() => {
        timerSeconds++;
        const m = String(Math.floor(timerSeconds/60)).padStart(2,'0');
        const s = String(timerSeconds%60).padStart(2,'0');
        const el = document.getElementById('cam-timer');
        if (el) el.textContent = m+':'+s;
    }, 1000);
}

// ── Si ya estaba en cámara live (recarga de página) ───────────────────────
if (IS_CAM_LIVE && SELLER_SESSION_ID) {
    // Re-conectar cámara para el preview del vendedor
    navigator.mediaDevices.getUserMedia({video:true, audio:true}).then(stream => {
        camStream = stream;
        sessionId = SELLER_SESSION_ID;
        const vid  = document.getElementById('cam-preview');
        const wrap = document.getElementById('cam-preview-wrap');
        if (vid) vid.srcObject = stream;
        if (wrap) wrap.style.display = 'block';

        const mimeType = ['video/webm;codecs=vp9,opus','video/webm;codecs=vp8,opus','video/webm']
            .find(t=>MediaRecorder.isTypeSupported(t))||'video/webm';
        mediaRec = new MediaRecorder(stream, {mimeType, videoBitsPerSecond:800000});
        mediaRec.ondataavailable = sendChunk;
        mediaRec.start(2000);
        startTimer();
    }).catch(()=>{});
}
})();
</script>
</body>
</html>
