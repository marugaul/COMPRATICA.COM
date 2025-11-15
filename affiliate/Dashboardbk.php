<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';

aff_require_login();
$pdo = db();
$aff_id = (int)$_SESSION['aff_id'];

$stats = [
  'products' => $pdo->query("SELECT COUNT(1) FROM products WHERE affiliate_id={$aff_id}")->fetchColumn(),
  'sales'    => $pdo->query("SELECT COUNT(1) FROM sales WHERE affiliate_id={$aff_id}")->fetchColumn(),
  'orders'   => $pdo->query("SELECT COUNT(1) FROM orders WHERE affiliate_id={$aff_id}")->fetchColumn(),
];

// Forzar UTF-8 correcto antes de imprimir nada
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Afiliados — Panel</title>
  <link rel="stylesheet" href="../assets/style.css?v=22">
</head>

<body>
<header class="header">
  <div class="logo">🛒 Afiliado: <?= htmlspecialchars($_SESSION['aff_name'] ?? '') ?></div>
  <nav>
    <a class="btn" href="../index.php">Ver tienda</a>
    <a class="btn" href="sales.php">Mis espacios</a>
    <a class="btn" href="products.php">Mis productos</a>
    <a class="btn" href="orders.php">Mis pedidos</a>
    <a class="btn" href="sales_pay_options.php">💳 Configurar mis métodos de pago</a>
  </nav>
</header>

<div class="container">
  <div class="grid">
    <?php
      $cards = [
        ['label' => 'Productos', 'value' => (int)$stats['products']],
        ['label' => 'Espacios',  'value' => (int)$stats['sales']],
        ['label' => 'Pedidos',   'value' => (int)$stats['orders']],
      ];
      foreach ($cards as $c):
    ?>
      <div class="card">
        <h3><?= htmlspecialchars($c['label']) ?></h3>
        <div class="price"><?= $c['value'] ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

