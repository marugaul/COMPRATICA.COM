<?php
// terminos-condiciones.php – Vista pública de Términos y Condiciones
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$typeMap = [
    'cliente'       => 'Clientes',
    'vendedor'      => 'Vendedores',
    'emprendedor'   => 'Emprendedores',
    'empleos'       => 'Empleos y Servicios',
    'servicios'     => 'Proveedores de Servicios',
    'bienes_raices' => 'Agentes Inmobiliarios',
];

$type = $_GET['type'] ?? 'cliente';
if (!isset($typeMap[$type])) $type = 'cliente';

$pdo = db();
$tc = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM terms_conditions WHERE type = ? AND is_active = 1");
    $stmt->execute([$type]);
    $tc = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$title = $tc['title'] ?? ('Términos y Condiciones — ' . $typeMap[$type]);
$content = $tc['content'] ?? 'Los términos y condiciones para este servicio serán publicados próximamente.';
$version = $tc['version'] ?? '1.0';
$updated = $tc['updated_at'] ?? date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: #f8f9fa;
      color: #2d3748;
      line-height: 1.7;
    }
    .top-bar {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      padding: 1rem 2rem;
      display: flex; align-items: center; justify-content: space-between;
    }
    .top-bar a { color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; font-weight: 600; }
    .top-bar .back-btn {
      background: rgba(255,255,255,0.15); padding: 0.5rem 1rem;
      border-radius: 6px; font-size: 0.875rem; border: 1px solid rgba(255,255,255,0.3);
      transition: background 0.2s;
    }
    .top-bar .back-btn:hover { background: rgba(255,255,255,0.25); }
    .container { max-width: 800px; margin: 2.5rem auto; padding: 0 1.5rem; }
    .doc-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      overflow: hidden;
    }
    .doc-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      padding: 2.5rem;
      color: white;
    }
    .doc-header h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem; }
    .doc-meta { font-size: 0.875rem; opacity: 0.85; display: flex; gap: 1.5rem; flex-wrap: wrap; margin-top: 0.75rem; }
    .doc-body {
      padding: 2.5rem;
      font-size: 0.9375rem;
      line-height: 1.8;
      color: #374151;
      white-space: pre-wrap;
      word-break: break-word;
    }
    .doc-footer {
      padding: 1.5rem 2.5rem;
      background: #f9fafb;
      border-top: 1px solid #e5e7eb;
      font-size: 0.825rem;
      color: #6b7280;
      display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;
    }
    .type-tabs {
      display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem;
    }
    .type-tab {
      padding: 0.4rem 0.875rem;
      border-radius: 999px;
      font-size: 0.8rem; font-weight: 500;
      text-decoration: none;
      background: white; color: #6b7280;
      border: 1px solid #e5e7eb;
      transition: all 0.2s;
    }
    .type-tab:hover { border-color: #667eea; color: #667eea; }
    .type-tab.active { background: #667eea; color: white; border-color: #667eea; }
    @media (max-width: 640px) {
      .container { padding: 0 1rem; margin: 1.5rem auto; }
      .doc-header, .doc-body { padding: 1.5rem; }
    }
  </style>
</head>
<body>

<div class="top-bar">
  <a href="/" class="brand"><i class="fas fa-store"></i> <?= htmlspecialchars(APP_NAME) ?></a>
  <a href="javascript:history.back()" class="back-btn"><i class="fas fa-arrow-left"></i> Volver</a>
</div>

<div class="container">
  <div class="type-tabs">
    <?php foreach ($typeMap as $k => $label): ?>
      <a href="?type=<?= urlencode($k) ?>" class="type-tab <?= $k === $type ? 'active' : '' ?>"><?= htmlspecialchars($label) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="doc-card">
    <div class="doc-header">
      <h1><?= htmlspecialchars($title) ?></h1>
      <div class="doc-meta">
        <span><i class="fas fa-tag"></i> Versión <?= htmlspecialchars($version) ?></span>
        <span><i class="fas fa-calendar-alt"></i> Última actualización: <?= htmlspecialchars(substr($updated, 0, 10)) ?></span>
        <span><i class="fas fa-map-marker-alt"></i> República de Costa Rica</span>
      </div>
    </div>
    <div class="doc-body"><?= htmlspecialchars($content) ?></div>
    <div class="doc-footer">
      <span><?= htmlspecialchars(APP_NAME) ?> · Todos los derechos reservados</span>
      <span>v<?= htmlspecialchars($version) ?></span>
    </div>
  </div>
</div>

</body>
</html>
