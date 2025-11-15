<?php
// affiliate/sales_pay_options.php
// Permite al afiliado ver y editar sus métodos de pago: SINPE Móvil y PayPal (independientes del admin)

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';

aff_require_login();
$pdo     = db();
$aff_id  = (int)($_SESSION['aff_id'] ?? 0);
$msg     = '';
$ok_note = '';

// Crear registro base si no existe
$row = $pdo->query("SELECT * FROM affiliate_payment_methods WHERE affiliate_id=$aff_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    $pdo->prepare("INSERT INTO affiliate_payment_methods (affiliate_id) VALUES (?)")->execute([$aff_id]);
    $row = $pdo->query("SELECT * FROM affiliate_payment_methods WHERE affiliate_id=$aff_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sinpe_phone   = trim($_POST['sinpe_phone'] ?? '');
        $paypal_email  = trim($_POST['paypal_email'] ?? '');
        $active_sinpe  = isset($_POST['active_sinpe']) ? 1 : 0;
        $active_paypal = isset($_POST['active_paypal']) ? 1 : 0;

        $sql = "UPDATE affiliate_payment_methods
                SET sinpe_phone=?, paypal_email=?, active_sinpe=?, active_paypal=?, updated_at=datetime('now','localtime')
                WHERE affiliate_id=?";
        $pdo->prepare($sql)->execute([$sinpe_phone, $paypal_email, $active_sinpe, $active_paypal, $aff_id]);

        $ok_note = "Métodos de pago actualizados correctamente.";
        $row = $pdo->query("SELECT * FROM affiliate_payment_methods WHERE affiliate_id=$aff_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $msg = 'Error: ' . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>💳 Configurar métodos de pago</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/style.css?v=20251014a">
  <style>
    .pay-card {border:1px solid #ccc;border-radius:10px;padding:15px;margin-bottom:15px;background:#fafafa;}
    .pay-label {font-weight:bold;margin-bottom:6px;display:block;}
  </style>
</head>
<body>
<header class="header">
  <div class="logo">💳 Configurar métodos de pago</div>
  <nav><a href="dashboard.php" class="btn">Volver</a></nav>
</header>

<div class="container">
  <?php if ($msg): ?><div class="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($ok_note): ?><div class="success"><?= htmlspecialchars($ok_note) ?></div><?php endif; ?>

  <form method="post" class="form">
    <div class="pay-card">
      <label class="pay-label"><input type="checkbox" name="active_sinpe" <?= $row['active_sinpe'] ? 'checked' : '' ?>> Activar SINPE Móvil</label>
      <input type="text" class="input" name="sinpe_phone" placeholder="Teléfono SINPE (Ej: 8888-8888)" value="<?= htmlspecialchars($row['sinpe_phone'] ?? '') ?>">
      <small>El teléfono donde recibirás transferencias SINPE Móvil.</small>
    </div>

    <div class="pay-card">
      <label class="pay-label"><input type="checkbox" name="active_paypal" <?= $row['active_paypal'] ? 'checked' : '' ?>> Activar PayPal</label>
      <input type="email" class="input" name="paypal_email" placeholder="Correo PayPal (Ej: micorreo@paypal.com)" value="<?= htmlspecialchars($row['paypal_email'] ?? '') ?>">
      <small>Correo de tu cuenta PayPal donde recibirás pagos. Los clientes pueden pagar con su cuenta o tarjeta sin registrarse.</small>
    </div>

    <button type="submit" class="btn primary">Guardar métodos de pago</button>
  </form>

  <hr style="margin:30px 0">

  <div class="pay-card">
    <h4>🔍 Vista previa de tus métodos activos</h4>
    <ul>
      <?php if ($row['active_sinpe'] && !empty($row['sinpe_phone'])): ?>
        <li>📱 SINPE Móvil activo — Teléfono: <strong><?= htmlspecialchars($row['sinpe_phone']) ?></strong></li>
      <?php endif; ?>
      <?php if ($row['active_paypal'] && !empty($row['paypal_email'])): ?>
        <li>💰 PayPal activo — Correo: <strong><?= htmlspecialchars($row['paypal_email']) ?></strong></li>
      <?php endif; ?>
      <?php if ((!$row['active_sinpe'] || empty($row['sinpe_phone'])) && (!$row['active_paypal'] || empty($row['paypal_email']))): ?>
        <li><em>No tienes métodos de pago activos actualmente.</em></li>
      <?php endif; ?>
    </ul>
  </div>
</div>
</body>
</html>
