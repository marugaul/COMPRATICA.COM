<?php
// admin/swiftpay.php — Panel de transacciones SwiftPay
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();
$pdo = db();

if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

// ── Filtros ──────────────────────────────────────────────────────────────────
$filterStatus  = $_GET['status']  ?? '';
$filterMode    = $_GET['mode']    ?? '';
$filterType    = $_GET['type']    ?? '';
$filterRef     = trim($_GET['ref'] ?? '');   // reference_table: orders | entrepreneur_orders
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 50;
$offset        = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($filterStatus !== '') { $where[] = 'st.status = ?';           $params[] = $filterStatus; }
if ($filterMode   !== '') { $where[] = 'st.mode = ?';             $params[] = $filterMode; }
if ($filterType   !== '') { $where[] = 'st.type = ?';             $params[] = $filterType; }
if ($filterRef    !== '') { $where[] = 'st.reference_table = ?';  $params[] = $filterRef; }

$whereSQL = implode(' AND ', $where);

// ── Totales por estado ───────────────────────────────────────────────────────
$stats = $pdo->query("
    SELECT status,
           COUNT(*) as cnt,
           SUM(CASE WHEN currency='CRC' THEN CAST(amount AS REAL) ELSE 0 END) as total_crc,
           SUM(CASE WHEN currency='USD' THEN CAST(amount AS REAL) ELSE 0 END) as total_usd
    FROM swiftpay_transactions
    GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);

$statsByStatus = [];
foreach ($stats as $s) $statsByStatus[$s['status']] = $s;

