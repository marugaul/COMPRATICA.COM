<?php
session_start();
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cantidadProductos = count($_SESSION['cart']);
?>
<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

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
  <link rel="stylesheet" href="assets/style.css?v=20251018-popover">
</head>
<body>

<!-- ========================= HEADER ========================= -->
<header class="header">
  <div class="logo">🛍️ <?php echo APP_NAME; ?> — Marketplace</div>
  <nav>
    <a class="btn" href="affiliate/login.php">Afiliados</a>
    <a class="btn primary" href="affiliate/register.php">Publicar mi venta</a>
    <a id="cartButton" class="btn" href="cart.php" data-cart-link style="position:relative">
      🛒 Carrito <span id="cartBadge" class="badge"><?php echo $cantidadProductos; ?></span>
    </a>
  </nav>
</header>

<!-- ========================= MAIN ========================= -->
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
          <span class="badge" style="background:<?php echo $color; ?>;color:#fff;border:none;position:static;display:inline-flex">
            <?php echo $state; ?>
          </span>
          <?php if ($secondary): ?>
            <span class="<?php echo $secClass; ?>"><?php echo $secondary; ?></span>
          <?php endif; ?>
        </div>

        <h3><?php echo htmlspecialchars($s['title']); ?></h3>
        <p class="small">
          <?php echo htmlspecialchars($s['affiliate_name']); ?><br>
          <?php echo date('d/m/Y H:i', $st); ?> — <?php echo date('d/m/Y H:i', $en); ?>
        </p>

        <div class="actions">
          <?php if ($state === 'En vivo'): ?>
            <a class="btn primary" href="store.php?sale_id=<?php echo (int)$s['id']; ?>">Entrar</a>
          <?php elseif ($state === 'Próxima'): ?>
            <span class="btn" style="opacity:0.6;cursor:not-allowed">Aún no inicia</span>
          <?php else: ?>
            <span class="btn" style="opacity:0.7;cursor:not-allowed">Finalizada</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if (empty($sales)): ?>
      <div class="card">
        <p class="small">Aún no hay espacios activos.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ========================= FOOTER ========================= -->
<footer class="site-footer">
  <div class="inner">
    <div>© <?php echo date('Y'); ?> <?php echo APP_NAME; ?> — Todos los derechos reservados.</div>
    <div class="footer-links">
      <a href="mailto:<?php echo ADMIN_EMAIL; ?>">Contacto</a>
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

<!-- Contador de carrito (badge) -->
<script>
(async function(){
  const API = '/api/cart.php';

  // Suma qty desde distintos formatos de respuesta
  function computeCount(payload){
    if (!payload || typeof payload !== 'object') return 0;
    if (typeof payload.cart_count === 'number') return payload.cart_count;
    if (typeof payload.count === 'number') return payload.count;

    // groups[].items[] { qty }
    if (Array.isArray(payload.groups)) {
      let c = 0;
      for (const g of payload.groups) {
        if (!g || !Array.isArray(g.items)) continue;
        for (const it of g.items) c += Number(it?.qty || 0);
      }
      if (c > 0) return c;
    }

    // items[] { qty }
    if (Array.isArray(payload.items)) {
      let c = 0;
      for (const it of payload.items) c += Number(it?.qty || 0);
      if (c > 0) return c;
    }
    return 0;
  }

  // Actualiza cualquier badge (#cartBadge | [data-cart-badge] | .js-cart-count)
  function updateBadge(count){
    const val = Number(count) || 0;
    const nodes = document.querySelectorAll('#cartBadge,[data-cart-badge],.js-cart-count');
    nodes.forEach(el=>{
      el.textContent = val;
      const show = val > 0;
      el.style.display = show ? 'inline-block' : 'none';
      if (el.classList) {
        if (show) el.classList.remove('d-none');
        else el.classList.add('d-none');
      }
    });
  }

  try {
    const r = await fetch(API+'?action=get', { credentials:'include' });
    const j = await r.json().catch(()=>null);
    updateBadge(computeCount(j));
  } catch(e){
    // silencioso
  }
})();
</script>

<!-- Popover del carrito -->
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

  // Soporta tanto payload.groups[].items[] como payload.items[], y agrupa por product_id
  function buildGroupedItems(payload){
    const rawItems = [];
    if (payload && Array.isArray(payload.groups)) {
      payload.groups.forEach(g => (g.items||[]).forEach(it => {
        rawItems.push({
          product_id: it.product_id ?? it.id,
          product_name: it.product_name ?? it.name ?? ('Producto #' + (it.product_id ?? it.id)),
          unit_price: +it.unit_price || 0,
          qty: +it.qty || 0,
          currency: (g && g.currency) || payload.currency || 'CRC',
          product_image_url: it.product_image_url || null
        });
      }));
    } else if (payload && Array.isArray(payload.items)) {
      (payload.items||[]).forEach(it=>{
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

    const map = new Map();
    for (const it of rawItems) {
      const k = String(it.product_id);
      if (!map.has(k)) map.set(k, { ...it });
      else {
        const acc = map.get(k);
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

    if (!grouped.length) {
      pop.innerHTML = `
        <div class="title">Tu carrito</div>
        <div class="empty">Aún no agregas productos.</div>
        <div class="footer">
          <span class="total">Total: ${fmt(0, currency)}</span>
          <a href="cart.php" class="btn" data-cart-link>Ver carrito</a>
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
        <a href="cart.php" class="btn primary" data-cart-link>Ver carrito</a>
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

  // Helpers globales por si querés refrescar desde otros scripts
  window.__cart_isPopoverOpen = function(){ return pop.classList.contains('open'); };
  window.__cart_renderPopover  = function(payload){ renderPopoverPayload(payload || {}); };
})();
</script>

<!-- FIX global de navegación -->
<link rel="stylesheet" href="/assets/css/cart_nav_fix.css">
<script src="/assets/js/cart_nav_fix.js"></script>

</body>
</html>
