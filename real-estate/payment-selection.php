<?php
// real-estate/payment-selection.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Verificar autenticación
$agent_id = (int)($_SESSION['agent_id'] ?? $_SESSION['user_id'] ?? $_SESSION['uid'] ?? 0);
if ($agent_id <= 0) {
  header('Location: login.php');
  exit;
}

$pdo = db();
$agent_name = $_SESSION['agent_name'] ?? $_SESSION['user_name'] ?? $_SESSION['name'] ?? 'Usuario';
$listing_id = (int)($_GET['listing_id'] ?? 0);

if ($listing_id <= 0) {
  header('Location: dashboard.php');
  exit;
}

// Obtener información de la publicación y el plan
$stmt = $pdo->prepare("
  SELECT l.*, p.name as plan_name, p.price_usd, p.price_crc, p.payment_methods, p.duration_days
  FROM real_estate_listings l
  LEFT JOIN listing_pricing p ON l.pricing_plan_id = p.id
  WHERE l.id = ? AND l.agent_id = ?
  LIMIT 1
");
$stmt->execute([$listing_id, $agent_id]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$listing) {
  header('Location: dashboard.php');
  exit;
}

// Si ya está confirmado, redirigir al dashboard
if ($listing['payment_status'] === 'confirmed') {
  header('Location: dashboard.php?msg=already_paid');
  exit;
}

// Si el plan es gratuito, activar y redirigir
if ((float)($listing['price_usd'] ?? 0) <= 0 && (float)($listing['price_crc'] ?? 0) <= 0) {
  $pdo->prepare("UPDATE real_estate_listings SET payment_status='free', is_active=1 WHERE id=?")->execute([$listing_id]);
  header('Location: dashboard.php?msg=activated');
  exit;
}

// Métodos disponibles — si no hay nada configurado, mostrar los tres
$rawMethods = trim($listing['payment_methods'] ?? '');
$payment_methods = $rawMethods !== '' ? array_map('trim', explode(',', $rawMethods)) : ['sinpe', 'paypal', 'swiftpay'];
$has_sinpe    = in_array('sinpe',    $payment_methods);
$has_paypal   = in_array('paypal',   $payment_methods);
$has_swiftpay = in_array('swiftpay', $payment_methods);

// Monto según moneda
$amount_usd = (float)($listing['price_usd'] ?? 0);
$amount_crc = (float)($listing['price_crc'] ?? 0);

// Para SwiftPay: preferir CRC, si no USD
$sp_currency = $amount_crc > 0 ? 'CRC' : 'USD';
$sp_amount   = $amount_crc > 0 ? number_format($amount_crc, 2, '.', '') : number_format($amount_usd, 2, '.', '');
$sp_desc     = 'Publicación Bienes Raíces: ' . ($listing['title'] ?? 'sin título');
$sp_success  = '/real-estate/dashboard.php?msg=payment_success';
$sp_cancel   = '/real-estate/payment-selection.php?listing_id=' . $listing_id;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pago de Publicación — <?php echo APP_NAME; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <?php if ($has_paypal && defined('PAYPAL_CLIENT_ID') && PAYPAL_CLIENT_ID): ?>
  <script src="https://www.paypal.com/sdk/js?client-id=<?= PAYPAL_CLIENT_ID ?>&currency=USD&enable-funding=card" defer></script>
  <?php endif; ?>
  <style>
    :root {
      --primary: #002b7f;
      --primary-light: #0041b8;
      --success: #27ae60;
      --warning: #f39c12;
      --danger: #e74c3c;
      --white: #fff;
      --dark: #1a1a1a;
      --gray-700: #4a5568;
      --gray-300: #cbd5e0;
      --gray-100: #f7fafc;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
    }
    .container {
      max-width: 740px;
      width: 100%;
      background: var(--white);
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      overflow: hidden;
    }
    .header {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: var(--white);
      padding: 2rem;
      text-align: center;
    }
    .header h1 { font-size: 1.75rem; margin-bottom: .5rem; font-weight: 800; }
    .header p { opacity: .9; }
    .content { padding: 2rem; }
    .success-icon { text-align: center; margin-bottom: 1.5rem; }
    .success-icon i { font-size: 4rem; color: var(--success); }
    .info-box {
      background: var(--gray-100);
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 2rem;
      border-left: 4px solid var(--primary);
    }
    .info-box h3 { color: var(--primary); margin-bottom: .75rem; display: flex; align-items: center; gap: .5rem; }
    .info-item { display: flex; justify-content: space-between; padding: .5rem 0; border-bottom: 1px solid var(--gray-300); }
    .info-item:last-child { border-bottom: none; }
    .info-label { color: var(--gray-700); font-weight: 500; }
    .info-value { font-weight: 700; }
    /* ── Métodos de pago ───────────────────────────── */
    .payment-options h3 { font-size: 1.2rem; font-weight: 700; text-align: center; margin-bottom: 1.5rem; }
    .method-card {
      border: 2px solid var(--gray-300);
      border-radius: 16px;
      overflow: hidden;
      margin-bottom: 1.5rem;
      transition: border-color .2s, box-shadow .2s;
    }
    .method-header {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1.25rem 1.5rem;
      cursor: pointer;
      user-select: none;
      background: var(--gray-100);
    }
    .method-header:hover { background: #eef2ff; }
    .method-icon { font-size: 2rem; width: 40px; text-align: center; }
    .method-icon.sinpe    { color: #0277bd; }
    .method-icon.paypal   { color: #0070ba; }
    .method-icon.swiftpay { color: #1a56db; }
    .method-name { font-size: 1.2rem; font-weight: 700; flex: 1; }
    .method-badge { font-size: .75rem; padding: .3rem .8rem; border-radius: 20px; font-weight: 600; }
    .badge-manual  { background: #fff3cd; color: #92400e; }
    .badge-auto    { background: #d1fae5; color: #065f46; }
    .method-body { padding: 1.5rem; display: none; border-top: 1px solid var(--gray-300); }
    .method-body.open { display: block; }
    /* Detalles SINPE */
    .sinpe-detail { background: #eff6ff; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1rem; }
    .sinpe-detail strong { display: block; color: #1e3a8a; font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .3rem; }
    .sinpe-detail p { font-size: 1.3rem; font-weight: 700; font-family: 'Courier New', monospace; }
    .upload-zone {
      border: 2px dashed var(--gray-300);
      border-radius: 10px;
      padding: 1.5rem;
      text-align: center;
      cursor: pointer;
      transition: all .2s;
      margin-top: 1rem;
    }
    .upload-zone:hover { border-color: var(--primary); background: #f0f4ff; }
    .upload-zone input { display: none; }
    .upload-zone i { font-size: 2.5rem; color: var(--gray-300); display: block; margin-bottom: .75rem; }
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: .5rem;
      width: 100%;
      padding: .9rem 1.5rem;
      border-radius: 12px;
      font-weight: 700;
      font-size: 1rem;
      border: none;
      cursor: pointer;
      text-decoration: none;
      transition: all .2s;
      margin-top: 1rem;
    }
    .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: #fff; }
    .btn-primary:hover { filter: brightness(1.1); transform: translateY(-1px); }
    .btn-secondary { background: var(--gray-300); color: var(--dark); }
    .btn-secondary:hover { background: #a0aec0; }
    .btn-sinpe { background: linear-gradient(135deg, #0277bd, #01579b); color: #fff; }
    .btn-sinpe:hover { filter: brightness(1.1); transform: translateY(-1px); }
    #paypal-button-container { margin-top: .75rem; }
    .alert-info {
      background: #fffbeb;
      border: 1px solid #fcd34d;
      border-left: 4px solid var(--warning);
      border-radius: 8px;
      padding: 1rem 1.25rem;
      margin-bottom: 1.5rem;
      font-size: .9rem;
      color: #78350f;
    }
    .alert-info i { color: var(--warning); margin-right: .4rem; }
    .actions { display: flex; gap: 1rem; margin-top: 1.5rem; }
    @media (max-width: 600px) { .actions { flex-direction: column; } }
  </style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1><i class="fas fa-file-invoice-dollar"></i> Completar Pago</h1>
    <p>Tu publicación fue creada. Elegí cómo pagar para activarla.</p>
  </div>

  <div class="content">
    <div class="success-icon"><i class="fas fa-check-circle"></i></div>

    <div class="info-box">
      <h3><i class="fas fa-home"></i> Detalles de tu Publicación</h3>
      <div class="info-item">
        <span class="info-label">Propiedad:</span>
        <span class="info-value"><?= htmlspecialchars($listing['title'] ?? '') ?></span>
      </div>
      <div class="info-item">
        <span class="info-label">Plan:</span>
        <span class="info-value"><?= htmlspecialchars($listing['plan_name'] ?? '') ?></span>
      </div>
      <div class="info-item">
        <span class="info-label">Duración:</span>
        <span class="info-value"><?= (int)($listing['duration_days'] ?? 0) ?> días</span>
      </div>
      <div class="info-item">
        <span class="info-label">Monto:</span>
        <span class="info-value">
          <?php if ($amount_crc > 0): ?>₡<?= number_format($amount_crc, 0) ?> CRC<?php endif; ?>
          <?php if ($amount_usd > 0 && $amount_crc > 0): ?> / <?php endif; ?>
          <?php if ($amount_usd > 0): ?>$<?= number_format($amount_usd, 2) ?> USD<?php endif; ?>
        </span>
      </div>
    </div>

    <div class="payment-options">
      <h3>💳 Seleccioná tu Método de Pago</h3>

      <?php if ($has_sinpe): ?>
      <!-- ── SINPE Móvil ─────────────────────────────────────────── -->
      <div class="method-card" id="card-sinpe">
        <div class="method-header" onclick="toggleMethod('sinpe')">
          <i class="fas fa-mobile-alt method-icon sinpe"></i>
          <span class="method-name">SINPE Móvil</span>
          <span class="method-badge badge-manual"><i class="fas fa-clock"></i> Requiere aprobación</span>
        </div>
        <div class="method-body" id="body-sinpe">
          <div class="sinpe-detail">
            <strong>Número de teléfono</strong>
            <p><?= htmlspecialchars(defined('SINPE_PHONE') ? SINPE_PHONE : '') ?></p>
          </div>
          <?php if ($amount_crc > 0): ?>
          <div class="sinpe-detail">
            <strong>Monto en colones</strong>
            <p>₡<?= number_format($amount_crc, 0) ?></p>
          </div>
          <?php endif; ?>
          <?php if ($amount_usd > 0): ?>
          <div class="sinpe-detail">
            <strong>Equivalente en dólares</strong>
            <p>$<?= number_format($amount_usd, 2) ?> USD</p>
          </div>
          <?php endif; ?>

          <div class="upload-zone" onclick="document.getElementById('sinpe-file').click()">
            <i class="fas fa-cloud-upload-alt"></i>
            <p><strong>Subir comprobante de pago</strong></p>
            <p style="font-size:.85rem;color:var(--gray-700);margin-top:.4rem">JPG, PNG o PDF · Máx. 5MB</p>
            <input type="file" id="sinpe-file" accept="image/*,.pdf">
          </div>
          <div id="sinpe-msg" style="margin-top:.75rem;font-size:.9rem;display:none"></div>
          <button class="btn btn-sinpe" id="sinpe-btn" onclick="confirmSinpe()">
            <i class="fas fa-check"></i> Ya transferí, enviar comprobante
          </button>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($has_paypal): ?>
      <!-- ── PayPal ──────────────────────────────────────────────── -->
      <div class="method-card" id="card-paypal">
        <div class="method-header" onclick="toggleMethod('paypal')">
          <i class="fab fa-paypal method-icon paypal"></i>
          <span class="method-name">PayPal / Tarjeta</span>
          <span class="method-badge badge-auto"><i class="fas fa-bolt"></i> Activación inmediata</span>
        </div>
        <div class="method-body" id="body-paypal">
          <p style="color:var(--gray-700);margin-bottom:.75rem">Pagá con tu cuenta PayPal o tarjeta de crédito/débito. Tu publicación se activa de inmediato al confirmar el pago.</p>
          <?php if ($amount_usd > 0): ?>
          <div class="sinpe-detail">
            <strong>Monto</strong>
            <p>$<?= number_format($amount_usd, 2) ?> USD</p>
          </div>
          <?php endif; ?>
          <div id="paypal-button-container"></div>
          <div id="paypal-error" style="display:none;color:var(--danger);margin-top:.75rem;font-size:.9rem"></div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($has_swiftpay): ?>
      <!-- ── SwiftPay (Tarjeta) ──────────────────────────────────── -->
      <div class="method-card" id="card-swiftpay">
        <div class="method-header" onclick="toggleMethod('swiftpay')">
          <i class="fas fa-credit-card method-icon swiftpay"></i>
          <span class="method-name">Tarjeta Visa / Mastercard</span>
          <span class="method-badge badge-auto"><i class="fas fa-bolt"></i> Activación inmediata</span>
        </div>
        <div class="method-body" id="body-swiftpay">
          <?php
            $sp_reference_id    = $listing_id;
            $sp_reference_table = 'real_estate_listings';
            include __DIR__ . '/../views/swiftpay-button.php';
          ?>
        </div>
      </div>
      <?php endif; ?>
    </div><!-- /.payment-options -->

    <div class="alert-info">
      <i class="fas fa-info-circle"></i>
      <strong>SINPE:</strong> tu publicación quedará pendiente de verificación (1-24 h hábiles).
      <strong>PayPal / Tarjeta:</strong> la activación es inmediata.
    </div>

    <div class="actions">
      <a href="dashboard.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Volver al Dashboard
      </a>
      <a href="create-listing.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Nueva Publicación
      </a>
    </div>
  </div><!-- /.content -->
</div><!-- /.container -->

<script>
// ── Acordeón de métodos ────────────────────────────────────────────
function toggleMethod(id) {
  ['sinpe','paypal','swiftpay'].forEach(m => {
    const b = document.getElementById('body-' + m);
    if (!b) return;
    if (m === id) {
      b.classList.toggle('open');
    } else {
      b.classList.remove('open');
    }
  });
}

// ── SINPE: subir comprobante ───────────────────────────────────────
document.getElementById('sinpe-file')?.addEventListener('change', function () {
  const msg = document.getElementById('sinpe-msg');
  if (this.files[0]) {
    msg.textContent = 'Archivo seleccionado: ' + this.files[0].name;
    msg.style.display = 'block';
    msg.style.color = '#2563eb';
  }
});

async function confirmSinpe() {
  const btn  = document.getElementById('sinpe-btn');
  const msg  = document.getElementById('sinpe-msg');
  const file = document.getElementById('sinpe-file').files[0];

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando…';

  try {
    if (file) {
      // Con comprobante
      const fd = new FormData();
      fd.append('receipt', file);
      fd.append('listing_id', '<?= $listing_id ?>');
      fd.append('listing_type', 'real_estate');

      const r = await fetch('/api/upload-sinpe-receipt.php', { method: 'POST', body: fd });
      const d = await r.json();

      if (d.ok) {
        msg.textContent = '✅ ' + (d.message || 'Comprobante enviado. Tu publicación será activada en breve.');
        msg.style.color = '#27ae60';
        msg.style.display = 'block';
        setTimeout(() => window.location.href = '/real-estate/dashboard.php?msg=sinpe_pending', 2000);
      } else {
        throw new Error(d.error || 'Error al subir comprobante');
      }
    } else {
      // Sin comprobante: solo marcar como pendiente
      const r = await fetch('/api/sinpe-notify-realestate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ listing_id: <?= $listing_id ?> })
      });
      const d = await r.json();

      if (d.ok) {
        msg.textContent = '✅ Pago registrado. Quedará activo una vez verificado.';
        msg.style.color = '#27ae60';
        msg.style.display = 'block';
        setTimeout(() => window.location.href = '/real-estate/dashboard.php?msg=sinpe_pending', 2000);
      } else {
        throw new Error(d.error || 'Error');
      }
    }
  } catch (e) {
    msg.textContent = '⚠️ ' + e.message;
    msg.style.color = 'var(--danger)';
    msg.style.display = 'block';
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Ya transferí, enviar comprobante';
  }
}

// ── PayPal ─────────────────────────────────────────────────────────
<?php if ($has_paypal && defined('PAYPAL_CLIENT_ID') && PAYPAL_CLIENT_ID && $amount_usd > 0): ?>
window.addEventListener('load', function () {
  if (typeof paypal === 'undefined') return;
  paypal.Buttons({
    createOrder: function(data, actions) {
      return actions.order.create({
        purchase_units: [{
          amount: { value: '<?= number_format($amount_usd, 2, '.', '') ?>' },
          description: '<?= addslashes(htmlspecialchars($listing['title'] ?? '')) ?>',
          custom_id: 'realestate_<?= $listing_id ?>'
        }]
      });
    },
    onApprove: function(data, actions) {
      return actions.order.capture().then(function(details) {
        return fetch('/api/process-paypal-payment.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            listing_id:      <?= $listing_id ?>,
            listing_type:    'real_estate',
            paypal_order_id: data.orderID,
            payer_email:     details.payer.email_address,
            amount:          <?= $amount_usd ?>
          })
        })
        .then(r => r.json())
        .then(res => {
          if (res.ok) {
            alert('¡Pago exitoso! Tu publicación está ahora activa.');
            window.location.href = '/real-estate/dashboard.php?msg=payment_success';
          } else {
            throw new Error(res.error || 'Error al procesar el pago');
          }
        });
      });
    },
    onError: function(err) {
      console.error(err);
      const el = document.getElementById('paypal-error');
      if (el) { el.textContent = 'Error al procesar PayPal. Intentá de nuevo.'; el.style.display = 'block'; }
    }
  }).render('#paypal-button-container');
});
<?php endif; ?>
</script>
</body>
</html>
