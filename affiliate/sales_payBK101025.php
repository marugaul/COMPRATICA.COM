<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';

aff_require_login();
$pdo = db();
$aff_id  = (int)$_SESSION['aff_id'];

$fee_id  = (int)($_GET['fee_id']  ?? 0);
$sale_id = (int)($_GET['sale_id'] ?? 0);
$msg = '';

// 1) Si llega solo sale_id, localizar fee del afiliado
if (!$fee_id && $sale_id) {
  $st = $pdo->prepare("SELECT id FROM sale_fees WHERE sale_id=? AND affiliate_id=? ORDER BY id DESC LIMIT 1");
  $st->execute([$sale_id, $aff_id]);
  $fee_id = (int)$st->fetchColumn();

  // 2) Si no existe fee, crearlo en el momento
  if (!$fee_id) {
    $s = $pdo->prepare("SELECT id, title FROM sales WHERE id=? AND affiliate_id=? LIMIT 1");
    $s->execute([$sale_id, $aff_id]);
    $sale = $s->fetch(PDO::FETCH_ASSOC);

    if ($sale) {
      $fee_crc = (float)get_setting('SALE_FEE_CRC', 2000);
      $ex = (float)get_exchange_rate(); if ($ex <= 0) $ex = 1;
      $amount_usd = $fee_crc / $ex;

      $pdo->prepare("INSERT INTO sale_fees(affiliate_id,sale_id,amount_crc,amount_usd,exrate_used,status,created_at,updated_at)
                     VALUES(?,?,?,?,?,'Pendiente',datetime('now'),datetime('now'))")
          ->execute([$aff_id,$sale_id,$fee_crc,$amount_usd,$ex]);

      $fee_id = (int)$pdo->lastInsertId();
    }
  }
}

// 3) Cargar el fee definitivo
$fee = null;
if ($fee_id) {
  $st = $pdo->prepare("SELECT f.*, s.title
                       FROM sale_fees f
                       JOIN sales s ON s.id = f.sale_id
                       WHERE f.id=? AND f.affiliate_id=?");
  $st->execute([$fee_id, $aff_id]);
  $fee = $st->fetch(PDO::FETCH_ASSOC);
}

if (!$fee) {
  http_response_code(404);
  $msg = 'Fee no encontrado';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Afiliados — Pago de activación</title>
  <link rel="stylesheet" href="../assets/style.css?v=22">
</head>
<body>
<header class="header">
  <div class="logo">🛒 Pago de activación</div>
  <nav><a class="btn" href="sales.php">Volver</a></nav>
</header>

<div class="container">
<?php if (!empty($msg)): ?>
  <div class="alert"><strong>Error:</strong> <?= htmlspecialchars($msg) ?></div>
<?php else: ?>
  <div class="card">
    <h3><?= htmlspecialchars($fee['title']) ?></h3>
    <p class="small">Estado: <strong><?= htmlspecialchars($fee['status']) ?></strong></p>
    <p>Monto: ₡<?= number_format($fee['amount_crc'],0,',','.') ?> (aprox. $<?= number_format($fee['amount_usd'],2,'.',',') ?>)</p>

    <h4>Opción 1: SINPE Móvil</h4>
    <div class="small">Paga al número <strong><?= htmlspecialchars(SINPE_PHONE) ?></strong> y sube el comprobante:</div>
    <form class="form" method="post" action="upload_fee_proof.php" enctype="multipart/form-data">
      <input type="hidden" name="fee_id" value="<?= (int)$fee['id'] ?>">
      <label>Comprobante <input class="input" type="file" name="proof" accept="image/*,application/pdf" required></label>
      <button class="btn">Subir comprobante</button>
    </form>

    <h4 style="margin-top:14px">Opción 2: PayPal</h4>
    <?php
      $item = urlencode('Activación de espacio - ' . $fee['title']);
      $paypal_link = "https://www.paypal.com/cgi-bin/webscr?cmd=_xclick"
        . "&business=" . urlencode(PAYPAL_EMAIL)
        . "&item_name=" . $item
        . "&amount=" . number_format((float)$fee['amount_usd'], 2, '.', '')
        . "&currency_code=USD"
        . "&notify_url=" . urlencode(app_base_url() . "/paypal_ipn.php")
        . "&return=" . urlencode(app_base_url() . "/affiliate/sales_fee_return.php?fee_id=".(int)$fee['id'])
        . "&cancel_return=" . urlencode(app_base_url() . "/affiliate/sales_fee_cancel.php?fee_id=".(int)$fee['id'])
        . "&custom=" . urlencode('FEE#'.$fee['id'])
        . "&rm=2";
    ?>
    <a class="btn primary" href="<?= $paypal_link ?>">Pagar con PayPal</a>
  </div>
<?php endif; ?>
</div>

<footer class="container small">© <?= date('Y') ?> <?php echo APP_NAME; ?></footer>
</body>
</html>