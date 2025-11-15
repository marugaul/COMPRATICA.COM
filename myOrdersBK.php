<?php
/**
 * my-orders.php — Panel de órdenes del comprador
 */
declare(strict_types=1);

session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Verificar que el usuario esté logueado
$uid = (int)($_SESSION['uid'] ?? 0);
if (!$uid) {
  header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
  exit;
}

$pdo = db();

// Obtener email del usuario
$userEmail = $_SESSION['email'] ?? '';

// Cargar todas las órdenes del usuario
$stmt = $pdo->prepare("
  SELECT 
    o.*,
    p.name as product_name,
    p.image as product_image,
    a.name as seller_name,
    a.email as seller_email
  FROM orders o
  LEFT JOIN products p ON p.id = o.product_id
  LEFT JOIN affiliates a ON a.id = o.affiliate_id
  WHERE o.buyer_email = ?
  ORDER BY o.created_at DESC
");
$stmt->execute([$userEmail]);
$allOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar órdenes por order_number
$orderGroups = [];
foreach ($allOrders as $order) {
  $orderNum = $order['order_number'];
  if (!isset($orderGroups[$orderNum])) {
    $orderGroups[$orderNum] = [
      'order_number' => $orderNum,
      'created_at' => $order['created_at'],
      'status' => $order['status'],
      'payment_method' => $order['payment_method'],
      'payment_type' => $order['payment_type'],
      'shipping_method' => $order['shipping_method'],
      'shipping_cost' => (float)$order['shipping_cost'],
      'currency' => $order['currency'],
      'grand_total' => (float)$order['grand_total'],
      'buyer_name' => $order['buyer_name'],
      'buyer_email' => $order['buyer_email'],
      'buyer_phone' => $order['buyer_phone'],
      'note' => $order['note'],
      'proof_image' => $order['proof_image'],
      'seller_name' => $order['seller_name'],
      'seller_email' => $order['seller_email'],
      'items' => [],
      'item_count' => 0,
      'subtotal' => 0,
      'tax_total' => 0
    ];
  }
  
  $orderGroups[$orderNum]['items'][] = [
    'product_name' => $order['product_name'],
    'product_image' => $order['product_image'],
    'qty' => (float)$order['qty'],
    'unit_price' => (float)($order['grand_total'] / $order['qty']), // Aproximado
  ];
  
  $orderGroups[$orderNum]['item_count'] += (float)$order['qty'];
  $orderGroups[$orderNum]['subtotal'] += (float)$order['subtotal'];
  $orderGroups[$orderNum]['tax_total'] += (float)$order['tax'];
}

function fmt_crc($n){ return '₡'.number_format((float)$n,0,',','.'); }
function fmt_usd($n){ return '$'.number_format((float)$n,2,'.',','); }
function fmt_any($n,$c){ return (strtoupper((string)$c)==='USD') ? fmt_usd($n) : fmt_crc($n); }

function getStatusBadge($status) {
  $badges = [
    'Pendiente' => ['bg' => '#fef3c7', 'color' => '#92400e', 'icon' => '⏳'],
    'En Revisión' => ['bg' => '#dbeafe', 'color' => '#1e40af', 'icon' => '🔍'],
    'Confirmado' => ['bg' => '#d1fae5', 'color' => '#065f46', 'icon' => '✅'],
    'Enviado' => ['bg' => '#e0e7ff', 'color' => '#3730a3', 'icon' => '📦'],
    'Completado' => ['bg' => '#d1fae5', 'color' => '#065f46', 'icon' => '🎉'],
    'Cancelado' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'icon' => '❌'],
  ];
  
  $badge = $badges[$status] ?? ['bg' => '#f3f4f6', 'color' => '#374151', 'icon' => '•'];
  
  return sprintf(
    '<span style="background:%s;color:%s;padding:6px 12px;border-radius:20px;font-size:0.85rem;font-weight:600;display:inline-block;">%s %s</span>',
    $badge['bg'],
    $badge['color'],
    $badge['icon'],
    htmlspecialchars($status)
  );
}

function getPaymentMethodLabel($method) {
  $labels = [
    'sinpe' => '📱 SINPE Móvil',
    'paypal' => '💳 PayPal',
    'card' => '💳 Tarjeta',
    'cash' => '💵 Efectivo',
  ];
  return $labels[$method] ?? ucfirst($method);
}

