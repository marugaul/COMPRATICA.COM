<?php
// affiliate/sales_pay.php — Pago de fee de espacio con notificación por correo (UTF-8 sin BOM)
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';

aff_require_login();
$pdo     = db();
$aff_id  = (int)($_SESSION['aff_id'] ?? 0);
$msg     = '';
$ok_note = '';

/** Helpers */
if (!function_exists('now_iso')) {
    function now_iso() { return date('Y-m-d H:i:s'); }
}

/**
 * Lee SIEMPRE el monto desde settings.sale_fee_crc (fila id=1),
 * sin defaults y sin validar montos previos.
 */
function current_sale_fee_crc(PDO $pdo): float {
  $v = $pdo->query("SELECT sale_fee_crc FROM settings WHERE id=1")->fetchColumn();
  return (float)$v; // SIEMPRE este valor
}

function load_fee_by_id(PDO $pdo, int $fee_id) {
  $sql = "SELECT f.*, a.email AS aff_email, a.name AS aff_name, s.title AS sale_title
          FROM sale_fees f
          JOIN sales s ON s.id = f.sale_id
          JOIN affiliates a ON a.id = f.affiliate_id
          WHERE f.id = ? LIMIT 1";
  $st = $pdo->prepare($sql);
  $st->execute([$fee_id]);
  return $st->fetch(PDO::FETCH_ASSOC);
}

