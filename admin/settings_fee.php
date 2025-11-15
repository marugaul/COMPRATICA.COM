<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
$pdo = db();
$msg = '';

function get_sale_fee_crc(PDO $pdo, $default = 2000) {
  try {
    $v = $pdo->query("SELECT sale_fee_crc FROM settings LIMIT 1")->fetchColumn();
    return ($v !== false && $v !== null) ? (int)$v : (int)$default;
  } catch (Throwable $e) { return (int)$default; }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_settings'])) {
  $fee = max(0, (int)($_POST['SALE_FEE_CRC'] ?? 2000));
  $pdo->prepare("UPDATE settings SET sale_fee_crc=?")->execute([$fee]);
  $msg = 'Ajustes guardados.';
}

$fee_val = get_sale_fee_crc($pdo);
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin - Costo por espacio</title>
<link rel="stylesheet" href="../assets/style.css">
</head><body>
<header class="header"><div class="logo">🛒 Admin — Costo por espacio</div>
<nav>
  <a class="btn" href="dashboard.php">Dashboard</a>
  <a class="btn" href="sales_admin.php">Espacios</a>
  <a class="btn" href="affiliates.php">Afiliados</a>
</nav></header>
<div class="container">
  <?php if($msg): ?><div class="success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <div class="card">
    <h3>Configurar costo de espacio (CRC)</h3>
    <form class="form" method="post">
      <label>Costo por espacio (CRC)
        <input class="input" type="number" name="SALE_FEE_CRC" value="<?= (int)$fee_val ?>" min="0" step="1">
      </label>
      <button class="btn primary" name="save_settings" value="1">Guardar</button>
    </form>
  </div>
</div>
</body></html>
