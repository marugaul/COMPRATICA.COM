<?php
// emprendedores-bulk-prices.php — Ajuste masivo de precios para emprendedoras
// Reutiliza la lógica de affiliate/bulk-prices.php adaptada a entrepreneur_products

$__sessPath = __DIR__ . '/sessions';
if (!is_dir($__sessPath)) @mkdir($__sessPath, 0755, true);
if (is_dir($__sessPath) && is_writable($__sessPath)) ini_set('session.save_path', $__sessPath);
session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/config.php';

if (!isset($_SESSION['uid']) || $_SESSION['uid'] <= 0) {
    header('Location: emprendedores-login.php');
    exit;
}

$pdo    = db();
$userId = (int)$_SESSION['uid'];

// Verificar identidad de emprendedora
if (empty($_SESSION['entrepreneur_id'])) {
    $stmtEnt = $pdo->prepare("SELECT id FROM entrepreneurs WHERE user_id = ?");
    $stmtEnt->execute([$userId]);
    $entId = (int)($stmtEnt->fetchColumn() ?: 0);
    if ($entId > 0) {
        $_SESSION['entrepreneur_id'] = $entId;
    } else {
        session_destroy();
        header('Location: emprendedores-login.php?error=not_entrepreneur');
        exit;
    }
}
$msg      = '';
$msg_type = 'error';

