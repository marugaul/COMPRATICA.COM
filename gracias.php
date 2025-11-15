<?php require_once __DIR__ . '/includes/config.php'; ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>¡Gracias! - <?php echo APP_NAME; ?></title><link rel="stylesheet" href="assets/style.css"></head>
<body><header class="header"><div class="logo">🛒 <?php echo APP_NAME; ?></div><nav><a class="btn" href="index.php">Volver a la tienda</a></nav></header>
<div class="container"><div class="success"><h2>¡Gracias!</h2><p>Si pagaste por PayPal, tu pago será validado automáticamente. Si pagaste por SINPE, revisaremos tu comprobante.</p></div></div>
<footer class="container small">© <?php echo date('Y'); ?> <?php echo APP_NAME; ?></footer></body></html>
