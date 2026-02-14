<?php
// affiliate/orders.php — UTF-8 (sin BOM) — Notifica por CUALQUIER cambio de estado
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$aff_id = (int)($_SESSION['aff_id'] ?? 0); // misma clave que setea affiliate/login.php
if ($aff_id <= 0) {
  header('Location: login.php');
  exit;
}

$pdo = db();
$msg = '';

/** Estados permitidos (mismo set que admin) */
function allowed_statuses(): array {
  return ['Pendiente','Pagado','Empacado','En camino','Entregado','Cancelado'];
}

/** Verifica que el pedido pertenezca a este afiliado */
function load_aff_order(PDO $pdo, int $order_id, int $aff_id) {
  $sql = "
    SELECT 
      o.*,
      p.name AS product_name,
      p.id   AS product_id,
      s.id   AS sale_id,
      s.affiliate_id AS sale_affiliate_id
    FROM orders o
    JOIN products p ON p.id = o.product_id
    LEFT JOIN sales s ON s.id = p.sale_id
    WHERE o.id = ?
      AND (s.affiliate_id = ? OR o.affiliate_id = ?)
    LIMIT 1
  ";
  $st = $pdo->prepare($sql);
  $st->execute([$order_id, $aff_id, $aff_id]);
  return $st->fetch(PDO::FETCH_ASSOC);
}

/** Chequea si orders tiene columna updated_at para no romper el UPDATE */
function orders_has_updated_at(PDO $pdo): bool {
  try {
    $cols = $pdo->query("PRAGMA table_info(orders)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
      if (isset($c['name']) && strtolower($c['name']) === 'updated_at') return true;
    }
  } catch (Throwable $e) {
    error_log("[affiliate/orders.php] PRAGMA table_info(orders) error: ".$e->getMessage());
  }
  return false;
}

/** Construye asunto y cuerpo según el nuevo estado */
function build_status_email(string $estado, int $order_id, string $product_name): array {
  $product = htmlspecialchars($product_name, ENT_QUOTES, 'UTF-8');

  switch ($estado) {
    case 'Pagado':
      $subject = "Tu pedido #{$order_id} fue validado y aprobado";
      $body = "Hola,<br><br>"
            . "Tu pago ha sido <strong>validado y aprobado</strong> para el pedido <strong>#{$order_id}</strong> "
            . "del artículo <strong>{$product}</strong>.<br>"
            . "Pronto coordinaremos la entrega. ¡Gracias por tu compra!<br><br>"
            . APP_NAME;
      break;

    case 'Empacado':
      $subject = "Tu pedido #{$order_id} está empacado";
      $body = "Hola,<br><br>"
            . "Tu pedido <strong>#{$order_id}</strong> del artículo <strong>{$product}</strong> ha sido <strong>empacado</strong>.<br>"
            . "Nos pondremos en contacto para la entrega.<br><br>"
            . APP_NAME;
      break;

    case 'En camino':
      $subject = "Tu pedido #{$order_id} va en camino";
      $body = "Hola,<br><br>"
            . "Tu pedido <strong>#{$order_id}</strong> del artículo <strong>{$product}</strong> está <strong>en camino</strong>.<br>"
            . "¡Gracias por tu compra!<br><br>"
            . APP_NAME;
      break;

    case 'Entregado':
      $subject = "¡Tu pedido #{$order_id} fue entregado!";
      $body = "Hola,<br><br>"
            . "Confirmamos que tu pedido <strong>#{$order_id}</strong> del artículo <strong>{$product}</strong> ha sido <strong>entregado</strong>.<br>"
            . "¡Esperamos que lo disfrutes!<br><br>"
            . APP_NAME;
      break;

    case 'Cancelado':
      $subject = "Tu pedido #{$order_id} fue cancelado";
      $body = "Hola,<br><br>"
            . "Tu pedido <strong>#{$order_id}</strong> del artículo <strong>{$product}</strong> ha sido <strong>cancelado</strong>.<br>"
            . "Si tienes dudas, por favor contáctanos.<br><br>"
            . APP_NAME;
      break;

    default: // Pendiente u otros
      $subject = "Actualización del pedido #{$order_id}: {$estado}";
      $body = "Hola,<br><br>"
            . "Tu pedido <strong>#{$order_id}</strong> del artículo <strong>{$product}</strong> ahora está en estado: <strong>{$estado}</strong>.<br>"
            . "Te mantendremos informado.<br><br>"
            . APP_NAME;
  }

  return [$subject, $body];
}