// ── Categorías de la emprendedora ──────────────────────────────────────────
$mc = $pdo->prepare("
    SELECT DISTINCT c.id, c.name
    FROM entrepreneur_categories c
    INNER JOIN entrepreneur_products p ON p.category_id = c.id
    WHERE p.user_id = ?
    ORDER BY c.name
");
$mc->execute([$userId]);
$my_categories = $mc->fetchAll(PDO::FETCH_ASSOC);

// ── Todos los productos de la emprendedora ────────────────────────────────
$q = $pdo->prepare("
    SELECT p.id, p.name, p.price, COALESCE(p.category_id, 0) as category_id, c.name as category_name
    FROM entrepreneur_products p
    LEFT JOIN entrepreneur_categories c ON c.id = p.category_id
    WHERE p.user_id = ?
    ORDER BY p.category_id, p.name
");
$q->execute([$userId]);
$all_products = $q->fetchAll(PDO::FETCH_ASSOC);

// ── POST: aplicar ajuste masivo ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_price_adjust'])) {
    try {
        $bpa_category = (int)($_POST['bpa_category'] ?? 0); // 0 = todas
        $bpa_adj_type = ($_POST['bpa_adj_type']  ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
        $bpa_adj_dir  = ($_POST['bpa_adj_dir']   ?? 'increase') === 'decrease' ? 'decrease' : 'increase';
        $bpa_amount   = abs((float)($_POST['bpa_adj_amount'] ?? 0));
        $bpa_ids      = array_values(array_filter(array_map('intval', (array)($_POST['bpa_ids'] ?? []))));

        if ($bpa_amount <= 0) {
            throw new RuntimeException('Ingresá un monto mayor a cero.');
        }

        // Obtener productos a ajustar
        if (!empty($bpa_ids)) {
            $ph     = implode(',', array_fill(0, count($bpa_ids), '?'));
            $params = array_merge([$userId], $bpa_ids);
            $stmt   = $pdo->prepare("SELECT id, price FROM entrepreneur_products WHERE user_id = ? AND id IN ($ph)");
            $stmt->execute($params);
        } elseif ($bpa_category > 0) {
            $stmt = $pdo->prepare("SELECT id, price FROM entrepreneur_products WHERE user_id = ? AND category_id = ?");
            $stmt->execute([$userId, $bpa_category]);
        } else {
            $stmt = $pdo->prepare("SELECT id, price FROM entrepreneur_products WHERE user_id = ?");
            $stmt->execute([$userId]);
        }

        $rows    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $upd     = $pdo->prepare("UPDATE entrepreneur_products SET price = ?, updated_at = datetime('now') WHERE id = ? AND user_id = ?");
        $updated = 0;

        foreach ($rows as $row) {
            $old   = (float)$row['price'];
            $delta = $bpa_adj_type === 'percent' ? $old * ($bpa_amount / 100.0) : $bpa_amount;
            $new   = $bpa_adj_dir  === 'increase' ? $old + $delta : $old - $delta;
            $new   = max(1, round($new));
            $upd->execute([$new, (int)$row['id'], $userId]);
            $updated++;
        }

        // Recargar productos
        $q->execute([$userId]);
        $all_products = $q->fetchAll(PDO::FETCH_ASSOC);

        $msg      = "Se actualizaron {$updated} producto" . ($updated !== 1 ? 's' : '') . " correctamente.";
        $msg_type = 'success';

    } catch (Throwable $e) {
        $msg      = $e->getMessage();
        $msg_type = 'error';
        error_log('[emprendedores-bulk-prices.php] ' . $e->getMessage());
    }
}

// Pasar productos a JS de forma segura
$products_json = json_encode(array_map(fn($p) => [
    'id'          => (int)$p['id'],
    'name'        => $p['name'],
    'price'       => (float)$p['price'],
    'category_id' => (int)$p['category_id'],
], $all_products), JSON_HEX_TAG | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ajuste Masivo de Precios — Emprendedores</title>
  <link rel="stylesheet" href="assets/css/main.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary: #667eea; --primary-2: #764ba2;
      --success: #27ae60; --success-h: #219a52;
      --danger:  #e74c3c; --danger-h:  #c0392b;
      --accent:  #667eea; --accent-h:  #5a6fd6;
      --gray-50: #f9fafb; --gray-100: #f3f4f6; --gray-200: #e5e7eb;
      --gray-300: #d1d5db; --gray-600: #6b7280; --gray-800: #1f2937;
    }
    body { background: var(--gray-50); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; }

    .page-header {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-2) 100%);
      padding: 1.1rem 2rem;
      display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;
      box-shadow: 0 2px 12px rgba(0,0,0,.12);
    }
    .page-header-title {
      font-size: 1.15rem; font-weight: 700; color: white;
      display: flex; align-items: center; gap: 0.7rem;
    }
    .nav-link {
      display: inline-flex; align-items: center; gap: 0.45rem;
      padding: 0.55rem 1rem; background: rgba(255,255,255,.15); color: white;
      text-decoration: none; border-radius: 20px; font-size: 0.875rem; font-weight: 500;
      border: 1px solid rgba(255,255,255,.25); transition: background .2s;
    }
    .nav-link:hover { background: rgba(255,255,255,.25); }

    .container { max-width: 860px; margin: 2rem auto; padding: 0 1rem; }

    .card {
      background: white; border-radius: 15px;
      box-shadow: 0 5px 20px rgba(0,0,0,.08);
      padding: 2rem; margin-bottom: 2rem;
    }
    .card h2 {
      font-size: 1.4rem; font-weight: 600; color: var(--gray-800);
      margin: 0 0 1.5rem; padding-bottom: 1rem;
      border-bottom: 2px solid var(--gray-100);
      display: flex; align-items: center; gap: 0.75rem;
    }
    label { font-weight: 500; color: var(--gray-800); display: block; margin-bottom: 0.4rem; }
    .input {
      border: 2px solid var(--gray-200); border-radius: 10px;
      padding: 0.7rem 1rem; font-size: 1rem; width: 100%; box-sizing: border-box;
      transition: border-color .2s, box-shadow .2s;
    }
    .input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(102,126,234,.15); outline: none; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
    @media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }

    .btn {
      padding: 0.65rem 1.4rem; border-radius: 25px; font-weight: 600; font-size: 0.9rem;
      display: inline-flex; align-items: center; gap: 0.5rem;
      border: none; cursor: pointer; text-decoration: none; transition: all .2s;
    }
    .btn:hover { filter: brightness(1.08); transform: translateY(-1px); box-shadow: 0 4px 15px rgba(0,0,0,.15); }
    .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-2)); color: white; }
    .btn-outline  { background: var(--gray-100); color: var(--gray-800); border: 1px solid var(--gray-300); border-radius: 25px; }

    .toggle-group { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.4rem; }
    .toggle-btn {
      padding: 0.55rem 1.1rem; border-radius: 20px; font-size: 0.875rem; font-weight: 500;
      border: 2px solid var(--gray-300); background: white; color: var(--gray-800); cursor: pointer; transition: all .2s;
    }
    .toggle-btn.active-pct { background: linear-gradient(135deg,var(--primary),var(--primary-2)); color:white; border-color:transparent; }
    .toggle-btn.active-fix { background: linear-gradient(135deg,#7c3aed,#6d28d9); color:white; border-color:transparent; }
    .toggle-btn.active-up  { background: linear-gradient(135deg,var(--success),var(--success-h)); color:white; border-color:transparent; }
    .toggle-btn.active-dn  { background: linear-gradient(135deg,var(--danger),var(--danger-h));   color:white; border-color:transparent; }

    .product-list { max-height: 300px; overflow-y: auto; border: 2px solid var(--gray-200); border-radius: 10px; padding: 0.4rem; }
    .product-row {
      display: flex; align-items: center; gap: 0.75rem;
      padding: 0.5rem 0.6rem; border-radius: 8px; cursor: pointer; transition: background .15s;
    }
    .product-row:hover { background: var(--gray-50); }
    .product-row input[type=checkbox] { width: auto; margin: 0; cursor: pointer; }
    .product-price { font-weight: 600; color: var(--primary); white-space: nowrap; }

    .preview-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 0.35rem 0.6rem; border-bottom: 1px solid var(--gray-200); font-size: 0.9rem;
    }
    .preview-row:last-child { border-bottom: none; }
    .arrow-up   { color: var(--success); font-weight: 700; }
    .arrow-down { color: var(--danger);  font-weight: 700; }

    .alert-success {
      background: rgba(39,174,96,.08); border: 1px solid rgba(39,174,96,.3);
      border-left: 4px solid var(--success); color: #166534;
      padding: 1rem 1.25rem; border-radius: 10px; margin-bottom: 1.5rem;
      display: flex; align-items: center; gap: 0.75rem;
    }
    .alert-error {
      background: rgba(231,76,60,.08); border: 1px solid rgba(231,76,60,.3);
      border-left: 4px solid var(--danger); color: #991b1b;
      padding: 1rem 1.25rem; border-radius: 10px; margin-bottom: 1.5rem;
      display: flex; align-items: center; gap: 0.75rem;
    }
    .section { margin-bottom: 1.75rem; }
    .step-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--gray-600); margin-bottom: 0.4rem; }
    #bpa-controls { display: none; }
    #bpa-products-wrap { display: none; }
    #bpa-empty { display: none; color: var(--gray-600); padding: 1rem; text-align: center; border: 2px dashed var(--gray-300); border-radius: 10px; }
    #bpa-preview-box { display: none; background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 10px; padding: 1rem; margin-bottom: 1.5rem; }
  </style>
