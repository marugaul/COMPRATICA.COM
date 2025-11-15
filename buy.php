<?php
// buy.php — versión segura con PayPal visible (UTF-8, sin BOM)
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';
if (file_exists(__DIR__ . '/includes/notify.php')) {
  require_once __DIR__ . '/includes/notify.php';
}

ini_set('display_errors', '0');
error_reporting(E_ALL);

$pdo = db();
$ok = false;
$error = '';
$order_id = 0;
$p = null;

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new RuntimeException('Solicitud inválida.');
  }

  // Datos del formulario
  $product_id   = (int)($_POST['product_id'] ?? 0);
  $qty          = max(1, (int)($_POST['qty'] ?? 1));
  $email        = trim((string)($_POST['buyer_email'] ?? ''));
  $phone        = trim((string)($_POST['buyer_phone'] ?? ''));
  $residency    = trim((string)($_POST['residency'] ?? 'Otro'));
  $note         = trim((string)($_POST['note'] ?? ''));

  // Multi-afiliados
  $affiliate_id = (int)($_POST['affiliate_id'] ?? 0);
  $sale_id      = (int)($_POST['sale_id'] ?? 0);

  if (!$product_id) throw new RuntimeException('Producto no especificado.');

  // Cargar producto + datos del espacio (sale)
  $st = $pdo->prepare("
    SELECT
      p.*,
      s.id        AS sale_id,
      s.affiliate_id AS sale_affiliate_id,
      s.title     AS sale_title,
      s.start_at  AS sale_start,
      s.end_at    AS sale_end,
      s.is_active AS sale_active
    FROM products p
    LEFT JOIN sales s ON s.id = p.sale_id
    WHERE p.id=? AND p.active=1
  ");
  $st->execute([$product_id]);
  $p = $st->fetch(PDO::FETCH_ASSOC);
  if (!$p) throw new RuntimeException('Producto no encontrado o inactivo.');

  if ($email === '' || $phone === '') throw new RuntimeException('Correo y teléfono son requeridos.');
  if ((int)$p['stock'] < $qty) throw new RuntimeException('No hay suficiente inventario.');

  // Si no vino afiliado en POST, usar el del producto/espacio si existe
  if (!$affiliate_id) {
    if (!empty($p['affiliate_id'])) {
      $affiliate_id = (int)$p['affiliate_id'];
    } elseif (!empty($p['sale_affiliate_id'])) {
      $affiliate_id = (int)$p['sale_affiliate_id'];
    }
  }
  if (!$sale_id && !empty($p['sale_id'])) {
    $sale_id = (int)$p['sale_id'];
  }

  $pdo->beginTransaction();

  // Insertar orden (requiere columnas: affiliate_id, sale_id)
  $ins = $pdo->prepare("INSERT INTO orders
    (product_id, qty, buyer_email, buyer_phone, residency, note, created_at, status, affiliate_id, sale_id)
    VALUES (?,?,?,?,?,?,?,? ,?,?)");
  $ins->execute([
    $product_id, $qty, $email, $phone, $residency, $note,
    (function_exists('now_iso') ? now_iso() : date('Y-m-d H:i:s')),
    'Pendiente', $affiliate_id, $sale_id
  ]);
  $order_id = (int)$pdo->lastInsertId();

  // Descontar stock
  $pdo->prepare("UPDATE products SET stock = stock - ?, updated_at = ? WHERE id = ?")
      ->execute([$qty, (function_exists('now_iso') ? now_iso() : date('Y-m-d H:i:s')), $product_id]);

  $pdo->commit();
  $ok = true;

  // Notificaciones (si existen helpers)
  if (function_exists('send_email') && function_exists('email_order_created')) {
    $order = [
      'id'=>$order_id,'qty'=>$qty,'buyer_email'=>$email,
      'buyer_phone'=>$phone,'residency'=>$residency,'note'=>$note
    ];
    @send_email($email, 'Pedido recibido #'.$order_id, email_order_created($order, $p));
    @send_email(ADMIN_EMAIL, 'Nuevo pedido #'.$order_id, email_order_created($order, $p));
  }

} catch (Throwable $e) {
  if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
  $error = $e->getMessage();
  error_log("[buy.php] ERROR: ".$e->getMessage()." @ ".$e->getFile().":".$e->getLine());
}

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Confirmación - <?php echo APP_NAME; ?></title>
<link rel="stylesheet" href="assets/style.css">
<style>
  .btn.paypal-btn { display:inline-flex; align-items:center; gap:8px; }
  .btn.paypal-btn img { height:18px; width:auto; display:block; }
  .brand-img { width:220px; max-width:100%; height:auto; border:1px solid #eee; border-radius:10px; }
</style>
</head>
<body>
<header class="header">
  <div class="logo">🛒 <?php echo APP_NAME; ?></div>
  <nav><a class="btn" href="index.php">Volver</a></nav>
</header>

<div class="container">
<?php if ($ok): ?>
  <div class="success">
    <h2>¡Pedido #<?php echo (int)$order_id; ?> creado!</h2>
    <p>Te contactaremos al <strong><?php echo htmlspecialchars($phone); ?></strong> o <strong><?php echo htmlspecialchars($email); ?></strong>.</p>
    <p class="small">Residencia: <?php echo htmlspecialchars($residency); ?></p>
  </div>

  <div class="card" style="margin-top:16px">
    <h3>Opciones de pago</h3>
    <?php
      // Totales
      $total = $qty * (float)$p['price'];
      $is_usd = (($p['currency'] ?? 'CRC') === 'USD');

      // Tipo de cambio
      $exrate = 1.0;
      if (!$is_usd && function_exists('get_exchange_rate')) {
        $exrate = (float)get_exchange_rate();
        if ($exrate <= 0) $exrate = 1.0;
      }

      // Monto para PayPal (USD siempre)
      $amount_usd = $is_usd ? $total : ($total / max(1.0, $exrate));
      $amount_usd = (float) number_format($amount_usd, 2, '.', '');

      // Precio visible
      $precio_txt = $is_usd
        ? ('$' . number_format($total, 2, '.', ','))
        : ('₡' . number_format($total, 0, ',', '.'));
    ?>

    <!-- SINPE Móvil (imagen estática + subir comprobante) -->
    <div style="display:grid; gap:10px; margin-bottom:16px;">
      <div class="small">SINPE Móvil (CRC) — Tel: <?php echo htmlspecialchars(SINPE_PHONE); ?></div>
      <img class="brand-img" src="assets/sinpe.jpg" alt="SINPE Móvil">
      <p class="note">Paga desde tu app y sube el comprobante:</p>

      <form class="form" method="post" action="upload_proof.php" enctype="multipart/form-data">
        <?php
          // Pasar IDs solo si existen; si no, upload_proof puede resolverlos por order_id.
          $hidden_sale = $sale_id ?: (int)($p['sale_id'] ?? 0);
          $hidden_aff  = $affiliate_id ?: (int)($p['sale_affiliate_id'] ?? ($p['affiliate_id'] ?? 0));
          if ($hidden_sale)  echo '<input type="hidden" name="sale_id" value="'.(int)$hidden_sale.'">';
          if ($hidden_aff)   echo '<input type="hidden" name="affiliate_id" value="'.(int)$hidden_aff.'">';
        ?>
        <input type="hidden" name="order_id" value="<?php echo (int)$order_id; ?>">
        <label>Foto del comprobante
          <input class="input" type="file" name="proof" accept="image/*,application/pdf" required>
        </label>
        <button class="btn">Subir comprobante</button>
      </form>
    </div>

    <!-- PayPal (USD) robusto -->
    <div style="display:grid; gap:8px; margin-top:4px;">
      <?php
        echo '<div class="small">PayPal (USD)'.($is_usd ? '' : ' — conversión automática a USD').'</div>';

        $paypal_email_ok = defined('PAYPAL_EMAIL') && filter_var(PAYPAL_EMAIL, FILTER_VALIDATE_EMAIL);
        if (!$paypal_email_ok) {
          echo '<div class="alert"><strong>Config:</strong> Define PAYPAL_EMAIL válido en includes/config.php</div>';
        } elseif ($amount_usd <= 0) {
          echo '<div class="alert"><strong>Atención:</strong> Monto PayPal $0.00. Revisa precio y tipo de cambio.</div>';
        } else {
          $item = urlencode(APP_NAME . ' - ' . $p['name']);
          $base = function_exists('app_base_url') ? app_base_url() :
                  (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

          $paypal_link = "https://www.paypal.com/cgi-bin/webscr?cmd=_xclick"
            . "&business=" . urlencode(PAYPAL_EMAIL)
            . "&item_name=" . $item
            . "&amount=" . number_format((float)$amount_usd, 2, '.', '')
            . "&currency_code=USD"
            . "&notify_url=" . urlencode($base . "/paypal_ipn.php")
            . "&return=" . urlencode($base . "/gracias.php")
            . "&cancel_return=" . urlencode($base . "/cancelado.php")
            . "&custom=" . urlencode((string)$order_id)
            . "&rm=2";

          echo '<a class="btn paypal-btn" href="'.htmlspecialchars($paypal_link).'">'
                . '<img src="assets/paypal.png" alt="PayPal">'
                . 'Pagar con PayPal'
              . '</a>';
          echo '<p class="small">Monto PayPal: $'.number_format((float)$amount_usd,2,'.',',').(!$is_usd ? ' (TC: '.htmlspecialchars((string)$exrate).')' : '').'</p>';
        }
      ?>
    </div>

    <p class="small" style="margin-top:12px">Total del pedido: <strong><?php echo $precio_txt; ?></strong></p>
    <a class="btn whats" href="https://wa.me/506<?php echo urlencode(SINPE_PHONE); ?>?text=<?php echo urlencode('Hola, consulta por pedido #' . $order_id . ' de ' . $p['name']); ?>" target="_blank">💬 Consultar por WhatsApp</a>
  </div>

<?php else: ?>
  <div class="alert"><strong>Error:</strong> <?php echo htmlspecialchars($error ?: 'No se pudo procesar la compra.'); ?></div>
<?php endif; ?>
</div>

<footer class="container small">© <?php echo date('Y'); ?> <?php echo APP_NAME; ?></footer>
</body>
</html>
