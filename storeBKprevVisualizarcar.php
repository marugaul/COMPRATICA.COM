<?php 
// store.php — listado público de productos de un espacio (sale_id)
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

if (!headers_sent()) {
  header('Content-Type: text/html; charset=UTF-8');
}
ini_set('default_charset', 'UTF-8');

/* --- Helper CSRF (solo lectura; la generación la hace config.php) --- */
function csrf_token(): string { return $_SESSION['csrf_token'] ?? ''; }

/* --- Bitácora simple de errores --- */
function log_error_cart(string $msg): void {
  $logDir = __DIR__ . '/logs';
  if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
  $line = sprintf("[%s] %s | IP:%s%s", 
    date('Y-m-d H:i:s'), $msg, $_SERVER['REMOTE_ADDR'] ?? 'N/A', PHP_EOL);
  @file_put_contents($logDir . '/error_cart.log', $line, FILE_APPEND);
}

$pdo = db();
$sale_id = (int)($_GET['sale_id'] ?? 0);

// Cargar venta
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

// Productos
$ps = $pdo->prepare("SELECT * FROM products WHERE sale_id=? AND active=1 ORDER BY created_at DESC LIMIT 200");
$ps->execute([$sale_id]);
$products = $ps->fetchAll(PDO::FETCH_ASSOC);

// Estado de fechas
$tzCR   = new DateTimeZone('America/Costa_Rica');
$tzDB   = new DateTimeZone('UTC');
$nowDT  = new DateTime('now', $tzCR);
$not_started = $finished = false;
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
  log_error_cart("Error de fecha sale_id=$sale_id: " . $e->getMessage());
}

