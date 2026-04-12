<?php
// admin/clientes.php — Gestión de Clientes Finales
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();
$pdo = db();

// ── Exportar CSV ────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = $pdo->query("
        SELECT u.id, u.name, u.email, u.phone,
               u.oauth_provider,
               CASE WHEN u.password_hash != '' AND u.password_hash IS NOT NULL THEN 1 ELSE 0 END AS has_pass,
               u.is_active, u.created_at,
               (SELECT MAX(last_activity) FROM user_sessions WHERE user_id = u.id AND revoked = 0) AS last_login,
               (SELECT COUNT(*) FROM orders WHERE buyer_email = u.email) AS total_orders
        FROM users u
        LEFT JOIN entrepreneurs e ON e.user_id = u.id
        WHERE e.user_id IS NULL
        ORDER BY u.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clientes_' . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Nombre', 'Email', 'Teléfono', 'Autenticación', 'Estado', 'Pedidos', 'Registro', 'Último acceso']);
    foreach ($rows as $r) {
        $auth = [];
        if ($r['oauth_provider']) $auth[] = ucfirst($r['oauth_provider']);
        if ($r['has_pass'])       $auth[] = 'Email/Contraseña';
        fputcsv($out, [
            $r['id'], $r['name'], $r['email'], $r['phone'] ?? '',
            implode(' + ', $auth),
            $r['is_active'] ? 'Activo' : 'Inactivo',
            $r['total_orders'],
            $r['created_at'],
            $r['last_login'] ?? '—',
        ]);
    }
    fclose($out);
    exit;
}

// ── Filtros ──────────────────────────────────────────────────────────────────
$search    = trim($_GET['q']    ?? '');
$auth_f    = $_GET['auth']      ?? 'all';   // all | google | facebook | email
$status_f  = $_GET['status']   ?? 'all';   // all | active | inactive
$from_date = $_GET['from']      ?? '';
$to_date   = $_GET['to']        ?? '';

$where  = ["e.user_id IS NULL"];
$params = [];

