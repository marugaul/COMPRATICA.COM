<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';
require_once __DIR__ . '/../includes/live_cam.php';
aff_require_login();
$pdo = db();
$aff_id = (int)$_SESSION['aff_id'];

// ── Manejar toggle EN VIVO ────────────────────────────────────────────────
$liveMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_live') {
    initLiveCamTables($pdo);
    $goLive    = (int)($_POST['go_live']    ?? 0);
    $liveTitle = trim($_POST['live_title']  ?? '');
    $liveLink  = trim($_POST['live_link']   ?? '');
    if ($goLive) {
        $pdo->prepare("UPDATE affiliates SET is_live=1, live_title=?, live_link=?, live_started_at=datetime('now'), live_type='link' WHERE id=?")
            ->execute([$liveTitle ?: 'EN VIVO', $liveLink ?: null, $aff_id]);
        $liveMsg = ['ok', '🔴 ¡Estás EN VIVO! Tu venta aparece primero en el listado.'];
    } else {
        // Terminar también la sesión de cámara si existe
        $curLive = $pdo->prepare("SELECT live_session_id, live_type FROM affiliates WHERE id=? LIMIT 1");
        $curLive->execute([$aff_id]);
        $curRow = $curLive->fetch(PDO::FETCH_ASSOC);
        if (($curRow['live_type'] ?? '') === 'camera' && !empty($curRow['live_session_id'])) {
            $pdo->prepare("UPDATE live_cam_sessions SET status='ended', ended_at=datetime('now') WHERE id=?")
                ->execute([$curRow['live_session_id']]);
        }
        $pdo->prepare("UPDATE affiliates SET is_live=0, live_title=NULL, live_link=NULL, live_started_at=NULL, live_type='link', live_session_id=NULL WHERE id=?")
            ->execute([$aff_id]);
        $liveMsg = ['info', '⚫ Transmisión finalizada.'];
    }
    header('Location: dashboard.php#live-section');
    exit;
}

// Inicializar columnas live si no existen y cargar estado actual
initLiveCamTables($pdo);
try {
    $liveStmt = $pdo->prepare("SELECT COALESCE(is_live,0) AS is_live, live_title, live_link,
                                      COALESCE(live_type,'link') AS live_type, live_session_id
                               FROM affiliates WHERE id=?");
    $liveStmt->execute([$aff_id]);
    $liveData = $liveStmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $_e) {}
$liveData = $liveData ?? ['is_live'=>0,'live_title'=>'','live_link'=>'','live_type'=>'link','live_session_id'=>''];

// Estadísticas básicas
$stats = [
  'products' => $pdo->query("SELECT COUNT(1) FROM products WHERE affiliate_id={$aff_id}")->fetchColumn(),
  'sales'    => $pdo->query("SELECT COUNT(1) FROM sales WHERE affiliate_id={$aff_id}")->fetchColumn(),
  'orders'   => $pdo->query("SELECT COUNT(1) FROM orders WHERE affiliate_id={$aff_id}")->fetchColumn(),
];

// URL de la tienda del afiliado (primer espacio activo, o venta-garaje general)
$affiliateSaleId = null;
try {
    $saleRow = $pdo->prepare("SELECT id FROM sales WHERE affiliate_id=? ORDER BY id ASC LIMIT 1");
    $saleRow->execute([$aff_id]);
    $affiliateSaleId = $saleRow->fetchColumn() ?: null;
} catch (Throwable $_e) {}
$storeUrl = $affiliateSaleId
    ? (defined('SITE_URL') ? SITE_URL : 'https://compratica.com') . '/store.php?sale_id=' . (int)$affiliateSaleId
    : (defined('SITE_URL') ? SITE_URL : 'https://compratica.com') . '/venta-garaje';

// Costo de crear un espacio
$sale_fee_crc = 3000; // default
try {
    $v = $pdo->query("SELECT sale_fee_crc FROM settings LIMIT 1")->fetchColumn();
    if ($v !== false && $v !== null) $sale_fee_crc = (float)$v;
} catch (Throwable $e) {}

// ⭐ Nuevas estadísticas de ubicaciones Uber
$uber_stats = [];
try {
    // Espacios con ubicación configurada
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT spl.sale_id) 
        FROM sale_pickup_locations spl
        JOIN sales s ON s.id = spl.sale_id
        WHERE s.affiliate_id = ? AND spl.is_active = 1
    ");
    $stmt->execute([$aff_id]);
    $uber_stats['locations_configured'] = (int)$stmt->fetchColumn();
    
    // Espacios sin ubicación
    $uber_stats['locations_missing'] = (int)$stats['sales'] - $uber_stats['locations_configured'];
    
    // Total de envíos por Uber (si ya hay deliveries)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM uber_deliveries WHERE affiliate_id = ?
    ");
    $stmt->execute([$aff_id]);
    $uber_stats['total_deliveries'] = (int)$stmt->fetchColumn();
    
    // Envíos completados
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM uber_deliveries 
        WHERE affiliate_id = ? AND status = 'delivered'
    ");
    $stmt->execute([$aff_id]);
    $uber_stats['delivered'] = (int)$stmt->fetchColumn();
    
    // Comisiones ganadas de Uber (por la plataforma)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(platform_commission), 0) 
        FROM uber_deliveries 
        WHERE affiliate_id = ? AND status = 'delivered'
    ");
    $stmt->execute([$aff_id]);
    $uber_stats['commission_earned'] = (float)$stmt->fetchColumn();
    
} catch (Exception $e) {
    // Si las tablas no existen aún, inicializar en 0
    $uber_stats = [
        'locations_configured' => 0,
        'locations_missing' => (int)$stats['sales'],
        'total_deliveries' => 0,
        'delivered' => 0,
        'commission_earned' => 0
    ];
}

