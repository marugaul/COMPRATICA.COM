<?php
// buy_affiliate.php — Flujo de compra con métodos del AFILIADO (PayPal/SINPE)
// Colocar junto a checkout.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/mailer.php'; // para send_mail / send_email

if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
ini_set('default_charset', 'UTF-8');

$pdo = db();
$msg = '';
$step = 'init'; // init | sinpe_upload | done

/* ------------ Helpers ------------ */
if (!function_exists('now_iso')) {
  function now_iso(){ return date('Y-m-d H:i:s'); }
}
function load_product(PDO $pdo, int $id) {
  $st = $pdo->prepare("SELECT * FROM products WHERE id=? AND active=1");
  $st->execute([$id]);
  return $st->fetch(PDO::FETCH_ASSOC);
}
function get_exrate() {
  if (function_exists('get_exchange_rate')) {
    $x = (float)get_exchange_rate();
    return $x > 0 ? $x : 1.0;
  }
  return 1.0;
}
function totals_from_product(array $p, int $qty, string $shipping): array {
  $currency = strtoupper($p['currency'] ?? 'CRC');
  $price    = (float)($p['price'] ?? 0);
  $qty      = max(1, $qty);
  $ex       = get_exrate();

  $shipping_fee_crc = ($shipping === 'delivery') ? 2000.0 : 0.0;

  if ($currency === 'USD') {
    $subtotal_usd = round($price * $qty, 2);
    $subtotal_crc = round($subtotal_usd * $ex, 2);
  } else { // CRC
    $subtotal_crc = round($price * $qty, 2);
    $subtotal_usd = ($ex > 0) ? round($subtotal_crc / $ex, 2) : round($subtotal_crc, 2);
  }

  $total_crc = $subtotal_crc + $shipping_fee_crc;
  $total_usd = ($ex > 0) ? round($total_crc / $ex, 2) : round($total_crc, 2);

  return [
    'exrate'         => $ex,
    'subtotal_crc'   => $subtotal_crc,
    'subtotal_usd'   => $subtotal_usd,
    'shipping_crc'   => $shipping_fee_crc,
    'total_crc'      => $total_crc,
    'total_usd'      => $total_usd,
    'currency'       => $currency,
  ];
}
function send_any_mail($to, $subj, $html){
  if (!$to) return false;
  if (function_exists('send_mail'))  return @send_mail($to, $subj, $html);
  if (function_exists('send_email')) return @send_email($to, $subj, $html);
  return false;
}

/** Devuelve set de columnas existentes en orders (nombre en minúsculas) */
function orders_columns(PDO $pdo): array {
  $cols = [];
  try {
    $rows = $pdo->query("PRAGMA table_info(orders)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
      if (!empty($r['name'])) $cols[strtolower($r['name'])] = true;
    }
  } catch (Throwable $e) {
    error_log("[buy_affiliate] PRAGMA table_info(orders) error: ".$e->getMessage());
  }
  return $cols;
}

