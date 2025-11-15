<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0700, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) {
    ini_set('session.save_path', $__sessPath);
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

$__isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PHPSESSID');
    
    if (PHP_VERSION_ID < 70300) {
        ini_set('session.cookie_lifetime', '0');
        ini_set('session.cookie_path', '/');
        ini_set('session.cookie_domain', '');
        ini_set('session.cookie_secure', $__isHttps ? '1' : '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_set_cookie_params(0, '/', '', $__isHttps, true);
    } else {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $__isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    
    ini_set('session.use_strict_mode', '0');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '86400');
    session_start();
}

if (($_GET['__log'] ?? '') === 'popover' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $file = $dir . '/popoverINDEX';
    $payload = file_get_contents('php://input') ?: '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] ip=$ip ua=" . str_replace(["\n","\r"], ' ', $ua) . " payload=" . str_replace(["\n","\r"], ' ', $payload) . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok'=>true]);
    exit;
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cantidadProductos = 0;
foreach ($_SESSION['cart'] as $it) { $cantidadProductos += (int)($it['qty'] ?? 0); }

if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
ini_set('default_charset', 'UTF-8');

$token = $_COOKIE['vg_csrf'] ?? bin2hex(random_bytes(32));
$isHttps = $__isHttps;
if (PHP_VERSION_ID < 70300) {
    setcookie('vg_csrf', $token, time()+7200, '/', '', $isHttps, false);
} else {
    setcookie('vg_csrf', $token, [
      'expires'  => time()+7200,
      'path'     => '/',
      'domain'   => '',
      'secure'   => $isHttps,
      'httponly' => false,
      'samesite' => 'Lax'
    ]);
}

