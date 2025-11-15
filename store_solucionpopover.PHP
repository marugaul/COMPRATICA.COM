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
  @file_put_contents($logDir . '/error_cart.log', $line, FILE_APPEND | LOCK_EX);
}

/* ====== GARANTIZAR SESIÓN Y COOKIE CSRF (parche mínimo) ====== */
if (session_status() !== PHP_SESSION_ACTIVE) {
  // 'Lax' si todo es mismo sitio; usa 'None' + HTTPS si son subdominios/embeds cross-site
  ini_set('session.cookie_samesite', 'Lax');
  if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) {
    ini_set('session.cookie_secure', '1');
  }
  session_start();
}
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // por si config.php no lo generó
}
if (empty($_COOKIE['vg_csrf'])) {
  $token   = $_SESSION['csrf_token'];
  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
  $host    = $_SERVER['HTTP_HOST'] ?? '';
  // Si usas varios subdominios, puedes forzar aquí '.tudominio.com'
  $cookieDomain = $host;

  setcookie('vg_csrf', $token, [
    'expires'  => time() + 7200, // 2 horas
    'path'     => '/',
    'domain'   => $cookieDomain,
    'secure'   => $isHttps,
    'httponly' => false, // debe ser legible por JS para double-submit cookie
    'samesite' => 'Lax', // usa 'None' si cross-site real + HTTPS
  ]);
}
/* ====== /parche ====== */

$pdo = db();
$sale_id = (int)($_GET['sale_id'] ?? 0);

