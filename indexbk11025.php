<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';
$pdo = db();
$products = $pdo->query("SELECT * FROM products WHERE active=1 ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="assets/style.css?v=11">
</head>
<body>
<header class="header">
  <div class="logo">🛒 <?php echo APP_NAME; ?></div>
  <nav><a class="btn" href="admin/login.php">Backoffice</a></nav>
</header>

<div class="container">
  <div class="grid">
    <?php foreach($products as $p): ?>
      <div class="card">
        <div class="imgbox">
          <img src="uploads/<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
        </div>
        <div class="price">
          <?php if(($p['currency'] ?? 'CRC') === 'USD'): ?>
            $<?= number_format((float)$p['price'], 2, '.', ',') ?>
          <?php else: ?>
            ₡<?= number_format((float)$p['price'], 0, ',', '.') ?>
          <?php endif; ?>
        </div>
        <div><?= htmlspecialchars($p['name']) ?></div>
        <?php if ((int)$p['stock'] <= 0): ?>
          <div class="small" style="color:#9ca3af;font-weight:600">Agotado</div>
        <?php else: ?>
          <a class="btn primary" href="buy.php?id=<?= (int)$p['id'] ?>">Comprar</a>
        <?php endif; ?>
        <a class="btn whats" href="https://wa.me/506<?= SINPE_PHONE ?>?text=Hola,%20quiero%20más%20información%20sobre%20<?= urlencode($p['name']) ?>" target="_blank">💬 Consultar por WhatsApp</a>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<footer class="container small">© <?php echo date('Y'); ?> <?php echo APP_NAME; ?></footer>
</body>
</html>