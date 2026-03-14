<?php
/**
 * Verificación de usuarios de prueba - Área Emprendedoras
 * Acceder en: /sql/verificar-usuarios.php
 * ELIMINAR este archivo en producción cuando ya no se necesite.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

// --- 1. Verificar existencia de columnas live ---
$colCheck = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
$cols = array_column($colCheck, 'name');
$liveColsOk = in_array('is_live', $cols) && in_array('live_title', $cols) && in_array('live_link', $cols);

// --- 2. Usuarios de prueba ---
$stmt = $pdo->prepare("
    SELECT id, name, email, status, is_active,
           is_live, live_title,
           substr(password_hash, 1, 30) AS hash_preview,
           created_at
    FROM users
    WHERE email IN ('marisol.test@compratica.com','cafe.test@compratica.com')
    ORDER BY id
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 3. Suscripciones ---
$subs = [];
try {
    $stmt2 = $pdo->prepare("
        SELECT es.id, es.user_id, es.plan_id, es.status, u.email
        FROM entrepreneur_subscriptions es
        JOIN users u ON u.id = es.user_id
        WHERE u.email IN ('marisol.test@compratica.com','cafe.test@compratica.com')
    ");
    $stmt2->execute();
    $subs = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $subs = [['error' => $e->getMessage()]];
}

// --- 4. Productos ---
$prods = [];
try {
    $stmt3 = $pdo->prepare("
        SELECT ep.id, u.email, ep.name, ep.price, ep.stock, ep.is_active, ep.featured
        FROM entrepreneur_products ep
        JOIN users u ON u.id = ep.user_id
        WHERE u.email IN ('marisol.test@compratica.com','cafe.test@compratica.com')
        ORDER BY u.email, ep.id
    ");
    $stmt3->execute();
    $prods = $stmt3->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $prods = [['error' => $e->getMessage()]];
}

// --- 5. Test password_verify ---
$testHash = '$2y$12$2JTtMZ60bTquPy3u6kxJIe1ExOlkLApfyniAOwnYI7PPYM0fgGBAu';
$passOk   = password_verify('Compratica2024!', $testHash);

// --- Render ---
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Verificación Usuarios Prueba</title>
<style>
body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 24px; }
h2 { color: #a78bfa; margin-top: 28px; }
.ok  { color: #4ade80; font-weight: bold; }
.err { color: #f87171; font-weight: bold; }
table { border-collapse: collapse; width: 100%; margin-top: 10px; font-size: 0.85rem; }
th { background: #1e293b; color: #94a3b8; padding: 8px 12px; text-align: left; }
td { background: #1e293b; border-top: 1px solid #334155; padding: 7px 12px; }
tr:hover td { background: #263347; }
.badge-ok  { background: #166534; color: #bbf7d0; padding: 2px 8px; border-radius: 4px; }
.badge-err { background: #7f1d1d; color: #fecaca; padding: 2px 8px; border-radius: 4px; }
</style>
</head>
<body>

<h1 style="color:#c4b5fd">🔍 Verificación — Usuarios de Prueba Emprendedoras</h1>

<h2>1. Columnas live en tabla users</h2>
<?php if ($liveColsOk): ?>
  <span class="ok">✔ is_live, live_title, live_link existen</span>
<?php else: ?>
  <span class="err">✘ Faltan columnas live. Ejecuta sql/01-migracion-live.sql</span><br>
  Columnas actuales: <?= implode(', ', $cols) ?>
<?php endif; ?>

<h2>2. Verificación de contraseña hash</h2>
<p>password_verify('Compratica2024!', hash):
  <?php if ($passOk): ?>
    <span class="ok">✔ CORRECTA — el hash es válido</span>
  <?php else: ?>
    <span class="err">✘ FALLA — el hash NO coincide con 'Compratica2024!'</span>
  <?php endif; ?>
</p>

<h2>3. Usuarios insertados (<?= count($users) ?> encontrados)</h2>
<?php if (empty($users)): ?>
  <span class="err">✘ No se encontraron usuarios. Ejecuta sql/02-usuarios-prueba.sql</span>
<?php else: ?>
<table>
  <tr>
    <th>ID</th><th>Nombre</th><th>Email</th><th>status</th>
    <th>is_active</th><th>is_live</th><th>live_title</th>
    <th>hash (primeros 30 chars)</th><th>created_at</th>
  </tr>
  <?php foreach ($users as $u): ?>
  <tr>
    <td><?= $u['id'] ?></td>
    <td><?= htmlspecialchars($u['name']) ?></td>
    <td><?= htmlspecialchars($u['email']) ?></td>
    <td><?= $u['status'] === 'active' ? '<span class="badge-ok">active</span>' : '<span class="badge-err">'.htmlspecialchars($u['status']).'</span>' ?></td>
    <td><?= (int)$u['is_active'] === 1 ? '<span class="badge-ok">1</span>' : '<span class="badge-err">'.(int)$u['is_active'].'</span>' ?></td>
    <td><?= $u['is_live'] ?></td>
    <td><?= htmlspecialchars((string)$u['live_title']) ?></td>
    <td style="font-size:0.75rem"><?= htmlspecialchars($u['hash_preview']) ?>…</td>
    <td><?= htmlspecialchars($u['created_at']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>4. Suscripciones (<?= isset($subs[0]['error']) ? 'ERROR' : count($subs) ?> encontradas)</h2>
<?php if (isset($subs[0]['error'])): ?>
  <span class="err">✘ <?= htmlspecialchars($subs[0]['error']) ?></span>
<?php elseif (empty($subs)): ?>
  <span class="err">✘ Sin suscripciones. Ejecuta sql/02-usuarios-prueba.sql</span>
<?php else: ?>
<table>
  <tr><th>ID</th><th>user_id</th><th>email</th><th>plan_id</th><th>status</th></tr>
  <?php foreach ($subs as $s): ?>
  <tr>
    <td><?= $s['id'] ?></td><td><?= $s['user_id'] ?></td>
    <td><?= htmlspecialchars($s['email']) ?></td>
    <td><?= $s['plan_id'] ?></td>
    <td><?= $s['status'] === 'active' ? '<span class="badge-ok">active</span>' : htmlspecialchars($s['status']) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>5. Productos (<?= isset($prods[0]['error']) ? 'ERROR' : count($prods) ?> encontrados)</h2>
<?php if (isset($prods[0]['error'])): ?>
  <span class="err">✘ <?= htmlspecialchars($prods[0]['error']) ?></span>
<?php elseif (empty($prods)): ?>
  <span class="err">✘ Sin productos. Ejecuta sql/03-productos-prueba.sql</span>
<?php else: ?>
<table>
  <tr><th>ID</th><th>email dueña</th><th>nombre producto</th><th>precio</th><th>stock</th><th>activo</th><th>destacado</th></tr>
  <?php foreach ($prods as $p): ?>
  <tr>
    <td><?= $p['id'] ?></td>
    <td><?= htmlspecialchars($p['email']) ?></td>
    <td><?= htmlspecialchars($p['name']) ?></td>
    <td>₡<?= number_format((float)$p['price'], 0, ',', '.') ?></td>
    <td><?= $p['stock'] ?></td>
    <td><?= (int)$p['is_active'] ? '<span class="badge-ok">sí</span>' : '<span class="badge-err">no</span>' ?></td>
    <td><?= (int)$p['featured'] ? '⭐' : '—' ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<br><hr style="border-color:#334155">
<p style="color:#64748b;font-size:0.8rem">⚠️ Elimina este archivo cuando ya no lo necesites: <code>sql/verificar-usuarios.php</code></p>
</body>
</html>
