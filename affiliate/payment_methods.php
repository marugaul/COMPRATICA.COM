<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';

aff_require_login();
$pdo = db();
$aff_id = (int)($_SESSION['aff_id'] ?? 0);

$msg = '';
$ok  = '';

// Leer o crear registro base
$row = $pdo->query("SELECT * FROM affiliate_payment_methods WHERE affiliate_id=$aff_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    $pdo->prepare("INSERT INTO affiliate_payment_methods (affiliate_id) VALUES (?)")->execute([$aff_id]);
    $row = $pdo->query("SELECT * FROM affiliate_payment_methods WHERE affiliate_id=$aff_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sinpe_phone  = trim($_POST['sinpe_phone'] ?? '');
        $paypal_email = trim($_POST['paypal_email'] ?? '');
        $active_sinpe = isset($_POST['active_sinpe']) ? 1 : 0;
        $active_paypal= isset($_POST['active_paypal']) ? 1 : 0;

        $st = $pdo->prepare("UPDATE affiliate_payment_methods
                             SET sinpe_phone=?, paypal_email=?, active_sinpe=?, active_paypal=?, updated_at=datetime('now','localtime')
                             WHERE affiliate_id=?");
        $st->execute([$sinpe_phone, $paypal_email, $active_sinpe, $active_paypal, $aff_id]);
        $ok = 'Métodos de pago actualizados correctamente.';
        $row = $pdo->query("SELECT * FROM affiliate_payment_methods WHERE affiliate_id=$aff_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $msg = 'Error: '.$e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>⚙️ Métodos de Pago del Afiliado</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../assets/style.css?v=20251014a">
</head>
<body>
<header class="header">
  <div class="logo">⚙️ Métodos de Pago</div>
  <nav><a href="sales.php" class="btn">Volver</a></nav>
</header>

<div class="container">
  <?php if ($msg): ?><div class="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($ok): ?><div class="success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

  <form class="form" method="post">
    <h3>Configura tus métodos de pago</h3>

    <label><input type="checkbox" name="active_sinpe" <?= $row['active_sinpe'] ? 'checked' : '' ?>> Activar SINPE Móvil</label>
    <input type="text" name="sinpe_phone" class="input" placeholder="Teléfono SINPE" value="<?= htmlspecialchars($row['sinpe_phone'] ?? '') ?>">

    <label><input type="checkbox" name="active_paypal" <?= $row['active_paypal'] ? 'checked' : '' ?>> Activar PayPal</label>
    <input type="email" name="paypal_email" class="input" placeholder="Correo PayPal" value="<?= htmlspecialchars($row['paypal_email'] ?? '') ?>">

    <button class="btn primary" type="submit">Guardar</button>
  </form>
</div>
</body>
</html>
