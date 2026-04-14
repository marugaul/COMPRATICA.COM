<?php 
// affiliate/sales.php — crear y listar espacios (robusto)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/affiliate_auth.php';
aff_require_login();
$pdo = db();
$aff_id = (int)$_SESSION['aff_id'];
$msg = '';
// --- NUEVO: helper con zona horaria de Costa Rica ---
if (!function_exists('now_cr')) {
  function now_cr(): string {
    // Forzamos America/Costa_Rica para que no dependa de la config del hosting
    $tz = new DateTimeZone('America/Costa_Rica');
    return (new DateTime('now', $tz))->format('Y-m-d H:i:s');
  }
}
// Re-activar espacio existente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate'])) {
  try {
    $sale_id = (int)($_POST['sale_id'] ?? 0);
    if ($sale_id <= 0) throw new RuntimeException('ID de espacio inválido.');
    // Verificar que el espacio pertenece al afiliado
    $chk = $pdo->prepare("SELECT id FROM sales WHERE id=? AND affiliate_id=?");
    $chk->execute([$sale_id, $aff_id]);
    if (!$chk->fetchColumn()) throw new RuntimeException('Espacio no encontrado.');
    // Crear nuevo fee de activación — privado usa PRIVATE_SPACE_PRICE_USD
    $ex = (float)(function_exists('get_exchange_rate') ? get_exchange_rate() : 540);
    if ($ex <= 0) $ex = 540;
    $sale_priv = $pdo->prepare("SELECT is_private FROM sales WHERE id=? LIMIT 1");
    $sale_priv->execute([$sale_id]);
    $sale_is_private = (bool)($sale_priv->fetchColumn() ?: false);
    if ($sale_is_private) {
      $private_usd = (float)(get_setting('PRIVATE_SPACE_PRICE_USD', 20) ?: 20);
      $fee_crc     = round($private_usd * $ex);
      $amount_usd  = $private_usd;
    } else {
      $fee_crc    = (float)get_setting('SALE_FEE_CRC', 2000);
      $amount_usd = $fee_crc / $ex;
    }
    $pdo->prepare("INSERT INTO sale_fees
      (affiliate_id, sale_id, amount_crc, amount_usd, exrate_used, status, created_at, updated_at)
      VALUES (:aff, :sale, :crc, :usd, :ex, 'Pendiente', :now, :now)")
      ->execute([
        ':aff'  => $aff_id,
        ':sale' => $sale_id,
        ':crc'  => $fee_crc,
        ':usd'  => $amount_usd,
        ':ex'   => $ex,
        ':now'  => now_cr(),
      ]);
    $fee_id = (int)$pdo->lastInsertId();
    header("Location: sales_pay.php?fee_id=" . $fee_id);
    exit;
  } catch (Throwable $e) {
    error_log("[affiliate/sales.php] Reactivate error: " . $e->getMessage());
    $msg = 'Error al re-activar el espacio: ' . $e->getMessage();
  }
}

