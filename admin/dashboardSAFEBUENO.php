<?php
// admin/dashboard.php (SAFE limpio, sin BOM)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

try {
  require_login(); // si no hay sesión, redirige a login.php
  $pdo = db();

  $products = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
  $orders = $pdo->query("SELECT o.*, p.name AS product_name FROM orders o JOIN products p ON p.id=o.product_id ORDER BY o.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
  $settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
  $ex = (float)($settings['exchange_rate'] ?? 540.00);
} catch (Throwable $e) {
  header('Content-Type: text/plain; charset=utf-8');
  echo "FATAL: ".$e->getMessage()."\n\n".$e->getTraceAsString();
  exit;
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Backoffice (SAFE)</title><link rel="stylesheet" href="../assets/style.css"></head>
<body>
<header class="header">
  <div class="logo">⚙️ Backoffice (SAFE)</div>
  <nav><a class="btn" href="../index">Ver tienda</a><a class="btn" href="logout.php">Salir</a></nav>
</header>
<div class="container">
  <div class="success">session_id: <?php echo session_id(); ?> | logged: <?php echo !empty($_SESSION['admin'])?'true':'false'; ?></div>

  <h2>Configuración</h2>
  <form class="form" method="post">
    <input type="hidden" name="action" value="save_settings">
    <label>Tipo de cambio (CRC por 1 USD)
      <input class="input" type="number" name="exchange_rate" step="0.01" min="100" value="<?php echo htmlspecialchars(number_format($ex,2,'.','')); ?>">
    </label>
    <div class="actions"><button class="btn primary">Guardar</button></div>
  </form>

  <h2>Productos (máx 10)</h2>
  <pre><?php var_dump(array_slice($products,0,10)); ?></pre>

  <h2>Pedidos (máx 10)</h2>
  <pre><?php var_dump(array_slice($orders,0,10)); ?></pre>
</div>
</body></html>