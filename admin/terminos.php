<?php
// admin/terminos.php – Gestión de Términos y Condiciones
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (!defined('APP_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    define('APP_URL', $scheme . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
}

require_login();
$pdo = db();

if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}

$types = [
    'cliente'       => ['label' => 'Clientes', 'icon' => 'fa-user', 'color' => '#3498db'],
    'vendedor'      => ['label' => 'Vendedores (Venta de Garaje)', 'icon' => 'fa-store', 'color' => '#e67e22'],
    'emprendedor'   => ['label' => 'Emprendedores', 'icon' => 'fa-rocket', 'color' => '#9b59b6'],
    'empleos'       => ['label' => 'Empleos y Servicios', 'icon' => 'fa-briefcase', 'color' => '#27ae60'],
    'servicios'     => ['label' => 'Proveedores de Servicios', 'icon' => 'fa-tools', 'color' => '#1abc9c'],
    'bienes_raices' => ['label' => 'Agentes Inmobiliarios', 'icon' => 'fa-home', 'color' => '#c0392b'],
];

$msg     = '';
$msgType = 'success';
$editing = null;

/* ── POST: guardar cambios ────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $type    = $_POST['type'] ?? '';
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $version = trim($_POST['version'] ?? '1.0');

        if (!isset($types[$type])) {
            $msg = 'Tipo de términos inválido.';
            $msgType = 'error';
        } elseif ($title === '' || $content === '') {
            $msg = 'El título y el contenido son obligatorios.';
            $msgType = 'error';
        } else {
            try {
                $existing = $pdo->prepare("SELECT id FROM terms_conditions WHERE type = ?");
                $existing->execute([$type]);
                $row = $existing->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    $pdo->prepare("
                        UPDATE terms_conditions
                        SET title = ?, content = ?, version = ?, updated_at = datetime('now')
                        WHERE type = ?
                    ")->execute([$title, $content, $version, $type]);
                    $msg = "✅ Términos de {$types[$type]['label']} actualizados (versión {$version}).";
                } else {
                    $pdo->prepare("
                        INSERT INTO terms_conditions (type, title, content, version, is_active)
                        VALUES (?, ?, ?, ?, 1)
                    ")->execute([$type, $title, $content, $version]);
                    $msg = "✅ Términos de {$types[$type]['label']} creados (versión {$version}).";
                }
            } catch (Throwable $e) {
                $msg = 'Error al guardar: ' . $e->getMessage();
                $msgType = 'error';
            }
        }
    }
}

/* ── Cargar términos existentes ──────────────────────────────────── */
$terms = [];
try {
    $rows = $pdo->query("SELECT * FROM terms_conditions ORDER BY type")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $terms[$r['type']] = $r;
    }
} catch (Throwable $e) {
    error_log('[terminos.php] Error cargando términos: ' . $e->getMessage());
}

