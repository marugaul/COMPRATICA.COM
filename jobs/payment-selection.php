<?php
// jobs/payment-selection.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Verificar autenticación
if (!isset($_SESSION['employer_id']) || $_SESSION['employer_id'] <= 0) {
  header('Location: login.php');
  exit;
}

$pdo = db();
$employer_id = (int)$_SESSION['employer_id'];
$employer_name = $_SESSION['employer_name'] ?? 'Usuario';
$listing_id = (int)($_GET['listing_id'] ?? 0);

if ($listing_id <= 0) {
  header('Location: dashboard.php');
  exit;
}

// Obtener información de la publicación y el plan
$stmt = $pdo->prepare("
  SELECT l.*, p.name as plan_name, p.price_usd, p.price_crc, p.payment_methods, p.duration_days
  FROM job_listings l
  LEFT JOIN job_pricing p ON l.pricing_plan_id = p.id
  WHERE l.id = ? AND l.employer_id = ?
  LIMIT 1
");
$stmt->execute([$listing_id, $employer_id]);
$listing = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$listing) {
  header('Location: dashboard.php');
  exit;
}

// Si el plan es gratuito o ya está confirmado, redirigir al dashboard
if ($listing['payment_status'] === 'free' || $listing['payment_status'] === 'confirmed') {
  header('Location: dashboard.php?msg=already_paid');
  exit;
}

