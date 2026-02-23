<?php
// jobs/dashboard.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Verificar sesión
if (!isset($_SESSION['employer_id']) || $_SESSION['employer_id'] <= 0) {
  header('Location: login.php');
  exit;
}

$pdo = db();
$employer_id = (int)$_SESSION['employer_id'];
$employer_name = $_SESSION['employer_name'] ?? 'Usuario';

// Mensajes de éxito/error
$success_message = '';
$error_message = '';

if (isset($_GET['success'])) {
  switch ($_GET['success']) {
    case '1':
      $success_message = 'Publicación creada exitosamente';
      break;
    case 'updated':
      $success_message = 'Publicación actualizada exitosamente';
      break;
    case 'deleted':
      $success_message = 'Publicación eliminada exitosamente';
      break;
  }
}

if (isset($_GET['error'])) {
  switch ($_GET['error']) {
    case 'not_found':
      $error_message = 'Publicación no encontrada';
      break;
    case 'delete_failed':
      $error_message = 'Error al eliminar la publicación';
      break;
    case 'invalid_id':
      $error_message = 'ID de publicación inválido';
      break;
  }
}

// Obtener publicaciones del empleador
$stmt = $pdo->prepare("
  SELECT * FROM job_listings
  WHERE employer_id = ?
  ORDER BY created_at DESC
");
$stmt->execute([$employer_id]);
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$stats = [
  'total' => count($listings),
  'active' => 0,
  'inactive' => 0,
  'jobs' => 0,
  'services' => 0
];

foreach ($listings as $listing) {
  if ($listing['is_active']) $stats['active']++;
  else $stats['inactive']++;

  if ($listing['listing_type'] === 'job') $stats['jobs']++;
  else $stats['services']++;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Empleos y Servicios — <?php echo APP_NAME; ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    :root {
      --primary: #27ae60;
      --primary-dark: #229954;
      --white: #ffffff;
      --dark: #1a1a1a;
      --gray-700: #4a5568;
      --gray-500: #718096;
      --gray-300: #cbd5e0;
      --gray-100: #f7fafc;
      --bg: #f8f9fa;
      --radius: 8px;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      color: var(--dark);
    }

    .header {
      background: var(--white);
      border-bottom: 1px solid var(--gray-300);
      padding: 1.5rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .header h1 {
      font-size: 1.5rem;
      color: var(--dark);
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .btn {
      padding: 0.75rem 1.5rem;
      background: var(--primary);
      color: var(--white);
      border: none;
      border-radius: var(--radius);
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.25s;
    }

    .btn:hover {
      background: var(--primary-dark);
    }

    .btn-secondary {
      background: var(--gray-300);
      color: var(--dark);
    }

    .btn-secondary:hover {
      background: var(--gray-500);
      color: var(--white);
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .stat-card {
      background: var(--white);
      padding: 1.5rem;
      border-radius: var(--radius);
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .stat-card h3 {
      font-size: 2rem;
      color: var(--primary);
      margin-bottom: 0.5rem;
    }

    .stat-card p {
      color: var(--gray-700);
      font-size: 0.95rem;
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
    }

    .section-header h2 {
      font-size: 1.5rem;
      color: var(--dark);
    }

    .listings-grid {
      display: grid;
      gap: 1.5rem;
    }

    .listing-card {
      background: var(--white);
      padding: 1.5rem;
      border-radius: var(--radius);
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .listing-info h3 {
      font-size: 1.25rem;
      margin-bottom: 0.5rem;
    }

    .listing-meta {
      display: flex;
      gap: 1rem;
      font-size: 0.9rem;
      color: var(--gray-700);
    }

    .badge {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
    }

    .badge.job {
      background: #e3f2fd;
      color: #1976d2;
    }

    .badge.service {
      background: #f3e5f5;
      color: #7b1fa2;
    }

    .badge.active {
      background: #e8f5e9;
      color: #2e7d32;
    }

    .badge.inactive {
      background: #ffebee;
      color: #c62828;
    }

    .empty-state {
      text-align: center;
      padding: 4rem 2rem;
      color: var(--gray-500);
    }

    .empty-state i {
      font-size: 4rem;
      margin-bottom: 1rem;
      color: var(--gray-300);
    }

    .alert {
      padding: 1rem 1.5rem;
      border-radius: var(--radius);
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .alert-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .alert-error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
  </style>
</head>
<body>
  <div class="header">
    <h1><i class="fas fa-briefcase"></i> Panel de Empleos y Servicios</h1>
    <div class="user-info">
      <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($employer_name); ?></span>
      <a href="logout.php" class="btn btn-secondary"><i class="fas fa-sign-out-alt"></i> Salir</a>
    </div>
  </div>

  <div class="container">
    <?php if ($success_message): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success_message); ?>
      </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
      <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error_message); ?>
      </div>
    <?php endif; ?>

    <div class="stats-grid">
      <div class="stat-card">
        <h3><?php echo $stats['total']; ?></h3>
        <p><i class="fas fa-list"></i> Total Publicaciones</p>
      </div>
      <div class="stat-card">
        <h3><?php echo $stats['active']; ?></h3>
        <p><i class="fas fa-check-circle"></i> Activas</p>
      </div>
      <div class="stat-card">
        <h3><?php echo $stats['jobs']; ?></h3>
        <p><i class="fas fa-briefcase"></i> Empleos</p>
      </div>
      <div class="stat-card">
        <h3><?php echo $stats['services']; ?></h3>
        <p><i class="fas fa-tools"></i> Servicios</p>
      </div>
    </div>

    <div class="section-header">
      <h2>Mis Publicaciones</h2>
      <a href="create-listing.php" class="btn">
        <i class="fas fa-plus"></i>
        Nueva Publicación
      </a>
    </div>

    <?php if (empty($listings)): ?>
      <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <h3>No tenés publicaciones todavía</h3>
        <p>Creá tu primera publicación de empleo o servicio para empezar.</p>
        <br>
        <a href="create-listing.php" class="btn">
          <i class="fas fa-plus"></i>
          Crear Primera Publicación
        </a>
      </div>
    <?php else: ?>
      <div class="listings-grid">
        <?php foreach ($listings as $listing): ?>
          <div class="listing-card">
            <div class="listing-info">
              <h3><?php echo htmlspecialchars($listing['title']); ?></h3>
              <div class="listing-meta">
                <span class="badge <?php echo $listing['listing_type']; ?>">
                  <?php echo $listing['listing_type'] === 'job' ? 'Empleo' : 'Servicio'; ?>
                </span>
                <span class="badge <?php echo $listing['is_active'] ? 'active' : 'inactive'; ?>">
                  <?php echo $listing['is_active'] ? 'Activa' : 'Inactiva'; ?>
                </span>
                <span><i class="fas fa-eye"></i> <?php echo $listing['views_count'] ?? 0; ?> vistas</span>
                <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($listing['created_at'])); ?></span>
              </div>
            </div>
            <div>
              <a href="edit-listing.php?id=<?php echo $listing['id']; ?>" class="btn btn-secondary">
                <i class="fas fa-edit"></i>
                Editar
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