/* ── ¿Modo edición? ──────────────────────────────────────────────── */
$editType = $_GET['edit'] ?? '';
if (isset($types[$editType])) {
    $editing = $editType;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Términos y Condiciones — Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    :root {
      --bg: #0f172a;
      --surface: #1e293b;
      --surface2: #273449;
      --border: rgba(255,255,255,0.1);
      --text: #e2e8f0;
      --text-muted: #94a3b8;
      --accent: #3b82f6;
      --success: #22c55e;
      --danger: #ef4444;
      --radius: 10px;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
    }

    /* ── NAV ── */
    .top-nav {
      background: linear-gradient(135deg, #1e3a5f 0%, #2c3e50 100%);
      padding: 1rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 0.75rem;
      border-bottom: 1px solid var(--border);
    }
    .top-nav .brand {
      font-size: 1.1rem;
      font-weight: 700;
      color: white;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .nav-links { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .nav-btn {
      display: inline-flex; align-items: center; gap: 0.4rem;
      padding: 0.5rem 0.875rem;
      background: rgba(255,255,255,0.1);
      color: white; text-decoration: none;
      border-radius: 6px; font-size: 0.825rem; font-weight: 500;
      border: 1px solid rgba(255,255,255,0.2);
      transition: background 0.2s;
    }
    .nav-btn:hover { background: rgba(255,255,255,0.2); }
    .nav-btn.active { background: rgba(59,130,246,0.5); border-color: rgba(59,130,246,0.7); }

    /* ── LAYOUT ── */
    .page { max-width: 1100px; margin: 2rem auto; padding: 0 1.5rem; }
    .page-header { margin-bottom: 2rem; }
    .page-header h1 { font-size: 1.75rem; font-weight: 700; color: white; }
    .page-header p { color: var(--text-muted); margin-top: 0.25rem; }

    /* ── ALERT ── */
    .alert {
      padding: 1rem 1.25rem;
      border-radius: var(--radius);
      margin-bottom: 1.5rem;
      font-weight: 500;
      display: flex; align-items: center; gap: 0.75rem;
    }
    .alert.success { background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.3); color: #86efac; }
    .alert.error   { background: rgba(239,68,68,0.15);  border: 1px solid rgba(239,68,68,0.3);  color: #fca5a5; }

    /* ── GRID ── */
    .types-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1.25rem;
      margin-bottom: 2.5rem;
    }
    .type-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.5rem;
      transition: border-color 0.2s;
    }
    .type-card:hover { border-color: rgba(255,255,255,0.25); }
    .type-card-header {
      display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;
    }
    .type-icon {
      width: 42px; height: 42px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem; color: white; flex-shrink: 0;
    }
    .type-card-header h3 { font-size: 0.95rem; font-weight: 600; color: white; }
    .type-meta { font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem; }
    .type-status {
      display: inline-flex; align-items: center; gap: 0.3rem;
      font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.6rem;
      border-radius: 999px; margin-bottom: 1rem;
    }
    .type-status.ok { background: rgba(34,197,94,0.15); color: #86efac; }
    .type-status.missing { background: rgba(239,68,68,0.15); color: #fca5a5; }
    .type-card-actions { display: flex; gap: 0.5rem; margin-top: 0.75rem; }
    .btn {
      display: inline-flex; align-items: center; gap: 0.4rem;
      padding: 0.5rem 1rem; border-radius: 7px;
      font-size: 0.85rem; font-weight: 500;
      text-decoration: none; cursor: pointer; border: none;
      transition: opacity 0.2s;
    }
    .btn:hover { opacity: 0.85; }
    .btn-primary { background: var(--accent); color: white; }
    .btn-secondary { background: var(--surface2); color: var(--text); border: 1px solid var(--border); }
    .btn-success { background: #16a34a; color: white; }

    /* ── EDITOR ── */
    .editor-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 2rem;
    }
    .editor-card h2 {
      font-size: 1.25rem; font-weight: 700; color: white; margin-bottom: 1.5rem;
      display: flex; align-items: center; gap: 0.5rem;
    }
    .form-group { margin-bottom: 1.25rem; }
    .form-group label {
      display: block; font-size: 0.875rem; font-weight: 500; color: var(--text-muted);
      margin-bottom: 0.4rem;
    }
    .form-control {
      width: 100%; padding: 0.75rem 1rem;
      background: var(--bg); border: 1px solid rgba(255,255,255,0.15);
      border-radius: 7px; color: var(--text); font-size: 0.9rem;
      font-family: inherit; transition: border-color 0.2s;
    }
    .form-control:focus { outline: none; border-color: var(--accent); }
    textarea.form-control { min-height: 420px; resize: vertical; font-family: 'Courier New', monospace; font-size: 0.85rem; line-height: 1.6; }
    .form-row { display: grid; grid-template-columns: 1fr 160px; gap: 1rem; }
    .form-hint { font-size: 0.775rem; color: var(--text-muted); margin-top: 0.3rem; }
    .form-actions { display: flex; gap: 0.75rem; margin-top: 1.5rem; }

    /* ── ACCEPTANCES TABLE ── */
    .section-title { font-size: 1rem; font-weight: 600; color: white; margin: 2rem 0 1rem; }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    th { background: var(--surface2); color: var(--text-muted); font-weight: 600; padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--border); }
    td { padding: 0.75rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); color: var(--text); }
    tr:hover td { background: rgba(255,255,255,0.03); }
    .badge {
      display: inline-block; padding: 0.15rem 0.5rem;
      border-radius: 4px; font-size: 0.75rem; font-weight: 600;
    }
    .badge-blue { background: rgba(59,130,246,0.2); color: #93c5fd; }
    .badge-green { background: rgba(34,197,94,0.2); color: #86efac; }
    .badge-orange { background: rgba(234,179,8,0.2); color: #fde047; }
    .badge-purple { background: rgba(168,85,247,0.2); color: #d8b4fe; }
    .badge-teal { background: rgba(20,184,166,0.2); color: #5eead4; }
    .badge-red { background: rgba(239,68,68,0.2); color: #fca5a5; }

    @media (max-width: 640px) {
      .page { padding: 0 1rem; }
      .form-row { grid-template-columns: 1fr; }
      .types-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<!-- NAV -->
<nav class="top-nav">
  <a href="dashboard.php" class="brand">
    <i class="fas fa-shield-alt"></i> Admin Panel
  </a>
  <div class="nav-links">
    <a class="nav-btn" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a class="nav-btn" href="affiliates.php"><i class="fas fa-store"></i> Afiliados</a>
    <a class="nav-btn active" href="terminos.php"><i class="fas fa-file-contract"></i> T&amp;C</a>
    <a class="nav-btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a>
  </div>
</nav>

<div class="page">
  <div class="page-header">
    <h1><i class="fas fa-file-contract"></i> Términos y Condiciones</h1>
    <p>Gestioná los términos y condiciones para cada tipo de usuario. Los usuarios los aceptan al registrarse.</p>
  </div>

  <?php if ($msg): ?>
    <div class="alert <?= $msgType ?>">
      <i class="fas <?= $msgType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
      <?= h($msg) ?>
    </div>
  <?php endif; ?>

  <?php if (!$editing): ?>
  <!-- ── GRILLA RESUMEN ── -->
  <div class="types-grid">
    <?php foreach ($types as $key => $info): ?>
      <?php $tc = $terms[$key] ?? null; ?>
      <div class="type-card">
        <div class="type-card-header">
          <div class="type-icon" style="background: <?= $info['color'] ?>">
            <i class="fas <?= $info['icon'] ?>"></i>
          </div>
          <div>
            <h3><?= h($info['label']) ?></h3>
            <?php if ($tc): ?>
              <div class="type-meta">v<?= h($tc['version']) ?> · <?= h(substr($tc['updated_at'] ?? $tc['created_at'] ?? '', 0, 10)) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($tc): ?>
          <span class="type-status ok"><i class="fas fa-check-circle"></i> Configurado</span>
        <?php else: ?>
          <span class="type-status missing"><i class="fas fa-exclamation-circle"></i> Sin configurar</span>
        <?php endif; ?>

        <?php if ($tc): ?>
          <div style="font-size:0.8rem;color:var(--text-muted);line-height:1.5;max-height:60px;overflow:hidden;">
            <?= h(mb_substr(strip_tags($tc['content']), 0, 120)) ?>…
          </div>
        <?php endif; ?>

        <div class="type-card-actions">
          <a href="?edit=<?= urlencode($key) ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> <?= $tc ? 'Editar' : 'Crear' ?>
          </a>
          <?php if ($tc): ?>
            <a href="?preview=<?= urlencode($key) ?>" class="btn btn-secondary" onclick="previewTC('<?= h($key) ?>'); return false;">
              <i class="fas fa-eye"></i> Ver
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- ── ÚLTIMAS ACEPTACIONES ── -->
  <?php
  $acceptances = [];
  try {
    $acceptances = $pdo->query("
      SELECT ta.*, ta.accepted_at
      FROM terms_acceptances ta
      ORDER BY ta.accepted_at DESC
      LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {}

  $typeBadge = [
    'cliente' => 'badge-blue', 'vendedor' => 'badge-orange',
    'emprendedor' => 'badge-purple', 'empleos' => 'badge-green',
    'servicios' => 'badge-teal', 'bienes_raices' => 'badge-red',
  ];
  ?>

  <?php if ($acceptances): ?>
    <div class="section-title"><i class="fas fa-history"></i> Últimas aceptaciones (50 recientes)</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Tipo</th>
            <th>Tabla</th>
            <th>ID Usuario</th>
            <th>Versión</th>
            <th>IP</th>
            <th>Fecha</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($acceptances as $a): ?>
            <tr>
              <td><?= (int)$a['id'] ?></td>
              <td><span class="badge <?= $typeBadge[$a['terms_type']] ?? 'badge-blue' ?>"><?= h($a['terms_type']) ?></span></td>
              <td><span style="font-size:0.78rem;color:var(--text-muted)"><?= h($a['user_table']) ?></span></td>
              <td><?= (int)$a['user_id'] ?></td>
              <td><?= h($a['version']) ?></td>
              <td style="font-size:0.78rem;color:var(--text-muted)"><?= h($a['ip_address'] ?? '—') ?></td>
              <td style="font-size:0.78rem"><?= h(substr($a['accepted_at'] ?? '', 0, 16)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div style="color:var(--text-muted);font-size:0.9rem;padding:1rem 0">
      <i class="fas fa-info-circle"></i> Todavía no hay aceptaciones registradas.
    </div>
  <?php endif; ?>

  <?php else: /* ── EDITOR ── */ ?>
    <?php $tc = $terms[$editing] ?? null; $info = $types[$editing]; ?>
    <a href="terminos.php" class="btn btn-secondary" style="margin-bottom:1.5rem;display:inline-flex">
      <i class="fas fa-arrow-left"></i> Volver al listado
    </a>

    <div class="editor-card">
      <h2>
        <span style="background:<?= $info['color'] ?>;width:32px;height:32px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-size:0.9rem">
          <i class="fas <?= $info['icon'] ?>"></i>
        </span>
        Términos para <?= h($info['label']) ?>
      </h2>

      <form method="POST">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="type" value="<?= h($editing) ?>">

        <div class="form-row">
          <div class="form-group" style="margin:0">
            <label>Título de los Términos</label>
            <input type="text" name="title" class="form-control"
              value="<?= h($tc['title'] ?? '') ?>" required
              placeholder="Ej: Términos y Condiciones para Clientes">
          </div>
          <div class="form-group" style="margin:0">
            <label>Versión</label>
            <input type="text" name="version" class="form-control"
              value="<?= h($tc['version'] ?? '1.0') ?>" required
              placeholder="1.0">
            <div class="form-hint">Cambiala para requerir re-aceptación.</div>
          </div>
        </div>

        <div class="form-group" style="margin-top:1.25rem">
          <label>Contenido (Markdown o texto plano)</label>
          <textarea name="content" class="form-control" required
            placeholder="Escribí aquí los términos y condiciones..."><?= h($tc['content'] ?? '') ?></textarea>
          <div class="form-hint">
            Podés usar Markdown: **negrita**, *cursiva*, ## Sección, - ítem de lista.
            El contenido se mostrará a los usuarios al registrarse.
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save"></i> Guardar Términos
          </button>
          <a href="terminos.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancelar
          </a>
        </div>
      </form>
    </div>

    <?php if ($tc): ?>
      <div style="margin-top:2rem">
        <div class="section-title"><i class="fas fa-eye"></i> Vista previa (tal como verá el usuario)</div>
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:2rem;max-height:500px;overflow-y:auto">
          <h3 style="color:white;margin-bottom:1rem"><?= h($tc['title']) ?></h3>
          <div id="tc-preview" style="color:var(--text);font-size:0.9rem;line-height:1.7;white-space:pre-wrap"><?= h($tc['content']) ?></div>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Modal preview -->
<div id="previewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:9999;overflow-y:auto;padding:2rem">
  <div style="background:#1e293b;border-radius:12px;max-width:700px;margin:0 auto;padding:2rem;position:relative">
    <button onclick="document.getElementById('previewModal').style.display='none'"
      style="position:absolute;top:1rem;right:1rem;background:none;border:none;color:#94a3b8;font-size:1.5rem;cursor:pointer">&times;</button>
    <h3 id="modalTitle" style="color:white;margin-bottom:1.5rem"></h3>
    <pre id="modalContent" style="color:#e2e8f0;font-family:inherit;font-size:0.85rem;line-height:1.7;white-space:pre-wrap;max-height:60vh;overflow-y:auto"></pre>
    <div style="margin-top:1rem;font-size:0.8rem;color:#94a3b8" id="modalMeta"></div>
  </div>
</div>

<script>
const termsData = <?php
  $out = [];
  foreach ($terms as $k => $v) {
    $out[$k] = ['title' => $v['title'], 'content' => $v['content'], 'version' => $v['version'], 'updated_at' => $v['updated_at'] ?? ''];
  }
  echo json_encode($out, JSON_UNESCAPED_UNICODE);
?>;

function previewTC(type) {
  const t = termsData[type];
  if (!t) return;
  document.getElementById('modalTitle').textContent = t.title;
  document.getElementById('modalContent').textContent = t.content;
  document.getElementById('modalMeta').textContent = 'Versión ' + t.version + ' · Actualizado: ' + (t.updated_at || '').substring(0, 10);
  document.getElementById('previewModal').style.display = 'block';
}
document.getElementById('previewModal').addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});
</script>
</body>
</html>