// Crear espacio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
  try {
    $title = trim($_POST['title'] ?? '');
    $start = trim($_POST['start_at'] ?? '');
    $end   = trim($_POST['end_at'] ?? '');
    if ($title === '' || $start === '' || $end === '') {
      throw new RuntimeException('Faltan datos obligatorios (título, inicio, fin).');
    }

    // 🔒 Privacidad del espacio
    $isPrivate = !empty($_POST['is_private']) ? 1 : 0;
    $accessCode = null;

    if ($isPrivate) {
      $accessCode = trim($_POST['access_code'] ?? '');
      // Validar código de 6 dígitos
      if (!preg_match('/^[0-9]{6}$/', $accessCode)) {
        throw new RuntimeException('El código de acceso debe ser exactamente 6 dígitos numéricos.');
      }
    }

    // Portada opcional
    $img = null;
    if (!empty($_FILES['cover']['name']) && is_uploaded_file($_FILES['cover']['tmp_name'])) {
      @mkdir(__DIR__ . '/../uploads/affiliates', 0775, true);
      $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) $ext = 'jpg';
      $img = 'cover_' . uniqid() . '.' . $ext;
      if (!move_uploaded_file($_FILES['cover']['tmp_name'], __DIR__ . '/../uploads/affiliates/' . $img)) {
        $img = null; // no bloquear por la imagen
      }
    }
    // INSERT con timestamps en hora de Costa Rica
    $sql = "INSERT INTO sales
            (affiliate_id, title, cover_image, start_at, end_at, is_active, is_private, access_code, created_at, updated_at)
            VALUES
            (:aff, :title, :cover, :start, :end, 0, :private, :code, :now, :now)";
    $stmt = $pdo->prepare($sql);
    $now  = now_cr();
    $stmt->execute([
      ':aff'     => $aff_id,
      ':title'   => $title,
      ':cover'   => $img,
      ':start'   => $start,
      ':end'     => $end,
      ':private' => $isPrivate,
      ':code'    => $accessCode,
      ':now'     => $now
    ]);
    $sale_id = (int)$pdo->lastInsertId();
    // Crear fee de activación — privado usa PRIVATE_SPACE_PRICE_USD
    $ex = (float)(function_exists('get_exchange_rate') ? get_exchange_rate() : 540);
    if ($ex <= 0) $ex = 540;
    if ($isPrivate) {
      $private_usd = (float)(get_setting('PRIVATE_SPACE_PRICE_USD', 20) ?: 20);
      $fee_crc     = round($private_usd * $ex);
      $amount_usd  = $private_usd;
    } else {
      $fee_crc    = (float)get_setting('SALE_FEE_CRC', 2000);
      $amount_usd = $fee_crc / $ex;
    }
    $pdo->prepare("INSERT INTO sale_fees
      (affiliate_id, sale_id, amount_crc, amount_usd, exrate_used, status, created_at, updated_at)
      VALUES
      (:aff, :sale, :crc, :usd, :ex, 'Pendiente', :now, :now)")
        ->execute([
          ':aff' => $aff_id,
          ':sale'=> $sale_id,
          ':crc' => $fee_crc,
          ':usd' => $amount_usd,
          ':ex'  => $ex,
          ':now' => now_cr()
        ]);
    $fee_id = (int)$pdo->lastInsertId();
    // Redirigir a pago del fee (evita "Fee no encontrado")
    header("Location: sales_pay.php?fee_id=".$fee_id);
    exit;
  } catch (Throwable $e) {
    error_log("[affiliate/sales.php] Create error: ".$e->getMessage());
    $msg = 'Error al crear el espacio: '.$e->getMessage();
  }
}
// Listado de espacios del afiliado
$rows = $pdo->prepare("SELECT * FROM sales WHERE affiliate_id=? ORDER BY datetime(start_at) DESC");
$rows->execute([$aff_id]);
$sales = $rows->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Afiliados — Mis espacios</title>
  <link rel="stylesheet" href="../assets/style.css?v=23">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
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
      transition: box-shadow 0.3s ease;
    }

    .card:hover {
      box-shadow: 0 4px 12px rgba(0,0,0,0.12);
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

    /* Formulario mejorado */
    .form label {
      font-weight: 500;
      color: var(--gray-800);
      margin-bottom: 0.5rem;
      display: block;
    }

    .form .input {
      border: 2px solid var(--gray-200);
      border-radius: 8px;
      padding: 0.75rem 1rem;
      font-size: 1rem;
      transition: all 0.3s ease;
      width: 100%;
    }

    .form .input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
      outline: none;
    }

    /* Grid para formulario */
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
    }

    @media (max-width: 768px) {
      .form-grid {
        grid-template-columns: 1fr;
      }
    }

    /* Tabla profesional */
    .table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
    }

    .table th {
      background: var(--gray-100);
      color: var(--gray-800);
      font-weight: 600;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.5px;
      padding: 1rem;
      text-align: left;
      border-bottom: 2px solid var(--gray-300);
    }

    .table th:first-child {
      border-radius: 8px 0 0 0;
    }

    .table th:last-child {
      border-radius: 0 8px 0 0;
    }

    .table td {
      padding: 1.25rem 1rem;
      border-bottom: 1px solid var(--gray-200);
      color: var(--gray-800);
      vertical-align: middle;
    }

    .table tr:last-child td {
      border-bottom: none;
    }

    .table tr:hover {
      background: var(--gray-50);
    }

    /* Botones mejorados */
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
    }

    .btn.primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
    }

    .btn-pickup {
      background: linear-gradient(135deg, #e67e22, #d35400);
      color: white;
      padding: 0.625rem 1.25rem;
      border-radius: 6px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.875rem;
      font-weight: 500;
      transition: all 0.3s ease;
      border: none;
    }

    .btn-pickup:hover {
      background: linear-gradient(135deg, #d35400, #c0392b);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(230, 126, 34, 0.4);
    }

    /* Badges profesionales */
    .location-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.375rem;
      padding: 0.375rem 0.875rem;
      border-radius: 16px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .badge-success {
      background: rgba(39, 174, 96, 0.1);
      color: var(--success);
      border: 1px solid rgba(39, 174, 96, 0.3);
    }

    .badge-warning {
      background: rgba(243, 156, 18, 0.1);
      color: var(--warning);
      border: 1px solid rgba(243, 156, 18, 0.3);
    }

    /* Info boxes mejorados */
    .info-box {
      border-radius: 8px;
      padding: 1.25rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: flex-start;
      gap: 1rem;
      border-left: 4px solid;
    }

    .info-box.blue {
      background: rgba(52, 152, 219, 0.05);
      border-color: var(--accent);
      color: var(--primary);
    }

    .info-box.orange {
      background: rgba(243, 156, 18, 0.05);
      border-color: var(--warning);
      color: #856404;
    }

    .info-box-icon {
      font-size: 1.5rem;
      flex-shrink: 0;
    }

    /* Privacy section */
    .privacy-section {
      background: var(--gray-50);
      border: 2px solid var(--gray-200);
      border-radius: 8px;
      padding: 1.5rem;
      margin: 1.5rem 0;
    }

    .privacy-section h4 {
      margin-top: 0;
      color: var(--primary);
      font-size: 1.1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    /* Actions container */
    .actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.625rem;
      align-items: center;
    }

    /* Tooltip mejorado */
    .tooltip-wrapper {
      position: relative;
      display: inline-block;
    }

    .tooltip-text {
      visibility: hidden;
      width: 220px;
      background: var(--gray-800);
      color: white;
      text-align: center;
      border-radius: 6px;
      padding: 0.75rem;
      position: absolute;
      z-index: 1;
      bottom: 125%;
      left: 50%;
      margin-left: -110px;
      opacity: 0;
      transition: opacity 0.3s;
      font-size: 0.8rem;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }

    .tooltip-text::after {
      content: "";
      position: absolute;
      top: 100%;
      left: 50%;
      margin-left: -5px;
      border-width: 5px;
      border-style: solid;
      border-color: var(--gray-800) transparent transparent transparent;
    }

    .tooltip-wrapper:hover .tooltip-text {
      visibility: visible;
      opacity: 1;
    }

    /* Alert mejorado */
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

    /* Empty state */
    .empty-state {
      text-align: center;
      padding: 3rem 2rem;
      color: var(--gray-600);
    }

    .empty-state-icon {
      font-size: 4rem;
      color: var(--gray-300);
      margin-bottom: 1rem;
    }

    .empty-state h4 {
      color: var(--gray-800);
      margin-bottom: 0.5rem;
    }

    /* Location preview */
    .location-preview {
      font-size: 0.8rem;
      color: var(--gray-600);
      margin-top: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.375rem;
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
    <a class="nav-btn" href="../index" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-store"></i>
      <span>Ver Tienda</span>
    </a>
    <a class="nav-btn" href="products.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-box"></i>
      <span>Productos</span>
    </a>
    <a class="nav-btn" href="orders.php" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.2);" onmouseover="this.style.background='rgba(255,255,255,0.2)'; this.style.borderColor='rgba(255,255,255,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)';">
      <i class="fas fa-shopping-cart"></i>
      <span>Pedidos</span>
    </a>
  </nav>
</header>
<div class="container">
  <?php if ($msg): ?>
    <div class="alert">
      <i class="fas fa-exclamation-triangle"></i>
      <span><strong>Aviso:</strong> <?= htmlspecialchars($msg) ?></span>
    </div>
  <?php endif; ?>
  
  <!-- Mensaje informativo sobre ubicaciones -->
  <div class="info-box blue">
    <div class="info-box-icon">
      <i class="fas fa-lightbulb"></i>
    </div>
    <div>
      <strong style="display: block; margin-bottom: 0.25rem;">¿Sabías que?</strong>
      Puedes configurar la ubicación de recogida para cada espacio.
      Esto permite que tus clientes elijan <strong>envío por Uber</strong> en el checkout y mejora tus opciones de entrega.
    </div>
  </div>
  
  <div class="card">
    <h3><i class="fas fa-plus-circle"></i> Crear Nuevo Espacio</h3>
    <?php
      $fee_crc_display = (float)get_setting('SALE_FEE_CRC', 2000);
      $private_usd_display = (float)(get_setting('PRIVATE_SPACE_PRICE_USD', 20) ?: 20);
      $ex_display = (float)(function_exists('get_exchange_rate') ? get_exchange_rate() : 540);
      if ($ex_display <= 0) $ex_display = 540;
      $private_crc_display = round($private_usd_display * $ex_display);
    ?>
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:0.9rem 1.1rem;margin-bottom:1.25rem;" id="fee-info-box">
      <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;">
        <i class="fas fa-tag" style="color:#059669;font-size:1rem;"></i>
        <span style="font-size:0.95rem;color:#065f46;font-weight:600;" id="fee-cost-label">
          Costo de activación: ₡<?= number_format($fee_crc_display, 0, '.', ',') ?> por espacio
        </span>
      </div>
      <div style="font-size:0.83rem;color:#374151;margin-bottom:0.35rem;" id="fee-cost-desc">
        Se cobra al crear o re-activar un espacio. Puedes pagar con:
      </div>
      <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
        <span style="display:inline-flex;align-items:center;gap:0.3rem;background:white;border:1px solid #d1d5db;border-radius:6px;padding:0.25rem 0.65rem;font-size:0.82rem;color:#1f2937;">
          <img src="../assets/sinpe.jpg" alt="SINPE" style="height:16px;vertical-align:middle;"> SINPE Móvil
        </span>
        <span style="display:inline-flex;align-items:center;gap:0.3rem;background:white;border:1px solid #d1d5db;border-radius:6px;padding:0.25rem 0.65rem;font-size:0.82rem;color:#1f2937;">
          <img src="../assets/paypal.png" alt="PayPal" style="height:14px;vertical-align:middle;"> PayPal
        </span>
      </div>
    </div>
    <form class="form" method="post" enctype="multipart/form-data" id="saleForm">
      <label>
        <i class="fas fa-tag"></i> Título del Espacio
        <input class="input" name="title" placeholder="Ej: Venta de Garage 2026" required>
      </label>

      <label>
        <i class="fas fa-image"></i> Imagen de Portada
        <input class="input" type="file" name="cover" accept="image/*">
      </label>

      <div class="form-grid">
        <label>
          <i class="fas fa-calendar-alt"></i> Fecha y Hora de Inicio
          <small style="color: var(--gray-600); display:block; margin-top:4px;">
            Incluye la hora exacta (ej: 8:00 AM)
          </small>
          <input class="input" type="datetime-local" name="start_at" id="start_at" required>
        </label>

        <label>
          <i class="fas fa-calendar-check"></i> Fecha y Hora de Fin
          <small style="color: var(--gray-600); display:block; margin-top:4px;">
            Incluye la hora exacta (ej: 6:00 PM)
          </small>
          <input class="input" type="datetime-local" name="end_at" id="end_at" required>
        </label>
      </div>

      <div class="privacy-section">
        <h4><i class="fas fa-lock"></i> Configuración de Privacidad</h4>

        <label style="display: flex; align-items: center; cursor: pointer; margin-bottom: 1rem;">
          <input type="checkbox" name="is_private" id="is_private" value="1" style="width: auto; margin-right: 0.5rem;">
          <span><strong>Espacio privado</strong> - Requiere código de acceso para ver productos</span>
        </label>

        <div id="access_code_container" style="display: none;">
          <label>
            <i class="fas fa-key"></i> Código de Acceso (6 dígitos)
            <small style="color: var(--gray-600); display:block; margin-top:4px;">
              Los clientes necesitarán este código para acceder a los productos
            </small>
            <input class="input" type="text" name="access_code" id="access_code"
                   pattern="[0-9]{6}" maxlength="6" placeholder="Ej: 123456"
                   style="font-size: 1.2rem; letter-spacing: 0.3rem; font-family: 'Courier New', monospace; text-align: center;">
            <small style="color: var(--gray-600); display:block; margin-top:4px;">
              Solo números, exactamente 6 dígitos
            </small>
          </label>
        </div>
      </div>

      <button class="btn primary" name="create" value="1">
        <i class="fas fa-plus-circle"></i>
        Crear Espacio (Pagar Activación)
      </button>
    </form>

    <script>
    // Establecer valores por defecto razonables
    document.addEventListener('DOMContentLoaded', function() {
      const startInput = document.getElementById('start_at');
      const endInput = document.getElementById('end_at');

      // Si están vacíos, sugerir valores por defecto
      if (!startInput.value) {
        // Mañana a las 8:00 AM
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        tomorrow.setHours(8, 0, 0, 0);
        startInput.value = tomorrow.toISOString().slice(0, 16);
      }

      if (!endInput.value) {
        // Una semana después a las 6:00 PM
        const nextWeek = new Date();
        nextWeek.setDate(nextWeek.getDate() + 8);
        nextWeek.setHours(18, 0, 0, 0);
        endInput.value = nextWeek.toISOString().slice(0, 16);
      }

      // Actualizar fin cuando cambie inicio (sugerir 7 días después)
      startInput.addEventListener('change', function() {
        const startDate = new Date(this.value);
        if (!isNaN(startDate)) {
          const endDate = new Date(startDate);
          endDate.setDate(endDate.getDate() + 7);
          // Solo actualizar si endInput está vacío o es anterior a la nueva fecha
          const currentEnd = new Date(endInput.value);
          if (!endInput.value || currentEnd < startDate) {
            endInput.value = endDate.toISOString().slice(0, 16);
          }
        }
      });

      // 🔒 Control de espacio privado
      const isPrivateCheckbox = document.getElementById('is_private');
      const accessCodeContainer = document.getElementById('access_code_container');
      const accessCodeInput = document.getElementById('access_code');

      // Costos (desde PHP)
      const FEE_CRC_NORMAL  = <?= (int)$fee_crc_display ?>;
      const FEE_USD_PRIVATE = <?= number_format($private_usd_display, 2, '.', '') ?>;
      const FEE_CRC_PRIVATE = <?= (int)$private_crc_display ?>;

      function updateFeeLabel(isPrivate) {
        const label = document.getElementById('fee-cost-label');
        const desc  = document.getElementById('fee-cost-desc');
        if (!label) return;
        if (isPrivate) {
          label.innerHTML = '<i class="fas fa-lock" style="margin-right:.3rem;"></i> Costo Espacio Privado: <strong>$' +
            FEE_USD_PRIVATE.toFixed(2).replace('.', ',') + '</strong> / mes' +
            ' <small style="font-weight:400;color:#374151;">(aprox. ₡' +
            FEE_CRC_PRIVATE.toLocaleString('es-CR') + ')</small>';
          desc.textContent = 'El espacio privado tiene un costo diferenciado. Puedes pagar con:';
        } else {
          label.textContent = 'Costo de activación: ₡' + FEE_CRC_NORMAL.toLocaleString('es-CR') + ' por espacio';
          desc.textContent  = 'Se cobra al crear o re-activar un espacio. Puedes pagar con:';
        }
      }

      // Mostrar/ocultar campo de código según checkbox
      isPrivateCheckbox.addEventListener('change', function() {
        updateFeeLabel(this.checked);
        if (this.checked) {
          accessCodeContainer.style.display = 'block';
          accessCodeInput.required = true;
          // Generar código automático si está vacío
          if (!accessCodeInput.value) {
            accessCodeInput.value = Math.floor(100000 + Math.random() * 900000).toString();
          }
        } else {
          accessCodeContainer.style.display = 'none';
          accessCodeInput.required = false;
        }
      });

      // Validar que solo sean números
      accessCodeInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
      });
    });
    </script>
  </div>
  
  <div class="card">
    <h3><i class="fas fa-list-ul"></i> Mis Espacios de Venta</h3>
    <table class="table">
      <tr>
        <th>Título</th>
        <th>Inicio</th>
        <th>Fin</th>
        <th>Activo</th>
        <th>Ubicación</th>
        <th>Acciones</th>
      </tr>
      <?php foreach($sales as $s):
        // ⭐ Verificar si tiene ubicación de recogida configurada
        $stmt = $pdo->prepare("
          SELECT id, address, city, contact_name
          FROM sale_pickup_locations
          WHERE sale_id = ? AND is_active = 1
          LIMIT 1
        ");
        $stmt->execute([(int)$s['id']]);
        $pickup_location = $stmt->fetch(PDO::FETCH_ASSOC);
        $has_location = !empty($pickup_location);
        // Fee más reciente
        $feeQ = $pdo->prepare("SELECT id, status FROM sale_fees WHERE sale_id=? AND affiliate_id=? ORDER BY id DESC LIMIT 1");
        $feeQ->execute([(int)$s['id'], $aff_id]);
        $latestFee = $feeQ->fetch(PDO::FETCH_ASSOC);
        $lastFeeStatus = $latestFee['status'] ?? null;
        $lastFeeId     = $latestFee ? (int)$latestFee['id'] : 0;
        // ¿Se puede re-activar? Solo si el último fee fue pagado y el espacio no está activo
        $canReactivate = (!empty($s['is_active']) === false) && $lastFeeStatus === 'Pagado';
      ?>
        <?php
          // URL pública del espacio
          $salePublicUrl = rtrim(APP_URL, '/') . '/store.php?sale_id=' . (int)$s['id'];
          // Texto WhatsApp de compartir
          $waShareText = 'Hola! Te comparto mi espacio en COMPRATICA: ' . $salePublicUrl;
          if (!empty($s['is_private']) && !empty($s['access_code'])) {
            $waShareText .= ' — Código de acceso: ' . $s['access_code'];
          }
          $waShareUrl = 'https://wa.me/?text=' . rawurlencode($waShareText);
        ?>
        <tr>
          <td>
            <?= htmlspecialchars($s['title']) ?>
            <?php if (!empty($s['is_private'])): ?>
              <div style="margin-top:6px;">
                <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;
                             background:#fef3c7;border:1px solid #f59e0b;border-radius:6px;
                             font-size:.78rem;font-weight:600;color:#92400e;">
                  <i class="fas fa-lock" style="font-size:.7rem;"></i> Privado
                </span>
                <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 12px;
                             background:#ede9fe;border:1px solid #8b5cf6;border-radius:6px;
                             font-size:.85rem;font-weight:700;color:#5b21b6;
                             letter-spacing:.15rem;font-family:'Courier New',monospace;margin-left:4px;">
                  <i class="fas fa-key" style="font-size:.7rem;"></i>
                  <?= htmlspecialchars($s['access_code'] ?? '—') ?>
                </span>
              </div>
            <?php else: ?>
              <div style="margin-top:4px;">
                <span style="display:inline-flex;align-items:center;gap:4px;font-size:.75rem;color:#6b7280;">
                  <i class="fas fa-globe"></i> Público
                </span>
              </div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($s['start_at']) ?></td>
          <td><?= htmlspecialchars($s['end_at']) ?></td>
          <td>
            <?php if (!empty($s['is_active'])): ?>
              <span class="location-badge badge-success"><i class="fas fa-check-circle"></i> Activo</span>
            <?php elseif ($lastFeeStatus === 'Pendiente'): ?>
              <span class="location-badge badge-warning"><i class="fas fa-clock"></i> Pago pendiente</span>
            <?php else: ?>
              <span class="location-badge" style="background:rgba(231,76,60,0.1);color:#c0392b;border:1px solid rgba(231,76,60,0.3);">
                <i class="fas fa-times-circle"></i> Inactivo
              </span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($has_location): ?>
              <span class="location-badge badge-success">
                <i class="fas fa-check-circle"></i> Configurada
              </span>
            <?php else: ?>
              <div class="tooltip-wrapper">
                <span class="location-badge badge-warning">
                  <i class="fas fa-exclamation-triangle"></i> No configurada
                </span>
                <span class="tooltip-text">
                  Configura la ubicación para habilitar envíos por Uber
                </span>
              </div>
            <?php endif; ?>
          </td>
          <td class="actions">
            <!-- ✏️ Botón EDITAR ESPACIO -->
            <a class="btn" href="edit_sale.php?id=<?= (int)$s['id'] ?>"
               title="Editar espacio"
               style="background: #667eea; color: white;">
              <i class="fas fa-edit"></i> Editar
            </a>

            <?php if ($canReactivate): ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('¿Deseas re-activar este espacio? Se generará un nuevo cobro de activación.');">
                <input type="hidden" name="sale_id" value="<?= (int)$s['id'] ?>">
                <button type="submit" name="reactivate" value="1" class="btn"
                  style="background:linear-gradient(135deg,#27ae60,#2ecc71);color:white;">
                  <i class="fas fa-redo"></i> Re-activar Espacio
                </button>
              </form>
            <?php elseif ($lastFeeId > 0): ?>
              <a class="btn" href="sales_pay.php?fee_id=<?= $lastFeeId ?>">Pagar/Estado</a>
            <?php else: ?>
              <a class="btn" href="sales_pay.php?sale_id=<?= (int)$s['id'] ?>">Pagar/Estado</a>
            <?php endif; ?>

            <!-- ⭐ NUEVO: Botón configurar ubicación de recogida -->
            <a class="btn-pickup" href="sale_pickup_location.php?sale_id=<?= (int)$s['id'] ?>"
               title="<?= $has_location ? 'Editar ubicación de recogida' : 'Configurar ubicación de recogida' ?>">
              <i class="fas fa-map-marker-alt"></i>
              <?= $has_location ? 'Editar Ubicación' : 'Configurar Ubicación' ?>
            </a>

            <?php if ($has_location): ?>
              <!-- Mini-preview de la ubicación -->
              <div class="location-preview">
                <i class="fas fa-map-pin"></i>
                <span><?= htmlspecialchars($pickup_location['city'] ?? '') ?> - <?= htmlspecialchars($pickup_location['contact_name'] ?? '') ?></span>
              </div>
            <?php endif; ?>

            <!-- Compartir por WhatsApp -->
            <a href="<?= htmlspecialchars($waShareUrl) ?>" target="_blank" rel="noopener"
               title="Compartir espacio por WhatsApp"
               style="margin-top:0.4rem;display:inline-flex;align-items:center;gap:6px;
                      padding:0.45rem 0.9rem;background:#25D366;color:white;text-decoration:none;
                      border-radius:6px;font-size:0.8rem;font-weight:600;
                      box-shadow:0 2px 6px rgba(37,211,102,.3);transition:background .2s;"
               onmouseover="this.style.background='#1ebe5e'" onmouseout="this.style.background='#25D366'">
              <i class="fab fa-whatsapp" style="font-size:1rem;"></i> Compartir
            </a>

            <!-- Link directo al espacio -->
            <a href="<?= htmlspecialchars($salePublicUrl) ?>" target="_blank"
               title="Ver espacio público"
               style="margin-top:0.4rem;display:inline-flex;align-items:center;gap:5px;
                      padding:0.45rem 0.75rem;background:#e0f2fe;color:#0369a1;text-decoration:none;
                      border-radius:6px;font-size:0.8rem;font-weight:600;border:1px solid #bae6fd;">
              <i class="fas fa-external-link-alt"></i> Ver
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      
      <?php if (empty($sales)): ?>
        <tr>
          <td colspan="6">
            <div class="empty-state">
              <div class="empty-state-icon">
                <i class="fas fa-store-slash"></i>
              </div>
              <h4>No tienes espacios creados aún</h4>
              <p>Crea tu primer espacio de venta usando el formulario de arriba para comenzar a vender tus productos.</p>
            </div>
          </td>
        </tr>
      <?php endif; ?>
    </table>
  </div>
  
  <!-- Información adicional sobre ubicaciones -->
  <?php if (!empty($sales)): ?>
    <div class="info-box orange">
      <div class="info-box-icon">
        <i class="fas fa-truck"></i>
      </div>
      <div>
        <h4 style="margin-top: 0; margin-bottom: 1rem; color: #d35400; font-size: 1.1rem;">
          ¿Por qué configurar la ubicación de recogida?
        </h4>
        <ul style="margin: 0 0 1rem 0; padding-left: 1.5rem; line-height: 1.8;">
          <li><strong>Envíos por Uber:</strong> Tus clientes podrán elegir envío con conductor de Uber</li>
          <li><strong>Cotización automática:</strong> El sistema calcula el costo según la distancia</li>
          <li><strong>Más ventas:</strong> Ofrece más opciones de entrega a tus clientes</li>
          <li><strong>Tracking en tiempo real:</strong> Cliente y vendedor pueden rastrear el pedido</li>
        </ul>
        <p style="margin: 0; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
          <i class="fas fa-lightbulb"></i>
          <strong>Consejo:</strong> Configura la ubicación ahora para que esté lista cuando tu espacio se active.
        </p>
      </div>
    </div>
  <?php endif; ?>
</div>
</body>
</html>