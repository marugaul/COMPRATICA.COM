<?php
// store.php — versión con galería e popover restaurados + API /api/cart.php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
ini_set('default_charset', 'UTF-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
  ini_set('session.cookie_samesite', 'Lax');
  if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) {
    ini_set('session.cookie_secure', '1');
  }
  session_start();
}

// CSRF por cookie
$token = $_COOKIE['vg_csrf'] ?? bin2hex(random_bytes(32));
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
setcookie('vg_csrf', $token, [
  'expires'  => time()+7200,
  'path'     => '/',
  'secure'   => $isHttps,
  'httponly' => false,
  'samesite' => 'Lax'
]);

function log_error_cart(string $msg): void {
  $logDir = __DIR__ . '/logs';
  if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
  $line = sprintf("[%s] %s | IP:%s%s", date('Y-m-d H:i:s'), $msg, $_SERVER['REMOTE_ADDR'] ?? 'N/A', PHP_EOL);
  @file_put_contents($logDir . '/error_cart.log', $line, FILE_APPEND | LOCK_EX);
}

$API_URL = '/api/cart.php';

$pdo = db();
$sale_id = (int)($_GET['sale_id'] ?? 0);

// Resolver sale_id
if ($sale_id < 1) {
  $pid = (int)($_GET['product_id'] ?? ($_GET['id'] ?? 0));
  if ($pid > 0) {
    try {
      $stPid = $pdo->prepare("SELECT sale_id FROM products WHERE id=?");
      $stPid->execute([$pid]);
      $sid = (int)$stPid->fetchColumn();
      if ($sid > 0) $sale_id = $sid;
    } catch (Throwable $e) { log_error_cart("Resolver sale_id desde product_id=$pid: ".$e->getMessage()); }
  }
}
if ($sale_id < 1) {
  try {
    $nowUTC = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $stOne = $pdo->prepare("SELECT id FROM sales WHERE (start_at IS NULL OR start_at <= ?) AND (end_at IS NULL OR end_at >= ?) ORDER BY created_at DESC LIMIT 2");
    $stOne->execute([$nowUTC, $nowUTC]);
    $rows = $stOne->fetchAll(PDO::FETCH_COLUMN);
    if (count($rows) === 1) $sale_id = (int)$rows[0];
  } catch (Throwable $e) { log_error_cart("Resolver sale activo: ".$e->getMessage()); }
}
if ($sale_id < 1) { http_response_code(404); die('Espacio no encontrado.'); }

$st = $pdo->prepare("SELECT s.*, a.name AS aff_name, a.id AS aff_id FROM sales s JOIN affiliates a ON a.id = s.affiliate_id WHERE s.id=?");
$st->execute([$sale_id]);
$sale = $st->fetch(PDO::FETCH_ASSOC);
if (!$sale) { http_response_code(404); die('Espacio no encontrado.'); }

$ps = $pdo->prepare("SELECT * FROM products WHERE sale_id=? AND active=1 ORDER BY created_at DESC LIMIT 200");
$ps->execute([$sale_id]);
$products = $ps->fetchAll(PDO::FETCH_ASSOC);

// Fechas
$tzCR = new DateTimeZone('America/Costa_Rica');
$tzDB = new DateTimeZone('UTC');
$nowDT = new DateTime('now', $tzCR);
$not_started = $finished = false;
try {
  if (!empty($sale['start_at'])) { $start = new DateTime($sale['start_at'], $tzDB); $start->setTimezone($tzCR); $not_started = ($nowDT < $start); }
  if (!empty($sale['end_at'])) { $end = new DateTime($sale['end_at'], $tzDB); $end->setTimezone($tzCR); $finished = ($nowDT > $end); }
} catch (Throwable $e) { log_error_cart("Fechas sale_id=$sale_id: ".$e->getMessage()); }