// ✅ Forzar UTF-8 antes de cualquier salida
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
ini_set('default_charset', 'UTF-8');

// ✅ Evita error si mbstring no está instalado
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
} else {
    if (!function_exists('mb_strlen')) {
        function mb_strlen($s, $enc = null) { return strlen($s); }
    }
    if (!function_exists('mb_substr')) {
        function mb_substr($s, $start, $len = null, $enc = null) {
            return ($len === null) ? substr($s, $start) : substr($s, $start, $len);
        }
    }
    if (!function_exists('mb_strtolower')) {
        function mb_strtolower($s, $enc = null) { return strtolower($s); }
    }
    if (!function_exists('mb_strtoupper')) {
        function mb_strtoupper($s, $enc = null) { return strtoupper($s); }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Afiliados — Panel</title>
  <link rel="stylesheet" href="../assets/style.css?v=23">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    /* Estilos adicionales para dashboard mejorado */
    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    
    .stat-card {
      background: white;
      border-radius: 8px;
      padding: 1.5rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .stat-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .stat-card-header {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 1rem;
    }
    
    .stat-icon {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
    }
    
    .stat-icon.blue { background: #e3f2fd; color: #1976d2; }
    .stat-icon.green { background: #e8f5e9; color: #388e3c; }
    .stat-icon.orange { background: #fff3e0; color: #f57c00; }
    .stat-icon.purple { background: #f3e5f5; color: #7b1fa2; }
    .stat-icon.red { background: #ffebee; color: #c62828; }
    
    .stat-title {
      font-size: 0.9rem;
      color: #666;
      font-weight: 500;
    }
    
    .stat-value {
      font-size: 2rem;
      font-weight: 700;
      color: #2c3e50;
      margin: 0.5rem 0;
    }
    
    .stat-subtitle {
      font-size: 0.85rem;
      color: #999;
    }
    
    .alert-box {
      background: #fff3cd;
      border-left: 4px solid #ffc107;
      padding: 1rem 1.5rem;
      border-radius: 4px;
      margin-bottom: 2rem;
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    
    .alert-box.warning { background: #fff3cd; border-color: #ffc107; }
    .alert-box.info { background: #d1ecf1; border-color: #17a2b8; }
    .alert-box.success { background: #d4edda; border-color: #28a745; }
    
    .alert-icon {
      font-size: 1.5rem;
    }
    
    .alert-content h4 {
      margin: 0 0 0.5rem 0;
      color: #856404;
    }
    
    .alert-content p {
      margin: 0;
      color: #856404;
    }
    
    .quick-actions {
      background: white;
      border-radius: 8px;
      padding: 1.5rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      margin-bottom: 2rem;
    }
    
    .quick-actions h3 {
      margin: 0 0 1rem 0;
      color: #2c3e50;
    }
    
    .action-buttons {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
    }
    
    .action-btn {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 1rem;
      border-radius: 8px;
      text-decoration: none;
      transition: all 0.3s ease;
      font-weight: 500;
    }
    
    .action-btn.primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }
    
    .action-btn.success {
      background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
      color: white;
    }
    
    .action-btn.warning {
      background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%);
      color: white;
    }
    
    .action-btn.danger {
      background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
      color: white;
    }
    
    .action-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    
    .section-title {
      font-size: 1.5rem;
      color: #2c3e50;
      margin: 2rem 0 1rem 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
  </style>
</head>
<body>
<header class="header" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); box-shadow: 0 2px 12px rgba(0,0,0,0.1); padding: 1rem 2rem;">
  <div class="logo" style="font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; color: white;">
    <i class="fas fa-user-tie"></i>
    <?= htmlspecialchars($_SESSION['aff_name'] ?? 'Afiliado') ?>
  </div>
  <nav style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
    <a class="nav-btn" href="<?= htmlspecialchars($storeUrl) ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-store"></i>
      <span>Ver Tienda</span>
    </a>
    <a class="nav-btn" href="sales.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-store-alt"></i>
      <span>Espacios</span>
    </a>
    <a class="nav-btn" href="products.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-box"></i>
      <span>Productos</span>
    </a>
    <a class="nav-btn" href="orders.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-shopping-cart"></i>
      <span>Pedidos</span>
    </a>
    <a class="nav-btn" href="sales_pay_options.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-credit-card"></i>
      <span>Pagos</span>
    </a>
    <a class="nav-btn" href="shipping_options.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-shipping-fast"></i>
      <span>Envíos</span>
    </a>
    <a class="nav-btn" href="bulk-prices.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-tags"></i>
      <span>Ajuste Precios</span>
    </a>
    <a class="nav-btn" href="inventory.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-file-invoice"></i>
      <span>Inventario</span>
    </a>
    <a class="nav-btn" href="#" onclick="affToggleLiveSection(event)"
       id="nav-live-btn"
       style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem;
              background: <?= $liveData['is_live'] ? 'rgba(239,68,68,0.8)' : 'rgba(255,255,255,0.1)' ?>;
              color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem;
              font-weight: <?= $liveData['is_live'] ? '700' : '500' ?>; transition: all 0.3s ease;
              border: 1px solid <?= $liveData['is_live'] ? 'rgba(239,68,68,1)' : 'rgba(255,255,255,0.2)' ?>;">
      <?php if ($liveData['is_live']): ?>
        <span style="width:8px;height:8px;background:white;border-radius:50%;display:inline-block;animation:live-pulse 1.2s infinite;"></span>
      <?php else: ?>
        <i class="fas fa-broadcast-tower"></i>
      <?php endif; ?>
      <span>En Vivo</span>
    </a>
  </nav>
</header>

<div class="container">
  <h2 style="margin-bottom: 2rem; color: #2c3e50;">
    <i class="fas fa-tachometer-alt"></i> Panel de Control
  </h2>
  
  <?php
  // Verificar si el afiliado ha configurado opciones de envío
  $shipping_config = $pdo->query("SELECT * FROM affiliate_shipping_options WHERE affiliate_id=$aff_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
  $has_shipping_config = !empty($shipping_config);
  $no_shipping_enabled = $has_shipping_config && !$shipping_config['enable_pickup'] && !$shipping_config['enable_free_shipping'] && !$shipping_config['enable_uber'];
  ?>

  <?php if (!$has_shipping_config || $no_shipping_enabled): ?>
    <!-- ⚠️ Alerta si no tiene opciones de envío configuradas -->
    <div class="alert-box warning">
      <div class="alert-icon">⚠️</div>
      <div class="alert-content">
        <h4>¡Configura tus opciones de envío!</h4>
        <p>
          Aún no has configurado las opciones de envío que quieres ofrecer a tus clientes.
          Configura al menos una opción para que puedan completar sus compras.
          <a href="shipping_options.php" style="color: #d35400; font-weight: 600; margin-left: 0.5rem;">
            Configurar ahora →
          </a>
        </p>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($uber_stats['locations_missing'] > 0 && $stats['sales'] > 0): ?>
    <!-- ⭐ Alerta si hay espacios sin ubicación -->
    <div class="alert-box warning">
      <div class="alert-icon">⚠️</div>
      <div class="alert-content">
        <h4>¡Configura las ubicaciones de recogida!</h4>
        <p>
          Tienes <strong><?= $uber_stats['locations_missing'] ?> espacio(s)</strong>
          sin ubicación configurada. Configúralas para habilitar envíos por Uber.
          <a href="sales.php" style="color: #d35400; font-weight: 600; margin-left: 0.5rem;">
            Configurar ahora →
          </a>
        </p>
      </div>
    </div>
  <?php elseif ($stats['sales'] > 0 && $uber_stats['locations_configured'] === $stats['sales']): ?>
    <!-- Mensaje de éxito -->
    <div class="alert-box success">
      <div class="alert-icon">✅</div>
      <div class="alert-content">
        <h4 style="color: #155724;">¡Excelente!</h4>
        <p style="color: #155724;">
          Todos tus espacios tienen ubicación de recogida configurada.
          Tus clientes pueden elegir envío por Uber.
        </p>
      </div>
    </div>
  <?php endif; ?>
  
  <!-- Estadísticas principales -->
  <div class="section-title">
    <i class="fas fa-chart-line"></i> Estadísticas Generales
  </div>
  
  <div class="dashboard-grid">
    <div class="stat-card">
      <div class="stat-card-header">
        <div class="stat-icon blue">
          <i class="fas fa-box"></i>
        </div>
        <div>
          <div class="stat-title">Productos</div>
        </div>
      </div>
      <div class="stat-value"><?= (int)$stats['products'] ?></div>
      <div class="stat-subtitle">Total de productos activos</div>
    </div>
    
    <div class="stat-card">
      <div class="stat-card-header">
        <div class="stat-icon green">
          <i class="fas fa-store"></i>
        </div>
        <div>
          <div class="stat-title">Espacios</div>
        </div>
      </div>
      <div class="stat-value"><?= (int)$stats['sales'] ?></div>
      <div class="stat-subtitle">
        Espacios de venta creados
        <span style="display:block;margin-top:0.35rem;font-size:0.78rem;color:#6b7280;">
          Costo por espacio: <strong style="color:#059669;">₡<?= number_format($sale_fee_crc, 0, '.', ',') ?></strong>
        </span>
      </div>
    </div>
    
    <div class="stat-card">
      <div class="stat-card-header">
        <div class="stat-icon orange">
          <i class="fas fa-shopping-cart"></i>
        </div>
        <div>
          <div class="stat-title">Pedidos</div>
        </div>
      </div>
      <div class="stat-value"><?= (int)$stats['orders'] ?></div>
      <div class="stat-subtitle">Órdenes recibidas</div>
    </div>
  </div>
  
  <!-- ⭐ Estadísticas de Uber -->
  <?php if ($stats['sales'] > 0): ?>
    <div class="section-title">
      <i class="fas fa-truck"></i> Envíos por Uber
    </div>
    
    <div class="dashboard-grid">
      <div class="stat-card">
        <div class="stat-card-header">
          <div class="stat-icon <?= $uber_stats['locations_configured'] > 0 ? 'green' : 'red' ?>">
            <i class="fas fa-map-marker-alt"></i>
          </div>
          <div>
            <div class="stat-title">Ubicaciones Configuradas</div>
          </div>
        </div>
        <div class="stat-value">
          <?= $uber_stats['locations_configured'] ?> / <?= $stats['sales'] ?>
        </div>
        <div class="stat-subtitle">
          <?php if ($uber_stats['locations_missing'] > 0): ?>
            <span style="color: #e74c3c;">
              Faltan <?= $uber_stats['locations_missing'] ?> por configurar
            </span>
          <?php else: ?>
            <span style="color: #27ae60;">
              Todas configuradas ✓
            </span>
          <?php endif; ?>
        </div>
      </div>
      
      <?php if ($uber_stats['total_deliveries'] > 0): ?>
        <div class="stat-card">
          <div class="stat-card-header">
            <div class="stat-icon purple">
              <i class="fas fa-shipping-fast"></i>
            </div>
            <div>
              <div class="stat-title">Envíos Totales</div>
            </div>
          </div>
          <div class="stat-value"><?= $uber_stats['total_deliveries'] ?></div>
          <div class="stat-subtitle">
            <?= $uber_stats['delivered'] ?> completados
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-card-header">
            <div class="stat-icon green">
              <i class="fas fa-dollar-sign"></i>
            </div>
            <div>
              <div class="stat-title">Comisiones Generadas</div>
            </div>
          </div>
          <div class="stat-value" style="font-size: 1.5rem;">
            ₡<?= number_format($uber_stats['commission_earned'], 2) ?>
          </div>
          <div class="stat-subtitle">De envíos por Uber completados</div>
        </div>
      <?php else: ?>
        <div class="stat-card">
          <div class="stat-card-header">
            <div class="stat-icon purple">
              <i class="fas fa-info-circle"></i>
            </div>
            <div>
              <div class="stat-title">Envíos por Uber</div>
            </div>
          </div>
          <div class="stat-value" style="font-size: 1.2rem;">Sin envíos aún</div>
          <div class="stat-subtitle">
            Configura ubicaciones para empezar a recibir pedidos con envío Uber
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  
  <!-- Acciones rápidas -->
  <div class="section-title">
    <i class="fas fa-bolt"></i> Acciones Rápidas
  </div>
  
  <div class="quick-actions">
    <div class="action-buttons">
      <a href="sales.php" class="action-btn primary">
        <i class="fas fa-store"></i>
        <span>Gestionar Espacios</span>
      </a>
      
      <a href="products.php" class="action-btn success">
        <i class="fas fa-box"></i>
        <span>Gestionar Productos</span>
      </a>
      
      <a href="orders.php" class="action-btn warning">
        <i class="fas fa-shopping-cart"></i>
        <span>Ver Pedidos</span>
      </a>
      
      <a href="sales_pay_options.php" class="action-btn danger">
        <i class="fas fa-credit-card"></i>
        <span>Métodos de Pago</span>
      </a>

      <a href="shipping_options.php" class="action-btn primary" style="background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);">
        <i class="fas fa-shipping-fast"></i>
        <span>Opciones de Envío</span>
      </a>

      <a href="bulk-prices.php" class="action-btn primary" style="background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);">
        <i class="fas fa-tags"></i>
        <span>Ajuste de Precios</span>
      </a>
    </div>
  </div>
  
  <?php if ($uber_stats['locations_missing'] > 0 && $stats['sales'] > 0): ?>
    <!-- Call to action para configurar ubicaciones -->
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; border-radius: 12px; text-align: center; margin-top: 2rem;">
      <h3 style="margin: 0 0 1rem 0; color: white;">
        <i class="fas fa-rocket"></i> ¡Aumenta tus ventas con envío por Uber!
      </h3>
      <p style="margin: 0 0 1.5rem 0; font-size: 1.1rem;">
        Configura las ubicaciones de recogida y ofrece a tus clientes envío rápido con conductor de Uber
      </p>
      <a href="sales.php" style="background: white; color: #667eea; padding: 1rem 2rem; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block;">
        <i class="fas fa-map-marker-alt"></i> Configurar Ubicaciones Ahora
      </a>
    </div>
  <?php endif; ?>

  <!-- ── SECCIÓN EN VIVO ──────────────────────────────────────────────────── -->
  <style>
  @keyframes live-pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
  .aff-live-card { background:#fafafa; border-radius:12px; padding:20px; }
  .aff-live-field { margin-bottom:14px; }
  .aff-live-field label { display:block; font-weight:600; margin-bottom:6px; color:#555; font-size:.9rem; }
  .aff-live-field input { width:100%; padding:10px 14px; border:2px solid #e0e0e0; border-radius:8px; font-size:.95rem; box-sizing:border-box; }
  .aff-live-field input:focus { border-color:#ef4444; outline:none; }
  .btn-go-live-aff { background:linear-gradient(135deg,#ef4444,#dc2626); color:white; border:none; padding:12px 28px; border-radius:10px; font-weight:700; font-size:1rem; cursor:pointer; display:inline-flex; align-items:center; gap:8px; }
  .btn-go-live-aff:hover { opacity:.88; }
  .aff-live-mode-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
  @media(max-width:600px){ .aff-live-mode-grid { grid-template-columns:1fr; } }
  .aff-live-mode-card { border:2px solid #e2e8f0; border-radius:14px; padding:20px; cursor:pointer; transition:all .2s; text-align:center; background:#fafafa; }
  .aff-live-mode-card:hover, .aff-live-mode-card.selected { border-color:#ef4444; background:#fff5f5; }
  .aff-live-mode-card i { font-size:2.2rem; display:block; margin-bottom:10px; }
  .aff-cam-preview-wrap { position:relative; background:#000; border-radius:12px; overflow:hidden; margin-bottom:14px; aspect-ratio:16/9; max-width:480px; }
  #aff-cam-preview { width:100%; height:100%; object-fit:cover; display:block; transform:scaleX(-1); }
  .aff-cam-live-badge { position:absolute; top:10px; left:10px; background:rgba(220,38,38,.9); color:white; border-radius:20px; padding:3px 10px; font-size:.78rem; font-weight:700; display:flex; align-items:center; gap:5px; }
  .aff-cam-live-badge .dot { width:8px;height:8px;background:white;border-radius:50%;animation:live-pulse 1s infinite; }
  #aff-cam-timer { position:absolute; top:10px; right:10px; background:rgba(0,0,0,.6); color:white; border-radius:8px; padding:3px 9px; font-size:.8rem; font-family:monospace; }
  </style>

  <div id="live-section" style="display:none; background:white; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.07);
       padding:28px; margin-top:2rem; border:2px solid <?= $liveData['is_live'] ? '#ef4444' : '#e2e8f0' ?>; transition:border-color .3s;">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
      <h2 style="margin:0; font-size:1.3rem; color:#1f2937;">
        <?php if ($liveData['is_live']): ?>
          <span style="display:inline-flex;align-items:center;gap:8px;">
            <span style="width:12px;height:12px;background:#ef4444;border-radius:50%;display:inline-block;animation:live-pulse 1.2s infinite;"></span>
            EN VIVO ahora
          </span>
        <?php else: ?>
          <i class="fas fa-broadcast-tower" style="color:#ef4444;"></i> Transmisión en Vivo
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

    <?php if ($liveData['is_live'] && $liveData['live_type'] === 'camera'): ?>
    <!-- ── CÁMARA: en vivo ── -->
    <div style="display:flex;flex-wrap:wrap;gap:20px;align-items:flex-start;">
      <div style="flex:1;min-width:260px;">
        <div class="aff-cam-preview-wrap">
          <video id="aff-cam-preview" autoplay muted playsinline></video>
          <div class="aff-cam-live-badge"><span class="dot"></span> EN VIVO</div>
          <div id="aff-cam-timer">00:00</div>
        </div>
        <p style="color:#888;font-size:.82rem;margin:6px 0;">Tu cámara está transmitiendo en tu venta de garaje.</p>
      </div>
      <div>
        <strong style="color:#dc2626;font-size:1rem;display:block;margin-bottom:8px;">
          <i class="fas fa-circle" style="animation:live-pulse 1s infinite;"></i>
          <?= htmlspecialchars($liveData['live_title'] ?? 'En Vivo') ?>
        </strong>
        <button id="aff-btn-stop-cam" onclick="affStopCamLive()"
                style="background:#ef4444;color:white;border:none;padding:11px 22px;border-radius:10px;font-weight:700;cursor:pointer;">
          <i class="fas fa-stop-circle"></i> Terminar Transmisión
        </button>
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
        <div style="color:#888;font-size:.82rem;margin-top:4px;">Tu venta aparece primera en el listado con el badge EN VIVO.</div>
      </div>
    </div>

    <?php else: ?>
    <!-- ── No live: elegir modo ── -->
    <p style="color:#666;margin:0 0 14px;font-size:.9rem;">
      <i class="fas fa-info-circle" style="color:#ef4444;"></i>
      Elige cómo querés transmitir en vivo. Tu venta aparecerá primero en el listado.
    </p>

    <div class="aff-live-mode-grid">
      <div class="aff-live-mode-card selected" id="aff-card-link" onclick="affSelectMode('link')">
        <i class="fas fa-link" style="color:#667eea;"></i>
        <h4 style="margin:0 0 6px;">Link Externo</h4>
        <p style="margin:0;font-size:.82rem;color:#6b7280;">YouTube, Facebook, Instagram, TikTok</p>
      </div>
      <div class="aff-live-mode-card" id="aff-card-cam" onclick="affSelectMode('cam')">
        <i class="fas fa-video" style="color:#10b981;"></i>
        <h4 style="margin:0 0 6px;">Mi Cámara</h4>
        <p style="margin:0;font-size:.82rem;color:#6b7280;">Transmití directo desde tu navegador</p>
      </div>
    </div>

    <!-- Formulario modo LINK -->
    <div id="aff-form-link">
      <div class="aff-live-card">
        <form method="POST">
          <input type="hidden" name="action" value="toggle_live">
          <input type="hidden" name="go_live" value="1">
          <div class="aff-live-field">
            <label><i class="fas fa-tag"></i> Título del live</label>
            <input type="text" name="live_title" placeholder='Ej: "Ropa y accesorios en oferta 🎉"' maxlength="80">
          </div>
          <div class="aff-live-field">
            <label><i class="fab fa-youtube"></i> Link del live (YouTube, Facebook, Instagram, TikTok)</label>
            <input type="url" name="live_link" placeholder="https://youtube.com/live/...">
          </div>
          <button type="submit" class="btn-go-live-aff">
            <span style="width:10px;height:10px;background:white;border-radius:50%;display:inline-block;"></span>
            Iniciar EN VIVO con Link
          </button>
        </form>
      </div>
    </div>

    <!-- Panel modo CÁMARA -->
    <div id="aff-form-cam" style="display:none;">
      <div class="aff-live-card" style="background:#f0fdf4;">
        <div class="aff-live-field">
          <label><i class="fas fa-tag"></i> Título del live</label>
          <input type="text" id="aff-cam-title-input" placeholder='Ej: "Mostrando todo lo que tengo 📦"' maxlength="80"
                 style="width:100%;padding:10px 14px;border:2px solid #e0e0e0;border-radius:8px;font-size:.95rem;box-sizing:border-box;">
        </div>
        <div id="aff-cam-preview-wrap" class="aff-cam-preview-wrap" style="display:none;">
          <video id="aff-cam-preview" autoplay muted playsinline></video>
          <div class="aff-cam-live-badge"><span class="dot"></span> EN VIVO</div>
          <div id="aff-cam-timer">00:00</div>
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:4px;">
          <button id="aff-btn-preview-cam" onclick="affPreviewCamera()"
                  style="background:#374151;color:white;border:none;padding:11px 20px;border-radius:10px;font-weight:700;cursor:pointer;">
            <i class="fas fa-camera"></i> Probar cámara
          </button>
          <button id="aff-btn-start-cam" onclick="affStartCamLive()" disabled
                  class="btn-go-live-aff" style="background:linear-gradient(135deg,#10b981,#059669);">
            <span style="width:10px;height:10px;background:white;border-radius:50%;display:inline-block;"></span>
            Iniciar EN VIVO con Cámara
          </button>
        </div>
        <p id="aff-cam-status" style="color:#6b7280;font-size:.82rem;margin:8px 0 0;"></p>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <!-- ── FIN EN VIVO ─────────────────────────────────────────────────────── -->

</div>

<script>
// ── Toggle sección En Vivo ───────────────────────────────────────────────
function affToggleLiveSection(e) {
  e && e.preventDefault();
  const sec = document.getElementById('live-section');
  const visible = sec.style.display !== 'none';
  sec.style.display = visible ? 'none' : '';
  if (!visible) sec.scrollIntoView({ behavior:'smooth', block:'start' });
}

// Si está en vivo, mostrar automáticamente al cargar
<?php if ($liveData['is_live']): ?>
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('live-section').style.display = '';
});
<?php endif; ?>

// Selector de modo
function affSelectMode(mode) {
  document.getElementById('aff-form-link').style.display = mode === 'link' ? '' : 'none';
  document.getElementById('aff-form-cam').style.display  = mode === 'cam'  ? '' : 'none';
  document.getElementById('aff-card-link').classList.toggle('selected', mode === 'link');
  document.getElementById('aff-card-cam').classList.toggle('selected', mode === 'cam');
}

// ── Cámara ────────────────────────────────────────────────────────────────
let affStream, affRecorder, affChunkIndex = 0, affSessionId = '', affTimerInterval;
const AFF_SESSION_ID = <?= json_encode($liveData['live_session_id'] ?? '') ?>;

async function affPreviewCamera() {
  try {
    // SD quality (480p / 15fps) — suficiente para live de venta de garaje
    affStream = await navigator.mediaDevices.getUserMedia({
      video: { width: { ideal: 640 }, height: { ideal: 480 }, frameRate: { ideal: 15 } },
      audio: true
    });
    const vid  = document.getElementById('aff-cam-preview');
    const wrap = document.getElementById('aff-cam-preview-wrap');
    vid.srcObject = affStream;
    wrap.style.display = '';
    document.getElementById('aff-btn-start-cam').disabled = false;
    const st = document.getElementById('aff-cam-status');
    if (st) st.innerHTML = '<i class="fas fa-check-circle" style="color:#10b981;"></i> Cámara lista. Pulsá "Iniciar EN VIVO con Cámara".';
  } catch(e) {
    alert('No se pudo acceder a la cámara: ' + e.message);
  }
}

async function affStartCamLive() {
  if (!affStream) return alert('Primero probá la cámara.');
  const title = document.getElementById('aff-cam-title-input')?.value || 'En Vivo';

  // ─ Un solo fetch al API ─
  const fd = new FormData();
  fd.append('title', title);
  let json;
  try {
    const r = await fetch('/api/live-cam-start.php', { method:'POST', credentials:'same-origin', body: fd });
    json = await r.json();
  } catch(e) {
    return alert('Error de red al iniciar: ' + e.message);
  }
  if (!json.ok) return alert('Error al iniciar: ' + (json.msg || json.error));

  affSessionId  = json.session_id;
  affChunkIndex = 0;

  // Iniciar grabación — verificar soporte WebM antes de continuar (iOS no lo tiene)
  const mimeType = MediaRecorder.isTypeSupported('video/webm;codecs=vp8')
    ? 'video/webm;codecs=vp8'
    : MediaRecorder.isTypeSupported('video/webm') ? 'video/webm' : '';
  if (!mimeType) {
    // Cancelar la sesión que ya se abrió
    fetch('/api/live-cam-end.php', { method:'POST', credentials:'same-origin',
      body: new URLSearchParams({ session_id: affSessionId }) }).catch(()=>{});
    affSessionId = '';
    alert('Tu dispositivo no admite la transmisión en vivo (video/webm no soportado en iOS).\nUsá un dispositivo Android o una computadora.');
    return;
  }
  affRecorder = new MediaRecorder(affStream, { mimeType, videoBitsPerSecond: 350000 });
  affRecorder.ondataavailable = async (e) => {
    if (!e.data || !e.data.size) return;
    const chunk = new FormData();
    chunk.append('session_id', affSessionId);
    chunk.append('index', affChunkIndex++);
    chunk.append('chunk', e.data, 'chunk.webm');
    fetch('/api/live-cam-chunk.php', { method:'POST', credentials:'same-origin', body: chunk });
  };
  affRecorder.start(500); // chunks cada 500ms = lag mínimo

  // ─ Actualizar UI sin recargar ─
  // Asegurar que el video sigue mostrando el stream
  const vid  = document.getElementById('aff-cam-preview');
  const wrap = document.getElementById('aff-cam-preview-wrap');
  vid.srcObject = affStream;
  wrap.style.display = '';

  // Ocultar botones de inicio
  document.getElementById('aff-btn-start-cam').style.display   = 'none';
  document.getElementById('aff-btn-preview-cam').style.display = 'none';

  // Timer
  let secs = 0;
  affTimerInterval = setInterval(() => {
    secs++;
    const t = document.getElementById('aff-cam-timer');
    if (t) t.textContent = String(Math.floor(secs/60)).padStart(2,'0') + ':' + String(secs%60).padStart(2,'0');
  }, 1000);

  // Mostrar estado + botón detener
  const st = document.getElementById('aff-cam-status');
  if (st) {
    st.innerHTML = '<span style="color:#ef4444;font-weight:700;"><i class="fas fa-circle" style="animation:live-pulse 1s infinite;margin-right:5px;"></i>EN VIVO — Los clientes te están viendo en tu venta.</span>';
    const stopBtn = document.createElement('button');
    stopBtn.innerHTML = '<i class="fas fa-stop-circle"></i> Terminar Transmisión';
    stopBtn.style.cssText = 'margin-top:12px;background:#ef4444;color:white;border:none;padding:11px 22px;border-radius:10px;font-weight:700;cursor:pointer;display:block;';
    stopBtn.onclick = affStopCamLive;
    st.after(stopBtn);
  }

  // Actualizar cabecera de la sección
  const sec = document.getElementById('live-section');
  if (sec) sec.style.borderColor = '#ef4444';
}

async function affStopCamLive() {
  if (affRecorder && affRecorder.state !== 'inactive') affRecorder.stop();
  if (affStream) affStream.getTracks().forEach(t => t.stop());
  clearInterval(affTimerInterval);
  const fd = new FormData();
  fd.append('session_id', affSessionId || AFF_SESSION_ID);
  await fetch('/api/live-cam-stop.php', { method:'POST', credentials:'same-origin', body: fd });
  location.reload();
}
</script>
</body>
</html>