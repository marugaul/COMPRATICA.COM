<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/auth.php';
$pdo=db(); $msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){ $fee_crc=(int)($_POST['SALE_FEE_CRC']??2000); set_setting('SALE_FEE_CRC',$fee_crc); $msg='Configuración guardada'; }
$fee=(int)get_setting('SALE_FEE_CRC',2000);
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin — Settings</title><link rel="stylesheet" href="../assets/style.css?v=22"></head>
<body><header class="header"><div class="logo">🛒 Admin — Configuración</div><nav><a class="btn" href="dashboard.php">Dashboard</a><a class="btn" href="affiliates.php">Afiliados</a></nav></header>
<div class="container"><?php if($msg): ?><div class="success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="card"><h3>Costos</h3><form class="form" method="post">
<label>Costo por crear espacio (CRC) <input class="input" type="number" name="SALE_FEE_CRC" value="<?= (int)$fee ?>"></label>
<button class="btn primary">Guardar</button></form></div></div></body></html>