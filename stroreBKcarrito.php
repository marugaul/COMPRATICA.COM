<?php
// store.php — listado público de productos de un espacio (sale_id)
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

if (!headers_sent()) {
  header('Content-Type: text/html; charset=UTF-8');
}
ini_set('default_charset', 'UTF-8');

$pdo = db();
$sale_id = (int)($_GET['sale_id'] ?? 0);

// Cargar venta
$st = $pdo->prepare("SELECT s.*, a.name AS aff_name, a.id AS aff_id
                     FROM sales s
                     JOIN affiliates a ON a.id = s.affiliate_id
                     WHERE s.id=?");
$st->execute([$sale_id]);
$sale = $st->fetch(PDO::FETCH_ASSOC);
if (!$sale) { http_response_code(404); die('Espacio no encontrado.'); }

// Productos del espacio
$ps = $pdo->prepare("SELECT * FROM products WHERE sale_id=? AND active=1 ORDER BY created_at DESC LIMIT 200");
$ps->execute([$sale_id]);
$products = $ps->fetchAll(PDO::FETCH_ASSOC);

/**
 * Estado por fechas usando TZ de Costa Rica.
 * Suponemos que start_at / end_at en DB están en UTC (recomendado).
 * Si en tu instalación se guardan en hora local, cambia 'UTC' por 'America/Costa_Rica'.
 */
$tzCR   = new DateTimeZone('America/Costa_Rica');
$tzDB   = new DateTimeZone('UTC'); // <-- cambia a 'America/Costa_Rica' si guardas local
$nowDT  = new DateTime('now', $tzCR);

$not_started = false;
$finished    = false;

try {
  if (!empty($sale['start_at'])) {
    $start = new DateTime($sale['start_at'], $tzDB);
    $start->setTimezone($tzCR);
    $not_started = ($nowDT < $start);
  }
  if (!empty($sale['end_at'])) {
    $end = new DateTime($sale['end_at'], $tzDB);
    $end->setTimezone($tzCR);
    $finished = ($nowDT > $end);
  }
} catch (Throwable $e) {
  // Ignorar errores de parseo para no romper la vista
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($sale['title']) ?> - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="assets/style.css?v=25">
  <style>
    .imgbox{width:100%;height:auto;border-radius:12px;border:1px solid #eee;overflow:hidden}
    /* Galería manual por producto */
    .gal{position:relative}
    .gal-img{width:100%;height:190px;object-fit:cover;display:block}
    .gal-prev,.gal-next{
      position:absolute;top:50%;transform:translateY(-50%);
      background:rgba(0,0,0,.55);color:#fff;border:0;border-radius:999px;
      width:32px;height:32px;display:flex;align-items:center;justify-content:center;
      cursor:pointer;user-select:none
    }
    .gal-prev{left:8px}
    .gal-next{right:8px}
    .gal-prev:disabled,.gal-next:disabled{opacity:.4;cursor:not-allowed}
  </style>
</head>
<body>
<header class="header">
  <div class="logo">&#128722; <?= APP_NAME ?></div>
  <nav><a class="btn" href="index.php">Inicio</a></nav>
</header>

<div class="container">
  <div class="card">
    <h2 style="margin:0 0 8px"><?= htmlspecialchars($sale['title']) ?></h2>
    <div class="small">Afiliado: <?= htmlspecialchars($sale['aff_name'] ?? 'N/D') ?></div>
    <?php if ($not_started): ?><div class="alert">Este espacio aún no inicia.</div><?php endif; ?>
    <?php if ($finished): ?><div class="alert">Este espacio ya finalizó.</div><?php endif; ?>
  </div>

  <div class="grid">
    <?php foreach($products as $p): ?>
      <div class="card">
        <?php
          // Construir arreglo de imágenes: image + image2 (si existen)
          $img1 = !empty($p['image'])  ? 'uploads/' . $p['image']  : null;
          $img2 = !empty($p['image2']) ? 'uploads/' . $p['image2'] : null;
          $imgs = array_values(array_filter([$img1, $img2]));
        ?>
        <div class="imgbox">
          <div class="gal" data-images='<?= json_encode($imgs, JSON_UNESCAPED_SLASHES) ?>'>
            <?php if (!empty($imgs)): ?>
              <img class="gal-img" src="<?= htmlspecialchars($imgs[0]) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
              <?php if (count($imgs) > 1): ?>
                <button type="button" class="gal-prev" aria-label="Anterior">&lsaquo;</button>
                <button type="button" class="gal-next" aria-label="Siguiente">&rsaquo;</button>
              <?php endif; ?>
            <?php else: ?>
              <div class="small" style="padding:12px;text-align:center">Sin imagen</div>
            <?php endif; ?>
          </div>
        </div>

        <div style="font-weight:700"><?= htmlspecialchars($p['name']) ?></div>
        <div class="price">
          <?php if(($p['currency'] ?? 'CRC')==='USD'): ?>
            $<?= number_format((float)$p['price'], 2, '.', ',') ?>
          <?php else: ?>
            &#x20A1;<?= number_format((float)$p['price'], 0, ',', '.') ?>
          <?php endif; ?>
          &nbsp;&#8226;&nbsp; Stock: <?= (int)$p['stock'] ?>
        </div>

        <?php if ((int)$p['stock'] > 0): ?>
          <form method="get" action="checkout.php" class="actions">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="sale_id" value="<?= (int)$sale_id ?>">
            <input type="hidden" name="affiliate_id" value="<?= (int)$sale['aff_id'] ?>">
            <button class="btn primary" type="submit" <?= ($not_started||$finished)?'disabled':''; ?>>
              Comprar
            </button>
          </form>
        <?php else: ?>
          <div class="alert">Agotado</div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<footer class="container small">&copy; <?= date('Y') ?> <?= APP_NAME ?></footer>

<!-- Galería: JS sin autoplay (por producto) -->
<script>
(function(){
  function initGallery(root){
    var data = root.getAttribute('data-images');
    if(!data) return;
    var imgs = [];
    try { imgs = JSON.parse(data) || []; } catch(e){}
    if(!Array.isArray(imgs) || imgs.length === 0) return;

    var imgEl = root.querySelector('.gal-img');
    var prev  = root.querySelector('.gal-prev');
    var next  = root.querySelector('.gal-next');
    var idx   = 0;

    function render(){
      imgEl.src = imgs[idx];
      if(prev) prev.disabled = (idx === 0);
      if(next) next.disabled = (idx === imgs.length - 1);
    }
    if(prev) prev.addEventListener('click', function(){ if(idx > 0){ idx--; render(); } });
    if(next) next.addEventListener('click', function(){ if(idx < imgs.length - 1){ idx++; render(); } });

    render();
  }

  document.querySelectorAll('.gal[data-images]').forEach(initGallery);
})();
</script>
</body>
</html>
