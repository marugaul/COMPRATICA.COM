<?php
/**
 * admin/cleanup_bots.php
 * One-time maintenance: delete users whose phone starts with +1 (US bots).
 * Requires admin login. Shows preview before executing.
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();
$pdo = db();

$deleted = 0;
$msg     = '';
$error   = '';

// ── Execute delete ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'BORRAR') {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE TRIM(phone) LIKE '+1%'");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        $msg = "Se eliminaron $deleted usuario(s) con teléfono +1.";
    } catch (Throwable $e) {
        $error = 'Error al eliminar: ' . $e->getMessage();
    }
}

// ── Preview ────────────────────────────────────────────────────────────────────
$preview = $pdo->query("
    SELECT id, name, email, phone, created_at
    FROM users
    WHERE TRIM(phone) LIKE '+1%'
    ORDER BY id DESC
    LIMIT 500
")->fetchAll(PDO::FETCH_ASSOC);

$total = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE TRIM(phone) LIKE '+1%'")->fetchColumn();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Limpieza Bots (+1) — Admin</title>
  <link rel="stylesheet" href="/assets/fontawesome-css/all.min.css">
  <style>
    body{font-family:'Segoe UI',sans-serif;background:#f9fafb;margin:0;padding:2rem;}
    .wrap{max-width:1100px;margin:0 auto;}
    h1{color:#dc2626;margin-bottom:.5rem;}
    .info{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:1.25rem 1.5rem;margin-bottom:1.5rem;}
    .info p{margin:.3rem 0;color:#374151;}
    .info strong{color:#111;}
    form.confirm{background:#fef2f2;border:2px solid #fca5a5;border-radius:10px;padding:1.25rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;}
    form.confirm p{margin:0;color:#7f1d1d;font-weight:600;}
    input[name=confirm]{padding:.5rem .75rem;border:1.5px solid #fca5a5;border-radius:7px;font-size:.9rem;width:160px;}
    button.del{background:#dc2626;color:white;border:none;padding:.6rem 1.4rem;border-radius:7px;font-weight:700;cursor:pointer;font-size:.9rem;}
    button.del:hover{background:#b91c1c;}
    .msg-ok{background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:1rem 1.25rem;border-radius:8px;margin-bottom:1rem;font-weight:600;}
    .msg-err{background:#fee2e2;border:1px solid #fca5a5;color:#7f1d1d;padding:1rem 1.25rem;border-radius:8px;margin-bottom:1rem;font-weight:600;}
    table{width:100%;border-collapse:collapse;font-size:.85rem;background:white;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;}
    thead th{background:#dc2626;color:white;padding:.6rem .9rem;text-align:left;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;}
    tbody td{padding:.55rem .9rem;border-bottom:1px solid #f3f4f6;color:#374151;}
    tbody tr:hover{background:#fef2f2;}
    .back{display:inline-flex;align-items:center;gap:.4rem;color:#4b5563;text-decoration:none;font-size:.875rem;margin-bottom:1.25rem;}
    .back:hover{color:#111;}
  </style>
</head>
<body>
<div class="wrap">
  <a class="back" href="clientes.php"><i class="fas fa-arrow-left"></i> Volver a Clientes</a>
  <h1><i class="fas fa-robot"></i> Limpieza de Bots — teléfonos +1</h1>

  <?php if ($msg): ?>
    <div class="msg-ok"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="msg-err"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="info">
    <p><strong>Criterio:</strong> usuarios cuyo teléfono comienza con <code>+1</code> (prefijo de EE.UU. / Canadá — registros de bots).</p>
    <p><strong>Registros a eliminar:</strong> <span style="color:#dc2626;font-size:1.3rem;font-weight:800;"><?= $total ?></span></p>
    <p style="color:#6b7280;font-size:.82rem;">Esta acción es <strong>irreversible</strong>. Verificá la lista abajo antes de confirmar.</p>
  </div>

  <?php if ($total > 0 && !$deleted): ?>
  <form method="post" class="confirm">
    <p><i class="fas fa-exclamation-triangle"></i> Para confirmar escribí <strong>BORRAR</strong> y presioná el botón:</p>
    <input type="text" name="confirm" placeholder="BORRAR" autocomplete="off">
    <button type="submit" class="del"><i class="fas fa-trash"></i> Eliminar <?= $total ?> bots</button>
  </form>
  <?php elseif ($total === 0): ?>
    <div class="msg-ok"><i class="fas fa-check-circle"></i> No quedan usuarios con teléfono +1 en la base de datos.</div>
  <?php endif; ?>

  <?php if (!empty($preview)): ?>
  <table>
    <thead>
      <tr>
        <th>ID</th><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Registro</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($preview as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td><?= htmlspecialchars($r['email']) ?></td>
        <td style="color:#dc2626;font-weight:600"><?= htmlspecialchars($r['phone']) ?></td>
        <td style="color:#9ca3af;font-size:.78rem"><?= htmlspecialchars($r['created_at'] ?? '—') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if ($total > 500): ?>
      <tr><td colspan="5" style="text-align:center;color:#9ca3af;font-style:italic;padding:1rem">
        … y <?= $total - 500 ?> más (se muestran los primeros 500)
      </td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
</body>
</html>