function img_url_or_null($file){
  if (!$file) return null;
  // Ajusta esta línea si tus imágenes están realmente en /uploads (desde la raíz web).
  // Si están en la carpeta relativa a store.php, deja 'uploads/'.
  return 'uploads/' . $file;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($sale['title']) ?> - <?= APP_NAME ?></title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .gal{position:relative}
    .gal-img{width:100%;height:190px;object-fit:cover;display:block}
    .gal-prev,.gal-next{position:absolute;top:50%;transform:translateY(-50%);background:rgba(0,0,0,.55);color:#fff;border:0;border-radius:999px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer}
    .gal-prev{left:8px}.gal-next{right:8px}
    .gal-prev:disabled,.gal-next:disabled{opacity:.4;cursor:not-allowed}
    .atc { display:flex; gap:.5rem; align-items:center; margin:.5rem 0; flex-wrap:wrap; }
    .atc input[type="number"]{ width:80px; padding:.4rem; }
    .cart-popover{position:absolute; width:320px; background:#fff; border:1px solid #ddd; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.12); padding:12px; display:none; z-index:1000;}
    .cart-popover.open{ display:block; }
    .cart-popover .title{ font-weight:700; margin-bottom:8px; }
    .cart-popover .list{ max-height:300px; overflow:auto; }
    .cart-popover .item{ display:flex; gap:8px; align-items:center; padding:6px 0; }
    .cart-popover .thumb{ width:40px; height:40px; object-fit:cover; border-radius:8px; }
    .cart-popover .name{ font-size:.9rem; }
    .cart-popover .meta{ font-size:.8rem; color:#666; }
    .cart-popover .price{ margin-left:auto; font-weight:700; }
    .cart-popover .footer{ display:flex; justify-content:space-between; align-items:center; margin-top:8px; }
    .badge{ position:absolute; top:-6px; right:-6px; background:#e11; color:#fff; border-radius:999px; padding:2px 7px; font-size:.75rem; }
  </style>
</head>
<body>
<header class="header">
  <div class="logo"><a href="index.php" style="text-decoration:none;color:inherit;">🛍️ <?= APP_NAME ?></a></div>
  <nav>
    <a class="btn" href="index.php">Inicio</a>
    <a id="cartButton" class="btn" href="cart.php" style="position:relative">🛒 Carrito <span id="cartBadge" class="badge">0</span></a>
  </nav>
</header>

<div class="container">
  <div class="card">
    <h2><?= htmlspecialchars($sale['title']) ?></h2>
    <div class="small">Afiliado: <?= htmlspecialchars($sale['aff_name'] ?? 'N/D') ?></div>
    <?php if ($not_started): ?><div class="alert">Este espacio aún no inicia.</div><?php endif; ?>
    <?php if ($finished): ?><div class="alert">Este espacio ya finalizó.</div><?php endif; ?>
  </div>

  <div class="grid">
    <?php foreach ($products as $p): ?>
    <?php
      $img1 = img_url_or_null($p['image'] ?? null);
      $img2 = img_url_or_null($p['image2'] ?? null);
      $imgs = array_values(array_filter([$img1, $img2]));
    ?>
    <div class="card">
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
        <?php if ((strtoupper($p['currency'] ?? 'CRC')) === 'USD'): ?>
          $<?= number_format((float)$p['price'], 2, '.', ',') ?>
        <?php else: ?>
          ₡<?= number_format((float)$p['price'], 0, ',', '.') ?>
        <?php endif; ?>
        &nbsp;•&nbsp; Stock: <?= (int)$p['stock'] ?>
      </div>

      <?php if ((int)$p['stock'] > 0): ?>
      <form class="atc" method="post" action="#">
        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
        <input type="hidden" name="unit_price" value="<?= number_format((float)$p['price'], 2, '.', '') ?>">
        <label>Cantidad
          <input type="number" name="qty" value="1" min="1" max="<?= (int)$p['stock'] ?>" required>
        </label>
        <button class="btn" type="submit" <?= ($not_started || $finished) ? 'disabled' : ''; ?>>Agregar al carrito</button>
      </form>
      <?php else: ?>
      <div class="alert">Agotado</div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<footer class="container small">&copy; <?= date('Y') ?> <?= APP_NAME ?></footer>

<!-- Galería -->
<script>
(function(){
  document.querySelectorAll('.gal[data-images]').forEach(function(root){
    var imgs=[]; try{ imgs = JSON.parse(root.dataset.images)||[]; }catch(e){}
    if(!imgs.length) return;
    var img = root.querySelector('.gal-img');
    var prev = root.querySelector('.gal-prev');
    var next = root.querySelector('.gal-next');
    var i = 0;
    function render(){
      img.src = imgs[i];
      if(prev) prev.disabled = (i===0);
      if(next) next.disabled = (i===imgs.length-1);
    }
    if(prev) prev.onclick = function(){ if(i>0){ i--; render(); } };
    if(next) next.onclick = function(){ if(i<imgs.length-1){ i++; render(); } };
    render();
  });
})();
</script>

<!-- Add to cart + badge -->
<script>
(function(){
  var API = <?= json_encode($API_URL, JSON_UNESCAPED_SLASHES) ?>;

  function getCsrf(){
    var m = document.cookie.match(/(?:^|; )vg_csrf=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
  }
  function updateBadge(count){
    var el = document.getElementById('cartBadge');
    if (!el) return;
    var val = Number(count)||0;
    el.textContent = val;
    el.style.display = val>0 ? 'inline-block' : 'none';
  }

  async function addToCart(form){
    var fd = new FormData(form);
    var payload = {
      product_id: +(fd.get('product_id')||0),
      unit_price: +(fd.get('unit_price')||0),
      qty: +(fd.get('qty')||1)
    };
    var csrf = getCsrf();
    if(!csrf){ alert('No hay token de seguridad. Recarga la página.'); return; }

    try{
      var res = await fetch(API+'?action=add', {
        method:'POST',
        headers:{ 'Content-Type':'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(payload),
        credentials:'include'
      });
      var text = await res.text();
      var data = null; try{ data = JSON.parse(text); }catch(_){}
      if (!res.ok) { alert('No se pudo agregar. HTTP '+res.status); return; }
      if (data && data.ok) {
        if (typeof data.cart_count === 'number') updateBadge(data.cart_count);
        alert('Producto agregado ✅');
      } else {
        alert('No se pudo agregar: ' + ((data && data.error) || 'Error'));
      }
    }catch(e){ alert('Error de red al agregar.'); }
  }

  document.addEventListener('submit', function(e){
    var f = e.target;
    if (f && f.classList && f.classList.contains('atc')) {
      e.preventDefault();
      addToCart(f);
    }
  });

  // Inicializa badge
  (async function(){
    try{
      var r = await fetch(API+'?action=get', {credentials:'include'});
      var j = await r.json().catch(()=>null);
      if (j && typeof j.cart_count === 'number') updateBadge(j.cart_count);
    }catch(_){}
  })();
})();
</script>

<!-- Popover del carrito -->
<script>
(function(){
  const API = <?= json_encode($API_URL, JSON_UNESCAPED_SLASHES) ?>;
  let pop = document.getElementById('cartPopover');
  if (!pop) {
    pop = document.createElement('div');
    pop.id = 'cartPopover';
    pop.className = 'cart-popover';
    document.body.appendChild(pop);
  }

  function fmtCRC(n){ try{ return '₡'+(n).toLocaleString('es-CR',{maximumFractionDigits:0}); }catch(_){ return '₡'+Math.round(n); } }
  function fmtUSD(n){ try{ return '$'+(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}); }catch(_){ return '$'+Number(n).toFixed(2); } }
  function fmt(n,c){ return (String(c||'CRC').toUpperCase()==='USD') ? fmtUSD(n) : fmtCRC(n); }

  function positionPopover(btn) {
    const r = btn.getBoundingClientRect();
    const gap = 8;
    const top = r.bottom + gap + window.scrollY;
    const left = (r.right - 320) + window.scrollX;
    pop.style.top = top + 'px';
    pop.style.left = Math.max(8, left) + 'px';
  }

  function renderEmpty(){
    pop.innerHTML = `
      <div class="title">Tu carrito</div>
      <div class="empty">Aún no agregas productos.</div>
      <div class="footer">
        <span class="total">Total: ${fmt(0, 'CRC')}</span>
        <a href="cart.php" class="btn">Ver carrito</a>
      </div>
    `;
  }

  function renderPayload(payload){
    const items = Array.isArray(payload?.items) ? payload.items : [];
    if (!items.length) { renderEmpty(); return; }

    const currency = payload.currency || 'CRC';
    const grouped = new Map();
    for (const it of items) {
      const key = String(it.product_id ?? it.id);
      const qty = +it.qty || 0;
      const unit = +it.unit_price || 0;
      const name = it.product_name || it.name || ('Producto #' + key);
      const img = it.product_image_url || null;
      if (!grouped.has(key)) grouped.set(key, {product_id:key, qty:0, unit_price:unit, product_name:name, product_image_url:img, currency});
      const acc = grouped.get(key);
      acc.qty += qty;
      if (unit > 0) acc.unit_price = unit;
      if (img && !acc.product_image_url) acc.product_image_url = img;
      if (name && name!==acc.product_name) acc.product_name = name;
    }
    const list = Array.from(grouped.values());
    const listHTML = list.slice(0,5).map(it=>{
      const line = it.unit_price * it.qty;
      const img = it.product_image_url || 'assets/no-image.png';
      return `
        <div class="item">
          <img class="thumb" src="${img}" alt="">
          <div>
            <div class="name">${it.product_name}</div>
            <div class="meta">x${it.qty}</div>
          </div>
          <div class="price">${fmt(line, currency)}</div>
        </div>
      `;
    }).join('');
    const total = list.reduce((s,it)=> s + (it.unit_price*it.qty), 0);
    pop.innerHTML = `
      <div class="title">Tu carrito</div>
      <div class="list">${listHTML}</div>
      <div class="footer">
        <span class="total">Total: ${fmt(total, currency)}</span>
        <a href="cart.php" class="btn primary">Ver carrito</a>
      </div>
    `;
  }

  async function openPopover(btn){
    try{
      const r = await fetch(API+'?action=get', {credentials:'include'});
      const j = await r.json().catch(()=>null);
      renderPayload(j||{});
    }catch(_){
      renderEmpty();
    }
    positionPopover(btn);
    pop.classList.add('open');
  }
  function closePopover(){ pop.classList.remove('open'); }

  const btn = document.getElementById('cartButton');
  if (!btn) return;

  let hoverTimer = null;
  btn.addEventListener('click', function(e){
    if (e.metaKey || e.ctrlKey) return;
    e.preventDefault();
    if (pop.classList.contains('open')) closePopover(); else openPopover(btn);
  });
  btn.addEventListener('mouseenter', ()=>{ clearTimeout(hoverTimer); openPopover(btn); });
  btn.addEventListener('mouseleave', ()=>{ hoverTimer = setTimeout(closePopover, 120); });
  pop.addEventListener('mouseenter', ()=>{ clearTimeout(hoverTimer); });
  pop.addEventListener('mouseleave', ()=>{ hoverTimer = setTimeout(closePopover, 120); });

  document.addEventListener('click', (e)=>{
    if (!pop.classList.contains('open')) return;
    const t = e.target;
    if (t === pop || pop.contains(t) || t === btn || btn.contains(t)) return;
    closePopover();
  });
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closePopover(); });

  // Exponer helpers por si los necesitas
  window.__cart_isPopoverOpen = function(){ return pop.classList.contains('open'); };
  window.__cart_renderPopover  = function(payload){ renderPayload(payload || {}); };
})();
</script>
</body>
</html>