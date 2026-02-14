<?php
// real-estate/dashboard.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['agent_id']) || $_SESSION['agent_id'] <= 0) {
  header('Location: login.php');
  exit;
}

$pdo = db();
$agent_id = (int)$_SESSION['agent_id'];
$agent_name = $_SESSION['agent_name'] ?? 'Usuario';

$stmt = $pdo->prepare("
  SELECT * FROM real_estate_listings
  WHERE agent_id = ?
  ORDER BY created_at DESC
");
$stmt->execute([$agent_id]);
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = ['total' => count($listings), 'active' => 0, 'sale' => 0, 'rent' => 0];
foreach ($listings as $l) {
  if ($l['is_active']) $stats['active']++;
  if ($l['listing_type'] === 'sale') $stats['sale']++;
  else $stats['rent']++;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Bienes Raíces — <?php echo APP_NAME; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    :root { --primary: #002b7f; --white: #fff; --dark: #1a1a1a; --gray-700: #4a5568; --gray-300: #cbd5e0; --bg: #f8f9fa; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--dark); }
    .header { background: var(--white); border-bottom: 1px solid var(--gray-300); padding: 1.5rem 2rem; display: flex; justify-content: space-between; align-items: center; }
    .header h1 { font-size: 1.5rem; }
    .btn { padding: 0.75rem 1.5rem; background: var(--primary); color: var(--white); border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; }
    .btn:hover { background: #001d5c; }
    .btn-secondary { background: var(--gray-300); color: var(--dark); }
    .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .stat-card { background: var(--white); padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .stat-card h3 { font-size: 2rem; color: var(--primary); margin-bottom: 0.5rem; }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .section-header h2 { font-size: 1.5rem; }
    .listing-card { background: var(--white); padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; }
    .empty-state { text-align: center; padding: 4rem 2rem; color: #718096; }
    .empty-state i { font-size: 4rem; margin-bottom: 1rem; color: var(--gray-300); }
    .alert { padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; }
    .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert.info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
  </style>
</head>
<body>
  <div class="header">
    <h1><i class="fas fa-home"></i> Panel de Bienes Raíces</h1>
    <div>
      <span><?php echo htmlspecialchars($agent_name); ?></span>
      <a href="logout.php" class="btn btn-secondary">Salir</a>
    </div>
  </div>

  <div class="container">
    <?php if (isset($_GET['welcome'])): ?>
      <div class="alert success">
        <strong>¡Bienvenido!</strong> Tu cuenta ha sido creada exitosamente con Google. Ya podés empezar a publicar tus propiedades.
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['login']) && $_GET['login'] === 'success'): ?>
      <div class="alert info">
        <strong>Sesión iniciada</strong> correctamente.
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['msg'])): ?>
      <?php if ($_GET['msg'] === 'created_free'): ?>
        <div class="alert success">
          <strong>¡Propiedad publicada!</strong> Tu propiedad ha sido creada exitosamente y ya está visible en el sitio.
        </div>
      <?php elseif ($_GET['msg'] === 'created_pending'): ?>
        <div class="alert info">
          <strong>Propiedad creada</strong> — Tu publicación está pendiente de pago. Una vez confirmado el pago, será activada automáticamente.
        </div>
      <?php endif; ?>
    <?php endif; ?>
    <div class="stats-grid">
      <div class="stat-card">
        <h3><?php echo $stats['total']; ?></h3>
        <p>Total Propiedades</p>
      </div>
      <div class="stat-card">
        <h3><?php echo $stats['active']; ?></h3>
        <p>Activas</p>
      </div>
      <div class="stat-card">
        <h3><?php echo $stats['sale']; ?></h3>
        <p>En Venta</p>
      </div>
      <div class="stat-card">
        <h3><?php echo $stats['rent']; ?></h3>
        <p>En Alquiler</p>
      </div>
    </div>

    <div class="section-header">
      <h2>Mis Propiedades</h2>
      <a href="create-listing.php" class="btn">Nueva Propiedad</a>
    </div>

    <?php if (empty($listings)): ?>
      <div class="empty-state">
        <i class="fas fa-home"></i>
        <h3>No tenés propiedades todavía</h3>
        <p>Creá tu primera publicación para empezar.</p>
        <br>
        <a href="create-listing.php" class="btn">Crear Primera Propiedad</a>
      </div>
    <?php else: ?>
      <?php foreach ($listings as $l): ?>
        <div class="listing-card">
          <div>
            <h3><?php echo htmlspecialchars($l['title']); ?></h3>
            <p><?php echo $l['listing_type'] === 'sale' ? 'Venta' : 'Alquiler'; ?> - <?php echo $l['is_active'] ? 'Activa' : 'Inactiva'; ?></p>
          </div>
          <a href="edit-listing.php?id=<?php echo $l['id']; ?>" class="btn">Editar</a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</body>
</html>
