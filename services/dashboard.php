<?php
// services/dashboard.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['agent_id']) || (int)$_SESSION['agent_id'] <= 0) {
    header('Location: login.php');
    exit;
}

$pdo       = db();
$agent_id  = (int)$_SESSION['agent_id'];
$agent_name = $_SESSION['agent_name'] ?? 'Usuario';

// Cargar servicios del proveedor
$stmt = $pdo->prepare("
    SELECT sl.*, c.name AS category_name, lp.name AS plan_name, lp.duration_days
    FROM service_listings sl
    LEFT JOIN categories c ON c.id = sl.category_id
    LEFT JOIN listing_pricing lp ON lp.id = sl.pricing_plan_id
    WHERE sl.agent_id = ?
    ORDER BY sl.created_at DESC
");
$stmt->execute([$agent_id]);
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$stats = ['total' => count($listings), 'active' => 0, 'pending' => 0, 'views' => 0];
foreach ($listings as $l) {
    if ((int)$l['is_active']) $stats['active']++;
    if ($l['payment_status'] === 'pending') $stats['pending']++;
    $stats['views'] += (int)$l['views_count'];
}

// Categorías disponibles para el badge
function catShort($name) {
    return preg_replace('/^SERV:\s*/', '', $name);
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Servicios — <?php echo APP_NAME; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    :root {
      --primary: #1a6b3a;
      --primary-dark: #104d28;
      --primary-light: #e8f5e9;
      --white: #fff;
      --dark: #1a1a1a;
      --gray-700: #4a5568;
      --gray-600: #718096;
      --gray-300: #cbd5e0;
      --gray-100: #f7fafc;
      --bg: #f0f7f2;
      --danger: #e74c3c;
      --warning: #f39c12;
      --success: #27ae60;
      --info: #2980b9;
      --radius: 12px;
      --shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--dark); }

    /* Header */
    .header {
      background: var(--white);
      border-bottom: 1px solid var(--gray-300);
      padding: 1.25rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: var(--shadow);
      position: sticky;
      top: 0;
      z-index: 100;
    }
    .header-brand { display: flex; align-items: center; gap: 0.75rem; }
    .header-brand i { font-size: 1.5rem; color: var(--primary); }
    .header-brand h1 { font-size: 1.375rem; font-weight: 700; color: var(--dark); }
    .header-right { display: flex; align-items: center; gap: 1rem; }
    .user-pill { display: flex; align-items: center; gap: 0.5rem; background: var(--gray-100); padding: 0.5rem 1rem; border-radius: 50px; font-weight: 500; color: var(--gray-700); font-size: 0.9rem; }
    .user-pill i { color: var(--primary); }

    /* Buttons */
    .btn { padding: 0.65rem 1.25rem; background: var(--primary); color: var(--white); border: none; border-radius: var(--radius); font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.9375rem; transition: all 0.2s; }
    .btn:hover { background: var(--primary-dark); transform: translateY(-1px); }
    .btn-sm { padding: 0.5rem 1rem; font-size: 0.875rem; }
    .btn-secondary { background: var(--gray-100); color: var(--dark); border: 1px solid var(--gray-300); }
    .btn-secondary:hover { background: var(--gray-300); transform: none; }
    .btn-danger { background: #fff0f0; color: var(--danger); border: 1px solid #fcc; }
    .btn-danger:hover { background: var(--danger); color: var(--white); }
    .btn-warning { background: #fff8e1; color: var(--warning); border: 1px solid #ffe082; }
    .btn-warning:hover { background: var(--warning); color: var(--white); }

    /* Layout */
    .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }

    /* Alerts */
    .alert { padding: 1rem 1.5rem; border-radius: var(--radius); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border: 1px solid; }
    .alert i { font-size: 1.25rem; }
    .alert.success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
    .alert.info    { background: #d1ecf1; color: #0c5460;  border-color: #bee5eb; }
    .alert.warning { background: #fff3cd; color: #856404;  border-color: #ffeeba; }

    /* Stats grid */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
    .stat-card { background: var(--white); padding: 1.5rem; border-radius: var(--radius); box-shadow: var(--shadow); border-left: 4px solid var(--primary); }
    .stat-card.orange { border-left-color: var(--warning); }
    .stat-card.blue   { border-left-color: var(--info); }
    .stat-card.red    { border-left-color: var(--danger); }
    .stat-num { font-size: 2.25rem; font-weight: 700; color: var(--primary); line-height: 1; margin-bottom: 0.25rem; }
    .stat-card.orange .stat-num { color: var(--warning); }
    .stat-card.blue   .stat-num { color: var(--info); }
    .stat-card.red    .stat-num { color: var(--danger); }
    .stat-label { font-size: 0.875rem; color: var(--gray-600); font-weight: 500; }

    /* Section header */
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
    .section-header h2 { font-size: 1.375rem; font-weight: 700; }

    /* Listing cards */
    .listing-card {
      background: var(--white);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      margin-bottom: 1rem;
      overflow: hidden;
      border: 1px solid var(--gray-300);
      display: flex;
      transition: box-shadow 0.2s;
    }
    .listing-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.12); }
    .listing-thumb {
      width: 130px;
      min-height: 110px;
      object-fit: cover;
      flex-shrink: 0;
      background: var(--gray-100);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--gray-300);
      font-size: 2.5rem;
    }
    .listing-thumb img { width: 130px; height: 110px; object-fit: cover; }
    .listing-body { flex: 1; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
    .listing-info { flex: 1; min-width: 200px; }
    .listing-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.25rem; color: var(--dark); }
    .listing-meta { font-size: 0.85rem; color: var(--gray-600); display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 0.4rem; }
    .listing-meta span { display: flex; align-items: center; gap: 0.3rem; }
    .listing-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }

    /* Status badges */
    .badge { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.2rem 0.7rem; border-radius: 50px; font-size: 0.8rem; font-weight: 600; }
    .badge-green  { background: #d4edda; color: #155724; }
    .badge-yellow { background: #fff3cd; color: #856404; }
    .badge-gray   { background: var(--gray-100); color: var(--gray-700); }
    .badge-blue   { background: #d1ecf1; color: #0c5460; }

    /* Plans display */
    .plan-tag { font-size: 0.78rem; font-weight: 600; color: var(--primary); background: var(--primary-light); padding: 0.15rem 0.6rem; border-radius: 50px; }

    /* Empty state */
    .empty-state { text-align: center; padding: 4rem 2rem; background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow); }
    .empty-state .icon { font-size: 4rem; color: var(--gray-300); margin-bottom: 1.25rem; }
    .empty-state h3 { font-size: 1.375rem; margin-bottom: 0.5rem; }
    .empty-state p { color: var(--gray-600); margin-bottom: 1.5rem; }

    /* Payment notice card */
    .payment-notice { background: #fff8e1; border: 1px solid #ffe082; border-radius: var(--radius); padding: 1.25rem; margin-bottom: 1rem; }
    .payment-notice h4 { color: var(--warning); margin-bottom: 0.5rem; }
    .payment-methods { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 0.75rem; }
    .pm-item { background: var(--white); border: 1px solid var(--gray-300); border-radius: 8px; padding: 0.75rem 1rem; font-size: 0.9rem; flex: 1; min-width: 160px; }
    .pm-item strong { display: block; margin-bottom: 0.25rem; }
    .expire-tag { font-size: 0.78rem; color: var(--gray-600); }

    @media (max-width: 640px) {
      .header { flex-direction: column; gap: 1rem; align-items: flex-start; }
      .listing-card { flex-direction: column; }
      .listing-thumb { width: 100%; height: 160px; }
      .listing-thumb img { width: 100%; height: 160px; }
    }
  </style>
</head>
<body>

<div class="header">
  <div class="header-brand">
    <i class="fas fa-concierge-bell"></i>
    <h1>Panel de Servicios</h1>
  </div>
  <div class="header-right">
    <div class="user-pill">
      <i class="fas fa-user-circle"></i>
      <?php echo htmlspecialchars($agent_name); ?>
    </div>
    <a href="/real-estate/dashboard.php" class="btn btn-secondary btn-sm"><i class="fas fa-home"></i> Bienes Raíces</a>
    <a href="logout.php" class="btn btn-secondary btn-sm"><i class="fas fa-sign-out-alt"></i> Salir</a>
  </div>
</div>

<div class="container">

  <?php if (isset($_GET['welcome'])): ?>
    <div class="alert success"><i class="fas fa-check-circle"></i> <strong>¡Bienvenido!</strong> Tu cuenta fue creada con Google. Empezá a publicar tus servicios.</div>
  <?php elseif (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
    <div class="alert info"><i class="fas fa-info-circle"></i> Sesión iniciada correctamente.</div>
  <?php endif; ?>

  <?php if (isset($_GET['msg'])): ?>
    <?php if ($_GET['msg'] === 'created_free'): ?>
      <div class="alert success"><i class="fas fa-check-circle"></i> <strong>¡Servicio publicado!</strong> Tu publicación ya está visible en el sitio.</div>
    <?php elseif ($_GET['msg'] === 'created_pending'): ?>
      <div class="alert warning"><i class="fas fa-clock"></i> <strong>Servicio creado.</strong> Tu publicación está pendiente de pago. Una vez confirmado, será activada.</div>
    <?php elseif ($_GET['msg'] === 'updated'): ?>
      <div class="alert success"><i class="fas fa-check-circle"></i> <strong>¡Actualizado!</strong> Los cambios fueron guardados exitosamente.</div>
    <?php elseif ($_GET['msg'] === 'deleted'): ?>
      <div class="alert info"><i class="fas fa-trash"></i> Servicio eliminado.</div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- Estadísticas -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-num"><?php echo $stats['total']; ?></div>
      <div class="stat-label"><i class="fas fa-list"></i> Total Servicios</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?php echo $stats['active']; ?></div>
      <div class="stat-label"><i class="fas fa-check-circle"></i> Activos</div>
    </div>
    <div class="stat-card orange">
      <div class="stat-num"><?php echo $stats['pending']; ?></div>
      <div class="stat-label"><i class="fas fa-clock"></i> Pendientes de Pago</div>
    </div>
    <div class="stat-card blue">
      <div class="stat-num"><?php echo number_format($stats['views']); ?></div>
      <div class="stat-label"><i class="fas fa-eye"></i> Vistas Totales</div>
    </div>
  </div>

  <!-- Información de pago (si hay pendientes) -->
  <?php if ($stats['pending'] > 0): ?>
  <div class="payment-notice">
    <h4><i class="fas fa-credit-card"></i> Tenés <?php echo $stats['pending']; ?> publicación(es) pendiente(s) de pago</h4>
    <p>Una vez realizado el pago, enviá el comprobante a <strong><?php echo defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'info@compratica.com'; ?></strong> para activar tu publicación.</p>
    <div class="payment-methods">
      <div class="pm-item">
        <strong><i class="fas fa-mobile-alt"></i> SINPE Móvil</strong>
        <?php echo defined('SINPE_PHONE') ? SINPE_PHONE : '8888-0000'; ?><br>
        <small>A nombre de: CompraTica</small>
      </div>
      <div class="pm-item">
        <strong><i class="fab fa-paypal"></i> PayPal</strong>
        <?php echo defined('PAYPAL_EMAIL') ? PAYPAL_EMAIL : 'pagos@compratica.com'; ?><br>
        <small>Transferencia de amigos</small>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Lista de servicios -->
  <div class="section-header">
    <h2><i class="fas fa-briefcase"></i> Mis Servicios</h2>
    <a href="create-listing.php" class="btn"><i class="fas fa-plus"></i> Nuevo Servicio</a>
  </div>

  <?php if (empty($listings)): ?>
    <div class="empty-state">
      <div class="icon"><i class="fas fa-concierge-bell"></i></div>
      <h3>Aún no tenés servicios publicados</h3>
      <p>Publicá tu primer servicio para que clientes potenciales te encuentren.</p>
      <a href="create-listing.php" class="btn"><i class="fas fa-plus"></i> Publicar Primer Servicio</a>
    </div>

  <?php else: ?>
    <?php foreach ($listings as $l):
      $images     = json_decode($l['images'] ?? '[]', true);
      $firstImage = is_array($images) && count($images) > 0 ? $images[0] : null;
      $catName    = catShort($l['category_name'] ?? '');
      $isActive   = (int)$l['is_active'];
      $pStatus    = $l['payment_status'] ?? 'pending';
      $endDate    = $l['end_date'] ?? '';
      $daysLeft   = $endDate ? max(0, (int)ceil((strtotime($endDate) - time()) / 86400)) : 0;

      $priceText = '';
      if ((float)$l['price_from'] > 0) {
        $sym = $l['currency'] === 'USD' ? '$' : '₡';
        $priceText = 'Desde ' . $sym . number_format((float)$l['price_from'], 0, '.', ',');
        if ((float)$l['price_to'] > 0) {
          $priceText .= ' - ' . $sym . number_format((float)$l['price_to'], 0, '.', ',');
        }
        $typeLabel = ['hora'=>'/hora','proyecto'=>'/proyecto','mensual'=>'/mes','consulta'=>'/consulta','negociable'=>'(negociable)'];
        $priceText .= ' ' . ($typeLabel[$l['price_type']] ?? '');
      }
    ?>
    <div class="listing-card">
      <div class="listing-thumb">
        <?php if ($firstImage): ?>
          <img src="<?php echo htmlspecialchars($firstImage); ?>" alt="<?php echo htmlspecialchars($l['title']); ?>">
        <?php else: ?>
          <i class="fas fa-concierge-bell"></i>
        <?php endif; ?>
      </div>
      <div class="listing-body">
        <div class="listing-info">
          <div class="listing-title"><?php echo htmlspecialchars($l['title']); ?></div>
          <div class="listing-meta">
            <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($catName); ?></span>
            <?php if ($l['province']): ?><span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($l['province']); ?></span><?php endif; ?>
            <?php if ($priceText): ?><span><i class="fas fa-dollar-sign"></i> <?php echo htmlspecialchars($priceText); ?></span><?php endif; ?>
            <span><i class="fas fa-eye"></i> <?php echo (int)$l['views_count']; ?> vistas</span>
          </div>
          <div style="margin-top: 0.5rem; display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
            <?php if ($pStatus === 'free' || $pStatus === 'paid'): ?>
              <span class="badge badge-green"><i class="fas fa-check"></i> Activo</span>
            <?php elseif ($pStatus === 'pending'): ?>
              <span class="badge badge-yellow"><i class="fas fa-clock"></i> Pendiente de pago</span>
            <?php endif; ?>
            <?php if ($endDate && $pStatus !== 'pending'): ?>
              <span class="expire-tag"><i class="fas fa-calendar"></i>
                <?php echo $daysLeft > 0 ? "Vence en {$daysLeft} días" : 'Vencido'; ?>
              </span>
            <?php endif; ?>
            <span class="plan-tag"><?php echo htmlspecialchars($l['plan_name'] ?? ''); ?></span>
          </div>
        </div>
        <div class="listing-actions">
          <a href="edit-listing.php?id=<?php echo (int)$l['id']; ?>" class="btn btn-sm"><i class="fas fa-edit"></i> Editar</a>
          <a href="delete-listing.php?id=<?php echo (int)$l['id']; ?>"
             class="btn btn-sm btn-danger"
             onclick="return confirm('¿Eliminar este servicio?')">
            <i class="fas fa-trash"></i>
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>
</body>
</html>
