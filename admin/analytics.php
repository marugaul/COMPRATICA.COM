<?php
/**
 * admin/analytics.php
 * Panel de estadísticas en tiempo real para el administrador.
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();
$pdo = db();

// ─── Helper seguro: devuelve 0 si falla ──────────────────────────────────────
function safeqv($pdo, $sql, $params = []) {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchColumn() ?: 0;
    } catch (Throwable $e) { return 0; }
}
function safeq($pdo, $sql, $params = []) {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return []; }
}

// ─── Asegurar tabla site_visits ───────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_visits (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id VARCHAR(64),
        ip         VARCHAR(45),
        page       VARCHAR(500),
        referrer   VARCHAR(500),
        user_agent VARCHAR(500),
        sale_id    INTEGER DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sv_created ON site_visits(created_at)");
} catch (Throwable $e) {}

// ─── Visitantes online ────────────────────────────────────────────────────────
$online_now   = (int)safeqv($pdo, "SELECT COUNT(DISTINCT session_id) FROM site_visits WHERE created_at > datetime('now','-5 minutes')");
$online_1h    = (int)safeqv($pdo, "SELECT COUNT(DISTINCT session_id) FROM site_visits WHERE created_at > datetime('now','-1 hour')");
$visits_today = (int)safeqv($pdo, "SELECT COUNT(*) FROM site_visits WHERE date(created_at)=date('now')");
$visits_week  = (int)safeqv($pdo, "SELECT COUNT(*) FROM site_visits WHERE created_at > datetime('now','-7 days')");

$top_pages = safeq($pdo, "SELECT page, COUNT(*) as views FROM site_visits WHERE date(created_at)=date('now') AND page!='' GROUP BY page ORDER BY views DESC LIMIT 10");
$visits_by_hour = safeq($pdo, "SELECT strftime('%H',created_at) as hour, COUNT(*) as views FROM site_visits WHERE date(created_at)=date('now') GROUP BY hour ORDER BY hour ASC");
$recent_visitors = safeq($pdo, "SELECT ip, page, referrer, created_at FROM site_visits ORDER BY created_at DESC LIMIT 20");

// ─── Pedidos ──────────────────────────────────────────────────────────────────
$orders_today  = (int)safeqv($pdo, "SELECT COUNT(*) FROM orders WHERE date(created_at)=date('now')");
$orders_week   = (int)safeqv($pdo, "SELECT COUNT(*) FROM orders WHERE created_at > datetime('now','-7 days')");
$orders_month  = (int)safeqv($pdo, "SELECT COUNT(*) FROM orders WHERE created_at > datetime('now','-30 days')");
$orders_total  = (int)safeqv($pdo, "SELECT COUNT(*) FROM orders");
$orders_pending= (int)safeqv($pdo, "SELECT COUNT(*) FROM orders WHERE status='Pendiente'");
$orders_paid   = (int)safeqv($pdo, "SELECT COUNT(*) FROM orders WHERE status='Pagado'");

// ─── Ingresos ─────────────────────────────────────────────────────────────────
$revenue_today = (float)safeqv($pdo, "SELECT COALESCE(SUM(total_crc),0) FROM orders WHERE date(created_at)=date('now') AND status NOT IN ('Cancelado')");
$revenue_week  = (float)safeqv($pdo, "SELECT COALESCE(SUM(total_crc),0) FROM orders WHERE created_at > datetime('now','-7 days') AND status NOT IN ('Cancelado')");
$revenue_month = (float)safeqv($pdo, "SELECT COALESCE(SUM(total_crc),0) FROM orders WHERE created_at > datetime('now','-30 days') AND status NOT IN ('Cancelado')");

// ─── Pedidos por día (últimos 14 días) ────────────────────────────────────────
$orders_by_day = safeq($pdo, "SELECT date(created_at) as day, COUNT(*) as total FROM orders WHERE created_at > datetime('now','-14 days') GROUP BY day ORDER BY day ASC");

// ─── Afiliados ────────────────────────────────────────────────────────────────
$aff_total    = (int)safeqv($pdo, "SELECT COUNT(*) FROM affiliates");
$aff_active   = (int)safeqv($pdo, "SELECT COUNT(*) FROM affiliates WHERE is_active=1");
$aff_new_week = (int)safeqv($pdo, "SELECT COUNT(*) FROM affiliates WHERE created_at > datetime('now','-7 days')");

// ─── Espacios ─────────────────────────────────────────────────────────────────
$spaces_total  = (int)safeqv($pdo, "SELECT COUNT(*) FROM sales");
$spaces_active = (int)safeqv($pdo, "SELECT COUNT(*) FROM sales WHERE is_active=1");

// ─── Productos ────────────────────────────────────────────────────────────────
$products_total   = (int)safeqv($pdo, "SELECT COUNT(*) FROM products");
$products_active  = (int)safeqv($pdo, "SELECT COUNT(*) FROM products WHERE active=1");
$products_nostock = (int)safeqv($pdo, "SELECT COUNT(*) FROM products WHERE stock=0 AND active=1");

// ─── Top afiliados ────────────────────────────────────────────────────────────
$top_affiliates = safeq($pdo, "
    SELECT a.name, a.email, COUNT(o.id) as orders, COALESCE(SUM(o.total_crc),0) as revenue
    FROM orders o JOIN affiliates a ON a.id=o.affiliate_id
    WHERE o.created_at > datetime('now','-30 days')
    GROUP BY a.id ORDER BY orders DESC LIMIT 10
");

// ─── Últimas órdenes ──────────────────────────────────────────────────────────
$latest_orders = safeq($pdo, "
    SELECT o.id, o.buyer_email, o.product_name, o.qty, o.total_crc, o.status, o.created_at,
           a.name as affiliate_name
    FROM orders o LEFT JOIN affiliates a ON a.id=o.affiliate_id
    ORDER BY o.created_at DESC LIMIT 10
");

// ─── Productos más vendidos ───────────────────────────────────────────────────
$top_products = safeq($pdo, "
    SELECT product_name, COUNT(*) as orders, SUM(qty) as units, COALESCE(SUM(total_crc),0) as revenue
    FROM orders WHERE created_at > datetime('now','-30 days')
    GROUP BY product_name ORDER BY orders DESC LIMIT 10
");

// ─── Conversión ───────────────────────────────────────────────────────────────
$conversion = ($visits_today > 0) ? round($orders_today / $visits_today * 100, 2) : 0;

// ─── Arrays para Chart.js ─────────────────────────────────────────────────────
$chart_days   = array_column($orders_by_day, 'day');
$chart_orders = array_column($orders_by_day, 'total');
$chart_hours  = array_fill(0, 24, 0);
foreach ($visits_by_hour as $r) {
    $chart_hours[(int)$r['hour']] = (int)$r['views'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Estadísticas — COMPRATICA.COM</title>
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    :root {
      --primary:#002b7f; --accent:#3498db; --green:#27ae60; --yellow:#f39c12;
      --red:#e74c3c; --purple:#8b5cf6; --gray-50:#f8fafc; --gray-100:#f1f5f9;
      --gray-200:#e2e8f0; --gray-500:#64748b; --gray-700:#334155; --gray-900:#0f172a;
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',sans-serif;background:var(--gray-50);color:var(--gray-700)}

    .top-nav{background:linear-gradient(135deg,#1e293b,#334155);padding:.75rem 1.5rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;box-shadow:0 2px 8px rgba(0,0,0,.2)}
    .top-nav .brand{font-size:1.1rem;font-weight:700;color:#fff;display:flex;align-items:center;gap:.5rem}
    .top-nav .nav-link{color:rgba(255,255,255,.85);text-decoration:none;font-size:.85rem;padding:.4rem .9rem;border-radius:6px;border:1px solid rgba(255,255,255,.2);transition:background .2s}
    .top-nav .nav-link:hover{background:rgba(255,255,255,.15)}
    .top-nav .spacer{flex:1}

    .live-badge{display:inline-flex;align-items:center;gap:.4rem;background:rgba(39,174,96,.2);border:1px solid rgba(39,174,96,.5);color:#27ae60;padding:.35rem .85rem;border-radius:20px;font-size:.82rem;font-weight:600}
    .live-dot{width:8px;height:8px;border-radius:50%;background:#27ae60;animation:pulse 1.5s infinite}
    @keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.4)}}

    .container{max-width:1400px;margin:0 auto;padding:1.5rem}
    h1{font-size:1.6rem;color:var(--gray-900);margin-bottom:1.5rem;display:flex;align-items:center;gap:.75rem}

    .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:1rem;margin-bottom:1.5rem}
    .stat-card{background:#fff;border-radius:12px;padding:1.25rem;box-shadow:0 1px 4px rgba(0,0,0,.07);border:1px solid var(--gray-200);display:flex;flex-direction:column;gap:.35rem}
    .stat-card .label{font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;color:var(--gray-500);font-weight:600}
    .stat-card .value{font-size:2rem;font-weight:700;line-height:1}
    .stat-card .sub{font-size:.78rem;color:var(--gray-500)}
    .stat-card .icon{font-size:1.5rem;margin-bottom:.25rem}
    .green{color:var(--green)}.blue{color:var(--accent)}.yellow{color:var(--yellow)}.red{color:var(--red)}.purple{color:var(--purple)}

    .section-title{font-size:1rem;font-weight:700;color:var(--gray-900);margin:1.5rem 0 .75rem;padding-bottom:.5rem;border-bottom:2px solid var(--gray-200);display:flex;align-items:center;gap:.5rem}

    .charts-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem}
    @media(max-width:900px){.charts-row{grid-template-columns:1fr}}
    .chart-card{background:#fff;border-radius:12px;padding:1.25rem;box-shadow:0 1px 4px rgba(0,0,0,.07);border:1px solid var(--gray-200)}
    .chart-card h3{font-size:.9rem;color:var(--gray-700);margin-bottom:1rem}

    .table-card{background:#fff;border-radius:12px;padding:1.25rem;box-shadow:0 1px 4px rgba(0,0,0,.07);border:1px solid var(--gray-200);margin-bottom:1rem;overflow-x:auto}
    .table-card h3{font-size:.9rem;color:var(--gray-700);margin-bottom:1rem}
    table{width:100%;border-collapse:collapse;font-size:.83rem}
    th{background:var(--gray-100);padding:.6rem .75rem;text-align:left;font-weight:600;color:var(--gray-700);white-space:nowrap}
    td{padding:.55rem .75rem;border-bottom:1px solid var(--gray-100);color:var(--gray-700)}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:var(--gray-50)}

    .pill{display:inline-block;padding:2px 8px;border-radius:12px;font-size:.75rem;font-weight:600}
    .pill-green{background:rgba(39,174,96,.12);color:#27ae60}
    .pill-yellow{background:rgba(243,156,18,.12);color:#b7860a}
    .pill-red{background:rgba(231,76,60,.12);color:#c0392b}
    .pill-blue{background:rgba(52,152,219,.12);color:#1a6fa8}
    .pill-gray{background:var(--gray-100);color:var(--gray-500)}

    .two-cols{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
    @media(max-width:900px){.two-cols{grid-template-columns:1fr}}

    .refresh-info{font-size:.75rem;color:rgba(255,255,255,.7);display:flex;align-items:center;gap:.4rem}
    .refresh-countdown{font-weight:700;color:#7dd3fc}

    .online-hero{background:linear-gradient(135deg,#0f4c81,#1a6fa8);color:white;border-radius:16px;padding:1.5rem 2rem;display:flex;align-items:center;gap:2rem;margin-bottom:1.5rem;box-shadow:0 4px 16px rgba(0,43,127,.3);flex-wrap:wrap}
    .online-hero .big{font-size:3.5rem;font-weight:800;line-height:1}
    .online-hero .desc{font-size:.9rem;opacity:.85;margin-top:.25rem}
    .online-hero .side{opacity:.8;font-size:.85rem;border-left:1px solid rgba(255,255,255,.3);padding-left:2rem}
    .online-hero .side p{margin:.4rem 0}
    .online-hero .side strong{color:#fff}
    .page-url{max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block}

    .no-data{color:var(--gray-500);font-size:.85rem;padding:.5rem 0;font-style:italic}
  </style>
</head>
<body>

<nav class="top-nav">
  <div class="brand"><i class="fas fa-chart-line"></i> Estadísticas</div>
  <a class="nav-link" href="dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
  <div class="spacer"></div>
  <div class="live-badge"><div class="live-dot"></div> EN VIVO</div>
  <div class="refresh-info">
    <i class="fas fa-sync-alt"></i> Auto-refresh en
    <span class="refresh-countdown" id="countdown">30</span>s
  </div>
</nav>

<div class="container">

  <h1><i class="fas fa-chart-line" style="color:var(--accent)"></i> Panel de Estadísticas</h1>

  <!-- ONLINE AHORA -->
  <div class="online-hero">
    <div>
      <div class="big"><?= $online_now ?></div>
      <div style="font-size:1rem;font-weight:600;margin-top:.25rem">usuarios online ahora</div>
      <div class="desc">Visitantes únicos en los últimos 5 minutos</div>
    </div>
    <div class="side">
      <p><strong><?= $online_1h ?></strong> en la última hora</p>
      <p><strong><?= number_format($visits_today) ?></strong> páginas vistas hoy</p>
      <p><strong><?= number_format($visits_week) ?></strong> páginas vistas esta semana</p>
      <p><strong><?= $conversion ?>%</strong> tasa de conversión hoy</p>
    </div>
  </div>

  <!-- VENTAS -->
  <div class="section-title"><i class="fas fa-shopping-bag" style="color:var(--green)"></i> Pedidos y Ventas</div>
  <div class="stats-grid">
    <div class="stat-card">
      <div class="icon green"><i class="fas fa-calendar-day"></i></div>
      <div class="label">Pedidos hoy</div>
      <div class="value green"><?= $orders_today ?></div>
      <div class="sub">₡<?= number_format($revenue_today, 0, '.', ',') ?></div>
    </div>
    <div class="stat-card">
      <div class="icon blue"><i class="fas fa-calendar-week"></i></div>
      <div class="label">Esta semana</div>
      <div class="value blue"><?= $orders_week ?></div>
      <div class="sub">₡<?= number_format($revenue_week, 0, '.', ',') ?></div>
    </div>
    <div class="stat-card">
      <div class="icon yellow"><i class="fas fa-calendar-alt"></i></div>
      <div class="label">Este mes</div>
      <div class="value yellow"><?= $orders_month ?></div>
      <div class="sub">₡<?= number_format($revenue_month, 0, '.', ',') ?></div>
    </div>
    <div class="stat-card">
      <div class="icon purple"><i class="fas fa-layer-group"></i></div>
      <div class="label">Total histórico</div>
      <div class="value purple"><?= $orders_total ?></div>
      <div class="sub"><?= $orders_pending ?> pendientes · <?= $orders_paid ?> pagados</div>
    </div>
  </div>

  <!-- PLATAFORMA -->
  <div class="section-title"><i class="fas fa-store" style="color:var(--purple)"></i> Plataforma</div>
  <div class="stats-grid">
    <div class="stat-card">
      <div class="icon blue"><i class="fas fa-user-tie"></i></div>
      <div class="label">Afiliados</div>
      <div class="value blue"><?= $aff_total ?></div>
      <div class="sub"><?= $aff_active ?> activos · <?= $aff_new_week ?> nuevos esta semana</div>
    </div>
    <div class="stat-card">
      <div class="icon green"><i class="fas fa-store-alt"></i></div>
      <div class="label">Espacios</div>
      <div class="value green"><?= $spaces_total ?></div>
      <div class="sub"><?= $spaces_active ?> activos</div>
    </div>
    <div class="stat-card">
      <div class="icon yellow"><i class="fas fa-box"></i></div>
      <div class="label">Productos</div>
      <div class="value yellow"><?= $products_total ?></div>
      <div class="sub"><?= $products_active ?> activos · <?= $products_nostock ?> sin stock</div>
    </div>
    <div class="stat-card">
      <div class="icon green"><i class="fas fa-mouse-pointer"></i></div>
      <div class="label">Visitas hoy</div>
      <div class="value green"><?= number_format($visits_today) ?></div>
      <div class="sub"><?= number_format($visits_week) ?> esta semana</div>
    </div>
  </div>

  <!-- GRÁFICOS -->
  <div class="charts-row">
    <div class="chart-card">
      <h3><i class="fas fa-chart-bar"></i> Pedidos por día (últimos 14 días)</h3>
      <canvas id="ordersChart" height="130"></canvas>
    </div>
    <div class="chart-card">
      <h3><i class="fas fa-clock"></i> Visitas por hora (hoy)</h3>
      <canvas id="hoursChart" height="130"></canvas>
    </div>
  </div>

  <!-- TABLAS DOS COLUMNAS -->
  <div class="two-cols">
    <div class="table-card">
      <h3><i class="fas fa-fire" style="color:var(--red)"></i> Páginas más vistas hoy</h3>
      <?php if ($top_pages): ?>
      <table>
        <thead><tr><th>Página</th><th>Visitas</th></tr></thead>
        <tbody>
        <?php foreach ($top_pages as $tp): ?>
          <tr>
            <td><span class="page-url" title="<?= htmlspecialchars((string)($tp['page'] ?? '')) ?>"><?= htmlspecialchars((string)($tp['page'] ?? '')) ?></span></td>
            <td><strong><?= (int)$tp['views'] ?></strong></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <p class="no-data">Sin datos todavía. El tracker registrará visitas automáticamente.</p>
      <?php endif; ?>
    </div>

    <div class="table-card">
      <h3><i class="fas fa-trophy" style="color:var(--yellow)"></i> Top afiliados (30 días)</h3>
      <?php if ($top_affiliates): ?>
      <table>
        <thead><tr><th>Afiliado</th><th>Pedidos</th><th>Ingresos</th></tr></thead>
        <tbody>
        <?php foreach ($top_affiliates as $ta): ?>
          <tr>
            <td><?= htmlspecialchars((string)($ta['name'] ?? '')) ?><br><small style="color:var(--gray-500)"><?= htmlspecialchars((string)($ta['email'] ?? '')) ?></small></td>
            <td><strong><?= (int)$ta['orders'] ?></strong></td>
            <td>₡<?= number_format((float)($ta['revenue'] ?? 0), 0, '.', ',') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <p class="no-data">Sin pedidos en los últimos 30 días.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- TOP PRODUCTOS -->
  <div class="table-card">
    <h3><i class="fas fa-star" style="color:var(--accent)"></i> Productos más vendidos (30 días)</h3>
    <?php if ($top_products): ?>
    <table>
      <thead><tr><th>Producto</th><th>Pedidos</th><th>Unidades</th><th>Ingresos</th></tr></thead>
      <tbody>
      <?php foreach ($top_products as $tp): ?>
        <tr>
          <td><?= htmlspecialchars((string)($tp['product_name'] ?? '')) ?></td>
          <td><?= (int)($tp['orders'] ?? 0) ?></td>
          <td><?= (int)($tp['units'] ?? 0) ?></td>
          <td>₡<?= number_format((float)($tp['revenue'] ?? 0), 0, '.', ',') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <p class="no-data">Sin pedidos recientes.</p>
    <?php endif; ?>
  </div>

  <!-- ÚLTIMOS PEDIDOS -->
  <div class="table-card">
    <h3><i class="fas fa-list-alt"></i> Últimas órdenes</h3>
    <?php if ($latest_orders): ?>
    <table>
      <thead>
        <tr><th>#</th><th>Producto</th><th>Comprador</th><th>Total</th><th>Afiliado</th><th>Estado</th><th>Fecha</th></tr>
      </thead>
      <tbody>
      <?php foreach ($latest_orders as $o):
        $st = (string)($o['status'] ?? '');
        $pill = 'pill-gray';
        if ($st === 'Pagado')    $pill = 'pill-green';
        elseif ($st === 'Pendiente') $pill = 'pill-yellow';
        elseif ($st === 'Cancelado') $pill = 'pill-red';
        elseif ($st === 'Entregado') $pill = 'pill-blue';
      ?>
        <tr>
          <td><?= (int)($o['id'] ?? 0) ?></td>
          <td><?= htmlspecialchars((string)($o['product_name'] ?? '—')) ?></td>
          <td style="font-size:.78rem"><?= htmlspecialchars((string)($o['buyer_email'] ?? '—')) ?></td>
          <td>₡<?= number_format((float)($o['total_crc'] ?? 0), 0, '.', ',') ?></td>
          <td style="font-size:.78rem"><?= htmlspecialchars((string)($o['affiliate_name'] ?? '—')) ?></td>
          <td><span class="pill <?= $pill ?>"><?= htmlspecialchars($st) ?></span></td>
          <td style="font-size:.78rem"><?= htmlspecialchars(substr((string)($o['created_at'] ?? ''), 0, 16)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <p class="no-data">Sin órdenes aún.</p>
    <?php endif; ?>
  </div>

  <!-- VISITANTES RECIENTES -->
  <div class="table-card">
    <h3><i class="fas fa-eye"></i> Visitantes recientes</h3>
    <?php if ($recent_visitors): ?>
    <table>
      <thead><tr><th>IP</th><th>Página</th><th>Origen</th><th>Hora</th></tr></thead>
      <tbody>
      <?php foreach ($recent_visitors as $v): ?>
        <tr>
          <td><?= htmlspecialchars((string)($v['ip'] ?? '—')) ?></td>
          <td><span class="page-url" title="<?= htmlspecialchars((string)($v['page'] ?? '')) ?>"><?= htmlspecialchars((string)($v['page'] ?? '')) ?></span></td>
          <td style="font-size:.78rem;color:var(--gray-500)"><?= htmlspecialchars(substr((string)($v['referrer'] ?? '—'), 0, 50)) ?></td>
          <td style="font-size:.78rem"><?= htmlspecialchars(substr((string)($v['created_at'] ?? ''), 11, 8)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <p class="no-data">Sin visitas registradas aún. Navega el sitio y aparecerán aquí.</p>
    <?php endif; ?>
  </div>

</div><!-- /container -->

<script>
// ── Charts ────────────────────────────────────────────────────────────────────
const ordersCtx = document.getElementById('ordersChart').getContext('2d');
new Chart(ordersCtx, {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_values($chart_days)) ?>,
    datasets: [{
      label: 'Pedidos',
      data: <?= json_encode(array_values($chart_orders)) ?>,
      backgroundColor: 'rgba(52,152,219,.7)',
      borderColor: '#3498db',
      borderWidth: 1,
      borderRadius: 4
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
  }
});

const hoursCtx = document.getElementById('hoursChart').getContext('2d');
new Chart(hoursCtx, {
  type: 'line',
  data: {
    labels: Array.from({length:24}, (_,i)=> i + ':00'),
    datasets: [{
      label: 'Visitas',
      data: <?= json_encode(array_values($chart_hours)) ?>,
      fill: true,
      backgroundColor: 'rgba(39,174,96,.15)',
      borderColor: '#27ae60',
      borderWidth: 2,
      tension: 0.4,
      pointRadius: 3
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
  }
});

// ── Auto-refresh countdown ────────────────────────────────────────────────────
let secs = 30;
const el = document.getElementById('countdown');
setInterval(() => {
  secs--;
  el.textContent = secs;
  if (secs <= 0) location.reload();
}, 1000);
</script>
</body>
</html>
