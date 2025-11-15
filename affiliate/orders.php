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
  <title>Afiliados — Pedidos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/style.css">
  <style>
    .thumb{width:60px;height:60px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb}
    .status-form{display:flex;gap:6px;align-items:center}
    .nowrap{white-space:nowrap}
  </style>
</head>
<body>
<header class="header">
  <div class="logo">🛒 Afiliados — Pedidos</div>
  <nav>
    <a class="btn" href="dashboard.php">Dashboard</a>
    <a class="btn" href="products.php">Mis productos</a>
    <a class="btn" href="sales.php">Mis espacios</a>
    <a class="btn" href="logout.php">Salir</a>
  </nav>
</header>

<div class="container">
  <?php if (!empty($msg)): ?>
    <div class="success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="card">
    <h3>Pedidos (últimos 200)</h3>
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
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><?= (int)$o['id'] ?></td>
            <td class="small"><?= htmlspecialchars($o['created_at']) ?></td>
            <td>
              <?php if(!empty($o['product_image'])): ?>
                <img class="thumb" src="../uploads/<?= htmlspecialchars($o['product_image']) ?>" alt="">
              <?php endif; ?>
              <div><?= htmlspecialchars($o['product_name']) ?></div>
            </td>
            <td><?= (int)$o['qty'] ?></td>
            <td class="small">
              <?= htmlspecialchars($o['buyer_email']) ?><br>
              <?= htmlspecialchars($o['buyer_phone']) ?>
            </td>
            <td class="small"><?= htmlspecialchars($o['residency']) ?></td>
            <td>
              <?php if(!empty($o['proof_image'])): ?>
                <a href="../uploads/payments/<?= htmlspecialchars($o['proof_image']) ?>" target="_blank">
                  <img class="thumb" src="../uploads/payments/<?= htmlspecialchars($o['proof_image']) ?>" alt="Comprobante">
                </a>
              <?php else: ?>
                <span class="small">Sin comprobante</span>
              <?php endif; ?>
            </td>
            <td class="small"><strong><?= htmlspecialchars($o['status']) ?></strong></td>
            <td>
              <form method="post" class="status-form">
                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                <select name="status" class="input" style="padding:6px">
                  <?php foreach (allowed_statuses() as $st): ?>
                    <option value="<?= $st ?>" <?= ($o['status'] === $st ? 'selected' : '') ?>><?= $st ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn" name="update_order_status" value="1">Guardar</button>
              </form>
              <?php if (!empty($o['proof_image']) && $o['status'] !== 'Pagado'): ?>
                <form method="post" style="margin-top:6px">
                  <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                  <input type="hidden" name="status" value="Pagado">
                  <button class="btn primary" name="update_order_status" value="1">Validar pago y notificar</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($orders)): ?>
          <tr><td colspan="9" class="small">No hay pedidos por ahora.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
