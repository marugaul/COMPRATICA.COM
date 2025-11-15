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
// Crear espacio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
  try {
    $title = trim($_POST['title'] ?? '');
    $start = trim($_POST['start_at'] ?? '');
    $end   = trim($_POST['end_at'] ?? '');
    if ($title === '' || $start === '' || $end === '') {
      throw new RuntimeException('Faltan datos obligatorios (título, inicio, fin).');
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
            (affiliate_id, title, cover_image, start_at, end_at, is_active, created_at, updated_at)
            VALUES
            (:aff, :title, :cover, :start, :end, 0, :now, :now)";
    $stmt = $pdo->prepare($sql);
    $now  = now_cr();
    $stmt->execute([
      ':aff'   => $aff_id,
      ':title' => $title,
      ':cover' => $img,
      ':start' => $start,
      ':end'   => $end,
      ':now'   => $now
    ]);
    $sale_id = (int)$pdo->lastInsertId();
    // Crear fee de activación (parametrizable)
    $fee_crc = (float)get_setting('SALE_FEE_CRC', 2000);
    $ex      = (float)(function_exists('get_exchange_rate') ? get_exchange_rate() : 1);
    if ($ex <= 0) $ex = 1;
    $amount_usd = $fee_crc / $ex;
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
          ':now' => now_cr() // también en CR
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* Estilos para botones de ubicación Uber */
    .btn-pickup {
      background: linear-gradient(135deg, #e67e22, #d35400);
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 4px;
      text-decoration: none;
      display: inline-block;
      margin: 0.25rem;
      font-size: 0.9rem;
      transition: all 0.3s ease;
    }
    .btn-pickup:hover {
      background: linear-gradient(135deg, #d35400, #c0392b);
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    .location-badge {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 600;
      margin-left: 0.5rem;
    }
    .badge-success {
      background: #27ae60;
      color: white;
    }
    .badge-warning {
      background: #f39c12;
      color: white;
    }
    .actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      align-items: center;
    }
    /* Tooltip para ubicación no configurada */
    .tooltip-wrapper {
      position: relative;
      display: inline-block;
    }
    .tooltip-text {
      visibility: hidden;
      width: 200px;
      background-color: #555;
      color: #fff;
      text-align: center;
      border-radius: 6px;
      padding: 8px;
      position: absolute;
      z-index: 1;
      bottom: 125%;
      left: 50%;
      margin-left: -100px;
      opacity: 0;
      transition: opacity 0.3s;
      font-size: 0.8rem;
    }
    .tooltip-wrapper:hover .tooltip-text {
      visibility: visible;
      opacity: 1;
    }
  </style>
</head>
<body>
<header class="header">
  <div class="logo">🛒 Mis espacios</div>
  <nav><a class="btn" href="dashboard.php">Panel</a></nav>
</header>
<div class="container">
  <?php if ($msg): ?>
    <div class="alert"><strong>Aviso:</strong> <?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  
  <!-- Mensaje informativo sobre ubicaciones -->
  <div class="card" style="background: #e7f3ff; border-left: 4px solid #3498db; margin-bottom: 1rem;">
    <p style="margin: 0; color: #2c3e50;">
      <i class="fas fa-info-circle"></i> 
      <strong>¿Sabías que?</strong> Puedes configurar la ubicación de recogida para cada espacio. 
      Esto permite que tus clientes elijan <strong>envío por Uber</strong> en el checkout.
    </p>
  </div>
  
  <div class="card">
    <h3>Crear nuevo espacio</h3>
    <form class="form" method="post" enctype="multipart/form-data">
      <label>Título <input class="input" name="title" required></label>
      <label>Portada <input class="input" type="file" name="cover" accept="image/*"></label>
      <label>Inicio <input class="input" type="datetime-local" name="start_at" required></label>
      <label>Fin <input class="input" type="datetime-local" name="end_at" required></label>
      <button class="btn primary" name="create" value="1">Crear (pagar activación)</button>
    </form>
  </div>
  
  <div class="card">
    <h3>Mis espacios</h3>
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
      ?>
        <tr>
          <td><?= htmlspecialchars($s['title']) ?></td>
          <td><?= htmlspecialchars($s['start_at']) ?></td>
          <td><?= htmlspecialchars($s['end_at']) ?></td>
          <td><?= !empty($s['is_active']) ? 'Sí' : 'No' ?></td>
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
            <?php
              // Si ya hay fee creado, ir por fee_id; si no, por sale_id
              $q = $pdo->prepare("SELECT id FROM sale_fees WHERE sale_id=? AND affiliate_id=? ORDER BY id DESC LIMIT 1");
              $q->execute([(int)$s['id'], $aff_id]);
              $existing_fee_id = (int)$q->fetchColumn();
              if ($existing_fee_id):
            ?>
              <a class="btn" href="sales_pay.php?fee_id=<?= (int)$existing_fee_id ?>">Pagar/Estado</a>
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
              <small style="display: block; color: #666; margin-top: 0.25rem; font-size: 0.8rem;">
                <i class="fas fa-map-pin"></i> 
                <?= htmlspecialchars($pickup_location['city'] ?? '') ?> - 
                <?= htmlspecialchars($pickup_location['contact_name'] ?? '') ?>
              </small>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      
      <?php if (empty($sales)): ?>
        <tr>
          <td colspan="6" style="text-align: center; padding: 2rem; color: #999;">
            <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
            No tienes espacios creados aún. ¡Crea tu primer espacio arriba!
          </td>
        </tr>
      <?php endif; ?>
    </table>
  </div>
  
  <!-- Información adicional sobre ubicaciones -->
  <?php if (!empty($sales)): ?>
    <div class="card" style="background: #fff4e6; border-left: 4px solid #f39c12;">
      <h4 style="margin-top: 0; color: #d35400;">
        <i class="fas fa-truck"></i> ¿Por qué configurar la ubicación de recogida?
      </h4>
      <ul style="margin: 0; padding-left: 1.5rem; line-height: 1.8;">
        <li><strong>Envíos por Uber:</strong> Tus clientes podrán elegir envío con conductor de Uber</li>
        <li><strong>Cotización automática:</strong> El sistema calcula el costo según la distancia</li>
        <li><strong>Más ventas:</strong> Ofrece más opciones de entrega a tus clientes</li>
        <li><strong>Tracking en tiempo real:</strong> Cliente y vendedor pueden rastrear el pedido</li>
      </ul>
      <p style="margin: 1rem 0 0 0; color: #856404; font-size: 0.9rem;">
        <i class="fas fa-lightbulb"></i> 
        <strong>Consejo:</strong> Configura la ubicación ahora para que esté lista cuando tu espacio se active.
      </p>
    </div>
  <?php endif; ?>
</div>
</body>
</html>