<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
$pdo = db();
$msg = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  try {
    if (isset($_POST['toggle'])) {
      $sale_id = (int)$_POST['sale_id'];
      $new = (int)$_POST['new_state'];
      $pdo->prepare("UPDATE sales SET is_active=?, updated_at=datetime('now') WHERE id=?")
          ->execute([$new, $sale_id]);
      $msg = 'Estado de espacio actualizado.';
    } elseif (isset($_POST['approve_fee'])) {
      $fee_id = (int)$_POST['fee_id'];
      $fee = $pdo->prepare("SELECT sale_id FROM sale_fees WHERE id=?");
      $fee->execute([$fee_id]);
      $sale_id = (int)$fee->fetchColumn();
      if ($sale_id) {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE sale_fees SET status='Pagado', updated_at=datetime('now') WHERE id=?")
            ->execute([$fee_id]);
        $pdo->prepare("UPDATE sales SET is_active=1, updated_at=datetime('now') WHERE id=?")
            ->execute([$sale_id]);
        $pdo->commit();
        $msg = 'Pago aprobado y espacio activado.';
      } else {
        $msg = 'No se encontró el sale_id del fee.';
      }
    }
  } catch (Throwable $e) {
    $msg = 'Error: '.$e->getMessage();
  }
}

$sql = "
SELECT s.*, a.email AS aff_email,
  (SELECT status FROM sale_fees f WHERE f.sale_id=s.id ORDER BY f.id DESC LIMIT 1) AS fee_status,
  (SELECT id FROM sale_fees f WHERE f.sale_id=s.id ORDER BY f.id DESC LIMIT 1) AS fee_id
FROM sales s
LEFT JOIN affiliates a ON a.id=s.affiliate_id
ORDER BY datetime(s.created_at) DESC
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin - Espacios</title>
<link rel="stylesheet" href="../assets/style.css">
</head><body>
<header class="header">
  <div class="logo">🛒 Admin — Espacios</div>
  <nav><a class="btn" href="dashboard.php">Dashboard</a></nav>
</header>
<div class="container">
<?php if($msg): ?><div class="alert"><strong>Aviso:</strong> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="card">
  <h3>Espacios</h3>
  <table class="table">
    <tr><th>ID</th><th>Título</th><th>Afiliado</th><th>Inicio</th><th>Fin</th><th>Fee</th><th>Activo</th><th>Acciones</th></tr>
    <?php foreach($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= htmlspecialchars($r['title'] ?? '—') ?></td>
      <td><?= htmlspecialchars($r['aff_email'] ?: '—') ?></td>
      <td><?= htmlspecialchars($r['start_at'] ?: '—') ?></td>
      <td><?= htmlspecialchars($r['end_at'] ?: '—') ?></td>
      <td><?= htmlspecialchars($r['fee_status'] ?: '—') ?></td>
      <td><?= !empty($r['is_active'])?'Sí':'No' ?></td>
      <td>
        <form method="post" style="display:inline">
          <input type="hidden" name="sale_id" value="<?= (int)$r['id'] ?>">
          <input type="hidden" name="new_state" value="<?= !empty($r['is_active'])?0:1 ?>">
          <button class="btn" name="toggle" value="1"><?= !empty($r['is_active'])?'Desactivar':'Activar' ?></button>
        </form>
        <?php if(!empty($r['fee_id']) && ($r['fee_status']??'')!=='Pagado'): ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="fee_id" value="<?= (int)$r['fee_id'] ?>">
            <button class="btn" name="approve_fee" value="1">Aprobar pago SINPE</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
</div></body></html>
