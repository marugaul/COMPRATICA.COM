<?php
// affiliate/update_order_status.php — Endpoint AJAX para actualizar estado de pedido
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

$aff_id = (int)($_SESSION['aff_id'] ?? 0);
if ($aff_id <= 0) {
  echo json_encode(['ok' => false, 'msg' => 'Sesión expirada. Recarga la página.']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
  exit;
}

$allowed    = ['Pendiente','Pagado','Empacado','En camino','Entregado','Cancelado'];
$order_id   = (int)($_POST['order_id'] ?? 0);
$new_status = trim((string)($_POST['status'] ?? ''));

if ($order_id <= 0 || !in_array($new_status, $allowed, true)) {
  echo json_encode(['ok' => false, 'msg' => 'Datos inválidos.']);
  exit;
}

try {
  $pdo = db();

  // Verificar que el pedido pertenece al afiliado
  $st = $pdo->prepare("
    SELECT o.*, p.name AS product_name
    FROM orders o
    JOIN products p ON p.id = o.product_id
    LEFT JOIN sales s ON s.id = p.sale_id
    WHERE o.id = ? AND (s.affiliate_id = ? OR o.affiliate_id = ?)
    LIMIT 1
  ");
  $st->execute([$order_id, $aff_id, $aff_id]);
  $ord = $st->fetch(PDO::FETCH_ASSOC);

  if (!$ord) {
    echo json_encode(['ok' => false, 'msg' => 'Pedido no encontrado o no tienes permiso.']);
    exit;
  }

  $order_number    = (string)($ord['order_number'] ?? '');
  $previous_status = (string)($ord['status'] ?? '');
  $buyerEmail      = trim((string)($ord['buyer_email'] ?? ''));

  // Verificar si la tabla tiene updated_at
  $has_updated_at = false;
  foreach ($pdo->query("PRAGMA table_info(orders)")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    if (strtolower($c['name']) === 'updated_at') { $has_updated_at = true; break; }
  }

  // Actualizar todos los items de la misma orden
  if ($order_number !== '') {
    $sql = $has_updated_at
      ? "UPDATE orders SET status=?, updated_at=datetime('now') WHERE order_number=?"
      : "UPDATE orders SET status=? WHERE order_number=?";
    $upd = $pdo->prepare($sql);
    $upd->execute([$new_status, $order_number]);
  } else {
    $sql = $has_updated_at
      ? "UPDATE orders SET status=?, updated_at=datetime('now') WHERE id=?"
      : "UPDATE orders SET status=? WHERE id=?";
    $upd = $pdo->prepare($sql);
    $upd->execute([$new_status, $order_id]);
  }
  $rows = $upd->rowCount();
  error_log("[update_order_status] aff={$aff_id} order={$order_number}({$order_id}) {$previous_status}→{$new_status} rows={$rows}");

  // Enviar emails solo si el estado cambió
  if ($previous_status !== $new_status) {
    $on      = $order_number ?: '#' . $order_id;
    $subject = "Actualización del pedido {$on}: {$new_status}";
    $body    = "Hola,<br><br>Tu pedido <strong>" . htmlspecialchars($on, ENT_QUOTES, 'UTF-8')
             . "</strong> ha cambiado a estado: <strong>" . htmlspecialchars($new_status, ENT_QUOTES, 'UTF-8')
             . "</strong>.<br><br>" . (defined('APP_NAME') ? APP_NAME : 'COMPRATICA');

    $admin_email = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : '';

    if ($buyerEmail !== '') {
      try { send_email($buyerEmail, $subject, $body); } catch (Throwable $e) {
        error_log("[update_order_status] email comprador falló: " . $e->getMessage());
      }
    }
    if ($admin_email !== '' && strtolower($admin_email) !== strtolower($buyerEmail)) {
      try { send_email($admin_email, "[Afiliado] Pedido {$on} → {$new_status}", $body); } catch (Throwable $e) {
        error_log("[update_order_status] email admin falló: " . $e->getMessage());
      }
    }
    try {
      $sa = $pdo->prepare("SELECT email FROM affiliates WHERE id=? LIMIT 1");
      $sa->execute([$aff_id]);
      $aff_email = strtolower(trim((string)($sa->fetchColumn() ?: '')));
      if ($aff_email && $aff_email !== strtolower($buyerEmail) && $aff_email !== strtolower($admin_email)) {
        send_email($aff_email, "[Vendedor] Pedido {$on} → {$new_status}", $body);
      }
    } catch (Throwable $e) {
      error_log("[update_order_status] email afiliado falló: " . $e->getMessage());
    }
  }

  $label = $order_number ?: '#' . $order_id;
  echo json_encode(['ok' => true, 'msg' => "Pedido {$label} actualizado a '{$new_status}'."]);

} catch (Throwable $e) {
  error_log("[update_order_status] ERROR: " . $e->getMessage());
  echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}
