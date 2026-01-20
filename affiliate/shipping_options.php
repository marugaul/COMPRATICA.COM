<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';

aff_require_login();
$pdo = db();
$aff_id = (int)($_SESSION['aff_id'] ?? 0);

$msg = '';
$ok  = '';

// Leer o crear registro base
$row = $pdo->query("SELECT * FROM affiliate_shipping_options WHERE affiliate_id=$aff_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    $pdo->prepare("INSERT INTO affiliate_shipping_options (affiliate_id) VALUES (?)")->execute([$aff_id]);
    $row = $pdo->query("SELECT * FROM affiliate_shipping_options WHERE affiliate_id=$aff_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $enable_pickup = isset($_POST['enable_pickup']) ? 1 : 0;
        $enable_free_shipping = isset($_POST['enable_free_shipping']) ? 1 : 0;
        $enable_uber = isset($_POST['enable_uber']) ? 1 : 0;
        $pickup_instructions = trim($_POST['pickup_instructions'] ?? '');
        $free_shipping_min_amount = (float)($_POST['free_shipping_min_amount'] ?? 0);

        $st = $pdo->prepare("UPDATE affiliate_shipping_options
                             SET enable_pickup=?, enable_free_shipping=?, enable_uber=?,
                                 pickup_instructions=?, free_shipping_min_amount=?,
                                 updated_at=datetime('now','localtime')
                             WHERE affiliate_id=?");
        $st->execute([
            $enable_pickup,
            $enable_free_shipping,
            $enable_uber,
            $pickup_instructions,
            $free_shipping_min_amount,
            $aff_id
        ]);
        $ok = 'Opciones de envío actualizadas correctamente.';
        $row = $pdo->query("SELECT * FROM affiliate_shipping_options WHERE affiliate_id=$aff_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $msg = 'Error: '.$e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>🚚 Opciones de Envío del Afiliado</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../assets/style.css?v=20251014a">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .shipping-option {
      background: #f8f9fa;
      border: 2px solid #e9ecef;
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      transition: all 0.3s ease;
    }
    .shipping-option.active {
      border-color: #007bff;
      background: #e7f3ff;
    }
    .shipping-option-header {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1rem;
    }
    .shipping-option-icon {
      width: 50px;
      height: 50px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
    }
    .icon-pickup { background: #d1ecf1; color: #0c5460; }
    .icon-free { background: #d4edda; color: #155724; }
    .icon-uber { background: #fff3cd; color: #856404; }
    .shipping-option-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: #2c3e50;
      margin: 0;
    }
    .shipping-option-description {
      color: #6c757d;
      font-size: 0.9rem;
      margin: 0.5rem 0 1rem 0;
    }
    .checkbox-label {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-weight: 500;
      cursor: pointer;
      padding: 0.75rem;
      border-radius: 8px;
      transition: background 0.2s ease;
    }
    .checkbox-label:hover {
      background: rgba(0,123,255,0.05);
    }
    .checkbox-label input[type="checkbox"] {
      width: 20px;
      height: 20px;
      cursor: pointer;
    }
    .extra-fields {
      margin-top: 1rem;
      padding-top: 1rem;
      border-top: 1px solid #dee2e6;
      display: none;
    }
    .extra-fields.visible {
      display: block;
    }
    .form-help {
      font-size: 0.85rem;
      color: #6c757d;
      margin-top: 0.5rem;
    }
    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      padding: 1rem 2rem;
      font-size: 1.1rem;
      font-weight: 600;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s ease;
      width: 100%;
      margin-top: 1.5rem;
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
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
    <a class="nav-btn" href="../index.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-store"></i>
      <span>Ver Tienda</span>
    </a>
  </nav>
</header>

<div class="container">
  <?php if ($msg): ?><div class="alert" style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($ok): ?><div class="success" style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($ok) ?></div><?php endif; ?>

  <form class="form" method="post" style="max-width: 800px; margin: 0 auto;">
    <h2 style="text-align: center; margin-bottom: 2rem; color: #2c3e50;">
      <i class="fas fa-shipping-fast"></i> Configura tus Opciones de Envío
    </h2>
    <p style="text-align: center; color: #6c757d; margin-bottom: 2rem;">
      Selecciona qué opciones de envío quieres ofrecer a tus clientes en el checkout
    </p>

    <!-- OPCIÓN 1: Recoger en Tienda -->
    <div class="shipping-option <?= $row['enable_pickup'] ? 'active' : '' ?>" id="option-pickup">
      <div class="shipping-option-header">
        <div class="shipping-option-icon icon-pickup">
          <i class="fas fa-store"></i>
        </div>
        <div style="flex: 1;">
          <h3 class="shipping-option-title">Recoger en Tienda</h3>
          <p class="shipping-option-description">El cliente recoge su pedido en tu ubicación física (sin costo adicional)</p>
        </div>
      </div>
      <label class="checkbox-label">
        <input type="checkbox" name="enable_pickup" <?= $row['enable_pickup'] ? 'checked' : '' ?> onchange="toggleOption('pickup', this.checked)">
        <span>Activar Recoger en Tienda</span>
      </label>

      <div class="extra-fields <?= $row['enable_pickup'] ? 'visible' : '' ?>" id="fields-pickup">
        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">
          <i class="fas fa-info-circle"></i> Instrucciones para el cliente:
        </label>
        <textarea name="pickup_instructions" class="input" rows="3" placeholder="Ej: Pasar por nuestro local en San José, horario de 9am a 6pm de lunes a viernes"><?= htmlspecialchars($row['pickup_instructions'] ?? '') ?></textarea>
        <p class="form-help">Estas instrucciones se mostrarán al cliente en el checkout</p>
      </div>
    </div>

    <!-- OPCIÓN 2: Envío Gratis -->
    <div class="shipping-option <?= $row['enable_free_shipping'] ? 'active' : '' ?>" id="option-free">
      <div class="shipping-option-header">
        <div class="shipping-option-icon icon-free">
          <i class="fas fa-gift"></i>
        </div>
        <div style="flex: 1;">
          <h3 class="shipping-option-title">Envío Gratis</h3>
          <p class="shipping-option-description">Ofrece envío gratuito a tus clientes (tú asumes el costo del envío)</p>
        </div>
      </div>
      <label class="checkbox-label">
        <input type="checkbox" name="enable_free_shipping" <?= $row['enable_free_shipping'] ? 'checked' : '' ?> onchange="toggleOption('free', this.checked)">
        <span>Activar Envío Gratis</span>
      </label>

      <div class="extra-fields <?= $row['enable_free_shipping'] ? 'visible' : '' ?>" id="fields-free">
        <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">
          <i class="fas fa-coins"></i> Monto mínimo de compra (opcional):
        </label>
        <input type="number" name="free_shipping_min_amount" class="input" min="0" step="0.01"
               value="<?= htmlspecialchars($row['free_shipping_min_amount'] ?? '0') ?>"
               placeholder="0.00">
        <p class="form-help">Deja en 0 para ofrecer envío gratis sin monto mínimo. Si configuras un monto, el envío gratis solo aplicará si el subtotal es mayor o igual a este valor.</p>
      </div>
    </div>

    <!-- OPCIÓN 3: Envío por Uber -->
    <div class="shipping-option <?= $row['enable_uber'] ? 'active' : '' ?>" id="option-uber">
      <div class="shipping-option-header">
        <div class="shipping-option-icon icon-uber">
          <i class="fas fa-car"></i>
        </div>
        <div style="flex: 1;">
          <h3 class="shipping-option-title">Envío por Uber</h3>
          <p class="shipping-option-description">Cotización automática de envío usando Uber Direct (el cliente paga el envío)</p>
        </div>
      </div>
      <label class="checkbox-label">
        <input type="checkbox" name="enable_uber" <?= $row['enable_uber'] ? 'checked' : '' ?> onchange="toggleOption('uber', this.checked)">
        <span>Activar Envío por Uber</span>
      </label>

      <div class="extra-fields <?= $row['enable_uber'] ? 'visible' : '' ?>" id="fields-uber">
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 1rem; border-radius: 4px;">
          <p style="margin: 0; color: #856404;">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Importante:</strong> Para usar envío por Uber, debes tener configurada la ubicación de recogida en cada uno de tus espacios de venta.
          </p>
          <a href="sales.php" style="display: inline-block; margin-top: 0.75rem; color: #856404; font-weight: 600;">
            <i class="fas fa-map-marker-alt"></i> Configurar ubicaciones →
          </a>
        </div>
      </div>
    </div>

    <button class="btn-primary" type="submit">
      <i class="fas fa-save"></i> Guardar Configuración
    </button>
  </form>
</div>

<script>
function toggleOption(type, checked) {
  const option = document.getElementById('option-' + type);
  const fields = document.getElementById('fields-' + type);

  if (checked) {
    option.classList.add('active');
    fields.classList.add('visible');
  } else {
    option.classList.remove('active');
    fields.classList.remove('visible');
  }
}
</script>
</body>
</html>