function getShippingMethodLabel($method) {
  $labels = [
    'pickup' => '🏪 Recoger en tienda',
    'uber' => '🚗 Envío por Uber',
    'custom' => '📦 Envío a domicilio',
    'delivery' => '🚚 Delivery',
  ];
  return $labels[$method] ?? ucfirst($method);
}

$APP_NAME = defined('APP_NAME') ? APP_NAME : 'Compratica';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mis Órdenes - <?= htmlspecialchars($APP_NAME) ?></title>
  <link rel="stylesheet" href="assets/style.css?v=20251026">
  <style>
    :root {
      --primary: #0ea5e9;
      --primary-dark: #0284c7;
      --bg: #f9fafb;
      --card-bg: #ffffff;
      --border: #e5e7eb;
      --text: #111827;
      --text-muted: #6b7280;
      --radius: 12px;
    }
    
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: var(--bg);
      color: var(--text);
      line-height: 1.6;
    }
    
    .header {
      background: white;
      border-bottom: 1px solid var(--border);
      padding: 16px 0;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    
    .header-content {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .logo {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--primary);
      text-decoration: none;
    }
    
    .nav {
      display: flex;
      gap: 12px;
      align-items: center;
    }
    
    .btn {
      padding: 8px 16px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 500;
      transition: all 0.2s;
      border: 1px solid var(--border);
      background: white;
      color: var(--text);
      display: inline-block;
    }
    
    .btn:hover {
      background: var(--bg);
    }
    
    .btn-primary {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
    }
    
    .btn-primary:hover {
      background: var(--primary-dark);
      border-color: var(--primary-dark);
    }
    
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 30px 20px;
    }
    
    .page-header {
      margin-bottom: 30px;
    }
    
    .page-header h1 {
      font-size: 2rem;
      margin-bottom: 8px;
    }
    
    .page-header p {
      color: var(--text-muted);
    }
    
    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 30px;
    }
    
    .stat-card {
      background: white;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 20px;
      text-align: center;
    }
    
    .stat-card .number {
      font-size: 2rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 4px;
    }
    
    .stat-card .label {
      color: var(--text-muted);
      font-size: 0.9rem;
    }
    
    .orders-list {
      display: grid;
      gap: 20px;
    }
    
    .order-card {
      background: white;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      transition: all 0.2s;
    }
    
    .order-card:hover {
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    
    .order-header {
      background: var(--bg);
      padding: 16px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      border-bottom: 1px solid var(--border);
    }
    
    .order-number {
      font-weight: 700;
      font-family: 'Courier New', monospace;
      font-size: 1.1rem;
    }
    
    .order-date {
      color: var(--text-muted);
      font-size: 0.9rem;
    }
    
    .order-body {
      padding: 20px;
    }
    
    .order-items {
      display: grid;
      gap: 12px;
      margin-bottom: 16px;
    }
    
    .order-item {
      display: grid;
      grid-template-columns: 60px 1fr auto;
      gap: 12px;
      align-items: center;
      padding: 12px;
      background: var(--bg);
      border-radius: 8px;
    }
    
    .item-image {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid var(--border);
      background: white;
    }
    
    .item-info {
      flex: 1;
    }
    
    .item-name {
      font-weight: 600;
      margin-bottom: 4px;
    }
    
    .item-qty {
      color: var(--text-muted);
      font-size: 0.9rem;
    }
    
    .item-price {
      font-weight: 600;
      text-align: right;
    }
    
    .order-summary {
      border-top: 1px solid var(--border);
      padding-top: 16px;
      display: grid;
      gap: 8px;
    }
    
    .summary-row {
      display: flex;
      justify-content: space-between;
      font-size: 0.95rem;
    }
    
    .summary-row.total {
      font-size: 1.2rem;
      font-weight: 700;
      padding-top: 8px;
      border-top: 2px solid var(--border);
      margin-top: 8px;
    }
    
    .order-footer {
      background: var(--bg);
      padding: 16px 20px;
      border-top: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
    }
    
    .order-meta {
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
      font-size: 0.9rem;
      color: var(--text-muted);
    }
    
    .order-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      background: white;
      border: 1px solid var(--border);
      border-radius: var(--radius);
    }
    
    .empty-state .icon {
      font-size: 4rem;
      margin-bottom: 16px;
      opacity: 0.5;
    }
    
    .empty-state h2 {
      margin-bottom: 8px;
    }
    
    .empty-state p {
      color: var(--text-muted);
      margin-bottom: 20px;
    }
    
    @media (max-width: 768px) {
      .order-header, .order-footer {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .order-item {
        grid-template-columns: 50px 1fr;
      }
      
      .item-price {
        grid-column: 2;
        text-align: left;
        margin-top: 4px;
      }
    }
  </style>
</head>
<body>
  <header class="header">
    <div class="header-content">
      <a href="index.php" class="logo">🛍️ <?= htmlspecialchars($APP_NAME) ?></a>
      <nav class="nav">
        <a href="index.php" class="btn">Inicio</a>
        <a href="cart.php" class="btn">Carrito</a>
        <a href="my-orders.php" class="btn btn-primary">Mis Órdenes</a>
        <a href="logout.php" class="btn">Salir</a>
      </nav>
    </div>
  </header>

  <div class="container">
    <div class="page-header">
      <h1>📦 Mis Órdenes</h1>
      <p>Historial completo de tus compras</p>
    </div>

    <?php if (empty($orderGroups)): ?>
      <div class="empty-state">
        <div class="icon">🛒</div>
        <h2>No tienes órdenes aún</h2>
        <p>Cuando realices una compra, aparecerá aquí</p>
        <a href="index.php" class="btn btn-primary">Ir a comprar</a>
      </div>
    <?php else: ?>
      <div class="stats">
        <div class="stat-card">
          <div class="number"><?= count($orderGroups) ?></div>
          <div class="label">Órdenes Totales</div>
        </div>
        <div class="stat-card">
          <div class="number">
            <?= count(array_filter($orderGroups, fn($o) => in_array($o['status'], ['Pendiente', 'En Revisión']))) ?>
          </div>
          <div class="label">En Proceso</div>
        </div>
        <div class="stat-card">
          <div class="number">
            <?= count(array_filter($orderGroups, fn($o) => $o['status'] === 'Completado')) ?>
          </div>
          <div class="label">Completadas</div>
        </div>
      </div>

      <div class="orders-list">
        <?php foreach ($orderGroups as $order): ?>
          <div class="order-card">
            <div class="order-header">
              <div>
                <div class="order-number"><?= htmlspecialchars($order['order_number']) ?></div>
                <div class="order-date">
                  <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                </div>
              </div>
              <div>
                <?= getStatusBadge($order['status']) ?>
              </div>
            </div>

            <div class="order-body">
              <div class="order-items">
                <?php foreach ($order['items'] as $item): ?>
                  <div class="order-item">
                    <img 
                      class="item-image" 
                      src="<?= !empty($item['product_image']) ? 'uploads/'.$item['product_image'] : 'assets/no-image.png' ?>" 
                      alt="<?= htmlspecialchars($item['product_name']) ?>"
                    >
                    <div class="item-info">
                      <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                      <div class="item-qty">Cantidad: <?= $item['qty'] ?></div>
                    </div>
                    <div class="item-price">
                      <?= fmt_any($item['qty'] * $item['unit_price'], $order['currency']) ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <div class="order-summary">
                <div class="summary-row">
                  <span>Subtotal</span>
                  <span><?= fmt_any($order['subtotal'], $order['currency']) ?></span>
                </div>
                <div class="summary-row">
                  <span>Impuestos</span>
                  <span><?= fmt_any($order['tax_total'], $order['currency']) ?></span>
                </div>
                <?php if ($order['shipping_cost'] > 0): ?>
                  <div class="summary-row">
                    <span>Envío</span>
                    <span><?= fmt_any($order['shipping_cost'], $order['currency']) ?></span>
                  </div>
                <?php endif; ?>
                <div class="summary-row total">
                  <span>Total</span>
                  <span><?= fmt_any($order['grand_total'], $order['currency']) ?></span>
                </div>
              </div>
            </div>

            <div class="order-footer">
              <div class="order-meta">
                <div><?= getPaymentMethodLabel($order['payment_method']) ?></div>
                <div><?= getShippingMethodLabel($order['shipping_method']) ?></div>
                <?php if (!empty($order['seller_name'])): ?>
                  <div>👤 <?= htmlspecialchars($order['seller_name']) ?></div>
                <?php endif; ?>
              </div>
              
              <div class="order-actions">
                <a href="order-success.php?order=<?= urlencode($order['order_number']) ?>" class="btn">
                  Ver Detalles
                </a>
                <?php if ($order['payment_method'] === 'sinpe' && empty($order['proof_image']) && $order['status'] === 'Pendiente'): ?>
                  <a href="upload-proof.php?order=<?= urlencode($order['order_number']) ?>" class="btn btn-primary">
                    📤 Subir Comprobante
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>