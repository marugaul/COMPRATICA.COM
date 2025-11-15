(function(){
  // ====== Util de log hacia /cart.php (ya lo tienes implementado) ======
  function vgLog(level, message, context){
    try{
      fetch('/cart.php?clientlog=1', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({ level, message, context })
      });
    }catch(_){}
  }

  // ====== Selectores de elementos clicables que deberían llevar a /cart.php ======
  // No necesitas cambiar tu HTML: el script detecta cualquiera de estos patrones.
  var SELECTORS = [
    '[data-cart-link]',      // recomendado
    'a[href="/cart.php"]',
    'a[href="cart.php"]',
    '#btnCart',              // id frecuente
    '.btn-cart',             // clase frecuente
    '.cart-link',            // clase frecuente
    '[data-open="cart"]',    // algunos templates
    '[data-action="open-cart"]'
  ].join(',');

  // ====== Cerrar popovers/sidebars comunes antes de navegar (mejor UX) ======
  function closeCartUIs(){
    var nodes = document.querySelectorAll(
      '.cart-popover, [data-cart-popover], ' +  // popover típico
      '.cart-sidebar, [data-cart-sidebar], ' +  // sidebar tipo drawer
      '.mini-cart, .mini-cart--open'            // variantes
    );
    nodes.forEach(function(n){
      n.classList.remove('open','show','is-open','active');
      n.setAttribute('aria-hidden','true');
      // si es sidebar desplazable
      if (n.style) n.style.display = 'none';
    });

    // backdrops/overlays que suelen bloquear clicks
    var overlays = document.querySelectorAll('.cart-overlay, .popover-backdrop, .modal-backdrop, [data-overlay]');
    overlays.forEach(function(ov){
      ov.classList.add('vg-cart-overlay-off');
      ov.style.pointerEvents = 'none';
    });
  }

  // ====== Forzar navegación (captura en fase de captura para ganar prioridad) ======
  document.addEventListener('click', function onClick(e){
    var el = e.target.closest(SELECTORS);
    if (!el) return;

    // Href destino (preferimos /cart.php)
    var href = el.getAttribute('href') || el.dataset.href || '/cart.php';

    // Bloquear cualquier handler que intercepte el click
    e.stopPropagation();
    e.preventDefault();

    // Cierra UI superpuestas
    try { closeCartUIs(); } catch(_){}

    // Log útil para debug
    vgLog('info', 'nav_to_cart_click', {
      href: href,
      from: location.pathname + location.search,
      targetTag: el.tagName,
      classes: el.className || ''
    });

    // Navega sin depender de otros scripts
    window.location.assign(href);
  }, true);

  // ====== Defensa extra: si alguien reabre overlay por CSS/JS, lo anulamos ======
  var style = document.createElement('style');
  style.textContent = `
    .vg-cart-overlay-off { pointer-events: none !important; }
    .cart-popover[aria-hidden="true"],
    .mini-cart:not(.is-open),
    [data-cart-popover][aria-hidden="true"] { pointer-events: none !important; }
  `;
  document.documentElement.appendChild(style);
})();
