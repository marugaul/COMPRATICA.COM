<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
$pdo = db();
$msg = '';

function get_sale_fee_crc(PDO $pdo, $default = 2000) {
  try {
    $v = $pdo->query("SELECT sale_fee_crc FROM settings LIMIT 1")->fetchColumn();
    return ($v !== false && $v !== null) ? (int)$v : (int)$default;
  } catch (Throwable $e) { return (int)$default; }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_settings'])) {
  $fee = max(0, (int)($_POST['SALE_FEE_CRC'] ?? 2000));
  $pdo->prepare("UPDATE settings SET sale_fee_crc=?")->execute([$fee]);
  $msg = 'Ajustes guardados correctamente.';
}

$fee_val = get_sale_fee_crc($pdo);

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
<title>Costo por Espacio - <?= h(APP_NAME ?? 'Admin') ?></title>
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

  .alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    border: 1px solid #34d399;
    color: #065f46;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
  }

  .form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
  }

  .form-group label {
    font-weight: 600;
    color: var(--gray-800);
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .form-description {
    font-size: 0.875rem;
    color: var(--gray-600);
    margin-top: 0.25rem;
    line-height: 1.5;
  }

  .input {
    padding: 0.875rem;
    border: 1px solid var(--gray-300);
    border-radius: 6px;
    font-size: 1.1rem;
    transition: all 0.2s ease;
    max-width: 400px;
    font-weight: 500;
  }

  .input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
  }

  .btn {
    padding: 0.875rem 1.75rem;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    text-decoration: none;
    color: white;
  }

  .btn.primary {
    background: linear-gradient(135deg, var(--success) 0%, #229954 100%);
    box-shadow: 0 2px 8px rgba(39, 174, 96, 0.3);
  }

  .btn.primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4);
  }

  .info-card {
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    border: 1px solid #60a5fa;
    border-radius: 8px;
    padding: 1.25rem;
    margin-top: 1.5rem;
  }

  .info-card-title {
    font-weight: 600;
    color: #1e40af;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .info-card-text {
    color: #1e3a8a;
    font-size: 0.9rem;
    line-height: 1.6;
  }

  @media (max-width: 768px) {
    .container {
      padding: 1rem;
    }
    .input {
      max-width: 100%;
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
    <i class="fas fa-cog"></i> Configuración de Costos
  </h2>

  <?php if($msg): ?>
  <div class="alert">
    <i class="fas fa-check-circle"></i>
    <span><?= h($msg) ?></span>
  </div>
  <?php endif; ?>

  <div class="section">
    <h3 class="section-title">
      <i class="fas fa-dollar-sign"></i> Costo por Espacio de Venta
    </h3>
    <form method="post">
      <div class="form-group">
        <label>
          <i class="fas fa-money-bill-wave"></i> Costo por Espacio (CRC)
        </label>
        <input class="input" type="number" name="SALE_FEE_CRC" value="<?= (int)$fee_val ?>" min="0" step="1">
        <div class="form-description">
          Este es el monto en colones costarricenses (CRC) que se cobrará a cada afiliado por mantener un espacio de venta activo. Este costo se aplicará de manera recurrente según la política de cobro establecida.
        </div>
      </div>
      <button class="btn primary" name="save_settings" value="1">
        <i class="fas fa-save"></i>
        Guardar Configuración
      </button>
    </form>

    <div class="info-card">
      <div class="info-card-title">
        <i class="fas fa-info-circle"></i>
        Información Importante
      </div>
      <div class="info-card-text">
        <strong>Nota:</strong> Este valor afecta directamente el costo que los afiliados deben pagar por tener un espacio de venta en la plataforma. Cualquier cambio se aplicará a los nuevos cobros generados. Asegúrese de comunicar cualquier modificación a los afiliados con anticipación.
      </div>
    </div>
  </div>
</div>
</body>
</html>
