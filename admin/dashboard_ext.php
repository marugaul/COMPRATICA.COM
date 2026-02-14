<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
$pdo = db();

$sql = "
SELECT
  p.*,
  s.id   AS sale_id,
  s.title AS sale_title,
  s.is_active AS sale_active,
  a.id   AS aff_id,
  a.email AS aff_email,
  (
    SELECT status
    FROM sale_fees f
    WHERE f.sale_id = s.id
    ORDER BY f.id DESC
    LIMIT 1
  ) AS fee_status
FROM products p
LEFT JOIN sales s      ON s.id = p.sale_id
LEFT JOIN affiliates a ON a.id = s.affiliate_id
ORDER BY p.created_at DESC
LIMIT 200;
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Productos (Extendido) - <?= h(APP_NAME ?? 'Admin') ?></title>
<link rel="stylesheet" href="../assets/style.css?v=24">
<link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
<style>
  :root {
    --primary: #2c3e50;
    --primary-light: #34495e;
    --accent: #3498db;
    --success: #27ae60;
    --danger: #e74c3c;
    --warning: #f39c12;
    --gray-50: #f9fafb;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-300: #d1d5db;
    --gray-600: #6b7280;
    --gray-800: #1f2937;
  }

  body {
    background: var(--gray-50);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  }

  .container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
  }

  .section {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid var(--gray-200);
  }

  .section-title {
    font-size: 1.5rem;
    color: var(--primary);
    margin: 0 0 1.5rem 0;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--gray-100);
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  .table-wrap {
    overflow-x: auto;
  }

  .table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
  }

  .table thead {
    background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-50) 100%);
  }

  .table th {
    padding: 1rem 0.75rem;
    text-align: left;
    font-weight: 600;
    color: var(--gray-800);
    border-bottom: 2px solid var(--gray-300);
    white-space: nowrap;
  }

  .table td {
    padding: 1rem 0.75rem;
    border-bottom: 1px solid var(--gray-200);
    vertical-align: middle;
  }

  .table tbody tr {
    transition: background 0.2s ease;
  }

  .table tbody tr:hover {
    background: var(--gray-50);
  }

  .small {
    font-size: 0.85rem;
    color: var(--gray-600);
  }

  @media (max-width: 768px) {
    .container {
      padding: 1rem;
    }
  }
</style>
</head>
<body>
<header class="header" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); box-shadow: 0 2px 12px rgba(0,0,0,0.1); padding: 1rem 2rem;">
  <div class="logo" style="font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; color: white;">
    <i class="fas fa-shield-alt"></i>
    Panel de Administración
  </div>
  <nav style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
    <a class="nav-btn" href="../index.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-store"></i>
      <span>Ver Tienda</span>
    </a>
    <a class="nav-btn" href="dashboard.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-tachometer-alt"></i>
      <span>Dashboard</span>
    </a>
    <a class="nav-btn" href="dashboard_ext.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-box-open"></i>
      <span>Productos (Ext)</span>
    </a>
    <a class="nav-btn" href="sales_admin.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-store-alt"></i>
      <span>Espacios</span>
    </a>
    <a class="nav-btn" href="affiliates.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-users"></i>
      <span>Afiliados</span>
    </a>
    <a class="nav-btn" href="settings_fee.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-dollar-sign"></i>
      <span>Costo Espacio</span>
    </a>
    <a class="nav-btn" href="email_marketing.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-envelope"></i>
      <span>Email Marketing</span>
    </a>
    <a class="nav-btn" href="../tools/sql_exec.php" target="_blank" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-database"></i>
      <span>SQL Tools</span>
    </a>
    <a class="nav-btn" href="logout.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-sign-out-alt"></i>
      <span>Salir</span>
    </a>
  </nav>
</header>

<div class="container">
  <h2 style="margin-bottom: 2rem; color: var(--primary);">
    <i class="fas fa-box-open"></i> Productos (Vista Extendida)
  </h2>

  <div class="section">
    <h3 class="section-title">
      <i class="fas fa-list"></i> Productos (<?= count($rows) ?>)
      <span style="font-size: 0.9rem; font-weight: 400; color: var(--gray-600); margin-left: auto;">últimos 200</span>
    </h3>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Espacio</th>
            <th>Afiliado</th>
            <th>Fee</th>
            <th>Activo (Esp.)</th>
            <th>Stock</th>
            <th>Precio</th>
            <th>Creado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): ?>
          <tr>
            <td><strong>#<?= (int)$r['id'] ?></strong></td>
            <td><?= h($r['name']) ?></td>
            <td class="small">
              <?= h($r['sale_title'] ?: '—') ?>
              <?php if(!empty($r['sale_id'])): ?>
                <span style="color: var(--gray-600);">(#<?= (int)$r['sale_id'] ?>)</span>
              <?php endif; ?>
            </td>
            <td class="small">
              <?= h($r['aff_email'] ?: '—') ?>
              <?php if(!empty($r['aff_id'])): ?>
                <span style="color: var(--gray-600);">(#<?= (int)$r['aff_id'] ?>)</span>
              <?php endif; ?>
            </td>
            <td><?= h($r['fee_status'] ?: '—') ?></td>
            <td><?= !empty($r['sale_active']) ? '<span style="color: var(--success);">✅ Sí</span>' : '<span style="color: var(--danger);">❌ No</span>' ?></td>
            <td><strong><?= (int)$r['stock'] ?></strong></td>
            <td>
              <?php
                $cur = strtoupper($r['currency'] ?? 'CRC');
                $sym = ($cur === 'USD') ? '$' : '₡';
                $fmt = ($cur === 'USD')
                  ? number_format((float)$r['price'], 2, '.', ',')
                  : number_format((float)$r['price'], 0, ',', '.');
              ?>
              <strong><?= h($sym . $fmt) ?></strong>
            </td>
            <td class="small"><?= h($r['created_at'] ?: '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
