<?php
// store.php — listado público de productos de un espacio (sale_id)
require_once __DIR__ . '/inc/security.php';   // Unifica sesión + CSRF + headers
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

if (!headers_sent()) {
  header('Content-Type: text/html; charset=UTF-8');
}
ini_set('default_charset', 'UTF-8');

/* --- Logging PHP a archivo --- */
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('error_reporting', (string)E_ALL);
$__logDir = __DIR__ . '/logs';
if (!is_dir($__logDir)) @mkdir($__logDir, 0775, true);
@ini_set('error_log', $__logDir . '/php_error.log');

/* --- Bitácora simple --- */
function log_error_cart(string $msg): void {
  $logDir = __DIR__ . '/logs';
  if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
  $line = sprintf("[%s] %s | IP:%s%s", 
    date('Y-m-d H:i:s'), $msg, $_SERVER['REMOTE_ADDR'] ?? 'N/A', PHP_EOL);
  @file_put_contents($logDir . '/error_cart.log', $line, FILE_APPEND | LOCK_EX);
}

$pdo = db();

/* ===================== Resolver sale_id ===================== */
$sale_id = (int)($_GET['sale_id'] ?? 0);
if ($sale_id < 1) {
  $pid = (int)($_GET['product_id'] ?? 0);
  if ($pid > 0) {
    try {
      $stPid = $pdo->prepare("SELECT sale_id FROM products WHERE id=?");
      $stPid->execute([$pid]);
      $sid = (int)$stPid->fetchColumn();
      if ($sid > 0) {
        $sale_id = $sid;
        log_error_cart("Auto-resuelto sale_id=$sale_id desde product_id=$pid");
      }
    } catch (Throwable $e) {
      log_error_cart("Error al resolver sale_id desde product_id=$pid: ".$e->getMessage());
    }
  }
}

if ($sale_id < 1) {
  log_error_cart("Store sin sale_id; QS=" . ($_SERVER['QUERY_STRING'] ?? ''));
  http_response_code(400);
  echo "<h3>Error: Falta información del espacio</h3>";
  exit;
}

/* ===================== Cargar venta ===================== */
$st = $pdo->prepare("SELECT s.*, a.name AS aff_name, a.id AS aff_id
                     FROM sales s
                     JOIN affiliates a ON a.id = s.affiliate_id
                     WHERE s.id=?");
$st->execute([$sale_id]);
$sale = $st->fetch(PDO::FETCH_ASSOC);
if (!$sale) { 
  http_response_code(404); 
  log_error_cart("Espacio no encontrado (sale_id=$sale_id)");
  die('Espacio no encontrado.'); 
}

/* ===================== Productos ===================== */
$ps = $pdo->prepare("SELECT * FROM products WHERE sale_id=? AND active=1 ORDER BY created_at DESC LIMIT 200");
$ps->execute([$sale_id]);
$products = $ps->fetchAll(PDO::FETCH_ASSOC);

/* --- URL del API carrito --- */
$API_URL  = '/api/cart.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($sale['title']) ?> - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="assets/style.css?v=30">
</head>
<body>
<header class="header">
  <div class="logo">&#128722; <?= APP_NAME ?></div>
  <nav>
    <a class="btn" href="index.php">Inicio</a>
    <a class="btn" href="cart.php">Ver carrito</a>
  </nav>
</header>

<div class="container">
  <div class="card">
    <h2><?= htmlspecialchars($sale['title']) ?></h2>
    <div class="small">Afiliado: <?= htmlspecialchars($sale['aff_name'] ?? 'N/D') ?></div>
  </div>

  <div class="grid">
    <?php foreach($products as $p): ?>
      <div class="card">
        <div><?= htmlspecialchars($p['name']) ?></div>
        <div>₡<?= number_format((float)$p['price'], 0, ',', '.') ?></div>
        <form class="atc" method="get" action="">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
          <input type="hidden" name="sale_id" value="<?= (int)$sale_id ?>">
          <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
          <input type="hidden" name="unit_price" value="<?= number_format((float)$p['price'], 2, '.', '') ?>">
          <input type="number" name="qty" value="1" min="1" max="<?= (int)$p['stock'] ?>" required>
          <button class="btn add-btn" type="submit" data-pid="<?= (int)$p['id'] ?>">Agregar al carrito</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
(function(){
  async function logApi(msg, extra){
    try{
      await fetch('<?= $API_URL ?>?action=log_error', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({msg, extra})
      });
    }catch(_){}
  }

  async function addToCart(form){
    const fd=new FormData(form);
    const payload={
      product_id:+(fd.get('product_id')||0),
      unit_price:+(fd.get('unit_price')||0),
      qty:+(fd.get('qty')||1)
    };
    const csrf = document.cookie.match(/(?:^|; )vg_csrf=([^;]+)/);
    const csrfHeader = csrf ? decodeURIComponent(csrf[1]) : (fd.get('csrf_token')||'');

    try{
      const res = await fetch('<?= $API_URL ?>?action=add', {
        method:'POST',
        headers:{
          'Content-Type':'application/json',
          'X-CSRF-Token': csrfHeader
        },
        body: JSON.stringify(payload),
        credentials: 'include'
      });

      if (!res.ok) {
        let txt = '';
        try { txt = await res.text(); } catch(_){}
        await logApi('HTTP '+res.status, txt);
        alert('❌ Error HTTP '+res.status);
        return;
      }

      const data = await res.json().catch(()=>null);
      if (data && data.ok) {
        alert('✅ Agregado al carrito');
      } else {
        await logApi('Respuesta no OK', data);
        alert('⚠️ No se pudo agregar');
      }
    }catch(err){
      await logApi('Error JS', err.message);
      alert('❌ Error de red');
    }
  }

  document.addEventListener('submit', e=>{
    const f=e.target;
    if(f.classList.contains('atc')){
      e.preventDefault();
      addToCart(f);
    }
  });
})();
</script>
</body>
</html>
