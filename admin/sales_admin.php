<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
$pdo = db();
$msg = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  try {
    if (isset($_POST['toggle'])) {
      $sale_id = (int)$_POST['sale_id'];
      $new = (int)$_POST['new_state'];
      $pdo->prepare("UPDATE sales SET is_active=?, updated_at=datetime('now') WHERE id=?")
          ->execute([$new, $sale_id]);
      $msg = 'Estado de espacio actualizado.';
    } elseif (isset($_POST['approve_fee'])) {
      $fee_id = (int)$_POST['fee_id'];
      $fee = $pdo->prepare("SELECT sale_id FROM sale_fees WHERE id=?");
      $fee->execute([$fee_id]);
      $sale_id = (int)$fee->fetchColumn();
      if ($sale_id) {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE sale_fees SET status='Pagado', updated_at=datetime('now') WHERE id=?")
            ->execute([$fee_id]);
        $pdo->prepare("UPDATE sales SET is_active=1, updated_at=datetime('now') WHERE id=?")
            ->execute([$sale_id]);
        $pdo->commit();
        $msg = 'Pago aprobado y espacio activado.';
      } else {
        $msg = 'No se encontró el sale_id del fee.';
      }
    }
  } catch (Throwable $e) {
    $msg = 'Error: '.$e->getMessage();
  }
}

$sql = "
SELECT s.*, a.email AS aff_email,
  (SELECT status FROM sale_fees f WHERE f.sale_id=s.id ORDER BY f.id DESC LIMIT 1) AS fee_status,
  (SELECT id FROM sale_fees f WHERE f.sale_id=s.id ORDER BY f.id DESC LIMIT 1) AS fee_id
FROM sales s
LEFT JOIN affiliates a ON a.id=s.affiliate_id
ORDER BY datetime(s.created_at) DESC
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
<title>Espacios - <?= h(APP_NAME ?? 'Admin') ?></title>
<link rel="stylesheet" href="../assets/style.css?v=24">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

  .success {
    background: rgba(39, 174, 96, 0.1);
    border: 1px solid rgba(39, 174, 96, 0.3);
    border-left: 4px solid var(--success);
    color: #155724;
    padding: 1rem 1.25rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
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

  .btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 500;
    font-size: 0.875rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    border: none;
    cursor: pointer;
    background: var(--gray-200);
    color: var(--gray-800);
    margin-right: 0.5rem;
  }

  .btn:hover {
    background: var(--gray-300);
    transform: translateY(-1px);
  }

  .btn.primary {
    background: linear-gradient(135deg, var(--accent) 0%, #2980b9 100%);
    color: white;
    box-shadow: 0 2px 6px rgba(52, 152, 219, 0.3);
  }

  .btn.success {
    background: linear-gradient(135deg, var(--success) 0%, #229954 100%);
    color: white;
    box-shadow: 0 2px 6px rgba(39, 174, 96, 0.3);
  }

  .btn.danger {
    background: linear-gradient(135deg, var(--danger) 0%, #c0392b 100%);
    color: white;
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
    <a class="nav-btn" href="../index" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
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
  <?php if($msg): ?>
    <div class="success">
      <i class="fas fa-check-circle"></i>
      <strong>Aviso:</strong> <?= h($msg) ?>
    </div>
  <?php endif; ?>

  <h2 style="margin-bottom: 2rem; color: var(--primary);">
    <i class="fas fa-store-alt"></i> Gestión de Espacios
  </h2>

  <div class="section">
    <h3 class="section-title">
      <i class="fas fa-list"></i> Espacios (<?= count($rows) ?>)
    </h3>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Título</th>
            <th>Afiliado</th>
            <th>Ubicación</th>
            <th>Privada</th>
            <th>Código</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th>Fee</th>
            <th>Activo</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): ?>
          <tr>
            <td><strong>#<?= (int)$r['id'] ?></strong></td>
            <td><?= h($r['title'] ?? '—') ?></td>
            <td class="small"><?= h($r['aff_email'] ?: '—') ?></td>
            <td class="small">
              <?php if (!empty($r['location'])): ?>
                <i class="fas fa-map-marker-alt" style="color: var(--danger);"></i>
                <?= h($r['location']) ?>
              <?php else: ?>
                <span style="color: var(--gray-600);">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($r['is_private'])): ?>
                <span style="background: linear-gradient(135deg, rgba(243, 156, 18, 0.1), rgba(243, 156, 18, 0.05)); border: 1px solid rgba(243, 156, 18, 0.3); color: #d68910; padding: 0.25rem 0.625rem; border-radius: 12px; font-size: 0.8125rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem;">
                  <i class="fas fa-lock"></i>
                </span>
              <?php else: ?>
                <span style="color: var(--gray-600);">Pública</span>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php if (!empty($r['is_private']) && !empty($r['access_code'])): ?>
                <code style="background: var(--gray-100); padding: 0.25rem 0.5rem; border-radius: 4px; font-family: monospace; font-weight: 700; color: var(--primary);"><?= h($r['access_code']) ?></code>
              <?php else: ?>
                <span style="color: var(--gray-600);">—</span>
              <?php endif; ?>
            </td>
            <td class="small"><?= h($r['start_at'] ?: '—') ?></td>
            <td class="small"><?= h($r['end_at'] ?: '—') ?></td>
            <td><?= h($r['fee_status'] ?: '—') ?></td>
            <td><?= !empty($r['is_active']) ? '<span style="color: var(--success);">✅ Sí</span>' : '<span style="color: var(--danger);">❌ No</span>' ?></td>
            <td>
              <form method="post" style="display:inline">
                <input type="hidden" name="sale_id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="new_state" value="<?= !empty($r['is_active'])?0:1 ?>">
                <button class="btn <?= !empty($r['is_active'])?'danger':'success' ?>" name="toggle" value="1">
                  <i class="fas fa-<?= !empty($r['is_active'])?'ban':'check' ?>"></i>
                  <?= !empty($r['is_active'])?'Desactivar':'Activar' ?>
                </button>
              </form>
              <?php if(!empty($r['fee_id']) && ($r['fee_status']??'')!=='Pagado'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="fee_id" value="<?= (int)$r['fee_id'] ?>">
                  <button class="btn primary" name="approve_fee" value="1">
                    <i class="fas fa-check-double"></i>
                    Aprobar pago SINPE
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
