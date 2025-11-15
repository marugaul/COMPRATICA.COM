
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();
ini_set('display_errors', 0);
error_reporting(E_ALL);

/* -------------------- Helpers -------------------- */
function table_has_column(PDO $pdo, $table, $col) {
  try {
    $st = $pdo->query("PRAGMA table_info($table)");
    $cols = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($cols as $c) {
      if (isset($c['name']) && strtolower($c['name']) === strtolower($col)) return true;
    }
  } catch (Throwable $e) {}
  return false;
}
function fetch_sale_fee_crc(PDO $pdo, $default=2000.0) {
  // 1) Si existe columna sale_fee_crc en settings (modelo columnas fijas)
  if (table_has_column($pdo, 'settings', 'sale_fee_crc')) {
    try {
      // tomar la última fila por si hay más de una
      $st = $pdo->query("SELECT sale_fee_crc FROM settings WHERE sale_fee_crc IS NOT NULL ORDER BY id DESC LIMIT 1");
      $val = $st ? $st->fetchColumn() : null;
      $fee = (float)$val;
      if ($fee > 0) return $fee;
    } catch (Throwable $e) {}
  }
  // 2) Si existe esquema key/val (settings.key/settings.val)
  if (table_has_column($pdo, 'settings', 'key') && table_has_column($pdo, 'settings', 'val')) {
    try {
      $st = $pdo->prepare("SELECT val FROM settings WHERE key='sale_fee_crc' LIMIT 1");
      $st->execute();
      $val = $st->fetchColumn();
      $fee = (float)$val;
      if ($fee > 0) return $fee;
    } catch (Throwable $e) {}
  }
  // 3) Respaldo
  return (float)$default;
}
function fetch_exchange_rate(PDO $pdo, $default=540.00) {
  if (table_has_column($pdo, 'settings', 'exchange_rate')) {
    try {
      $st = $pdo->query("SELECT exchange_rate FROM settings WHERE exchange_rate IS NOT NULL ORDER BY id DESC LIMIT 1");
      $val = $st ? $st->fetchColumn() : null;
      $rate = (float)$val;
      if ($rate > 0) return $rate;
    } catch (Throwable $e) {}
  }
  // key/val fallback
  if (table_has_column($pdo, 'settings', 'key') && table_has_column($pdo, 'settings', 'val')) {
    try {
      $st = $pdo->prepare("SELECT val FROM settings WHERE key='exchange_rate' LIMIT 1");
      $st->execute();
      $val = $st->fetchColumn();
      $rate = (float)$val;
      if ($rate > 0) return $rate;
    } catch (Throwable $e) {}
  }
  return (float)$default;
}

/* -------------------- Cargar fee_id / sale -------------------- */
$fee_id = (int)($_GET['fee_id'] ?? 0);
if ($fee_id <= 0) {
  http_response_code(400);
  echo "Falta fee_id";
  exit;
}

$fee = $pdo->prepare("SELECT * FROM sale_fees WHERE id=?");
$fee->execute([$fee_id]);
$feeRow = $fee->fetch(PDO::FETCH_ASSOC);

if (!$feeRow) {
  http_response_code(404);
  echo "Fee no encontrado";
  exit;
}