// ── Totales aprobados por referencia (afiliado vs emprendedor) ───────────────
$byRef = $pdo->query("
    SELECT reference_table,
           COUNT(*) as cnt,
           SUM(CASE WHEN currency='CRC' THEN CAST(amount AS REAL) ELSE 0 END) as total_crc,
           SUM(CASE WHEN currency='USD' THEN CAST(amount AS REAL) ELSE 0 END) as total_usd
    FROM swiftpay_transactions
    WHERE status = 'approved'
    GROUP BY reference_table
")->fetchAll(PDO::FETCH_ASSOC);

$byRefMap = [];
foreach ($byRef as $r) $byRefMap[$r['reference_table']] = $r;

// ── Listado paginado ─────────────────────────────────────────────────────────
$total = (int)$pdo->prepare("SELECT COUNT(*) FROM swiftpay_transactions st WHERE $whereSQL")
                   ->execute($params) ? $pdo->prepare("SELECT COUNT(*) FROM swiftpay_transactions st WHERE $whereSQL")->execute($params) : 0;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM swiftpay_transactions st WHERE $whereSQL");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$listStmt = $pdo->prepare("
    SELECT st.*,
           -- Emprendedoras: comprador y vendedor
           eo.buyer_name   AS eo_buyer_name,
           eo.buyer_email  AS eo_buyer_email,
           eo.buyer_phone  AS eo_buyer_phone,
           us.name         AS eo_seller_name,
           us.email        AS eo_seller_email,
           -- Venta de Garaje: comprador
           o.buyer_email   AS o_buyer_email,
           o.buyer_phone   AS o_buyer_phone,
           -- Para anulaciones: datos de la tx original
           orig.id         AS orig_id,
           orig.auth_code  AS orig_auth_code,
           orig.order_id   AS orig_order_id
    FROM swiftpay_transactions st
    LEFT JOIN entrepreneur_orders eo
           ON st.reference_table = 'entrepreneur_orders' AND st.reference_id = eo.id
    LEFT JOIN users us
           ON eo.seller_user_id = us.id
    LEFT JOIN orders o
           ON st.reference_table = 'orders' AND st.reference_id = o.id
    LEFT JOIN swiftpay_transactions orig
           ON st.type = 'void'
          AND orig.type != 'void'
          AND orig.status IN ('approved','voided')
          AND orig.amount = st.amount
          AND orig.currency = st.currency
          AND orig.id < st.id
    WHERE $whereSQL
    ORDER BY st.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$listStmt->execute($params);
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Helpers de formato ───────────────────────────────────────────────────────
function fmtMoney(string $amount, string $currency): string {
    $n = number_format((float)$amount, 2, '.', ',');
    return $currency === 'USD' ? "$\u{00A0}$n" : "₡\u{00A0}$n";
}
function statusBadge(string $status): string {
    $map = [
        'approved'    => ['#d1fae5','#065f46','Aprobada'],
        'pending'     => ['#fef9c3','#713f12','Pendiente'],
        'pending_3ds' => ['#dbeafe','#1e40af','3DS Pendiente'],
        'declined'    => ['#fee2e2','#991b1b','Rechazada'],
        'voided'      => ['#f3f4f6','#374151','Anulada'],
    ];
    [$bg, $color, $label] = $map[$status] ?? ['#f3f4f6','#6b7280', $status];
    return "<span style='background:$bg;color:$color;padding:2px 10px;border-radius:20px;font-size:.75rem;font-weight:700;white-space:nowrap'>$label</span>";
}
function typeBadge(string $type): string {
    $map = [
        'authorize' => ['#ede9fe','#5b21b6','Cobro'],
        'preauth'   => ['#fef3c7','#92400e','Pre-auth'],
        'complete'  => ['#d1fae5','#065f46','Completar'],
        'void'      => ['#fee2e2','#991b1b','Anulación'],
    ];
    [$bg, $color, $label] = $map[$type] ?? ['#f3f4f6','#6b7280',$type];
    return "<span style='background:$bg;color:$color;padding:2px 8px;border-radius:20px;font-size:.72rem;font-weight:600'>$label</span>";
}
function refLabel(string $table): string {
    return match($table) {
        'orders'              => '<i class="fas fa-store" title="Venta de Garaje"></i> Garaje',
        'entrepreneur_orders' => '<i class="fas fa-rocket" title="Emprendedoras"></i> Emprendedora',
        default               => h($table) ?: '—',
    };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>SwiftPay — Transacciones | Admin CompraTica</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f1f5f9;color:#1e293b;min-height:100vh}
    /* ── Nav ── */
    .top-nav{background:linear-gradient(135deg,#0d1b3e,#1a3a8f);padding:.75rem 1.5rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap}
    .top-nav a{color:#fff;text-decoration:none;font-size:.85rem;opacity:.8;transition:opacity .2s}
    .top-nav a:hover{opacity:1}
    .top-nav .brand{font-weight:800;font-size:1rem;opacity:1;margin-right:auto;display:flex;align-items:center;gap:.5rem}
    /* ── Layout ── */
    .page{max-width:1400px;margin:0 auto;padding:1.5rem}
    h1{font-size:1.4rem;font-weight:800;margin-bottom:1.5rem;display:flex;align-items:center;gap:.6rem}
    /* ── Stats cards ── */
    .stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem}
    .stat-card{background:#fff;border-radius:12px;padding:1.1rem 1.25rem;box-shadow:0 1px 6px rgba(0,0,0,.07)}
    .stat-card .label{font-size:.75rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.4rem}
    .stat-card .value{font-size:1.5rem;font-weight:800;color:#0f172a}
    .stat-card .sub{font-size:.78rem;color:#64748b;margin-top:.2rem}
    /* ── Section cards ── */
    .section{background:#fff;border-radius:14px;box-shadow:0 1px 6px rgba(0,0,0,.07);margin-bottom:1.5rem;overflow:hidden}
    .section-header{padding:1rem 1.25rem;border-bottom:1px solid #f1f5f9;font-weight:700;font-size:.95rem;display:flex;align-items:center;gap:.5rem;background:#f8fafc}
    /* ── Filters ── */
    .filters{padding:1rem 1.25rem;display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;border-bottom:1px solid #f1f5f9}
    .filters select,.filters input{padding:.4rem .75rem;border:1.5px solid #e2e8f0;border-radius:7px;font-size:.84rem;background:#fff;color:#1e293b;outline:none}
    .filters select:focus,.filters input:focus{border-color:#3b82f6}
    .filters button{padding:.4rem 1rem;background:#1a3a8f;color:#fff;border:none;border-radius:7px;font-size:.84rem;font-weight:600;cursor:pointer}
    .filters a.reset{font-size:.82rem;color:#64748b;text-decoration:none}
    .filters a.reset:hover{color:#dc2626}
    /* ── Table ── */
    .tbl-wrap{overflow-x:auto}
    table{width:100%;border-collapse:collapse;font-size:.83rem}
    th{background:#f8fafc;padding:.65rem 1rem;text-align:left;font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;border-bottom:1px solid #e2e8f0}
    td{padding:.7rem 1rem;border-bottom:1px solid #f1f5f9;vertical-align:middle}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:#fafbff}
    .mono{font-family:'Courier New',monospace;font-size:.78rem;color:#475569}
    /* ── Void btn ── */
    .btn-void{padding:.3rem .8rem;background:#fff;border:1.5px solid #dc2626;color:#dc2626;border-radius:6px;font-size:.78rem;font-weight:700;cursor:pointer;transition:all .2s;white-space:nowrap}
    .btn-void:hover{background:#dc2626;color:#fff}
    .btn-void:disabled{opacity:.4;cursor:not-allowed}
    /* ── Pagination ── */
    .pagination{padding:1rem 1.25rem;display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap}
    .pagination a,.pagination span{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:7px;font-size:.84rem;text-decoration:none;border:1.5px solid #e2e8f0;color:#475569;background:#fff;transition:all .2s}
    .pagination a:hover{background:#1a3a8f;color:#fff;border-color:#1a3a8f}
    .pagination .current{background:#1a3a8f;color:#fff;border-color:#1a3a8f;font-weight:700}
    /* ── Breakdown ── */
    .breakdown{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1rem;padding:1.25rem}
    .bk-card{border:1.5px solid #e2e8f0;border-radius:10px;padding:1rem 1.25rem}
    .bk-card .bk-title{font-weight:700;margin-bottom:.5rem;display:flex;align-items:center;gap:.4rem}
    .bk-card .bk-row{display:flex;justify-content:space-between;font-size:.84rem;color:#475569;margin-top:.25rem}
    .bk-card .bk-row strong{color:#0f172a}
    /* ── Toast ── */
    #toast{position:fixed;bottom:1.5rem;right:1.5rem;padding:.75rem 1.25rem;border-radius:10px;font-size:.88rem;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,.15);display:none;z-index:9999;max-width:360px}
    #toast.ok{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7}
    #toast.err{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}
  </style>
</head>
<body>

<nav class="top-nav">
  <a class="brand" href="dashboard.php">
    <i class="fas fa-bolt"></i> CompraTica Admin
  </a>
  <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
  <a href="terminos.php"><i class="fas fa-file-contract"></i> Términos</a>
  <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a>
</nav>

<div class="page">

  <h1><i class="fas fa-credit-card" style="color:#1a3a8f"></i> Transacciones SwiftPay</h1>

  <!-- ── Resumen por estado ─────────────────────────────────────────────── -->
  <div class="stats-grid">
    <?php
    $statusDef = [
      'approved'    => ['Aprobadas',     '#065f46','#d1fae5','fa-check-circle'],
      'pending'     => ['Pendientes',    '#92400e','#fef9c3','fa-clock'],
      'pending_3ds' => ['3DS Pendiente', '#1e40af','#dbeafe','fa-shield-alt'],
      'declined'    => ['Rechazadas',    '#991b1b','#fee2e2','fa-times-circle'],
      'voided'      => ['Anuladas',      '#374151','#f3f4f6','fa-ban'],
    ];
    foreach ($statusDef as $key => [$label, $color, $bg, $icon]):
      $s = $statsByStatus[$key] ?? ['cnt'=>0,'total_crc'=>0,'total_usd'=>0];
    ?>
    <div class="stat-card" style="border-left:4px solid <?= $color ?>">
      <div class="label"><i class="fas <?= $icon ?>" style="color:<?= $color ?>"></i> <?= $label ?></div>
      <div class="value"><?= number_format((int)$s['cnt']) ?></div>
      <?php if ($s['total_crc'] > 0): ?>
      <div class="sub">₡<?= number_format((float)$s['total_crc'], 0) ?></div>
      <?php endif; ?>
      <?php if ($s['total_usd'] > 0): ?>
      <div class="sub">$<?= number_format((float)$s['total_usd'], 2) ?> USD</div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ── Desglose por origen ───────────────────────────────────────────── -->
  <?php if (!empty($byRefMap)): ?>
  <div class="section" style="margin-bottom:1.5rem">
    <div class="section-header"><i class="fas fa-chart-pie"></i> Cobros Aprobados por Origen</div>
    <div class="breakdown">
      <?php foreach ($byRefMap as $table => $data): ?>
      <div class="bk-card">
        <div class="bk-title"><?= refLabel($table) ?></div>
        <div class="bk-row"><span>Transacciones</span><strong><?= number_format((int)$data['cnt']) ?></strong></div>
        <?php if ($data['total_crc'] > 0): ?>
        <div class="bk-row"><span>Total CRC</span><strong>₡<?= number_format((float)$data['total_crc'], 0) ?></strong></div>
        <?php endif; ?>
        <?php if ($data['total_usd'] > 0): ?>
        <div class="bk-row"><span>Total USD</span><strong>$<?= number_format((float)$data['total_usd'], 2) ?></strong></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Tabla de transacciones ────────────────────────────────────────── -->
  <div class="section">
    <div class="section-header"><i class="fas fa-list"></i> Detalle de Transacciones (<?= number_format($total) ?>)</div>

    <!-- Filtros -->
    <form class="filters" method="GET">
      <select name="status">
        <option value="">Todos los estados</option>
        <?php foreach (['approved'=>'Aprobadas','pending'=>'Pendientes','pending_3ds'=>'3DS Pendiente','declined'=>'Rechazadas','voided'=>'Anuladas'] as $v=>$l): ?>
        <option value="<?= $v ?>" <?= $filterStatus===$v?'selected':'' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
      <select name="type">
        <option value="">Todos los tipos</option>
        <?php foreach (['authorize'=>'Cobro','preauth'=>'Pre-auth','complete'=>'Completar','void'=>'Anulación'] as $v=>$l): ?>
        <option value="<?= $v ?>" <?= $filterType===$v?'selected':'' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
      <select name="ref">
        <option value="">Todos los orígenes</option>
        <option value="orders" <?= $filterRef==='orders'?'selected':'' ?>>Venta de Garaje</option>
        <option value="entrepreneur_orders" <?= $filterRef==='entrepreneur_orders'?'selected':'' ?>>Emprendedoras</option>
      </select>
      <select name="mode">
        <option value="">Sandbox + Live</option>
        <option value="sandbox" <?= $filterMode==='sandbox'?'selected':'' ?>>Solo Sandbox</option>
        <option value="live" <?= $filterMode==='live'?'selected':'' ?>>Solo Live</option>
      </select>
      <button type="submit"><i class="fas fa-filter"></i> Filtrar</button>
      <a class="reset" href="swiftpay.php"><i class="fas fa-times"></i> Limpiar</a>
    </form>

    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Fecha</th>
            <th>Origen</th>
            <th>Tipo</th>
            <th>Estado</th>
            <th>Monto</th>
            <th>Order ID</th>
            <th>Auth Code</th>
            <th>RRN</th>
            <th>Modo</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
          <tr><td colspan="11" style="text-align:center;padding:2rem;color:#94a3b8">Sin transacciones</td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $row):
            // Resolver comprador y vendedor según origen
            $buyerName  = '';
            $buyerEmail = '';
            $buyerPhone = '';
            $sellerName = '';
            if (!empty($row['eo_buyer_name'])) {
                $buyerName  = $row['eo_buyer_name'];
                $buyerEmail = $row['eo_buyer_email'] ?? '';
                $buyerPhone = $row['eo_buyer_phone'] ?? '';
                $sellerName = $row['eo_seller_name'] ?? '';
            } elseif (!empty($row['o_buyer_email'])) {
                $buyerEmail = $row['o_buyer_email'];
                $buyerPhone = $row['o_buyer_phone'] ?? '';
            }
            // Auth code: propio si existe, o del tx original (para anulaciones)
            $authCode   = $row['auth_code'] ?? '';
            $origAuth   = $row['orig_auth_code'] ?? '';
            $origId     = $row['orig_id'] ?? '';
            $rrn        = $row['rrn'] ?? '';
            $hasDetail  = $buyerName || $buyerEmail || $sellerName || $row['description'];
          ?>
          <tr>
            <td class="mono"><?= $row['id'] ?></td>
            <td style="white-space:nowrap;font-size:.78rem;color:#475569"><?= h(substr($row['created_at'],0,16)) ?></td>
            <td><?= refLabel($row['reference_table'] ?? '') ?></td>
            <td><?= typeBadge($row['type']) ?></td>
            <td><?= statusBadge($row['status']) ?></td>
            <td style="font-weight:700;white-space:nowrap">
              <?= ($row['amount'] && $row['currency']) ? fmtMoney($row['amount'], $row['currency']) : '—' ?>
              <?php if ($row['mode'] === 'sandbox'): ?>
              <span style="font-size:.65rem;color:#f59e0b;font-weight:600;display:block">SANDBOX</span>
              <?php endif; ?>
            </td>
            <td class="mono" style="font-size:.75rem"><?= h($row['order_id'] ?: '—') ?></td>
            <td class="mono" style="font-size:.75rem">
              <?php if ($authCode): ?>
                <span style="color:#065f46;font-weight:700"><?= h($authCode) ?></span>
              <?php elseif ($origAuth): ?>
                <span style="color:#6b7280;font-size:.7rem" title="Auth de tx original #<?= h($origId) ?>">
                  ↩ <?= h($origAuth) ?>
                </span>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="mono" style="font-size:.75rem"><?= h($rrn ?: '—') ?></td>
            <td>
              <span style="font-size:.72rem;font-weight:600;color:<?= $row['mode']==='live'?'#065f46':'#92400e' ?>">
                <?= strtoupper(h($row['mode'])) ?>
              </span>
              <span style="font-size:.65rem;color:#94a3b8;display:block"><?= h($row['ip_address'] ?? '') ?></span>
            </td>
            <td>
              <?php if ($row['status'] === 'approved' && $row['type'] !== 'void'): ?>
              <button class="btn-void"
                      data-id="<?= $row['id'] ?>"
                      data-amount="<?= h($row['amount'] ?? '') ?>"
                      data-currency="<?= h($row['currency'] ?? '') ?>"
                      onclick="confirmVoid(this)">
                <i class="fas fa-ban"></i> Anular
              </button>
              <?php elseif ($row['status'] === 'voided'): ?>
              <span style="color:#94a3b8;font-size:.78rem">Anulada</span>
              <?php else: ?>
              <span style="color:#cbd5e1;font-size:.78rem">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php if ($hasDetail): ?>
          <tr style="background:#f8fafc">
            <td colspan="11" style="padding:.35rem 1rem .5rem 2rem;font-size:.76rem;color:#475569;border-bottom:1px solid #f1f5f9">
              <?php if ($sellerName): ?>
                <span style="margin-right:1.2rem">
                  <i class="fas fa-store" style="color:#7c3aed;margin-right:.25rem"></i>
                  <strong>Vendedor:</strong> <?= h($sellerName) ?> <?= $row['eo_seller_email'] ? '&lt;'.h($row['eo_seller_email']).'&gt;' : '' ?>
                </span>
              <?php endif; ?>
              <?php if ($buyerName || $buyerEmail): ?>
                <span style="margin-right:1.2rem">
                  <i class="fas fa-user" style="color:#0284c7;margin-right:.25rem"></i>
                  <strong>Comprador:</strong>
                  <?= h($buyerName ?: '') ?>
                  <?= $buyerEmail ? '&lt;'.h($buyerEmail).'&gt;' : '' ?>
                  <?= $buyerPhone ? ' · '.h($buyerPhone) : '' ?>
                </span>
              <?php endif; ?>
              <?php if ($row['description']): ?>
                <span>
                  <i class="fas fa-tag" style="color:#94a3b8;margin-right:.25rem"></i>
                  <?= h($row['description']) ?>
                </span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>
          <?php if (!empty($row['error_message'])): ?>
          <tr>
            <td colspan="11" style="background:#fff5f5;padding:.4rem 1rem;font-size:.76rem;color:#991b1b;border-bottom:1px solid #f1f5f9">
              <i class="fas fa-exclamation-triangle"></i> <?= h($row['error_message']) ?>
            </td>
          </tr>
          <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php
      $qp = $_GET;
      for ($i = 1; $i <= $pages; $i++):
        $qp['page'] = $i;
        $href = '?' . http_build_query($qp);
      ?>
      <?php if ($i === $page): ?>
        <span class="current"><?= $i ?></span>
      <?php else: ?>
        <a href="<?= h($href) ?>"><?= $i ?></a>
      <?php endif; ?>
      <?php endfor; ?>
    </div>
    <?php endif; ?>

  </div><!-- /.section -->

</div><!-- /.page -->

<div id="toast"></div>

<script>
function confirmVoid(btn) {
    const id       = btn.dataset.id;
    const amount   = btn.dataset.amount;
    const currency = btn.dataset.currency;
    const symbol   = currency === 'USD' ? '$' : '₡';
    const fmt      = symbol + parseFloat(amount).toLocaleString('es-CR', {minimumFractionDigits:2});

    if (!confirm('¿Anular esta transacción?\n\nMonto: ' + fmt + '\nID: #' + id + '\n\nEsta acción no se puede deshacer.')) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Anulando…';

    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 30000);

    fetch('/api/swiftpay-void.php', {
        method:  'POST',
        headers: {'Content-Type': 'application/json'},
        body:    JSON.stringify({tx_id: parseInt(id)}),
        signal:  controller.signal,
    })
    .then(r => { clearTimeout(timeout); return r.json(); })
    .then(data => {
        if (data.ok) {
            showToast('✅ ' + data.message, 'ok');
            // Actualizar fila visualmente
            const td = btn.closest('td');
            td.innerHTML = '<span style="color:#94a3b8;font-size:.78rem">Anulada</span>';
            // Actualizar badge de estado
            const tds = btn.closest('tr').querySelectorAll('td');
            tds[4].innerHTML = '<span style="background:#f3f4f6;color:#374151;padding:2px 10px;border-radius:20px;font-size:.75rem;font-weight:700">Anulada</span>';
            setTimeout(() => location.reload(), 2000);
        } else {
            showToast('❌ ' + (data.error || 'Error al anular'), 'err');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-ban"></i> Anular';
        }
    })
    .catch(err => {
        const isTimeout = err && err.name === 'AbortError';
        if (isTimeout) {
            showToast('⚠️ La solicitud tardó demasiado — recargá la página para verificar si se anuló.', 'err');
            btn.innerHTML = '<i class="fas fa-sync"></i> Recargar';
            btn.disabled = false;
            btn.onclick = () => location.reload();
        } else {
            showToast('❌ Error de conexión — recargá la página para verificar el estado.', 'err');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-ban"></i> Anular';
        }
    });
}

function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = type;
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 4000);
}
</script>
</body>
</html>
