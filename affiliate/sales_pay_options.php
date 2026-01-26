<?php
// affiliate/sales_pay_options.php
// Permite al afiliado ver y editar sus métodos de pago: SINPE Móvil y PayPal (independientes del admin)

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';

aff_require_login();
$pdo     = db();
$aff_id  = (int)($_SESSION['aff_id'] ?? 0);
$msg     = '';
$ok_note = '';

// Crear registro base si no existe
$row = $pdo->query("SELECT * FROM affiliate_payment_methods WHERE affiliate_id=$aff_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    $pdo->prepare("INSERT INTO affiliate_payment_methods (affiliate_id) VALUES (?)")->execute([$aff_id]);
    $row = $pdo->query("SELECT * FROM affiliate_payment_methods WHERE affiliate_id=$aff_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sinpe_phone   = trim($_POST['sinpe_phone'] ?? '');
        $paypal_email  = trim($_POST['paypal_email'] ?? '');
        $active_sinpe  = isset($_POST['active_sinpe']) ? 1 : 0;
        $active_paypal = isset($_POST['active_paypal']) ? 1 : 0;

        $sql = "UPDATE affiliate_payment_methods
                SET sinpe_phone=?, paypal_email=?, active_sinpe=?, active_paypal=?, updated_at=datetime('now','localtime')
                WHERE affiliate_id=?";
        $pdo->prepare($sql)->execute([$sinpe_phone, $paypal_email, $active_sinpe, $active_paypal, $aff_id]);

        $ok_note = "Métodos de pago actualizados correctamente.";
        $row = $pdo->query("SELECT * FROM affiliate_payment_methods WHERE affiliate_id=$aff_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $msg = 'Error: ' . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Métodos de Pago - Afiliados</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/style.css?v=20251014a">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* Variables de color corporativas */
    :root {
      --primary: #2c3e50;
      --primary-light: #34495e;
      --accent: #3498db;
      --accent-hover: #2980b9;
      --success: #27ae60;
      --warning: #f39c12;
      --danger: #e74c3c;
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

    /* Header empresarial */
    .header {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      box-shadow: 0 2px 12px rgba(0,0,0,0.1);
      padding: 1.5rem 2rem;
    }

    .header .logo {
      font-size: 1.25rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    /* Cards mejorados */
    .card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
      border: 1px solid var(--gray-200);
      padding: 2rem;
      margin-bottom: 2rem;
    }

    .card h3 {
      color: var(--primary);
      font-size: 1.5rem;
      font-weight: 600;
      margin: 0 0 1.5rem 0;
      padding-bottom: 1rem;
      border-bottom: 2px solid var(--gray-100);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .pay-card {
      background: var(--gray-50);
      border: 2px solid var(--gray-200);
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      transition: all 0.3s ease;
    }

    .pay-card:hover {
      border-color: var(--accent);
      box-shadow: 0 2px 8px rgba(52, 152, 219, 0.15);
    }

    .pay-label {
      font-weight: 600;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      color: var(--gray-800);
      font-size: 1.1rem;
      cursor: pointer;
    }

    .pay-label input[type="checkbox"] {
      width: 20px;
      height: 20px;
      cursor: pointer;
    }

    .form .input {
      border: 2px solid var(--gray-200);
      border-radius: 8px;
      padding: 0.75rem 1rem;
      font-size: 1rem;
      transition: all 0.3s ease;
      width: 100%;
      margin-bottom: 0.5rem;
    }

    .form .input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
      outline: none;
    }

    .btn {
      padding: 0.625rem 1.25rem;
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
    }

    .btn.primary {
      background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
      color: white;
      box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
      padding: 1rem 2rem;
      font-size: 1rem;
    }

    .btn.primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
    }

    .alert {
      background: rgba(231, 76, 60, 0.1);
      border: 1px solid rgba(231, 76, 60, 0.3);
      border-left: 4px solid var(--danger);
      color: #c0392b;
      padding: 1rem 1.25rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
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

    .preview-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border-radius: 12px;
      padding: 1.5rem;
      margin-top: 2rem;
    }

    .preview-card h4 {
      margin: 0 0 1rem 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 1.2rem;
    }

    .preview-card ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .preview-card li {
      padding: 0.75rem 1rem;
      background: rgba(255,255,255,0.1);
      border-radius: 8px;
      margin-bottom: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    small {
      color: var(--gray-600);
      font-size: 0.875rem;
      display: block;
      margin-top: 0.25rem;
    }
  </style>
</head>
<body>
<header class="header" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); box-shadow: 0 2px 12px rgba(0,0,0,0.1); padding: 1rem 2rem;">
  <div class="logo" style="font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; color: white;">
    <i class="fas fa-user-tie"></i>
    Panel de Afiliado
  </div>
  <nav style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
    <a class="nav-btn" href="dashboard.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-th-large"></i>
      <span>Dashboard</span>
    </a>
    <a class="nav-btn" href="sales.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-store-alt"></i>
      <span>Espacios</span>
    </a>
    <a class="nav-btn" href="../index" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-store"></i>
      <span>Ver Tienda</span>
    </a>
  </nav>
</header>

<div class="container">
  <?php if ($msg): ?><div class="alert"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($ok_note): ?><div class="success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($ok_note) ?></div><?php endif; ?>

  <div class="card">
    <h3><i class="fas fa-credit-card"></i> Configurar Métodos de Pago</h3>

    <form method="post" class="form">
      <div class="pay-card">
        <label class="pay-label">
          <input type="checkbox" name="active_sinpe" <?= $row['active_sinpe'] ? 'checked' : '' ?>>
          <i class="fas fa-mobile-alt"></i>
          Activar SINPE Móvil
        </label>
        <input type="text" class="input" name="sinpe_phone" placeholder="Teléfono SINPE (Ej: 8888-8888)" value="<?= htmlspecialchars($row['sinpe_phone'] ?? '') ?>">
        <small>El teléfono donde recibirás transferencias SINPE Móvil de tus clientes.</small>
      </div>

      <div class="pay-card">
        <label class="pay-label">
          <input type="checkbox" name="active_paypal" <?= $row['active_paypal'] ? 'checked' : '' ?>>
          <i class="fab fa-paypal"></i>
          Activar PayPal
        </label>
        <input type="email" class="input" name="paypal_email" placeholder="Correo PayPal (Ej: micorreo@paypal.com)" value="<?= htmlspecialchars($row['paypal_email'] ?? '') ?>">
        <small>Correo de tu cuenta PayPal. Los clientes pueden pagar con su cuenta o tarjeta sin registrarse.</small>
      </div>

      <button type="submit" class="btn primary">
        <i class="fas fa-save"></i>
        Guardar Métodos de Pago
      </button>
    </form>

    <div class="preview-card">
      <h4><i class="fas fa-eye"></i> Vista Previa de tus Métodos Activos</h4>
      <ul>
        <?php if ($row['active_sinpe'] && !empty($row['sinpe_phone'])): ?>
          <li>
            <i class="fas fa-mobile-alt"></i>
            <strong>SINPE Móvil activo</strong> — Teléfono: <?= htmlspecialchars($row['sinpe_phone']) ?>
          </li>
        <?php endif; ?>
        <?php if ($row['active_paypal'] && !empty($row['paypal_email'])): ?>
          <li>
            <i class="fab fa-paypal"></i>
            <strong>PayPal activo</strong> — Correo: <?= htmlspecialchars($row['paypal_email']) ?>
          </li>
        <?php endif; ?>
        <?php if ((!$row['active_sinpe'] || empty($row['sinpe_phone'])) && (!$row['active_paypal'] || empty($row['paypal_email']))): ?>
          <li style="opacity: 0.7;">
            <i class="fas fa-info-circle"></i>
            <em>No tienes métodos de pago activos actualmente.</em>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</div>
</body>
</html>