if ($search !== '') {
    $where[]  = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($auth_f === 'google')   { $where[] = "u.oauth_provider = 'google'"; }
if ($auth_f === 'facebook') { $where[] = "u.oauth_provider = 'facebook'"; }
if ($auth_f === 'email')    { $where[] = "(u.oauth_provider IS NULL OR u.oauth_provider = '') AND u.password_hash != '' AND u.password_hash IS NOT NULL"; }
if ($status_f === 'active')   { $where[] = "u.is_active = 1"; }
if ($status_f === 'inactive') { $where[] = "u.is_active = 0"; }
if ($from_date !== '') { $where[] = "date(u.created_at) >= ?"; $params[] = $from_date; }
if ($to_date   !== '') { $where[] = "date(u.created_at) <= ?"; $params[] = $to_date; }

$sql = "
    SELECT u.id, u.name, u.email, u.phone,
           u.oauth_provider,
           CASE WHEN u.password_hash != '' AND u.password_hash IS NOT NULL THEN 1 ELSE 0 END AS has_pass,
           u.is_active, u.created_at,
           (SELECT MAX(last_activity) FROM user_sessions WHERE user_id = u.id AND revoked = 0) AS last_login,
           (SELECT COUNT(*) FROM orders WHERE buyer_email = u.email) AS total_orders
    FROM users u
    LEFT JOIN entrepreneurs e ON e.user_id = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY u.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Estadísticas ─────────────────────────────────────────────────────────────
$stats = $pdo->query("
    SELECT
        COUNT(u.id)                                                          AS total,
        SUM(u.is_active)                                                     AS activos,
        SUM(CASE WHEN u.oauth_provider = 'google'   THEN 1 ELSE 0 END)      AS google,
        SUM(CASE WHEN u.oauth_provider = 'facebook' THEN 1 ELSE 0 END)      AS facebook,
        SUM(CASE WHEN (u.oauth_provider IS NULL OR u.oauth_provider = '')
                  AND u.password_hash != '' AND u.password_hash IS NOT NULL
                  THEN 1 ELSE 0 END)                                         AS solo_email,
        SUM(CASE WHEN strftime('%Y-%m', u.created_at) = strftime('%Y-%m','now') THEN 1 ELSE 0 END) AS este_mes
    FROM users u
    LEFT JOIN entrepreneurs e ON e.user_id = u.id
    WHERE e.user_id IS NULL
")->fetch(PDO::FETCH_ASSOC);

// Helper — auth badges HTML
function authBadges(string $provider, int $hasPass): string {
    $b = '';
    if ($provider === 'google') {
        $b .= '<span style="display:inline-flex;align-items:center;gap:3px;background:#fff3f3;border:1px solid #fca5a5;color:#dc2626;border-radius:20px;padding:2px 9px;font-size:.75rem;font-weight:600;">
                 <svg viewBox="0 0 24 24" style="width:13px;height:13px;flex-shrink:0"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                 Google
               </span> ';
    } elseif ($provider === 'facebook') {
        $b .= '<span style="display:inline-flex;align-items:center;gap:3px;background:#eff6ff;border:1px solid #93c5fd;color:#1d4ed8;border-radius:20px;padding:2px 9px;font-size:.75rem;font-weight:600;">
                 <i class="fab fa-facebook-f" style="font-size:.7rem"></i> Facebook
               </span> ';
    }
    if ($hasPass) {
        $b .= '<span style="display:inline-flex;align-items:center;gap:3px;background:#f0fdf4;border:1px solid #86efac;color:#15803d;border-radius:20px;padding:2px 9px;font-size:.75rem;font-weight:600;">
                 <i class="fas fa-envelope" style="font-size:.65rem"></i> Correo
               </span>';
    }
    return $b ?: '<span style="color:#9ca3af;font-size:.8rem">—</span>';
}

function fmtDate(?string $d): string {
    if (!$d) return '—';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y H:i', $ts) : $d;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Clientes — Admin CompraTica</title>
  <link rel="stylesheet" href="../assets/style.css?v=24">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    :root {
      --primary: #2c3e50; --accent: #3498db; --success: #27ae60;
      --danger: #e74c3c; --warning: #f39c12;
      --g50:#f9fafb; --g100:#f3f4f6; --g200:#e5e7eb; --g800:#1f2937;
    }
    body { background:var(--g50); font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif; margin:0; }
    .wrap { max-width:1200px; margin:2rem auto; padding:0 1.25rem; }
    /* ── Header ── */
    header { background:linear-gradient(135deg,#2c3e50 0%,#34495e 100%); box-shadow:0 2px 12px rgba(0,0,0,.15); padding:.875rem 1.5rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem; }
    header .logo { font-size:1.1rem; font-weight:700; color:white; display:flex; align-items:center; gap:.6rem; }
    header nav { display:flex; gap:.4rem; flex-wrap:wrap; align-items:center; }
    .nav-btn { display:inline-flex; align-items:center; gap:.4rem; padding:.5rem .875rem; background:rgba(255,255,255,.1); color:white; text-decoration:none; border-radius:6px; font-size:.825rem; font-weight:500; border:1px solid rgba(255,255,255,.2); transition:all .2s; }
    .nav-btn:hover { background:rgba(255,255,255,.2); border-color:rgba(255,255,255,.4); }
    .nav-btn.active { background:rgba(255,255,255,.22); border-color:rgba(255,255,255,.5); font-weight:700; }
    /* ── Stats ── */
    .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:1rem; margin-bottom:1.5rem; }
    .stat { background:white; border-radius:10px; padding:1.1rem 1.25rem; border:1px solid var(--g200); box-shadow:0 1px 3px rgba(0,0,0,.06); }
    .stat .num { font-size:2rem; font-weight:800; line-height:1; }
    .stat .lbl { font-size:.78rem; color:#6b7280; margin-top:.25rem; }
    /* ── Filters ── */
    .filters { background:white; border-radius:10px; border:1px solid var(--g200); padding:1.1rem 1.25rem; margin-bottom:1.25rem; display:flex; gap:.75rem; flex-wrap:wrap; align-items:flex-end; box-shadow:0 1px 3px rgba(0,0,0,.05); }
    .filters label { font-size:.8rem; font-weight:600; color:var(--g800); display:block; margin-bottom:.3rem; }
    .filters input[type=text], .filters input[type=date], .filters select {
      padding:.5rem .75rem; border:1.5px solid var(--g200); border-radius:7px; font-size:.875rem; background:var(--g50); outline:none; transition:border-color .2s;
    }
    .filters input:focus, .filters select:focus { border-color:var(--accent); }
    .filters .f-group { display:flex; flex-direction:column; }
    .btn { display:inline-flex; align-items:center; gap:.4rem; padding:.55rem 1rem; border-radius:7px; font-weight:600; font-size:.875rem; cursor:pointer; border:none; text-decoration:none; transition:all .2s; }
    .btn-primary { background:var(--accent); color:white; }
    .btn-primary:hover { background:#2980b9; }
    .btn-success { background:var(--success); color:white; }
    .btn-success:hover { background:#229954; }
    .btn-sm { padding:.35rem .75rem; font-size:.78rem; }
    /* ── Table ── */
    .card { background:white; border-radius:12px; border:1px solid var(--g200); box-shadow:0 1px 4px rgba(0,0,0,.06); overflow:hidden; }
    .card-header { padding:1rem 1.25rem; border-bottom:1px solid var(--g100); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem; }
    .card-header h2 { margin:0; font-size:1.1rem; color:var(--primary); display:flex; align-items:center; gap:.5rem; }
    .table-wrap { overflow-x:auto; }
    table { width:100%; border-collapse:collapse; font-size:.875rem; }
    thead th { background:var(--g50); color:#374151; font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; padding:.75rem 1rem; border-bottom:2px solid var(--g200); white-space:nowrap; }
    tbody td { padding:.75rem 1rem; border-bottom:1px solid var(--g100); vertical-align:middle; }
    tbody tr:hover { background:#fafafa; }
    tbody tr:last-child td { border-bottom:none; }
    .avatar { width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,#667eea,#764ba2); display:flex; align-items:center; justify-content:center; color:white; font-weight:700; font-size:.875rem; flex-shrink:0; }
    .badge-active { background:#d1fae5; color:#065f46; border-radius:20px; padding:2px 10px; font-size:.75rem; font-weight:600; }
    .badge-inactive { background:#fee2e2; color:#991b1b; border-radius:20px; padding:2px 10px; font-size:.75rem; font-weight:600; }
    .empty { text-align:center; padding:3rem; color:#9ca3af; }
    .empty i { font-size:3.5rem; display:block; margin-bottom:.75rem; }
  </style>
</head>
<body>

<header>
  <div class="logo"><i class="fas fa-shield-alt"></i> Panel de Administración</div>
  <nav>
    <a class="nav-btn" href="../index.php"><i class="fas fa-store"></i> Ver Tienda</a>
    <a class="nav-btn" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a class="nav-btn" href="affiliates.php"><i class="fas fa-user-tie"></i> Afiliados</a>
    <a class="nav-btn" href="emprendedoras.php"><i class="fas fa-store-alt"></i> Emprendedoras</a>
    <a class="nav-btn active" href="clientes.php"><i class="fas fa-users"></i> Clientes</a>
    <a class="nav-btn" href="email_marketing.php"><i class="fas fa-envelope"></i> Email Marketing</a>
    <a class="nav-btn" href="analytics.php"><i class="fas fa-chart-line"></i> Analytics</a>
    <a class="nav-btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a>
  </nav>
</header>

<div class="wrap">

  <h1 style="color:var(--primary);font-size:1.6rem;margin:1.5rem 0 1.25rem;display:flex;align-items:center;gap:.6rem;">
    <i class="fas fa-users"></i> Clientes Finales
  </h1>

  <!-- Stats -->
  <div class="stats">
    <div class="stat">
      <div class="num" style="color:var(--accent)"><?= (int)$stats['total'] ?></div>
      <div class="lbl">Total clientes</div>
    </div>
    <div class="stat">
      <div class="num" style="color:var(--success)"><?= (int)$stats['activos'] ?></div>
      <div class="lbl">Activos</div>
    </div>
    <div class="stat">
      <div class="num" style="color:#f59e0b"><?= (int)$stats['este_mes'] ?></div>
      <div class="lbl">Registros este mes</div>
    </div>
    <div class="stat">
      <div class="num" style="color:#dc2626"><?= (int)$stats['google'] ?></div>
      <div class="lbl">
        <svg viewBox="0 0 24 24" style="width:12px;height:12px;vertical-align:middle"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
        Login con Google
      </div>
    </div>
    <div class="stat">
      <div class="num" style="color:#1d4ed8"><?= (int)$stats['facebook'] ?></div>
      <div class="lbl"><i class="fab fa-facebook-f" style="font-size:.75rem"></i> Login con Facebook</div>
    </div>
    <div class="stat">
      <div class="num" style="color:#15803d"><?= (int)$stats['solo_email'] ?></div>
      <div class="lbl"><i class="fas fa-envelope" style="font-size:.75rem"></i> Solo correo</div>
    </div>
  </div>

  <!-- Filters -->
  <form method="get" action="">
    <div class="filters">
      <div class="f-group">
        <label>Buscar</label>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Nombre, email o teléfono..." style="min-width:220px;">
      </div>
      <div class="f-group">
        <label>Autenticación</label>
        <select name="auth">
          <option value="all"      <?= $auth_f==='all'      ?'selected':'' ?>>Todos</option>
          <option value="google"   <?= $auth_f==='google'   ?'selected':'' ?>>Google</option>
          <option value="facebook" <?= $auth_f==='facebook' ?'selected':'' ?>>Facebook</option>
          <option value="email"    <?= $auth_f==='email'    ?'selected':'' ?>>Solo correo</option>
        </select>
      </div>
      <div class="f-group">
        <label>Estado</label>
        <select name="status">
          <option value="all"      <?= $status_f==='all'      ?'selected':'' ?>>Todos</option>
          <option value="active"   <?= $status_f==='active'   ?'selected':'' ?>>Activos</option>
          <option value="inactive" <?= $status_f==='inactive' ?'selected':'' ?>>Inactivos</option>
        </select>
      </div>
      <div class="f-group">
        <label>Desde</label>
        <input type="date" name="from" value="<?= htmlspecialchars($from_date) ?>">
      </div>
      <div class="f-group">
        <label>Hasta</label>
        <input type="date" name="to" value="<?= htmlspecialchars($to_date) ?>">
      </div>
      <div class="f-group" style="justify-content:flex-end">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrar</button>
      </div>
      <?php if ($search || $auth_f !== 'all' || $status_f !== 'all' || $from_date || $to_date): ?>
        <div class="f-group" style="justify-content:flex-end">
          <a href="clientes.php" class="btn" style="background:var(--g100);color:var(--g800)"><i class="fas fa-times"></i> Limpiar</a>
        </div>
      <?php endif; ?>
    </div>
  </form>

  <!-- Table card -->
  <div class="card">
    <div class="card-header">
      <h2><i class="fas fa-list"></i> <?= count($customers) ?> cliente<?= count($customers) !== 1 ? 's' : '' ?> encontrado<?= count($customers) !== 1 ? 's' : '' ?></h2>
      <a href="clientes.php?export=csv&<?= http_build_query(['q'=>$search,'auth'=>$auth_f,'status'=>$status_f,'from'=>$from_date,'to'=>$to_date]) ?>"
         class="btn btn-success btn-sm">
        <i class="fas fa-file-csv"></i> Exportar CSV
      </a>
    </div>
    <div class="table-wrap">
      <?php if (empty($customers)): ?>
        <div class="empty">
          <i class="fas fa-user-slash"></i>
          <p>No se encontraron clientes con los filtros aplicados.</p>
        </div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Cliente</th>
            <th>Email</th>
            <th>Teléfono</th>
            <th>Autenticación</th>
            <th>Estado</th>
            <th>Pedidos</th>
            <th>Registro</th>
            <th>Último acceso</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($customers as $c): ?>
          <?php
            $initials = mb_strtoupper(mb_substr(trim($c['name']), 0, 2));
            $orders   = (int)$c['total_orders'];
            $authBadges = authBadges((string)($c['oauth_provider'] ?? ''), (int)$c['has_pass']);
          ?>
          <tr>
            <td style="color:#9ca3af;font-size:.8rem"><?= (int)$c['id'] ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:.6rem">
                <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                <span style="font-weight:600"><?= htmlspecialchars($c['name']) ?></span>
              </div>
            </td>
            <td style="color:#4b5563"><?= htmlspecialchars($c['email']) ?></td>
            <td style="color:#6b7280"><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
            <td><?= $authBadges ?></td>
            <td>
              <?php if ($c['is_active']): ?>
                <span class="badge-active"><i class="fas fa-circle" style="font-size:.5rem;vertical-align:middle"></i> Activo</span>
              <?php else: ?>
                <span class="badge-inactive"><i class="fas fa-circle" style="font-size:.5rem;vertical-align:middle"></i> Inactivo</span>
              <?php endif; ?>
            </td>
            <td style="text-align:center">
              <?php if ($orders > 0): ?>
                <span style="background:#dbeafe;color:#1e40af;border-radius:20px;padding:2px 10px;font-size:.78rem;font-weight:700"><?= $orders ?></span>
              <?php else: ?>
                <span style="color:#d1d5db;font-size:.85rem">0</span>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap;font-size:.8rem;color:#6b7280"><?= fmtDate($c['created_at']) ?></td>
            <td style="white-space:nowrap;font-size:.8rem;color:#6b7280"><?= fmtDate($c['last_login']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

</div>
</body>
</html>
