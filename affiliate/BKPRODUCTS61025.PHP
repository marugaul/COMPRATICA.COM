<?php
require_once __DIR__ . '/../includes/db.php'; require_once __DIR__ . '/../includes/config.php'; require_once __DIR__ . '/../includes/affiliate_auth.php';
aff_require_login(); $pdo=db(); $aff_id=(int)$_SESSION['aff_id']; $msg='';
function upload_image($field){
  if(!empty($_FILES[$field]['name'])){
    @mkdir(__DIR__ . '/../uploads', 0775, true);
    $ext=pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
    $name='img_'.bin2hex(random_bytes(6)).'.'.strtolower($ext);
    move_uploaded_file($_FILES[$field]['tmp_name'], __DIR__.'/../uploads/'.$name);
    return $name;
  } return null;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(isset($_POST['create'])){
    $name=trim($_POST['name']??''); $desc=trim($_POST['description']??''); $price=(float)($_POST['price']??0); $stock=(int)($_POST['stock']??0);
    $currency=in_array($_POST['currency']??'CRC',['CRC','USD'])?$_POST['currency']:'CRC'; $img=upload_image('image');
    $st=$pdo->prepare("INSERT INTO products(name,description,price,stock,image,active,created_at,updated_at,currency,affiliate_id) VALUES(?,?,?,?,?,1,datetime('now'),datetime('now'),?,?)");
    $st->execute([$name,$desc,$price,$stock,$img,$currency,$aff_id]); $msg='Producto creado';
  } elseif(isset($_POST['update'])){
    $id=(int)$_POST['id']; $name=trim($_POST['name']??''); $desc=trim($_POST['description']??''); $price=(float)($_POST['price']??0); $stock=(int)($_POST['stock']??0);
    $currency=in_array($_POST['currency']??'CRC',['CRC','USD'])?$_POST['currency']:'CRC'; $img=upload_image('image');
    if($img){ $st=$pdo->prepare("UPDATE products SET name=?, description=?, price=?, stock=?, image=?, currency=?, updated_at=datetime('now') WHERE id=? AND affiliate_id=?"); $st->execute([$name,$desc,$price,$stock,$img,$currency,$id,$aff_id]); }
    else { $st=$pdo->prepare("UPDATE products SET name=?, description=?, price=?, stock=?, currency=?, updated_at=datetime('now') WHERE id=? AND affiliate_id=?"); $st->execute([$name,$desc,$price,$stock,$currency,$id,$aff_id]); }
    $msg='Producto actualizado';
  } elseif(isset($_POST['delete'])){
    $id=(int)$_POST['id']; $pdo->prepare("DELETE FROM products WHERE id=? AND affiliate_id=?")->execute([$id,$aff_id]); $msg='Producto eliminado';
  }
}
$rows=$pdo->prepare("SELECT * FROM products WHERE affiliate_id=? ORDER BY id DESC"); $rows->execute([$aff_id]); $products=$rows->fetchAll(PDO::FETCH_ASSOC);
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Afiliados — Mis productos</title><link rel="stylesheet" href="../assets/style.css?v=22"></head>
<body><header class="header"><div class="logo">🛒 Mis productos</div><nav><a class="btn" href="dashboard.php">Panel</a></nav></header>
<div class="container"><?php if($msg): ?><div class="success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="card"><h3>Crear producto</h3>
<form class="form" method="post" enctype="multipart/form-data">
<label>Nombre <input class="input" name="name" required></label>
<label>Descripción <textarea class="input" name="description"></textarea></label>
<label>Precio <input class="input" type="number" step="0.01" name="price" required></label>
<label>Stock <input class="input" type="number" name="stock" required></label>
<label>Moneda <select class="input" name="currency"><option value="CRC">CRC</option><option value="USD">USD</option></select></label>
<label>Imagen <input class="input" type="file" name="image" accept="image/*" required></label>
<button class="btn primary" name="create">Crear</button></form></div>
<div class="card"><h3>Mis productos</h3>
<table class="table"><tr><th>#</th><th>Imagen</th><th>Nombre</th><th>Precio</th><th>Stock</th><th>Moneda</th><th>Acciones</th></tr>
<?php foreach($products as $p): ?><tr>
<td><?= (int)$p['id'] ?></td><td><img class="thumb" src="../uploads/<?= htmlspecialchars($p['image']) ?>"></td>
<td><?= htmlspecialchars($p['name']) ?></td>
<td><?= number_format((float)$p['price'], ($p['currency']==='USD'?2:0), $p['currency']==='USD'?'.':',', $p['currency']==='USD'?',':'.') ?></td>
<td><?= (int)$p['stock'] ?></td><td><?= htmlspecialchars($p['currency']??'CRC') ?></td>
<td class="actions"><details><summary class="btn">Editar</summary>
<form class="form" method="post" enctype="multipart/form-data" style="margin-top:8px">
<input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
<label>Nombre <input class="input" name="name" value="<?= htmlspecialchars($p['name']) ?>" required></label>
<label>Descripción <textarea class="input" name="description"><?= htmlspecialchars($p['description']??'') ?></textarea></label>
<label>Precio <input class="input" type="number" step="0.01" name="price" value="<?= htmlspecialchars($p['price']) ?>" required></label>
<label>Stock <input class="input" type="number" name="stock" value="<?= (int)$p['stock'] ?>" required></label>
<label>Moneda <select class="input" name="currency"><option value="CRC" <?= ($p['currency']==='CRC'?'selected':'') ?>>CRC</option><option value="USD" <?= ($p['currency']==='USD'?'selected':'') ?>>USD</option></select></label>
<label>Imagen (opcional) <input class="input" type="file" name="image" accept="image/*"></label>
<button class="btn" name="update">Guardar</button>
<button class="btn" name="delete" onclick="return confirm('¿Eliminar producto?')">Eliminar</button>
</form></details></td></tr><?php endforeach; ?>
</table></div></div></body></html>