$sale_id = (int)$feeRow['sale_id'];
$st = $pdo->prepare("SELECT s.*, a.email AS aff_email
                     FROM sales s
                     LEFT JOIN affiliates a ON a.id = s.affiliate_id
                     WHERE s.id=?");
$st->execute([$sale_id]);
$sale = $st->fetch(PDO::FETCH_ASSOC);
if (!$sale) {
  http_response_code(404);
  echo "Espacio no encontrado";
  exit;
}

/* -------------------- Monto del fee (CRC) -------------------- */
/*$fee_crc = 0.0;

// 1) Si el fee ya trae un monto específico, úsalo
if (isset($feeRow['amount_crc']) && is_numeric($feeRow['amount_crc'])) {
  $fee_crc = (float)$feeRow['amount_crc'];
}

// 2) Si no trae, leer desde settings (robusto)
if ($fee_crc <= 0) {
  $fee_crc = fetch_sale_fee_crc($pdo, 2000.0);
}

*/
/* -------------------- Monto del fee (CRC) -------------------- */
$fee_crc = 0.0;
/*
// 1️⃣ Si sale_fees.amount_crc tiene un valor, úsalo
if (isset($feeRow['amount_crc']) && is_numeric($feeRow['amount_crc'])) {
  $fee_crc = (float)$feeRow['amount_crc'];
}
*/
// 2️⃣ Si no tiene, leer desde settings.sale_fee_crc (tu caso real)
if ($fee_crc <= 0) {
  try {
    $stmt = $pdo->query("SELECT sale_fee_crc FROM settings WHERE id = 1");
    $fee_crc = (float)$stmt->fetchColumn();
  } catch (Throwable $e) {
    $fee_crc = 2000;
  }
}

// 3️⃣ Validación final
if ($fee_crc <= 0) $fee_crc = 3000.0;






// Tipo de cambio para PayPal (CRC->USD)
$exrate = fetch_exchange_rate($pdo, 540.00);
if ($exrate <= 0) $exrate = 540.00;
$fee_usd = number_format($fee_crc / $exrate, 2, '.', '');

/* -------------------- Enlaces de pago -------------------- */
$item = urlencode('Fee espacio #' . $sale_id . ' - ' . (string)($sale['title'] ?? ''));
$notify = urlencode(app_base_url() . '/../paypal_ipn.php'); // IPN en raíz pública
$return = urlencode(app_base_url() . '/sales.php?ok=1');
$cancel = urlencode(app_base_url() . '/sales.php?cancel=1');

$paypal_link = "https://www.paypal.com/cgi-bin/webscr?cmd=_xclick"
  . "&business=" . urlencode(PAYPAL_EMAIL)
  . "&item_name=" . $item
  . "&amount=" . $fee_usd
  . "&currency_code=USD"
  . "&notify_url=" . $notify
  . "&return=" . $return
  . "&cancel_return=" . $cancel
  . "&custom=" . urlencode('FEE:' . $fee_id)
  . "&rm=2"; // POST en return

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Pagar espacio #<?= (int)$sale_id ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/style.css">
  <style>
    .payrow{display:grid;gap:10px}
    .paybtn img{height:34px; width:auto; vertical-align:middle}
  </style>
</head>
<body>
<header class="header">
  <div class="logo">🛒 Afiliados — Pagar espacio</div>
  <nav>
    <a class="btn" href="sales.php">Volver</a>
  </nav>
</header>

<div class="container">
  <div class="card">
    <h3>Espacio #<?= (int)$sale_id ?></h3>
    <div class="small">
      Título: <strong><?= htmlspecialchars($sale['title'] ?? '—') ?></strong><br>
      Afiliado: <?= htmlspecialchars($sale['aff_email'] ?? '—') ?><br>
      Fee ID: #<?= (int)$fee_id ?> &nbsp;|&nbsp; Estado actual: <strong><?= htmlspecialchars($feeRow['status'] ?? 'Pendiente') ?></strong>
    </div>
  </div>

  <div class="card">
    <h3>Monto a pagar</h3>
    <p><strong>₡<?= number_format($fee_crc, 0, ',', '.') ?></strong> (aprox. USD $<?= htmlspecialchars($fee_usd) ?> con TC <?= number_format($exrate,2,'.','') ?>)</p>
    <div class="payrow">
      <!-- PayPal -->
      <a class="btn paybtn" href="<?= $paypal_link ?>">
        <img src="../assets/paypal.png" alt="Pay with PayPal"> Pagar con PayPal
      </a>

      <!-- SINPE (subida de comprobante) -->
      <div class="small">SINPE Móvil (CRC) — Tel: <?= htmlspecialchars(SINPE_PHONE) ?></div>
      <form class="form" method="post" action="upload_fee_proof.php" enctype="multipart/form-data">
        <input type="hidden" name="fee_id" value="<?= (int)$fee_id ?>">
        <label>Comprobante de pago (imagen)
          <input class="input" type="file" name="proof" accept="image/*" required>
        </label>
        <button class="btn">Subir comprobante y solicitar aprobación</button>
      </form>
      <p class="note">Una vez subido el comprobante, el administrador revisará y activará tu espacio.</p>
    </div>
  </div>
</div>
</body>
</html>