/* ====== Resolver sale_id si no vino en la URL ====== */
if ($sale_id < 1) {
  $pid = (int)($_GET['product_id'] ?? ($_GET['id'] ?? 0));
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
      log_error_cart("Error resolviendo sale_id desde product_id=$pid: ".$e->getMessage());
    }
  }
}
if ($sale_id < 1) {
  try {
    $nowUTC = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $stOne = $pdo->prepare("
      SELECT id FROM sales
      WHERE (start_at IS NULL OR start_at <= ?)
        AND (end_at   IS NULL OR end_at   >= ?)
      ORDER BY created_at DESC
      LIMIT 2
    ");
    $stOne->execute([$nowUTC, $nowUTC]);
    $rows = $stOne->fetchAll(PDO::FETCH_COLUMN);
    if (count($rows) === 1) {
      $sale_id = (int)$rows[0];
      log_error_cart("Auto-resuelto sale_id único activo: $sale_id");
    }
  } catch (Throwable $e) {
    log_error_cart("Error resolviendo sale_id activo: ".$e->getMessage());
  }
}

/* ==== Si aún no hay sale_id, 404 ==== */
if ($sale_id < 1) {
  http_response_code(404); 
  log_error_cart("Espacio no encontrado (sale_id=0)");
  die('Espacio no encontrado.'); 
}

/* ====== Cargar venta ====== */
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

/* ====== Productos ====== */
$ps = $pdo->prepare("SELECT * FROM products WHERE sale_id=? AND active=1 ORDER BY created_at DESC LIMIT 200");
$ps->execute([$sale_id]);
$products = $ps->fetchAll(PDO::FETCH_ASSOC);

/* ====== Estado de fechas ====== */
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
  <link rel="stylesheet" href="assets/style.css?v=20251018-popover">
  <style>
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
  </style>
</head>
<body>
<header class="header">
  <div class="logo"><a href="index.php" style="text-decoration:none;color:inherit;">🛍️ <?= APP_NAME ?></a></div>
  <nav>
    <a class="btn" href="index.php">Inicio</a>
    
    <a href="/cart.php" data-cart-link onclick="try{navigator.sendBeacon('/cart.php?clientlog=1', new Blob([JSON.stringify({level:'CLIENT',message:'click_to_cart',from:location.pathname+location.search})],{type:'application/json'}));}catch(e){}">
  🛒 Carrito
</a>

    <a id="cartButton" class="btn" href="cart.php" style="position:relative">
      🛒 Carrito <span id="cartBadge" class="badge">0</span>
    </a>
    
    
    
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
          <?php if ((strtoupper($p['currency'] ?? 'CRC')) === 'USD'): ?>
            $<?= number_format((float)$p['price'], 2, '.', ',') ?>
          <?php else: ?>
            ₡<?= number_format((float)$p['price'], 0, ',', '.') ?>
          <?php endif; ?>
          &nbsp;•&nbsp; Stock: <?= (int)$p['stock'] ?>
        </div>

        <?php if ((int)$p['stock'] > 0): ?>
          <form class="atc" method="post" action="#">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="unit_price" value="<?= number_format((float)$p['price'], 2, '.', '') ?>">
            <label>Cantidad
              <input type="number" name="qty" value="1" min="1" max="<?= (int)$p['stock'] ?>" required>
            </label>
            <button class="btn" type="submit" <?= ($not_started || $finished) ? 'disabled' : ''; ?>>Agregar al carrito</button>
          </form>

          <form method="get" action="checkout.php" class="actions">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="sale_id" value="<?= (int)$sale_id ?>">
            <input type="hidden" name="affiliate_id" value="<?= (int)$sale['aff_id'] ?>">
            <button class="btn primary" type="submit" <?= ($not_started || $finished) ? 'disabled' : ''; ?>>Comprar</button>
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
    var imgs=[];
    try{ imgs = JSON.parse(root.dataset.images)||[]; }catch(e){}
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

<!-- Add-to-Cart: listeners delegados + badge -->
<script>
(function(){
  var API = '/api/cart.php';

  function getCsrf(){
    var m = document.cookie.match(/(?:^|; )vg_csrf=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
  }

  // ====== CAMBIO: contador unificado y refresco de popover ======
  function updateBadge(count){
    var val = Number(count) || 0;
    var nodes = document.querySelectorAll('#cartBadge,[data-cart-badge],.js-cart-count');
    nodes.forEach(function(el){
      el.textContent = val;
      var show = val > 0;
      el.style.display = show ? 'inline-block' : 'none';
      if (el.classList) {
        if (show) el.classList.remove('d-none');
        else el.classList.add('d-none');
      }
    });
  }

  async function refreshCartUI(){
    try{
      const r = await fetch(API+'?action=get', {credentials:'include'});
      const j = await r.json().catch(()=>null);
      if (j && (typeof j.cart_count === 'number' || typeof j.count === 'number')) {
        updateBadge(j.cart_count ?? j.count);
      }
      // Si el popover está abierto, repíntalo
      if (window.__cart_isPopoverOpen && window.__cart_isPopoverOpen()) {
        if (window.__cart_renderPopover) {
          window.__cart_renderPopover(j || {});
        }
      }
    }catch(_){}
  }
  // ====== /cambios ======

  async function logApi(msg, extra){
    try{
      await fetch(API+'?action=log_error',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({msg,extra})});
    }catch(_){}
  }

  async function addToCart(form){
    var fd = new FormData(form);
    var payload = {
      product_id: +(fd.get('product_id')||0),
      unit_price: +(fd.get('unit_price')||0),
      qty: +(fd.get('qty')||1)
    };

    // token SOLO desde cookie (double-submit)
    var csrfToken = getCsrf();

    try{
      var res = await fetch(API+'?action=add', {
        method:'POST',
        headers:{ 
          'Content-Type':'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': csrfToken 
        },
        body: JSON.stringify(payload),
        credentials: 'include'
      });
      if(!res.ok){
        var txt=''; try{txt=await res.text();}catch(_){}
        await logApi('AddToCart HTTP '+res.status, {payload, body:txt});
        alert('❌ No se pudo agregar (HTTP '+res.status+').');
        return;
      }
      var data=null; try{ data=await res.json(); }catch(_){}
      if(data && data.ok){
        if(typeof data.cart_count==='number' || typeof data.count==='number') {
          updateBadge(data.cart_count ?? data.count);
        }
        // ====== NUEVO: refrescar estado real (y popover si está abierto)
        refreshCartUI();
        // ======
        alert('✅ Producto agregado al carrito');
      }else{
        await logApi('AddToCart respuesta no OK', {payload, data});
        alert('⚠️ No se pudo agregar.');
      }
    }catch(err){
      await logApi('AddToCart error JS', {payload, message: (err && err.message)||'desconocido'});
      alert('❌ Error de red al agregar.');
    }
  }

  document.addEventListener('submit', function(e){
    var f = e.target;
    if (f && f.classList && f.classList.contains('atc')) {
      e.preventDefault();
      e.stopPropagation();
      addToCart(f);
      return false;
    }
  }, true);

  (async function(){
    try{
      var r = await fetch(API+'?action=get', {credentials:'include'});
      var j = null; try{ j = await r.json(); }catch(_){}
      if (j && typeof j.cart_count === 'number') updateBadge(j.cart_count);
      else if (j && typeof j.count === 'number') updateBadge(j.count);
    }catch(_){}
  })();
})();
</script>

<!-- Popover del carrito (agrupa por producto) -->
<script>
(function(){
  const API = '/api/cart.php';
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

  // AGRUPAR POR PRODUCTO aunque el API devuelva múltiples líneas
  function buildGroupedItems(payload){
    // Soportar dos formatos: payload.groups[].items[] o payload.items[]
    const rawItems = [];
    if (payload && Array.isArray(payload.groups)) {
      payload.groups.forEach(g => {
        (g.items || []).forEach(it => {
          rawItems.push({
            product_id: it.product_id ?? it.id,
            product_name: it.product_name ?? it.name ?? ('Producto #' + (it.product_id ?? it.id)),
            unit_price: +it.unit_price || 0,
            qty: +it.qty || 0,
            currency: (g && g.currency) || payload.currency || 'CRC',
            product_image_url: it.product_image_url || null
          });
        });
      });
    } else if (payload && Array.isArray(payload.items)) {
      (payload.items || []).forEach(it => {
        rawItems.push({
          product_id: it.product_id ?? it.id,
          product_name: it.product_name ?? it.name ?? ('Producto #' + (it.product_id ?? it.id)),
          unit_price: +it.unit_price || 0,
          qty: +it.qty || 0,
          currency: payload.currency || 'CRC',
          product_image_url: it.product_image_url || null
        });
      });
    }

    // Map por product_id
    const map = new Map();
    for (const it of rawItems) {
      const key = String(it.product_id);
      if (!map.has(key)) {
        map.set(key, { ...it });
      } else {
        const acc = map.get(key);
        acc.qty += it.qty;
        if (it.unit_price > 0) acc.unit_price = it.unit_price;
        if (it.product_name && it.product_name !== acc.product_name) acc.product_name = it.product_name;
      }
    }
    return Array.from(map.values());
  }

  function renderPopoverPayload(payload){
    const grouped = buildGroupedItems(payload);
    const currency = (payload && payload.currency) || (grouped[0]?.currency) || 'CRC';

    if (grouped.length === 0) {
      pop.innerHTML = `
        <div class="title">Tu carrito</div>
        <div class="empty">Aún no agregas productos.</div>
        <div class="footer">
          <span class="total">Total: ${fmt(0, currency)}</span>
          <a href="cart.php" class="btn">Ver carrito</a>
        </div>
      `;
      return;
    }

    const preview = grouped.slice(0,5);
    const listHTML = preview.map(it=>{
      const img = it.product_image_url || 'assets/no-image.png';
      const line = (it.unit_price * it.qty);
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

    const grand = grouped.reduce((s,it)=> s + (it.unit_price * it.qty), 0);

    pop.innerHTML = `
      <div class="title">Tu carrito</div>
      <div class="list">${listHTML}</div>
      <div class="footer">
        <span class="total">Total: ${fmt(grand, currency)}</span>
        <a href="cart.php" class="btn primary">Ver carrito</a>
      </div>
    `;
  }

  async function openPopover(btn){
    try{
      const r = await fetch(API+'?action=get', {credentials:'include'});
      const j = await r.json().catch(()=>null);
      renderPopoverPayload(j||{});
    }catch(e){
      pop.innerHTML = '<div class="title">Tu carrito</div><div class="empty">No se pudo cargar.</div>';
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

  // ====== NUEVO: expone helpers globales para refrescar desde AddToCart ======
  window.__cart_isPopoverOpen = function(){ return pop.classList.contains('open'); };
  window.__cart_renderPopover  = function(payload){ renderPopoverPayload(payload || {}); };
  // ======
})();
</script>

</body>
</html>