$payment_methods = explode(',', $listing['payment_methods'] ?? '');
$has_sinpe = in_array('sinpe', $payment_methods);
$has_paypal = in_array('paypal', $payment_methods);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pago de Publicación — <?php echo APP_NAME; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    :root {
      --primary: #27ae60;
      --primary-light: #2ecc71;
      --success: #27ae60;
      --warning: #f39c12;
      --danger: #e74c3c;
      --white: #fff;
      --dark: #1a1a1a;
      --gray-700: #4a5568;
      --gray-300: #cbd5e0;
      --gray-100: #f7fafc;
      --bg: #f8f9fa;
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
      max-width: 700px;
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
    .header h1 {
      font-size: 1.75rem;
      margin-bottom: 0.5rem;
      font-weight: 800;
    }
    .header p {
      opacity: 0.9;
      font-size: 1rem;
    }
    .content {
      padding: 2rem;
    }
    .success-check {
      text-align: center;
      margin-bottom: 2rem;
    }
    .success-check i {
      font-size: 4rem;
      color: var(--success);
      animation: scaleIn 0.5s ease;
    }
    @keyframes scaleIn {
      0% { transform: scale(0); }
      50% { transform: scale(1.1); }
      100% { transform: scale(1); }
    }
    .info-box {
      background: var(--gray-100);
      border-radius: 12px;
      padding: 1.5rem;
      margin-bottom: 2rem;
      border-left: 4px solid var(--primary);
    }
    .info-box h3 {
      color: var(--primary);
      margin-bottom: 0.75rem;
      font-size: 1.125rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .info-item {
      display: flex;
      justify-content: space-between;
      padding: 0.5rem 0;
      border-bottom: 1px solid var(--gray-300);
    }
    .info-item:last-child {
      border-bottom: none;
    }
    .info-label {
      color: var(--gray-700);
      font-weight: 500;
    }
    .info-value {
      font-weight: 700;
      color: var(--dark);
    }
    .payment-options {
      margin-bottom: 2rem;
    }
    .payment-options h3 {
      color: var(--dark);
      margin-bottom: 1.5rem;
      font-size: 1.25rem;
      text-align: center;
    }
    .payment-method {
      background: var(--white);
      border: 3px solid var(--gray-300);
      border-radius: 16px;
      padding: 2rem;
      margin-bottom: 1.5rem;
      transition: all 0.3s ease;
      cursor: pointer;
    }
    .payment-method:hover {
      border-color: var(--primary);
      transform: translateY(-4px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }
    .payment-method.sinpe {
      background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    }
    .payment-method.paypal {
      background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);
    }
    .payment-method-header {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1rem;
    }
    .payment-method-icon {
      font-size: 2.5rem;
    }
    .payment-method-icon.sinpe {
      color: #0277bd;
    }
    .payment-method-icon.paypal {
      color: #0070ba;
    }
    .payment-method-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--dark);
    }
    .payment-method-description {
      color: var(--gray-700);
      margin-bottom: 1rem;
      line-height: 1.6;
    }
    .payment-method-details {
      background: var(--white);
      border-radius: 8px;
      padding: 1rem;
      margin-top: 1rem;
    }
    .payment-method-details strong {
      display: block;
      margin-bottom: 0.5rem;
      color: var(--primary);
    }
    .payment-method-details p {
      font-size: 1.125rem;
      font-weight: 600;
      color: var(--dark);
      font-family: 'Courier New', monospace;
    }
    .alert {
      background: #fff3cd;
      border: 1px solid #ffc107;
      border-left: 4px solid var(--warning);
      border-radius: 8px;
      padding: 1.25rem;
      margin-bottom: 2rem;
    }
    .alert i {
      color: var(--warning);
      margin-right: 0.5rem;
    }
    .alert strong {
      display: block;
      margin-bottom: 0.5rem;
    }
    .alert ul {
      margin: 0.5rem 0 0 1.5rem;
    }
    .alert li {
      margin-bottom: 0.25rem;
    }
    .btn {
      display: inline-block;
      padding: 1rem 2rem;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: var(--white);
      text-decoration: none;
      border-radius: 12px;
      font-weight: 700;
      font-size: 1rem;
      text-align: center;
      transition: all 0.3s ease;
      border: none;
      cursor: pointer;
      width: 100%;
    }
    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(39, 174, 96, 0.3);
    }
    .btn.secondary {
      background: var(--gray-300);
      color: var(--dark);
    }
    .btn.secondary:hover {
      background: var(--gray-700);
      color: var(--white);
    }
    .btn.paypal-btn {
      background: #0070ba;
      color: white;
      margin-bottom: 1rem;
    }
    .btn.paypal-btn:hover {
      background: #005a94;
    }
    .upload-section {
      background: var(--gray-100);
      border-radius: 12px;
      padding: 1.5rem;
      margin-top: 1rem;
    }
    .upload-section h4 {
      margin-bottom: 1rem;
      color: var(--dark);
    }
    .file-upload {
      border: 2px dashed var(--gray-300);
      border-radius: 8px;
      padding: 2rem;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s;
    }
    .file-upload:hover {
      border-color: var(--primary);
      background: rgba(39, 174, 96, 0.05);
    }
    .file-upload input {
      display: none;
    }
    .actions {
      display: flex;
      gap: 1rem;
      margin-top: 2rem;
    }
    @media (max-width: 768px) {
      .actions {
        flex-direction: column;
      }
      .payment-method {
        padding: 1.5rem;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1><i class="fas fa-file-invoice-dollar"></i> Completar Pago</h1>
      <p>Tu publicación ha sido creada exitosamente</p>
    </div>

    <div class="content">
      <div class="success-check">
        <i class="fas fa-check-circle"></i>
      </div>

      <div class="info-box">
        <h3><i class="fas fa-info-circle"></i> Detalles de tu Publicación</h3>
        <div class="info-item">
          <span class="info-label">Título:</span>
          <span class="info-value"><?= htmlspecialchars($listing['title']) ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Plan:</span>
          <span class="info-value"><?= htmlspecialchars($listing['plan_name']) ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Duración:</span>
          <span class="info-value"><?= $listing['duration_days'] ?> días</span>
        </div>
        <div class="info-item">
          <span class="info-label">ID de Publicación:</span>
          <span class="info-value">#<?= $listing_id ?></span>
        </div>
      </div>

      <div class="payment-options">
        <h3>💳 Seleccioná tu Método de Pago</h3>

        <?php if ($has_sinpe): ?>
        <div class="payment-method sinpe">
          <div class="payment-method-header">
            <i class="fas fa-mobile-alt payment-method-icon sinpe"></i>
            <div>
              <div class="payment-method-title">SINPE Móvil</div>
            </div>
          </div>
          <div class="payment-method-description">
            Transferí el monto directamente desde tu banca móvil.
          </div>
          <div class="payment-method-details">
            <strong>Monto a transferir:</strong>
            <?php if ($listing['price_usd'] > 0): ?>
              <p>💵 $<?= number_format($listing['price_usd'], 2) ?> USD</p>
            <?php endif; ?>
            <?php if ($listing['price_crc'] > 0): ?>
              <p>₡<?= number_format($listing['price_crc'], 0) ?> CRC</p>
            <?php endif; ?>
            <?php if (defined('SINPE_PHONE') && SINPE_PHONE): ?>
              <strong style="margin-top: 1rem;">Número de teléfono:</strong>
              <p><?= htmlspecialchars(SINPE_PHONE) ?></p>
            <?php endif; ?>
          </div>

          <div class="upload-section">
            <h4><i class="fas fa-cloud-upload-alt"></i> Subir Comprobante</h4>
            <div class="file-upload" onclick="document.getElementById('sinpe-receipt').click()">
              <i class="fas fa-file-image" style="font-size: 3rem; color: var(--gray-300); margin-bottom: 1rem;"></i>
              <p><strong>Hacé clic para subir el comprobante de pago</strong></p>
              <p style="font-size: 0.875rem; color: var(--gray-700); margin-top: 0.5rem;">JPG, PNG o PDF · Máx. 5MB</p>
              <input type="file" id="sinpe-receipt" accept="image/*,.pdf">
            </div>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($has_paypal): ?>
        <div class="payment-method paypal">
          <div class="payment-method-header">
            <i class="fab fa-paypal payment-method-icon paypal"></i>
            <div>
              <div class="payment-method-title">PayPal</div>
            </div>
          </div>
          <div class="payment-method-description">
            Pagá de forma segura con tarjeta de crédito o débito a través de PayPal.
          </div>
          <div class="payment-method-details">
            <strong>Monto a pagar:</strong>
            <?php if ($listing['price_usd'] > 0): ?>
              <p>💵 $<?= number_format($listing['price_usd'], 2) ?> USD</p>
            <?php endif; ?>
            <?php if (defined('PAYPAL_EMAIL') && PAYPAL_EMAIL): ?>
              <strong style="margin-top: 1rem;">Correo PayPal:</strong>
              <p><?= htmlspecialchars(PAYPAL_EMAIL) ?></p>
            <?php endif; ?>
          </div>
          <div style="margin-top: 1.5rem;">
            <button class="btn paypal-btn" onclick="payWithPayPal()">
              <i class="fab fa-paypal"></i> Pagar con PayPal
            </button>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <div class="alert">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Importante:</strong>
        <ul>
          <li><strong>SINPE:</strong> Una vez realizada la transferencia, enviá el comprobante a <strong>pagos@<?= parse_url(APP_URL, PHP_URL_HOST) ?? 'compratica.com' ?></strong> indicando el número de publicación <strong>#<?= $listing_id ?></strong></li>
          <li><strong>PayPal:</strong> Los pagos por PayPal se verifican automáticamente. Tu publicación se activará una vez confirmado el pago.</li>
          <li>Recibirás un correo de confirmación cuando tu publicación esté activa.</li>
        </ul>
      </div>

      <div class="actions">
        <a href="dashboard.php" class="btn secondary">
          <i class="fas fa-arrow-left"></i> Volver al Dashboard
        </a>
        <a href="create-listing.php" class="btn">
          <i class="fas fa-plus"></i> Crear Otra Publicación
        </a>
      </div>
    </div>
  </div>

  <script>
    function payWithPayPal() {
      alert('Integración con PayPal en desarrollo. Por favor usa SINPE Móvil temporalmente.');
    }

    // File upload handler
    document.getElementById('sinpe-receipt')?.addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (file) {
        if (file.size > 5 * 1024 * 1024) {
          alert('El archivo es muy grande. El máximo es 5MB.');
          return;
        }
        uploadReceipt(file);
      }
    });

    function uploadReceipt(file) {
      const formData = new FormData();
      formData.append('receipt', file);
      formData.append('listing_id', <?= $listing_id ?>);

      // TODO: Implementar endpoint de carga de comprobantes
      alert('Funcionalidad de carga de comprobantes en desarrollo.');
    }
  </script>
</body>
</html>