$pdo = db();
$sales = $pdo->query("
  SELECT s.*, a.name AS affiliate_name
  FROM sales s
  JOIN affiliates a ON a.id = s.affiliate_id
  WHERE s.is_active = 1
  ORDER BY datetime(s.start_at) ASC
")->fetchAll(PDO::FETCH_ASSOC);

function same_date($tsA, $tsB) {
  return date('Y-m-d', $tsA) === date('Y-m-d', $tsB);
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo APP_NAME; ?> — Marketplace</title>
  <link rel="stylesheet" href="assets/style.css?v=20251025">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial;margin:0;background:#f9fafb;color:#111}
    .header{display:flex;align-items:center;justify-content:space-between;padding:16px 24px;background:#fff;border-bottom:1px solid #e5e7eb;box-shadow:0 1px 3px rgba(0,0,0,0.05);flex-wrap:wrap;gap:12px}
    .logo{font-size:1.2rem;font-weight:700;color:#111}
    nav{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
    .btn{display:inline-flex;align-items:center;gap:8px;border:1px solid #d1d5db;border-radius:10px;padding:10px 16px;text-decoration:none;color:#374151;background:#fff;cursor:pointer;font-size:0.95rem;font-weight:500;transition:all 0.2s}
    .btn:hover{background:#f9fafb;border-color:#9ca3af}
    .btn.primary{background:#0ea5e9;border-color:#0ea5e9;color:#fff}
    .btn.primary:hover{background:#0284c7}
    .container{max-width:1200px;margin:0 auto;padding:24px}
    h1{font-size:2rem;margin:0 0 8px 0}
    .small{font-size:0.95rem;color:#6b7280;margin:0 0 24px 0}
    .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:24px}
    @media (max-width:640px){.grid{grid-template-columns:1fr}}
    .card{border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,0.08);transition:transform 0.2s,box-shadow 0.2s}
    .card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.12)}
    .imgbox{position:relative;width:100%;height:200px;overflow:hidden;background:#f3f4f6}
    .sale-img{width:100%;height:100%;object-fit:cover}
    .badges-row{display:flex;gap:8px;padding:12px;flex-wrap:wrap}
    .badge{display:inline-flex;align-items:center;padding:4px 10px;border-radius:6px;font-size:0.85rem;font-weight:600}
    .chip{display:inline-flex;padding:4px 10px;border-radius:6px;font-size:0.85rem;font-weight:600}
    .chip-red{background:#fee2e2;color:#991b1b}
    .chip-orange{background:#fed7aa;color:#9a3412}
    .chip-blue{background:#dbeafe;color:#1e40af}
    .card h3{font-size:1.2rem;margin:0 12px 8px 12px;color:#111}
    .card p{margin:0 12px 12px 12px;font-size:0.9rem;color:#6b7280;line-height:1.5}
    .actions{padding:12px;display:flex;gap:8px}
    .site-footer{background:#111;color:#fff;padding:24px;margin-top:48px}
    .site-footer .inner{max-width:1200px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px}
    .footer-links{display:flex;gap:16px}
    .footer-links a{color:#9ca3af;text-decoration:none;font-size:0.9rem}
    .footer-links a:hover{color:#fff}
    .cart-popover{position:absolute;width:340px;background:#fff;border:1px solid #d1d5db;border-radius:14px;box-shadow:0 10px 40px rgba(0,0,0,0.15);padding:16px;display:none;z-index:9999;max-height:500px;overflow:auto}
    .cart-popover.open{display:block}
    .cart-popover .title{font-size:1.1rem;font-weight:700;margin-bottom:12px;color:#111}
    .cart-popover .list{display:flex;flex-direction:column;gap:12px;margin-bottom:12px}
    .cart-popover .item{display:flex;gap:10px;align-items:center;padding:8px;background:#f9fafb;border-radius:10px}
    .cart-popover .thumb{width:50px;height:50px;object-fit:cover;border-radius:8px;background:#e5e7eb}
    .cart-popover .item-info{flex:1}
    .cart-popover .name{font-size:0.95rem;font-weight:600;color:#111;margin-bottom:2px}
    .cart-popover .meta{font-size:0.85rem;color:#6b7280}
    .cart-popover .price{font-weight:700;color:#059669;white-space:nowrap}
    .cart-popover .empty{padding:20px;text-align:center;color:#6b7280;font-size:0.95rem}
    .cart-popover .footer{display:flex;justify-content:space-between;align-items:center;padding-top:12px;border-top:1px solid #e5e7eb}
    .cart-popover .total{font-weight:700;font-size:1.1rem;color:#111}
    .cart-link{position:relative}
    .cart-badge{position:absolute;top:-8px;right:-8px;background:#dc2626;color:#fff;border-radius:999px;padding:3px 8px;font-size:0.75rem;font-weight:700;box-shadow:0 2px 4px rgba(0,0,0,0.2)}
  </style>
</head>
<body>

<header class="header">
  <div class="logo">🛍️ <?php echo APP_NAME; ?></div>
  <nav>
    <a class="btn" href="affiliate/login.php">Afiliados</a>
    <a class="btn primary" href="affiliate/register.php">Publicar mi venta</a>
    <a id="cartButton" class="btn cart-link" href="cart.php" data-cart-link>
      🛒 Carrito 
      <span id="cartBadge" class="cart-badge" style="display:none"><?php echo (int)$cantidadProductos; ?></span>
    </a>
    <a class="btn" href="admin/login.php">Backoffice</a>
  </nav>
</header>

<div class="container">
  <h1>Espacios de venta</h1>
  <p class="small">Descubre ventas de garaje activas y próximas cerca de ti.</p>

  <div class="grid">
    <?php
    $nowTs = time();
    foreach ($sales as $s):
      $st  = strtotime($s['start_at']);
      $en  = strtotime($s['end_at']);

      $state = 'Próxima';
      $color = '#2563eb';
      if ($nowTs >= $st && $nowTs <= $en) {
        $state = 'En vivo';
        $color = '#059669';
      } elseif ($nowTs > $en) {
        $state = 'Finalizada';
        $color = '#6b7280';
      }

      $secondary = null; $secClass = '';
      if ($state === 'En vivo' && same_date($en, $nowTs)) {
        $secondary = 'Último día'; $secClass = 'chip chip-red';
      } elseif (same_date($st, $nowTs)) {
        $secondary = 'Hoy';        $secClass = 'chip chip-orange';
      } elseif ($st >= strtotime('-2 days', $nowTs)) {
        $secondary = 'Nuevo';      $secClass = 'chip chip-blue';
      }

      $img = $s['cover_image'] ? 'uploads/affiliates/' . htmlspecialchars($s['cover_image']) : 'assets/placeholder.jpg';
      $img2 = !empty($s['cover_image2']) ? 'uploads/affiliates/' . htmlspecialchars($s['cover_image2']) : null;
      $imgs = $img2 ? [$img, $img2] : [$img];
    ?>
      <div class="card">
        <div class="imgbox">
          <img class="sale-img" data-images='<?php echo json_encode($imgs, JSON_UNESCAPED_SLASHES); ?>' src="<?php echo $imgs[0]; ?>" alt="Portada de <?php echo htmlspecialchars($s['title']); ?>">
        </div>

        <div class="badges-row">
          <span class="badge" style="background:<?php echo $color; ?>;color:#fff">
            <?php echo $state; ?>
          </span>
          <?php if ($secondary): ?>
            <span class="<?php echo $secClass; ?>"><?php echo $secondary; ?></span>
          <?php endif; ?>
        </div>

        <h3><?php echo htmlspecialchars($s['title']); ?></h3>
        <p>
          <?php echo htmlspecialchars($s['affiliate_name']); ?><br>
          <?php echo date('d/m/Y H:i', $st); ?> — <?php echo date('d/m/Y H:i', $en); ?>
        </p>

        <div class="actions">
          <?php if ($state === 'En vivo'): ?>
            <a class="btn primary" href="store.php?sale_id=<?php echo (int)$s['id']; ?>" style="flex:1;justify-content:center">Entrar</a>
          <?php elseif ($state === 'Próxima'): ?>
            <span class="btn" style="flex:1;justify-content:center;opacity:0.6;cursor:not-allowed">Aún no inicia</span>
          <?php else: ?>
            <span class="btn" style="flex:1;justify-content:center;opacity:0.7;cursor:not-allowed">Finalizada</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if (empty($sales)): ?>
      <div class="card">
        <p style="padding:20px">Aún no hay espacios activos.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<footer class="site-footer">
  <div class="inner">
    <div>© <?php echo date('Y'); ?> <?php echo APP_NAME; ?> — Todos los derechos reservados.</div>
    <div class="footer-links">
      <a href="mailto:<?php echo defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'info@compratica.com'; ?>">Contacto</a>
      <a href="affiliate/login.php">Ser afiliado</a>
      <a href="admin/login.php">Administrador</a>
    </div>
  </div>
</footer>

<script>
(function(){
  var nodes = document.querySelectorAll('.sale-img[data-images]');
  nodes.forEach(function(img){
    try {
      var arr = JSON.parse(img.getAttribute('data-images')||'[]');
      if (!Array.isArray(arr) || arr.length < 2) return;
      var i = 0;
      setInterval(function(){
        i = (i + 1) % arr.length;
        img.src = arr[i];
      }, 3500);
    } catch(e){}
  });
})();
</script>

<script>
(function(){
  const API = '/api/cart.php';

  function updateBadge(count){
    const val = Number(count)||0;
    const el = document.getElementById('cartBadge');
    if (!el) return;
    el.textContent = val;
    el.style.display = val>0 ? 'inline-block' : 'none';
  }
  function computeCount(payload){
    if (!payload || typeof payload !== 'object') return 0;
    if (typeof payload.cart_count === 'number') return payload.cart_count;
    if (Array.isArray(payload.items)) return payload.items.reduce((n,it)=> n + Number(it?.qty||0), 0);
    if (Array.isArray(payload.groups)) {
      let n = 0; for (const g of payload.groups) for (const it of (g.items||[])) n += Number(it?.qty||0); return n;
    }
    return 0;
  }
  (async function initBadge(){
    try{
      const r = await fetch(API+'?action=get', {credentials:'include', cache:'no-store'});
      const j = await r.json().catch(()=>null);
      updateBadge(computeCount(j));
    }catch(_){}
  })();
})();
</script>

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
    const left = (r.right - 340) + window.scrollX;
    pop.style.position = 'absolute';
    pop.style.top = top + 'px';
    pop.style.left = Math.max(8, left) + 'px';
  }

  function renderEmpty(){
    pop.innerHTML = `
      <div class="title">Tu carrito</div>
      <div class="empty">Aún no agregas productos.</div>
      <div class="footer">
        <span class="total">Total: ${fmt(0, 'CRC')}</span>
        <a href="cart.php" class="btn primary">Ver carrito</a>
      </div>
    `;
  }

  function renderPayload(payload){
    const items = Array.isArray(payload?.items) ? payload.items : [];
    if (!items.length) { renderEmpty(); return; }

    const currency = (items[0] && items[0].currency) ? items[0].currency : (payload.currency || 'CRC');

    const map = new Map();
    for (const it of items) {
      const key = String(it.product_id ?? it.id);
      const qty = +it.qty || 0;
      const unit = +it.unit_price || 0;
      const name = it.product_name || ('Producto #' + key);
      const img = it.product_image_url || null;
      if (!map.has(key)) map.set(key, {product_id:key, qty:0, unit_price:unit, product_name:name, product_image_url:img, currency});
      const acc = map.get(key);
      acc.qty += qty;
      if (unit > 0) acc.unit_price = unit;
      if (img && !acc.product_image_url) acc.product_image_url = img;
      if (name && name!==acc.product_name) acc.product_name = name;
    }
    const list = Array.from(map.values());

    const listHTML = list.slice(0,5).map(it=>{
      const line = it.unit_price * it.qty;
      const img = it.product_image_url || 'assets/no-image.png';
      return `
        <div class="item">
          <img class="thumb" src="${img}" alt="">
          <div class="item-info">
            <div class="name">${it.product_name}</div>
            <div class="meta">Cantidad: ${it.qty}</div>
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
    try {
      navigator.sendBeacon
        ? navigator.sendBeacon('/index.php?__log=popover', new Blob([JSON.stringify({event:'open_attempt', t:Date.now()})], {type:'application/json'}))
        : fetch('/index.php?__log=popover', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({event:'open_attempt', t:Date.now()})});
    } catch(_){}

    try{
      const r = await fetch(API+'?action=get', {credentials:'include', cache:'no-store'});
      const text = await r.text();
      let j = null; try{ j = JSON.parse(text); }catch(_){}
      try{
        const payload = {event:'api_response', status: r.status, ok: r.ok, preview: text.slice(0,200) };
        navigator.sendBeacon
          ? navigator.sendBeacon('/index.php?__log=popover', new Blob([JSON.stringify(payload)], {type:'application/json'}))
          : fetch('/index.php?__log=popover', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)});
      }catch(_){}

      if (j) renderPayload(j); else renderEmpty();
    }catch(e){
      try{
        navigator.sendBeacon
          ? navigator.sendBeacon('/index.php?__log=popover', new Blob([JSON.stringify({event:'fetch_error', msg:String(e)})], {type:'application/json'}))
          : fetch('/index.php?__log=popover', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({event:'fetch_error', msg:String(e)})});
      }catch(_){}
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
  btn.addEventListener('mouseleave', ()=>{ hoverTimer = setTimeout(closePopover, 150); });
  pop.addEventListener('mouseenter', ()=>{ clearTimeout(hoverTimer); });
  pop.addEventListener('mouseleave', ()=>{ hoverTimer = setTimeout(closePopover, 150); });

  document.addEventListener('click', (e)=>{
    if (!pop.classList.contains('open')) return;
    const t = e.target;
    if (t === pop || pop.contains(t) || t === btn || btn.contains(t)) return;
    closePopover();
  });
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closePopover(); });
})();
</script>

</body>
</html>