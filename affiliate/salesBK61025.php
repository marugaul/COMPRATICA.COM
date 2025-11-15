<?php
require_once __DIR__ . '/../includes/db.php'; require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/settings.php'; require_once __DIR__ . '/../includes/affiliate_auth.php';
aff_require_login(); $pdo=db(); $aff_id=(int)$_SESSION['aff_id']; $msg='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create'])){
  $title=trim($_POST['title']??''); $start=trim($_POST['start_at']??''); $end=trim($_POST['end_at']??'');
  $img=null; if(!empty($_FILES['cover']['name'])){ @mkdir(__DIR__.'/../uploads/affiliates',0775,true); $ext=pathinfo($_FILES['cover']['name'],PATHINFO_EXTENSION); $img='cover_'.uniqid().'.'.$ext; move_uploaded_file($_FILES['cover']['tmp_name'], __DIR__.'/../uploads/affiliates/'.$img); }
  $pdo->prepare("INSERT INTO sales(affiliate_id,title,cover_image,start_at,end_at,is_active,created_at,updated_at) VALUES(?,?,?,?,?,0,datetime('now'),datetime('now'))")->execute([$aff_id,$title,$img,$start,$end]);
  $sale_id = $pdo->lastInsertId();
  $fee_crc = (float)get_setting('SALE_FEE_CRC', 2000); $ex = (float)get_exchange_rate(); if($ex<=0)$ex=1; $amount_usd=$fee_crc/$ex;
  $pdo->prepare("INSERT INTO sale_fees(affiliate_id,sale_id,amount_crc,amount_usd,exrate_used,status,created_at,updated_at) VALUES(?,?,?,?,?,'Pendiente',datetime('now'),datetime('now'))")->execute([$aff_id,$sale_id,$fee_crc,$amount_usd,$ex,'Pendiente']);
  header("Location: sales_pay.php?sale_id=".$sale_id); exit;
}
$rows=$pdo->prepare("SELECT * FROM sales WHERE affiliate_id=? ORDER BY datetime(start_at) DESC"); $rows->execute([$aff_id]); $sales=$rows->fetchAll(PDO::FETCH_ASSOC);
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Afiliados — Mis espacios</title><link rel="stylesheet" href="../assets/style.css?v=22"></head>
<body><header class="header"><div class="logo">🛒 Mis espacios</div><nav><a class="btn" href="dashboard.php">Panel</a></nav></header>
<div class="container">
<div class="card"><h3>Crear nuevo espacio</h3><form class="form" method="post" enctype="multipart/form-data">
<label>Título <input class="input" name="title" required></label>
<label>Portada <input class="input" type="file" name="cover" accept="image/*"></label>
<label>Inicio <input class="input" type="datetime-local" name="start_at" required></label>
<label>Fin <input class="input" type="datetime-local" name="end_at" required></label>
<button class="btn primary" name="create">Crear (se solicitará pago de activación)</button></form></div>
<div class="card"><h3>Mis espacios</h3><table class="table"><tr><th>Título</th><th>Inicio</th><th>Fin</th><th>Activo</th><th>Acciones</th></tr>
<?php foreach($sales as $s): ?><tr><td><?= htmlspecialchars($s['title']) ?></td><td><?= htmlspecialchars($s['start_at']) ?></td><td><?= htmlspecialchars($s['end_at']) ?></td><td><?= $s['is_active']?'Sí':'No' ?></td><td class="actions"><a class="btn" href="sales_pay.php?sale_id=<?= (int)$s['id'] ?>">Pagar/Estado</a></td></tr><?php endforeach; ?></table></div>
</div></body></html>