function load_or_create_fee_for_sale(PDO $pdo, int $sale_id, int $aff_id) {
  $q = $pdo->prepare("SELECT f.*, a.email AS aff_email, a.name AS aff_name, s.title AS sale_title
                      FROM sale_fees f
                      JOIN sales s ON s.id = f.sale_id
                      JOIN affiliates a ON a.id = f.affiliate_id
                      WHERE f.sale_id = ? AND f.affiliate_id = ?
                      ORDER BY f.id DESC LIMIT 1");
  $q->execute([$sale_id, $aff_id]);
  $row = $q->fetch(PDO::FETCH_ASSOC);
  if ($row) return $row;

  // Crear fee si no existía — SIEMPRE usar variable global sale_fee_crc
  $fee_crc = current_sale_fee_crc($pdo);
  $ex      = (float)(function_exists('get_exchange_rate') ? get_exchange_rate() : 1);
  if ($ex <= 0) $ex = 1;
  $usd = $fee_crc / $ex;

  $ins = $pdo->prepare("INSERT INTO sale_fees
    (affiliate_id, sale_id, amount_crc, amount_usd, exrate_used, status, created_at, updated_at)
    VALUES (?,?,?,?,?,'Pendiente',?,?)");
  $now = now_iso();
  $ins->execute([$aff_id, $sale_id, $fee_crc, $usd, $ex, $now, $now]);

  $id = (int)$pdo->lastInsertId();
  return load_fee_by_id($pdo, $id);
}

/** Entrada por GET: fee_id o sale_id */
$fee_id  = (int)($_GET['fee_id']  ?? 0);
$sale_id = (int)($_GET['sale_id'] ?? 0);
$fee = null;

try {
  if ($fee_id > 0) {
    $fee = load_fee_by_id($pdo, $fee_id);
    if (!$fee || (int)$fee['affiliate_id'] !== $aff_id) {
      throw new RuntimeException('Fee no encontrado o no te pertenece.');
    }
  } elseif ($sale_id > 0) {
    // Crear o cargar fee para el sale (valida que pertenezca al afiliado)
    $chk = $pdo->prepare("SELECT id FROM sales WHERE id=? AND affiliate_id=? LIMIT 1");
    $chk->execute([$sale_id, $aff_id]);
    if (!$chk->fetchColumn()) throw new RuntimeException('El espacio no existe o no te pertenece.');
    $fee = load_or_create_fee_for_sale($pdo, $sale_id, $aff_id);
  } else {
    throw new RuntimeException('Solicitud inválida (sin fee_id ni sale_id).');
  }
} catch (Throwable $e) {
  $msg = 'Error: ' . $e->getMessage();
}

/** POST: subida de comprobante SINPE -> notificar */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_proof']) && empty($msg)) {
  try {
    // Re-cargar fee por seguridad
    $fee = load_fee_by_id($pdo, (int)$fee['id']);
    if (!$fee || (int)$fee['affiliate_id'] !== $aff_id) {
      throw new RuntimeException('No se pudo validar el fee para cargar comprobante.');
    }

    if (empty($_FILES['proof']['name']) || !is_uploaded_file($_FILES['proof']['tmp_name'])) {
      throw new RuntimeException('Adjunta el comprobante de pago.');
    }

    @mkdir(__DIR__ . '/../uploads/payments', 0775, true);
    $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif','pdf'])) $ext = 'jpg';

    $fname = 'fee_' . (int)$fee['id'] . '_' . uniqid() . '.' . $ext;
    $dest  = __DIR__ . '/../uploads/payments/' . $fname;
    if (!move_uploaded_file($_FILES['proof']['tmp_name'], $dest)) {
      throw new RuntimeException('No se pudo guardar el comprobante.');
    }

    // Guarda referencia del comprobante y deja el fee en Pendiente
    // (Columna proof_image en sale_fees)
    $upd = $pdo->prepare("UPDATE sale_fees SET status='Pendiente', proof_image=?, updated_at=? WHERE id=?");
    $upd->execute([$fname, now_iso(), (int)$fee['id']]);

    // ---- Enviar correos ----
    $aff_email = (string)($fee['aff_email'] ?? '');
    $aff_name  = (string)($fee['aff_name']  ?? '');
    $sale_tit  = (string)($fee['sale_title'] ?? ('Espacio #' . (int)$fee['sale_id']));

    // Monto SIEMPRE desde settings
    $fee_crc_global = current_sale_fee_crc($pdo);
    $monto_str = '₡'.number_format($fee_crc_global, 0, ',', '.');

    $adminSub  = "[Afiliados] Comprobante recibido: {$sale_tit}";
    $adminBody = "Se recibió un comprobante de pago de <strong>".$monto_str."</strong> "
               . "para el fee del espacio <strong>".htmlspecialchars($sale_tit)."</strong>.<br>"
               . "Afiliado: <strong>".htmlspecialchars($aff_name)."</strong> (".htmlspecialchars($aff_email).")<br>"
               . "Fee ID: <strong>".(int)$fee['id']."</strong><br>"
               . "Queda <strong>Pendiente</strong> de validación y activación por el administrador.";

    $affSub   = "Recibimos tu pago del espacio — Pendiente de validación";
    $affBody  = "Hola <strong>".htmlspecialchars($aff_name)."</strong>,<br><br>"
              . "Recibimos el comprobante de tu pago (<strong>{$monto_str}</strong>) para el espacio "
              . "<strong>".htmlspecialchars($sale_tit)."</strong>.<br>"
              . "Tu activación quedará <strong>pendiente de validación</strong> por el administrador. "
              . "Te notificaremos cuando se active.<br><br>"
              . APP_NAME;

    // Destinatarios explícitos y logs para validar
    $admin_to  = (defined('ADMIN_EMAIL') && ADMIN_EMAIL) ? ADMIN_EMAIL : '';
    $client_to = $aff_email;

    error_log("[sales_pay] will send admin_to={$admin_to} client_to={$client_to}");

    // Envío admin
    if ($admin_to) {
      try {
        $okAdmin = send_mail($admin_to, $adminSub, $adminBody);
        error_log("[sales_pay] admin mail result=" . ($okAdmin ? "OK" : "FAIL"));
      } catch (Throwable $e) {
        error_log("[sales_pay] admin mail EX: ".$e->getMessage());
      }
    }

    // Envío cliente (solo si es email válido)
    if ($client_to && filter_var($client_to, FILTER_VALIDATE_EMAIL)) {
      try {
        $okClient = send_mail($client_to, $affSub, $affBody);
        error_log("[sales_pay] client mail result=" . ($okClient ? "OK" : "FAIL"));
      } catch (Throwable $e) {
        error_log("[sales_pay] client mail EX: ".$e->getMessage());
      }
    } else {
      error_log("[sales_pay] client email missing/invalid, not sending");
    }

    $ok_note = "Comprobante subido. Te enviamos un correo; el admin validará y activará tu espacio.";
    // refrescamos datos
    $fee = load_fee_by_id($pdo, (int)$fee['id']);

  } catch (Throwable $e) {
    $msg = 'Error al subir comprobante: ' . $e->getMessage();
  }
}

/** Para mostrar montos y construir pagos — SIEMPRE desde settings */
$fee_crc = current_sale_fee_crc($pdo);
$ex      = (float)(function_exists('get_exchange_rate') ? get_exchange_rate() : 1);
if ($ex <= 0) $ex = 1;
$usd = $fee_crc / $ex;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>🛒 Afiliados — Pagar espacio</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/style.css?v=20251012b">
  <style>.brand-img{width:220px;max-width:100%;height:auto;border:1px solid #eee;border-radius:10px}</style>
</head>
<body>
<header class="header">
  <div class="logo">🛒 Afiliados — Pagar espacio</div>
  <nav><a class="btn" href="sales.php">Volver</a></nav>
</header>

<div class="container">
  <?php if ($msg): ?><div class="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($ok_note): ?><div class="success"><?= htmlspecialchars($ok_note) ?></div><?php endif; ?>

  <?php if ($fee): ?>
    <div class="card">
      <h3>Espacio #<?= (int)$fee['sale_id'] ?></h3>
      <div class="small">Afiliado: <?= htmlspecialchars($fee['aff_email'] ?? '') ?></div>
      <div class="small">Fee ID: #<?= (int)$fee['id'] ?>  |  Estado actual: <strong><?= htmlspecialchars($fee['status'] ?? 'Pendiente') ?></strong></div>

      <h4>Monto a pagar</h4>
      <p>₡<?= number_format($fee_crc, 0, ',', '.') ?> (aprox. USD $<?= number_format($usd, 2, '.', ',') ?> con TC <?= number_format($ex, 2, '.', ',') ?>)</p>

      <div style="margin-top:12px;display:grid;gap:10px">
        <!-- SINPE Móvil -->
        <div>
          <div class="small">SINPE Móvil (CRC) — Tel: <?= htmlspecialchars(SINPE_PHONE) ?></div>
          <img class="brand-img" src="../assets/sinpe.jpg" alt="SINPE Móvil">
          <p class="note">Paga desde tu app y sube el comprobante (queda pendiente de validación por el admin):</p>
          <form class="form" method="post" enctype="multipart/form-data">
            <label>Comprobante (imagen o PDF)
              <input class="input" type="file" name="proof" accept="image/*,application/pdf" required>
            </label>
            <button class="btn primary" name="upload_proof" value="1">Subir comprobante</button>
          </form>
          <?php if (!empty($fee['proof_image'])): ?>
            <div class="small">Comprobante subido:
              <a href="../uploads/payments/<?= htmlspecialchars($fee['proof_image']) ?>" target="_blank">
                <?= htmlspecialchars($fee['proof_image']) ?>
              </a>
            </div>
          <?php endif; ?>
        </div>

        <!-- PayPal -->
        <div>
          <div class="small">PayPal (USD) — activación automática al confirmar (IPN)</div>
          <?php
            $paypal_ok = defined('PAYPAL_EMAIL') && filter_var(PAYPAL_EMAIL, FILTER_VALIDATE_EMAIL);
            if ($paypal_ok) {
              $item  = urlencode(APP_NAME.' - Activación espacio #'.$fee['sale_id']);
              $base  = (function_exists('app_base_url') ? app_base_url() : (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST']));
              $link  = "https://www.paypal.com/cgi-bin/webscr?cmd=_xclick"
                     . "&business=" . urlencode(PAYPAL_EMAIL)
                     . "&item_name=" . $item
                     . "&amount=" . number_format($usd, 2, '.', '')   // SIEMPRE desde settings
                     . "&currency_code=USD"
                     . "&notify_url=" . urlencode($base . "/paypal_ipn.php?type=fee&fee_id=".(int)$fee['id'])
                     . "&return=" . urlencode($base . "/gracias.php")
                     . "&cancel_return=" . urlencode($base . "/cancelado.php")
                     . "&custom=" . urlencode("FEE:" . (int)$fee['id'])
                     . "&rm=2";
              echo '<a class="btn" href="'.htmlspecialchars($link).'"><img src="../assets/paypal.png" alt="PayPal" style="height:18px;vertical-align:middle"> Pagar con PayPal</a>';
            } else {
              echo '<div class="alert">Configura PAYPAL_EMAIL en includes/config.php</div>';
            }
          ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

</body>
</html>
