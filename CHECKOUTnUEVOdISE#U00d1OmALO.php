<?php
/**
 * checkout.php
 * Versión: Regenerado - Diseño alegre y colorido.
 * Notas: CSS incrustado para forzar estilos.
 */
declare(strict_types=1);
session_start();

function safe($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fmt_crc($n){ return '₡'.number_format((float)$n,0,',','.'); }
function fmt_usd($n){ return '$'.number_format((float)$n,2,'.',','); }
function fmt_any($n,$c){ return strtoupper($c)==='USD' ? fmt_usd($n) : fmt_crc($n); }

/* --- CARGAR GRUPO (misma lógica resumida) --- */
$sale_id = (int)($_GET['sale_id'] ?? 0);
$group = null;
$currency = 'CRC';
$items_grouped = [];

if (isset($_SESSION['cart']['groups']) && is_array($_SESSION['cart']['groups'])) {
  foreach ($_SESSION['cart']['groups'] as $g) {
    if ((int)($g['sale_id'] ?? 0) === $sale_id) { $group = $g; break; }
  }
}

/* fallback simple: si existe cart_payload */
if (!$group && !empty($_SESSION['cart_payload']['groups'])) {
  foreach ($_SESSION['cart_payload']['groups'] as $g) {
    if ((int)($g['sale_id'] ?? 0) === $sale_id) { $group = $g; break; }
  }
}

/* Si tienes DB, puedes usar tu lógica anterior. Aquí asumimos $group está bien formateado. */
if ($group) {
  $currency = $group['currency'] ?? 'CRC';
  $items = $group['items'] ?? [];
  // Agrupar por product_id + precio
  $map = [];
  foreach ($items as $it) {
    $pid = (int)($it['product_id'] ?? 0);
    $name = (string)($it['product_name'] ?? 'Producto');
    $unit = (float)($it['unit_price'] ?? 0);
    $img  = !empty($it['product_image_url']) ? $it['product_image_url'] : 'assets/no-image.png';
    $key = ($pid>0?('ID'.$pid):md5($name)).'|'.number_format($unit,4,'.','');
    if (!isset($map[$key])) {
      $map[$key] = ['product_id'=>$pid,'product_name'=>$name,'product_image_url'=>$img,'unit_price'=>$unit,'qty'=>0,'line_total'=>0];
    }
    $qty = (float)($it['qty'] ?? 1);
    $map[$key]['qty'] += $qty;
    $map[$key]['line_total'] = $map[$key]['qty'] * $map[$key]['unit_price'];
  }
  $items_grouped = array_values($map);
  $totals = $group['totals'] ?? ['count'=>0,'subtotal'=>0,'tax_total'=>0,'grand_total'=>0];
} else {
  $totals = ['count'=>0,'subtotal'=>0,'tax_total'=>0,'grand_total'=>0];
}

$APP = defined('APP_NAME') ? safe(APP_NAME) : 'MiTienda';
$compactId = 'items_' . ($sale_id?: '0');
$is_single = count($items_grouped) === 1;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Checkout — <?= $APP ?></title>
  <style>
    /* ==========================================================================
       CSS INCRUSTADO - Diseño Alegre y Colorido para Checkout
       ========================================================================== */
    :root {
      --primary-color: #6a0dad; /* Morado vibrante */
      --secondary-color: #ff6f61; /* Coral alegre */
      --accent-color: #ffcc5c; /* Amarillo soleado */
      --text-dark: #333;
      --text-light: #fff;
      --bg-light: #f8f9fa;
      --bg-medium: #e9ecef;
      --card-bg: #ffffff;
      --border-color: #dee2e6;
      --shadow-light: 0 4px 12px rgba(0, 0, 0, 0.08);
      --shadow-medium: 0 8px 24px rgba(0, 0, 0, 0.12);
      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 16px;
    }

    body {
      font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      margin: 0;
      background-color: var(--bg-light);
      color: var(--text-dark);
      line-height: 1.6;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    /* Header */
    .header {
      background: linear-gradient(90deg, var(--primary-color) 0%, #8a2be2 100%);
      padding: 15px 25px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: var(--shadow-light);
    }
    .header .logo a {
      color: var(--text-light);
      text-decoration: none;
      font-size: 1.8rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .header .logo a::before {
      content: '✨'; /* Icono alegre */
      font-size: 1.5rem;
    }
    .header .btn {
      background-color: var(--secondary-color);
      color: var(--text-light);
      border: none;
      padding: 10px 20px;
      border-radius: var(--radius-sm);
      text-decoration: none;
      font-weight: 600;
      transition: background-color 0.3s ease, transform 0.2s ease;
    }
    .header .btn:hover {
      background-color: #e05a50;
      transform: translateY(-2px);
    }

    /* Main Container & Layout */
    .container {
      max-width: 1200px;
      margin: 30px auto;
      padding: 0 20px;
    }
    .checkout-layout {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 30px;
      align-items: start;
    }

    /* Page Title */
    .page-title {
      font-size: 2rem;
      color: var(--primary-color);
      margin-bottom: 25px;
      text-align: center;
      font-weight: 800;
    }

    /* Card Styles */
    .card {
      background-color: var(--card-bg);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-light);
      padding: 25px;
      margin-bottom: 20px;
      transition: all 0.3s ease;
    }
    .card:hover {
      box-shadow: var(--shadow-medium);
    }

    /* Space Card (Product List) */
    .space-card-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--border-color);
    }
    .space-card .title {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--primary-color);
    }
    .space-card .actions {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .count-badge {
      background-color: var(--accent-color);
      color: var(--text-dark);
      padding: 5px 12px;
      border-radius: 20px;
      font-weight: 700;
      font-size: 0.9rem;
    }
    .link-btn {
      background: none;
      border: none;
      color: var(--secondary-color);
      font-weight: 600;
      cursor: pointer;
      font-size: 1rem;
      transition: color 0.3s ease;
    }
    .link-btn:hover {
      color: #e05a50;
    }

    /* Items List */
    .items-list {
      display: flex;
      flex-direction: column;
      gap: 15px;
    }
    .items-list.single-item {
      max-width: 700px; /* Ajuste para un solo item */
    }
    .empty {
      color: var(--muted);
      padding: 20px;
      text-align: center;
      font-style: italic;
    }

    /* Item Row */
    .item-row {
      background-color: var(--bg-light);
      border-radius: var(--radius-md);
      padding: 15px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .item-row:hover {
      transform: translateY(-3px);
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    }
    .item-summary {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    .item-thumb {
      width: 70px;
      height: 70px;
      object-fit: cover;
      border-radius: var(--radius-sm);
      border: 2px solid var(--border-color);
      flex-shrink: 0;
    }
    .item-main {
      flex-grow: 1;
    }
    .item-name {
      font-weight: 700;
      font-size: 1.1rem;
      color: var(--text-dark);
    }
    .item-sub {
      color: var(--muted);
      font-size: 0.95rem;
      margin-top: 5px;
    }
    .item-right {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 5px;
    }
    .item-price {
      font-weight: 800;
      font-size: 1.2rem;
      color: var(--secondary-color);
    }
    .detail-toggle {
      background-color: var(--bg-medium);
      color: var(--text-dark);
      border: 1px solid var(--border-color);
      padding: 6px 12px;
      border-radius: var(--radius-sm);
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }
    .detail-toggle:hover {
      background-color: #e0e0e0;
    }

    /* Item Detail (Expanded) */
    .item-detail {
      margin-top: 15px;
      padding-top: 15px;
      border-top: 1px dashed var(--border-color);
    }
    .detail-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
      gap: 15px;
      align-items: center;
    }
    .detail-grid label {
      font-size: 0.85rem;
      color: var(--muted);
      margin-bottom: 5px;
      display: block;
    }
    .detail-grid .muted {
      font-weight: 600;
      color: var(--text-dark);
    }
    .qty-controls {
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .qty-btn {
      background-color: var(--bg-medium);
      border: 1px solid var(--border-color);
      border-radius: var(--radius-sm);
      width: 35px;
      height: 35px;
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--text-dark);
      cursor: pointer;
      transition: background-color 0.3s ease;
    }
    .qty-btn:hover {
      background-color: #d0d0d0;
    }
    .qty-input {
      width: 60px;
      padding: 8px;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-sm);
      text-align: center;
      font-size: 1rem;
    }
    .detail-actions {
      grid-column: 1 / -1;
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 10px;
    }
    .update-line, .remove-line {
      padding: 8px 15px;
      border-radius: var(--radius-sm);
      font-weight: 600;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }
    .update-line {
      background-color: var(--primary-color);
      color: var(--text-light);
      border: none;
    }
    .update-line:hover {
      background-color: #5a0a9e;
    }
    .remove-line {
      background: none;
      border: 1px solid var(--border-color);
      color: var(--text-dark);
    }
    .remove-line:hover {
      background-color: #f0f0f0;
    }

    /* Checkout Form */
    .checkout-form .section-title {
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--primary-color);
      margin-bottom: 15px;
    }
    .form {
      display: grid;
      gap: 15px;
    }
    .form label {
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 5px;
      display: block;
    }
    .form .input {
      width: 100%;
      padding: 12px;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-sm);
      font-size: 1rem;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    .form .input:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(106, 13, 173, 0.2);
      outline: none;
    }
    .row-2 {
      display: flex;
      gap: 15px;
      margin-top: 20px;
    }
    .row-2 .btn {
      flex: 1;
      padding: 12px 20px;
      border-radius: var(--radius-sm);
      font-size: 1.1rem;
      font-weight: 700;
      text-align: center;
      text-decoration: none;
      transition: background-color 0.3s ease, transform 0.2s ease;
    }
    .row-2 .btn.primary {
      background-color: var(--primary-color);
      color: var(--text-light);
      border: none;
    }
    .row-2 .btn.primary:hover {
      background-color: #5a0a9e;
      transform: translateY(-2px);
    }
    .row-2 .btn:not(.primary) {
      background-color: var(--bg-medium);
      color: var(--text-dark);
      border: 1px solid var(--border-color);
    }
    .row-2 .btn:not(.primary):hover {
      background-color: #d0d0d0;
      transform: translateY(-2px);
    }

    /* Right Column (Sidebar) */
    .right-col {
      position: sticky;
      top: 30px; /* Ajusta según la altura de tu header */
    }
    .summary-card .summary-head, .info-card .info-head {
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--primary-color);
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--border-color);
    }
    .summary-body {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .summary-line {
      display: flex;
      justify-content: space-between;
      font-size: 1rem;
      color: var(--text-dark);
    }
    .summary-line span:first-child {
      color: var(--muted);
    }
    .summary-line.total {
      font-size: 1.4rem;
      font-weight: 800;
      color: var(--secondary-color);
      margin-top: 15px;
      padding-top: 15px;
      border-top: 2px dashed var(--border-color);
    }

    /* Payment Info Card */
    .pay-row {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 0;
      cursor: pointer;
      font-weight: 600;
      color: var(--text-dark);
    }
    .pay-row input[type="radio"] {
      accent-color: var(--primary-color);
      width: 18px;
      height: 18px;
    }
    .info-card .hint {
      margin-top: 15px;
      padding-top: 10px;
      border-top: 1px solid var(--border-color);
      font-style: italic;
    }

    /* Responsive */
    @media (max-width: 992px) {
      .checkout-layout {
        grid-template-columns: 1fr;
      }
      .right-col {
        position: static;
        order: -1; /* Mueve el resumen arriba en móvil */
      }
      .page-title {
        font-size: 1.8rem;
      }
    }

    @media (max-width: 576px) {
      .header {
        padding: 10px 15px;
      }
      .header .logo a {
        font-size: 1.5rem;
      }
      .container {
        margin: 20px auto;
        padding: 0 15px;
      }
      .page-title {
        font-size: 1.5rem;
        margin-bottom: 20px;
      }
      .card {
        padding: 15px;
      }
      .space-card .title {
        font-size: 1.1rem;
      }
      .item-thumb {
        width: 60px;
        height: 60px;
      }
      .item-name {
        font-size: 1rem;
      }
      .item-sub {
        font-size: 0.85rem;
      }
      .item-price {
        font-size: 1.1rem;
      }
      .detail-grid {
        grid-template-columns: 1fr;
      }
      .detail-actions {
        flex-direction: column;
        gap: 8px;
      }
      .row-2 {
        flex-direction: column;
        gap: 10px;
      }
      .row-2 .btn {
        font-size: 1rem;
      }
      .summary-line.total {
        font-size: 1.2rem;
      }
    }

    /* Focus visible para accesibilidad */
    a:focus-visible, button:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible {
      outline: 3px solid var(--accent-color);
      outline-offset: 2px;
      border-radius: var(--radius-sm);
    }
  </style>
</head>
<body class="checkout-page">
  <header class="header">
    <div class="logo"><a href="index.php">🛒 <?= $APP ?></a></div>
    <nav>
      <a href="cart.php" class="btn">Carrito</a>
    </nav>
  </header>

  <main class="container checkout-layout">
    <section class="left-col">
      <h2 class="page-title">¡Casi listo para tu compra!</h2>

      <div class="card space-card">
        <div class="space-card-head">
          <div class="title">Tus productos en este espacio</div>
          <div class="actions">
            <div class="count-badge"><?= (int)($totals['count'] ?? 0) ?> artículos</div>
            <button id="toggleAllBtn" class="link-btn" aria-expanded="true">Ocultar ▾</button>
          </div>
        </div>

        <div id="<?= $compactId ?>" class="items-list <?= $is_single? 'single-item' : '' ?>" aria-live="polite">
          <?php if (empty($items_grouped)): ?>
            <div class="empty">¡Vaya! Parece que no hay artículos en este espacio.</div>
          <?php else: ?>
            <?php foreach ($items_grouped as $idx => $it): 
              $pid = (int)$it['product_id'];
              $name = $it['product_name'];
              $img = $it['product_image_url'];
              $qty = (int)$it['qty'];
              $unit = (float)$it['unit_price'];
              $line = (float)$it['line_total'];
              $rowId = "item_{$idx}_{$pid}";
            ?>
              <article class="item-row" data-id="<?= $rowId ?>">
                <div class="item-summary">
                  <img class="item-thumb" src="<?= safe($img) ?>" alt="<?= safe($name) ?>">
                  <div class="item-main">
                    <div class="item-name"><?= safe($name) ?></div>
                    <div class="item-sub"><?= $qty ?> × <?= fmt_any($unit,$currency) ?> c/u</div>
                  </div>
                  <div class="item-right">
                    <div class="item-price"><?= fmt_any($line,$currency) ?></div>
                    <button class="detail-toggle" aria-expanded="false" aria-controls="<?= $rowId ?>-detail">Ver detalles</button>
                  </div>
                </div>

                <div id="<?= $rowId ?>-detail" class="item-detail" hidden>
                  <div class="detail-grid">
                    <div>
                      <label>Precio unitario</label>
                      <div class="muted"><?= fmt_any($unit,$currency) ?></div>
                    </div>
                    <div>
                      <label>Cantidad</label>
                      <div class="qty-controls">
                        <button class="qty-btn" data-action="dec">−</button>
                        <input class="qty-input" type="number" min="1" value="<?= $qty ?>" data-unit="<?= $unit ?>">
                        <button class="qty-btn" data-action="inc">+</button>
                      </div>
                    </div>
                    <div>
                      <label>Subtotal de línea</label>
                      <div class="muted line-sub"><?= fmt_any($line,$currency) ?></div>
                    </div>
                    <div class="detail-actions">
                      <button class="update-line">Actualizar</button>
                      <button class="remove-line">Eliminar</button>
                    </div>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div>

      <div class="card checkout-form">
        <div class="section-title">¿Quién compra? ¡Tus datos!</div>
        <form id="checkoutForm" class="form">
          <input type="hidden" name="sale_id" value="<?= (int)$sale_id ?>">
          <input type="hidden" name="currency" value="<?= safe($currency) ?>">
          <label>Nombre completo
            <input class="input" name="buyer_name" value="<?= safe($_SESSION['name'] ?? '') ?>" required>
          </label>
          <label>Correo electrónico
            <input class="input" name="buyer_email" type="email" value="<?= safe($_SESSION['email'] ?? '') ?>" required>
          </label>
          <label>Teléfono de contacto
            <input class="input" name="buyer_phone" type="tel" placeholder="Ej: 8888 8888" required>
          </label>

          <div class="row-2">
            <button class="btn primary" id="confirmBtn" type="submit">¡Confirmar mi compra!</button>
            <a class="btn" href="cart.php">Volver al carrito</a>
          </div>
        </form>
      </div>

    </section>

    <aside class="right-col">
      <div class="card summary-card">
        <div class="summary-head">Tu resumen de compra</div>
        <div class="summary-body">
          <div class="summary-line"><span>Cantidad de artículos</span><span id="sumCount"><?= (int)($totals['count'] ?? 0) ?></span></div>
          <div class="summary-line"><span>Subtotal</span><span id="sumSub"><?= fmt_any($totals['subtotal'] ?? 0,$currency) ?></span></div>
          <div class="summary-line"><span>Impuestos</span><span id="sumTax"><?= fmt_any($totals['tax_total'] ?? 0,$currency) ?></span></div>
          <div class="summary-line total"><span>Total a pagar</span><span id="sumTotal"><?= fmt_any($totals['grand_total'] ?? 0,$currency) ?></span></div>
        </div>
      </div>

      <div class="card info-card">
        <div class="info-head">¿Cómo quieres pagar?</div>
        <div class="info-body">
          <label class="pay-row"><input type="radio" name="payment" value="sinpe" checked> 📱 SINPE Móvil</label>
          <label class="pay-row"><input type="radio" name="payment" value="paypal"> 💳 PayPal</label>
          <div class="hint">Recuerda: si pagas con SINPE, deberás subir el comprobante después de confirmar.</div>
        </div>
      </div>
    </aside>
  </main>

<script>
/* Minimal JS para expandir filas, editar qty, actualizar totales y enviar formulario. */
(function(){
  const itemsRoot = document.getElementById('<?= $compactId ?>');
  const toggleAll = document.getElementById('toggleAllBtn');
  const isSingle = itemsRoot && itemsRoot.classList.contains('single-item');

  // Toggle global (ocultar/mostrar lista)
  if (toggleAll && itemsRoot) {
    if (isSingle) {
      itemsRoot.style.display = 'none';
      toggleAll.setAttribute('aria-expanded','false');
      toggleAll.textContent = 'Mostrar ▴';
    }
    toggleAll.addEventListener('click', () => {
      const visible = itemsRoot.style.display !== 'none';
      itemsRoot.style.display = visible ? 'none' : '';
      toggleAll.setAttribute('aria-expanded', visible ? 'false' : 'true');
      toggleAll.textContent = visible ? 'Mostrar ▴' : 'Ocultar ▾';
    });
  }

  // Delegación: toggles de detalle por item
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.detail-toggle');
    if (btn) {
      const expanded = btn.getAttribute('aria-expanded') === 'true';
      const targetId = btn.getAttribute('aria-controls');
      const detail = document.getElementById(targetId);
      if (!detail) return;
      if (expanded) {
        detail.hidden = true;
        btn.setAttribute('aria-expanded','false');
        btn.textContent = 'Ver detalles';
      } else {
        detail.hidden = false;
        btn.setAttribute('aria-expanded','true');
        btn.textContent = 'Ocultar detalles';
      }
    }

    // qty buttons
    const qbtn = e.target.closest('.qty-btn');
    if (qbtn) {
      const parent = qbtn.closest('.detail-grid');
      const input = parent.querySelector('.qty-input');
      const action = qbtn.dataset.action;
      let val = parseInt(input.value || '1', 10);
      if (action === 'inc') val++;
      if (action === 'dec') val = Math.max(1, val-1);
      input.value = val;
      // update line preview
      const unit = parseFloat(input.dataset.unit || '0');
      const line = Math.round(unit * val * 100) / 100;
      parent.querySelector('.line-sub').textContent = formatMoney(line);
    }

    // actualizar linea (simula AJAX: actualiza subtotal en cliente)
    const upd = e.target.closest('.update-line');
    if (upd) {
      const art = upd.closest('.item-row');
      const input = art.querySelector('.qty-input');
      const qty = parseInt(input.value || '1', 10);
      const unit = parseFloat(input.dataset.unit || '0');
      const newLine = Math.round(unit * qty * 100) / 100;
      // Actualizar vista
      art.querySelector('.item-sub').textContent = qty + ' × ' + formatMoney(unit) + ' c/u';
      art.querySelector('.item-price').textContent = formatMoney(newLine);
      art.querySelector('.line-sub').textContent = formatMoney(newLine);
      // Opcional: enviar a servidor para persistir (no implementado)
      recalcTotals();
      alert('Línea actualizada (sin persistencia en servidor).');
    }

    // eliminar linea (solo UI)
    const rem = e.target.closest('.remove-line');
    if (rem) {
      const art = rem.closest('.item-row');
      if (!confirm('¿Estás seguro de eliminar este artículo del espacio?')) return;
      art.remove();
      recalcTotals();
    }
  });

  // Form submit
  const form = document.getElementById('checkoutForm');
  if (form) {
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const btn = document.getElementById('confirmBtn');
      btn.disabled = true;
      btn.textContent = '¡Enviando tu pedido...!';
      const body = {};
      new FormData(form).forEach((v,k)=> body[k]=v);
      // Recopilar items visibles (simple)
      body.items = [];
      document.querySelectorAll('.item-row').forEach(row=>{
        const name = row.querySelector('.item-name').textContent.trim();
        const qty = row.querySelector('.qty-input') ? parseInt(row.querySelector('.qty-input').value||1,10) : 1;
        const priceText = row.querySelector('.item-price').textContent.replace(/[^\d\.,]/g,'').replace(',','');
        body.items.push({ name, qty, price: priceText });
      });

      try {
        const res = await fetch('/api/apicheckout.php', {
          method:'POST',
          headers:{ 'Content-Type':'application/json' },
          body: JSON.stringify(body)
        });
        const json = await res.json();
        if (json && json.ok) {
          window.location.href = json.redirect || ('/order-success.php?order=' + (json.order_number || ''));
          return;
        }
        alert('¡Ups! Hubo un error: ' + (json.error || 'No se pudo procesar la orden.'));
      } catch (err) {
        console.error(err);
        alert('¡Oh no! Error de conexión. Por favor, intenta de nuevo.');
      } finally {
        btn.disabled = false;
        btn.textContent = '¡Confirmar mi compra!';
      }
    });
  }

  function formatMoney(n){
    // asume colones si no USD
    if ('<?= $currency ?>' === 'USD') return '$' + Number(n).toFixed(2);
    return '₡' + Math.round(n).toLocaleString('es-CR');
  }

  function recalcTotals(){
    let count = 0, sub = 0;
    document.querySelectorAll('.item-row').forEach(r=>{
      const priceNode = r.querySelector('.item-price');
      if (!priceNode) return;
      const clean = priceNode.textContent.replace(/[^\d\.,]/g,'').replace(',','');
      const p = Number(clean) || 0;
      sub += p;
      const qtyInput = r.querySelector('.qty-input');
      count += qtyInput ? parseInt(qtyInput.value||1,10) : 1;
    });
    document.getElementById('sumCount').textContent = count;
    document.getElementById('sumSub').textContent = formatMoney(sub);
    // tax = 0 (si aplicas taxes, calcular)
    document.getElementById('sumTax').textContent = formatMoney(0);
    document.getElementById('sumTotal').textContent = formatMoney(sub);
  }

})();
</script>
</body>
</html>