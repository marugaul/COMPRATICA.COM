<?php
require_once __DIR__ . '/includes/config.php';
$order_id = (int)($_GET['order_id'] ?? 0);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8">
<title>Gracias por tu compra</title>
<link rel="stylesheet" href="assets/style.css">
</head><body>
<div class="container" style="max-width:600px;margin:40px auto">
  <h1>¡Gracias por tu compra!</h1>
  <p>Tu número de pedido es <strong>#<?php echo $order_id ?: 'N/D'; ?></strong>.</p>
  <p>Te enviaremos un correo con la confirmación. Si pagaste por SINPE, revisaremos tu comprobante.</p>
  <p><a class="btn" href="index.php">Volver a la tienda</a></p>
</div>
</body></html>