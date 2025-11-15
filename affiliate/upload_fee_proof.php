<?php
require_once __DIR__ . '/../includes/db.php'; require_once __DIR__ . '/../includes/config.php'; require_once __DIR__ . '/../includes/affiliate_auth.php';
aff_require_login(); $pdo=db(); $aff_id=(int)$_SESSION['aff_id']; $fee_id=(int)($_POST['fee_id']??0);
$st=$pdo->prepare("SELECT * FROM sale_fees WHERE id=? AND affiliate_id=?"); $st->execute([$fee_id,$aff_id]); $fee=$st->fetch(PDO::FETCH_ASSOC);
if(!$fee){ die('Fee no encontrado'); }
if(!empty($_FILES['proof']['name'])){
  @mkdir(__DIR__.'/../uploads/affiliates',0775,true);
  $ext=pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION);
  $name='feepf_'.uniqid().'.'.$ext;
  move_uploaded_file($_FILES['proof']['tmp_name'], __DIR__.'/../uploads/affiliates/'.$name);
  $pdo->prepare("UPDATE sale_fees SET proof_file=?, status='Pagado', updated_at=datetime('now') WHERE id=?")->execute([$name,$fee_id]);
  $pdo->prepare("UPDATE sales SET is_active=1, updated_at=datetime('now') WHERE id=?")->execute([$fee['sale_id']]);
}
header("Location: sales.php");