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

/** Encuentra la URL correcta del comprobante revisando ambos directorios */
function getProofInfo(string $filename): array {
  if ($filename === '') return ['found' => false, 'url' => '', 'type' => ''];
  $ext  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
  $type = $ext === 'pdf' ? 'pdf' : 'image';
  $base = __DIR__ . '/../uploads/';
  $subs = ['proofs/', 'payments/', ''];
  foreach ($subs as $sub) {
    if (file_exists($base . $sub . $filename)) {
      return ['found' => true, 'url' => '../uploads/' . $sub . $filename, 'type' => $type];
    }
  }
  // Archivo en DB pero no en disco: mostrar enlace de payments como fallback
  return ['found' => false, 'url' => '../uploads/payments/' . $filename, 'type' => $type];
}

/** Genera link de WhatsApp al comprador del pedido */
function whatsappLink(string $phone, int $orderId, string $product): string {
  $clean = preg_replace('/[^0-9]/', '', $phone);
  // Agregar código de Costa Rica si el número tiene 8 dígitos
  if (strlen($clean) === 8) $clean = '506' . $clean;
  $text = rawurlencode("Hola! Te escribimos de COMPRATICA sobre tu pedido #$orderId ($product). ¿Tienes alguna consulta?");
  return "https://wa.me/{$clean}?text={$text}";
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

/** Construye asunto y cuerpo unificado usando order_number y todos los artículos */
function build_status_email_unified(string $estado, string $order_number, array $items, float $grand_total, string $currency): array {
  $on = htmlspecialchars($order_number, ENT_QUOTES, 'UTF-8');
  $cur = strtoupper($currency);

  // Tabla de artículos
  $rows = '';
  foreach ($items as $it) {
    $name = htmlspecialchars($it['product_name'] ?? 'Producto', ENT_QUOTES, 'UTF-8');
    $qty  = number_format((float)($it['qty'] ?? 1), 0);
    $tot  = ($cur === 'USD') ? '$'.number_format((float)($it['grand_total'] ?? 0), 2) : '₡'.number_format((float)($it['grand_total'] ?? 0), 0);
    $rows .= "<tr><td style='padding:6px 10px;border-bottom:1px solid #eee'>{$name}</td><td style='padding:6px 10px;border-bottom:1px solid #eee;text-align:center'>{$qty}</td><td style='padding:6px 10px;border-bottom:1px solid #eee;text-align:right'>{$tot}</td></tr>";
  }
  $total_fmt = ($cur === 'USD') ? '$'.number_format($grand_total, 2) : '₡'.number_format($grand_total, 0);
  $items_table = "<table width='100%' cellspacing='0' cellpadding='0' style='border-collapse:collapse;margin:12px 0'>"
    . "<thead><tr style='background:#f5f5f5'><th style='padding:8px 10px;text-align:left'>Artículo</th><th style='padding:8px 10px;text-align:center'>Cant.</th><th style='padding:8px 10px;text-align:right'>Total</th></tr></thead>"
    . "<tbody>{$rows}</tbody>"
    . "<tfoot><tr><td colspan='2' style='padding:8px 10px;font-weight:700'>Total del pedido:</td><td style='padding:8px 10px;text-align:right;font-weight:700'>{$total_fmt}</td></tr></tfoot>"
    . "</table>";

  switch ($estado) {
    case 'Pagado':
      $subject = "Tu pedido {$on} fue validado y aprobado";
      $body = "Hola,<br><br>"
            . "Tu pago ha sido <strong>validado y aprobado</strong> para el pedido <strong>{$on}</strong>.<br><br>"
            . $items_table
            . "<br>Pronto coordinaremos la entrega. ¡Gracias por tu compra!<br><br>"
            . APP_NAME;
      break;
    case 'Empacado':
      $subject = "Tu pedido {$on} está empacado";
      $body = "Hola,<br><br>"
            . "Tu pedido <strong>{$on}</strong> ha sido <strong>empacado</strong>.<br><br>"
            . $items_table
            . "<br>Nos pondremos en contacto para la entrega.<br><br>"
            . APP_NAME;
      break;
    case 'En camino':
      $subject = "Tu pedido {$on} va en camino";
      $body = "Hola,<br><br>"
            . "Tu pedido <strong>{$on}</strong> está <strong>en camino</strong>.<br><br>"
            . $items_table
            . "<br>¡Gracias por tu compra!<br><br>"
            . APP_NAME;
      break;
    case 'Entregado':
      $subject = "¡Tu pedido {$on} fue entregado!";
      $body = "Hola,<br><br>"
            . "Confirmamos que tu pedido <strong>{$on}</strong> ha sido <strong>entregado</strong>.<br><br>"
            . $items_table
            . "<br>¡Esperamos que lo disfrutes!<br><br>"
            . APP_NAME;
      break;
    case 'Cancelado':
      $subject = "Tu pedido {$on} fue cancelado";
      $body = "Hola,<br><br>"
            . "Tu pedido <strong>{$on}</strong> ha sido <strong>cancelado</strong>.<br><br>"
            . $items_table
            . "<br>Si tienes dudas, por favor contáctanos.<br><br>"
            . APP_NAME;
      break;
    default:
      $subject = "Actualización del pedido {$on}: {$estado}";
      $body = "Hola,<br><br>"
            . "Tu pedido <strong>{$on}</strong> ahora está en estado: <strong>{$estado}</strong>.<br><br>"
            . $items_table
            . "<br>Te mantendremos informado.<br><br>"
            . APP_NAME;
  }

  return [$subject, $body];
}

/** Log a archivo en /logs/ para diagnostico */
function ordlog(string $tag, array $data = []): void {
  $logDir  = __DIR__ . '/../logs';
  $logFile = $logDir . '/affiliate_orders_debug.log';
  if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
  $line = '[' . date('Y-m-d H:i:s') . '] ' . $tag;
  if ($data) $line .= ' | ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

/** POST: actualizar estado y notificar */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
  ordlog("GUARDAR_CLICK", [
    'post_order_id'          => $_POST['order_id'] ?? '(vacio)',
    'post_status'            => $_POST['status'] ?? '(vacio)',
    'post_update_order_status' => $_POST['update_order_status'] ?? '(vacio)',
    'aff_id_en_sesion'       => $aff_id,
    'session_keys'           => array_keys($_SESSION),
    'todos_los_post'         => array_keys($_POST),
  ]);
  try {
    $order_id   = (int)($_POST['order_id'] ?? 0);
    $new_status = trim((string)($_POST['status'] ?? 'Pendiente'));

    ordlog("VALORES_RECIBIDOS", [
      'order_id'   => $order_id,
      'new_status' => $new_status,
      'es_valido'  => in_array($new_status, allowed_statuses(), true) ? 'SI' : 'NO - RECHAZADO',
      'validos'    => allowed_statuses(),
    ]);

    if (!in_array($new_status, allowed_statuses(), true)) {
      throw new RuntimeException('Estado inválido: ' . $new_status);
    }

    // Cargar pedido y validar pertenencia al afiliado
    $ord = load_aff_order($pdo, $order_id, $aff_id);
    ordlog("LOAD_ORDER_RESULT", [
      'order_id'    => $order_id,
      'aff_id'      => $aff_id,
      'encontrado'  => $ord ? 'SI' : 'NO - falla aqui',
      'order_number'=> $ord['order_number'] ?? '(no encontrado)',
      'status_actual'=> $ord['status'] ?? '(no encontrado)',
      'affiliate_id_en_orden' => $ord['affiliate_id'] ?? '(null)',
      'sale_affiliate_id'     => $ord['sale_affiliate_id'] ?? '(null)',
    ]);
    if (!$ord) {
      throw new RuntimeException('Pedido no encontrado o no pertenece a este afiliado.');
    }

    $order_number    = (string)($ord['order_number'] ?? '');
    $buyerEmail      = trim((string)($ord['buyer_email'] ?? ''));
    $previous_status = (string)($ord['status'] ?? '');

    // UPDATE TODOS los items de la misma orden (mismo order_number)
    // NOTA: se omite AND affiliate_id=? porque la propiedad ya fue validada por load_aff_order().
    // Los pedidos pueden estar vinculados al afiliado vía sales.affiliate_id (no siempre en orders.affiliate_id).
    $hasUpdatedAt = orders_has_updated_at($pdo);
    if ($order_number !== '') {
      if ($hasUpdatedAt) {
        $upd = $pdo->prepare("UPDATE orders SET status=?, updated_at=datetime('now') WHERE order_number=?");
        $upd->execute([$new_status, $order_number]);
      } else {
        $upd = $pdo->prepare("UPDATE orders SET status=? WHERE order_number=?");
        $upd->execute([$new_status, $order_number]);
      }
    } else {
      // Fallback: actualizar solo este id
      if ($hasUpdatedAt) {
        $upd = $pdo->prepare("UPDATE orders SET status=?, updated_at=datetime('now') WHERE id=?");
        $upd->execute([$new_status, $order_id]);
      } else {
        $upd = $pdo->prepare("UPDATE orders SET status=? WHERE id=?");
        $upd->execute([$new_status, $order_id]);
      }
    }
    $rows_updated = $upd->rowCount();
    ordlog("UPDATE_RESULT", [
      'new_status'    => $new_status,
      'order_number'  => $order_number,
      'order_id'      => $order_id,
      'filas_afectadas' => $rows_updated,
      'ok' => $rows_updated > 0 ? 'SI - status cambiado en BD' : 'CERO FILAS - status NO se guardo en BD',
    ]);

    // Obtener TODOS los items de esta orden para el email unificado
    $all_items = [];
    $grand_total_order = 0.0;
    $currency_order = 'CRC';
    if ($order_number !== '') {
      $st_items = $pdo->prepare("SELECT o.*, p.name AS product_name FROM orders o JOIN products p ON p.id = o.product_id WHERE o.order_number = ?");
      $st_items->execute([$order_number]);
      $all_items = $st_items->fetchAll(PDO::FETCH_ASSOC);
      foreach ($all_items as $ai) {
        $grand_total_order += (float)($ai['grand_total'] ?? 0);
        if (!empty($ai['currency'])) $currency_order = strtoupper($ai['currency']);
      }
    }
    if (empty($all_items)) {
      $all_items = [['product_name' => $ord['product_name'] ?? '', 'qty' => $ord['qty'] ?? 1, 'grand_total' => $ord['grand_total'] ?? 0]];
      $grand_total_order = (float)($ord['grand_total'] ?? 0);
      $currency_order = strtoupper((string)($ord['currency'] ?? 'CRC'));
    }

    [$subject, $body] = build_status_email_unified($new_status, $order_number ?: '#'.$order_id, $all_items, $grand_total_order, $currency_order);

    // Solo enviar emails si el estado realmente cambió (evita duplicados por doble clic o re-envío)
    if ($previous_status !== $new_status) {
      error_log("[affiliate/orders.php] Notificación estado '{$previous_status}' → '{$new_status}' order {$order_number} buyer='{$buyerEmail}'");

      $buyer_email_lower = strtolower($buyerEmail);
      $admin_email_str   = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : '';
      $admin_email_lower = strtolower($admin_email_str);

      // Obtener email del afiliado
      $aff_email_str = '';
      try {
        $st_aff = $pdo->prepare("SELECT email FROM affiliates WHERE id = ? LIMIT 1");
        $st_aff->execute([$aff_id]);
        $aff_email_str = strtolower(trim((string)($st_aff->fetchColumn() ?: '')));
      } catch (Throwable $e) {
        error_log("[affiliate/orders.php] Error obteniendo email afiliado: ".$e->getMessage());
      }

      // Enviar al cliente
      if ($buyerEmail !== '') {
        try {
          $okClient = send_email($buyerEmail, $subject, $body);
          error_log("[affiliate/orders.php] send_email cliente ".($okClient ? 'OK' : 'FAIL')." (order {$order_number})");
        } catch (Throwable $e) {
          error_log("[affiliate/orders.php] Excepción email cliente: ".$e->getMessage());
        }
      }

      // Aviso al admin (solo si su email es distinto al del comprador)
      if ($admin_email_str !== '' && $admin_email_lower !== $buyer_email_lower) {
        try {
          $okAdm = send_email($admin_email_str, "[Afiliado] Pedido {$order_number} → {$new_status}", $body);
          error_log("[affiliate/orders.php] send_email admin ".($okAdm ? 'OK' : 'FAIL')." (order {$order_number})");
        } catch (Throwable $e) {
          error_log("[affiliate/orders.php] Excepción email admin: ".$e->getMessage());
        }
      }

      // Aviso al afiliado/vendedor (solo si es distinto al comprador y al admin)
      if ($aff_email_str !== '' && $aff_email_str !== $buyer_email_lower && $aff_email_str !== $admin_email_lower) {
        try {
          $okAff = send_email($aff_email_str, "[Vendedor] Pedido {$order_number} → {$new_status}", $body);
          error_log("[affiliate/orders.php] send_email afiliado ".($okAff ? 'OK' : 'FAIL')." (order {$order_number})");
        } catch (Throwable $e) {
          error_log("[affiliate/orders.php] Excepción email afiliado: ".$e->getMessage());
        }
      }
    } else {
      error_log("[affiliate/orders.php] Estado '{$new_status}' ya era el mismo; no se envían emails (order {$order_number})");
    }

    $msg = "Pedido {$order_number} actualizado a '{$new_status}' (todos los artículos).";
    // PRG: redirect para evitar reenvío del form al refrescar
    header('Location: orders.php?msg=' . urlencode($msg));
    exit;
  } catch (Throwable $e) {
    $msg = 'Error: ' . $e->getMessage();
    ordlog("EXCEPCION", ['error' => $e->getMessage(), 'linea' => $e->getLine(), 'archivo' => basename($e->getFile())]);
    error_log("[affiliate/orders.php] ERROR al actualizar estado: ".$e->getMessage());
  }
}

// Leer mensaje de redirect si viene en GET
if (empty($msg) && !empty($_GET['msg'])) {
  $msg = (string)$_GET['msg'];
}

/** Listado de pedidos del afiliado - agrupados por order_number */
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
  LIMIT 500
");
$list->execute([$aff_id, $aff_id]);
$raw_orders = $list->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por order_number: una fila = una orden completa
$orders_grouped = [];
foreach ($raw_orders as $row) {
  $key = !empty($row['order_number']) ? $row['order_number'] : 'no-order-'.$row['id'];
  if (!isset($orders_grouped[$key])) {
    $orders_grouped[$key] = [
      'id'           => $row['id'],
      'order_number' => $row['order_number'] ?? '',
      'buyer_name'   => $row['buyer_name'] ?? '',
      'buyer_email'  => $row['buyer_email'] ?? '',
      'buyer_phone'  => $row['buyer_phone'] ?? '',
      'payment_method'=> $row['payment_method'] ?? '',
      'status'       => $row['status'] ?? 'Pendiente',
      'currency'     => $row['currency'] ?? 'CRC',
      'note'         => $row['note'] ?? '',
      'created_at'   => $row['created_at'] ?? '',
      'proof_file'   => $row['proof_image'] ?? '',
      'grand_total'  => 0.0,
      'items'        => [],
    ];
  }
  $orders_grouped[$key]['items'][] = [
    'product_name'  => $row['product_name'] ?? 'Producto',
    'product_image' => $row['product_image'] ?? '',
    'qty'           => $row['qty'] ?? 1,
    'grand_total'   => (float)($row['grand_total'] ?? 0),
  ];
  $orders_grouped[$key]['grand_total'] += (float)($row['grand_total'] ?? 0);
}
$orders = array_values($orders_grouped);
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
            <th>Orden</th>
            <th>Fecha</th>
            <th>Artículos</th>
            <th>Total</th>
            <th>Cliente</th>
            <th>Notas / Dirección</th>
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
            $cur = strtoupper($o['currency'] ?? 'CRC');
            $total_fmt = ($cur === 'USD') ? '$'.number_format($o['grand_total'], 2) : '₡'.number_format($o['grand_total'], 0);
          ?>
            <tr>
              <td><strong style="font-size:0.85rem"><?= htmlspecialchars($o['order_number'] ?: '#'.$o['id']) ?></strong></td>
              <td class="small"><?= htmlspecialchars(substr($o['created_at'], 0, 16)) ?></td>
              <td>
                <?php foreach ($o['items'] as $it): ?>
                  <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:4px;">
                    <?php if(!empty($it['product_image'])): ?>
                      <img class="thumb" style="width:40px;height:40px" src="../uploads/<?= htmlspecialchars($it['product_image']) ?>" alt="">
                    <?php endif; ?>
                    <div>
                      <div><?= htmlspecialchars($it['product_name']) ?></div>
                      <div class="small">Cant: <?= (int)$it['qty'] ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </td>
              <td><strong><?= $total_fmt ?></strong></td>
              <td class="small">
                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                  <span><?= htmlspecialchars($o['buyer_name'] ?? '') ?></span>
                  <span><i class="fas fa-envelope" style="opacity: 0.5;"></i> <?= htmlspecialchars($o['buyer_email']) ?></span>
                  <span><i class="fas fa-phone" style="opacity: 0.5;"></i> <?= htmlspecialchars($o['buyer_phone']) ?></span>
                </div>
              </td>
              <td class="small">
                <?php
                  $note_text = trim($o['note'] ?? '');
                  if ($note_text === '') {
                    echo '<span style="color:#bbb">—</span>';
                  } else {
                    $note_safe = htmlspecialchars($note_text, ENT_QUOTES, 'UTF-8');
                    $note_linked = preg_replace(
                      '/(https?:\/\/www\.google\.com\/maps[^\s|<]*)/i',
                      '<a href="$1" target="_blank" style="color:var(--primary);white-space:nowrap">'
                      . '<i class="fas fa-map-marker-alt"></i> Ver mapa</a>',
                      $note_safe
                    );
                    echo $note_linked;
                  }
                ?>
              </td>
              <td>
                <?php
                  $proof = getProofInfo($o['proof_file'] ?? '');
                  if ($proof['found']):
                    if ($proof['type'] === 'pdf'): ?>
                      <a href="<?= htmlspecialchars($proof['url']) ?>" target="_blank"
                         style="display:inline-flex;align-items:center;gap:6px;padding:6px 10px;background:#fef3c7;border:1px solid #f59e0b;border-radius:6px;color:#92400e;font-size:.8rem;font-weight:600;text-decoration:none;">
                        <i class="fas fa-file-pdf" style="color:#ef4444;font-size:1.2rem;"></i> Ver PDF
                      </a>
                    <?php else: ?>
                      <a href="<?= htmlspecialchars($proof['url']) ?>" target="_blank">
                        <img class="thumb" src="<?= htmlspecialchars($proof['url']) ?>"
                             alt="Comprobante" title="Click para ver comprobante completo"
                             onerror="this.onerror=null;this.closest('a').innerHTML='<span style=\'font-size:.75rem;color:#ef4444;\'><i class=\'fas fa-exclamation-circle\'></i> Error al cargar</span>';">
                      </a>
                    <?php endif;
                  elseif (!empty($o['proof_file'])): ?>
                    <a href="<?= htmlspecialchars($proof['url']) ?>" target="_blank"
                       style="display:inline-flex;align-items:center;gap:5px;font-size:.8rem;color:#2563eb;">
                      <i class="fas fa-external-link-alt"></i> Ver adjunto
                    </a>
                  <?php else: ?>
                    <span class="small" style="opacity:.6;"><i class="fas fa-minus-circle"></i> Sin comprobante</span>
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
                <?php if (!empty($o['proof_file']) && $o['status'] !== 'Pagado'): ?>
                  <form method="post" style="margin-top: 0.5rem;">
                    <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                    <input type="hidden" name="status" value="Pagado">
                    <button class="btn primary" name="update_order_status" value="1">
                      <i class="fas fa-check-circle"></i>
                      Validar pago
                    </button>
                  </form>
                <?php endif; ?>
                <?php if (!empty($o['buyer_phone'])): ?>
                  <?php
                    $wa_items_list = implode(', ', array_column($o['items'], 'product_name'));
                    $wa_order = htmlspecialchars($o['order_number'] ?: '#'.$o['id']);
                  ?>
                  <a href="<?= htmlspecialchars(whatsappLink($o['buyer_phone'], (int)$o['id'], $wa_items_list)) ?>"
                     target="_blank" rel="noopener"
                     style="margin-top:0.5rem;display:inline-flex;align-items:center;gap:6px;
                            padding:0.45rem 0.9rem;background:#25D366;color:white;text-decoration:none;
                            border-radius:6px;font-size:0.8rem;font-weight:600;
                            box-shadow:0 2px 6px rgba(37,211,102,.35);transition:all .2s;"
                     onmouseover="this.style.background='#1ebe5e'" onmouseout="this.style.background='#25D366'">
                    <i class="fab fa-whatsapp" style="font-size:1rem;"></i> WhatsApp
                  </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
<script>
// Deshabilitar botones de submit al primer clic para evitar doble envío
document.querySelectorAll('form[method="post"] button[name="update_order_status"]').forEach(function(btn) {
  btn.closest('form').addEventListener('submit', function() {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
  });
});
</script>
</body>
</html>