/** POST: actualizar estado y notificar */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
  try {
    $order_id   = (int)($_POST['order_id'] ?? 0);
    $new_status = trim((string)($_POST['status'] ?? 'Pendiente'));

    if (!in_array($new_status, allowed_statuses(), true)) {
      throw new RuntimeException('Estado inválido.');
    }

    // Cargar pedido y validar pertenencia al afiliado
    $ord = load_aff_order($pdo, $order_id, $aff_id);
    if (!$ord) {
      throw new RuntimeException('Pedido no encontrado o no pertenece a este afiliado.');
    }

    // UPDATE (con o sin updated_at)
    $hasUpdatedAt = orders_has_updated_at($pdo);
    if ($hasUpdatedAt) {
      $upd = $pdo->prepare("UPDATE orders SET status=?, updated_at=datetime('now') WHERE id=?");
      $upd->execute([$new_status, $order_id]);
    } else {
      $upd = $pdo->prepare("UPDATE orders SET status=? WHERE id=?");
      $upd->execute([$new_status, $order_id]);
    }

    // Construir correo para cualquier estado
    $buyerEmail = trim((string)($ord['buyer_email'] ?? ''));
    $productName = (string)($ord['product_name'] ?? '');
    [$subject, $body] = build_status_email($new_status, $order_id, $productName);

    error_log("[affiliate/orders.php] Notificación estado '{$new_status}' order #{$order_id} buyer='{$buyerEmail}'");

    // Enviar al cliente
    if ($buyerEmail !== '') {
      try {
        $okClient = send_email($buyerEmail, $subject, $body);
        error_log("[affiliate/orders.php] send_email cliente ".($okClient ? 'OK' : 'FAIL')." (order #{$order_id})");
      } catch (Throwable $e) {
        error_log("[affiliate/orders.php] Excepción email cliente: ".$e->getMessage());
      }
    } else {
      error_log("[affiliate/orders.php] Buyer email vacío; no se envía (order #{$order_id})");
    }

    // Aviso al admin (opcional)
    try {
      $admin = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : '';
      if ($admin !== '') {
        $okAdm = send_email($admin, "[Afiliado] Pedido #{$order_id} → {$new_status}", $body);
        error_log("[affiliate/orders.php] send_email admin ".($okAdm ? 'OK' : 'FAIL')." (order #{$order_id})");
      } else {
        error_log("[affiliate/orders.php] ADMIN_EMAIL no definido; no se envía admin (order #{$order_id})");
      }
    } catch (Throwable $e) {
      error_log("[affiliate/orders.php] Excepción email admin: ".$e->getMessage());
    }

    $msg = "Estado del pedido #{$order_id} actualizado a '{$new_status}'.";
  } catch (Throwable $e) {
    $msg = 'Error: ' . $e->getMessage();
    error_log("[affiliate/orders.php] ERROR al actualizar estado: ".$e->getMessage());
  }
}