/** Inserta en orders usando SOLO las columnas que existan realmente */
function insert_order_dynamic(PDO $pdo, array $data): int {
  $cols_exist = orders_columns($pdo);
  if (empty($cols_exist)) {
    throw new RuntimeException('No se pudo leer el esquema de la tabla orders.');
  }

  $cols = [];
  $vals = [];
  $args = [];

  foreach ($data as $col => $val) {
    $lc = strtolower($col);
    if (isset($cols_exist[$lc])) {
      $cols[] = $col;
      $vals[] = '?';
      $args[] = $val;
    }
  }

  if (empty($cols)) {
    throw new RuntimeException('No hay columnas compatibles para insertar en orders.');
  }

  $sql = "INSERT INTO orders (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")";
  $st = $pdo->prepare($sql);
  $st->execute($args);
  return (int)$pdo->lastInsertId();
}
/* --------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // POST desde checkout
  $product_id   = (int)($_POST['product_id'] ?? 0);
  $qty          = max(1, (int)($_POST['qty'] ?? 1));
  $buyer_email  = trim($_POST['buyer_email'] ?? '');
  $buyer_phone  = trim($_POST['buyer_phone'] ?? '');
  $residency    = trim($_POST['residency'] ?? '');
  $shipping     = trim($_POST['shipping'] ?? 'pickup'); // pickup|delivery
  $note         = trim($_POST['note'] ?? '');
  $sale_id      = (int)($_POST['sale_id'] ?? 0);
  $affiliate_id = (int)($_POST['affiliate_id'] ?? 0);
  $method       = trim($_POST['payment_method'] ?? ''); // sinpe|paypal

  // Cargar producto
  $p = $product_id ? load_product($pdo, $product_id) : null;
  if (!$p) {
    $msg = 'Producto no encontrado o inactivo.';
  } else {
    if (!$affiliate_id && !empty($p['affiliate_id'])) {
      $affiliate_id = (int)$p['affiliate_id'];
    }
    if ($affiliate_id <= 0) {
      $msg = 'No se pudo determinar el afiliado propietario.';
    }
  }

  // Cargar métodos del afiliado
  if (!$msg) {
    $pm = $pdo->query("SELECT * FROM affiliate_payment_methods WHERE affiliate_id={$affiliate_id} LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    $has_sinpe  = !empty($pm['active_sinpe'])  && !empty($pm['sinpe_phone']);
    $has_paypal = !empty($pm['active_paypal']) && !empty($pm['paypal_email']) && filter_var($pm['paypal_email'], FILTER_VALIDATE_EMAIL);

    if ($method === 'sinpe' && !$has_sinpe)   $msg = 'El afiliado no tiene SINPE habilitado.';
    if ($method === 'paypal' && !$has_paypal) $msg = 'El afiliado no tiene PayPal habilitado.';
  }

  // Totales
  if (!$msg) {
    $tot = totals_from_product($p, $qty, $shipping);
  }

  // ------------------ PAYPAL (afiliado) ------------------
  if (!$msg && $method === 'paypal') {
    $amount_usd = $tot['total_usd'];
    if ($amount_usd <= 0) {
      $msg = 'Monto inválido para PayPal.';
    } else {
      $item_name = urlencode(APP_NAME . ' - ' . ($p['name'] ?? ('Producto #'.$product_id)));
      $base   = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'];
      $return = $base . "/gracias.php";
      $cancel = $base . "/cancelado.php";

      $paypal_link = "https://www.paypal.com/cgi-bin/webscr?cmd=_xclick"
                   . "&business=" . urlencode($pm['paypal_email'])
                   . "&item_name=" . $item_name
                   . "&amount=" . number_format($amount_usd, 2, '.', '')
                   . "&currency_code=USD"
                   . "&no_note=1&no_shipping=1&rm=2"
                   . "&return=" . urlencode($return)
                   . "&cancel_return=" . urlencode($cancel);

      header("Location: {$paypal_link}");
      exit;
    }
  }

  // ------------------ SINPE (afiliado) ------------------
  if (!$msg && $method === 'sinpe') {
    $step = 'sinpe_upload';

    // ¿Llegó el comprobante? (segundo submit)
    if (!empty($_FILES['proof']['name'])) {
      try {
        // Guardar comprobante donde orders.php lo espera:
        @mkdir(__DIR__ . '/uploads/payments', 0775, true);
        $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','gif','pdf'])) $ext = 'jpg';
        $fname = 'aff_' . $affiliate_id . '_p' . $product_id . '_' . uniqid() . '.' . $ext;
        $dest  = __DIR__ . '/uploads/payments/' . $fname;
        if (!is_uploaded_file($_FILES['proof']['tmp_name']) || !move_uploaded_file($_FILES['proof']['tmp_name'], $dest)) {
          throw new RuntimeException('No se pudo guardar el comprobante.');
        }

        // ------- Crear pedido en orders (estado Pendiente) dinámico -------
        $now = now_iso();
        $data = [
          'affiliate_id'  => $affiliate_id,
          'sale_id'       => $sale_id,
          'product_id'    => $product_id,
          'qty'           => $qty,
          'buyer_email'   => $buyer_email,
          'buyer_phone'   => $buyer_phone,
          'residency'     => $residency,
          'shipping'      => $shipping,
          'note'          => $note,

          'price_crc'     => $tot['subtotal_crc'],
          'price_usd'     => $tot['subtotal_usd'],
          'exrate_used'   => $tot['exrate'],
          'shipping_crc'  => $tot['shipping_crc'],
          'total_crc'     => $tot['total_crc'],
          'total_usd'     => $tot['total_usd'],

          // Estas se insertarán solo si existen en tu tabla:
          'payment_method'=> 'SINPE',
          'status'        => 'Pendiente',
          'proof_image'   => $fname,
          'created_at'    => $now,
          'updated_at'    => $now,
        ];

        $order_id = insert_order_dynamic($pdo, $data);

        // ------- Emails: admin y cliente -------
        $pname = htmlspecialchars($p['name'] ?? ('Producto #'.$product_id));
        $monto_str_crc = '₡' . number_format($tot['total_crc'], 0, ',', '.');
        $file_url = htmlspecialchars("uploads/payments/{$fname}");

        // Admin
        $admin_to = (defined('ADMIN_EMAIL') && ADMIN_EMAIL) ? ADMIN_EMAIL : '';
        $subj_admin = "[Compratica] Comprobante SINPE recibido — Pedido #".($order_id ?: 'N/D');
        $body_admin = "Se recibió un comprobante de pago por SINPE.<br>"
                    . "Pedido ID: <strong>".($order_id ?: 'N/D')."</strong><br>"
                    . "Producto: <strong>{$pname}</strong><br>"
                    . "Cantidad: <strong>".(int)$qty."</strong><br>"
                    . "Total: <strong>{$monto_str_crc}</strong><br>"
                    . "Comprador: <strong>".htmlspecialchars($buyer_email)."</strong> — Tel: <strong>".htmlspecialchars($buyer_phone)."</strong><br>"
                    . "Residencia: <strong>".htmlspecialchars($residency)."</strong><br>"
                    . "Archivo: <a href=\"{$file_url}\" target=\"_blank\">Ver comprobante</a><br>"
                    . "Estado: <strong>Pendiente</strong> de confirmación.";

        // Cliente
        $subj_client = "Recibimos tu comprobante — Pedido #".($order_id ?: 'N/D');
        $body_client = "Hola,<br>"
                     . "Recibimos tu comprobante por SINPE para <strong>{$pname}</strong>.<br>"
                     . "Total: <strong>{$monto_str_crc}</strong>.<br>"
                     . "Tu pedido quedó <strong>Pendiente de confirmación</strong>. Te avisaremos por correo.<br><br>"
                     . APP_NAME;

        if ($admin_to)     { send_any_mail($admin_to,     $subj_admin,  $body_admin); }
        if ($buyer_email)  { send_any_mail($buyer_email,  $subj_client, $body_client); }

        // (Opcional) avisar también al afiliado dueño
        try {
          $aff_email_to = $pdo->prepare("SELECT email FROM affiliates WHERE id=? LIMIT 1");
          $aff_email_to->execute([$affiliate_id]);
          $aff_email_to = (string)$aff_email_to->fetchColumn();
          if ($aff_email_to) { send_any_mail($aff_email_to, $subj_admin,  $body_admin); }
        } catch (Throwable $e) { error_log("[buy_affiliate] obtener correo afiliado: ".$e->getMessage()); }

        $step = 'done';
      } catch (Throwable $e) {
        $msg = 'Error al subir comprobante: ' . $e->getMessage();
      }
    }
  }
} else {
  $msg = 'Acceso inválido.';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Compra — <?= htmlspecialchars(APP_NAME) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/style.css?v=14">
  <style>.brand-img{width:220px;max-width:100%;height:auto;border:1px solid #eee;border-radius:10px}</style>
</head>
<body>
<header class="header">
  <div class="logo">🧾 Compra</div>
  <nav><a class="btn" href="index.php">Volver</a></nav>
</header>

<div class="container">
  <?php if (!empty($msg)): ?>
    <div class="alert"><?= htmlspecialchars($msg) ?></div>
  <?php else: ?>

    <?php if ($step === 'sinpe_upload'): ?>
      <div class="card">
        <h3>Pago por SINPE Móvil</h3>
        <p>Enviá el monto al teléfono del vendedor (afiliado) y subí tu comprobante para registrar tu pedido.</p>
        <div class="small">Teléfono SINPE destino: <strong><?= htmlspecialchars($pm['sinpe_phone']) ?></strong></div>
        <img class="brand-img" src="assets/sinpe.jpg" alt="SINPE Móvil">
        <form class="form" method="post" enctype="multipart/form-data">
          <!-- Reenviamos contexto original para procesar el upload -->
          <input type="hidden" name="product_id" value="<?= (int)$product_id ?>">
          <input type="hidden" name="qty" value="<?= (int)$qty ?>">
          <input type="hidden" name="buyer_email" value="<?= htmlspecialchars($buyer_email) ?>">
          <input type="hidden" name="buyer_phone" value="<?= htmlspecialchars($buyer_phone) ?>">
          <input type="hidden" name="residency" value="<?= htmlspecialchars($residency) ?>">
          <input type="hidden" name="shipping" value="<?= htmlspecialchars($shipping) ?>">
          <input type="hidden" name="note" value="<?= htmlspecialchars($note) ?>">
          <input type="hidden" name="sale_id" value="<?= (int)$sale_id ?>">
          <input type="hidden" name="affiliate_id" value="<?= (int)$affiliate_id ?>">
          <input type="hidden" name="payment_method" value="sinpe">

          <label>Comprobante (imagen o PDF)
            <input class="input" type="file" name="proof" accept="image/*,application/pdf" required>
          </label>
          <button class="btn primary" type="submit">Enviar comprobante</button>
        </form>
        <p class="note">Recibirás confirmación por correo. Tu pedido quedará <strong>Pendiente</strong> hasta validar el pago.</p>
      </div>

    <?php elseif ($step === 'done'): ?>
      <div class="success">
        ¡Listo! Recibimos tu comprobante y creamos tu pedido. Te avisaremos cuando se confirme el pago.
      </div>

    <?php else: ?>
      <div class="alert">Flujo no reconocido.</div>
    <?php endif; ?>

  <?php endif; ?>
</div>

<footer class="container small">© <?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?></footer>
</body>
</html>
