<?php
// affiliate/sales.php — crear y listar espacios (robusto)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';

aff_require_login();
$pdo = db();
$aff_id = (int)$_SESSION['aff_id'];
$msg = '';

//function now_iso(){ return date('Y-m-d H:i:s'); }

// Crear espacio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
  try {
    $title = trim($_POST['title'] ?? '');
    $start = trim($_POST['start_at'] ?? '');
    $end   = trim($_POST['end_at'] ?? '');

    if ($title === '' || $start === '' || $end === '') {
      throw new RuntimeException('Faltan datos obligatorios (título, inicio, fin).');
    }

    // Portada opcional
    $img = null;
    if (!empty($_FILES['cover']['name']) && is_uploaded_file($_FILES['cover']['tmp_name'])) {
      @mkdir(__DIR__ . '/../uploads/affiliates', 0775, true);
      $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) $ext = 'jpg';
      $img = 'cover_' . uniqid() . '.' . $ext;
      if (!move_uploaded_file($_FILES['cover']['tmp_name'], __DIR__ . '/../uploads/affiliates/' . $img)) {
        $img = null; // no bloquear por la imagen
      }
    }

    // INSERT con parámetros con nombre (evita desalineos)
    $sql = "INSERT INTO sales
            (affiliate_id, title, cover_image, start_at, end_at, is_active, created_at, updated_at)
            VALUES
            (:aff, :title, :cover, :start, :end, 0, :now, :now)";
    $stmt = $pdo->prepare($sql);
    $now  = now_iso();
    $stmt->execute([
      ':aff'   => $aff_id,
      ':title' => $title,
      ':cover' => $img,
      ':start' => $start,
      ':end'   => $end,
      ':now'   => $now
    ]);
    $sale_id = (int)$pdo->lastInsertId();

    // Crear fee de activación (parametrizable)
    $fee_crc = (float)get_setting('SALE_FEE_CRC', 2000);
    $ex      = (float)(function_exists('get_exchange_rate') ? get_exchange_rate() : 1);
    if ($ex <= 0) $ex = 1;
    $amount_usd = $fee_crc / $ex;

    $pdo->prepare("INSERT INTO sale_fees
      (affiliate_id, sale_id, amount_crc, amount_usd, exrate_used, status, created_at, updated_at)
      VALUES
      (:aff, :sale, :crc, :usd, :ex, 'Pendiente', :now, :now)")
        ->execute([
          ':aff' => $aff_id,
          ':sale'=> $sale_id,
          ':crc' => $fee_crc,
          ':usd' => $amount_usd,
          ':ex'  => $ex,
          ':now' => now_iso()
        ]);

    $fee_id = (int)$pdo->lastInsertId();

    // Redirigir a pago del fee (evita “Fee no encontrado”)
    header("Location: sales_pay.php?fee_id=".$fee_id);
    exit;

  } catch (Throwable $e) {
    error_log("[affiliate/sales.php] Create error: ".$e->getMessage());
    $msg = 'Error al crear el espacio: '.$e->getMessage();
  }
}

// Listado de espacios del afiliado
$rows = $pdo->prepare("SELECT * FROM sales WHERE affiliate_id=? ORDER BY datetime(start_at) DESC");
$rows->execute([$aff_id]);
$sales = $rows->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Afiliados — Mis espacios</title>
  <link rel="stylesheet" href="../assets/style.css?v=23">
</head>
<body>
<header class="header">
  <div class="logo">🛒 Mis espacios</div>
  <nav><a class="btn" href="dashboard.php">Panel</a></nav>
</header>

<div class="container">

  <?php if ($msg): ?>
    <div class="alert"><strong>Aviso:</strong> <?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <div class="card">
    <h3>Crear nuevo espacio</h3>
    <form class="form" method="post" enctype="multipart/form-data">
      <label>Título <input class="input" name="title" required></label>
      <label>Portada <input class="input" type="file" name="cover" accept="image/*"></label>
      <label>Inicio <input class="input" type="datetime-local" name="start_at" required></label>
      <label>Fin <input class="input" type="datetime-local" name="end_at" required></label>
      <button class="btn primary" name="create" value="1">Crear (pagar activación)</button>
    </form>
  </div>

  <div class="card">
    <h3>Mis espacios</h3>
    <table class="table">
      <tr><th>Título</th><th>Inicio</th><th>Fin</th><th>Activo</th><th>Acciones</th></tr>
      <?php foreach($sales as $s): ?>
        <tr>
          <td><?= htmlspecialchars($s['title']) ?></td>
          <td><?= htmlspecialchars($s['start_at']) ?></td>
          <td><?= htmlspecialchars($s['end_at']) ?></td>
          <td><?= !empty($s['is_active']) ? 'Sí' : 'No' ?></td>
          <td class="actions">
            <?php
              // Si ya hay fee creado, ir por fee_id; si no, por sale_id
              $q = $pdo->prepare("SELECT id FROM sale_fees WHERE sale_id=? AND affiliate_id=? ORDER BY id DESC LIMIT 1");
              $q->execute([(int)$s['id'], $aff_id]);
              $existing_fee_id = (int)$q->fetchColumn();
              if ($existing_fee_id):
            ?>
              <a class="btn" href="sales_pay.php?fee_id=<?= (int)$existing_fee_id ?>">Pagar/Estado</a>
            <?php else: ?>
              <a class="btn" href="sales_pay.php?sale_id=<?= (int)$s['id'] ?>">Pagar/Estado</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
</body>
</html>
