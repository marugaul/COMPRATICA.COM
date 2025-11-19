<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
$pdo = db();

$sql = "
SELECT
  p.*,
  s.id   AS sale_id,
  s.title AS sale_title,
  s.is_active AS sale_active,
  a.id   AS aff_id,
  a.email AS aff_email,
  (
    SELECT status
    FROM sale_fees f
    WHERE f.sale_id = s.id
    ORDER BY f.id DESC
    LIMIT 1
  ) AS fee_status
FROM products p
LEFT JOIN sales s      ON s.id = p.sale_id
LEFT JOIN affiliates a ON a.id = s.affiliate_id
ORDER BY p.created_at DESC
LIMIT 200;
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin - Productos (Extendido)</title>
<link rel="stylesheet" href="../assets/style.css">
</head><body>
<header class="header"><div class="logo">🛒 Admin — Productos (Extendido)</div>
<nav>
  <a class="btn" href="dashboard.php">Dashboard</a>
  <a class="btn" href="sales_admin.php">Espacios</a>
  <a class="btn" href="affiliates.php">Afiliados</a>
  <a class="btn" href="settings_fee.php">Costo por espacio</a>
  <a class="btn" href="email_marketing.php">📧 Email Marketing</a>
</nav></header>

<div class="container">
  <div class="card">
    <h3>Productos</h3>
    <table class="table">
      <tr>
        <th>ID</th><th>Nombre</th><th>Espacio</th><th>Afiliado</th>
        <th>Fee</th><th>Activo</th><th>Stock</th><th>Precio</th><th>Creado</th>
      </tr>
      <?php foreach($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td>
          <?= htmlspecialchars($r['sale_title'] ?: '—') ?>
          <?php if(!empty($r['sale_id'])): ?> (#<?= (int)$r['sale_id'] ?>)<?php endif; ?>
        </td>
        <td>
          <?= htmlspecialchars($r['aff_email'] ?: '—') ?>
          <?php if(!empty($r['aff_id'])): ?> (#<?= (int)$r['aff_id'] ?>)<?php endif; ?>
        </td>
        <td><?= htmlspecialchars($r['fee_status'] ?: '—') ?></td>
        <td><?= !empty($r['sale_active']) ? 'Sí' : 'No' ?></td>
        <td><?= (int)$r['stock'] ?></td>
        <td>
          <?= (($r['currency']??'CRC')==='USD'?'$':'₡') ?>
          <?= ($r['currency']??'CRC')==='USD'
              ? number_format((float)$r['price'],2,'.',',')
              : number_format((float)$r['price'],0,',','.') ?>
        </td>
        <td><?= htmlspecialchars($r['created_at'] ?: '') ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
</body></html>