</head>
<body>

<!-- ── HEADER ─────────────────────────────────────────────────────────────── -->
<header class="page-header">
  <div class="page-header-title">
    <i class="fas fa-percentage"></i> Ajuste Masivo de Precios
  </div>
  <nav style="display:flex;gap:0.4rem;flex-wrap:wrap;">
    <a href="emprendedores-dashboard.php" class="nav-link">
      <i class="fas fa-th-large"></i><span>Dashboard</span>
    </a>
    <a href="emprendedores-producto-crear.php" class="nav-link">
      <i class="fas fa-plus"></i><span>Nuevo Producto</span>
    </a>
  </nav>
</header>

<div class="container">

  <?php if ($msg): ?>
    <div class="alert-<?= $msg_type ?>">
      <i class="fas fa-<?= $msg_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
      <span><?= htmlspecialchars($msg) ?></span>
    </div>
  <?php endif; ?>

  <?php if (empty($all_products)): ?>
    <div class="alert-error">
      <i class="fas fa-info-circle"></i>
      <span>No tenés productos aún. <a href="emprendedores-producto-crear.php" style="font-weight:600">Agregá tu primer producto</a>.</span>
    </div>
  <?php else: ?>

  <div class="card">
    <h2><i class="fas fa-tags"></i> Ajustar precios en bloque</h2>

    <form id="bpa-form" method="post">
      <input type="hidden" name="bulk_price_adjust" value="1">
      <input type="hidden" name="bpa_adj_type" id="bpa-adj-type" value="percent">
      <input type="hidden" name="bpa_adj_dir"  id="bpa-adj-dir"  value="increase">

      <!-- Paso 1: Categoría (opcional) ──────────────────────────────── -->
      <div class="section">
        <div class="step-label">Paso 1 — Filtrar por categoría (opcional)</div>
        <select class="input" name="bpa_category" id="bpa-category" style="max-width:400px">
          <option value="0">— Todos los productos —</option>
          <?php foreach ($my_categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Paso 2: Selección de productos ───────────────────────────── -->
      <div class="section" id="bpa-products-wrap">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.6rem;">
          <div class="step-label" style="margin:0">
            Paso 2 — Productos
            <span id="bpa-count" style="font-weight:400;color:var(--gray-600)"></span>
          </div>
          <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;font-size:0.875rem;font-weight:500;">
            <input type="checkbox" id="bpa-select-all" style="width:auto;margin:0;cursor:pointer;">
            Seleccionar todos
          </label>
        </div>
        <div class="product-list" id="bpa-products-list"></div>
      </div>

      <div id="bpa-empty">No hay productos en esta categoría.</div>

      <!-- Paso 3: Tipo de ajuste ────────────────────────────────────── -->
      <div id="bpa-controls">
        <div class="section form-grid">
          <div>
            <div class="step-label">Paso 3a — Tipo de ajuste</div>
            <div class="toggle-group">
              <button type="button" class="toggle-btn type-btn active-pct" data-type="percent">% Porcentaje</button>
              <button type="button" class="toggle-btn type-btn" data-type="fixed">Monto Fijo</button>
            </div>
          </div>
          <div>
            <div class="step-label">Paso 3b — Dirección</div>
            <div class="toggle-group">
              <button type="button" class="toggle-btn dir-btn active-up" data-dir="increase">
                <i class="fas fa-arrow-up"></i> Subir
              </button>
              <button type="button" class="toggle-btn dir-btn" data-dir="decrease">
                <i class="fas fa-arrow-down"></i> Bajar
              </button>
            </div>
          </div>
        </div>

        <div class="section" style="max-width:320px">
          <label id="bpa-amount-label">Porcentaje (%)</label>
          <input class="input" type="number" step="1" min="1"
                 name="bpa_adj_amount" id="bpa-adj-amount"
                 placeholder="Ej: 10" required>
        </div>

        <!-- Vista previa ───────────────────────────────────────────── -->
        <div id="bpa-preview-box">
          <div style="font-weight:600;margin-bottom:0.6rem;color:var(--gray-800)">
            <i class="fas fa-eye"></i> Vista previa del ajuste
          </div>
          <div id="bpa-preview-list"></div>
        </div>

        <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
          <button type="button" id="bpa-preview-btn" class="btn btn-outline">
            <i class="fas fa-search"></i> Vista Previa
          </button>
          <button type="submit" class="btn btn-primary" onclick="return confirmBpa()">
            <i class="fas fa-check-circle"></i> Aplicar Ajuste
          </button>
        </div>

      </div><!-- /bpa-controls -->
    </form>
  </div>

  <?php endif; ?>
</div>

<script>
(function () {
  const allProducts = <?= $products_json ?>;

  const catEl       = document.getElementById('bpa-category');
  const typeInput   = document.getElementById('bpa-adj-type');
  const dirInput    = document.getElementById('bpa-adj-dir');
  const amountEl    = document.getElementById('bpa-adj-amount');
  const listEl      = document.getElementById('bpa-products-list');
  const wrapEl      = document.getElementById('bpa-products-wrap');
  const emptyEl     = document.getElementById('bpa-empty');
  const ctrlEl      = document.getElementById('bpa-controls');
  const countEl     = document.getElementById('bpa-count');
  const selAllEl    = document.getElementById('bpa-select-all');
  const previewBox  = document.getElementById('bpa-preview-box');
  const previewList = document.getElementById('bpa-preview-list');
  const amountLbl   = document.getElementById('bpa-amount-label');

  function esc(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  function fmt(v) { return '₡' + Math.round(v).toLocaleString('es-CR'); }
  function hidePreview() { previewBox.style.display = 'none'; }

  function getFiltered() {
    const cat = parseInt(catEl.value) || 0;
    return cat ? allProducts.filter(p => p.category_id === cat) : allProducts.slice();
  }

  function renderList() {
    hidePreview();
    const items = getFiltered();
    if (items.length === 0) {
      wrapEl.style.display = 'none'; emptyEl.style.display = 'block'; ctrlEl.style.display = 'none';
      return;
    }
    emptyEl.style.display = 'none';
    wrapEl.style.display  = 'block';
    ctrlEl.style.display  = 'block';
    countEl.textContent   = `(${items.length} producto${items.length !== 1 ? 's' : ''})`;

    listEl.innerHTML = items.map(p =>
      `<label class="product-row">
        <input type="checkbox" name="bpa_ids[]" value="${p.id}" checked class="bpa-chk">
        <span style="flex:1">${esc(p.name)}</span>
        <span class="product-price">${fmt(p.price)}</span>
      </label>`
    ).join('');

    selAllEl.checked = true; selAllEl.indeterminate = false;
    listEl.querySelectorAll('.bpa-chk').forEach(c => c.addEventListener('change', () => { syncSelectAll(); hidePreview(); }));
  }

  function syncSelectAll() {
    const all = listEl.querySelectorAll('.bpa-chk');
    const chk = listEl.querySelectorAll('.bpa-chk:checked');
    selAllEl.checked       = all.length > 0 && all.length === chk.length;
    selAllEl.indeterminate = chk.length > 0 && chk.length < all.length;
  }

  selAllEl.addEventListener('change', function () {
    listEl.querySelectorAll('.bpa-chk').forEach(c => { c.checked = this.checked; });
    hidePreview();
  });

  catEl.addEventListener('change', renderList);

  // ── Adjust-type tabs ──────────────────────────────────────────────────────
  document.querySelectorAll('.type-btn').forEach(btn => btn.addEventListener('click', function () {
    document.querySelectorAll('.type-btn').forEach(b => { b.className = 'toggle-btn type-btn'; });
    this.classList.add(this.dataset.type === 'percent' ? 'active-pct' : 'active-fix');
    typeInput.value = this.dataset.type;
    updateAmountLabel();
    hidePreview();
  }));

  // ── Direction tabs ────────────────────────────────────────────────────────
  document.querySelectorAll('.dir-btn').forEach(btn => btn.addEventListener('click', function () {
    document.querySelectorAll('.dir-btn').forEach(b => { b.className = 'toggle-btn dir-btn'; });
    this.classList.add(this.dataset.dir === 'increase' ? 'active-up' : 'active-dn');
    dirInput.value = this.dataset.dir;
    hidePreview();
  }));

  function updateAmountLabel() {
    if (typeInput.value === 'percent') {
      amountLbl.textContent = 'Porcentaje (%)';
      amountEl.placeholder  = 'Ej: 10';
      amountEl.step         = '1';
    } else {
      amountLbl.textContent = 'Monto fijo (₡)';
      amountEl.placeholder  = 'Ej: 500';
      amountEl.step         = '100';
    }
  }

  // ── Vista previa ──────────────────────────────────────────────────────────
  document.getElementById('bpa-preview-btn').addEventListener('click', function () {
    const amount = parseFloat(amountEl.value) || 0;
    if (amount <= 0) { amountEl.focus(); return; }

    const checkedIds = new Set([...listEl.querySelectorAll('.bpa-chk:checked')].map(c => +c.value));
    const items      = getFiltered().filter(p => checkedIds.has(p.id));
    if (items.length === 0) return;

    const isPct = typeInput.value === 'percent';
    const isUp  = dirInput.value  === 'increase';

    previewList.innerHTML = items.slice(0, 12).map(p => {
      const delta    = isPct ? p.price * (amount / 100) : amount;
      const newPrice = Math.max(1, Math.round(isUp ? p.price + delta : p.price - delta));
      const arrow = isUp
        ? `<span class="arrow-up">↑ ${fmt(newPrice)}</span>`
        : `<span class="arrow-down">↓ ${fmt(newPrice)}</span>`;
      return `<div class="preview-row"><span>${esc(p.name)}</span><span>${fmt(p.price)} ${arrow}</span></div>`;
    }).join('');

    if (items.length > 12) {
      previewList.innerHTML += `<div style="text-align:center;padding:.5rem;color:var(--gray-600);font-style:italic">... y ${items.length - 12} productos más</div>`;
    }
    previewBox.style.display = 'block';
  });

  // ── Confirmación antes de enviar ──────────────────────────────────────────
  window.confirmBpa = function () {
    const amount  = parseFloat(amountEl.value) || 0;
    const checked = listEl.querySelectorAll('.bpa-chk:checked').length;
    if (amount <= 0) { alert('Ingresá un monto mayor a cero.'); return false; }
    if (checked === 0) { alert('Seleccioná al menos un producto.'); return false; }
    const dir  = dirInput.value  === 'increase' ? 'SUBIR'      : 'BAJAR';
    const type = typeInput.value === 'percent'  ? `${amount}%` : `₡${amount}`;
    return confirm(`¿Confirmar ${dir} precios en ${type} para ${checked} producto(s)?`);
  };

  // Render inicial con todos los productos
  renderList();
})();
</script>
</body>
</html>
