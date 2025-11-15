<?php
require_once __DIR__ . '/../includes/db.php'; require_once __DIR__ . '/../includes/config.php'; require_once __DIR__ . '/../includes/affiliate_auth.php';
aff_require_login(); $pdo=db(); $aff_id=(int)$_SESSION['aff_id']; $fee_id=(int)($_GET['fee_id']??0);
if($fee_id){
  $pdo->prepare("UPDATE sale_fees SET status='Pagado', updated_at=datetime('now') WHERE id=? AND affiliate_id=?")->execute([$fee_id,$aff_id]);
  $st=$pdo->prepare("SELECT sale_id FROM sale_fees WHERE id=?"); $st->execute([$fee_id]); $sale_id=(int)$st->fetchColumn();
  if($sale_id){ $pdo->prepare("UPDATE sales SET is_active=1, updated_at=datetime('now') WHERE id=?")->execute([$sale_id]); }
}
header("Location: sales.php");