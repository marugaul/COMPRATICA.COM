<?php
// affiliate/sales_payment_view.php
// Muestra SOLO los métodos de pago activos del afiliado propietario de un producto o espacio.
// No modifica datos; es una vista autónoma.

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';

// Asegurar UTF-8 correcto
if (!headers_sent()) {
  header('Content-Type: text/html; charset=UTF-8');
}
ini_set('default_charset', 'UTF-8');

$pdo = db();
aff_require_login(); // Requiere login de afiliado para ver esta pantalla (ajusta si querés pública)

$msg = '';
$item = null;
$item_type = null; // 'product' | 'sale'
$owner_id = 0;
$title = '';
$amount_usd = null;
$amount_crc = null;

// Helpers suaves para obtener monto
function prefer_float($v) {
  return is_numeric($v) ? (float)$v : null;
}
function get_exrate() {
  if (function_exists('get_exchange_rate')) {
    $ex = (float)get_exchange_rate();
    return $ex > 0 ? $ex : 1.0;
  }
  return 1.0;
}

// 1) Resolver parámetros (producto o espacio)
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$sale_id    = isset($_GET['sale_id'])    ? (int)$_GET['sale_id']    : 0;

try {
  if ($product_id > 0) {
    // Cargar producto
    $st = $pdo->prepare("SELECT p.*, a.name AS aff_name
                         FROM products p
                         JOIN affiliates a ON a.id = p.affiliate_id
                         WHERE p.id = ? LIMIT 1");
    $st->execute([$product_id]);
    $item = $st->fetch(PDO::FETCH_ASSOC);
    if (!$item) throw new RuntimeException('Producto no encontrado.');
    $item_type = 'product';
    $owner_id  = (int)$item['affiliate_id'];
    // Título
    $title = (string)($item['title'] ?? $item['name'] ?? ('Producto #'.$product_id));
    // Montos: intentos comunes de columnas
    $amount_usd = prefer_float($item['price_usd'] ?? $item['precio_usd'] ?? null);
    $amount_crc = prefer_float($item['price_crc'] ?? $item['precio_crc'] ?? $item['price'] ?? $item['precio'] ?? null);
    if ($amount_usd === null && $amount_crc !== null) {
      $ex = get_exrate();
      $amount_usd = $amount_crc / ($ex > 0 ? $ex : 1);
    }
  } elseif ($sale_id > 0) {
    // Cargar espacio (sale)
    $st = $pdo->prepare("SELECT s.*, a.name AS aff_name
                         FROM sales s
                         JOIN affiliates a ON a.id = s.affiliate_id
                         WHERE s.id = ? LIMIT 1");
    $st->execute([$sale_id]);
    $item = $st->fetch(PDO::FETCH_ASSOC);
    if (!$item) throw new RuntimeException('Espacio no encontrado.');
    $item_type = 'sale';
    $owner_id  = (int)$item['affiliate_id'];
    // Título
    $title = (string)($item['title'] ?? ('Espacio #'.$sale_id));
    // Montos (si el espacio tiene precio propio; si no, se mostrará PayPal sin monto)
    $amount_usd = prefer_float($item['price_usd'] ?? $item['precio_usd'] ?? null);
    $amount_crc = prefer_float($item['price_crc'] ?? $item['precio_crc'] ?? $item['price'] ?? $item['precio'] ?? null);
    if ($amount_usd === null && $amount_crc !== null) {
      $ex = get_exrate();
      $amount_usd = $amount_crc / ($ex > 0 ? $ex : 1);
    }
  } else {
    throw new RuntimeException('Solicitud inválida: faltó product_id o sale_id.');
  }
} catch (Throwable $e) {
  $msg = 'Error: ' . $e->getMessage();
}

// 2) Cargar métodos de pago del afiliado
$pm = null;
if ($owner_id > 0) {
  $pm = $pdo->query("SELECT * FROM affiliate_payment_methods WHERE affiliate_id = ".(int)$owner_id." LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

// 3) Construir PayPal link si procede
$paypal_link = null;
if ($pm && !empty($pm['paypal_email']) && filter_var($pm['paypal_email'], FILTER_VALIDATE_EMAIL) && !empty($pm['active_paypal'])) {
  $item_label = urlencode(APP_NAME . ' - ' . $title);
  $base = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
  // Si no tenemos monto USD, PayPal requiere amount; en ese caso ocultamos botón para evitar errores.
  if ($amount_usd !== null) {
    $paypal_link = "https://www.paypal.com/cgi-bin/webscr?cmd=_xclick"
                 . "&business=" . urlencode($pm['paypal_email'])
                 . "&item_name=" . $item_label
                 . "&amount=" . number_format($amount_usd, 2, '.', '')
                 . "&currency_code=USD"
                 . "&no_note=1&no_shipping=1&rm=2"
                 . "&return=" . urlencode($base . "/gracias.php")
                 . "&cancel_return=" . urlencode($base . "/cancelado.php");
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>💳 Pagos disponibles — <?= htmlspecialchars($title ?: ($item_type==='product'?'Producto':'Espacio')) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/style.css?v=20251014b">
  <style>
    .pay-card{border:1px solid #ddd;border-radius:12px;padding:16px;margin-bottom:16px;background:#fafafa}
    .brand-img{width:220px;max-width:100%;height:auto;border:1px solid #eee;border-radius:10px}
    .muted{color:#666;font-size:.9em}
  </style>
</head>
<body>
<header class="header">
  <div class="logo">💳 Métodos de pago disponibles</div>
  <nav>
    <a class="btn" href="sales.php">Mis espacios</a>
    <a class="btn" href="products.php">Mis productos</a>
    <a class="btn" href="sales_pay_options.php">Configurar mis métodos</a>
  </nav>
</header>

<div class="container">
  <?php if ($msg): ?><div class="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

  <?php if ($item): ?>
    <div class="card">
      <h3><?= $item_type==='product' ? 'Producto' : 'Espacio' ?>: <?= htmlspecialchars($title) ?></h3>
      <?php if ($amount_crc !== null || $amount_usd !== null): ?>
        <div class="muted">
          <?php if ($amount_crc !== null): ?>CRC: <strong>₡<?= number_format($amount_crc, 0, ',', '.') ?></strong><?php endif; ?>
          <?php if ($amount_usd !== null): ?><?= $amount_crc!==null?' | ':'' ?>USD: <strong>$<?= number_format($amount_usd, 2, '.', ',') ?></strong><?php endif; ?>
        </div>
      <?php else: ?>
        <div class="muted">Sin monto definido (PayPal requiere monto en USD para mostrar botón).</div>
      <?php endif; ?>
    </div>

    <div class="pay-card">
      <h4>Métodos habilitados por el afiliado</h4>

      <?php
        $shown = 0;
        if ($pm && !empty($pm['active_sinpe']) && !empty($pm['sinpe_phone'])):
          $shown++;
      ?>
        <div style="margin-bottom:18px;">
          <strong>📱 SINPE Móvil</strong><br>
          Teléfono: <strong><?= htmlspecialchars($pm['sinpe_phone']) ?></strong><br>
          <img class="brand-img" src="../assets/sinpe.jpg" alt="SINPE Móvil"><br>
          <small class="muted">Enviá el monto al número indicado. Conservá tu comprobante.</small>
        </div>
      <?php endif; ?>

      <?php if ($paypal_link): $shown++; ?>
        <div>
          <strong>💰 PayPal</strong><br>
          <div class="small muted">Pago con cuenta PayPal o con tarjeta (sin necesidad de cuenta).</div>
          <a class="btn" href="<?= htmlspecialchars($paypal_link) ?>">
            <img src="../assets/paypal.png" alt="PayPal" style="height:18px;vertical-align:middle"> Pagar con PayPal
          </a>
        </div>
      <?php endif; ?>

      <?php if ($shown === 0): ?>
        <div class="alert">⚠️ Este afiliado no tiene métodos de pago activos o faltan datos.</div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
