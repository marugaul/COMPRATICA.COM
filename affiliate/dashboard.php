<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';
aff_require_login();
$pdo = db();
$aff_id = (int)$_SESSION['aff_id'];

// Estadísticas básicas
$stats = [
  'products' => $pdo->query("SELECT COUNT(1) FROM products WHERE affiliate_id={$aff_id}")->fetchColumn(),
  'sales'    => $pdo->query("SELECT COUNT(1) FROM sales WHERE affiliate_id={$aff_id}")->fetchColumn(),
  'orders'   => $pdo->query("SELECT COUNT(1) FROM orders WHERE affiliate_id={$aff_id}")->fetchColumn(),
];

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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
<header class="header">
  <div class="logo">&#128722; Afiliado: <?= htmlspecialchars($_SESSION['aff_name'] ?? '') ?></div>
  <nav>
    <a class="btn" href="../index.php">Ver tienda</a>
    <a class="btn" href="sales.php">Mis espacios</a>
    <a class="btn" href="products.php">Mis productos</a>
    <a class="btn" href="orders.php">Mis pedidos</a>
    <a class="btn" href="sales_pay_options.php">&#128179; Métodos de pago</a>
    <a class="btn" href="shipping_options.php">🚚 Opciones de envío</a>
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
      <div class="stat-subtitle">Espacios de venta creados</div>
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
</div>
</body>
</html>