/** Listado de pedidos del afiliado */
$list = $pdo->prepare("
  SELECT 
    o.*,
    p.name  AS product_name,
    p.image AS product_image
  FROM orders o
  JOIN products p ON p.id = o.product_id
  LEFT JOIN sales s ON s.id = p.sale_id
  WHERE (s.affiliate_id = ? OR o.affiliate_id = ?)
  ORDER BY o.created_at DESC
  LIMIT 200
");
$list->execute([$aff_id, $aff_id]);
$orders = $list->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Pedidos - Afiliados</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/style.css?v=20251014a">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    /* Variables de color corporativas */
    :root {
      --primary: #2c3e50;
      --primary-light: #34495e;
      --accent: #3498db;
      --accent-hover: #2980b9;
      --success: #27ae60;
      --warning: #f39c12;
      --danger: #e74c3c;
      --info: #17a2b8;
      --gray-50: #f9fafb;
      --gray-100: #f3f4f6;
      --gray-200: #e5e7eb;
      --gray-300: #d1d5db;
      --gray-600: #6b7280;
      --gray-800: #1f2937;
    }

    body {
      background: var(--gray-50);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* Header empresarial */
    .header {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      box-shadow: 0 2px 12px rgba(0,0,0,0.1);
      padding: 1.5rem 2rem;
    }

    .header .logo {
      font-size: 1.25rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    /* Cards mejorados */
    .card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
      border: 1px solid var(--gray-200);
      padding: 2rem;
      margin-bottom: 2rem;
    }

    .card h3 {
      color: var(--primary);
      font-size: 1.5rem;
      font-weight: 600;
      margin: 0 0 1.5rem 0;
      padding-bottom: 1rem;
      border-bottom: 2px solid var(--gray-100);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    /* Tabla profesional */
    .table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9rem;
    }

    .table thead {
      background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-50) 100%);
    }

    .table th {
      padding: 1rem 0.75rem;
      text-align: left;
      font-weight: 600;
      color: var(--gray-800);
      border-bottom: 2px solid var(--gray-300);
      white-space: nowrap;
    }

    .table td {
      padding: 1rem 0.75rem;
      border-bottom: 1px solid var(--gray-200);
      vertical-align: middle;
    }

    .table tbody tr {
      transition: background 0.2s ease;
    }

    .table tbody tr:hover {
      background: var(--gray-50);
    }

    .thumb {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 8px;
      border: 2px solid var(--gray-200);
      transition: transform 0.2s ease;
    }

    .thumb:hover {
      transform: scale(1.05);
      border-color: var(--accent);
    }

    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      padding: 0.4rem 0.75rem;
      border-radius: 6px;
      font-weight: 600;
      font-size: 0.85rem;
    }

    .status-Pendiente {
      background: rgba(243, 156, 18, 0.1);
      color: var(--warning);
      border: 1px solid rgba(243, 156, 18, 0.3);
    }

    .status-Pagado {
      background: rgba(39, 174, 96, 0.1);
      color: var(--success);
      border: 1px solid rgba(39, 174, 96, 0.3);
    }

    .status-Empacado {
      background: rgba(52, 152, 219, 0.1);
      color: var(--accent);
      border: 1px solid rgba(52, 152, 219, 0.3);
    }

    .status-En-camino {
      background: rgba(155, 89, 182, 0.1);
      color: #9b59b6;
      border: 1px solid rgba(155, 89, 182, 0.3);
    }

    .status-Entregado {
      background: rgba(39, 174, 96, 0.15);
      color: #155724;
      border: 1px solid rgba(39, 174, 96, 0.4);
    }

    .status-Cancelado {
      background: rgba(231, 76, 60, 0.1);
      color: var(--danger);
      border: 1px solid rgba(231, 76, 60, 0.3);
    }

    .status-form {
      display: flex;
      gap: 0.5rem;
      align-items: center;
      flex-wrap: wrap;
    }

    .status-form select {
      border: 2px solid var(--gray-200);
      border-radius: 6px;
      padding: 0.5rem 0.75rem;
      font-size: 0.875rem;
      transition: all 0.3s ease;
    }

    .status-form select:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
      outline: none;
    }

    .btn {
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-weight: 500;
      font-size: 0.875rem;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
      border: none;
      cursor: pointer;
      background: var(--gray-200);
      color: var(--gray-800);
    }

    .btn:hover {
      background: var(--gray-300);
      transform: translateY(-1px);
    }

    .btn.primary {
      background: linear-gradient(135deg, var(--success) 0%, #229954 100%);
      color: white;
      box-shadow: 0 2px 6px rgba(39, 174, 96, 0.3);
    }

    .btn.primary:hover {
      box-shadow: 0 4px 10px rgba(39, 174, 96, 0.4);
      transform: translateY(-2px);
    }

    .small {
      font-size: 0.875rem;
      color: var(--gray-600);
    }

    .nowrap {
      white-space: nowrap;
    }

    .success {
      background: rgba(39, 174, 96, 0.1);
      border: 1px solid rgba(39, 174, 96, 0.3);
      border-left: 4px solid var(--success);
      color: #155724;
      padding: 1rem 1.25rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .empty-state {
      text-align: center;
      padding: 3rem 1rem;
      color: var(--gray-600);
    }

    .empty-state i {
      font-size: 3rem;
      margin-bottom: 1rem;
      color: var(--gray-300);
    }
  </style>
</head>
<body>
<header class="header" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); box-shadow: 0 2px 12px rgba(0,0,0,0.1); padding: 1rem 2rem;">
  <div class="logo" style="font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; color: white;">
    <i class="fas fa-user-tie"></i>
    Panel de Afiliado
  </div>
  <nav style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
    <a class="nav-btn" href="dashboard.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-th-large"></i>
      <span>Dashboard</span>
    </a>
    <a class="nav-btn" href="sales.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-store-alt"></i>
      <span>Espacios</span>
    </a>
    <a class="nav-btn" href="../index" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-store"></i>
      <span>Ver Tienda</span>
    </a>
  </nav>
</header>

<div class="container">
  <?php if (!empty($msg)): ?>
    <div class="success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="card">
    <h3>
      <i class="fas fa-box"></i>
      Pedidos Recientes
      <span style="font-size: 0.9rem; font-weight: 400; color: var(--gray-600); margin-left: auto;">(últimos 200)</span>
    </h3>
    <?php if (empty($orders)): ?>
      <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <p style="margin: 0; font-size: 1.1rem; font-weight: 500;">No hay pedidos por ahora</p>
        <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem;">Los pedidos de tus clientes aparecerán aquí</p>
      </div>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Producto</th>
            <th>Cant</th>
            <th>Cliente</th>
            <th>Residencia</th>
            <th>Comprobante</th>
            <th>Estado</th>
            <th class="nowrap">Actualizar</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o):
            $statusClass = 'status-' . str_replace(' ', '-', $o['status']);
            $statusIcon = [
              'Pendiente' => 'clock',
              'Pagado' => 'check-circle',
              'Empacado' => 'box',
              'En camino' => 'shipping-fast',
              'Entregado' => 'check-double',
              'Cancelado' => 'times-circle'
            ];
            $icon = $statusIcon[$o['status']] ?? 'circle';
          ?>
            <tr>
              <td><strong>#<?= (int)$o['id'] ?></strong></td>
              <td class="small"><?= htmlspecialchars($o['created_at']) ?></td>
              <td>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                  <?php if(!empty($o['product_image'])): ?>
                    <img class="thumb" src="../uploads/<?= htmlspecialchars($o['product_image']) ?>" alt="">
                  <?php endif; ?>
                  <div><?= htmlspecialchars($o['product_name']) ?></div>
                </div>
              </td>
              <td><strong><?= (int)$o['qty'] ?></strong></td>
              <td class="small">
                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                  <span><i class="fas fa-envelope" style="opacity: 0.5;"></i> <?= htmlspecialchars($o['buyer_email']) ?></span>
                  <span><i class="fas fa-phone" style="opacity: 0.5;"></i> <?= htmlspecialchars($o['buyer_phone']) ?></span>
                </div>
              </td>
              <td class="small"><?= htmlspecialchars($o['residency']) ?></td>
              <td>
                <?php if(!empty($o['proof_image'])): ?>
                  <a href="../uploads/payments/<?= htmlspecialchars($o['proof_image']) ?>" target="_blank">
                    <img class="thumb" src="../uploads/payments/<?= htmlspecialchars($o['proof_image']) ?>" alt="Comprobante" title="Ver comprobante">
                  </a>
                <?php else: ?>
                  <span class="small" style="opacity: 0.6;"><i class="fas fa-minus-circle"></i> Sin comprobante</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="status-badge <?= $statusClass ?>">
                  <i class="fas fa-<?= $icon ?>"></i>
                  <?= htmlspecialchars($o['status']) ?>
                </span>
              </td>
              <td>
                <form method="post" class="status-form">
                  <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                  <select name="status" class="input">
                    <?php foreach (allowed_statuses() as $st): ?>
                      <option value="<?= $st ?>" <?= ($o['status'] === $st ? 'selected' : '') ?>><?= $st ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn" name="update_order_status" value="1">
                    <i class="fas fa-save"></i>
                    Guardar
                  </button>
                </form>
                <?php if (!empty($o['proof_image']) && $o['status'] !== 'Pagado'): ?>
                  <form method="post" style="margin-top: 0.5rem;">
                    <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                    <input type="hidden" name="status" value="Pagado">
                    <button class="btn primary" name="update_order_status" value="1">
                      <i class="fas fa-check-circle"></i>
                      Validar pago
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