/* --- URL fija del API --- */
$API_URL = '/api/cart.php';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($sale['title']) ?> - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="assets/style.css?v=25">
  <style>
    .imgbox{width:100%;height:auto;border-radius:12px;border:1px solid #eee;overflow:hidden}
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
    .atc { display:flex; gap:.5rem; align-items:center; margin:.5rem 0; flex-wrap:wrap; }
    .atc input[type="number"]{ width:80px; padding:.4rem; }
    .atc .btn { padding:.5rem .8rem; }
  </style>
</head>
<body>
<header class="header">
  <div class="logo">&#128722; <?= APP_NAME ?></div>
  <nav><a class="btn" href="index.php">Inicio</a></nav>
</header>

<div class="container">
  <div class="card">
    <h2><?= htmlspecialchars($sale['title']) ?></h2>
    <div class="small">Afiliado: <?= htmlspecialchars($sale['aff_name'] ?? 'N/D') ?></div>
    <?php if ($not_started): ?><div class="alert">Este espacio aún no inicia.</div><?php endif; ?>
    <?php if ($finished): ?><div class="alert">Este espacio ya finalizó.</div><?php endif; ?>
  </div>

  <div class="grid">
    <?php foreach($products as $p): ?>
      <div class="card">
        <?php
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
          <?php if((strtoupper($p['currency'] ?? 'CRC'))==='USD'): ?>
            $<?= number_format((float)$p['price'], 2, '.', ',') ?>
          <?php else: ?>
            ₡<?= number_format((float)$p['price'], 0, ',', '.') ?>
          <?php endif; ?>
          &nbsp;•&nbsp; Stock: <?= (int)$p['stock'] ?>
        </div>

        <?php if ((int)$p['stock'] > 0): ?>
          <form class="atc" onsubmit="return addToCart_<?= (int)$p['id'] ?>(this, event)">
            <!-- CSRF inicial (solo fallback); el vigente se pide por API al enviar -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="unit_price" value="<?= number_format((float)$p['price'], 2, '.', '') ?>">
            <label>Cantidad
              <input type="number" name="qty" value="1" min="1" max="<?= (int)$p['stock'] ?>" required>
            </label>
            <button class="btn" type="submit" <?= ($not_started||$finished)?'disabled':''; ?>>Agregar al carrito</button>
          </form>

          <form method="get" action="checkout.php" class="actions">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="sale_id" value="<?= (int)$sale_id ?>">
            <input type="hidden" name="affiliate_id" value="<?= (int)$sale['aff_id'] ?>">
            <button class="btn primary" type="submit" <?= ($not_started||$finished)?'disabled':''; ?>>Comprar</button>
          </form>
        <?php else: ?>
          <div class="alert">Agotado</div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<footer class="container small">&copy; <?= date('Y') ?> <?= APP_NAME ?></footer>

<script>
(function(){
  document.querySelectorAll('.gal[data-images]').forEach(root=>{
    let imgs=[]; try{imgs=JSON.parse(root.dataset.images)||[]}catch(e){}
    if(!imgs.length)return;
    const img=root.querySelector('.gal-img'),prev=root.querySelector('.gal-prev'),next=root.querySelector('.gal-next');
    let i=0; function render(){img.src=imgs[i]; if(prev)prev.disabled=(i===0); if(next)next.disabled=(i===imgs.length-1);}
    if(prev)prev.onclick=()=>{if(i>0){i--;render();}};
    if(next)next.onclick=()=>{if(i<imgs.length-1){i++;render();}};
    render();
  });
})();
</script>

<script>
<?php foreach($products as $p): if ((int)$p['stock'] > 0): 
  $pid=(int)$p['id']; ?>
function addToCart_<?= $pid ?>(form, evt){
  if(evt) evt.preventDefault(); // ✅ Bloquea recarga
  (async ()=>{
    // 1) Obtén CSRF vigente desde el backend (evita tokens viejos)
    let csrfToken = '';
    try{
      const cr = await fetch('<?= $API_URL ?>?action=get_csrf', {credentials:'same-origin'});
      const cj = await cr.json();
      if (cj && cj.ok && cj.csrf_token) csrfToken = cj.csrf_token;
    }catch(e){ /* si falla, usamos el del input hidden como fallback */ }
    if (!csrfToken) {
      const hidden = form.querySelector('input[name="csrf_token"]');
      csrfToken = hidden ? hidden.value : '';
    }

    // 2) Datos base
    const fd=new FormData(form);
    const payload={
      product_id:+fd.get('product_id'),
      unit_price:+fd.get('unit_price'),
      qty:+(fd.get('qty')||1),
      csrf_token: csrfToken // también en body para fallback
    };

    // 3) Intento JSON
    try{
      const res=await fetch('<?= $API_URL ?>?action=add',{
        method:'POST',
        headers:{
          'Content-Type':'application/json',
          'X-CSRF-Token': csrfToken
        },
        body:JSON.stringify(payload),
        credentials:'same-origin'
      });
      if(!res.ok) throw new Error('HTTP '+res.status);
      const data=await res.json();
      if(data && data.ok){
        alert('✅ Producto agregado al carrito');
      }else{
        alert('⚠️ No se pudo agregar.');
      }
    }catch(errJSON){
      // 4) Fallback: x-www-form-urlencoded (por si WAF bloquea JSON)
      try{
        const enc = new URLSearchParams();
        enc.set('product_id', payload.product_id);
        enc.set('unit_price', payload.unit_price);
        enc.set('qty', payload.qty);
        enc.set('csrf_token', payload.csrf_token);

        const res2=await fetch('<?= $API_URL ?>?action=add',{
          method:'POST',
          headers:{ 'Content-Type':'application/x-www-form-urlencoded' },
          body: enc.toString(),
          credentials:'same-origin'
        });
        if(!res2.ok) throw new Error('HTTP '+res2.status);
        const data2=await res2.json();
        if(data2 && data2.ok){
          alert('✅ Producto agregado al carrito');
        }else{
          alert('⚠️ No se pudo agregar (fallback).');
        }
      }catch(errForm){
        alert('❌ Error de red al agregar.');
        fetch('<?= $API_URL ?>?action=log_error',{
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body:JSON.stringify({msg:'AddToCart falló: '+(errForm.message||'desconocido')+'; JSON err: '+(errJSON.message||'') , product: payload.product_id})
        });
      }
    }
  })();
  return false;
}
<?php endif; endforeach; ?>
</script>
</body>
</html>
