<?php
declare(strict_types=1);

/**
 * my_orders.php
 * Página "Mis Órdenes" — reemplaza el archivo existente.
 *
 * Requisitos:
 * - includes/config.php arranca la sesión y debe estar incluido al inicio.
 * - includes/db.php debe exponer una función db() que retorne un PDO.
 * - No se envía salida antes de cualquier header() o redirect.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Evitar cualquier salida antes de validar sesión/redirecciones

// Verificar login (redirigir si no está logeado)
$uid = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
if ($uid === 0) {
    // Redirigir a login; no debe haber salida previa
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    exit;
}

// Preparar DB
$pdo = null;
$allOrders = [];
$errorMessage = '';

try {
    if (!function_exists('db')) {
        throw new Exception("DB helper function 'db' not found (includes/db.php)");
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        throw new Exception("db() did not return a PDO instance");
    }
} catch (Exception $e) {
    $errorMessage = 'Error de conexión a la base de datos.';
    // En producción podrías loguear $e->getMessage()
}

// Obtener email del usuario (seguro que existe si está logeado)
$userEmail = trim((string)($_SESSION['email'] ?? ''));

// Consultar órdenes
if ($pdo && !$errorMessage && $userEmail !== '') {
    try {
        $sql = "
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
        ";
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute([$userEmail]);
        if ($ok === false) {
            $err = $stmt->errorInfo();
            throw new Exception("Statement execute failed: " . json_encode($err));
        }
        $allOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $errorMessage = 'Error al cargar las órdenes.';
    }
} elseif ($userEmail === '') {
    $errorMessage = 'No se encontró email de usuario en sesión.';
}

// Agrupar órdenes por número de pedido
$orderGroups = [];
if (empty($errorMessage) && is_array($allOrders)) {
    foreach ($allOrders as $order) {
        if (!is_array($order) || !isset($order['order_number'])) continue;
        $orderNum = $order['order_number'];
        if (!isset($orderGroups[$orderNum])) {
            $orderGroups[$orderNum] = [
                'order_number' => $orderNum,
                'created_at' => $order['created_at'] ?? '',
                'status' => $order['status'] ?? 'Desconocido',
                'payment_method' => $order['payment_method'] ?? '',
                'shipping_method' => $order['shipping_method'] ?? '',
                'shipping_cost' => (float)($order['shipping_cost'] ?? 0),
                'currency' => $order['currency'] ?? 'CRC',
                'grand_total' => (float)($order['grand_total'] ?? 0),
                'buyer_name' => $order['buyer_name'] ?? '',
                'buyer_email' => $order['buyer_email'] ?? '',
                'items' => [],
                'item_count' => 0,
                'subtotal' => 0,
                'tax_total' => 0
            ];
        }

        $qty = (float)($order['qty'] ?? 0);
        $subtotal = (float)($order['subtotal'] ?? 0);
        $tax = (float)($order['tax'] ?? 0);

        $unit_price = 0;
        if ($qty > 0) {
            $unit_price = isset($order['unit_price']) ? (float)$order['unit_price'] : ($subtotal > 0 ? $subtotal / $qty : 0);
        }

        $orderGroups[$orderNum]['items'][] = [
            'product_name' => $order['product_name'] ?? 'Producto desconocido',
            'product_image' => $order['product_image'] ?? '',
            'qty' => $qty,
            'unit_price' => $unit_price,
        ];

        $orderGroups[$orderNum]['item_count'] += $qty;
        $orderGroups[$orderNum]['subtotal'] += $subtotal;
        $orderGroups[$orderNum]['tax_total'] += $tax;
    }
}

// Helpers de formato
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
    /* CSS mínimo */
    :root{--primary:#0ea5e9;--bg:#f9fafb;--border:#e5e7eb;--text:#111827;--radius:12px}
    body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--text);line-height:1.6}
    .container{max-width:1200px;margin:0 auto;padding:30px 20px}
    .order-card{background:white;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:16px}
    .order-header{background:var(--bg);padding:12px 16px;display:flex;justify-content:space-between;align-items:center}
    .order-body{padding:16px}
  </style>
</head>
<body>
  <header style="background:white;padding:12px;border-bottom:1px solid #e5e7eb">
    <div style="max-width:1200px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;padding:0 20px">
      <a href="index.php" style="font-weight:700;color:var(--primary)">🛍️ <?= htmlspecialchars($APP_NAME) ?></a>
      <nav>
        <a href="index.php">Inicio</a> |
        <a href="cart.php">Carrito</a> |
        <a href="my_orders.php">Mis Órdenes</a> |
        <a href="logout.php">Salir</a>
      </nav>
    </div>
  </header>

  <div class="container">
    <h1>📦 Mis Órdenes</h1>
    <?php if (!empty($errorMessage)): ?>
      <div style="background:#fee2e2;color:#991b1b;padding:12px;border-radius:8px;margin-bottom:16px">
        <strong>Error:</strong> <?= htmlspecialchars($errorMessage) ?>
      </div>
    <?php endif; ?>

    <?php if (empty($orderGroups) && empty($errorMessage)): ?>
      <div style="background:white;padding:24px;border-radius:12px;text-align:center">
        <p>No tienes órdenes aún.</p>
        <a href="index.php">Ir a comprar</a>
      </div>
    <?php else: ?>
      <?php foreach ($orderGroups as $order): ?>
        <div class="order-card">
          <div class="order-header">
            <div>
              <div><strong><?= htmlspecialchars($order['order_number']) ?></strong></div>
              <div style="font-size:0.9rem;color:#6b7280"><?= ($order['created_at'] ? date('d/m/Y H:i', strtotime($order['created_at'])) : '') ?></div>
            </div>
            <div><?= getStatusBadge($order['status']) ?></div>
          </div>
          <div class="order-body">
            <?php foreach ($order['items'] as $item): ?>
              <div style="display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid #f3f4f6">
                <img src="<?= !empty($item['product_image']) ? 'uploads/' . htmlspecialchars($item['product_image']) : 'assets/no-image.png' ?>" alt="" style="width:60px;height:60px;object-fit:cover;border-radius:8px">
                <div style="flex:1">
                  <div><?= htmlspecialchars($item['product_name']) ?></div>
                  <div style="color:#6b7280;font-size:0.9rem">Cantidad: <?= htmlspecialchars((string)$item['qty']) ?></div>
                </div>
                <div><?= fmt_any($item['qty'] * $item['unit_price'], $order['currency']) ?></div>
              </div>
            <?php endforeach; ?>

            <div style="margin-top:12px;display:flex;justify-content:flex-end;gap:24px">
              <div>
                <div>Subtotal: <?= fmt_any($order['subtotal'], $order['currency']) ?></div>
                <div>Impuestos: <?= fmt_any($order['tax_total'], $order['currency']) ?></div>
                <?php if ($order['shipping_cost'] > 0): ?>
                  <div>Envío: <?= fmt_any($order['shipping_cost'], $order['currency']) ?></div>
                <?php endif; ?>
                <div><strong>Total: <?= fmt_any($order['grand_total'], $order['currency']) ?></strong></div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</body>
</html>