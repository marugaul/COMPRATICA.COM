<?php
// affiliate/orders.php — UTF-8 (sin BOM)
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

/*session_start();*/

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}


$aff_id = (int)($_SESSION['aff_id'] ?? 0); // <-- usar la misma clave que pone el login
if ($aff_id <= 0) {
  header('Location: login.php');
  exit;
}

$pdo = db();
$msg = '';

/** Util: lista de estados permitidos (coinciden con admin) */
function allowed_statuses() {
  return ['Pendiente','Pagado','Empacado','En camino','Entregado','Cancelado'];
}

/** Util: verifica que el pedido pertenezca a este afiliado */
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

/** POST: actualizar estado */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
  try {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $new_status = trim($_POST['status'] ?? 'Pendiente');

    if (!in_array($new_status, allowed_statuses(), true)) {
      throw new RuntimeException('Estado inválido.');
    }

    // Cargar pedido y validar pertenencia al afiliado
    $ord = load_aff_order($pdo, $order_id, $aff_id);
    if (!$ord) {
      throw new RuntimeException('Pedido no encontrado o no pertenece a este afiliado.');
    }

    // Actualizar estado
    $upd = $pdo->prepare("UPDATE orders SET status=?, updated_at=datetime('now') WHERE id=?");
    $upd->execute([$new_status, $order_id]);

    // Si marcó como Pagado -> enviar correo al cliente
    if ($new_status === 'Pagado') {
      $buyer = trim((string)$ord['buyer_email']);
      $subject = "Tu pedido #{$order_id} fue validado y aprobado";
      $body = "Hola,<br><br>"
            . "Tu pago ha sido <strong>validado y aprobado</strong> para el pedido <strong>#{$order_id}</strong> "
            . "del artículo <strong>".htmlspecialchars($ord['product_name'])."</strong>.<br>"
            . "Pronto coordinaremos la entrega. ¡Gracias por tu compra!<br><br>"
            . APP_NAME;

      if ($buyer !== '') {
        try { send_mail($buyer, $subject, $body); } catch (Throwable $e) { /* opcional: log */ }
      }
      // Aviso opcional al admin
      try { send_mail(ADMIN_EMAIL, "[Afiliado] Pedido #{$order_id} marcado como Pagado", $body); } catch (Throwable $e) {}
    }

    $msg = "Estado del pedido #{$order_id} actualizado a '{$new_status}'.";
  } catch (Throwable $e) {
    $msg = 'Error: ' . $e->getMessage();
  }
}

/** Listado de pedidos del afiliado */
$list = $pdo->prepare("
  SELECT 
    o.*,
    p.name AS product_name,
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
